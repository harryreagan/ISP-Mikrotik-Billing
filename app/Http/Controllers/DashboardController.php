<?php

namespace App\Http\Controllers;

use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (auth()->check() && method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Reseller')) {
            return redirect()->route('reseller.dashboard');
        }

        $results = [];
        $selectedYear = (int) (request('year') ?: Carbon::now()->year);
        $currentYear = $selectedYear;
        $previousYear = $currentYear - 1;
        $availableYears = [2024, 2025, 2026, 2027];

        $user = auth()->user();
        $isSuperAdmin = ($user && in_array(strtolower($user->role ?? ''), ['superadmin', 'super_admin'])) || ($user && empty($user->tenant_id));
        $scopedTenantId = $isSuperAdmin ? null : (int) ($user->tenant_id ?: 1);

        // Fetch unified, reconciled revenue from IspRevenueService (single source of truth)
        $revenueReport = \App\Services\IspRevenueService::getRevenueReport($scopedTenantId, $selectedYear);

        // Default resilient data structures
        $customersData = [
            'total' => 0,
            'active' => 0,
            'pending' => 0,
            'free' => 0,
            'temporary_disable' => 0,
            'inactive' => 0,
            'recent' => 0,
        ];

        $billInformationData = [
            'monthly_rent' => 0.0,
            'previous_due' => 0.0,
            'advance' => 0.0,
            'paid_amount' => 0.0,
            'today_paid_amount' => 0.0,
            'hotspot_total' => 0.0,
            'hotspot_today' => 0.0,
            'due_amount' => 0.0,
            'by_status' => [
                'active' => ['monthly_rent' => 0, 'advance' => 0, 'due_amount' => 0],
                'free' => ['monthly_rent' => 0, 'advance' => 0, 'due_amount' => 0],
                'inactive' => ['monthly_rent' => 0, 'advance' => 0, 'due_amount' => 0],
                'pending' => ['monthly_rent' => 0, 'advance' => 0, 'due_amount' => 0],
            ],
        ];

        $resellerData = [
            'total_resellers' => 0,
            'active_resellers' => 0,
            'suspended_resellers' => 0,
            'total_balance' => 0.0,
            'total_customers' => 0,
            'active_customers' => 0,
            'pending_customers' => 0,
            'total_commission' => 0.0,
        ];

        // 1. Customers Data (safe fallback between customers_infos and clients)
        try {
            if (Schema::hasTable('customers_infos')) {
                $statusCounts = CustomersInfo::selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                $recentCount = CustomersInfo::whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year)
                    ->count();

                $customersData = [
                    'total' => array_sum($statusCounts),
                    'active' => $statusCounts['active'] ?? 0,
                    'pending' => $statusCounts['pending'] ?? 0,
                    'free' => $statusCounts['free'] ?? 0,
                    'temporary_disable' => $statusCounts['disable'] ?? 0,
                    'inactive' => $statusCounts['inactive'] ?? 0,
                    'recent' => $recentCount,
                ];
            } elseif (Schema::hasTable('clients')) {
                $totalClients = DB::table('clients')->count();
                $activeClients = DB::table('clients')->where('status', 'active')->count();
                $recentClients = DB::table('clients')->whereMonth('created_at', Carbon::now()->month)->count();

                $customersData = [
                    'total' => $totalClients,
                    'active' => $activeClients,
                    'pending' => DB::table('clients')->where('status', 'pending')->count(),
                    'free' => 0,
                    'temporary_disable' => DB::table('clients')->where('status', 'suspended')->count(),
                    'inactive' => DB::table('clients')->where('status', 'inactive')->count(),
                    'recent' => $recentClients,
                ];
            }
        } catch (\Throwable $e) {}

        // 2. Billing Data (safe fallback between billing_infos and payment_transactions)
        try {
            if (Schema::hasTable('billing_infos') && Schema::hasTable('customers_infos')) {
                $billingStats = BillingInfo::join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
                    ->whereNull('customers_infos.deleted_at')
                    ->selectRaw("
                        SUM(billing_infos.monthly_rent) as monthly_rent,
                        SUM(billing_infos.advance) as advance,
                        SUM(billing_infos.paid_amount) as paid_amount,
                        SUM(CASE WHEN customers_infos.status != 'inactive' THEN billing_infos.previous_due ELSE 0 END) as previous_due_active,
                        SUM(CASE WHEN customers_infos.status != 'inactive' THEN billing_infos.due_amount ELSE 0 END) as due_amount_active
                    ")
                    ->first();

                $statusBillingRaw = BillingInfo::join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
                    ->whereNull('customers_infos.deleted_at')
                    ->whereIn('customers_infos.status', ['active', 'free', 'inactive', 'pending'])
                    ->selectRaw('
                        customers_infos.status,
                        SUM(billing_infos.monthly_rent) as monthly_rent,
                        SUM(billing_infos.advance) as advance,
                        SUM(billing_infos.due_amount) as due_amount
                    ')
                    ->groupBy('customers_infos.status')
                    ->get()
                    ->keyBy('status');

                $statuses = ['active', 'free', 'inactive', 'pending'];
                $billingByStatus = [];
                foreach ($statuses as $s) {
                    $row = $statusBillingRaw->get($s);
                    $billingByStatus[$s] = [
                        'monthly_rent' => (float) ($row->monthly_rent ?? 0),
                        'advance' => (float) ($row->advance ?? 0),
                        'due_amount' => (float) ($row->due_amount ?? 0),
                    ];
                }

                $tenantId = auth()->user()?->tenant_id ?: (session('tenant_id') ?: (session('impersonated_tenant_id') ?: 1));
                $todayEat = Carbon::now('Africa/Nairobi')->toDateString();

                $todayPaid = Schema::hasTable('collection_summaries')
                    ? CollectionSummary::whereDate('collection_date', $todayEat)
                        ->where(function ($q) {
                            $q->whereNull('payment_type')->orWhere('payment_type', '!=', 'hotspot');
                        })
                        ->sum('collection_amount')
                    : 0;

                $hotspotTotal = 0.0;
                $hotspotToday = 0.0;
                if (Schema::hasTable('hotspot_sales')) {
                    $hsBase = DB::table('hotspot_sales');
                    if (Schema::hasColumn('hotspot_sales', 'tenant_id') && $tenantId) {
                        $hsBase->where('tenant_id', $tenantId);
                    }
                    $hotspotTotal = (float) (clone $hsBase)->sum('amount');
                    $hotspotToday = (float) (clone $hsBase)->whereDate('sale_date', $todayEat)->sum('amount');
                }

                $billInformationData = [
                    'monthly_rent'         => (float) ($billingStats->monthly_rent ?? 0),
                    'previous_due'         => -1 * (float) ($billingStats->previous_due_active ?? 0),
                    'advance'              => (float) ($billingStats->advance ?? 0),
                    'paid_amount'          => $revenueReport['pppoe_revenue'],
                    'today_paid_amount'    => $revenueReport['today_pppoe'],
                    'hotspot_total'        => $revenueReport['hotspot_revenue'],
                    'hotspot_today'        => $revenueReport['today_hotspot'],
                    'subscription_revenue' => $revenueReport['subscription_revenue'],
                    'total_revenue'        => $revenueReport['total_revenue'],
                    'due_amount'           => -1 * (float) ($billingStats->due_amount_active ?? 0),
                    'by_status'            => $billingByStatus,
                    'year'                 => $selectedYear,
                    'is_super_admin'       => $isSuperAdmin,
                ];
            } elseif (Schema::hasTable('payment_transactions')) {
                $totalPaid = (float) DB::table('payment_transactions')->sum('amount');
                $todayPaid = (float) DB::table('payment_transactions')->whereDate('created_at', Carbon::today())->sum('amount');

                $billInformationData['paid_amount'] = $totalPaid;
                $billInformationData['today_paid_amount'] = $todayPaid;
                $billInformationData['monthly_rent'] = $totalPaid;
            }
        } catch (\Throwable $e) {}

        // 3. Reseller Data
        try {
            if (Schema::hasTable('resellers')) {
                $resellerStatusCounts = Reseller::selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                $totalCommission = Schema::hasTable('reseller_commissions') ? ResellerCommission::sum('amount') : 0;

                $resellerData = [
                    'total_resellers' => array_sum($resellerStatusCounts),
                    'active_resellers' => $resellerStatusCounts['active'] ?? 0,
                    'suspended_resellers' => $resellerStatusCounts['suspended'] ?? 0,
                    'total_balance' => (float) (Reseller::sum('balance') ?? 0),
                    'total_customers' => 0,
                    'active_customers' => 0,
                    'pending_customers' => 0,
                    'total_commission' => (float) $totalCommission,
                ];
            }
        } catch (\Throwable $e) {}

        // 4. Monthly Results Initialization (strictly reconciled from raw payment ledgers)
        for ($month = 1; $month <= 12; $month++) {
            $mTot = (float) ($revenueReport['monthly'][$month]['total'] ?? 0.0);
            $results[$month] = [
                'cashflow_previous_year' => 0.0,
                'income_current_year'    => $mTot,
                'revenue_difference'     => $mTot,
                'hotspot'                => (float) ($revenueReport['monthly'][$month]['hotspot'] ?? 0.0),
                'pppoe'                  => (float) ($revenueReport['monthly'][$month]['pppoe'] ?? 0.0),
                'subscriptions'          => (float) ($revenueReport['monthly'][$month]['subscriptions'] ?? 0.0),
                'name'                   => $revenueReport['monthly'][$month]['name'] ?? "Month {$month}",
            ];
        }

        $systemOverview = [];

        // Ensure all routers configured for THIS tenant (both online and offline) are listed with their telemetry
        try {
            if (Schema::hasTable('routers')) {
                $tenantId = auth()->user()?->tenant_id ?: (session('tenant_id') ?: (session('impersonated_tenant_id') ?: 1));

                $dbRouters = \App\Models\Router::where('tenant_id', $tenantId)
                    ->whereNotIn('status', ['pending', 'onboarding'])
                    ->orderByRaw("CASE WHEN status = 'online' THEN 0 ELSE 1 END")
                    ->orderBy('name')
                    ->get();

                foreach ($dbRouters as $r) {
                    $rName = $r->name ?: ($r->router_name ?: ('Router #' . $r->id));
                    // Use cached telemetry from DB for blazing-fast page load without blocking network calls
                    $isOnline = ($r->status === 'online' || $r->action === 'connected') &&
                        (empty($r->last_seen_at) || $r->last_seen_at->gt(now()->subMinutes(15)) || ($r->updated_at && $r->updated_at->gt(now()->subMinutes(15))));
                    $connType = $r->vpn_ip ? 'WireGuard' : 'API';

                    $portRoles = is_array($r->port_roles) ? $r->port_roles : [];
                    $activeHs = (int) ($portRoles['active_hs'] ?? 0);
                    $activePpp = (int) ($portRoles['active_ppp'] ?? 0);
                    $activeTotal = (int) ($portRoles['active_total'] ?? ($activeHs + $activePpp));

                    $totalMem = (int) ($r->total_memory ?? 0);
                    $freeMem = (int) ($r->free_memory ?? 0);
                    $memLoad = (int) ($r->memory_load ?? ($totalMem > 0 ? round((($totalMem - $freeMem) / $totalMem) * 100) : 0));

                    $systemOverview[$rName] = [
                        'status'        => true,
                        'is_online'     => $isOnline,
                        'router_status' => $isOnline ? 'online' : ($r->status ?: 'offline'),
                        'type'          => $connType,
                        'ip'            => $r->vpn_ip ?: ($r->ip ?: $r->ip_address),
                        'public_ip'     => $r->ip,
                        'vpn_ip'        => $r->vpn_ip,
                        'location'      => $r->location,
                        'serial_number' => $r->serial_number,
                        'last_seen_at'  => $r->last_seen_at ? $r->last_seen_at->diffForHumans() : null,
                        'active_hs'     => $activeHs,
                        'active_ppp'    => $activePpp,
                        'active_total'  => $activeTotal,
                        'memory_load'   => $memLoad,
                        'data'          => [[
                            'board-name'              => $r->model ?: ($r->board ?: $rName),
                            'platform'                => 'MikroTik',
                            'architecture-name'       => $r->architecture ?: 'RouterOS',
                            'uptime'                  => $r->uptime ?: ($isOnline ? 'Active' : 'Offline'),
                            'version'                 => $r->version ?: 'N/A',
                            'cpu'                     => $r->board ?: 'MikroTik CPU',
                            'cpu-count'               => '1',
                            'cpu-frequency'           => '-',
                            'cpu-load'                => (int) ($r->cpu_load ?? 0),
                            'total-memory'            => $totalMem,
                            'free-memory'             => $freeMem,
                            'total-hdd-space'         => 0,
                            'free-hdd-space'          => 0,
                            'factory-software'        => $r->version ?: 'N/A',
                            'build-time'              => $r->last_seen_at ? $r->last_seen_at->format('M d, Y H:i') : 'N/A',
                            'write-sect-total'        => '0',
                            'write-sect-since-reboot' => '0',
                        ]],
                    ];
                }
            }

            if (Schema::hasTable('router_lists')) {
                $rlQuery = \Illuminate\Support\Facades\DB::table('router_lists');
                if (Schema::hasColumn('router_lists', 'tenant_id')) {
                    $rlQuery->where('tenant_id', $tenantId);
                }
                $legacyRouters = $rlQuery->get();
                foreach ($legacyRouters as $lr) {
                    $lrName = $lr->router_name ?: ('Router #' . $lr->id);
                    if (!isset($systemOverview[$lrName])) {
                        $isOnline = ($lr->action === 'connected');
                        $systemOverview[$lrName] = [
                            'status'        => true,
                            'is_online'     => $isOnline,
                            'router_status' => $isOnline ? 'online' : 'offline',
                            'type'          => 'API',
                            'ip'            => $lr->ip_address,
                            'public_ip'     => $lr->ip_address,
                            'vpn_ip'        => null,
                            'location'      => null,
                            'serial_number' => null,
                            'last_seen_at'  => null,
                            'active_hs'     => 0,
                            'active_ppp'    => 0,
                            'active_total'  => 0,
                            'memory_load'   => 0,
                            'data'          => [[
                                'board-name'              => $lrName,
                                'platform'                => 'MikroTik',
                                'architecture-name'       => 'RouterOS',
                                'uptime'                  => $isOnline ? 'Active' : 'Offline',
                                'version'                 => 'N/A',
                                'cpu'                     => 'MikroTik CPU',
                                'cpu-count'               => '1',
                                'cpu-frequency'           => '-',
                                'cpu-load'                => 0,
                                'total-memory'            => 0,
                                'free-memory'             => 0,
                                'total-hdd-space'         => 0,
                                'free-hdd-space'          => 0,
                                'factory-software'        => 'N/A',
                                'build-time'              => 'N/A',
                                'write-sect-total'        => '0',
                                'write-sect-since-reboot' => '0',
                            ]],
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 5. Hotspot Sessions & Vouchers Overview
        $hotspotOverview = [
            'live_sessions' => 0,
            'active_users'  => 0,
            'expired_users' => 0,
            'total_users'   => 0,
            'today_sales'   => 0,
            'today_income'  => (float) ($revenueReport['today_hotspot'] ?? 0.0),
        ];

        try {
            if (Schema::hasTable('hotspot_sales')) {
                $tenantId = auth()->user()?->tenant_id ?: (session('tenant_id') ?: (session('impersonated_tenant_id') ?: 1));
                $todayDate = Carbon::now('Africa/Nairobi')->toDateString();

                $hsBase = DB::table('hotspot_sales');
                if (!$isSuperAdmin && Schema::hasColumn('hotspot_sales', 'tenant_id')) {
                    if ($tenantId == 1) {
                        $hsBase->where(function ($q) {
                            $q->where('tenant_id', 1)->orWhereNull('tenant_id');
                        });
                    } else {
                        $hsBase->where('tenant_id', $tenantId);
                    }
                }

                $activeHotspot = (clone $hsBase)
                    ->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->count();

                $expiredHotspot = (clone $hsBase)
                    ->where(function ($q) {
                        $q->where('status', 'expired')->orWhere(function ($q2) {
                            $q2->whereNotNull('expires_at')->where('expires_at', '<=', now());
                        });
                    })
                    ->count();

                $todaySalesCount = (clone $hsBase)
                    ->where(function ($q) use ($todayDate) {
                        $q->whereDate('sale_date', $todayDate)->orWhereDate('created_at', $todayDate);
                    })
                    ->count();

                // Sum live connected hotspot users reported by this tenant's routers
                $routerLiveHs = 0;
                if (isset($dbRouters)) {
                    foreach ($dbRouters as $dr) {
                        $pr = is_array($dr->port_roles) ? $dr->port_roles : [];
                        $routerLiveHs += (int) ($pr['active_hs'] ?? 0);
                    }
                }

                $liveSessions = max($activeHotspot, $routerLiveHs);
                $totalUsers = $activeHotspot + $expiredHotspot;

                $hotspotOverview = [
                    'live_sessions' => $liveSessions,
                    'active_users'  => $activeHotspot,
                    'expired_users' => $expiredHotspot,
                    'total_users'   => $totalUsers,
                    'today_sales'   => $todaySalesCount,
                    'today_income'  => (float) ($revenueReport['today_hotspot'] ?? 0.0),
                ];
            }
        } catch (\Throwable $e) {}

        return view('dashboard', compact('results', 'customersData', 'billInformationData', 'systemOverview', 'resellerData', 'revenueReport', 'selectedYear', 'availableYears', 'hotspotOverview'));
    }
}
