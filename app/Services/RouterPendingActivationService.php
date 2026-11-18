<?php

namespace App\Services;

use App\Models\HotspotSale;
use App\Models\Router;
use App\Models\RouterPendingActivation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class RouterPendingActivationService
{
    /**
     * Enqueue a pending hotspot user activation when a router is offline, unreachable, or on a new network.
     */
    public static function enqueue(
        int $tenantId,
        ?int $routerId,
        string $voucherCode,
        string $username,
        string $password,
        ?string $profile = null,
        ?string $limitUptime = null,
        ?int $limitBytes = null,
        ?string $clientIp = null,
        ?string $clientMac = null,
        ?Carbon $expiresAt = null,
        ?int $saleId = null,
        ?string $initialError = null
    ): RouterPendingActivation {
        return RouterPendingActivation::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id'    => $tenantId,
                'voucher_code' => strtoupper(trim($voucherCode)),
            ],
            [
                'router_id'       => $routerId,
                'hotspot_sale_id' => $saleId,
                'username'        => trim($username),
                'password'        => trim($password),
                'profile'         => $profile,
                'limit_uptime'    => $limitUptime,
                'limit_bytes'     => $limitBytes,
                'client_ip'       => $clientIp,
                'client_mac'      => $clientMac ? strtoupper(trim($clientMac)) : null,
                'expires_at'      => $expiresAt,
                'status'          => 'pending',
                'last_error'      => $initialError,
            ]
        );
    }

    /**
     * Process all pending activations for a specific router (e.g. called upon /router-hello or reconnect).
     */
    public function processPendingForRouter(Router $router): int
    {
        $pending = RouterPendingActivation::withoutGlobalScopes()
            ->where(function ($q) use ($router) {
                $q->where('router_id', $router->id)
                  ->orWhere(function ($sub) use ($router) {
                      $sub->whereNull('router_id')
                          ->where('tenant_id', $router->tenant_id);
                  });
            })
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 10)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderBy('id', 'asc')
            ->limit(30)
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $host = $router->vpn_ip ?: ($router->last_known_ip ?: $router->ip);
        if (!$host) {
            return 0;
        }

        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');
        $pass = $this->decryptRouterPassword($router);

        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 5);
        } catch (\Throwable $e) {
            Log::warning("[RouterPendingActivation] Cannot connect to Router #{$router->id} ({$router->name}): " . $e->getMessage());
            foreach ($pending as $act) {
                $act->increment('attempts');
                $act->update([
                    'last_attempt_at' => now(),
                    'last_error'      => 'Connection failed: ' . $e->getMessage(),
                ]);
            }
            return 0;
        }

        $processedCount = 0;

        foreach ($pending as $activation) {
            $activation->update(['status' => 'processing', 'last_attempt_at' => now()]);
            try {
                $code = strtoupper(trim($activation->voucher_code ?: $activation->username));
                $limitUptime = $activation->limit_uptime ?: '1h';

                // Check existing users on router
                $existingUsers = $api->executeCommand('/ip/hotspot/user/print');
                $matchedUser = null;
                if (is_array($existingUsers)) {
                    foreach ($existingUsers as $eu) {
                        if (strtoupper($eu['name'] ?? '') === $code) {
                            $matchedUser = $eu;
                            break;
                        }
                    }
                }

                $comment = 'HSmart ' . ($activation->username !== $code ? $activation->username : '') .
                           ($activation->expires_at ? ' exp:' . $activation->expires_at->toDateTimeString() : '');

                $userPayload = [
                    'name'         => $code,
                    'password'     => $activation->password ?: $code,
                    'limit-uptime' => $limitUptime,
                    'comment'      => trim($comment),
                ];
                if (!empty($activation->profile)) {
                    $userPayload['profile'] = $activation->profile;
                }
                if ($activation->limit_bytes && $activation->limit_bytes > 0) {
                    $userPayload['limit-bytes-total'] = (string) $activation->limit_bytes;
                }

                if ($matchedUser && !empty($matchedUser['.id'])) {
                    // Update user
                    $userPayload['.id'] = $matchedUser['.id'];
                    $api->executeCommand('/ip/hotspot/user/set', $userPayload);
                } else {
                    $api->executeCommand('/ip/hotspot/user/add', $userPayload);
                }

                // Remove any stale cookies for this user so uptime is enforced strictly
                try {
                    $cookies = $api->executeCommand('/ip/hotspot/cookie/print');
                    if (is_array($cookies)) {
                        foreach ($cookies as $ck) {
                            if (strtoupper($ck['user'] ?? '') === $code) {
                                $api->executeCommand('/ip/hotspot/cookie/remove', ['.id' => $ck['.id']]);
                            }
                        }
                    }
                } catch (\Throwable $ckEx) {}

                // Directly authorize client if IP is valid
                if (!empty($activation->client_ip) && filter_var($activation->client_ip, FILTER_VALIDATE_IP)) {
                    $loginParams = [
                        'user'     => $code,
                        'password' => $activation->password ?: $code,
                        'ip'       => $activation->client_ip,
                    ];
                    if (!empty($activation->client_mac) && preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $activation->client_mac)) {
                        $loginParams['mac-address'] = strtoupper($activation->client_mac);
                    }
                    $api->executeCommand('/ip/hotspot/active/login', $loginParams);
                }

                $activation->update([
                    'status'     => 'completed',
                    'router_id'  => $router->id,
                    'last_error' => null,
                ]);

                // Update HotspotSale if attached
                if ($activation->hotspot_sale_id) {
                    HotspotSale::withoutGlobalScopes()
                        ->where('id', $activation->hotspot_sale_id)
                        ->update([
                            'status'      => 'completed',
                            'router_id'   => $router->id,
                            'router_name' => $router->name,
                            'sync_error'  => null,
                        ]);
                } else {
                    HotspotSale::withoutGlobalScopes()
                        ->where('voucher_code', $code)
                        ->where('status', 'pending_router')
                        ->update([
                            'status'      => 'completed',
                            'router_id'   => $router->id,
                            'router_name' => $router->name,
                            'sync_error'  => null,
                        ]);
                }

                $processedCount++;
                Log::info("[RouterPendingActivation] Successfully activated queued user '{$code}' on Router #{$router->id} ({$router->name})");
            } catch (\Throwable $e) {
                Log::error("[RouterPendingActivation] Failed to activate user '{$activation->username}' on Router #{$router->id}: " . $e->getMessage());
                $activation->increment('attempts');
                $activation->update([
                    'status'     => 'failed',
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        return $processedCount;
    }

    /**
     * Process pending activations across all active routers.
     */
    public function processAllPending(): int
    {
        $routers = Router::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('status', 'online')
                  ->orWhere('action', 'connected')
                  ->orWhereNotNull('vpn_ip')
                  ->orWhereNotNull('last_known_ip');
            })
            ->get();

        $total = 0;
        foreach ($routers as $router) {
            $total += $this->processPendingForRouter($router);
        }

        return $total;
    }

    protected function decryptRouterPassword(Router $router): string
    {
        $raw = $router->api_password ?: $router->password;
        if (empty($raw)) return '';

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            $fallbackKeys = [
                'base64:OsvBUcJog9u3L7RHpAXOV3lAVumKIyCIOscxYwKXufQ=',
                'base64:NSLTh+nKIi546KF1WfENeIVjjBvu0SSvE2kgyiFu7CE=',
                'base64:motZaNhPAm6uv5uffwL6kbrJdtOu3qgrdgjtXarDoaU=',
                'base64:CGygtTkIhnvb+mwr8Mv5j5DjDkgmbq9ej3mmKwHcS5g=',
            ];
            foreach ($fallbackKeys as $k) {
                try {
                    $rawKey = base64_decode(substr($k, 7));
                    $enc = new \Illuminate\Encryption\Encrypter($rawKey, 'AES-256-CBC');
                    return $enc->decryptString($raw);
                } catch (\Throwable $ignored) {}
            }
            return $raw;
        }
    }
}
