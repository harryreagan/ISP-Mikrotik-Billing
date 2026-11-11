<?php

namespace App\Jobs;

use App\Http\Controllers\MikrotikController;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionMikrotikUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    protected int $customerId;

    public function __construct(int $customerId)
    {
        $this->customerId = $customerId;
    }

    public function handle(): void
    {
        $customer = CustomersInfo::withoutGlobalScopes()->with(['pppUser' => fn($q) => $q->withoutGlobalScopes()])->find($this->customerId);

        if (! $customer || ! $customer->pppUser) {
            Log::info("ProvisionMikrotikUserJob: Customer #{$this->customerId} or PPPoE user not found. Skipping.");
            return;
        }

        $tenantId = $customer->tenant_id ?? 1;
        \App\Services\Tenant\TenantContext::runWithTenant((int) $tenantId, function () use ($customer) {
            $pppUser = $customer->pppUser;
            $routerName = $pppUser->router_name;
            $username = $pppUser->username;
            $profile = $pppUser->profile;

            if (empty($routerName) || empty($username)) {
                Log::warning("ProvisionMikrotikUserJob: Incomplete router or username for customer #{$customer->id}");
                return;
            }

            try {
                $mikrotik = app(MikrotikController::class);

                // 1. Enable secret on RouterOS
                $mikrotik->enablePPPSecret($customer->customer_unique_id, $routerName, $username);

                // 2. Restore subscribed profile
                if (! empty($profile)) {
                    $mikrotik->updatePPPSecret($routerName, $username, 'profile', $profile);
                }

                // 3. Kick active connection so router applies renewed profile
                try {
                    $mikrotik->singleWrite(
                        $routerName,
                        '/ppp active remove [find name="'.$username.'"]'
                    );
                } catch (\Throwable $e) {
                    Log::debug("ProvisionMikrotikUserJob: Active session kick skipped: {$e->getMessage()}");
                }

                // 4. Update local DB secret status
                PPPSecrets::where('id', $pppUser->id)->update(['status' => 'active']);

                Log::info("ProvisionMikrotikUserJob: Successfully provisioned customer {$customer->customer_unique_id} on router {$routerName}");

            } catch (\Throwable $e) {
                Log::error("ProvisionMikrotikUserJob failed for customer {$customer->customer_unique_id}: {$e->getMessage()}");
                throw $e;
            }
        });
    }
}

