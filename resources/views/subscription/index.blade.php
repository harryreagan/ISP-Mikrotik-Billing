<x-app-layout>
    <div class="content">
        {{-- Page Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Subscription & Plan</li>
                    </ol>
                </nav>
                <h2 class="h3 font-weight-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span>Subscription & Plan Management</span>
                    @if($subscription['status'] === 'active')
                        <span class="badge bg-success-subtle text-success fs-7 px-3 py-1 rounded-pill border border-success-subtle">
                            <i class="bi bi-shield-check me-1"></i> Active ({{ $subscription['days_left'] }} days left)
                        </span>
                    @elseif($subscription['status'] === 'trial')
                        <span class="badge bg-warning-subtle text-warning-emphasis fs-7 px-3 py-1 rounded-pill border border-warning-subtle">
                            <i class="bi bi-clock-history me-1"></i> Trial ({{ $subscription['days_left'] }} days left)
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger fs-7 px-3 py-1 rounded-pill border border-danger-subtle">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Expired
                        </span>
                    @endif
                </h2>
                <p class="text-muted mb-0">Manage your HSmart ISP software license, active plan limits, and automated renewals.</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary" onclick="openPaymentModal('{{ $subscription['plan_slug'] ?: 'starter' }}', '{{ $subscription['plan_name'] ?: 'Starter' }}', {{ $subscription['plan_price'] ?: 400 }})">
                    <i class="bi bi-arrow-repeat me-1"></i> Renew Subscription
                </button>
            </div>
        </div>

        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm p-3 mb-4 d-flex align-items-center gap-3" role="alert">
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center shrink-0" style="width: 38px; height: 38px;">
                    <i class="bi bi-check-lg fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="d-block text-dark">Success!</strong>
                    <span class="text-secondary small">{{ session('success') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm p-3 mb-4 d-flex align-items-center gap-3" role="alert">
                <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center shrink-0" style="width: 38px; height: 38px;">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="d-block text-dark">Payment Notice</strong>
                    <span class="text-secondary small">{{ session('error') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Current Subscription Overview Card --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    {{-- Plan Badge & Expiry --}}
                    <div class="col-lg-4 col-md-6 border-end-md">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary" style="width: 58px; height: 58px;">
                                <i class="bi bi-hdd-network fs-3"></i>
                            </div>
                            <div>
                                <small class="text-uppercase text-muted fw-bold letter-spacing-1">Current Plan</small>
                                <h3 class="fw-bold mb-0 text-dark">{{ $subscription['plan_name'] }} Plan</h3>
                                <p class="text-muted small mb-0">
                                    KES {{ number_format($subscription['plan_price']) }} / month
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span class="text-muted">Subscription Validity:</span>
                                <span class="fw-semibold text-dark">{{ $subscription['expires_at_formatted'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center small">
                                <span class="text-muted">Status:</span>
                                <span class="badge {{ $subscription['status'] === 'active' ? 'bg-success' : ($subscription['status'] === 'trial' ? 'bg-warning text-dark' : 'bg-danger') }}">
                                    {{ ucfirst($subscription['status']) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Routers Usage --}}
                    <div class="col-lg-4 col-md-6 border-end-lg">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-router text-primary"></i> Routers Connected
                            </span>
                            <span class="badge bg-light text-dark border">
                                {{ $subscription['router_count'] }} / {{ $subscription['router_limit'] }}
                            </span>
                        </div>
                        @php
                            $routerPercent = $subscription['router_limit'] > 0 ? min(100, round(($subscription['router_count'] / $subscription['router_limit']) * 100)) : 0;
                        @endphp
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar {{ $routerPercent >= 90 ? 'bg-danger' : ($routerPercent >= 70 ? 'bg-warning' : 'bg-primary') }}" 
                                 role="progressbar" style="width: {{ $routerPercent }}%;" aria-valuenow="{{ $routerPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            @if($subscription['router_count'] >= $subscription['router_limit'])
                                <span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i> Router limit reached. Upgrade to add more routers.</span>
                            @else
                                {{ $subscription['router_limit'] - $subscription['router_count'] }} router slot(s) available.
                            @endif
                        </small>
                    </div>

                    {{-- Customers Usage --}}
                    <div class="col-lg-4 col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-people text-success"></i> Customer Capacity
                            </span>
                            <span class="badge bg-light text-dark border">
                                {{ $subscription['customer_count'] }} / {{ $subscription['customer_limit'] }}
                            </span>
                        </div>
                        @php
                            $custPercent = $subscription['customer_limit'] > 0 ? min(100, round(($subscription['customer_count'] / $subscription['customer_limit']) * 100)) : 0;
                        @endphp
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar {{ $custPercent >= 90 ? 'bg-danger' : ($custPercent >= 70 ? 'bg-warning' : 'bg-success') }}" 
                                 role="progressbar" style="width: {{ $custPercent }}%;" aria-valuenow="{{ $custPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            @if($subscription['customer_count'] >= $subscription['customer_limit'])
                                <span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i> Customer capacity reached. Upgrade for more users.</span>
                            @else
                                {{ $subscription['customer_limit'] - $subscription['customer_count'] }} customer slot(s) remaining.
                            @endif
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Available Subscription Plans --}}
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1">Choose or Upgrade Your Plan</h4>
            <p class="text-muted small mb-4">Pay automatically with M-Pesa STK Push or Paystack. Instant activation without downtime.</p>

            <div class="row g-4">
                @foreach($plans as $plan)
                    @php
                        $isCurrent = ($subscription['plan_slug'] === $plan['slug']);
                        $isPopular = !empty($plan['is_popular']);
                    @endphp
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden {{ $isPopular ? 'border-primary border-2' : '' }}" style="{{ $isPopular ? 'border: 2px solid #6366F1 !important;' : '' }}">
                            @if($isPopular)
                                <div class="position-absolute top-0 end-0 bg-primary text-white px-3 py-1 fw-bold fs-8 rounded-bottom-start" style="background: #6366F1 !important;">
                                    POPULAR
                                </div>
                            @endif

                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="fw-bold mb-0 text-dark">{{ $plan['name'] }}</h5>
                                        @if($isCurrent)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Current Plan</span>
                                        @endif
                                    </div>

                                    <div class="my-3">
                                        @if($plan['base_price_kes'])
                                            <span class="display-6 fw-bold text-dark">KES {{ number_format($plan['base_price_kes']) }}</span>
                                            <span class="text-muted fs-7">/ month</span>
                                        @else
                                            <span class="display-6 fw-bold text-dark">Custom</span>
                                        @endif
                                    </div>

                                    <hr class="opacity-10 my-3">

                                    <ul class="list-unstyled mb-4">
                                        @foreach($plan['features'] as $feat)
                                            <li class="d-flex align-items-center gap-2 mb-2 small text-secondary">
                                                <i class="bi bi-check-circle-fill text-success fs-7"></i>
                                                <span>{{ $feat }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div>
                                    @if($plan['base_price_kes'])
                                        <button type="button" 
                                                class="btn w-100 py-2 rounded-3 fw-semibold {{ $isCurrent ? 'btn-outline-primary' : ($isPopular ? 'btn-primary text-white' : 'btn-dark') }}"
                                                style="{{ $isPopular && !$isCurrent ? 'background: #6366F1; border-color: #6366F1;' : '' }}"
                                                onclick="openPaymentModal('{{ $plan['slug'] }}', '{{ $plan['name'] }}', {{ $plan['base_price_kes'] }})">
                                            @if($isCurrent)
                                                <i class="bi bi-arrow-repeat me-1"></i> Renew {{ $plan['name'] }}
                                            @else
                                                <i class="bi bi-lightning-charge-fill me-1"></i> Choose {{ $plan['name'] }}
                                            @endif
                                        </button>
                                    @else
                                        <a href="https://wa.me/254714652230?text=Hello%20HSmart,%20I%20would%20like%20to%20inquire%20about%20the%20Enterprise%20ISP%20Plan" 
                                           target="_blank" class="btn btn-outline-dark w-100 py-2 rounded-3 fw-semibold">
                                            <i class="bi bi-whatsapp me-1"></i> Contact Enterprise Sales
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Payment History Ledger --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0">Subscription Payment History</h5>
                <span class="text-muted small">All transactions processed via Safaricom M-Pesa & Paystack</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Reference</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Gateway</th>
                                <th>Receipt / Ext Ref</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $pmt)
                                <tr>
                                    <td class="ps-4 small text-muted">
                                        {{ $pmt->created_at ? $pmt->created_at->format('d M Y, h:i A') : '-' }}
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $pmt->reference }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ ucfirst($pmt->plan_slug) }}</span>
                                    </td>
                                    <td class="fw-bold text-dark">KES {{ number_format($pmt->amount, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $pmt->gateway === 'paystack' ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-success-subtle text-success border border-success-subtle' }}">
                                            {{ strtoupper($pmt->gateway) }}
                                        </span>
                                    </td>
                                    <td class="font-monospace small text-muted">
                                        {{ $pmt->external_reference ?: '-' }}
                                    </td>
                                    <td>
                                        @if($pmt->status === 'completed')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-check-circle me-1"></i> Completed
                                            </span>
                                        @elseif($pmt->status === 'pending')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                <i class="bi bi-clock me-1"></i> Pending
                                            </span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                <i class="bi bi-x-circle me-1"></i> Failed
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-receipt fs-1 d-block mb-2 text-muted opacity-50"></i>
                                        No subscription payment records yet. Select a plan above to activate your subscription.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modern Subscription Payment Modal (Styled like SmsTopUpModal.vue) --}}
    <div class="modal fade" id="subscriptionPaymentModal" tabindex="-1" aria-labelledby="subscriptionPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                {{-- Modal Header --}}
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center text-white shadow-sm" 
                             id="modalHeaderIconBadge"
                             style="width: 44px; height: 44px; background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                            <i class="bi bi-phone-fill fs-5" id="modalHeaderIcon"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="subscriptionPaymentModalLabel">Subscription Checkout</h5>
                            <small class="text-muted">System Gateway Renewal (Harry Smart Tech)</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    {{-- Selected Plan Summary Box --}}
                    <div class="p-3 rounded-3 mb-3 d-flex justify-content-between align-items-center" style="background: #F8FAFC; border: 1px dashed #CBD5E1;">
                        <div>
                            <span class="text-muted small d-block">Target Plan</span>
                            <strong class="text-dark fs-6" id="modalPlanName">Starter Plan</strong>
                            <small class="text-primary d-block fw-semibold" style="font-size: 11px;">
                                <i class="bi bi-shield-check me-1"></i> 30 Days License Validity
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block">Payable Amount</span>
                            <strong class="text-success fs-5" id="modalPlanPrice">KES 400</strong>
                        </div>
                    </div>

                    {{-- Step 1: Input Form --}}
                    <div id="paymentInputSection">
                        {{-- Select Payment Method Cards (M-Pesa vs Paystack) --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase text-muted" style="font-size: 11px; letter-spacing: 0.5px;">
                                Select Payment Method <span class="text-danger">*</span>
                            </label>
                            <div class="row g-2">
                                {{-- Card 1: M-Pesa STK Push --}}
                                <div class="col-6">
                                    <button type="button" 
                                            class="w-100 p-2.5 rounded-3 border text-start transition d-flex align-items-center gap-2 position-relative" 
                                            id="btnSelectMpesa"
                                            onclick="selectGateway('mpesa')"
                                            style="border: 2px solid #10B981 !important; background: #ECFDF5;">
                                        <div class="rounded-2 p-1.5 d-flex align-items-center justify-content-center text-white shrink-0" 
                                             style="width: 34px; height: 34px; background: #10B981;">
                                            <i class="bi bi-phone-fill fs-6"></i>
                                        </div>
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="fw-bold text-dark text-truncate" style="font-size: 12px;">M-Pesa STK Push</div>
                                            <div class="text-muted text-truncate" style="font-size: 10px;">Instant prompt on phone</div>
                                        </div>
                                    </button>
                                </div>

                                {{-- Card 2: Paystack --}}
                                <div class="col-6">
                                    <button type="button" 
                                            class="w-100 p-2.5 rounded-3 border text-start transition d-flex align-items-center gap-2 position-relative" 
                                            id="btnSelectPaystack"
                                            onclick="selectGateway('paystack')"
                                            style="border: 1px solid #E2E8F0; background: #FFFFFF;">
                                        <div class="rounded-2 p-1.5 d-flex align-items-center justify-content-center text-white shrink-0" 
                                             style="width: 34px; height: 34px; background: #06B6D4;">
                                            <i class="bi bi-credit-card-2-front-fill fs-6"></i>
                                        </div>
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="fw-bold text-dark text-truncate" style="font-size: 12px;">Paystack</div>
                                            <div class="text-muted text-truncate" style="font-size: 10px;">Cards, Bank & M-Pesa</div>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Section A: M-Pesa Input --}}
                        <div id="mpesaFieldsSection" class="mb-3">
                            <label for="subscriberPhone" class="form-label fw-semibold text-dark small mb-1">
                                Safaricom M-Pesa Phone Number <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">🇰🇪 +254</span>
                                <input type="tel" 
                                       class="form-control form-control-lg fs-6 font-monospace" 
                                       id="subscriberPhone" 
                                       placeholder="712345678" 
                                       value="{{ ltrim($user->phone ?? ($user->mobile ?? ''), '254') }}"
                                       autocomplete="tel">
                            </div>
                            <small class="text-muted mt-1 d-block" style="font-size: 11px;">
                                <i class="bi bi-info-circle me-1"></i> An instant prompt will appear on this phone requesting your M-Pesa PIN.
                            </small>
                        </div>

                        {{-- Section B: Paystack Input --}}
                        <div id="paystackFieldsSection" class="mb-3 d-none">
                            <label for="subscriberEmail" class="form-label fw-semibold text-dark small mb-1">
                                Payment Receipt Email <span class="text-danger">*</span>
                            </label>
                            <div class="input-group mb-2">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope-fill"></i></span>
                                <input type="email" 
                                       class="form-control form-control-lg fs-6" 
                                       id="subscriberEmail" 
                                       placeholder="admin@yourisp.com" 
                                       value="{{ $user->email ?? '' }}"
                                       autocomplete="email">
                            </div>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-2 border bg-light" style="font-size: 11px;">
                                <span class="text-muted"><i class="bi bi-shield-check text-primary me-1"></i> Supported:</span>
                                <span class="fw-semibold text-secondary">Visa • Mastercard • Apple Pay • Bank Transfer • M-Pesa</span>
                            </div>
                        </div>

                        {{-- Error Alert --}}
                        <div id="paymentErrorAlert" class="alert alert-danger d-none py-2 small mb-3"></div>

                        {{-- Action Buttons --}}
                        {{-- Button for M-Pesa --}}
                        <div id="btnMpesaContainer">
                            <button type="button" 
                                    class="btn w-100 py-3 text-white fw-bold rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2" 
                                    id="btnTriggerStk" 
                                    style="background: #10B981; border-color: #10B981;" 
                                    onclick="triggerSubscriptionStk()">
                                <span>Send M-Pesa STK Prompt</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>

                        {{-- Button for Paystack --}}
                        <div id="btnPaystackContainer" class="d-none">
                            <button type="button" 
                                    class="btn w-100 py-3 text-white fw-bold rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2" 
                                    id="btnTriggerPaystack" 
                                    style="background: #06B6D4; border-color: #06B6D4;" 
                                    onclick="triggerSubscriptionPaystack()">
                                <i class="bi bi-lock-fill"></i>
                                <span>Pay Securely with Paystack</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Step 2: Live Waiting & Polling (for M-Pesa) --}}
                    <div id="paymentWaitingSection" class="text-center py-4 d-none">
                        <div class="spinner-border text-success mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
                            <span class="visually-hidden">Waiting for payment...</span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Check Your Phone!</h5>
                        <p class="text-muted small px-3 mb-3">
                            An M-Pesa prompt has been sent to <strong id="waitingPhoneNumber" class="text-dark"></strong>.<br>
                            Please enter your <strong>M-Pesa PIN</strong> to complete your subscription.
                        </p>
                        <div class="badge bg-light text-secondary border px-3 py-2 small">
                            <i class="bi bi-clock-history me-1"></i> Waiting for PIN confirmation... (<span id="countdownTimer">60</span>s)
                        </div>
                    </div>

                    {{-- Step 3: Success Confirmation --}}
                    <div id="paymentSuccessSection" class="text-center py-4 d-none">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-success text-white mb-3 shadow" style="width: 64px; height: 64px;">
                            <i class="bi bi-check-lg fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Subscription Activated!</h4>
                        <p class="text-muted small mb-3">
                            Thank you! Your ISP software subscription has been successfully renewed for 30 days.
                        </p>
                        <button type="button" class="btn btn-primary px-4 py-2 rounded-3" onclick="window.location.reload()">
                            Continue to Dashboard
                        </button>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="modal-footer bg-light border-0 py-2 px-4 justify-content-between rounded-bottom-4">
                    <small class="text-muted d-flex align-items-center gap-1" style="font-size: 11px;">
                        <i class="bi bi-shield-lock-fill text-success"></i> 256-Bit SSL Encrypted & Safaricom / Paystack Verified
                    </small>
                    <button type="button" class="btn btn-sm btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    {{-- Paystack Inline SDK --}}
    <script src="https://js.paystack.co/v1/inline.js"></script>

    <script>
        let currentPlanSlug = '{{ $selectedPlan }}';
        let currentPlanPrice = 400;
        let selectedGateway = 'mpesa'; // 'mpesa' or 'paystack'
        let currentPollInterval = null;
        let countdownSeconds = 60;
        let countdownInterval = null;

        function selectGateway(gw) {
            selectedGateway = gw;
            const btnMpesa = document.getElementById('btnSelectMpesa');
            const btnPaystack = document.getElementById('btnSelectPaystack');
            const mpesaFields = document.getElementById('mpesaFieldsSection');
            const paystackFields = document.getElementById('paystackFieldsSection');
            const btnMpesaContainer = document.getElementById('btnMpesaContainer');
            const btnPaystackContainer = document.getElementById('btnPaystackContainer');
            const iconBadge = document.getElementById('modalHeaderIconBadge');
            const icon = document.getElementById('modalHeaderIcon');

            if (gw === 'mpesa') {
                btnMpesa.style.border = '2px solid #10B981';
                btnMpesa.style.background = '#ECFDF5';
                btnPaystack.style.border = '1px solid #E2E8F0';
                btnPaystack.style.background = '#FFFFFF';

                mpesaFields.classList.remove('d-none');
                paystackFields.classList.add('d-none');
                btnMpesaContainer.classList.remove('d-none');
                btnPaystackContainer.classList.add('d-none');

                iconBadge.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
                icon.className = 'bi bi-phone-fill fs-5';
            } else {
                btnPaystack.style.border = '2px solid #06B6D4';
                btnPaystack.style.background = '#ECFEFF';
                btnMpesa.style.border = '1px solid #E2E8F0';
                btnMpesa.style.background = '#FFFFFF';

                mpesaFields.classList.add('d-none');
                paystackFields.classList.remove('d-none');
                btnMpesaContainer.classList.add('d-none');
                btnPaystackContainer.classList.remove('d-none');

                iconBadge.style.background = 'linear-gradient(135deg, #06B6D4 0%, #0284C7 100%)';
                icon.className = 'bi bi-credit-card-2-front-fill fs-5';
            }
        }

        function openPaymentModal(slug, name, price) {
            currentPlanSlug = slug;
            currentPlanPrice = Number(price);
            document.getElementById('modalPlanName').innerText = name + ' Plan';
            document.getElementById('modalPlanPrice').innerText = 'KES ' + Number(price).toLocaleString();
            
            // Default to M-Pesa
            selectGateway('mpesa');

            // Reset modal state
            document.getElementById('paymentInputSection').classList.remove('d-none');
            document.getElementById('paymentWaitingSection').classList.add('d-none');
            document.getElementById('paymentSuccessSection').classList.add('d-none');
            document.getElementById('paymentErrorAlert').classList.add('d-none');
            
            if (currentPollInterval) clearInterval(currentPollInterval);
            if (countdownInterval) clearInterval(countdownInterval);

            const modal = new bootstrap.Modal(document.getElementById('subscriptionPaymentModal'));
            modal.show();
        }

        // Auto-open modal if plan was passed via query parameter
        @if(request()->filled('plan'))
            document.addEventListener('DOMContentLoaded', function() {
                const preselectedSlug = '{{ request('plan') }}';
                @foreach($plans as $p)
                    if ('{{ $p['slug'] }}' === preselectedSlug && {{ $p['base_price_kes'] ?: 0 }} > 0) {
                        openPaymentModal('{{ $p['slug'] }}', '{{ $p['name'] }}', {{ $p['base_price_kes'] }});
                    }
                @endforeach
            });
        @endif

        // Trigger M-Pesa STK Push
        function triggerSubscriptionStk() {
            const phoneInput = document.getElementById('subscriberPhone');
            const errorAlert = document.getElementById('paymentErrorAlert');
            const btn = document.getElementById('btnTriggerStk');
            
            const phone = phoneInput.value.trim();
            if (!phone) {
                errorAlert.innerText = 'Please enter your Safaricom phone number.';
                errorAlert.classList.remove('d-none');
                return;
            }

            errorAlert.classList.add('d-none');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Initiating M-Pesa STK...';

            fetch('{{ route('subscription.pay.mpesa') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    plan_slug: currentPlanSlug,
                    phone: phone
                })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<span>Send M-Pesa STK Prompt</span><i class="bi bi-arrow-right"></i>';

                if (data.status === 'success') {
                    // Switch to waiting screen
                    document.getElementById('paymentInputSection').classList.add('d-none');
                    document.getElementById('paymentWaitingSection').classList.remove('d-none');
                    document.getElementById('waitingPhoneNumber').innerText = data.phone || phone;

                    startStatusPolling(data.reference);
                } else {
                    errorAlert.innerText = data.message || 'Failed to trigger M-Pesa. Please try again.';
                    errorAlert.classList.remove('d-none');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<span>Send M-Pesa STK Prompt</span><i class="bi bi-arrow-right"></i>';
                errorAlert.innerText = 'Network error. Please try again.';
                errorAlert.classList.remove('d-none');
            });
        }

        // Trigger Paystack Checkout
        function triggerSubscriptionPaystack() {
            const emailInput = document.getElementById('subscriberEmail');
            const errorAlert = document.getElementById('paymentErrorAlert');
            const btn = document.getElementById('btnTriggerPaystack');

            const email = emailInput.value.trim();
            if (!email || !email.includes('@')) {
                errorAlert.innerText = 'Please enter a valid email address for your payment receipt.';
                errorAlert.classList.remove('d-none');
                return;
            }

            errorAlert.classList.add('d-none');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Connecting to Paystack...';

            fetch('{{ route('subscription.pay.paystack') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    plan_slug: currentPlanSlug,
                    email: email
                })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-lock-fill"></i><span>Pay Securely with Paystack</span><i class="bi bi-arrow-right"></i>';

                if (data.status === 'success' && (data.access_code || data.authorization_url)) {
                    // Dismiss modal before launching Paystack popup
                    const modalEl = document.getElementById('subscriptionPaymentModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();

                    if (window.PaystackPop && data.public_key) {
                        const handler = window.PaystackPop.setup({
                            key: data.public_key,
                            email: data.email,
                            amount: data.amount,
                            currency: 'KES',
                            ref: data.reference,
                            callback: function(response) {
                                const ref = (response && response.reference) ? response.reference : data.reference;
                                window.location.href = '/subscription/paystack/verify/' + encodeURIComponent(ref);
                            },
                            onClose: function() {
                                // User closed popup
                            }
                        });
                        handler.openIframe();
                    } else if (data.authorization_url) {
                        window.location.href = data.authorization_url;
                    } else {
                        window.location.href = 'https://checkout.paystack.com/' + data.access_code;
                    }
                } else {
                    errorAlert.innerText = data.message || 'Failed to initialize Paystack. Please try again.';
                    errorAlert.classList.remove('d-none');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-lock-fill"></i><span>Pay Securely with Paystack</span><i class="bi bi-arrow-right"></i>';
                errorAlert.innerText = 'Network connection error. Please try again.';
                errorAlert.classList.remove('d-none');
            });
        }

        function startStatusPolling(reference) {
            countdownSeconds = 60;
            document.getElementById('countdownTimer').innerText = countdownSeconds;

            countdownInterval = setInterval(() => {
                countdownSeconds--;
                document.getElementById('countdownTimer').innerText = countdownSeconds;
                if (countdownSeconds <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);

            let attempts = 0;
            const maxAttempts = 30; // 30 polls * 2s = 60s

            currentPollInterval = setInterval(() => {
                attempts++;
                if (attempts > maxAttempts) {
                    clearInterval(currentPollInterval);
                    clearInterval(countdownInterval);
                    alert('Payment confirmation timed out. If you entered your PIN, your subscription will be activated automatically in the background.');
                    window.location.reload();
                    return;
                }

                fetch('/subscription/check-status/' + encodeURIComponent(reference))
                    .then(res => res.json())
                    .then(res => {
                        if (res.status === 'completed') {
                            clearInterval(currentPollInterval);
                            clearInterval(countdownInterval);
                            
                            document.getElementById('paymentWaitingSection').classList.add('d-none');
                            document.getElementById('paymentSuccessSection').classList.remove('d-none');

                            // Auto-reload to reflect new limits
                            setTimeout(() => {
                                window.location.reload();
                            }, 2500);
                        } else if (res.status === 'failed') {
                            clearInterval(currentPollInterval);
                            clearInterval(countdownInterval);
                            alert(res.message || 'Transaction was cancelled or declined.');
                            window.location.reload();
                        }
                    })
                    .catch(() => {});
            }, 2000);
        }
    </script>
    @endpush
</x-app-layout>
