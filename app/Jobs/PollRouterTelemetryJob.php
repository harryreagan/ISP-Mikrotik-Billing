<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\Notification\IspNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PollRouterTelemetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function handle(): void
    {
        $tenants = \App\Models\Tenant::where('is_active', 1)->pluck('id');

        if ($tenants->isEmpty()) {
            $tenants = collect([1]);
        }

        foreach ($tenants as $tenantId) {
            \App\Services\Tenant\TenantContext::runWithTenant((int) $tenantId, function () {
                $routers = Router::where('deleted_at', null)->get();
                foreach ($routers as $router) {
                    $this->pollRouter($router);
                }
            });
        }
    }

    protected function pollRouter(Router $router): void
    {
        $host = $router->vpn_ip ?: $router->ip;
        if (empty($host)) {
            return;
        }

        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');
        $pass = $this->decryptRouterPassword($router->api_password ?: $router->password);

        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 6);

            // 1. Resource query (CPU, Memory, Uptime, Version, Board)
            $res = $api->executeCommand('/system/resource/print');
            $data = is_array($res) && !empty($res) ? $res[0] : null;

            if ($data) {
                $cpuLoad = (int) ($data['cpu-load'] ?? 0);
                $freeMem = (int) ($data['free-memory'] ?? 0);
                $totalMem = (int) ($data['total-memory'] ?? 0);
                $memPercent = $totalMem > 0 ? (int) round((($totalMem - $freeMem) / $totalMem) * 100) : 0;
                $uptime = (string) ($data['uptime'] ?? 'unknown');
                $version = (string) ($data['version'] ?? $router->version);
                $board = (string) ($data['board-name'] ?? $router->model);

                // 2. Active hotspot users count
                $hsActive = $api->executeCommand('/ip/hotspot/active/print');
                $hsCount = is_array($hsActive) ? count($hsActive) : 0;

                // 3. Active PPPoE sessions count
                $pppoeActive = $api->executeCommand('/interface/pppoe-server/print');
                $pppoeCount = is_array($pppoeActive) ? count($pppoeActive) : 0;

                // 4. Traffic bytes
                $ifaces = $api->executeCommand('/interface/print');
                $bytesIn = 0;
                $bytesOut = 0;
                if (is_array($ifaces)) {
                    foreach ($ifaces as $if) {
                        $bytesIn += (int) ($if['rx-byte'] ?? 0);
                        $bytesOut += (int) ($if['tx-byte'] ?? 0);
                    }
                }

                // 5. Update Router Record
                $router->updateQuietly([
                    'status'                     => 'online',
                    'cpu_load'                   => $cpuLoad,
                    'memory_load'                => $memPercent,
                    'uptime'                     => $uptime,
                    'version'                    => $version,
                    'model'                      => $board,
                    'last_seen_at'               => now(),
                    'last_polled_at'             => now(),
                    'last_successful_sync_at'    => now(),
                ]);

                // 6. Record Telemetry Snapshot
                DB::table('router_performance_snapshots')->insert([
                    'router_id'      => $router->id,
                    'cpu_load'       => $cpuLoad,
                    'memory_load'    => $memPercent,
                    'uptime'         => $uptime,
                    'active_clients' => $hsCount + $pppoeCount,
                    'bytes_in'       => $bytesIn,
                    'bytes_out'      => $bytesOut,
                    'created_at'     => now(),
                ]);

                // 7. Resolve any pending offline alert and notify recovery
                $existingAlert = DB::table('isp_router_alerts')
                    ->where('router_id', $router->id)
                    ->where('alert_type', 'offline')
                    ->where('is_resolved', false)
                    ->first();

                if ($existingAlert) {
                    DB::table('isp_router_alerts')
                        ->where('id', $existingAlert->id)
                        ->update([
                            'is_resolved' => true,
                            'resolved_at' => now(),
                            'updated_at'  => now(),
                        ]);

                    IspNotificationService::notifyAdminRouterRestored($router);
                }

                // 8. High CPU alert check (> 90%)
                if ($cpuLoad >= 90) {
                    $this->triggerAlert($router, 'high_cpu', "Router {$router->name} CPU load critical: {$cpuLoad}%");
                }
            } else {
                $this->handleRouterOffline($router, 'Router API returned empty response.');
            }
        } catch (\Throwable $e) {
            $this->handleRouterOffline($router, $e->getMessage());
        } finally {
            if (isset($api)) {
                $api->close();
            }
        }
    }

    protected function decryptRouterPassword(?string $encryptedVal): string
    {
        if (empty($encryptedVal)) return '';
        $keys = [
            'base64:OsvBUcJog9u3L7RHpAXOV3lAVumKIyCIOscxYwKXufQ=',
            'base64:NSLTh+nKIi546KF1WfENeIVjjBvu0SSvE2kgyiFu7CE=',
            'base64:motZaNhPAm6uv5uffwL6kbrJdtOu3qgrdgjtXarDoaU=',
            'base64:CGygtTkIhnvb+mwr8Mv5j5DjDkgmbq9ej3mmKwHcS5g=',
        ];
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($encryptedVal);
        } catch (\Throwable $e) {}
        foreach ($keys as $k) {
            try {
                $rawKey = base64_decode(substr($k, 7));
                $enc = new \Illuminate\Encryption\Encrypter($rawKey, 'AES-256-CBC');
                return $enc->decryptString($encryptedVal);
            } catch (\Throwable $e) {}
        }
        return $encryptedVal;
    }

    protected function handleRouterOffline(Router $router, string $reason): void
    {
        $router->updateQuietly([
            'status'         => 'offline',
            'last_polled_at' => now(),
        ]);

        $this->triggerAlert($router, 'offline', "Router {$router->name} is unreachable: {$reason}");
    }

    protected function triggerAlert(Router $router, string $type, string $message): void
    {
        $existing = DB::table('isp_router_alerts')
            ->where('router_id', $router->id)
            ->where('alert_type', $type)
            ->where('is_resolved', false)
            ->first();

        if ($type === 'offline') {
            if (!$existing) {
                // 1st Alert: Immediate notification
                DB::table('isp_router_alerts')->insert([
                    'tenant_id'       => $router->tenant_id ?: 1,
                    'router_id'       => $router->id,
                    'alert_type'      => 'offline',
                    'message'         => $message,
                    'last_alerted_at' => now(),
                    'alert_count'     => 1,
                    'is_resolved'     => false,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                IspNotificationService::notifyAdminRouterOffline($router, 0);
            } elseif ($existing->alert_count == 1) {
                // 2nd Alert: 12-Hour Escalation Reminder
                $hoursDown = \Carbon\Carbon::parse($existing->created_at)->diffInHours(now());
                if ($hoursDown >= 12 && \Carbon\Carbon::parse($existing->last_alerted_at)->lte(now()->subHours(6))) {
                    DB::table('isp_router_alerts')->where('id', $existing->id)->update([
                        'alert_count'     => 2,
                        'last_alerted_at' => now(),
                        'message'         => "Router {$router->name} has been offline for 12+ hours since " . $existing->created_at,
                        'updated_at'      => now(),
                    ]);

                    IspNotificationService::notifyAdminRouterOffline12hReminder($router, \Carbon\Carbon::parse($existing->created_at)->format('H:i, d M'));
                }
            }
        } else {
            // General alerts (e.g. high_cpu)
            if ($existing) {
                if ((time() - strtotime($existing->last_alerted_at)) < 3600) {
                    return;
                }
                DB::table('isp_router_alerts')->where('id', $existing->id)->update([
                    'last_alerted_at' => now(),
                    'alert_count'     => $existing->alert_count + 1,
                ]);
            } else {
                DB::table('isp_router_alerts')->insert([
                    'tenant_id'       => $router->tenant_id ?: 1,
                    'router_id'       => $router->id,
                    'alert_type'      => $type,
                    'message'         => $message,
                    'last_alerted_at' => now(),
                    'alert_count'     => 1,
                    'is_resolved'     => false,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }
}

