<?php

namespace App\Services;

use App\Models\CollectionSummary;
use App\Models\HotspotSale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IspRevenueService
{
    /**
     * Compute unified, reconciled revenue figures for a tenant or globally for super admin.
     *
     * @param int|null $tenantId Null for Super Admin (all tenants), or integer for isolated tenant
     * @param int|null $year Defaults to current calendar year
     * @return array
     */
    public static function getRevenueReport(?int $tenantId = null, ?int $year = null): array
    {
        $timezone = config('app.timezone') ?: 'Africa/Nairobi';
        $year = $year ?: (int) Carbon::now()->timezone($timezone)->year;
        $isSuperAdmin = ($tenantId === null || $tenantId <= 0);

        $todayDate = Carbon::now()->timezone($timezone)->toDateString();

        // 1. Hotspot Revenue (from hotspot_sales)
        $monthlyHotspot = array_fill(1, 12, 0.0);
        $totalHotspot = 0.0;
        $todayHotspot = 0.0;

        if (Schema::hasTable('hotspot_sales')) {
            $isSqlite = config('database.default') === 'sqlite';
            $monthExpr = $isSqlite ? "CAST(strftime('%m', COALESCE(sale_date, created_at)) AS INTEGER)" : "MONTH(COALESCE(sale_date, created_at))";
            $yearExpr = $isSqlite ? "CAST(strftime('%Y', COALESCE(sale_date, created_at)) AS INTEGER)" : "YEAR(COALESCE(sale_date, created_at))";

            $hsQuery = DB::table('hotspot_sales')
                ->where('amount', '>', 0)
                ->whereRaw("{$yearExpr} = ?", [$year]);

            if (!$isSuperAdmin && Schema::hasColumn('hotspot_sales', 'tenant_id')) {
                if ($tenantId == 1) {
                    $hsQuery->where(function ($q) {
                        $q->where('tenant_id', 1)->orWhereNull('tenant_id');
                    });
                } else {
                    $hsQuery->where('tenant_id', $tenantId);
                }
            }

            // Group by month
            $hsMonthly = (clone $hsQuery)
                ->selectRaw("{$monthExpr} as m, SUM(amount) as total")
                ->groupBy('m')
                ->pluck('total', 'm')
                ->toArray();

            foreach ($hsMonthly as $m => $sum) {
                $mInt = (int) $m;
                if ($mInt >= 1 && $mInt <= 12) {
                    $monthlyHotspot[$mInt] = round((float) $sum, 2);
                }
            }
            $totalHotspot = round(array_sum($monthlyHotspot), 2);

            // Today's Hotspot
            $todayHsQuery = DB::table('hotspot_sales')
                ->where('amount', '>', 0)
                ->where(function ($q) use ($todayDate) {
                    $q->whereDate('sale_date', $todayDate)
                      ->orWhereDate('created_at', $todayDate);
                });
            if (!$isSuperAdmin && Schema::hasColumn('hotspot_sales', 'tenant_id')) {
                if ($tenantId == 1) {
                    $todayHsQuery->where(function ($q) {
                        $q->where('tenant_id', 1)->orWhereNull('tenant_id');
                    });
                } else {
                    $todayHsQuery->where('tenant_id', $tenantId);
                }
            }
            $todayHotspot = round((float) $todayHsQuery->sum('amount'), 2);
        }

        // 2. PPPoE Collections (from collection_summaries)
        $monthlyPppoe = array_fill(1, 12, 0.0);
        $totalPppoe = 0.0;
        $todayPppoe = 0.0;

        if (Schema::hasTable('collection_summaries')) {
            $isSqlite = config('database.default') === 'sqlite';
            $monthExpr = $isSqlite ? "CAST(strftime('%m', COALESCE(collection_date, created_at)) AS INTEGER)" : "MONTH(COALESCE(collection_date, created_at))";
            $yearExpr = $isSqlite ? "CAST(strftime('%Y', COALESCE(collection_date, created_at)) AS INTEGER)" : "YEAR(COALESCE(collection_date, created_at))";

            $colQuery = DB::table('collection_summaries')
                ->where('collection_amount', '>', 0)
                ->whereRaw("{$yearExpr} = ?", [$year]);

            if (!$isSuperAdmin) {
                if (Schema::hasColumn('collection_summaries', 'tenant_id')) {
                    if ($tenantId == 1) {
                        $colQuery->where(function ($q) {
                            $q->where('collection_summaries.tenant_id', 1)->orWhereNull('collection_summaries.tenant_id');
                        });
                    } else {
                        $colQuery->where('collection_summaries.tenant_id', $tenantId);
                    }
                } elseif (Schema::hasTable('customers_infos')) {
                    $colQuery->join('customers_infos', function ($join) {
                        $join->on('collection_summaries.customer_collection_unique_id', '=', 'customers_infos.customer_unique_id')
                             ->orOn('collection_summaries.customer_collection_unique_id', '=', 'customers_infos.mobile');
                    });
                    if ($tenantId == 1) {
                        $colQuery->where(function ($q) {
                            $q->where('customers_infos.tenant_id', 1)->orWhereNull('customers_infos.tenant_id');
                        });
                    } else {
                        $colQuery->where('customers_infos.tenant_id', $tenantId);
                    }
                }
            }

            $colMonthly = (clone $colQuery)
                ->selectRaw("{$monthExpr} as m, SUM(collection_amount) as total")
                ->groupBy('m')
                ->pluck('total', 'm')
                ->toArray();

            foreach ($colMonthly as $m => $sum) {
                $mInt = (int) $m;
                if ($mInt >= 1 && $mInt <= 12) {
                    $monthlyPppoe[$mInt] = round((float) $sum, 2);
                }
            }
            $totalPppoe = round(array_sum($monthlyPppoe), 2);

            // Today's PPPoE
            $todayColQuery = DB::table('collection_summaries')
                ->where('collection_amount', '>', 0)
                ->where(function ($q) use ($todayDate) {
                    $q->whereDate('collection_date', $todayDate)
                      ->orWhereDate('created_at', $todayDate);
                });
            if (!$isSuperAdmin) {
                if (Schema::hasColumn('collection_summaries', 'tenant_id')) {
                    if ($tenantId == 1) {
                        $todayColQuery->where(function ($q) {
                            $q->where('collection_summaries.tenant_id', 1)->orWhereNull('collection_summaries.tenant_id');
                        });
                    } else {
                        $todayColQuery->where('collection_summaries.tenant_id', $tenantId);
                    }
                } elseif (Schema::hasTable('customers_infos')) {
                    $todayColQuery->join('customers_infos', function ($join) {
                        $join->on('collection_summaries.customer_collection_unique_id', '=', 'customers_infos.customer_unique_id')
                             ->orOn('collection_summaries.customer_collection_unique_id', '=', 'customers_infos.mobile');
                    });
                    if ($tenantId == 1) {
                        $todayColQuery->where(function ($q) {
                            $q->where('customers_infos.tenant_id', 1)->orWhereNull('customers_infos.tenant_id');
                        });
                    } else {
                        $todayColQuery->where('customers_infos.tenant_id', $tenantId);
                    }
                }
            }
            $todayPppoe = round((float) $todayColQuery->sum('collection_amount'), 2);
        }

        // 3. Platform Subscriptions (Platform fee / tenant subscriptions to platform - Super Admin only)
        $monthlySubscriptions = array_fill(1, 12, 0.0);
        $totalSubscriptions = 0.0;

        if ($isSuperAdmin) {
            $subTable = null;
            if (Schema::hasTable('subscription_payments')) {
                $subTable = 'subscription_payments';
            } elseif (Schema::hasTable('payment_transactions')) {
                $subTable = 'payment_transactions';
            }

            if ($subTable) {
                $isSqlite = config('database.default') === 'sqlite';
                $monthExpr = $isSqlite ? "CAST(strftime('%m', created_at) AS INTEGER)" : "MONTH(created_at)";
                $yearExpr = $isSqlite ? "CAST(strftime('%Y', created_at) AS INTEGER)" : "YEAR(created_at)";

                $subQuery = DB::table($subTable)
                    ->whereRaw("{$yearExpr} = ?", [$year]);

                if (Schema::hasColumn($subTable, 'status')) {
                    $subQuery->where(function ($q) {
                        $q->where('status', 'completed')->orWhere('status', 'paid');
                    });
                }

                $amtCol = Schema::hasColumn($subTable, 'amount') ? 'amount' : 'amount_paid';
                $subMonthly = (clone $subQuery)
                    ->selectRaw("{$monthExpr} as m, SUM({$amtCol}) as total")
                    ->groupBy('m')
                    ->pluck('total', 'm')
                    ->toArray();

                foreach ($subMonthly as $m => $sum) {
                    $mInt = (int) $m;
                    if ($mInt >= 1 && $mInt <= 12) {
                        $monthlySubscriptions[$mInt] = round((float) $sum, 2);
                    }
                }
                $totalSubscriptions = round(array_sum($monthlySubscriptions), 2);
            }
        }

        // 4. Build Reconciled Month-by-Month Breakdown (Jan..Dec)
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        $monthlyBreakdown = [];
        $totalAllMonths = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $h = $monthlyHotspot[$m];
            $p = $monthlyPppoe[$m];
            $s = $isSuperAdmin ? $monthlySubscriptions[$m] : 0.0;
            $monthTotal = round($h + $p + $s, 2);
            $totalAllMonths = round($totalAllMonths + $monthTotal, 2);

            $monthlyBreakdown[$m] = [
                'month'         => $m,
                'name'          => $monthNames[$m],
                'hotspot'       => $h,
                'pppoe'         => $p,
                'subscriptions' => $s,
                'total'         => $monthTotal,
            ];
        }

        // 5. Total Global / Tenant Revenue
        $totalRevenue = $isSuperAdmin
            ? round($totalHotspot + $totalPppoe + $totalSubscriptions, 2)
            : round($totalHotspot + $totalPppoe, 2);

        $todayTotal = round($todayHotspot + $todayPppoe, 2);

        // Pending Due / Arrears (for tenant)
        $dueAmount = 0.0;
        if (!$isSuperAdmin && Schema::hasTable('billing_infos')) {
            $bQuery = DB::table('billing_infos');
            if (Schema::hasColumn('billing_infos', 'tenant_id')) {
                $bQuery->where('tenant_id', $tenantId);
            }
            $dueAmount = (float) $bQuery->sum('due_amount');
            if ($dueAmount < 0) $dueAmount = -1 * $dueAmount;
        }

        return [
            'year'                 => $year,
            'tenant_id'            => $tenantId,
            'is_super_admin'       => $isSuperAdmin,
            'total_revenue'        => $totalRevenue,
            'hotspot_revenue'      => $totalHotspot,
            'pppoe_revenue'        => $totalPppoe,
            'subscription_revenue' => $totalSubscriptions,
            'today_revenue'        => $todayTotal,
            'today_hotspot'        => $todayHotspot,
            'today_pppoe'          => $todayPppoe,
            'due_amount'           => $dueAmount,
            'monthly'              => $monthlyBreakdown,
            'reconciled'           => (abs($totalRevenue - $totalAllMonths) < 0.01),
        ];
    }
}

