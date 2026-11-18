<?php

namespace App\Console\Commands;

use App\Services\RouterPendingActivationService;
use Illuminate\Console\Command;

class ProcessPendingRouterActivations extends Command
{
    protected $signature = 'isp:process-pending-router-activations';

    protected $description = 'Process queued hotspot user activations for routers that were offline or changed networks.';

    public function handle(RouterPendingActivationService $service): int
    {
        $count = $service->processAllPending();
        if ($count > 0) {
            $this->info("Successfully processed {$count} pending router activations.");
        }
        return Command::SUCCESS;
    }
}

