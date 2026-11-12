<div class="position-relative">
    <input type="search" name="customer_list" class="form-control form-control-sm w-100"
        placeholder="{{ siteUrlSettings('customer_id_prefix') ?: 'FCNET' }}-XXX, name, mobile"
        wire:model.live="customer_list" autocomplete="off" tabindex="1"
        wire:keydown.arrow-down="incrementHighlight" wire:keydown.arrow-up="decrementHighlight"
        wire:keydown.enter="selectHighlightedCustomer" id="customer_list"
        autofocus>
    @if (!empty($customers))
        <ul class="scrollbar-overlay overflow-auto list-group position-absolute w-100 ring-1 ring-slate-200/70 mt-1"
            style="max-height:20rem; z-index: 1050; border-radius: 12px;"
            x-data
            @scroll="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 5) { $wire.loadMore() }"
        >
            @foreach ($customers as $index => $customer)
                <li
                    wire:click="selectCustomer('{{ encrypt($customer->customer_unique_id) }}')"
                    class="list-group-item list-group-item-action py-2 {{ $index === $highlightedIndex ? 'bg-orange-50 text-orange-600' : '' }}"
                    style="cursor: pointer; font-size: 0.85rem;"
                    wire:key="customer-{{ $customer->id }}"
                >
                    <div class="fw-semibold d-flex justify-content-between align-items-center">
                        <div>
                            {{ $customer->customer_unique_id }}
                            <span class="badge rounded-pill ms-2 bg-primary">{{ $customer->username ?? 'N/A' }}</span>
                        </div>
                        @if($customer->status)
                            @php
                                $statusClass = match($customer->status) {
                                    'active' => 'bg-success',
                                    'disable', 'inactive' => 'bg-danger',
                                    'free' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }} rounded-pill">{{ ucfirst($customer->status) }}</span>
                        @endif
                    </div>
                    <div class="text-muted small">
                        {{ $customer->customer_name }} | {{ $customer->mobile }} | 
                        @foreach ($customer->customerAddress as $address)
                            {{ $address->input_type_test }},
                            {{ $address->input_type_dropdown }},
                            {{ $address->input_type_textarea }}
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
