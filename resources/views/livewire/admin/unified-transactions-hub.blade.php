<div class="zoom-in" x-data>
    {{-- Page Header --}}
    <x-ui.page-header
        :title="__('Payments & Statements Hub')"
        :subtitle="__('Unified real-time financial ledger, customer invoice statements, instant unmatched claim, and collection recording')"
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2">
                <button type="button" wire:click="reconcilePayments" wire:loading.attr="disabled" class="btn btn-sm btn-outline-success">
                    <span wire:loading.remove wire:target="reconcilePayments"><i class="bi bi-arrow-repeat me-1"></i> {{ __('Reconcile Payments') }}</span>
                    <span wire:loading wire:target="reconcilePayments"><span class="spinner-border spinner-border-sm me-1"></span> {{ __('Reconciling...') }}</span>
                </button>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" wire:click="setTab('transactions')" class="btn {{ $activeTab === 'transactions' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="bi bi-receipt-cutoff me-1"></i> {{ __('Ledger') }}
                    </button>
                    <button type="button" wire:click="setTab('invoices')" class="btn {{ $activeTab === 'invoices' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="bi bi-file-earmark-text me-1"></i> {{ __('Invoices') }}
                    </button>
                    <button type="button" wire:click="setTab('unmatched')" class="btn {{ $activeTab === 'unmatched' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="bi bi-patch-question me-1"></i> {{ __('Unmatched') }}
                        @if($metrics['pending_unmatched'] > 0)
                            <span class="badge bg-warning text-dark ms-1">{{ $metrics['pending_unmatched'] }}</span>
                        @endif
                    </button>
                    <button type="button" wire:click="setTab('collect')" class="btn {{ $activeTab === 'collect' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="bi bi-cash-stack me-1"></i> {{ __('Record Collection') }}
                    </button>
                </div>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Top Metrics Overview Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100 overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-white-50 small text-uppercase fw-bold">{{ __("Today's Collections") }}</span>
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-wallet2 text-white fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 text-white font-monospace">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format($metrics['today_total'], 2) }}</h3>
                    <small class="text-white-50">{{ __('PPPoE + Hotspot settlements') }}</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100 overflow-hidden" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #fff;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-white-50 small text-uppercase fw-bold">{{ __('Month Collections') }}</span>
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-calendar-check text-white fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 text-white font-monospace">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format($metrics['month_total'], 2) }}</h3>
                    <small class="text-white-50">{{ date('F Y') }} {{ __('total billing') }}</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100 overflow-hidden" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: #fff;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-white-50 small text-uppercase fw-bold">{{ __('Hotspot vs PPPoE') }}</span>
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-pie-chart text-white fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-0">
                        <span class="text-white small">WiFi: <b>{{ number_format($metrics['hotspot_month'], 0) }}</b></span>
                        <span class="text-white small">PPPoE: <b>{{ number_format($metrics['pppoe_month'], 0) }}</b></span>
                    </div>
                    <div class="progress mt-2 bg-white bg-opacity-25" style="height: 6px;">
                        @php
                            $totalMonth = $metrics['month_total'] > 0 ? $metrics['month_total'] : 1;
                            $hsPercent = round(($metrics['hotspot_month'] / $totalMonth) * 100);
                        @endphp
                        <div class="progress-bar bg-white" style="width: {{ $hsPercent }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100 {{ $metrics['pending_unmatched'] > 0 ? 'bg-warning-subtle border-warning' : 'bg-body' }}" role="button" wire:click="setTab('unmatched')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-bold">{{ __('Unmatched Deposits') }}</span>
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center {{ $metrics['pending_unmatched'] > 0 ? 'bg-warning text-dark' : 'bg-secondary-subtle text-secondary' }}" style="width: 38px; height: 38px;">
                            <i class="bi bi-patch-question fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 {{ $metrics['pending_unmatched'] > 0 ? 'text-warning-emphasis' : 'text-body' }}">{{ $metrics['pending_unmatched'] }}</h3>
                    <small class="{{ $metrics['pending_unmatched'] > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                        {{ $metrics['pending_unmatched'] > 0 ? __('Needs assignment & activation') : __('All deposits matched') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- Global Alert Banner --}}
    @if ($statusMessage)
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>{{ $statusMessage }}</div>
            <button type="button" class="btn-close" wire:click="$set('statusMessage', '')"></button>
        </div>
    @endif
    @if ($errorMessage)
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div>{{ $errorMessage }}</div>
            <button type="button" class="btn-close" wire:click="$set('errorMessage', '')"></button>
        </div>
    @endif

    {{-- Main Tabs Navigation --}}
    <ul class="nav nav-tabs mb-4 border-bottom">
        <li class="nav-item">
            <button type="button" wire:click="setTab('transactions')" class="nav-link py-3 px-4 {{ $activeTab === 'transactions' ? 'active fw-bold border-bottom-0' : 'text-muted' }}">
                <i class="bi bi-receipt-cutoff me-2 text-primary"></i>{{ __('Transaction Statements Ledger') }}
            </button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="setTab('invoices')" class="nav-link py-3 px-4 {{ $activeTab === 'invoices' ? 'active fw-bold border-bottom-0' : 'text-muted' }}">
                <i class="bi bi-file-earmark-text me-2 text-info"></i>{{ __('Invoices & Billing Statements') }}
            </button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="setTab('unmatched')" class="nav-link py-3 px-4 {{ $activeTab === 'unmatched' ? 'active fw-bold border-bottom-0' : 'text-muted' }}">
                <i class="bi bi-patch-question me-2 text-warning"></i>{{ __('Unmatched Payments') }}
                @if($metrics['pending_unmatched'] > 0)
                    <span class="badge rounded-pill bg-warning text-dark ms-2">{{ $metrics['pending_unmatched'] }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="setTab('collect')" class="nav-link py-3 px-4 {{ $activeTab === 'collect' ? 'active fw-bold border-bottom-0' : 'text-muted' }}">
                <i class="bi bi-cash-stack me-2 text-success"></i>{{ __('Record / Adjust Collection') }}
            </button>
        </li>
    </ul>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 1: ALL TRANSACTIONS / MASTER STATEMENT LEDGER                       --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'transactions')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input wire:model.live.debounce.300ms="txSearch" type="text" class="form-control border-start-0" placeholder="{{ __('Search Transaction Code, Invoice #, Customer, Phone, PPPoE...') }}">
                            @if(!empty($txSearch))
                                <button class="btn btn-outline-secondary" type="button" wire:click="$set('txSearch', '')"><i class="bi bi-x"></i></button>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select wire:model.live="txTypeFilter" class="form-select form-select-sm">
                            <option value="all">{{ __('All Transactions (PPPoE + Hotspot)') }}</option>
                            <option value="pppoe">{{ __('PPPoE / Fiber Collections') }}</option>
                            <option value="hotspot">{{ __('Hotspot WiFi Sales') }}</option>
                            <option value="cash">{{ __('Manual Cash Collections') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select wire:model.live="txDateFilter" class="form-select form-select-sm">
                            <option value="today">{{ __('Today') }}</option>
                            <option value="yesterday">{{ __('Yesterday') }}</option>
                            <option value="this_week">{{ __('This Week') }}</option>
                            <option value="this_month">{{ __('This Month') }}</option>
                            <option value="all">{{ __('All Time') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-12 text-md-end">
                        <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="bi bi-printer me-1"></i> {{ __('Print Ledger') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase text-muted" style="font-size: 0.75rem;">
                            <th>{{ __('Date & Time') }}</th>
                            <th>{{ __('Transaction / Ref') }}</th>
                            <th>{{ __('Subscriber / User') }}</th>
                            <th>{{ __('Service') }}</th>
                            <th>{{ __('Channel / By') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th class="text-center">{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($txRecords as $row)
                            @php
                                $isHotspot = ($txTypeFilter === 'hotspot') || isset($row->voucher_code);
                                $date = $isHotspot ? ($row->created_at ?? $row->sale_date) : ($row->collection_date ?? $row->created_at);
                                $ref = $isHotspot ? ($row->voucher_code ?: 'HS-'.$row->id) : ($row->transaction_id ?: ($row->invoice_no ? '#'.$row->invoice_no : 'COL-'.$row->id));
                                $amount = $isHotspot ? $row->amount : $row->collection_amount;
                                $channel = $isHotspot ? ($row->payment_method ?: 'Portal STK') : ($row->payment_method ?: ($row->payment_type ?: $row->collected_by));
                            @endphp
                            <tr wire:key="tx-row-{{ $isHotspot ? 'hs' : 'col' }}-{{ $row->id }}">
                                <td>
                                    <div class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</div>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($date)->format('h:i A') }}</small>
                                </td>
                                <td>
                                    <span class="font-monospace fw-bold text-primary">{{ $ref }}</span>
                                </td>
                                <td>
                                    @if ($isHotspot)
                                        <div class="fw-semibold text-dark">{{ $row->username ?: 'Hotspot Voucher' }}</div>
                                        <small class="text-muted"><i class="bi bi-router me-1"></i>{{ $row->router_name ?: 'MikroTik Router' }}</small>
                                    @else
                                        <div class="fw-semibold text-dark">{{ $row->customer->customer_name ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $row->customer->mobile ?? '' }} &bull; <span class="font-monospace">{{ $row->customer->pppUser->username ?? $row->customer_collection_unique_id }}</span></small>
                                    @endif
                                </td>
                                <td>
                                    @if ($isHotspot)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-wifi me-1"></i>Hotspot</span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-hdd-network me-1"></i>PPPoE</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $channel)) }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold font-monospace text-success fs-6">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $amount, 2) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1"><i class="bi bi-check-circle me-1"></i>{{ __('Paid') }}</span>
                                </td>
                                <td class="text-end">
                                    <button type="button" wire:click="openReceiptModal({{ $row->id }}, '{{ $isHotspot ? 'hotspot' : 'pppoe' }}')" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.8rem;">
                                        <i class="bi bi-receipt me-1"></i>{{ __('Receipt') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-receipt text-muted" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-2 mb-0">{{ __('No transactions found matching your filter criteria.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($txRecords->hasPages())
                <div class="card-footer bg-white py-2">
                    {{ $txRecords->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2: INVOICES & STATEMENTS                                            --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'invoices')
        <div class="row g-4">
            <div class="col-lg-4 col-md-5 col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <span class="fw-bold text-dark"><i class="bi bi-search me-2 text-primary"></i>{{ __('Select Customer') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="position-relative">
                            <label class="form-label small text-muted">{{ __('Search customer by Name, Mobile, ID or PPPoE') }}</label>
                            <input type="search" class="form-control form-control-sm w-100"
                                placeholder="{{ siteUrlSettings('customer_id_prefix') ?: 'FCNET' }}-XXX, name, mobile..."
                                wire:model.live.debounce.300ms="invoiceCustomerSearch" autocomplete="off">

                            @if (!empty($invoiceCustomers))
                                <ul class="list-group position-absolute w-100 shadow mt-1 overflow-auto" style="max-height: 18rem; z-index: 1050; border-radius: 8px;">
                                    @foreach ($invoiceCustomers as $c)
                                        <li wire:click="selectInvoiceCustomer('{{ encrypt($c['customer_unique_id']) }}')" class="list-group-item list-group-item-action py-2" style="cursor: pointer; font-size: 0.85rem;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold text-dark">{{ $c['customer_unique_id'] }}</span>
                                                <span class="badge bg-primary">{{ $c['username'] ?? 'PPPoE' }}</span>
                                            </div>
                                            <div class="text-muted small">{{ $c['customer_name'] }} &bull; {{ $c['mobile'] }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        @if ($invoiceCustomerData)
                            <div class="mt-4 pt-3 border-top">
                                <h6 class="fw-bold text-dark mb-2">{{ __('Customer Profile') }}</h6>
                                <p class="mb-1 small"><b>{{ __('Name:') }}</b> {{ $invoiceCustomerData->customer_name }}</p>
                                <p class="mb-1 small"><b>{{ __('Mobile:') }}</b> {{ $invoiceCustomerData->mobile }}</p>
                                <p class="mb-1 small"><b>{{ __('Username:') }}</b> {{ $invoiceCustomerData->pppUser->username ?? 'N/A' }}</p>
                                <p class="mb-1 small"><b>{{ __('Plan:') }}</b> {{ $invoiceCustomerData->billing->billing_type ?? 'Monthly' }}</p>
                                <p class="mb-1 small"><b>{{ __('Rent:') }}</b> {{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) ($invoiceCustomerData->billing->monthly_rent ?? 0), 2) }}</p>
                                <p class="mb-1 small"><b>{{ __('Due Amount:') }}</b> <span class="fw-bold text-danger">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) ($invoiceCustomerData->billing->due_amount ?? 0), 2) }}</span></p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-md-7 col-12">
                @if ($invoiceCustomerData)
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-info"></i>{{ __('Customer Billing Statement') }}</span>
                            <button type="button" wire:click="printInvoice" class="btn btn-sm btn-primary">
                                <i class="bi bi-printer me-1"></i> {{ __('Print Statement') }}
                            </button>
                        </div>
                        <div class="card-body p-4" id="print-section">
                            {{-- Branded Printable Invoice Container --}}
                            <div class="border rounded p-4 bg-white">
                                <div class="row align-items-center mb-4 pb-3 border-bottom">
                                    <div class="col-6">
                                        <h4 class="fw-bold text-primary mb-0">{{ siteUrlSettings('site_name') ?: 'HSmart ISP' }}</h4>
                                        <small class="text-muted">{{ siteUrlSettings('site_address') }} &bull; Tel: {{ siteUrlSettings('site_phone') }}</small>
                                    </div>
                                    <div class="col-6 text-end">
                                        <h5 class="fw-bold text-uppercase text-dark mb-0">{{ __('Invoice Statement') }}</h5>
                                        <span class="small text-muted">{{ __('Date:') }} {{ date('d M Y') }}</span>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-6">
                                        <span class="text-muted small text-uppercase fw-bold">{{ __('Bill To:') }}</span>
                                        <div class="fw-bold text-dark fs-6">{{ $invoiceCustomerData->customer_name }}</div>
                                        <div class="small text-muted">{{ __('Account ID:') }} {{ $invoiceCustomerData->customer_unique_id }}</div>
                                        <div class="small text-muted">{{ __('Phone:') }} {{ $invoiceCustomerData->mobile }}</div>
                                        <div class="small text-muted">{{ __('PPPoE Username:') }} {{ $invoiceCustomerData->pppUser->username ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span class="text-muted small text-uppercase fw-bold">{{ __('Account Overview:') }}</span>
                                        <div class="small"><b>{{ __('Billing Cycle:') }}</b> {{ date('F Y') }}</div>
                                        <div class="small"><b>{{ __('Auto-Disable Date:') }}</b> {{ $invoiceCustomerData->billing->auto_disable_date ?: 'N/A' }}</div>
                                        <div class="small"><b>{{ __('Service Status:') }}</b>
                                            <span class="badge {{ $invoiceCustomerData->status === 'active' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($invoiceCustomerData->status) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <table class="table table-bordered mb-4">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('Item Description') }}</th>
                                            <th class="text-end">{{ __('Charges') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ __('Monthly Internet Subscription') }}</div>
                                                <small class="text-muted">{{ __('PPPoE Bandwidth & Port Access') }}</small>
                                            </td>
                                            <td class="text-end font-monospace">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) ($invoiceCustomerData->billing->monthly_rent ?? 0), 2) }}</td>
                                        </tr>
                                        @if(($invoiceCustomerData->billing->additional_charge ?? 0) > 0)
                                            <tr>
                                                <td>{{ __('Additional Charges (Router / Installation)') }}</td>
                                                <td class="text-end font-monospace">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $invoiceCustomerData->billing->additional_charge, 2) }}</td>
                                            </tr>
                                        @endif
                                        @if(($invoiceCustomerData->billing->previous_due ?? 0) > 0)
                                            <tr>
                                                <td>{{ __('Previous Outstanding Balance') }}</td>
                                                <td class="text-end font-monospace text-danger">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $invoiceCustomerData->billing->previous_due, 2) }}</td>
                                            </tr>
                                        @endif
                                        <tr class="table-light fw-bold">
                                            <td>{{ __('Total Amount Due') }}</td>
                                            <td class="text-end font-monospace text-danger fs-6">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) ($invoiceCustomerData->billing->due_amount ?? 0), 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                @if (!empty($invoiceCollectionSummaries) && count($invoiceCollectionSummaries) > 0)
                                    <h6 class="fw-bold text-dark mt-4 mb-2">{{ __('Recent Payment Collections') }}</h6>
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr class="text-muted small">
                                                <th>{{ __('Date') }}</th>
                                                <th>{{ __('Trx / Ref') }}</th>
                                                <th>{{ __('Method') }}</th>
                                                <th class="text-end">{{ __('Amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($invoiceCollectionSummaries as $s)
                                                <tr>
                                                    <td>{{ \Carbon\Carbon::parse($s->collection_date)->format('d-M-Y') }}</td>
                                                    <td class="font-monospace">{{ $s->transaction_id ?: ($s->invoice_no ? '#'.$s->invoice_no : 'COL-'.$s->id) }}</td>
                                                    <td>{{ ucfirst($s->payment_method ?: $s->payment_type ?: 'Cash') }}</td>
                                                    <td class="text-end font-monospace fw-bold text-success">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $s->collection_amount, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card shadow-sm border-0 py-5 text-center">
                        <div class="card-body">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                            <h5 class="fw-semibold text-dark mt-3">{{ __('No Customer Selected') }}</h5>
                            <p class="text-muted mb-0">{{ __('Search and select a customer from the left panel to generate and print their billing statement.') }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 3: UNMATCHED PAYMENTS & INSTANT CLAIM                               --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'unmatched')
        {{-- Flash Alerts --}}
        @if ($statusMessage)
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ $statusMessage }}
                <button type="button" class="btn-close" wire:click="$set('statusMessage', '')"></button>
            </div>
        @endif
        @if ($errorMessage)
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $errorMessage }}
                <button type="button" class="btn-close" wire:click="$set('errorMessage', '')"></button>
            </div>
        @endif

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input wire:model.live.debounce.300ms="unmatchedSearch" type="text" class="form-control border-start-0" placeholder="{{ __('Search Code, Phone, Sender, Account Ref...') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select wire:model.live="unmatchedStatusFilter" class="form-select form-select-sm">
                            <option value="pending">{{ __('Pending Review') }} ({{ $metrics['pending_unmatched'] }})</option>
                            <option value="matched">{{ __('Auto Matched') }}</option>
                            <option value="manual_matched">{{ __('Manually Matched') }}</option>
                            <option value="ignored">{{ __('Ignored') }}</option>
                            <option value="all">{{ __('All Deposits') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <select wire:model.live="unmatchedSourceFilter" class="form-select form-select-sm">
                            <option value="all">{{ __('All Sources') }}</option>
                            <option value="mpesa_sms">M-Pesa SMS</option>
                            <option value="daraja">Daraja STK</option>
                            <option value="daraja_c2b">Daraja C2B</option>
                            <option value="payhero">PayHero</option>
                            <option value="kopokopo">KopoKopo</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-12 text-end">
                        <a href="{{ route('admin.payment-gateways') }}" class="btn btn-sm btn-outline-secondary w-100" title="{{ __('Gateway Settings') }}">
                            <i class="bi bi-gear"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase text-muted" style="font-size: 0.75rem;">
                            <th>{{ __('Date & Time') }}</th>
                            <th>{{ __('Transaction Code') }}</th>
                            <th>{{ __('Source') }}</th>
                            <th>{{ __('Sender / Phone') }}</th>
                            <th>{{ __('Account Ref') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th class="text-center">{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unmatchedPayments as $payment)
                            <tr wire:key="unmatched-row-{{ $payment->id }}">
                                <td>
                                    <div class="fw-semibold text-dark">{{ $payment->created_at->format('d M Y') }}</div>
                                    <small class="text-muted">{{ $payment->created_at->format('h:i A') }}</small>
                                </td>
                                <td>
                                    <span class="font-monospace fw-bold text-primary">{{ $payment->transaction_code }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ strtoupper($payment->source) }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $payment->sender_name ?: 'N/A' }}</div>
                                    <small class="text-muted">{{ $payment->phone ?: 'No phone' }}</small>
                                </td>
                                <td>
                                    @if ($payment->account_reference)
                                        <span class="badge bg-secondary-subtle text-secondary font-monospace">{{ $payment->account_reference }}</span>
                                    @else
                                        <span class="text-muted small">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold font-monospace text-success fs-6">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $payment->amount, 2) }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($payment->status === 'pending')
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>{{ __('Pending') }}</span>
                                    @elseif (in_array($payment->status, ['matched', 'manual_matched']))
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>{{ __('Matched') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($payment->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($payment->status === 'pending')
                                        <button type="button" wire:click="openAssignModal({{ $payment->id }})" class="btn btn-sm btn-primary py-0 px-2" style="font-size: 0.8rem;">
                                            <i class="bi bi-check2-circle me-1"></i>{{ __('Claim & Match') }}
                                        </button>
                                        <button type="button" wire:click="ignorePayment({{ $payment->id }})" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.8rem;" title="{{ __('Ignore') }}">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    @elseif ($payment->customer)
                                        <small class="text-muted">{{ $payment->customer->customer_name }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-shield-check text-success" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-2 mb-0">{{ __('No unmatched payments found.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($unmatchedPayments->hasPages())
                <div class="card-footer bg-white py-2">
                    {{ $unmatchedPayments->links() }}
                </div>
            @endif
        </div>

        {{-- Instant Claim / Assign Modal --}}
        @if ($unmatchedSelectedId && $selectedClaimPayment)
            <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); z-index: 1060;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content shadow border-0">
                        <div class="modal-header bg-primary text-white py-2">
                            <h6 class="modal-title fw-bold"><i class="bi bi-link-45deg me-1"></i>{{ __('Match & Activate Deposit') }}</h6>
                            <button type="button" class="btn-close btn-close-white" wire:click="closeAssignModal"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="alert alert-info py-2 small mb-3">
                                <div><b>{{ __('Code:') }}</b> <span class="font-monospace fw-bold">{{ $selectedClaimPayment->transaction_code }}</span></div>
                                <div><b>{{ __('Amount:') }}</b> <span class="fw-bold text-success">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $selectedClaimPayment->amount, 2) }}</span></div>
                                <div><b>{{ __('Sender:') }}</b> {{ $selectedClaimPayment->sender_name }} ({{ $selectedClaimPayment->phone }})</div>
                            </div>

                            <label class="form-label small fw-bold">{{ __('Search Customer to Credit & Activate') }}</label>
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="{{ __('Type Name, ID, Mobile or PPPoE...') }}" wire:model.live.debounce.300ms="claimCustomerSearch">

                            @if (!empty($claimCustomerResults) && count($claimCustomerResults) > 0)
                                <div class="list-group mb-3 overflow-auto" style="max-height: 12rem;">
                                    @foreach ($claimCustomerResults as $cust)
                                        <button type="button" wire:click="selectClaimCustomer({{ $cust->id }})" class="list-group-item list-group-item-action py-2 text-start {{ $claimSelectedCustomerId === $cust->id ? 'active' : '' }}">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold">{{ $cust->customer_name }}</span>
                                                <span class="badge bg-secondary font-monospace">{{ $cust->customer_unique_id }}</span>
                                            </div>
                                            <small class="{{ $claimSelectedCustomerId === $cust->id ? 'text-white-50' : 'text-muted' }}">
                                                {{ $cust->mobile }} &bull; PPPoE: {{ $cust->pppUser->username ?? 'N/A' }} &bull; Due: {{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) ($cust->billing->due_amount ?? 0), 2) }}
                                            </small>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if ($selectedClaimCustomer)
                                <div class="p-2 border rounded bg-light mb-3 small">
                                    <div class="fw-bold text-success"><i class="bi bi-check2-circle me-1"></i>{{ __('Target Customer Selected:') }}</div>
                                    <div><b>{{ $selectedClaimCustomer->customer_name }}</b> ({{ $selectedClaimCustomer->customer_unique_id }})</div>
                                    <div>{{ __('Current Due Balance:') }} <span class="text-danger fw-bold">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) ($selectedClaimCustomer->billing->due_amount ?? 0), 2) }}</span></div>
                                </div>
                            @endif

                            <div class="mb-2">
                                <label class="form-label small text-muted">{{ __('Admin Notes (Optional)') }}</label>
                                <input type="text" class="form-control form-control-sm" placeholder="{{ __('e.g. Customer confirmed via WhatsApp') }}" wire:model="claimAdminNotes">
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-secondary" wire:click="closeAssignModal">{{ __('Cancel') }}</button>
                            <button type="button" class="btn btn-sm btn-success" wire:click="assignPayment" {{ ! $claimSelectedCustomerId ? 'disabled' : '' }}>
                                <i class="bi bi-lightning-charge me-1"></i>{{ __('Fulfill & Activate Internet') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 4: RECORD & ADJUST COLLECTION (MANUAL CASH / OFFLINE)               --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'collect')
        <div class="row g-4">
            <div class="col-lg-4 col-md-5 col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <span class="fw-bold text-dark"><i class="bi bi-search me-2 text-success"></i>{{ __('Find Customer') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="position-relative">
                            <label class="form-label small text-muted">{{ __('Search customer by Name, Mobile, ID or PPPoE') }}</label>
                            <input type="search" class="form-control form-control-sm w-100"
                                placeholder="{{ siteUrlSettings('customer_id_prefix') ?: 'FCNET' }}-XXX, name, mobile..."
                                wire:model.live.debounce.300ms="collectCustomerSearch" autocomplete="off">

                            @if (!empty($collectCustomers))
                                <ul class="list-group position-absolute w-100 shadow mt-1 overflow-auto" style="max-height: 18rem; z-index: 1050; border-radius: 8px;">
                                    @foreach ($collectCustomers as $c)
                                        <li wire:click="selectCollectCustomer('{{ encrypt($c['customer_unique_id']) }}')" class="list-group-item list-group-item-action py-2" style="cursor: pointer; font-size: 0.85rem;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold text-dark">{{ $c['customer_unique_id'] }}</span>
                                                <span class="badge bg-primary">{{ $c['username'] ?? 'PPPoE' }}</span>
                                            </div>
                                            <div class="text-muted small">{{ $c['customer_name'] }} &bull; {{ $c['mobile'] }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        @if ($collectCustomerData)
                            <div class="mt-4 pt-3 border-top">
                                <h6 class="fw-bold text-dark mb-2">{{ __('Customer Details') }}</h6>
                                <table class="table table-sm table-borderless small mb-0">
                                    <tr>
                                        <td class="text-muted py-1">{{ __('Account:') }}</td>
                                        <td class="fw-semibold text-dark py-1">{{ $collectCustomerData->customer_unique_id }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">{{ __('Name:') }}</td>
                                        <td class="fw-semibold text-dark py-1">{{ $collectCustomerData->customer_name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">{{ __('Status:') }}</td>
                                        <td class="py-1">
                                            <span class="badge {{ $collectCustomerData->status === 'active' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($collectCustomerData->status) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">{{ __('PPPoE:') }}</td>
                                        <td class="fw-semibold text-primary py-1">{{ $collectCustomerData->pppUser->username ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">{{ __('Monthly Rent:') }}</td>
                                        <td class="fw-semibold text-dark py-1">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) ($collectCustomerData->billing->monthly_rent ?? 0), 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">{{ __('Auto-Disable:') }}</td>
                                        <td class="fw-semibold text-danger py-1">{{ $collectCustomerData->billing->auto_disable_date ?: 'N/A' }}</td>
                                    </tr>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-md-7 col-12">
                @if ($collectCustomerData)
                    {{-- Payment Entry Form --}}
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <span class="fw-bold text-dark"><i class="bi bi-cash-stack me-2 text-success"></i>{{ __('Record Bill Payment / Offline Collection') }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4 col-12">
                                    <label class="form-label small fw-bold">{{ __('Total Outstanding Due') }}</label>
                                    <input type="text" class="form-control form-control-sm bg-light fw-bold text-danger font-monospace" value="{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $collectTotalAmount, 2) }}" readonly>
                                </div>
                                <div class="col-md-4 col-12">
                                    <label class="form-label small fw-bold text-success">{{ __('Amount Paid Now *') }}</label>
                                    <input type="number" step="any" class="form-control form-control-sm font-monospace fw-bold" placeholder="0.00" wire:model.live.debounce.300ms="collectPaidAmount" wire:keyup="calculatePayment" autofocus>
                                </div>
                                <div class="col-md-4 col-12">
                                    <label class="form-label small fw-bold">{{ __('Remaining Balance Due') }}</label>
                                    <input type="text" class="form-control form-control-sm bg-light font-monospace fw-bold" value="{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $collectDueAmount, 2) }}" readonly>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label small fw-bold">{{ __('New Auto-Disable / Expiry Date') }}</label>
                                    <input type="date" class="form-control form-control-sm font-monospace" wire:model="collectExpireDate">
                                    <small class="text-muted">{{ __('Calculated from package billing cycle') }}</small>
                                </div>
                                <div class="col-md-6 col-12 d-flex align-items-end">
                                    <button type="button" wire:click="saveCollection" class="btn btn-sm btn-success w-100 py-2 fw-semibold" {{ (float) $collectPaidAmount <= 0 ? 'disabled' : '' }}>
                                        <i class="bi bi-check-circle me-1"></i> {{ __('Save Payment & Activate Internet') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Customer Collection History with Reverse / Delete capability --}}
                    @if (!empty($collectRecentSummaries) && count($collectRecentSummaries) > 0)
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3">
                                <span class="fw-bold text-dark"><i class="bi bi-clock-history me-2 text-muted"></i>{{ __('Recent Collections for') }} {{ $collectCustomerData->customer_name }}</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr class="text-muted">
                                            <th>{{ __('Date') }}</th>
                                            <th>{{ __('Trx / Invoice') }}</th>
                                            <th>{{ __('Collected By') }}</th>
                                            <th class="text-end">{{ __('Amount') }}</th>
                                            <th class="text-end">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($collectRecentSummaries as $cs)
                                            <tr wire:key="cs-row-{{ $cs->id }}">
                                                <td>{{ \Carbon\Carbon::parse($cs->collection_date)->format('d M Y, h:i A') }}</td>
                                                <td class="font-monospace text-primary">{{ $cs->transaction_id ?: ($cs->invoice_no ? '#'.$cs->invoice_no : 'COL-'.$cs->id) }}</td>
                                                <td>{{ $cs->collected_by ?: 'System' }}</td>
                                                <td class="text-end font-monospace fw-bold text-success">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $cs->collection_amount, 2) }}</td>
                                                <td class="text-end">
                                                    <button type="button" onclick="confirm('Are you sure you want to reverse and delete this collection record? The customer due balance will be restored.') || event.stopImmediatePropagation()" wire:click="deleteCollectionRecord({{ $cs->id }})" class="btn btn-sm btn-outline-danger py-0 px-2" title="{{ __('Reverse Collection') }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="card shadow-sm border-0 py-5 text-center">
                        <div class="card-body">
                            <i class="bi bi-wallet2 text-muted" style="font-size: 3rem;"></i>
                            <h5 class="fw-semibold text-dark mt-3">{{ __('No Customer Selected') }}</h5>
                            <p class="text-muted mb-0">{{ __('Search and select a customer from the left panel to record a payment or adjust past collections.') }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Printable Receipt Modal --}}
    @if ($selectedReceiptId && $selectedReceiptData)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); z-index: 1060;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow border-0">
                    <div class="modal-header bg-light py-2">
                        <h6 class="modal-title fw-bold text-dark"><i class="bi bi-receipt me-1 text-primary"></i>{{ __('Payment Receipt') }}</h6>
                        <button type="button" class="btn-close" wire:click="closeReceiptModal"></button>
                    </div>
                    <div class="modal-body p-4" id="receipt-print-area">
                        <div class="text-center mb-3">
                            <h5 class="fw-bold text-primary mb-0">{{ siteUrlSettings('site_name') ?: 'HSmart ISP' }}</h5>
                            <small class="text-muted">{{ siteUrlSettings('site_phone') }} &bull; {{ siteUrlSettings('site_address') }}</small>
                            <hr class="my-2">
                            <span class="badge bg-success-subtle text-success px-3 py-1 fw-bold text-uppercase">{{ __('Official Payment Receipt') }}</span>
                        </div>

                        @php
                            $isHs = ($selectedReceiptType === 'hotspot');
                            $rDate = $isHs ? ($selectedReceiptData->created_at ?? $selectedReceiptData->sale_date) : ($selectedReceiptData->collection_date ?? $selectedReceiptData->created_at);
                            $rAmount = $isHs ? $selectedReceiptData->amount : $selectedReceiptData->collection_amount;
                            $rRef = $isHs ? ($selectedReceiptData->voucher_code ?: 'HS-'.$selectedReceiptData->id) : ($selectedReceiptData->transaction_id ?: ($selectedReceiptData->invoice_no ? '#'.$selectedReceiptData->invoice_no : 'COL-'.$selectedReceiptData->id));
                        @endphp

                        <table class="table table-sm table-borderless small mb-3">
                            <tr>
                                <td class="text-muted">{{ __('Receipt No:') }}</td>
                                <td class="text-end font-monospace fw-bold">{{ $rRef }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('Date & Time:') }}</td>
                                <td class="text-end">{{ \Carbon\Carbon::parse($rDate)->format('d M Y, h:i A') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('Customer / Account:') }}</td>
                                <td class="text-end fw-semibold">
                                    @if ($isHs)
                                        {{ $selectedReceiptData->username ?: 'Hotspot Voucher' }}
                                    @else
                                        {{ $selectedReceiptData->customer->customer_name ?? 'N/A' }} ({{ $selectedReceiptData->customer_collection_unique_id }})
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('Payment Channel:') }}</td>
                                <td class="text-end">{{ ucfirst($isHs ? ($selectedReceiptData->payment_method ?: 'Portal STK') : ($selectedReceiptData->payment_method ?: ($selectedReceiptData->payment_type ?: 'Cash'))) }}</td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold fs-6 text-dark pt-2">{{ __('Amount Paid:') }}</td>
                                <td class="text-end font-monospace fw-bold fs-6 text-success pt-2">{{ siteUrlSettings('site_currency') ?: 'KES' }} {{ number_format((float) $rAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('Status:') }}</td>
                                <td class="text-end"><span class="badge bg-success">{{ __('COMPLETED / VERIFIED') }}</span></td>
                            </tr>
                        </table>

                        <div class="text-center text-muted small border-top pt-2">
                            {{ __('Thank you for choosing') }} {{ siteUrlSettings('site_name') ?: 'HSmart ISP' }}.<br>
                            <small>{{ __('Computer generated receipt. No signature required.') }}</small>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-secondary" wire:click="closeReceiptModal">{{ __('Close') }}</button>
                        <button type="button" onclick="window.print()" class="btn btn-sm btn-primary">
                            <i class="bi bi-printer me-1"></i>{{ __('Print Receipt') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    Livewire.on('triggerPrint', () => {
        let cssLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.href);
        if (typeof printJS === 'function') {
            printJS({
                printable: 'print-section',
                type: 'html',
                css: cssLinks,
                targetStyles: ['*'],
                scanStyles: false,
            });
        } else {
            window.print();
        }
    });
</script>
@endpush

