<?php

namespace App\Console\Commands;

use App\Services\HubConnector\HubClient;
use App\Services\HubConnector\HubSyncService;
use Illuminate\Console\Command;

class HubTestCommand extends Command
{
    protected $signature = 'hub:test';
    protected $description = 'Test HMAC connection and heartbeat to HSmart Platforms Super Admin ISP Hub';

    public function handle(HubClient $client, HubSyncService $syncService): int
    {
        $this->info('========================================================');
        $this->info('🌐 HSmart Platforms ISP Hub Connector Connection Test');
        $this->info('========================================================');

        $url = config('hub.url');
        $ispId = config('hub.isp_id');
        $key = config('hub.key');

        $this->line("• Hub Target URL : {$url}");
        $this->line("• Assigned ISP ID: {$ispId}");
        $this->line("• Public Key ID  : " . ($key ? substr($key, 0, 14) . '...' : '<NOT SET>'));

        if (!$client->isConfigured()) {
            $this->error('❌ Connector is not properly configured. Check HUB_KEY and HUB_SECRET in .env.');
            return Command::FAILURE;
        }

        $this->info("\nSending signed HMAC-SHA256 heartbeat to {$url}/api/hub/v1/heartbeat...");

        $response = $syncService->sendHeartbeat();

        if ($response['success'] ?? false) {
            $this->info('✅ Connection SUCCESSFUL! Heartbeat acknowledged by HSmart Platforms Hub.');
            $this->line('• Server Time  : ' . ($response['server_time'] ?? 'N/A'));
            $this->line('• Status Code  : ' . ($response['status_code'] ?? 200));
            $this->line('• ISP Status   : ' . ($response['isp_status'] ?? 'active'));
            return Command::SUCCESS;
        }

        $this->error('❌ Connection FAILED: ' . ($response['error'] ?? 'Unknown error'));
        if (isset($response['status_code'])) {
            $this->line('• HTTP Status  : ' . $response['status_code']);
        }

        return Command::FAILURE;
    }
}
