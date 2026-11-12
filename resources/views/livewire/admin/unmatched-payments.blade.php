<div>
    <div class="zoom-in">

        {{-- Header --}}
        <x-ui.page-header
            :title="__('Unmatched & automated payments')"
            :subtitle="__('Audit M-Pesa SMS confirmations, C2B receipts, and manually match unassigned payments')"
        >
            <x-slot:actions>
                <a href="{{ route('admin.payment-gateways') }}" class="btn btn-sm btn-secondary">
                    <i class="bi bi-gear-fill me-1"></i> {{ __('Gateway Settings') }}
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Notifications --}}
        @if ($statusMessage)
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ $statusMessage }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errorMessage)
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $errorMessage }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Filter & Search Card --}}
        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5 col-12">
                        <label class="form-label mb-1">{{ __('Search Code, Phone, Reference') }}</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input wire:model.live.debounce.300ms="search" type="text" class="form-control form-control-sm" placeholder="e.g. QK71XYZ99, 254712345678, user123...">
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label mb-1">{{ __('Status') }}</label>
                        <select wire:model.live="statusFilter" class="form-select form-select-sm">
                            <option value="pending">{{ __('Pending Review') }} ({{ $pendingCount }})</option>
                            <option value="matched">{{ __('Auto Matched') }}</option>
                            <option value="manual_matched">{{ __('Manually Matched') }}</option>
                            <option value="ignored">{{ __('Ignored') }}</option>
                            <option value="all">{{ __('All Transactions') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label mb-1">{{ __('Gateway Source') }}</label>
                        <select wire:model.live="sourceFilter" class="form-select form-select-sm">
                            <option value="all">{{ __('All Sources') }}</option>
                            <option value="mpesa_sms">M-Pesa SMS</option>
                            <option value="daraja">Daraja STK</option>
                            <option value="daraja_c2b">Daraja C2B</option>
                            <option value="payhero">PayHero</option>
                            <option value="kopokopo">KopoKopo</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-12 text-end">
                        <button wire:click="$set('search', '')" class="btn btn-sm btn-secondary w-100" title="{{ __('Clear') }}">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table Card --}}
        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">{{ __('Date / Time') }}</th>
                            <th>{{ __('Source') }}</th>
                            <th>{{ __('Transaction Code') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Phone / Sender') }}</th>
                            <th>{{ __('Reference / Acc') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Customer / Admin') }}</th>
                            <th class="text-end pe-4">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="ps-4 small text-muted">
                                    {{ $payment->created_at->format('d M Y, H:i') }}
                                </td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill">
                                        {{ str_replace('_', ' ', $payment->source) }}
                                    </span>
                                </td>
                                <td>
                                    <code class="fw-semibold text-dark fs-7">{{ $payment->transaction_code }}</code>
                                </td>
                                <td class="fw-semibold text-emerald-600 tabular-nums">
                                    KES {{ number_format($payment->amount, 2) }}
                                </td>
                                <td>
                                    <div class="small fw-semibold">{{ $payment->phone ?: 'N/A' }}</div>
                                    <small class="text-muted">{{ $payment->sender_name }}</small>
                                </td>
                                <td>
                                    @if ($payment->account_reference)
                                        <span class="badge bg-secondary">{{ $payment->account_reference }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($payment->status === 'pending')
                                        <span class="badge bg-warning rounded-pill">
                                            <i class="bi bi-hourglass-split me-1"></i>{{ __('Pending') }}
                                        </span>
                                    @elseif ($payment->status === 'matched')
                                        <span class="badge bg-success rounded-pill">
                                            <i class="bi bi-check-all me-1"></i>{{ __('Auto Matched') }}
                                        </span>
                                    @elseif ($payment->status === 'manual_matched')
                                        <span class="badge bg-info rounded-pill">
                                            <i class="bi bi-person-check me-1"></i>{{ __('Manual Matched') }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary rounded-pill">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($payment->customer)
                                        <a href="{{ route('customers.index') }}?search={{ $payment->customer->customer_unique_id }}" class="fw-semibold text-orange-600 text-decoration-none">
                                            {{ $payment->customer->customer_name }} ({{ $payment->customer->customer_unique_id }})
                                        </a>
                                        @if ($payment->assignedByUser)
                                            <div class="text-muted fs-8">by {{ $payment->assignedByUser->name }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if ($payment->status === 'pending')
                                        <button wire:click="openAssignModal({{ $payment->id }})" class="btn btn-xs btn-primary me-1">
                                            <i class="bi bi-link-45deg me-1"></i> {{ __('Assign') }}
                                        </button>
                                        <button wire:click="ignorePayment({{ $payment->id }})" class="btn btn-xs btn-secondary" title="{{ __('Ignore') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @else
                                        <span class="text-muted small">
                                            <i class="bi bi-check-circle-fill text-emerald-500"></i>
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-0">
                                    <x-ui.empty-state
                                        icon="bi-inbox"
                                        :title="__('No unmatched payments')"
                                        :description="__('No unmatched payment records found matching the criteria.')"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="card-footer py-3">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>

        {{-- Manual Assignment Modal --}}
        @if ($selectedPaymentId && $selectedPayment)
            <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(2px);" aria-modal="true" role="dialog">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-link-45deg text-orange-500 me-2"></i>{{ __('Assign Payment to Customer') }}
                            </h5>
                            <button type="button" wire:click="closeAssignModal" class="btn-close" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            {{-- Payment Summary Card --}}
                            <div class="bg-slate-50 rounded-xl p-3 mb-4 ring-1 ring-slate-200/70">
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-3 col-6">
                                        <small class="text-muted d-block">{{ __('Transaction') }}</small>
                                        <strong class="text-dark">{{ $selectedPayment->transaction_code }}</strong>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <small class="text-muted d-block">{{ __('Amount') }}</small>
                                        <strong class="text-emerald-600 tabular-nums">KES {{ number_format($selectedPayment->amount, 2) }}</strong>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <small class="text-muted d-block">{{ __('Sender Mobile') }}</small>
                                        <span>{{ $selectedPayment->phone ?: 'N/A' }}</span>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <small class="text-muted d-block">{{ __('SMS Reference') }}</small>
                                        <span>{{ $selectedPayment->account_reference ?: 'None' }}</span>
                                    </div>
                                </div>
                                @if ($selectedPayment->raw_message)
                                    <hr class="my-2">
                                    <small class="text-muted d-block font-monospace fs-8">{{ $selectedPayment->raw_message }}</small>
                                @endif
                            </div>

                            {{-- Customer Search --}}
                            <div class="mb-3">
                                <label class="form-label">{{ __('Search Customer (Name, Customer ID, PPP Username, Phone)') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input wire:model.live.debounce.300ms="customerSearch" type="text" class="form-control" placeholder="Type customer name or username...">
                                </div>
                            </div>

                            {{-- Search Results List --}}
                            @if ($searchedCustomers->isNotEmpty())
                                <div class="list-group mb-3 rounded-xl overflow-hidden ring-1 ring-slate-200/70">
                                    @foreach ($searchedCustomers as $cust)
                                        <button type="button" wire:click="selectCustomer({{ $cust->id }})" 
                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selectedCustomerId === $cust->id ? 'bg-orange-50 text-orange-600' : '' }}">
                                            <div>
                                                <strong class="d-block">{{ $cust->customer_name }}</strong>
                                                <small class="text-muted">
                                                    ID: {{ $cust->customer_unique_id }} | PPP: {{ $cust->pppUser->username ?? 'N/A' }} | Phone: {{ $cust->mobile }}
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-secondary tabular-nums">
                                                    Due: KES {{ number_format($cust->billing->due_amount ?? 0, 2) }}
                                                </span>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (strlen(trim($customerSearch)) >= 2)
                                <div class="alert alert-warning py-2 small mb-3">
                                    {{ __('No customer found matching "') }}{{ $customerSearch }}{{ __('"') }}
                                </div>
                            @endif

                            {{-- Selected Customer Card --}}
                            @if ($selectedCustomer)
                                <div class="alert alert-success p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                            <strong>{{ $selectedCustomer->customer_name }}</strong> ({{ $selectedCustomer->customer_unique_id }})
                                            <div class="small text-muted mt-1 tabular-nums">
                                                Current Due: KES {{ number_format($selectedCustomer->billing->due_amount ?? 0, 2) }} | 
                                                Package Rent: KES {{ number_format($selectedCustomer->billing->monthly_rent ?? 0, 2) }}
                                            </div>
                                        </div>
                                        <span class="badge bg-success">{{ __('Selected') }}</span>
                                    </div>
                                </div>
                            @endif

                            {{-- Admin Notes --}}
                            <div class="mb-3">
                                <label class="form-label">{{ __('Admin Notes (Optional)') }}</label>
                                <textarea wire:model="adminNotes" class="form-control form-control-sm" rows="2" placeholder="e.g. Customer called to confirm payment from brother's phone..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" wire:click="closeAssignModal" class="btn btn-sm btn-secondary">
                                {{ __('Cancel') }}
                            </button>
                            <button type="button" wire:click="assignPayment" 
                                class="btn btn-sm btn-primary"
                                @if (!$selectedCustomerId) disabled @endif>
                                <i class="bi bi-lightning-charge-fill me-1"></i> {{ __('Assign & Renew Customer Now') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

