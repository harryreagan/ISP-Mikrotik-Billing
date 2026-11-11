<?php

namespace App\Console\Commands;

use App\Http\Controllers\MikrotikController;
use App\Models\MainSiteData;
use App\Models\RouterList;
use Illuminate\Console\Command;

class PollRouterLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    /**
     * The console command description.
     *
     * @var string
     */
    protected $signature = 'app:poll-router-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store logs from connected MikroTik routers in the background.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenants = \App\Models\Tenant::where('is_active', 1)->pluck('id');
        if ($tenants->isEmpty()) {
            $tenants = collect([1]);
        }

        foreach ($tenants as $tenantId) {
            \App\Services\Tenant\TenantContext::runWithTenant((int) $tenantId, function () {
                $enabled = (bool) MainSiteData::getValue('log_server_enabled', false);
                if (! $enabled) {
                    return;
                }

                $enabledRouters = MainSiteData::getValue('log_server_routers', []);
                if (empty($enabledRouters)) {
                    return;
                }

                $routers = RouterList::where(function ($q) {
                    $q->where('action', 'connected')->orWhere('status', 'online');
                })->whereIn('router_name', $enabledRouters)->get();

                if ($routers->isEmpty()) {
                    return;
                }

                $ctrl = app(MikrotikController::class);
                foreach ($routers as $router) {
                    try {
                        $logs = $ctrl->getRouterLogs($router->router_name ?: $router->name, 150);
                        if (! empty($logs)) {
                            $ctrl->storeRouterLogs($router->router_name ?: $router->name, $logs);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to poll logs for router {$router->name}: ".$e->getMessage());
                    }
                }
            });
        }
    }
}
