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
        $currentYear = Carbon::now()->year;
        $previousYear = Carbon::now()->subYear()->year;

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

                $todayPaid = Schema::hasTable('collection_summaries')
                    ? CollectionSummary::whereDate('collection_date', Carbon::today())->sum('collection_amount')
                    : 0;

                $hotspotTotal = Schema::hasTable('hotspot_sales') ? HotspotSale::sum('amount') : 0;
                $hotspotToday = Schema::hasTable('hotspot_sales') ? HotspotSale::whereDate('sale_date', Carbon::today())->sum('amount') : 0;

                $billInformationData = [
                    'monthly_rent' => (float) ($billingStats->monthly_rent ?? 0),
                    'previous_due' => -1 * (float) ($billingStats->previous_due_active ?? 0),
                    'advance' => (float) ($billingStats->advance ?? 0),
                    'paid_amount' => (float) ($billingStats->paid_amount ?? 0),
                    'today_paid_amount' => (float) $todayPaid,
                    'hotspot_total' => (float) $hotspotTotal,
                    'hotspot_today' => (float) $hotspotToday,
                    'due_amount' => -1 * (float) ($billingStats->due_amount_active ?? 0),
                    'by_status' => $billingByStatus,
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

        // 4. Monthly Results Initialization
        for ($month = 1; $month <= 12; $month++) {
            $results[$month] = [
                'cashflow_previous_year' => 0.0,
                'income_current_year' => 0.0,
                'revenue_difference' => 0.0,
            ];
        }

        try {
            if (Schema::hasTable('collection_summaries')) {
                $isSqlite = config('database.default') === 'sqlite';
                $monthExpr = $isSqlite ? "CAST(strftime('%m', collection_date) AS INTEGER)" : 'MONTH(collection_date)';
                $collectionsCurrentYear = CollectionSummary::whereYear('collection_date', $currentYear)
                    ->selectRaw("{$monthExpr} as month, SUM(collection_amount) as total")
                    ->groupBy('month')
                    ->pluck('total', 'month')
                    ->toArray();

                foreach ($collectionsCurrentYear as $m => $tot) {
                    if (isset($results[$m])) {
                        $results[$m]['income_current_year'] = (float) $tot;
                        $results[$m]['revenue_difference'] = (float) $tot;
                    }
                }
            } elseif (Schema::hasTable('payment_transactions')) {
                $monthlyPayments = DB::table('payment_transactions')
                    ->whereYear('created_at', $currentYear)
                    ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
                    ->groupBy('month')
                    ->pluck('total', 'month')
                    ->toArray();

                foreach ($monthlyPayments as $m => $tot) {
                    if (isset($results[$m])) {
                        $results[$m]['income_current_year'] = (float) $tot;
                        $results[$m]['revenue_difference'] = (float) $tot;
                    }
                }
            }
        } catch (\Throwable $e) {}

        $systemOverview = [];
        try {
            if (class_exists(MikrotikController::class)) {
                $systemOverview = app(MikrotikController::class)->systemOverview();
            }
        } catch (\Throwable $e) {
            $systemOverview = [];
        }

        return view('dashboard', compact('results', 'customersData', 'billInformationData', 'systemOverview', 'resellerData'));
    }
}
