<?php

namespace App\Console\Commands;

use App\Services\HubConnector\HubSyncService;
use Illuminate\Console\Command;

class HubSyncCommand extends Command
{
    protected $signature = 'hub:sync';
    protected $description = 'Trigger immediate sync (heartbeat, routers, online samples, outbox) to HSmart Hub';

    public function handle(HubSyncService $syncService): int
    {
        $this->info('Starting ISP Hub sync cycle...');

        $this->line('1/4 Sending Heartbeat...');
        $heartbeat = $syncService->sendHeartbeat();
        $this->line('   ' . ($heartbeat['success'] ? '✅ Heartbeat OK' : '❌ Failed: ' . ($heartbeat['error'] ?? '')));

        $this->line('2/4 Syncing Routers...');
        $routers = $syncService->syncRouters();
        $this->line('   ' . ($routers['success'] ? '✅ Routers Synced' : '❌ Failed: ' . ($routers['error'] ?? '')));

        $this->line('3/4 Sending Online Samples...');
        $samples = $syncService->sendOnlineSamples();
        $this->line('   ' . ($samples['success'] ? '✅ Samples Logged' : '❌ Failed: ' . ($samples['error'] ?? '')));

        $this->line('4/4 Polling Hub Commands & Notices...');
        $commands = $syncService->pollCommands();
        $this->line('   ' . ($commands['success'] ? '✅ Commands Polled' : '❌ Failed: ' . ($commands['error'] ?? '')));

        $this->info('ISP Hub sync completed.');
        return Command::SUCCESS;
    }
}
