<div class="zoom-in" x-data @focus-paid-amount.window="document.getElementById('paid_amount').focus()">
    <x-slot name="header">
        <h2 class="h4 font-weight-bold text-dark mb-0">
            <i class="bi bi-pencil-square me-2 text-orange-500"></i>{{ __('Collection Edit') }}
        </h2>
    </x-slot>

    <x-ui.page-header
        :title="__('Collection edit')"
        :subtitle="__('Search customers to inspect or remove collection entries')"
    />

    <div class="row g-4">
        <!-- Search Panel -->
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

        <!-- Info & Edit Panel -->
        <div class="col-lg-8 col-md-7 col-sm-12">
            @if (!empty($info_data))
                <x-mikrotik.section-form :class="'row'">
                    <x-slot name="title">
                        <span class="ui-section-title"><i class="bi bi-person-badge me-2 text-orange-500"></i>{{ __('Customer Info') }}</span>
                    </x-slot>
                    <x-slot name="aside">
                        <div class="col-md-12">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Customer ID') }}</td>
                                            <td class="fw-semibold text-dark py-2">{{ $info_data->customer_unique_id }}
                                            </td>
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
                                            <td class="text-dark py-2">{{ $info_data->pppUser->username ?? 'N/A' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">{{ __('Address') }}</td>
                                            <td class="text-muted py-2" style="font-size: 0.85rem;">
                                                @foreach ($info_data->customerAddress as $address)
                                                    {{ $address->input_type_dropdown }},
                                                    {{ $address->input_type_test }}
                                                    @if ($address->input_type_textarea)
                                                        | {{ $address->input_type_textarea }}
                                                    @endif
                                                @endforeach
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-3" colspan="2">
                                                <div class="fw-semibold text-dark mb-2"><i
                                                        class="bi bi-receipt text-orange-500 me-1"></i>{{ __('Collected History') }}
                                                </div>
                                                <div class="bg-slate-50 p-3 rounded-xl" style="font-size: 0.85rem;">
                                                    @if (count($collectionSummary) > 0)
                                                        @foreach ($collectionSummary as $summary)
                                                            <div
                                                                class="d-flex justify-content-between align-items-center border-bottom py-2">
                                                                <span class="text-dark">
                                                                    <span
                                                                        class="fw-semibold">#{{ $summary->customer_collection_unique_id }}</span>
                                                                    <span class="text-muted"
                                                                        style="font-size: 0.75rem;">({{ \Carbon\Carbon::parse($summary->collection_date)->format('d-M-Y') }})</span>
                                                                    <span
                                                                        class="badge bg-success ms-2">{{ $summary->collection_amount }}
                                                                        {{ siteUrlSettings('site_currency') }}</span>
                                                                </span>
                                                                <button
                                                                    class="btn btn-xs btn-outline-danger"
                                                                    wire:click="deleteCollection({{ $summary->id }})">
                                                                    <i class="bi bi-trash-fill"></i>
                                                                </button>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div class="text-muted text-center py-2">
                                                            {{ __('No collection history found') }}</div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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
