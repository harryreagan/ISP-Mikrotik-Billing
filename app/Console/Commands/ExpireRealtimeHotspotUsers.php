<?php

namespace App\Console\Commands;

use App\Models\HotspotSale;
use App\Models\HotspotVoucher;
use App\Models\PrepaidUser;
use App\Models\Router;
use App\Services\CaptivePortalTemplates;
use App\Services\MikrotikApiService;
use App\Services\MikrotikDuration;
use App\Services\Sms\IspSmsDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ExpireRealtimeHotspotUsers extends Command
{
    protected $signature = 'hotspot:expire {--sms : Send reconnection SMS to newly expired users}';

    protected $description = 'Audit and forcefully terminate expired hotspot and prepaid sessions on all online routers every minute.';

    public function handle(): int
    {
        $now = Carbon::now('Africa/Nairobi');
        $sendSmsFlag = (bool) $this->option('sms');

        // 1. Collect newly expired PrepaidUsers per router/tenant before marking them expired
        $expiredPrepaid = collect();
        if (Schema::hasTable('prepaid_users')) {
            $expiredPrepaid = PrepaidUser::withoutGlobalScopes()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->whereNotIn('status', ['expired'])
                ->get();
        }

        // Map expired usernames/MACs/IPs per tenant for thorough cleanup on routers
        $expiredTargetsByTenant = [];
        foreach ($expiredPrepaid as $pu) {
            $tId = (int) $pu->tenant_id;
            $expiredTargetsByTenant[$tId][strtolower(trim($pu->username))] = $pu;
        }

        // 2. Audit every router for live active/user/cookie/ip-binding/host expiry enforcement
        $routers = Router::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('status', 'online')
                  ->orWhere('action', 'connected')
                  ->orWhereNotNull('vpn_ip');
            })
            ->get();

        foreach ($routers as $router) {
            $tId = (int) ($router->tenant_id ?: 1);
            $tenantTargets = $expiredTargetsByTenant[$tId] ?? [];
            $this->auditAndExpireRouterSessions($router, $tenantTargets, $now);
        }

        // 3. Persist expired status & disconnection_reason on DB records
        foreach ($expiredPrepaid as $pu) {
            $pu->update([
                'is_online'            => false,
                'status'               => 'expired',
                'disconnection_reason' => 'expired',
            ]);

            $this->maybeSendReconnectionSms($pu, $sendSmsFlag);
        }

        if (Schema::hasTable('hotspot_vouchers') && Schema::hasColumn('hotspot_vouchers', 'expires_at')) {
            HotspotVoucher::where('status', 'used')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);
        }

        if (Schema::hasTable('hotspot_sales') && Schema::hasColumn('hotspot_sales', 'status') && Schema::hasColumn('hotspot_sales', 'expires_at')) {
            HotspotSale::whereIn('status', ['completed', 'active', 'pending_router'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);
        }

        return Command::SUCCESS;
    }

    /**
     * Inspect active sessions, hotspot users, cookies, ip-bindings, and hosts on a router and kick/purge expired entries.
     */
    protected function auditAndExpireRouterSessions(Router $router, array $tenantExpiredUsers, Carbon $now): void
    {
        $host = $router->vpn_ip ?: ($router->last_known_ip ?: $router->ip);
        if (!$host) {
            return;
        }

        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');
        $pass = CaptivePortalTemplates::decryptRouterPassword($router->api_password ?: $router->password);

        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 6);

            $activeSessions = $api->executeCommand('/ip/hotspot/active/print');
            $hotspotUsers = $api->executeCommand('/ip/hotspot/user/print');
            $cookies = $api->executeCommand('/ip/hotspot/cookie/print');
            $ipBindings = $api->executeCommand('/ip/hotspot/ip-binding/print');
            $hotspotHosts = $api->executeCommand('/ip/hotspot/host/print');

            if (!is_array($activeSessions)) $activeSessions = [];
            if (!is_array($hotspotUsers)) $hotspotUsers = [];
            if (!is_array($cookies)) $cookies = [];
            if (!is_array($ipBindings)) $ipBindings = [];
            if (!is_array($hotspotHosts)) $hotspotHosts = [];

            $userMap = [];
            foreach ($hotspotUsers as $hu) {
                $uName = strtolower(trim((string) ($hu['name'] ?? '')));
                if ($uName !== '') {
                    $userMap[$uName] = $hu;
                }
            }

            $expiredUsernames = [];
            $expiredMacs = [];
            $expiredIps = [];

            foreach ($tenantExpiredUsers as $uKey => $pu) {
                $expiredUsernames[$uKey] = true;
                if (!empty($pu->mac_address)) {
                    $expiredMacs[strtoupper(trim($pu->mac_address))] = true;
                }
                if (!empty($pu->ip_address)) {
                    $expiredIps[trim($pu->ip_address)] = true;
                }
            }

            // A. Inspect Active Hotspot Sessions
            foreach ($activeSessions as $session) {
                $sessionId = $session['.id'] ?? null;
                $username = trim((string) ($session['user'] ?? ''));
                $uKey = strtolower($username);
                $uptimeStr = $session['uptime'] ?? '0s';
                $sessMac = strtoupper(trim((string) ($session['mac-address'] ?? '')));
                $sessIp = trim((string) ($session['address'] ?? ''));

                $shouldDisconnect = isset($expiredUsernames[$uKey]);
                $reason = $shouldDisconnect ? 'Prepaid expiry reached' : '';

                $routerUser = $userMap[$uKey] ?? null;
                if (!$shouldDisconnect && $routerUser) {
                    $limitUptime = $routerUser['limit-uptime'] ?? null;
                    if (!empty($limitUptime)) {
                        $limitSec = MikrotikDuration::parse($limitUptime)['seconds'];
                        $uptimeSec = MikrotikDuration::parse($uptimeStr)['seconds'];
                        if ($limitSec > 0 && $uptimeSec >= $limitSec) {
                            $shouldDisconnect = true;
                            $reason = "Uptime limit reached ({$uptimeStr} >= {$limitUptime})";
                        }
                    }

                    $comment = $routerUser['comment'] ?? '';
                    if (!$shouldDisconnect && preg_match('/exp:([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})/', $comment, $m)) {
                        try {
                            $expTime = Carbon::parse($m[1], 'Africa/Nairobi');
                            if ($now->greaterThanOrEqualTo($expTime)) {
                                $shouldDisconnect = true;
                                $reason = 'Validity window expired at ' . $expTime->toDateTimeString();
                            }
                        } catch (\Throwable $ignored) {}
                    }

                    $limitBytes = (int) ($routerUser['limit-bytes-total'] ?? 0);
                    $totalBytes = (int) ($session['bytes-in'] ?? 0) + (int) ($session['bytes-out'] ?? 0);
                    if (!$shouldDisconnect && $limitBytes > 0 && $totalBytes >= $limitBytes) {
                        $shouldDisconnect = true;
                        $reason = 'Data quota exhausted';
                    }
                }

                if (!$shouldDisconnect && $username !== '') {
                    $dbVoucher = HotspotVoucher::where('username', $username)->first();
                    if ($dbVoucher && $dbVoucher->expires_at && now()->greaterThanOrEqualTo($dbVoucher->expires_at)) {
                        $shouldDisconnect = true;
                        $reason = 'DB voucher expired';
                    }

                    if (!$shouldDisconnect) {
                        $dbSale = HotspotSale::withoutGlobalScopes()
                            ->where('tenant_id', $router->tenant_id ?: 1)
                            ->where(fn($q) => $q->where('voucher_code', $username)->orWhere('username', $username))
                            ->whereNotNull('expires_at')
                            ->latest('id')
                            ->first();
                        if ($dbSale && now()->greaterThanOrEqualTo($dbSale->expires_at)) {
                            $shouldDisconnect = true;
                            $reason = 'DB sale expired';
                        }
                    }
                }

                if ($shouldDisconnect) {
                    $expiredUsernames[$uKey] = true;
                    if ($sessMac !== '') $expiredMacs[$sessMac] = true;
                    if ($sessIp !== '') $expiredIps[$sessIp] = true;

                    if ($sessionId) {
                        $api->executeCommand('/ip/hotspot/active/remove', [], ['.id' => $sessionId]);
                        Log::info("[hotspot:expire] Kicked active user '{$username}' on router {$router->name} ({$reason})");
                    }
                }
            }

            // B. Inspect ALL Hotspot Users on router & remove expired ones
            foreach ($hotspotUsers as $hu) {
                $uName = trim((string) ($hu['name'] ?? ''));
                $uKey = strtolower($uName);
                $huId = $hu['.id'] ?? null;
                if (!$huId || $uName === '' || $uKey === 'default' || $uKey === 'default-trial') {
                    continue;
                }

                $shouldRemoveUser = isset($expiredUsernames[$uKey]);

                if (!$shouldRemoveUser) {
                    $comment = $hu['comment'] ?? '';
                    if (preg_match('/exp:([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})/', $comment, $m)) {
                        try {
                            if ($now->greaterThanOrEqualTo(Carbon::parse($m[1], 'Africa/Nairobi'))) {
                                $shouldRemoveUser = true;
                            }
                        } catch (\Throwable $t) {}
                    }
                }

                if (!$shouldRemoveUser && !empty($hu['limit-uptime']) && !empty($hu['uptime'])) {
                    $limitSec = MikrotikDuration::parse($hu['limit-uptime'])['seconds'];
                    $uptimeSec = MikrotikDuration::parse($hu['uptime'])['seconds'];
                    if ($limitSec > 0 && $uptimeSec >= $limitSec) {
                        $shouldRemoveUser = true;
                    }
                }

                if (!$shouldRemoveUser) {
                    $dbSale = HotspotSale::withoutGlobalScopes()
                        ->where('tenant_id', $router->tenant_id ?: 1)
                        ->where(fn($q) => $q->where('voucher_code', $uName)->orWhere('username', $uName))
                        ->whereNotNull('expires_at')
                        ->latest('id')
                        ->first();
                    if ($dbSale && now()->greaterThanOrEqualTo($dbSale->expires_at)) {
                        $shouldRemoveUser = true;
                    }
                }

                if ($shouldRemoveUser) {
                    $expiredUsernames[$uKey] = true;
                    if (!empty($hu['mac-address'])) $expiredMacs[strtoupper(trim($hu['mac-address']))] = true;
                    $api->executeCommand('/ip/hotspot/user/remove', [], ['.id' => $huId]);
                    unset($userMap[$uKey]);
                    Log::info("[hotspot:expire] Removed expired hotspot user '{$uName}' on router {$router->name}");
                }
            }

            // C. Remove MAC cookies for expired/removed users
            foreach ($cookies as $ck) {
                $ckId = $ck['.id'] ?? null;
                $ckUser = strtolower(trim((string) ($ck['user'] ?? '')));
                $ckMac = strtoupper(trim((string) ($ck['mac-address'] ?? '')));
                if (!$ckId) continue;

                if (isset($expiredUsernames[$ckUser]) || ($ckMac !== '' && isset($expiredMacs[$ckMac])) || ($ckUser !== '' && !isset($userMap[$ckUser]))) {
                    $api->executeCommand('/ip/hotspot/cookie/remove', [], ['.id' => $ckId]);
                }
            }

            // D. Remove any /ip/hotspot/ip-binding entries for expired MACs or IPs
            foreach ($ipBindings as $ib) {
                $ibId = $ib['.id'] ?? null;
                $ibMac = strtoupper(trim((string) ($ib['mac-address'] ?? '')));
                $ibAddr = trim((string) ($ib['address'] ?? ''));
                if (!$ibId) continue;

                if (($ibMac !== '' && isset($expiredMacs[$ibMac])) || ($ibAddr !== '' && isset($expiredIps[$ibAddr]))) {
                    $api->executeCommand('/ip/hotspot/ip-binding/remove', [], ['.id' => $ibId]);
                }
            }

            // E. Remove /ip/hotspot/host entries for expired MACs/IPs so device must re-authenticate
            foreach ($hotspotHosts as $hh) {
                $hhId = $hh['.id'] ?? null;
                $hhMac = strtoupper(trim((string) ($hh['mac-address'] ?? '')));
                $hhAddr = trim((string) ($hh['address'] ?? ''));
                if (!$hhId) continue;

                if (($hhMac !== '' && isset($expiredMacs[$hhMac])) || ($hhAddr !== '' && isset($expiredIps[$hhAddr]))) {
                    $api->executeCommand('/ip/hotspot/host/remove', [], ['.id' => $hhId]);
                }
            }

            // F. Disable expired PPPoE users belonging to this router/tenant
            $expiredPpp = array_filter($tenantExpiredUsers, fn($pu) => in_array($pu->type, ['pppoe', 'static'], true));
            if (!empty($expiredPpp)) {
                $pppNamesLower = array_map(fn($pu) => strtolower(trim($pu->username)), $expiredPpp);
                $pppSecrets = $api->executeCommand('/ppp/secret/print');
                if (is_array($pppSecrets)) {
                    foreach ($pppSecrets as $sec) {
                        $secName = strtolower(trim((string) ($sec['name'] ?? '')));
                        if (!empty($sec['.id']) && in_array($secName, $pppNamesLower, true)) {
                            $api->executeCommand('/ppp/secret/disable', [], ['.id' => $sec['.id']]);
                        }
                    }
                }
                $pppActives = $api->executeCommand('/ppp/active/print');
                if (is_array($pppActives)) {
                    foreach ($pppActives as $act) {
                        $actName = strtolower(trim((string) ($act['name'] ?? '')));
                        if (!empty($act['.id']) && in_array($actName, $pppNamesLower, true)) {
                            $api->executeCommand('/ppp/active/remove', [], ['.id' => $act['.id']]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[hotspot:expire] Router {$router->name} audit failed: " . $e->getMessage());
        }
    }

    protected function maybeSendReconnectionSms(PrepaidUser $pu, bool $forceSend): void
    {
        if (empty($pu->phone) || $pu->reconnection_sms_sent) {
            return;
        }

        $tenantId = (int) ($pu->tenant_id ?: 1);
        $shouldSend = $forceSend;

        if (!$shouldSend && Schema::hasTable('isp_notification_settings')) {
            try {
                $setting = DB::table('isp_notification_settings')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('event_key', ['hotspot_expiry', 'account_expired', 'expiry_reminder'])
                    ->where('sms_enabled', 1)
                    ->exists();
                $shouldSend = $setting;
            } catch (\Throwable $e) {}
        }

        if (!$shouldSend) {
            return;
        }

        try {
            $plan = $pu->package?->name ?: ($pu->plan_name ?: 'WiFi');
            $msg = "Your HSmart {$plan} session ({$pu->username}) has expired. Reconnect to WiFi and purchase a new package anytime to resume browsing.";
            $sent = IspSmsDispatcher::sendDirectSms($pu->phone, $msg, $tenantId);
            if ($sent) {
                $pu->update([
                    'reconnection_sms_sent'    => true,
                    'reconnection_sms_sent_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("[hotspot:expire] Reconnection SMS error for user #{$pu->id}: " . $e->getMessage());
        }
    }
}
