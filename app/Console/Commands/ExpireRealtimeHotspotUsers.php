<?php

namespace App\Console\Commands;

use App\Http\Controllers\MikrotikController;
use App\Models\HotspotSale;
use App\Models\HotspotVoucher;
use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\MikrotikDuration;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ExpireRealtimeHotspotUsers extends Command
{
    protected $signature = 'app:expire-realtime-hotspot-users';

    protected $description = 'Audit and forcefully terminate expired hotspot and voucher sessions on all online routers.';

    public function handle(): void
    {
        $tenants = \App\Models\Tenant::where('is_active', 1)->pluck('id');
        if ($tenants->isEmpty()) {
            $tenants = collect([1]);
        }

        // 1. Fetch all online or connected routers
        $routers = Router::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('status', 'online')
                  ->orWhere('action', 'connected')
                  ->orWhereNotNull('vpn_ip');
            })
            ->get();

        if ($routers->isEmpty()) {
            return;
        }

        foreach ($routers as $router) {
            $this->auditAndExpireRouterSessions($router);
        }

        // 2. Mark database records expired
        $now = now();
        HotspotVoucher::where('status', 'used')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->update(['status' => 'expired']);

        HotspotSale::where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->update(['status' => 'expired']);
    }

    /**
     * Inspect active hotspot sessions on a router and kick any whose time or quota is exhausted.
     */
    protected function auditAndExpireRouterSessions(Router $router): void
    {
        $host = $router->vpn_ip ?: $router->ip;
        if (!$host) return;

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
            $api = new MikrotikApiService($host, $port, $user, $pass, 4);

            // Fetch live active hotspot sessions
            $activeSessions = $api->executeCommand('/ip/hotspot/active/print');
            if (!is_array($activeSessions) || empty($activeSessions)) {
                return;
            }

            // Fetch hotspot users to inspect limit-uptime and comments
            $hotspotUsers = $api->executeCommand('/ip/hotspot/user/print');
            $userMap = [];
            if (is_array($hotspotUsers)) {
                foreach ($hotspotUsers as $hu) {
                    $uName = strtolower(trim($hu['name'] ?? ''));
                    if ($uName) {
                        $userMap[$uName] = $hu;
                    }
                }
            }

            $now = Carbon::now('Africa/Nairobi');

            foreach ($activeSessions as $session) {
                $sessionId = $session['.id'] ?? null;
                $username  = trim($session['user'] ?? '');
                $uKey      = strtolower($username);
                $uptimeStr = $session['uptime'] ?? '0s';

                $shouldDisconnect = false;
                $reason = '';

                // 1. Check user profile and limit-uptime on RouterOS
                $routerUser = $userMap[$uKey] ?? null;
                if ($routerUser) {
                    // Check limit-uptime vs session uptime
                    $limitUptime = $routerUser['limit-uptime'] ?? null;
                    if (!empty($limitUptime)) {
                        $limitSec = MikrotikDuration::parse($limitUptime)['seconds'];
                        $uptimeSec = MikrotikDuration::parse($uptimeStr)['seconds'];
                        if ($limitSec > 0 && $uptimeSec >= $limitSec) {
                            $shouldDisconnect = true;
                            $reason = "Uptime limit reached ({$uptimeStr} >= {$limitUptime})";
                        }
                    }

                    // Check comment for explicit expiration timestamp (exp:YYYY-MM-DD HH:MM:SS)
                    $comment = $routerUser['comment'] ?? '';
                    if (!$shouldDisconnect && preg_match('/exp:([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})/', $comment, $m)) {
                        try {
                            $expTime = Carbon::parse($m[1], 'Africa/Nairobi');
                            if ($now->greaterThanOrEqualTo($expTime)) {
                                $shouldDisconnect = true;
                                $reason = "Validity window expired at " . $expTime->toDateTimeString();
                            }
                        } catch (\Throwable $ignored) {}
                    }

                    // Check data limit
                    $limitBytes = (int) ($routerUser['limit-bytes-total'] ?? 0);
                    $bytesIn = (int) ($session['bytes-in'] ?? 0);
                    $bytesOut = (int) ($session['bytes-out'] ?? 0);
                    $totalBytes = $bytesIn + $bytesOut;
                    if (!$shouldDisconnect && $limitBytes > 0 && $totalBytes >= $limitBytes) {
                        $shouldDisconnect = true;
                        $reason = "Data quota exhausted ({$totalBytes} >= {$limitBytes})";
                    }
                }

                // 2. Check Database records (HotspotVoucher or HotspotSale)
                if (!$shouldDisconnect) {
                    $dbVoucher = HotspotVoucher::where('username', $username)->first();
                    if ($dbVoucher && $dbVoucher->expires_at && $now->greaterThanOrEqualTo($dbVoucher->expires_at)) {
                        $shouldDisconnect = true;
                        $reason = "DB voucher expired at " . $dbVoucher->expires_at;
                    }

                    if (!$shouldDisconnect) {
                        $dbSale = HotspotSale::where(function ($q) use ($username) {
                            $q->where('voucher_code', $username)
                              ->orWhere('username', $username);
                        })->whereNotNull('expires_at')->first();

                        if ($dbSale && $now->greaterThanOrEqualTo($dbSale->expires_at)) {
                            $shouldDisconnect = true;
                            $reason = "DB sale expired at " . $dbSale->expires_at;
                        }
                    }
                }

                // 3. Forcefully disconnect expired session
                if ($shouldDisconnect && $sessionId) {
                    try {
                        $api->executeCommand('/ip/hotspot/active/remove', ['.id' => $sessionId]);
                        Log::info("[ExpireHotspot] Forcefully kicked active user '{$username}' from router {$router->name}. Reason: {$reason}");

                        // Also remove or disable the user from router so they cannot reconnect without paying
                        if ($routerUser && !empty($routerUser['.id'])) {
                            $api->executeCommand('/ip/hotspot/user/remove', ['.id' => $routerUser['.id']]);
                        }
                    } catch (\Throwable $kickEx) {
                        Log::warning("[ExpireHotspot] Could not kick user {$username}: " . $kickEx->getMessage());
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::warning("[ExpireRealtimeHotspotUsers] Router {$router->name} audit failed: " . $e->getMessage());
        }
    }
}
