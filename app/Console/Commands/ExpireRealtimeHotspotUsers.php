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
        if (\Illuminate\Support\Facades\Schema::hasColumn('hotspot_vouchers', 'expires_at')) {
            HotspotVoucher::where('status', 'used')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->update(['status' => 'expired']);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('hotspot_sales', 'status') && \Illuminate\Support\Facades\Schema::hasColumn('hotspot_sales', 'expires_at')) {
            HotspotSale::whereIn('status', ['completed', 'active'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->update(['status' => 'expired']);
        }
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

            // 1. Fetch live active hotspot sessions
            $activeSessions = $api->executeCommand('/ip/hotspot/active/print') ?: [];

            // 2. Fetch hotspot users to inspect limit-uptime, comments, and DB expiration
            $hotspotUsers = $api->executeCommand('/ip/hotspot/user/print') ?: [];
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

            // Audit Active Sessions and immediately kick expired ones
            if (is_array($activeSessions)) {
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

                        if (!$shouldDisconnect && \Illuminate\Support\Facades\Schema::hasColumn('hotspot_sales', 'expires_at')) {
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

                            // Also remove cookie so mac-cookie never bypasses limit-uptime
                            try {
                                $cookies = $api->executeCommand('/ip/hotspot/cookie/print');
                                if (is_array($cookies)) {
                                    foreach ($cookies as $ck) {
                                        if (strcasecmp($ck['user'] ?? '', $username) === 0) {
                                            $api->executeCommand('/ip/hotspot/cookie/remove', ['.id' => $ck['.id']]);
                                        }
                                    }
                                }
                            } catch (\Throwable $ckEx) {}

                            // Also remove the user from router so they cannot reconnect without paying
                            if ($routerUser && !empty($routerUser['.id'])) {
                                $api->executeCommand('/ip/hotspot/user/remove', ['.id' => $routerUser['.id']]);
                                unset($userMap[$uKey]);
                            }
                        } catch (\Throwable $kickEx) {
                            Log::warning("[ExpireHotspot] Could not kick user {$username}: " . $kickEx->getMessage());
                        }
                    }
                }
            }

            // 4. Audit ALL Hotspot Users (even if currently disconnected) to prevent free re-connection!
            if (is_array($hotspotUsers)) {
                foreach ($hotspotUsers as $hu) {
                    $uName = trim($hu['name'] ?? '');
                    if (empty($uName) || strtolower($uName) === 'default') continue;

                    $huId = $hu['.id'] ?? null;
                    if (!$huId) continue;

                    $shouldRemoveUser = false;
                    $purgeReason = '';

                    // Check comment expiration
                    $comment = $hu['comment'] ?? '';
                    if (preg_match('/exp:([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})/', $comment, $m)) {
                        try {
                            $expTime = Carbon::parse($m[1], 'Africa/Nairobi');
                            if ($now->greaterThanOrEqualTo($expTime)) {
                                $shouldRemoveUser = true;
                                $purgeReason = "Comment expiration passed at " . $expTime->toDateTimeString();
                            }
                        } catch (\Throwable $t) {}
                    }

                    // Check limit-uptime vs router user uptime
                    $limitUptime = $hu['limit-uptime'] ?? null;
                    $userUptime  = $hu['uptime'] ?? null;
                    if (!$shouldRemoveUser && !empty($limitUptime) && !empty($userUptime)) {
                        $limitSec = MikrotikDuration::parse($limitUptime)['seconds'];
                        $uptimeSec = MikrotikDuration::parse($userUptime)['seconds'];
                        if ($limitSec > 0 && $uptimeSec >= $limitSec) {
                            $shouldRemoveUser = true;
                            $purgeReason = "Cumulative uptime reached limit ({$userUptime} >= {$limitUptime})";
                        }
                    }

                    // Check DB expiration
                    if (!$shouldRemoveUser) {
                        $dbVoucher = HotspotVoucher::where('username', $uName)->first();
                        if ($dbVoucher && $dbVoucher->expires_at && $now->greaterThanOrEqualTo($dbVoucher->expires_at)) {
                            $shouldRemoveUser = true;
                            $purgeReason = "DB voucher expired at " . $dbVoucher->expires_at;
                        }

                        if (!$shouldRemoveUser && \Illuminate\Support\Facades\Schema::hasColumn('hotspot_sales', 'expires_at')) {
                            $dbSale = HotspotSale::where(function ($q) use ($uName) {
                                $q->where('voucher_code', $uName)
                                  ->orWhere('username', $uName);
                            })->whereNotNull('expires_at')->first();

                            if ($dbSale && $now->greaterThanOrEqualTo($dbSale->expires_at)) {
                                $shouldRemoveUser = true;
                                $purgeReason = "DB sale expired at " . $dbSale->expires_at;
                            }
                        }
                    }

                    if ($shouldRemoveUser) {
                        try {
                            $api->executeCommand('/ip/hotspot/user/remove', ['.id' => $huId]);
                            Log::info("[ExpireHotspot] Purged expired offline user '{$uName}' from router {$router->name}. Reason: {$purgeReason}");
                        } catch (\Throwable $ex) {}
                    }
                }
            }

            // 5. Audit and purge expired / orphaned cookies
            try {
                $cookies = $api->executeCommand('/ip/hotspot/cookie/print');
                if (is_array($cookies)) {
                    foreach ($cookies as $ck) {
                        $ckUser = trim($ck['user'] ?? '');
                        if (empty($ckUser)) continue;
                        $ckId = $ck['.id'] ?? null;
                        if (!$ckId) continue;

                        $shouldRemoveCookie = false;
                        if (!isset($userMap[strtolower($ckUser)])) {
                            // User is no longer in /ip/hotspot/user
                            $shouldRemoveCookie = true;
                        } else {
                            // Check DB sale status
                            $dbSale = HotspotSale::where(function ($q) use ($ckUser) {
                                $q->where('voucher_code', $ckUser)->orWhere('username', $ckUser);
                            })->latest('id')->first();
                            if ($dbSale && ($dbSale->status === 'expired' || ($dbSale->expires_at && $now->greaterThanOrEqualTo($dbSale->expires_at)))) {
                                $shouldRemoveCookie = true;
                            }
                        }

                        if ($shouldRemoveCookie) {
                            $api->executeCommand('/ip/hotspot/cookie/remove', ['.id' => $ckId]);
                        }
                    }
                }
            } catch (\Throwable $ckEx) {}

        } catch (\Throwable $e) {
            Log::warning("[ExpireRealtimeHotspotUsers] Router {$router->name} audit failed: " . $e->getMessage());
        }
    }
}
