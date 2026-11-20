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
            $now = Carbon::now('Africa/Nairobi');

            // 1. Check latest HotspotSale for this MAC
            $sale = HotspotSale::withoutGlobalScopes()
                ->where(function ($q) use ($mac) {
                    $q->where('mac_address', $mac)
                      ->orWhere('mac_address', strtoupper($mac))
                      ->orWhere('mac_address', strtolower($mac));
                })
                ->when($tenantId > 0, fn($q) => $q->where('tenant_id', $tenantId))
                ->latest('id')
                ->first();

            if ($sale) {
                $isExpired = false;
                if ($sale->status === 'expired') {
                    $isExpired = true;
                } elseif ($sale->expires_at) {
                    $saleExp = Carbon::parse($sale->expires_at, 'Africa/Nairobi');
                    if ($now->greaterThanOrEqualTo($saleExp)) {
                        $isExpired = true;
                        if ($sale->status !== 'expired') {
                            $sale->update(['status' => 'expired']);
                        }
                    }
                }

                if ($isExpired) {
                    return $this->withCors(response()->json([
                        'status'  => 'expired',
                        'message' => 'Your internet package has expired. Please select a package below to purchase a new pass.',
                    ]));
                }

                // If sale is still active and valid:
                if ($sale->expires_at) {
                    $saleExp = Carbon::parse($sale->expires_at, 'Africa/Nairobi');
                    $remainingMins = $now->diffInMinutes($saleExp, false);
                    if ($remainingMins > 0) {
                        $voucher = $sale->voucher_code ?: ($sale->username ?: strtoupper($sale->mpesa_receipt ?? ''));
                        if (!empty($voucher)) {
                            $durationHours = max(1, (int) ceil($remainingMins / 60));
                            $this->ensureHotspotUserOnRouter($routerId, $voucher, $durationHours);

                            return $this->withCors(response()->json([
                                'status'          => 'active',
                                'username'        => $voucher,
                                'password'        => $voucher,
                                'voucher_code'    => $voucher,
                                'remaining_human' => $remainingMins . ' minutes remaining',
                                'message'         => 'Welcome back! Your package is active. Reconnecting...',
                            ]));
                        }
                    }
                }
            }

            // 2. Fallback to check MpesaStkTransaction
            $tx = MpesaStkTransaction::withoutGlobalScopes()
                ->where('status', 'completed')
                ->where(function ($q) use ($mac) {
                    $q->where('raw_callback', 'like', '%' . $mac . '%')
                      ->orWhere('reference', 'like', '%' . $mac . '%');
                })
                ->when($tenantId > 0, fn($q) => $q->where('tenant_id', $tenantId))
                ->latest('id')
                ->first();

            if ($tx) {
                $durationHours = $this->resolveDurationHours($tx);
                $createdAt = Carbon::parse($tx->updated_at ?: $tx->created_at)->timezone('Africa/Nairobi');
                $expiresAt = (clone $createdAt)->addHours($durationHours);

                if ($now->lt($expiresAt)) {
                    $receipt = strtoupper($tx->mpesa_receipt_number ?: $tx->checkout_request_id);
                    $this->ensureHotspotUserOnRouter($routerId, $receipt, $durationHours);

                    return $this->withCors(response()->json([
                        'status'          => 'active',
                        'username'        => $receipt,
                        'password'        => $receipt,
                        'voucher_code'    => $receipt,
                        'remaining_human' => $now->diffInMinutes($expiresAt) . ' minutes remaining',
                        'message'         => 'Welcome back! Your package is active. Reconnecting...',
                    ]));
                } else {
                    return $this->withCors(response()->json([
                        'status'  => 'expired',
                        'message' => 'Your previous internet pass has expired. Please select a package below to purchase a new pass.',
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

        // Match by price in PackageList for accuracy
        $amt = (float) $tx->amount;
        $matchedPlan = PackageList::withoutGlobalScopes()
            ->where('price', $amt)
            ->first();
        if ($matchedPlan) {
            $val = (int) ($matchedPlan->validity_value ?: ($matchedPlan->duration_value ?: 0));
            $unit = strtolower((string) ($matchedPlan->validity_unit ?: ($matchedPlan->duration_unit ?: 'hours')));
            if ($val > 0) {
                if (str_starts_with($unit, 'min')) return max(1, (int) ceil($val / 60));
                if (str_starts_with($unit, 'hour')) return $val;
                if (str_starts_with($unit, 'day')) return $val * 24;
                if (str_starts_with($unit, 'week')) return $val * 168;
                if (str_starts_with($unit, 'month')) return $val * 720;
            }
        }

        // Strict amount based fallback (No more multi-day leakage for small amounts)
        if ($amt <= 10) return 1;
        if ($amt <= 20) return 3;
        if ($amt <= 35) return 12;
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
            $api = new MikrotikApiService($host, $port, $user, $pass, 6);
            $existing = $api->executeCommand('/ip/hotspot/user/print', [], ['name' => $username]);

            $durationInfo = \App\Services\MikrotikDuration::parse($durationHours, 'hours');
            $uptimeLimit = $durationInfo['mikrotik'];
            $expiresAt = now()->timezone('Africa/Nairobi')->addSeconds($durationInfo['seconds']);

            if (empty($existing) || !is_array($existing)) {
                $api->executeCommand('/ip/hotspot/user/add', [
                    'name'         => $username,
                    'password'     => $username,
                    'profile'      => 'default',
                    'limit-uptime' => $uptimeLimit,
                    'comment'      => 'HSmart Hotspot exp:' . $expiresAt->toDateTimeString(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("[ensureHotspotUserOnRouter] Error for router #{$routerId}: " . $e->getMessage());
        }
    }
}

