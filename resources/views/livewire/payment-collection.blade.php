<div class="zoom-in" x-data @focus-paid-amount.window="document.getElementById('paid_amount').focus()">
    <x-slot name="header">
        <h2 class="h4 font-weight-bold text-dark mb-0">
            <i class="bi bi-wallet2 me-2 text-orange-500"></i>{{ __('Payment Collection') }}
        </h2>
    </x-slot>

    <x-ui.page-header
        :title="__('Payment collection')"
        :subtitle="__('Search customers and record bill payments')"
    />

    <div class="row g-4">
        <!-- Search Box Panel -->
        <div class="col-lg-4 col-md-5 col-sm-12">
            <x-mikrotik.section-form>
                <x-slot name="title">
                    <span class="ui-section-title"><i class="bi bi-search me-2 text-orange-500"></i>{{ __('Search Customer') }}</span>
                </x-slot>
                <x-slot name="aside">
                    @include('livewire.partials.customer-search')
                </x-slot>
            </x-mikrotik.section-form>
        </div>

        <!-- Customer Detail & Payment Form Panel -->
        <div class="col-lg-8 col-md-7 col-sm-12">
            @if (!empty($info_data))
                <x-mikrotik.section-form :class="'row'" x-init="$dispatch('focus-paid-amount')">
                    <x-slot name="title">
                        <span class="ui-section-title"><i class="bi bi-person-badge me-2 text-orange-500"></i>{{ __('Customer Bill Summary') }}</span>
                    </x-slot>
                    <x-slot name="aside">
                        <div class="col-md-12">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Customer ID') }}</td>
                                            <td class="fw-semibold text-dark py-2">{{ $info_data->customer_unique_id }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Name') }}</td>
                                            <td class="fw-semibold text-dark py-2">{{ $info_data->customer_name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Billing Type') }}</td>
                                            <td class="text-dark py-2">
                                                <span class="badge bg-secondary">{{ __(ucfirst($info_data->billing->billing_type)) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('PPPoE Username') }}</td>
                                            <td class="text-dark py-2">{{ $info_data->pppUser->username ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Address') }}</td>
                                            <td class="text-muted py-2" style="font-size: 0.85rem;">
                                                @foreach ($info_data->customerAddress as $address)
                                                    {{ $address->input_type_dropdown }},
                                                    {{ $address->input_type_test }}
                                                    @if($address->input_type_textarea)
                                                        | {{ $address->input_type_textarea }}
                                                    @endif
                                                @endforeach
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Status') }}</td>
                                            @php
                                                $badge = match($info_data->status) {
                                                    'active' => 'bg-success',
                                                    'pending' => 'bg-warning',
                                                    'free' => 'bg-info',
                                                    default => 'bg-danger',
                                                };
                                            @endphp
                                            <td class="py-2">
                                                <span class="badge rounded-pill {{ $badge }}">{{ __(ucfirst($info_data->status)) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Expire Date') }}</td>
                                            <td class="text-dark py-2" style="font-size: 0.85rem;">
                                                <i class="bi bi-calendar-event text-orange-500 me-1"></i>
                                                {{ \Carbon\Carbon::parse($this->info_data->billing->auto_disable_date)->format('d-M-Y') }} 
                                                <span class="text-muted">({{ __('AutoDisable:') }} {{ $this->info_data->billing->auto_disable }})</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Monthly Rent') }}</td>
                                            <td class="text-end fw-semibold py-2 tabular-nums">{{ $info_data->billing->monthly_rent }} {{ siteUrlSettings('site_currency') }}</td>
                                        </tr>
                                        @if ($info_data->billing->additional_charge != 0)
                                            <tr>
                                                <td class="text-muted py-2">{{ __('Additional Charge') }}</td>
                                                <td class="text-end text-danger py-2 tabular-nums">+{{ $info_data->billing->additional_charge }} {{ siteUrlSettings('site_currency') }}</td>
                                            </tr>
                                        @endif
                                        @if ($info_data->billing->discount != 0)
                                            <tr>
                                                <td class="text-muted py-2">{{ __('Discount') }}</td>
                                                <td class="text-end text-success py-2 tabular-nums">-{{ $info_data->billing->discount }} {{ siteUrlSettings('site_currency') }}</td>
                                            </tr>
                                        @endif
                                        @if ($info_data->billing->advance != 0)
                                            <tr>
                                                <td class="text-muted py-2">{{ __('Advance') }}</td>
                                                <td class="text-end text-success py-2 tabular-nums">-{{ $info_data->billing->advance }} {{ siteUrlSettings('site_currency') }}</td>
                                            </tr>
                                        @endif
                                        @if ($info_data->billing->vat != 0)
                                            <tr>
                                                <td class="text-muted py-2">{{ __('Vat (%)') }}</td>
                                                <td class="text-end py-2 tabular-nums">{{ $info_data->billing->vat }}%</td>
                                            </tr>
                                        @endif
                                        @if ($info_data->billing->previous_due != 0)
                                            <tr>
                                                <td class="text-muted py-2">{{ __('Previous Due') }}</td>
                                                <td class="text-end text-danger py-2 tabular-nums">+{{ $info_data->billing->previous_due }} {{ siteUrlSettings('site_currency') }}</td>
                                            </tr>
                                        @endif
                                        <tr class="table-light">
                                            <td class="fw-semibold py-2">{{ __('Subtotal Sum') }}</td>
                                            <td class="text-end fw-semibold py-2 tabular-nums">{{ $info_data->billing->total_amount }} {{ siteUrlSettings('site_currency') }}</td>
                                        </tr>
                                        <tr class="bg-emerald-50/70">
                                            <th class="text-emerald-700 py-2">{{ __('Total Payable Amount') }}</th>
                                            <th class="text-end text-emerald-700 py-2 tabular-nums">{{ $info_data->billing->due_amount }} {{ siteUrlSettings('site_currency') }}</th>
                                        </tr>
                                        @if(count($collectionSummary) > 0)
                                        <tr>
                                            <td class="text-muted py-2" colspan="2">
                                                <div class="fw-semibold text-dark mb-1">{{ __('Collected History') }}</div>
                                                <div class="bg-slate-50 p-2 rounded-xl" style="font-size: 0.8rem;">
                                                    @foreach ($collectionSummary as $summary)
                                                        <div class="d-flex justify-content-between text-muted border-bottom py-1">
                                                            <span>#{{ $summary->customer_collection_unique_id }} ({{ \Carbon\Carbon::parse($summary->collection_date)->format('d-M-Y') }})</span>
                                                            <span class="fw-semibold text-emerald-600 tabular-nums">{{ $summary->collection_amount }} {{ siteUrlSettings('site_currency') }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                        <tr class="bg-amber-50/70">
                                            <th class="py-2">{{ __('Current Payable Due') }}</th>
                                            <th class="text-end text-rose-600 py-2 tabular-nums">{{$due_amount}} {{ siteUrlSettings('site_currency') }}</th>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Actions Form -->
                        <div class="col-md-12 mt-4 pt-3 border-top">
                            <form wire:submit.prevent="savePayment" class="row g-2 align-items-center">
                                <div class="col-md-3 col-sm-12">
                                    <input type="number" class="form-control form-control-sm" name="paid_amount" id="paid_amount" wire:model="paid_amount" wire:keyup="calculatePayment" min="1" autofocus placeholder="{{ __('Pay Amount') }}" required>
                                </div>
                                <div class="col-md-3 col-sm-12">
                                    <input type="text" class="form-control form-control-sm" name="invoice" id="invoice" wire:model.live="invoice" placeholder="{{ __('Invoice No') }}">
                                </div>
                                <div class="col-md-3 col-sm-12">
                                    <input type="date" class="form-control form-control-sm" name="expire_date" id="expire_date" wire:model.live="expire_date">
                                </div>
                                <div class="col-md-3 col-sm-12">
                                    <button class="btn btn-sm btn-primary w-100">
                                        <i class="bi bi-check-circle me-1"></i>{{ __('Pay Now') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </x-slot>
                </x-mikrotik.section-form>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    Livewire.on('focusInput', () => {
        document.getElementById('customer_list').focus();
    });
</script>
@endpush
