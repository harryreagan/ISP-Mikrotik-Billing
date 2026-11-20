<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance package_lists with sync status tracking & clean orphan seeded NULL-tenant packages
        if (Schema::hasTable('package_lists')) {
            Schema::table('package_lists', function (Blueprint $table) {
                if (!Schema::hasColumn('package_lists', 'sync_status')) {
                    $table->string('sync_status', 32)->default('pending')->after('push_to_mikrotik');
                }
                if (!Schema::hasColumn('package_lists', 'last_sync_error')) {
                    $table->text('last_sync_error')->nullable()->after('sync_status');
                }
                if (!Schema::hasColumn('package_lists', 'last_synced_at')) {
                    $table->timestamp('last_synced_at')->nullable()->after('last_sync_error');
                }
            });

            // Remove orphan NULL-tenant default seeded packages ("1 Hour Hotspot", "24 Hours Unlimited", "30 Days Home Fiber", etc.)
            $orphanPkgIds = DB::table('package_lists')->whereNull('tenant_id')->pluck('id')->toArray();
            if (!empty($orphanPkgIds)) {
                if (Schema::hasTable('customers_infos') && Schema::hasColumn('customers_infos', 'package_id')) {
                    DB::table('customers_infos')->whereIn('package_id', $orphanPkgIds)->update(['package_id' => null]);
                }
                if (Schema::hasTable('package_router')) {
                    DB::table('package_router')->whereIn('package_id', $orphanPkgIds)->delete();
                }
                if (Schema::hasTable('vouchers') && Schema::hasColumn('vouchers', 'package_id')) {
                    DB::table('vouchers')->whereIn('package_id', $orphanPkgIds)->update(['package_id' => null]);
                }
                if (Schema::hasTable('hotspot_vouchers') && Schema::hasColumn('hotspot_vouchers', 'plan_id')) {
                    DB::table('hotspot_vouchers')->whereIn('plan_id', $orphanPkgIds)->update(['plan_id' => null]);
                }
                DB::table('package_lists')->whereIn('id', $orphanPkgIds)->delete();
            }

            // Ensure explicit package type (hotspot | pppoe) on every remaining package
            $packages = DB::table('package_lists')->get(['id', 'package', 'type']);
            foreach ($packages as $pkg) {
                $t = strtolower(trim((string) ($pkg->type ?? '')));
                if (!in_array($t, ['hotspot', 'pppoe'], true)) {
                    $nameLower = strtolower((string) $pkg->package);
                    $resolvedType = (str_contains($nameLower, 'pppoe') || str_contains($nameLower, 'fiber') || str_contains($nameLower, 'static'))
                        ? 'pppoe'
                        : 'hotspot';
                    DB::table('package_lists')->where('id', $pkg->id)->update(['type' => $resolvedType]);
                }
            }

            // Sync package_lists.sync_status from existing package_router pivots
            if (Schema::hasTable('package_router')) {
                $pivots = DB::table('package_router')->get(['package_id', 'sync_status', 'sync_error', 'synced_at'])->groupBy('package_id');
                foreach ($pivots as $pkgId => $rows) {
                    $hasFailed = $rows->contains(fn($r) => ($r->sync_status ?? '') === 'failed');
                    $allSynced = $rows->every(fn($r) => ($r->sync_status ?? '') === 'synced');
                    $latestSyncedAt = $rows->pluck('synced_at')->filter()->sortDesc()->first();
                    $firstError = $rows->pluck('sync_error')->filter()->first();

                    DB::table('package_lists')->where('id', $pkgId)->update([
                        'sync_status'     => $allSynced ? 'synced' : ($hasFailed ? 'failed' : 'pending'),
                        'last_sync_error' => $hasFailed ? $firstError : null,
                        'last_synced_at'  => $latestSyncedAt,
                    ]);
                }
            }
        }

        // 2. Relink / Clean CustomersInfo & BillingInfo (FIX 1: Remove false KES 50 / Standard Package defaults)
        if (Schema::hasTable('customers_infos')) {
            // Fix HS-* customers marked incorrectly as pppoe
            DB::table('customers_infos')
                ->where('customer_unique_id', 'like', 'HS-%')
                ->where('service_type', 'pppoe')
                ->update(['service_type' => 'hotspot']);

            $customers = DB::table('customers_infos')->get(['id', 'tenant_id', 'customer_unique_id', 'mobile', 'ppp_user_id', 'package_id', 'service_type']);
            foreach ($customers as $cust) {
                $tenantId = (int) ($cust->tenant_id ?: 1);
                if (empty($cust->tenant_id)) {
                    DB::table('customers_infos')->where('id', $cust->id)->update(['tenant_id' => $tenantId]);
                }

                $validPkg = null;
                if (!empty($cust->package_id)) {
                    $validPkg = DB::table('package_lists')
                        ->where('id', $cust->package_id)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    if (!$validPkg) {
                        DB::table('customers_infos')->where('id', $cust->id)->update(['package_id' => null]);
                    }
                }

                // Attempt to relink from customer's secret profile or hotspot_sales within the same tenant
                if (!$validPkg) {
                    $profileCandidate = null;
                    if (!empty($cust->ppp_user_id) && Schema::hasTable('p_p_p_secrets')) {
                        $sec = DB::table('p_p_p_secrets')->where('id', $cust->ppp_user_id)->first(['profile']);
                        $profileCandidate = $sec->profile ?? null;
                    }
                    if ((empty($profileCandidate) || strtolower($profileCandidate) === 'default') && !empty($cust->mobile) && Schema::hasTable('hotspot_sales')) {
                        $sale = DB::table('hotspot_sales')
                            ->where('tenant_id', $tenantId)
                            ->where('phone', $cust->mobile)
                            ->orderByDesc('id')
                            ->first(['profile']);
                        $profileCandidate = $sale->profile ?? $profileCandidate;
                    }

                    if (!empty($profileCandidate) && strtolower(trim($profileCandidate)) !== 'default') {
                        $cleanProfile = trim($profileCandidate);
                        $stripped = preg_replace('/^(hs_|ppp_)/', '', $cleanProfile);
                        $validPkg = DB::table('package_lists')
                            ->where('tenant_id', $tenantId)
                            ->where(function ($q) use ($cleanProfile, $stripped) {
                                $q->whereRaw('LOWER(TRIM(package)) = ?', [strtolower($cleanProfile)])
                                  ->orWhereRaw('LOWER(TRIM(package)) = ?', [strtolower($stripped)]);
                            })
                            ->first();
                        if ($validPkg) {
                            DB::table('customers_infos')->where('id', $cust->id)->update([
                                'package_id'   => $validPkg->id,
                                'service_type' => $validPkg->type ?: $cust->service_type,
                            ]);
                        }
                    }
                }

                // Clean hardcoded synthetic 50 / 1500 monthly_rent on billing_infos if unlinked, or align to real package price
                if (Schema::hasTable('billing_infos')) {
                    $bill = DB::table('billing_infos')->where('customer_bill_unique_id', $cust->customer_unique_id)->first();
                    if ($bill) {
                        if ($validPkg) {
                            DB::table('billing_infos')->where('id', $bill->id)->update([
                                'monthly_rent' => (float) $validPkg->price,
                            ]);
                        } elseif (in_array((float) $bill->monthly_rent, [50.0, 1500.0], true)) {
                            DB::table('billing_infos')->where('id', $bill->id)->update([
                                'monthly_rent' => 0.00,
                                'total_amount' => 0.00,
                            ]);
                        }
                    }
                }
            }
        }

        // 3. Enhance collection_summaries & mpesa_stk_transactions and backfill service_type & subscriber info (FIX 3)
        if (Schema::hasTable('mpesa_stk_transactions') && !Schema::hasColumn('mpesa_stk_transactions', 'service_type')) {
            Schema::table('mpesa_stk_transactions', function (Blueprint $table) {
                $table->string('service_type', 32)->nullable()->index()->after('reference');
            });
        }

        if (Schema::hasTable('collection_summaries')) {
            Schema::table('collection_summaries', function (Blueprint $table) {
                if (!Schema::hasColumn('collection_summaries', 'service_type')) {
                    $table->string('service_type', 32)->default('pppoe')->index()->after('payment_type');
                }
                if (!Schema::hasColumn('collection_summaries', 'package_id')) {
                    $table->unsignedBigInteger('package_id')->nullable()->index()->after('service_type');
                }
                if (!Schema::hasColumn('collection_summaries', 'subscriber_name')) {
                    $table->string('subscriber_name', 255)->nullable()->after('customer_collection_unique_id');
                }
                if (!Schema::hasColumn('collection_summaries', 'phone')) {
                    $table->string('phone', 32)->nullable()->after('subscriber_name');
                }
                if (!Schema::hasColumn('collection_summaries', 'mac_address')) {
                    $table->string('mac_address', 64)->nullable()->after('phone');
                }
                if (!Schema::hasColumn('collection_summaries', 'router_id')) {
                    $table->unsignedBigInteger('router_id')->nullable()->index()->after('mac_address');
                }
            });

            // Backfill existing collection_summaries
            $rows = DB::table('collection_summaries')->get();
            foreach ($rows as $row) {
                $txCode = trim((string) ($row->transaction_id ?? ''));
                $custKey = trim((string) ($row->customer_collection_unique_id ?? ''));

                $hsSale = $txCode && Schema::hasTable('hotspot_sales')
                    ? DB::table('hotspot_sales')->where('voucher_code', $txCode)->first()
                    : null;

                $stkTx = $txCode && Schema::hasTable('mpesa_stk_transactions')
                    ? DB::table('mpesa_stk_transactions')->where('mpesa_receipt_number', $txCode)->first()
                    : null;

                $cust = null;
                if ($custKey && Schema::hasTable('customers_infos')) {
                    $cust = DB::table('customers_infos')
                        ->where('customer_unique_id', $custKey)
                        ->orWhere('mobile', $custKey)
                        ->first();
                }

                $tenantId = (int) ($row->tenant_id ?: ($hsSale->tenant_id ?? ($stkTx->tenant_id ?? ($cust->tenant_id ?? 1))));

                $payTypeLower = strtolower((string) ($row->payment_type ?? ''));
                $collByLower = strtolower((string) ($row->collected_by ?? ''));
                $stkMeta = ($stkTx && !empty($stkTx->raw_callback)) ? json_decode($stkTx->raw_callback, true) : [];
                $stkRef = (string) ($stkTx->reference ?? '');
                $isStkHotspot = !empty($stkMeta['package_id']) || !empty($stkMeta['router_id']) || !empty($stkMeta['mac']) || str_starts_with($stkRef, 'HSM-HS');

                $serviceType = ($payTypeLower === 'hotspot' || $hsSale || $isStkHotspot || str_contains($collByLower, 'hotspot') || str_starts_with($custKey, 'HS-'))
                    ? 'hotspot'
                    : ($cust && $cust->service_type === 'hotspot' ? 'hotspot' : 'pppoe');

                $packageId = $row->package_id ?: (!empty($stkMeta['package_id']) ? (int) $stkMeta['package_id'] : ($cust->package_id ?? null));
                if (!$packageId && $hsSale && !empty($hsSale->profile)) {
                    $matchedPkg = DB::table('package_lists')
                        ->where('tenant_id', $tenantId)
                        ->whereRaw('LOWER(TRIM(package)) = ?', [strtolower(trim($hsSale->profile))])
                        ->first(['id']);
                    $packageId = $matchedPkg->id ?? null;
                }

                $phone = $row->phone ?: ($hsSale->phone ?? ($stkTx->phone ?? ($cust->mobile ?? (preg_match('/^(?:254|0)[17]\d{8}$/', $custKey) ? $custKey : null))));
                $subName = $row->subscriber_name ?: ($hsSale?->voucher_code ?: ($cust?->customer_name ?: ($phone ?: ($custKey ?: $txCode))));
                $mac = $row->mac_address ?: ($hsSale->mac_address ?? ($stkMeta['mac'] ?? null));
                $routerId = $row->router_id ?: ($hsSale->router_id ?? (!empty($stkMeta['router_id']) ? (int) $stkMeta['router_id'] : ($cust->router_id ?? null)));

                DB::table('collection_summaries')->where('id', $row->id)->update([
                    'tenant_id'       => $tenantId,
                    'service_type'    => $serviceType,
                    'package_id'      => $packageId,
                    'subscriber_name' => $subName,
                    'phone'           => $phone,
                    'mac_address'     => $mac ? strtoupper($mac) : null,
                    'router_id'       => $routerId,
                ]);

                if ($stkTx) {
                    DB::table('mpesa_stk_transactions')->where('id', $stkTx->id)->update([
                        'service_type' => $serviceType,
                        'tenant_id'    => $stkTx->tenant_id ?: $tenantId,
                    ]);
                }
            }

            // Ensure every hotspot_sales record has a corresponding collection_summaries ledger entry
            if (Schema::hasTable('hotspot_sales')) {
                $sales = DB::table('hotspot_sales')
                    ->whereIn('status', ['completed', 'active', 'expired', 'pending_router'])
                    ->whereNotNull('voucher_code')
                    ->get();

                $maxInv = (int) (DB::table('collection_summaries')->max('invoice_no') ?: 100000);
                foreach ($sales as $sale) {
                    $code = strtoupper(trim((string) $sale->voucher_code));
                    if (empty($code)) {
                        continue;
                    }
                    $exists = DB::table('collection_summaries')->where('transaction_id', $code)->exists();
                    if (!$exists) {
                        $maxInv++;
                        $tenantId = (int) ($sale->tenant_id ?: 1);
                        $matchedPkgId = null;
                        if (!empty($sale->profile)) {
                            $pRow = DB::table('package_lists')
                                ->where('tenant_id', $tenantId)
                                ->whereRaw('LOWER(TRIM(package)) = ?', [strtolower(trim($sale->profile))])
                                ->first(['id']);
                            $matchedPkgId = $pRow->id ?? null;
                        }
                        DB::table('collection_summaries')->insert([
                            'tenant_id'                     => $tenantId,
                            'customer_collection_unique_id' => $sale->phone ?: $code,
                            'subscriber_name'               => $code . ($sale->phone ? ' (' . $sale->phone . ')' : ''),
                            'phone'                         => $sale->phone,
                            'mac_address'                   => $sale->mac_address ? strtoupper($sale->mac_address) : null,
                            'router_id'                     => $sale->router_id,
                            'collection_date'               => $sale->created_at ?: now(),
                            'collection_amount'             => (float) $sale->amount,
                            'collected_by'                  => 'Hotspot M-Pesa',
                            'payment_type'                  => 'hotspot',
                            'service_type'                  => 'hotspot',
                            'package_id'                    => $matchedPkgId,
                            'payment_method'                => $sale->payment_method ?: 'mpesa',
                            'transaction_id'                => $code,
                            'payment_status'                => 'paid',
                            'invoice_no'                    => $maxInv,
                            'bill_month'                    => Carbon::parse($sale->created_at ?: now())->format('F Y'),
                            'created_at'                    => $sale->created_at ?: now(),
                            'updated_at'                    => now(),
                        ]);
                    }
                }
            }
        }

        // 4. Add Roaming option on isp_portal_settings and create unified prepaid_users table (FEATURE)
        if (Schema::hasTable('isp_portal_settings') && !Schema::hasColumn('isp_portal_settings', 'allow_roaming')) {
            Schema::table('isp_portal_settings', function (Blueprint $table) {
                $table->boolean('allow_roaming')->default(true)->after('free_trial_duration');
            });
        }

        if (!Schema::hasTable('prepaid_users')) {
            Schema::create('prepaid_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('username', 120)->index();
                $table->string('password', 120)->nullable();
                $table->string('phone', 32)->nullable()->index();
                $table->string('mac_address', 64)->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->unsignedBigInteger('package_id')->nullable()->index();
                $table->string('plan_name', 120)->nullable();
                $table->string('type', 20)->default('hotspot')->index(); // hotspot | pppoe | static
                $table->unsignedBigInteger('router_id')->nullable()->index();
                $table->string('router_name', 120)->nullable();
                $table->unsignedBigInteger('roaming_router_id')->nullable()->index();
                $table->string('roaming_router_name', 120)->nullable();
                $table->boolean('is_roaming')->default(false)->index();
                $table->string('method', 120)->nullable(); // e.g. Mpesa STK + receipt code
                $table->string('receipt_code', 64)->nullable()->index();
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->string('status', 24)->default('offline')->index(); // online | offline | expired | disabled
                $table->boolean('is_online')->default(false)->index();
                $table->boolean('was_connected')->default(false);
                $table->boolean('reconnection_sms_sent')->default(false);
                $table->timestamp('reconnection_sms_sent_at')->nullable();
                $table->string('disconnection_reason', 64)->nullable();
                $table->boolean('fup_enabled')->default(false);
                $table->unsignedInteger('fup_limit_mb')->nullable();
                $table->unsignedBigInteger('daily_data_bytes')->default(0);
                $table->unsignedBigInteger('total_data_bytes')->default(0);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->unsignedBigInteger('hotspot_sale_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('ppp_secret_id')->nullable()->index();
                $table->timestamps();

                $table->unique(['tenant_id', 'username', 'type'], 'prepaid_users_tenant_user_type_unique');
            });
        }

        // Backfill prepaid_users from hotspot_sales and customers_infos
        if (Schema::hasTable('prepaid_users')) {
            if (Schema::hasTable('hotspot_sales')) {
                $sales = DB::table('hotspot_sales')->orderBy('id')->get();
                foreach ($sales as $sale) {
                    $uName = strtoupper(trim((string) ($sale->voucher_code ?: $sale->username)));
                    if (empty($uName)) {
                        continue;
                    }
                    $tenantId = (int) ($sale->tenant_id ?: 1);
                    $pkg = null;
                    if (!empty($sale->profile)) {
                        $pkg = DB::table('package_lists')
                            ->where('tenant_id', $tenantId)
                            ->whereRaw('LOWER(TRIM(package)) = ?', [strtolower(trim($sale->profile))])
                            ->first();
                    }
                    $isExp = ($sale->status === 'expired') || ($sale->expires_at && Carbon::parse($sale->expires_at)->isPast());
                    $methodLabel = str_contains(strtolower((string) $sale->payment_method), 'mpesa') || str_contains(strtolower((string) $sale->payment_method), 'payhero') || str_contains(strtolower((string) $sale->payment_method), 'daraja')
                        ? 'Mpesa STK' . ($sale->voucher_code ? ' + ' . strtoupper($sale->voucher_code) : '')
                        : ucfirst((string) ($sale->payment_method ?: 'Manual'));

                    DB::table('prepaid_users')->updateOrInsert(
                        [
                            'tenant_id' => $tenantId,
                            'username'  => $uName,
                            'type'      => 'hotspot',
                        ],
                        [
                            'password'             => $uName,
                            'phone'                => $sale->phone,
                            'mac_address'          => $sale->mac_address ? strtoupper($sale->mac_address) : null,
                            'ip_address'           => $sale->ip_address,
                            'package_id'           => $pkg->id ?? null,
                            'plan_name'            => $pkg->package ?? ($sale->profile ?: null),
                            'router_id'            => $sale->router_id,
                            'router_name'          => $sale->router_name,
                            'method'               => $methodLabel,
                            'receipt_code'         => $sale->voucher_code ? strtoupper($sale->voucher_code) : null,
                            'amount'               => (float) $sale->amount,
                            'status'               => $isExp ? 'expired' : 'offline',
                            'is_online'            => false,
                            'was_connected'        => !empty($sale->mac_address) || !empty($sale->ip_address),
                            'disconnection_reason' => $isExp ? 'expired' : null,
                            'fup_enabled'          => !empty($pkg?->data_limit_mb),
                            'fup_limit_mb'         => $pkg->data_limit_mb ?? null,
                            'started_at'           => $sale->started_at ?: $sale->created_at,
                            'expires_at'           => $sale->expires_at,
                            'hotspot_sale_id'      => $sale->id,
                            'created_at'           => $sale->created_at ?: now(),
                            'updated_at'           => now(),
                        ]
                    );
                }
            }

            if (Schema::hasTable('customers_infos')) {
                $custs = DB::table('customers_infos')->whereNull('deleted_at')->get();
                foreach ($custs as $cust) {
                    $tenantId = (int) ($cust->tenant_id ?: 1);
                    $secret = (!empty($cust->ppp_user_id) && Schema::hasTable('p_p_p_secrets'))
                        ? DB::table('p_p_p_secrets')->where('id', $cust->ppp_user_id)->first()
                        : null;
                    $uName = trim((string) ($secret->username ?? $cust->customer_unique_id));
                    $type = in_array($cust->service_type, ['hotspot', 'pppoe', 'static'], true)
                        ? $cust->service_type
                        : ($secret->service ?? 'pppoe');

                    $pkg = !empty($cust->package_id)
                        ? DB::table('package_lists')->where('id', $cust->package_id)->where('tenant_id', $tenantId)->first()
                        : null;

                    $bill = Schema::hasTable('billing_infos')
                        ? DB::table('billing_infos')->where('customer_bill_unique_id', $cust->customer_unique_id)->first()
                        : null;

                    $router = !empty($cust->router_id)
                        ? DB::table('routers')->where('id', $cust->router_id)->first(['id', 'name'])
                        : null;

                    $expiresAt = $bill->auto_disable_date ?? null;
                    $isDisabled = in_array($cust->status, ['disable', 'suspended', 'inactive'], true);
                    $isExpired = !$isDisabled && $expiresAt && Carbon::parse($expiresAt)->isPast();

                    // Do not overwrite if already created cleanly from a recent hotspot_sale
                    $existing = DB::table('prepaid_users')
                        ->where('tenant_id', $tenantId)
                        ->where('username', $uName)
                        ->where('type', $type)
                        ->first();
                    if ($existing) {
                        DB::table('prepaid_users')->where('id', $existing->id)->update([
                            'customer_id'   => $cust->id,
                            'ppp_secret_id' => $secret->id ?? null,
                        ]);
                        continue;
                    }

                    DB::table('prepaid_users')->insert([
                        'tenant_id'            => $tenantId,
                        'username'             => $uName,
                        'password'             => $secret->password ?? null,
                        'phone'                => $cust->mobile,
                        'mac_address'          => $secret->caller_id ?? null,
                        'ip_address'           => $secret->ppp_remote_ip ?? null,
                        'package_id'           => $pkg->id ?? null,
                        'plan_name'            => $pkg->package ?? null,
                        'type'                 => $type,
                        'router_id'            => $cust->router_id ?: ($secret->router_id ?? null),
                        'router_name'          => $router->name ?? ($secret->router_name ?? null),
                        'method'               => 'Customer Account',
                        'amount'               => (float) ($pkg->price ?? 0),
                        'status'               => $isDisabled ? 'disabled' : ($isExpired ? 'expired' : 'offline'),
                        'is_online'            => false,
                        'was_connected'        => !empty($secret->caller_id) || !empty($secret->ppp_remote_ip),
                        'disconnection_reason' => $isDisabled ? 'disabled' : ($isExpired ? 'expired' : null),
                        'fup_enabled'          => !empty($pkg?->data_limit_mb),
                        'fup_limit_mb'         => $pkg->data_limit_mb ?? null,
                        'started_at'           => $cust->connection_date ?: $cust->created_at,
                        'expires_at'           => $expiresAt,
                        'customer_id'          => $cust->id,
                        'ppp_secret_id'        => $secret->id ?? null,
                        'created_at'           => $cust->created_at ?: now(),
                        'updated_at'           => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prepaid_users');

        if (Schema::hasTable('isp_portal_settings') && Schema::hasColumn('isp_portal_settings', 'allow_roaming')) {
            Schema::table('isp_portal_settings', function (Blueprint $table) {
                $table->dropColumn('allow_roaming');
            });
        }

        if (Schema::hasTable('collection_summaries')) {
            Schema::table('collection_summaries', function (Blueprint $table) {
                foreach (['service_type', 'package_id', 'subscriber_name', 'phone', 'mac_address', 'router_id'] as $col) {
                    if (Schema::hasColumn('collection_summaries', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('mpesa_stk_transactions') && Schema::hasColumn('mpesa_stk_transactions', 'service_type')) {
            Schema::table('mpesa_stk_transactions', function (Blueprint $table) {
                $table->dropColumn('service_type');
            });
        }

        if (Schema::hasTable('package_lists')) {
            Schema::table('package_lists', function (Blueprint $table) {
                foreach (['sync_status', 'last_sync_error', 'last_synced_at'] as $col) {
                    if (Schema::hasColumn('package_lists', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
