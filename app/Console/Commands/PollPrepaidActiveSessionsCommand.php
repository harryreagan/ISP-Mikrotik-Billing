<?php

namespace App\Console\Commands;

use App\Models\PrepaidUser;
use App\Models\Router;
use App\Models\Tenant;
use App\Services\CaptivePortalTemplates;
use App\Services\MikrotikApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PollPrepaidActiveSessionsCommand extends Command
{
    protected $signature = 'isp:poll-active-sessions {--tenant= : Optional tenant ID} {--router= : Optional router ID}';

    protected $description = 'Poll /ip/hotspot/active and /ppp/active every minute to update Prepaid Users online state, roaming state, daily data, and FUP enforcement.';

    public function handle(): int
    {
        if (!Schema::hasTable('prepaid_users')) {
            return Command::SUCCESS;
        }

        $tenantFilter = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $routerFilter = $this->option('router') ? (int) $this->option('router') : null;

        $tenantIds = $tenantFilter
            ? collect([$tenantFilter])
            : Tenant::where('is_active', 1)->pluck('id');

        if ($tenantIds->isEmpty()) {
            $tenantIds = collect([1]);
        }

        foreach ($tenantIds as $tenantId) {
            $tenantId = (int) $tenantId;
            $routers = Router::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', 'online')
                ->when($routerFilter, fn($q) => $q->where('id', $routerFilter))
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id')
                ->get()
                ->unique(fn($r) => trim((string) ($r->vpn_ip ?: $r->ip)));

            if ($routers->isEmpty()) {
                continue;
            }

            $tenantActiveMap = [];

            foreach ($routers as $router) {
                $host = $router->vpn_ip ?: ($router->last_known_ip ?: $router->ip);
                if (empty($host)) {
                    continue;
                }

                $port = (int) ($router->api_port ?: 8728);
                $user = $router->api_user ?: ($router->username ?: 'admin');
                $pass = CaptivePortalTemplates::decryptRouterPassword($router->api_password ?: $router->password);

                try {
                    $api = new MikrotikApiService($host, $port, $user, $pass, 6);

                    $hsActive = $api->executeCommand('/ip/hotspot/active/print');
                    $pppActive = $api->executeCommand('/ppp/active/print');

                    if (!is_array($hsActive)) {
                        $hsActive = [];
                    }
                    if (!is_array($pppActive)) {
                        $pppActive = [];
                    }

                    $activeKeysOnThisRouter = [];

                    // 1. Process Hotspot active sessions
                    foreach ($hsActive as $sess) {
                        $uName = strtoupper(trim((string) ($sess['user'] ?? '')));
                        if ($uName === '') {
                            continue;
                        }

                        $ip = $sess['address'] ?? null;
                        $mac = !empty($sess['mac-address']) ? strtoupper(trim((string) $sess['mac-address'])) : null;
                        $bytes = (int) ($sess['bytes-in'] ?? 0) + (int) ($sess['bytes-out'] ?? 0);
                        $activeKeysOnThisRouter['hotspot:' . strtolower($uName)] = true;

                        $tenantActiveMap['hotspot:' . strtolower($uName)] = [
                            'router_id'   => $router->id,
                            'router_name' => $router->name,
                            'ip'          => $ip,
                            'mac'         => $mac,
                            'bytes'       => $bytes,
                            'uptime'      => $sess['uptime'] ?? null,
                            'polled_at'   => now()->toIso8601String(),
                        ];

                        $prepaid = PrepaidUser::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('type', 'hotspot')
                            ->whereRaw('LOWER(username) = ?', [strtolower($uName)])
                            ->first();

                        if (!$prepaid) {
                            $prepaid = PrepaidUser::withoutGlobalScopes()->create([
                                'tenant_id'     => $tenantId,
                                'username'      => $uName,
                                'phone'         => null,
                                'mac_address'   => $mac,
                                'ip_address'    => $ip,
                                'type'          => 'hotspot',
                                'router_id'     => $router->id,
                                'router_name'   => $router->name,
                                'method'        => 'Router Sync',
                                'status'        => 'online',
                                'is_online'     => true,
                                'was_connected' => true,
                                'daily_data_bytes' => $bytes,
                                'last_seen_at'  => now(),
                                'started_at'    => now(),
                            ]);
                        } else {
                            // Check FUP enforcement
                            if ($prepaid->fup_enabled && $prepaid->fup_limit_mb > 0 && $bytes >= ((int) $prepaid->fup_limit_mb * 1048576)) {
                                if (!empty($sess['.id'])) {
                                    $api->executeCommand('/ip/hotspot/active/remove', [], ['.id' => $sess['.id']]);
                                }
                                try {
                                    $hu = $api->executeCommand('/ip/hotspot/user/print', [], ['name' => $uName]);
                                    if (is_array($hu) && !empty($hu[0]['.id'])) {
                                        $api->executeCommand('/ip/hotspot/user/disable', [], ['.id' => $hu[0]['.id']]);
                                    }
                                } catch (\Throwable $e) {}

                                $prepaid->update([
                                    'is_online'            => false,
                                    'status'               => 'disabled',
                                    'was_connected'        => true,
                                    'daily_data_bytes'     => $bytes,
                                    'mac_address'          => $mac ?: $prepaid->mac_address,
                                    'ip_address'           => $ip ?: $prepaid->ip_address,
                                    'last_seen_at'         => now(),
                                    'disconnection_reason' => 'data limit/FUP',
                                ]);
                                continue;
                            }

                            $isRoaming = $prepaid->router_id && (int) $prepaid->router_id !== (int) $router->id;
                            $prepaid->update([
                                'is_online'            => true,
                                'status'               => 'online',
                                'was_connected'        => true,
                                'daily_data_bytes'     => $bytes,
                                'mac_address'          => $mac ?: $prepaid->mac_address,
                                'ip_address'           => $ip ?: $prepaid->ip_address,
                                'last_seen_at'         => now(),
                                'disconnection_reason' => null,
                                'is_roaming'           => $isRoaming ? true : $prepaid->is_roaming,
                                'roaming_router_id'    => $isRoaming ? $router->id : $prepaid->roaming_router_id,
                                'roaming_router_name'  => $isRoaming ? $router->name : $prepaid->roaming_router_name,
                            ]);
                        }
                    }

                    // 2. Process PPPoE / Static active sessions
                    foreach ($pppActive as $sess) {
                        $uName = trim((string) ($sess['name'] ?? ''));
                        if ($uName === '') {
                            continue;
                        }

                        $ip = $sess['address'] ?? null;
                        $mac = !empty($sess['caller-id']) ? strtoupper(trim((string) $sess['caller-id'])) : null;
                        $bytes = (int) ($sess['bytes-in'] ?? 0) + (int) ($sess['bytes-out'] ?? 0);
                        $activeKeysOnThisRouter['pppoe:' . strtolower($uName)] = true;

                        $tenantActiveMap['pppoe:' . strtolower($uName)] = [
                            'router_id'   => $router->id,
                            'router_name' => $router->name,
                            'ip'          => $ip,
                            'mac'         => $mac,
                            'bytes'       => $bytes,
                            'uptime'      => $sess['uptime'] ?? null,
                            'polled_at'   => now()->toIso8601String(),
                        ];

                        $prepaid = PrepaidUser::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->whereIn('type', ['pppoe', 'static'])
                            ->whereRaw('LOWER(username) = ?', [strtolower($uName)])
                            ->first();

                        if ($prepaid) {
                            if ($prepaid->fup_enabled && $prepaid->fup_limit_mb > 0 && $bytes >= ((int) $prepaid->fup_limit_mb * 1048576)) {
                                if (!empty($sess['.id'])) {
                                    $api->executeCommand('/ppp/active/remove', [], ['.id' => $sess['.id']]);
                                }
                                try {
                                    $sec = $api->executeCommand('/ppp/secret/print', [], ['name' => $uName]);
                                    if (is_array($sec) && !empty($sec[0]['.id'])) {
                                        $api->executeCommand('/ppp/secret/disable', [], ['.id' => $sec[0]['.id']]);
                                    }
                                } catch (\Throwable $e) {}

                                $prepaid->update([
                                    'is_online'            => false,
                                    'status'               => 'disabled',
                                    'was_connected'        => true,
                                    'daily_data_bytes'     => $bytes,
                                    'mac_address'          => $mac ?: $prepaid->mac_address,
                                    'ip_address'           => $ip ?: $prepaid->ip_address,
                                    'last_seen_at'         => now(),
                                    'disconnection_reason' => 'data limit/FUP',
                                ]);
                                continue;
                            }

                            $isRoaming = $prepaid->router_id && (int) $prepaid->router_id !== (int) $router->id;
                            $prepaid->update([
                                'is_online'            => true,
                                'status'               => 'online',
                                'was_connected'        => true,
                                'daily_data_bytes'     => $bytes,
                                'mac_address'          => $mac ?: $prepaid->mac_address,
                                'ip_address'           => $ip ?: $prepaid->ip_address,
                                'last_seen_at'         => now(),
                                'disconnection_reason' => null,
                                'is_roaming'           => $isRoaming ? true : $prepaid->is_roaming,
                                'roaming_router_id'    => $isRoaming ? $router->id : $prepaid->roaming_router_id,
                                'roaming_router_name'  => $isRoaming ? $router->name : $prepaid->roaming_router_name,
                            ]);
                        }
                    }

                    // 3. Mark users assigned to this router who are no longer active as offline/expired
                    $onlineOnRouter = PrepaidUser::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where(function ($q) use ($router) {
                            $q->where('router_id', $router->id)->orWhere('roaming_router_id', $router->id);
                        })
                        ->where('is_online', true)
                        ->get();

                    foreach ($onlineOnRouter as $u) {
                        $key = ($u->type === 'hotspot' ? 'hotspot:' : 'pppoe:') . strtolower(trim((string) $u->username));
                        if (!isset($activeKeysOnThisRouter[$key])) {
                            $isExp = $u->expires_at && $u->expires_at->isPast();
                            $u->update([
                                'is_online'            => false,
                                'status'               => $isExp ? 'expired' : ($u->status === 'disabled' ? 'disabled' : 'offline'),
                                'disconnection_reason' => $isExp ? 'expired' : $u->disconnection_reason,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("[PollPrepaidActiveSessions] Router #{$router->id} ({$router->name}) poll check delayed: " . $e->getMessage());
                } finally {
                    if (isset($api)) {
                        $api->close();
                    }
                }
            }

            Cache::put("tenant_{$tenantId}_prepaid_active_map", $tenantActiveMap, now()->addSeconds(120));
        }

        return Command::SUCCESS;
    }
}

