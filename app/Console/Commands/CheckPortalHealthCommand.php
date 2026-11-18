<?php

namespace App\Console\Commands;

use App\Jobs\RepairCaptivePortalJob;
use App\Models\Router;
use App\Services\Notification\IspNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPortalHealthCommand extends Command
{
    protected $signature = 'isp:check-portal-health {--router=}';
    protected $description = 'Audit captive portal configuration and health across all connected routers';

    public function handle(): int
    {
        $this->info('[CheckPortalHealth] Auditing captive portal health across all active routers...');

        $query = Router::withoutGlobalScopes()->where('status', 'online');
        if ($this->option('router')) {
            $query->where('id', (int) $this->option('router'));
        }

        $routers = $query->get();
        $this->line("Found {$routers->count()} online router(s) to audit.");

        $healthyCount = 0;
        $degradedCount = 0;

        foreach ($routers as $router) {
            $this->line("Checking router #{$router->id} ({$router->name})...");
            try {
                $job = new RepairCaptivePortalJob($router->id);
                $report = $job->handle();

                if (!empty($report['healthy'])) {
                    $healthyCount++;
                    $this->info("Router #{$router->id} is HEALTHY.");
                } else {
                    $degradedCount++;
                    $this->warn("Router #{$router->id} is DEGRADED. Summary: " . ($report['summary'] ?? 'Issues detected'));
                }
            } catch (\Throwable $e) {
                $degradedCount++;
                $this->error("Exception auditing router #{$router->id}: " . $e->getMessage());
            }
        }

        $this->info("Audit completed. Healthy: {$healthyCount}, Degraded: {$degradedCount}.");
        return 0;
    }
}
