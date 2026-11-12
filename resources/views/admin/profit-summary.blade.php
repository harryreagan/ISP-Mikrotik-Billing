<x-app-layout>
<div>

    {{-- ── Header ── --}}
    <x-ui.page-header
        :title="__('Profit & loss summary')"
        :subtitle="__('Revenue vs Expenses breakdown')"
    >
        <x-slot:actions>
            {{-- Month/Year Filter --}}
            <form method="GET" action="{{ route('admin.profit-summary') }}" class="d-flex align-items-center gap-2">
                <select name="month" class="form-select form-select-sm" style="width:140px;">
                    @foreach($months as $num => $name)
                        <option value="{{ $num }}" @selected($num == $month)>{{ __($name) }}</option>
                    @endforeach
                </select>
                <select name="year" class="form-select form-select-sm" style="width:100px;">
                    @foreach($years as $y)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> {{ __('Apply') }}
                </button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ── KPI Cards ── --}}
    <div class="row g-3 mb-4">

        {{-- Total Revenue --}}
        <div class="col-6 col-md-3">
            <x-ui.stat-card
                :label="__('Total Revenue')"
                :value="'KES ' . number_format($totalRevenue, 2)"
                :sub="__('ISP') . ': KES ' . number_format($collectionRevenue, 2) . ' · ' . __('Hotspot') . ': KES ' . number_format($hotspotRevenue, 2)"
                color="green"
            >
                <x-slot:icon><i class="bi bi-arrow-down-circle-fill"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Reseller Commission --}}
        <div class="col-6 col-md-3">
            <x-ui.stat-card
                :label="__('Commissions')"
                :value="'KES ' . number_format($resellerCommissions, 2)"
                :sub="__('Reseller Commissions')"
                color="pink"
            >
                <x-slot:icon><i class="bi bi-person-lines-fill"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Total Expenses --}}
        <div class="col-6 col-md-3">
            <x-ui.stat-card
                :label="__('Expenses')"
                :value="'KES ' . number_format($totalExpenses, 2)"
                :sub="trans_choice('{1} :count category|[2,*] :count categories', $expensesByCategory->count(), ['count' => $expensesByCategory->count()])"
                color="pink"
            >
                <x-slot:icon><i class="bi bi-receipt-cutoff"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Net Profit --}}
        <div class="col-6 col-md-3">
            @php $isProfitable = $netProfit >= 0; @endphp
            <x-ui.stat-card
                :label="__('Net :type', ['type' => $isProfitable ? __('Profit') : __('Loss')])"
                :value="($isProfitable ? '' : '-') . 'KES ' . number_format(abs($netProfit), 2)"
                :sub="__('After all costs')"
                :color="$isProfitable ? 'blue' : 'pink'"
            >
                <x-slot:icon><i class="bi bi-{{ $isProfitable ? 'graph-up-arrow' : 'graph-down-arrow' }}"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
    </div>

    <div class="row g-4">

        {{-- ── P&L Statement ── --}}
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="ui-section-title mb-0"><i class="bi bi-list-columns-reverse me-2 text-orange-500"></i>{{ __('P&L Statement') }}</h6>
                    <p class="text-muted small mb-0">{{ __($months[$month]) }} {{ $year }}</p>
                </div>
                <div class="card-body px-4">

                    {{-- Revenue rows --}}
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small text-muted">{{ __('ISP Collections') }}</span>
                        <span class="fw-semibold text-emerald-600 tabular-nums">+ KES {{ number_format($collectionRevenue, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small text-muted">{{ __('Hotspot Sales') }}</span>
                        <span class="fw-semibold text-emerald-600 tabular-nums">+ KES {{ number_format($hotspotRevenue, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom bg-emerald-50 rounded-xl px-2">
                        <span class="small fw-semibold">Total Revenue</span>
                        <span class="fw-semibold text-emerald-700 tabular-nums">KES {{ number_format($totalRevenue, 2) }}</span>
                    </div>

                    <div class="mt-3 mb-1 text-xs font-medium text-slate-400">{{ __('Costs & expenses') }}</div>

                    {{-- Commission row --}}
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small text-muted">
                            <i class="bi bi-person-fill-gear text-warning me-1"></i>{{ __('Reseller Commissions') }}
                        </span>
                        <span class="fw-semibold text-rose-600 tabular-nums">− KES {{ number_format($resellerCommissions, 2) }}</span>
                    </div>

                    {{-- Expense category rows --}}
                    @php
                        $catColors = ['item_purchase'=>'primary','raw_bill'=>'warning','employee_salary'=>'info','miscellaneous'=>'secondary'];
                        $catIcons  = ['item_purchase'=>'bag-fill','raw_bill'=>'lightning-charge-fill','employee_salary'=>'people-fill','miscellaneous'=>'three-dots'];
                    @endphp
                    @foreach($categories as $key => $label)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small text-muted">
                            <i class="bi bi-{{ $catIcons[$key] ?? 'circle' }} text-{{ $catColors[$key] ?? 'secondary' }} me-1"></i>{{ __($label) }}
                        </span>
                        <span class="fw-semibold text-rose-600 tabular-nums">− KES {{ number_format($expensesByCategory[$key] ?? 0, 2) }}</span>
                    </div>
                    @endforeach

                    {{-- Total costs --}}
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom bg-rose-50 rounded-xl px-2 mt-1">
                        <span class="small fw-semibold">{{ __('Total Costs') }}</span>
                        <span class="fw-semibold text-rose-700 tabular-nums">KES {{ number_format($resellerCommissions + $totalExpenses, 2) }}</span>
                    </div>

                    {{-- Net profit --}}
                    <div class="d-flex justify-content-between align-items-center py-3 mt-2 rounded-xl px-3 {{ $isProfitable ? 'bg-sky-50 text-sky-800' : 'bg-rose-50 text-rose-800' }}">
                        <span class="fw-semibold">{{ __('Net :type', ['type' => $isProfitable ? __('Profit') : __('Loss')]) }}</span>
                        <span class="fw-semibold text-lg tabular-nums">
                            {{ $isProfitable ? '' : '-' }}KES {{ number_format(abs($netProfit), 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Expense Breakdown Doughnut ── --}}
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="ui-section-title mb-0"><i class="bi bi-pie-chart-fill me-2 text-orange-500"></i>{{ __('Expense Breakdown') }}</h6>
                    <p class="text-muted small mb-0">{{ __('By category') }}</p>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <canvas id="expenseDonut" width="220" height="220"></canvas>
                    <div class="mt-3 w-100">
                        @foreach($categories as $key => $label)
                        @php $amount = $expensesByCategory[$key] ?? 0; @endphp
                        @if($amount > 0)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle d-inline-block" style="width:10px;height:10px;background:
                                    {{ ['item_purchase'=>'#3b82f6','raw_bill'=>'#f59e0b','employee_salary'=>'#06b6d4','miscellaneous'=>'#94a3b8'][$key] ?? '#ccc' }};"></span>
                                <span class="small text-muted">{{ __($label) }}</span>
                            </div>
                            <span class="small fw-semibold tabular-nums">KES {{ number_format($amount, 2) }}</span>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 12-Month Trend Chart ── --}}
        <div class="col-md-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="ui-section-title mb-0"><i class="bi bi-bar-chart-line-fill me-2 text-orange-500"></i>{{ __('12-Month Trend') }}</h6>
                    <p class="text-muted small mb-0">{{ __('Revenue vs Expenses') }}</p>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="260"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Detailed Item & Employee Expenses ── --}}
    <div class="row g-4 mt-1">
        {{-- Item Purchases --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="ui-section-title mb-0">
                            <i class="bi bi-bag-check-fill me-2 text-orange-500"></i>{{ __('Item Purchases Breakdown') }}
                        </h6>
                        <span class="badge bg-info rounded-pill">
                            {{ trans_choice('{1} :count item|[2,*] :count items', $itemPurchases->count(), ['count' => $itemPurchases->count()]) }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0">{{ __('Detailed list of purchased items for this month') }}</p>
                </div>
                <div class="card-body p-0">
                    @if($itemPurchases->isEmpty())
                        <x-ui.empty-state
                            icon="bi-inbox"
                            :title="__('No item purchases')"
                            :description="__('No item purchases recorded this month')"
                        />
                    @else
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="sticky-top" style="z-index: 1;">
                                    <tr>
                                        <th class="ps-4">{{ __('Date') }}</th>
                                        <th>{{ __('Item / Title') }}</th>
                                        <th>{{ __('Ref No.') }}</th>
                                        <th class="text-end pe-4">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($itemPurchases as $item)
                                        <tr>
                                            <td class="ps-4 small text-muted">{{ $item->expense_date->format('d M, Y') }}</td>
                                            <td class="small">
                                                <div class="fw-semibold text-dark">{{ $item->title }}</div>
                                                @if($item->description)
                                                    <div class="text-muted" style="font-size: 0.72rem;">{{ Str::limit($item->description, 50) }}</div>
                                                @endif
                                            </td>
                                            <td class="small text-muted">{{ $item->reference_no ?? '-' }}</td>
                                            <td class="small fw-semibold text-dark text-end pe-4 tabular-nums">KES {{ number_format($item->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Employee Salary / Bills --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="ui-section-title mb-0">
                            <i class="bi bi-people-fill me-2 text-orange-500"></i>{{ __('Employee Salary & Bills') }}
                        </h6>
                        <span class="badge bg-info rounded-pill">
                            {{ trans_choice('{1} :count bill|[2,*] :count bills', $employeeSalaries->count(), ['count' => $employeeSalaries->count()]) }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0">{{ __('Detailed list of employee payments & claims for this month') }}</p>
                </div>
                <div class="card-body p-0">
                    @if($employeeSalaries->isEmpty())
                        <x-ui.empty-state
                            icon="bi-inbox"
                            :title="__('No employee salary or bills')"
                            :description="__('No employee salary or bills recorded this month')"
                        />
                    @else
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="sticky-top" style="z-index: 1;">
                                    <tr>
                                        <th class="ps-4">{{ __('Date') }}</th>
                                        <th>{{ __('Employee / Purpose') }}</th>
                                        <th>{{ __('Ref No.') }}</th>
                                        <th class="text-end pe-4">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employeeSalaries as $bill)
                                        <tr>
                                            <td class="ps-4 small text-muted">{{ $bill->expense_date->format('d M, Y') }}</td>
                                            <td class="small">
                                                <div class="fw-semibold text-dark">{{ $bill->title }}</div>
                                                @if($bill->description)
                                                    <div class="text-muted" style="font-size: 0.72rem;">{{ Str::limit($bill->description, 50) }}</div>
                                                @endif
                                            </td>
                                            <td class="small text-muted">{{ $bill->reference_no ?? '-' }}</td>
                                            <td class="small fw-semibold text-dark text-end pe-4 tabular-nums">KES {{ number_format($bill->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Quick link to expense manager ── --}}
    <div class="mt-4 text-center">
        <a wire:navigate.hover href="{{ route('admin.expenses') }}" class="btn btn-secondary">
            <i class="bi bi-wallet2 me-2"></i>{{ __('Manage Expenses') }}
        </a>
    </div>
</div>

@push('scripts')
<script src="/js/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Expense Doughnut ─────────────────────────────────────────────────
    const donutCtx = document.getElementById('expenseDonut');
    if (donutCtx) {
        const expenseData = @json($expensesByCategory);
        const catKeys   = ['item_purchase', 'raw_bill', 'employee_salary', 'miscellaneous'];
        const catLabels = { item_purchase: '{{ __('Item Purchase') }}', raw_bill: '{{ __('Raw Bill') }}', employee_salary: '{{ __('Employee Salary') }}', miscellaneous: '{{ __('Miscellaneous') }}' };
        const catColors = ['#3b82f6', '#f59e0b', '#06b6d4', '#94a3b8'];

        const data   = catKeys.map(k => expenseData[k] ?? 0);
        const hasAny = data.some(v => v > 0);

        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: catKeys.map(k => catLabels[k]),
                datasets: [{
                    data: hasAny ? data : [1],
                    backgroundColor: hasAny ? catColors : ['#e5e7eb'],
                    borderWidth: 0,
                }]
            },
            options: {
                cutout: '72%',
                plugins: { legend: { display: false }, tooltip: { enabled: hasAny } },
                animation: { animateScale: true }
            }
        });
    }

    // ── 12-Month Trend ────────────────────────────────────────────────────
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        const chartData = @json($chartData);
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: chartData.map(d => d.label),
                datasets: [
                    {
                        label: '{{ __('Revenue') }}',
                        data: chartData.map(d => d.revenue),
                        backgroundColor: 'rgba(16,185,129,.75)',
                        borderRadius: 4,
                    },
                    {
                        label: '{{ __('Expenses') }}',
                        data: chartData.map(d => d.expense),
                        backgroundColor: 'rgba(239,68,68,.65)',
                        borderRadius: 4,
                    },
                    {
                        label: '{{ __('Net Profit') }}',
                        data: chartData.map(d => d.profit),
                        type: 'line',
                        borderColor: '#3b82f6',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.4,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                },
                scales: {
                    y: {
                        grid: { color: '#f1f5f9' },
                        ticks: { callback: v => 'KES ' + v.toLocaleString(), font: { size: 11 } }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            }
        });
    }
});
</script>
@endpush

<style>
    .profit-kpi-card {
        transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .profit-kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.08) !important;
    }
</style>
</x-app-layout>
