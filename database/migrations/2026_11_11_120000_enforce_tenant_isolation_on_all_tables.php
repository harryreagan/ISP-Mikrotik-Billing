<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables requiring tenant_id column, indexing, and foreign key constraint.
     */
    protected array $tenantOwnedTables = [
        'p_p_p_secrets',
        'hotspot_sales',
        'billing_infos',
        'mpesa_stk_transactions',
        'payment_summaries',
        'unmatched_payments',
        'isp_expenses',
        'reseller_commissions',
        'reseller_packages',
        'reseller_wallet_transactions',
        'sms_templates',
        'customers_addresses',
        'address_fields',
        'comment_lists',
        'customer_reviews',
        'permitted_urls',
        'package_purchase_requests',
        'mikrotik_logs',
        'official_infos',
        'vouchers',
        'user_login_logs',
        'main_site_data',
        'radcheck',
        'radreply',
        'radacct',
        'radusergroup',
        'nas',
        'router_interfaces',
        'router_lists',
    ];

    public function up(): void
    {
        // 1. Ensure master tenant #1 exists
            if (Schema::hasTable('tenants')) {
                DB::table('tenants')->updateOrInsert(
                    ['id' => 1],
                    [
                        'name'         => 'HSmart Master Network',
                        'subdomain'    => 'hsmart-master',
                        'billing_code' => 'HSMST1',
                        'country'      => 'Kenya',
                        'county'       => 'Nairobi',
                        'is_active'    => 1,
                        'sms_balance'  => 100.00,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]
                );
            }

            // 2. Ensure each admin user without a tenant is assigned or provisioned a tenant
            if (Schema::hasTable('users')) {
                $unassignedUsers = DB::table('users')->whereNull('tenant_id')->get();
                foreach ($unassignedUsers as $u) {
                    if (!empty($u->is_super_admin) || strtolower($u->role ?? '') === 'superadmin') {
                        continue; // Super admin remains tenant_id = null for global view
                    }
                    $tenantId = DB::table('tenants')->insertGetId([
                        'name'         => ($u->name ?: 'ISP') . ' Networks',
                        'subdomain'    => \Illuminate\Support\Str::slug($u->name ?: 'isp') . '-' . $u->id,
                        'billing_code' => strtoupper(\Illuminate\Support\Str::random(6)),
                        'country'      => 'Kenya',
                        'county'       => 'Nairobi',
                        'is_active'    => 1,
                        'sms_balance'  => 20.00,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    DB::table('users')->where('id', $u->id)->update(['tenant_id' => $tenantId]);
                }
            }

            // 3. Add tenant_id column and index to all tenant-owned tables
            foreach ($this->tenantOwnedTables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                if (!Schema::hasColumn($table, 'tenant_id')) {
                    Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                        $col = $tableBlueprint->unsignedBigInteger('tenant_id')->nullable()->index();
                        if (Schema::hasColumn($table, 'id')) {
                            $col->after('id');
                        }
                    });
                }
            }

            // 4. Backfill existing records from owner / router / customer / creator
            $this->backfillRecords();

            // 5. Add foreign key constraints where safe
            foreach ($this->tenantOwnedTables as $table) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                    continue;
                }

                try {
                    Schema::table($table, function (Blueprint $tableBlueprint) {
                        $tableBlueprint->foreign('tenant_id')
                            ->references('id')
                            ->on('tenants')
                            ->cascadeOnDelete();
                    });
                } catch (\Throwable $e) {
                    Log::warning("[Migration] Could not apply FK constraint on {$table}: " . $e->getMessage());
                }
            }
    }

    /**
     * Safely backfill tenant_id onto legacy unassigned rows.
     */
    protected function backfillRecords(): void
    {
        // 1. Routers with null tenant_id -> default to 1
        if (Schema::hasTable('routers') && Schema::hasColumn('routers', 'tenant_id')) {
            DB::table('routers')->whereNull('tenant_id')->update(['tenant_id' => 1]);
        }

        // 2. PPPSecrets: match router_name to router's tenant_id, else 1
        if (Schema::hasTable('p_p_p_secrets') && Schema::hasColumn('p_p_p_secrets', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE p_p_p_secrets p
                    LEFT JOIN routers r ON (r.name = p.router_name OR r.router_name = p.router_name)
                    SET p.tenant_id = COALESCE(r.tenant_id, 1)
                    WHERE p.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 3. CustomersInfo: backfill null tenant_id
        if (Schema::hasTable('customers_infos') && Schema::hasColumn('customers_infos', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE customers_infos c
                    LEFT JOIN routers r ON r.id = c.router_id
                    SET c.tenant_id = COALESCE(r.tenant_id, 1)
                    WHERE c.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 4. BillingInfo: match customer_bill_unique_id -> customers_infos.tenant_id
        if (Schema::hasTable('billing_infos') && Schema::hasColumn('billing_infos', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE billing_infos b
                    LEFT JOIN customers_infos c ON c.customer_unique_id = b.customer_bill_unique_id
                    SET b.tenant_id = COALESCE(c.tenant_id, 1)
                    WHERE b.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 5. HotspotSale: match router_name -> routers.tenant_id
        if (Schema::hasTable('hotspot_sales') && Schema::hasColumn('hotspot_sales', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE hotspot_sales hs
                    LEFT JOIN routers r ON (r.name = hs.router_name OR r.router_name = hs.router_name)
                    SET hs.tenant_id = COALESCE(r.tenant_id, 1)
                    WHERE hs.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 6. HotspotVoucher: match router_name -> routers.tenant_id
        if (Schema::hasTable('hotspot_vouchers') && Schema::hasColumn('hotspot_vouchers', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE hotspot_vouchers hv
                    LEFT JOIN routers r ON (r.name = hv.router_name OR r.router_name = hv.router_name)
                    SET hv.tenant_id = COALESCE(r.tenant_id, 1)
                    WHERE hv.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 7. MpesaStkTransaction: match customer_unique_id -> customers_infos.tenant_id
        if (Schema::hasTable('mpesa_stk_transactions') && Schema::hasColumn('mpesa_stk_transactions', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE mpesa_stk_transactions m
                    LEFT JOIN customers_infos c ON c.customer_unique_id = m.customer_unique_id
                    SET m.tenant_id = COALESCE(c.tenant_id, 1)
                    WHERE m.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 8. PaymentSummary: backfill null tenant_id
        if (Schema::hasTable('payment_summaries') && Schema::hasColumn('payment_summaries', 'tenant_id')) {
            try {
                if (Schema::hasColumn('payment_summaries', 'customer_payment_unique_id')) {
                    DB::statement("
                        UPDATE payment_summaries ps
                        LEFT JOIN customers_infos c ON c.customer_unique_id = ps.customer_payment_unique_id
                        SET ps.tenant_id = COALESCE(c.tenant_id, 1)
                        WHERE ps.tenant_id IS NULL
                    ");
                } else {
                    DB::table('payment_summaries')->whereNull('tenant_id')->update(['tenant_id' => 1]);
                }
            } catch (\Throwable $e) {
                DB::table('payment_summaries')->whereNull('tenant_id')->update(['tenant_id' => 1]);
            }
        }

        // 9. UnmatchedPayments: backfill
        if (Schema::hasTable('unmatched_payments') && Schema::hasColumn('unmatched_payments', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE unmatched_payments up
                    LEFT JOIN users u ON u.id = up.assigned_by
                    SET up.tenant_id = COALESCE(u.tenant_id, 1)
                    WHERE up.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 10. IspExpense: match added_by -> users.tenant_id
        if (Schema::hasTable('isp_expenses') && Schema::hasColumn('isp_expenses', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE isp_expenses ie
                    LEFT JOIN users u ON u.id = ie.added_by
                    SET ie.tenant_id = COALESCE(u.tenant_id, 1)
                    WHERE ie.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 11. Reseller: match user_id -> users.tenant_id
        if (Schema::hasTable('resellers') && Schema::hasColumn('resellers', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE resellers res
                    LEFT JOIN users u ON u.id = res.user_id
                    SET res.tenant_id = COALESCE(u.tenant_id, 1)
                    WHERE res.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 12. MainSiteData: default existing records to Tenant #1
        if (Schema::hasTable('main_site_data') && Schema::hasColumn('main_site_data', 'tenant_id')) {
            DB::table('main_site_data')->whereNull('tenant_id')->update(['tenant_id' => 1]);
        }

        // 13. RADIUS tables (nas, radcheck, radreply, radacct)
        if (Schema::hasTable('nas') && Schema::hasColumn('nas', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE nas n
                    LEFT JOIN routers r ON (r.vpn_ip COLLATE utf8mb4_unicode_ci = n.nasname COLLATE utf8mb4_unicode_ci OR r.ip COLLATE utf8mb4_unicode_ci = n.nasname COLLATE utf8mb4_unicode_ci)
                    SET n.tenant_id = COALESCE(r.tenant_id, 1)
                    WHERE n.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {
                DB::table('nas')->whereNull('tenant_id')->update(['tenant_id' => 1]);
            }
        }

        if (Schema::hasTable('radcheck') && Schema::hasColumn('radcheck', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE radcheck rc
                    LEFT JOIN p_p_p_secrets p ON p.username COLLATE utf8mb4_unicode_ci = rc.username COLLATE utf8mb4_unicode_ci
                    LEFT JOIN hotspot_vouchers hv ON hv.code COLLATE utf8mb4_unicode_ci = rc.username COLLATE utf8mb4_unicode_ci
                    SET rc.tenant_id = COALESCE(p.tenant_id, hv.tenant_id, 1)
                    WHERE rc.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {
                DB::table('radcheck')->whereNull('tenant_id')->update(['tenant_id' => 1]);
            }
        }

        if (Schema::hasTable('radreply') && Schema::hasColumn('radreply', 'tenant_id')) {
            try {
                DB::statement("
                    UPDATE radreply rr
                    LEFT JOIN radcheck rc ON rc.username COLLATE utf8mb4_unicode_ci = rr.username COLLATE utf8mb4_unicode_ci
                    SET rr.tenant_id = COALESCE(rc.tenant_id, 1)
                    WHERE rr.tenant_id IS NULL
                ");
            } catch (\Throwable $e) {
                DB::table('radreply')->whereNull('tenant_id')->update(['tenant_id' => 1]);
            }
        }

        // Generic catch-all for any remaining tables with null tenant_id
        foreach ($this->tenantOwnedTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                $orphans = DB::table($table)->whereNull('tenant_id')->count();
                if ($orphans > 0) {
                    Log::info("[Migration Backfill] Found {$orphans} orphan records in {$table}, assigning to Tenant #1.");
                    DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => 1]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tenantOwnedTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                    try {
                        $tableBlueprint->dropForeign([$table . '_tenant_id_foreign']);
                    } catch (\Throwable $e) {}
                    try {
                        $tableBlueprint->dropColumn('tenant_id');
                    } catch (\Throwable $e) {}
                });
            }
        }
    }
};
