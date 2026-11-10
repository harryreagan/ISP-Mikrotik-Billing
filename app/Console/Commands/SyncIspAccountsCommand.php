<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncIspAccountsCommand extends Command
{
    protected $signature = 'isp:sync-accounts';
    protected $description = 'Sync all ISP deployments from hsmart_db into wifi_system users with proper Admin roles';

    public function handle(): int
    {
        $this->info("=== Syncing All Connected ISP Deployments into ISP Billing ===");

        // Ensure Master Platform Tenant (ID 1) exists to prevent foreign key errors
        DB::table('tenants')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'HSmart Master Network',
                'subdomain' => 'hsmart-master',
                'billing_code' => 'HSMST1',
                'country' => 'Kenya',
                'county' => 'Nairobi',
                'is_active' => 1,
                'sms_balance' => 100.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $this->info("Tenant #1 (Master Platform) verified.");

        // Fix null tenant_id on admin/super admin users
        User::whereNull('tenant_id')->update(['tenant_id' => 1]);

        // Get all ISPs from hsmart_db or current connection
        $isps = [];
        try {
            $isps = DB::select("SELECT * FROM hsmart_db.isps");
        } catch (\Throwable $e) {
            $this->warn("Direct hsmart_db query failed: " . $e->getMessage() . " - falling back to tenants table if available.");
            if (\Illuminate\Support\Facades\Schema::hasTable('tenants')) {
                $isps = DB::table('tenants')->get();
            }
        }

        $this->info("Found " . count($isps) . " ISPs.");

        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        foreach ($isps as $isp) {
            $email = !empty($isp->contact_email) 
                ? trim($isp->contact_email) 
                : (strtolower(str_replace([' ', '-'], '', $isp->slug ?? $isp->name)) . '@hsmart.co.ke');
            $name = !empty($isp->contact_name) ? $isp->contact_name : $isp->name;
            $phone = !empty($isp->contact_phone) ? $isp->contact_phone : null;

            $user = User::where('tenant_id', $isp->id)->first();
            if (!$user) {
                $user = User::where('email', $email)->first();
            }

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'mobile' => $phone,
                    'password' => Hash::make('HSmart2026!'),
                    'role' => 'admin',
                    'status' => 'active',
                    'tenant_id' => $isp->id,
                    'is_super_admin' => 0,
                    'email_verified_at' => now(),
                ]);
                $this->line("  <fg=green>[CREATED]</> Tenant #{$isp->id}: {$name} ({$email})");
            } else {
                $user->name = $name;
                $user->tenant_id = $isp->id;
                $user->role = 'admin';
                $user->status = 'active';
                if ($phone && empty($user->phone)) {
                    $user->phone = $phone;
                    $user->mobile = $phone;
                }
                $user->save();
                $this->line("  <fg=yellow>[UPDATED]</> Tenant #{$isp->id}: {$name} ({$user->email})");
            }

            // Tenant users get 'Admin' role only
            if (!$user->hasRole('Admin')) {
                $user->assignRole('Admin');
            }
            if ($user->hasRole('Super Admin')) {
                $user->removeRole('Super Admin');
            }
        }

        // Global Super Admin check for Harry Reagan
        $superUsers = User::whereIn('email', ['harryreaganodhiambo@gmail.com', 'admin@hsmart.co.ke'])->get();
        foreach ($superUsers as $super) {
            $super->tenant_id = null;
            $super->role = 'superadmin';
            $super->is_super_admin = 1;
            $super->save();
            if (!$super->hasRole('Super Admin')) {
                $super->assignRole('Super Admin');
            }
            if (!$super->hasRole('Admin')) {
                $super->assignRole('Admin');
            }
            $this->line("  <fg=cyan>[VERIFIED]</> Super Admin {$super->name} ({$super->email}) global access secured.");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info("=== All ISP Accounts Successfully Synced & Permissions Reset ===");

        return Command::SUCCESS;
    }
}

