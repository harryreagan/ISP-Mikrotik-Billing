<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\HotspotSale;
use App\Models\MpesaStkTransaction;
use App\Models\PackageList;
use App\Models\Router;
use App\Services\MikrotikApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CaptivePortalApiController extends Controller
{
    protected function withCors(JsonResponse $response): JsonResponse
    {
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With, Authorization');
    }

    /**
     * Returning User Lookup:
     * Handles phone number, voucher code, or M-Pesa transaction code.
     * Resumes remaining time/data; if expired, guides user to purchase packages.
     */
    public function returningUser(Request $request): JsonResponse
    {
        $rawInput = trim((string) $request->input('code', ''));
        $mac      = trim((string) $request->input('mac', ''));
        $routerId = (int) $request->input('router_id', 0);
        $tenantId = (int) $request->input('tenant_id', 1);

        if (empty($rawInput) && empty($mac)) {
            return $this->withCors(response()->json([
                'status'  => 'error',
                'message' => 'Please enter your Mobile Number, Voucher Code, or M-Pesa Receipt Code.',
            ], 422));
        }

        try {
            // Check 1: Input is a Phone Number
            $cleanDigits = preg_replace('/[^\d]/', '', $rawInput);
            if (strlen($cleanDigits) >= 9 && strlen($cleanDigits) <= 13 && (str_starts_with($rawInput, '0') || str_starts_with($rawInput, '254') || str_starts_with($rawInput, '7') || str_starts_with($rawInput, '1'))) {
                $normPhone = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($rawInput) : $rawInput;

                // Find latest successful transaction for this phone
                $tx = MpesaStkTransaction::withoutGlobalScopes()
                    ->where('status', 'completed')
                    ->where(function ($q) use ($rawInput, $normPhone) {
                        $q->where('phone', $normPhone)->orWhere('phone', $rawInput);
                    })
                    ->when($tenantId > 0, fn($q) => $q->where('tenant_id', $tenantId))
                    ->latest('id')
                    ->first();

                if ($tx) {
                    $receipt = strtoupper($tx->mpesa_receipt_number ?: $tx->checkout_request_id);
                    $durationHours = $this->resolveDurationHours($tx);

                    $createdAt = Carbon::parse($tx->updated_at ?: $tx->created_at)->timezone('Africa/Nairobi');
                    $expiresAt = (clone $createdAt)->addHours($durationHours);

                    if (now()->timezone('Africa/Nairobi')->lt($expiresAt)) {
                        $remainingMin = now()->timezone('Africa/Nairobi')->diffInMinutes($expiresAt);
                        $remainingHuman = $remainingMin > 60
                            ? round($remainingMin / 60, 1) . ' hours remaining'
                            : $remainingMin . ' minutes remaining';

                        // Ensure user on router
                        $this->ensureHotspotUserOnRouter($routerId, $receipt, $durationHours);

                        return $this->withCors(response()->json([
                            'status'          => 'active',
                            'username'        => $receipt,
                            'password'        => $receipt,
                            'voucher_code'    => $receipt,
                            'remaining_human' => $remainingHuman,
                            'message'         => "Welcome back! Your {$durationHours}h package is active ({$remainingHuman}). Connecting...",
                        ]));
                    } else {
                        return $this->withCors(response()->json([
                            'status'       => 'expired',
                            'message'      => "Your previous package expired at {$expiresAt->format('H:i, d M')}. Please choose a package below to renew.",
                            'expired_at'   => $expiresAt->toDateTimeString(),
                            'voucher_code' => $receipt,
                        ]));
                    }
                }
            }

            // Check 2: Input is an M-Pesa Receipt Code or Voucher Code
            $upperCode = strtoupper($rawInput);
            $txByCode = MpesaStkTransaction::withoutGlobalScopes()
                ->where('status', 'completed')
                ->where('mpesa_receipt_number', $upperCode)
                ->latest('id')
                ->first();

            if ($txByCode) {
                $durationHours = $this->resolveDurationHours($txByCode);
                $createdAt = Carbon::parse($txByCode->updated_at ?: $txByCode->created_at)->timezone('Africa/Nairobi');
                $expiresAt = (clone $createdAt)->addHours($durationHours);

                if (now()->timezone('Africa/Nairobi')->lt($expiresAt)) {
                    $remainingMin = now()->timezone('Africa/Nairobi')->diffInMinutes($expiresAt);
                    $remainingHuman = $remainingMin > 60
                        ? round($remainingMin / 60, 1) . ' hours remaining'
                        : $remainingMin . ' minutes remaining';

                    $this->ensureHotspotUserOnRouter($routerId, $upperCode, $durationHours);

                    return $this->withCors(response()->json([
                        'status'          => 'active',
                        'username'        => $upperCode,
                        'password'        => $upperCode,
                        'voucher_code'    => $upperCode,
                        'remaining_human' => $remainingHuman,
                        'message'         => "Valid M-Pesa Receipt! {$remainingHuman}. Connecting...",
                    ]));
                } else {
                    return $this->withCors(response()->json([
                        'status'     => 'expired',
                        'message'    => "M-Pesa receipt {$upperCode} expired on {$expiresAt->format('d M Y')}. Please select a package below to reconnect.",
                        'expired_at' => $expiresAt->toDateTimeString(),
                    ]));
                }
            }

            // Check 3: Check hotspot_sales table directly for this voucher
            if (DB::getSchemaBuilder()->hasTable('hotspot_sales')) {
                $sale = DB::table('hotspot_sales')
                    ->where(function ($q) use ($upperCode, $rawInput) {
                        $q->where('voucher_code', $upperCode)->orWhere('username', $rawInput);
                    })
                    ->latest('id')
                    ->first();

                if ($sale) {
                    $createdAt = Carbon::parse($sale->created_at ?: $sale->sale_date)->timezone('Africa/Nairobi');
                    $expiresAt = (clone $createdAt)->addHours(24); // default 24h if unknown

                    if (now()->timezone('Africa/Nairobi')->lt($expiresAt)) {
                        $this->ensureHotspotUserOnRouter($routerId, $upperCode, 24);
                        return $this->withCors(response()->json([
                            'status'          => 'active',
                            'username'        => $upperCode,
                            'password'        => $upperCode,
                            'voucher_code'    => $upperCode,
                            'remaining_human' => 'Active',
                            'message'         => "Active package found! Connecting...",
                        ]));
                    }
                }
            }

            return $this->withCors(response()->json([
                'status'  => 'not_found',
                'message' => 'No active voucher or payment found for this entry. Please check the code or select a new package.',
            ]));

        } catch (\Throwable $e) {
            Log::error('[CaptivePortalApiController] returningUser exception: ' . $e->getMessage());
            return $this->withCors(response()->json([
                'status'  => 'error',
                'message' => 'Service temporarily unavailable. You can enter your voucher directly below.',
            ]));
        }
    }

    /**
     * Check if a client MAC address has an active session.
     */
    public function checkSession(Request $request): JsonResponse
    {
        $mac      = trim((string) $request->input('mac', ''));
        $routerId = (int) $request->input('router_id', 0);
        $tenantId = (int) $request->input('tenant_id', 1);

        if (empty($mac)) {
            return $this->withCors(response()->json(['status' => 'none']));
        }

        try {
            // Find recent transaction matching this MAC within validity window
            $tx = MpesaStkTransaction::withoutGlobalScopes()
                ->where('status', 'completed')
                ->where('mac', $mac)
                ->when($tenantId > 0, fn($q) => $q->where('tenant_id', $tenantId))
                ->latest('id')
                ->first();

            if ($tx) {
                $durationHours = $this->resolveDurationHours($tx);
                $createdAt = Carbon::parse($tx->updated_at ?: $tx->created_at)->timezone('Africa/Nairobi');
                $expiresAt = (clone $createdAt)->addHours($durationHours);

                if (now()->timezone('Africa/Nairobi')->lt($expiresAt)) {
                    $receipt = strtoupper($tx->mpesa_receipt_number ?: $tx->checkout_request_id);
                    $this->ensureHotspotUserOnRouter($routerId, $receipt, $durationHours);

                    return $this->withCors(response()->json([
                        'status'          => 'active',
                        'username'        => $receipt,
                        'password'        => $receipt,
                        'voucher_code'    => $receipt,
                        'remaining_human' => now()->timezone('Africa/Nairobi')->diffInMinutes($expiresAt) . ' minutes remaining',
                        'message'         => 'Welcome back! Your package is active. Reconnecting...',
                    ]));
                }
            }

            return $this->withCors(response()->json(['status' => 'none']));
        } catch (\Throwable $e) {
            return $this->withCors(response()->json(['status' => 'none']));
        }
    }

    /**
     * Helper to resolve package duration in hours from transaction metadata.
     */
    protected function resolveDurationHours(MpesaStkTransaction $tx): int
    {
        $meta = is_array($tx->raw_callback) ? $tx->raw_callback : [];
        $packageId = $meta['package_id'] ?? null;

        if ($packageId) {
            $plan = PackageList::withoutGlobalScopes()->find((int) $packageId);
            if ($plan) {
                $hours = (int) ($plan->duration_hours ?? 0);
                if ($hours > 0) return $hours;

                $days = (int) ($plan->duration_days ?? $plan->validity ?? 0);
                if ($days > 0) return $days * 24;

                $name = strtolower($plan->package_name ?: ($plan->name ?: ''));
                if (str_contains($name, 'hour') || str_contains($name, '1h') || str_contains($name, '2h') || str_contains($name, '3h')) {
                    preg_match('/(\d+)\s*(?:hour|hr|h)/i', $name, $m);
                    if (!empty($m[1])) return (int) $m[1];
                }
                if (str_contains($name, 'day') || str_contains($name, '24h')) return 24;
                if (str_contains($name, 'week') || str_contains($name, '7d')) return 168;
                if (str_contains($name, 'month') || str_contains($name, '30d')) return 720;
            }
        }

        // Amount based heuristic fallback (KES 10-20 = 1-3 hours, KES 50 = 24h, KES 250 = 7 days)
        $amt = (float) $tx->amount;
        if ($amt <= 15) return 2;
        if ($amt <= 30) return 6;
        if ($amt <= 60) return 24;
        if ($amt <= 300) return 168;
        return 720;
    }

    /**
     * Ensure Hotspot User exists on MikroTik Router with correct profile and uptime limits.
     */
    protected function ensureHotspotUserOnRouter(int $routerId, string $username, int $durationHours): void
    {
        if ($routerId <= 0) return;

        $router = Router::withoutGlobalScopes()->find($routerId);
        if (!$router) return;

        $host = $router->vpn_ip ?: $router->ip;
        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');
        $pass = '';

        $keys = [
            'base64:OsvBUcJog9u3L7RHpAXOV3lAVumKIyCIOscxYwKXufQ=',
            'base64:NSLTh+nKIi546KF1WfENeIVjjBvu0SSvE2kgyiFu7CE=',
            'base64:motZaNhPAm6uv5uffwL6kbrJdtOu3qgrdgjtXarDoaU=',
            'base64:CGygtTkIhnvb+mwr8Mv5j5DjDkgmbq9ej3mmKwHcS5g=',
        ];

        try {
            $pass = Crypt::decryptString($router->api_password ?: $router->password);
        } catch (\Throwable $e) {
            foreach ($keys as $k) {
                try {
                    $rawKey = base64_decode(substr($k, 7));
                    $enc = new \Illuminate\Encryption\Encrypter($rawKey, 'AES-256-CBC');
                    $pass = $enc->decryptString($router->api_password ?: $router->password);
                    break;
                } catch (\Throwable $e2) {}
            }
            if (empty($pass)) {
                $pass = $router->api_password ?: $router->password;
            }
        }

        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 3);
            $existing = $api->executeCommand('/ip/hotspot/user/print', [], ['name' => $username]);

            $uptimeLimit = "{$durationHours}h";
            if (empty($existing) || !is_array($existing)) {
                $api->executeCommand('/ip/hotspot/user/add', [
                    'name'         => $username,
                    'password'     => $username,
                    'profile'      => 'default',
                    'limit-uptime' => $uptimeLimit,
                    'comment'      => 'HSmart Hotspot Voucher (' . now()->toIso8601String() . ')',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("[ensureHotspotUserOnRouter] Error for router #{$routerId}: " . $e->getMessage());
        }
    }
}
