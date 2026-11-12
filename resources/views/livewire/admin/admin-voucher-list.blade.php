<div>
    <div class="ui-page-wrap">

        {{-- Header --}}
        <x-ui.page-header :title="__('Reseller Vouchers Audit')" :subtitle="__('Track and monitor all generated, used, and expired reseller recharge vouchers')">
            <x-slot:actions>
                <button wire:click="resetFilters" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-repeat me-1"></i> {{ __('Reset Filters') }}
                </button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Filter Bar --}}
        <div class="card">
            <div class="card-body py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6 col-12">
                        <label class="form-label mb-1">{{ __('Search Vouchers') }}</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input wire:model.live.debounce.400ms="search" type="text" 
                                placeholder="{{ __('Search by voucher code, reseller name, customer...') }}" 
                                class="form-control form-control-sm border-start-0">
                        </div>
                    </div>
                    <div class="col-md-3 col-12">
                        <label class="form-label mb-1">{{ __('Status') }}</label>
                        <select wire:model.live="statusFilter" class="form-select form-select-sm">
                            <option value="all">{{ __('All Statuses') }}</option>
                            <option value="unused">{{ __('Unused Only') }}</option>
                            <option value="used">{{ __('Used Only') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-12">
                        <label class="form-label mb-1">{{ __('Reseller Filter') }}</label>
                        <select wire:model.live="resellerFilter" class="form-select form-select-sm">
                            <option value="all">{{ __('All Resellers') }}</option>
                            @foreach($resellers as $res)
                                <option value="{{ $res->id }}">{{ $res->user?->name ?? 'N/A' }} ({{ $res->company }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4 py-3">{{ __('Voucher Code') }}</th>
                                <th>{{ __('Value') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Reseller') }}</th>
                                <th>{{ __('Commission Type') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Redeemed By') }}</th>
                                <th>{{ __('Expiry Date') }}</th>
                                <th class="pe-4">{{ __('Used At') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $voucher)
                            <tr wire:key="admin-voucher-{{ $voucher->id }}">
                                <td class="ps-4">
                                    <div class="fw-semibold font-monospace text-dark">{{ $voucher->code }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-success tabular-nums">KES  {{ number_format($voucher->value, 2) }}</div>
                                </td>
                                <td class="small">
                                    @if($voucher->type === 'package_based')
                                        <span class="badge bg-success-subtle text-success">
                                            {{ __('Package: :package', ['package' => $voucher->package?->package ?? 'N/A']) }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            {{ __('Fixed Amount') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $voucher->reseller?->user?->name ?? 'N/A' }}</div>
                                    <div class="text-muted small">{{ $voucher->reseller?->company }}</div>
                                </td>
                                <td>
                                    @if($voucher->commission_paid_upfront)
                                        <span class="badge bg-info-subtle text-info">
                                            {{ __('Upfront Discount') }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">
                                            {{ __('Redeem Commission') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $isExpired = $voucher->isExpired() && $voucher->status === 'unused';
                                        $statusClass = $isExpired ? 'secondary' : ($voucher->status === 'used' ? 'danger' : 'success');
                                        $statusLabel = $isExpired ? 'Expired' : ($voucher->status === 'used' ? 'Used' : 'Unused');
                                    @endphp
                                    <span class="badge bg-{{ $statusClass }}-subtle text-{{ $statusClass }}">
                                        {{ __($statusLabel) }}
                                    </span>
                                </td>
                                <td>
                                    @if($voucher->usedBy)
                                        <div class="fw-semibold text-dark">{{ $voucher->usedBy->customer_name }}</div>
                                        <div class="text-muted small">A/C: {{ $voucher->usedBy->customer_unique_id }}</div>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ $voucher->expiry_date->format('d M Y') }}
                                </td>
                                <td class="pe-4 small text-muted">
                                    {{ $voucher->used_at ? $voucher->used_at->format('d M Y, h:i A') : '-' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="p-0">
                                    <x-ui.empty-state icon="bi-ticket-perforated" :title="__('No vouchers found.')" :description="__('Adjust your search or status filter to view reseller vouchers.')" />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($vouchers->hasPages())
                <div class="px-4 py-3 border-top">
                    {{ $vouchers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
