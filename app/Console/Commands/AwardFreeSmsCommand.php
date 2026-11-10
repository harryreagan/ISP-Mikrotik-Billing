<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AwardFreeSmsCommand extends Command
{
    protected $signature = 'isp:award-free-sms {credits=20 : Number of SMS credits to award}';
    protected $description = 'Award free SMS credits to all registered ISPs and tenants';

    public function handle()
    {
        $credits = (float) $this->argument('credits');
        if ($credits <= 0) {
            $credits = 20.00;
        }

        $this->info("Awarding {$credits} free SMS credits to all ISPs...");

        // 1. Update tenants table
        $updatedTenants = DB::table('tenants')
            ->update([
                'sms_balance' => DB::raw("GREATEST(COALESCE(sms_balance, 0), {$credits})"),
                'updated_at'  => now(),
            ]);

        // 2. Ensure every tenant has an sms_wallets record with at least $credits
        $allTenants = DB::table('tenants')->get(['id', 'name']);
        $credited = 0;

        foreach ($allTenants as $t) {
            $wallet = DB::table('sms_wallets')->where('tenant_id', $t->id)->first();
            if (!$wallet) {
                DB::table('sms_wallets')->insert([
                    'tenant_id'             => $t->id,
                    'balance'               => $credits,
                    'low_balance_threshold' => 50.00,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
                $credited++;
            } else {
                if ($wallet->balance < $credits) {
                    DB::table('sms_wallets')->where('tenant_id', $t->id)->update([
                        'balance'    => $credits,
                        'updated_at' => now(),
                    ]);
                    $credited++;
                }
            }
        }

        $this->info("Successfully verified and awarded {$credits} SMS credits to all {$allTenants->count()} ISPs (updated: {$credited}).");
        return 0;
    }
}

