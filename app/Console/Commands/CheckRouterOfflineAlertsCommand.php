<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\Notification\IspNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckRouterOfflineAlertsCommand extends Command
{
    protected $signature = 'isp:check-router-offline-alerts';
    protected $description = 'Detect offline MikroTik routers, send immediate alerts, and 12-hour reminder escalations to ISP owners';

    public function handle(): int
    {
        $this->info('[CheckRouterOfflineAlerts] Checking router connectivity across all tenants...');

        $routers = Router::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->get();

        $offlineCount = 0;
        $escalatedCount = 0;
        $recoveredCount = 0;

        foreach ($routers as $router) {
            $tenantId = (int) ($router->tenant_id ?: 1);

            // Determine if router is online
            // Online criteria: status is 'online' AND last_seen_at within last 10 minutes
            $isOnline = ($router->status === 'online') &&
                        $router->last_seen_at &&
                        Carbon::parse($router->last_seen_at)->gte(now()->subMinutes(10));

            // Check for existing unresolved offline alert
            $existingAlert = DB::table('isp_router_alerts')
                ->where('router_id', $router->id)
                ->where('alert_type', 'offline')
                ->where('is_resolved', false)
                ->first();

            if (!$isOnline) {
                // Router is OFFLINE
                if ($router->status !== 'offline') {
                    $router->updateQuietly([
                        'status'         => 'offline',
                        'last_polled_at' => now(),
                    ]);
                }

                if (!$existingAlert) {
                    // ── 1ST ALERT: Immediate Offline Alert ──
                    $downtimeStr = $router->last_seen_at
                        ? Carbon::parse($router->last_seen_at)->format('H:i, d M')
                        : now()->format('H:i, d M');

                    $alertId = DB::table('isp_router_alerts')->insertGetId([
                        'tenant_id'       => $tenantId,
                        'router_id'       => $router->id,
                        'alert_type'      => 'offline',
                        'message'         => "Router {$router->name} went offline. Last seen: {$downtimeStr}",
                        'last_alerted_at' => now(),
                        'alert_count'     => 1,
                        'is_resolved'     => false,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);

                    $res = IspNotificationService::notifyAdminRouterOffline($router, 0);
                    $this->warn("Router #{$router->id} ({$router->name}) is OFFLINE. Dispatched 1st alert: " . json_encode($res));
                    $offlineCount++;

                } elseif ($existingAlert->alert_count == 1) {
                    // ── 2ND ALERT: 12-Hour Escalation Reminder ──
                    $alertCreatedAt = Carbon::parse($existingAlert->created_at);
                    $hoursDown = $alertCreatedAt->diffInHours(now());

                    // If down for >= 12 hours and last alert was sent at least 6 hours ago
                    if ($hoursDown >= 12 && Carbon::parse($existingAlert->last_alerted_at)->lte(now()->subHours(6))) {
                        DB::table('isp_router_alerts')
                            ->where('id', $existingAlert->id)
                            ->update([
                                'alert_count'     => 2,
                                'last_alerted_at' => now(),
                                'message'         => "Router {$router->name} has been offline for 12+ hours since " . $alertCreatedAt->format('H:i, d M'),
                                'updated_at'      => now(),
                            ]);

                        $res = IspNotificationService::notifyAdminRouterOffline12hReminder($router, $alertCreatedAt->format('H:i, d M'));
                        $this->error("Router #{$router->id} ({$router->name}) offline for 12h+. Dispatched escalation reminder: " . json_encode($res));
                        $escalatedCount++;
                    }
                }
            } else {
                // Router is ONLINE
                if ($existingAlert) {
                    // ── RECOVERY: Router Restored ──
                    DB::table('isp_router_alerts')
                        ->where('id', $existingAlert->id)
                        ->update([
                            'is_resolved' => true,
                            'resolved_at' => now(),
                            'updated_at'  => now(),
                        ]);

                    $res = IspNotificationService::notifyAdminRouterRestored($router);
                    $this->info("Router #{$router->id} ({$router->name}) is back ONLINE. Sent recovery alert: " . json_encode($res));
                    $recoveredCount++;
                }
            }
        }

        $this->info("Summary: {$offlineCount} new offline alerts, {$escalatedCount} 12h escalations, {$recoveredCount} recovered.");
        return 0;
    }
}

