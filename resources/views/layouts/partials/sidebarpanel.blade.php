@if (auth()->user()->roles()->exists() || auth()->user()->permissions()->exists() || in_array(strtolower(auth()->user()->role ?? ''), ['admin', 'superadmin', 'super_admin']))
    @if (auth()->user()->hasRole('Reseller'))
        <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">

            {{-- ── Dashboard (always visible) ── --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                    <div class="col-auto navbar-vertical-label">{{ __('Reseller panel') }}</div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('reseller.dashboard') }}"
                    role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-speedometer2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Dashboard') }}</span>
                    </div>
                </a>
            </li>

            {{-- ── Customers ── --}}
            @canany(['view-customer', 'create-customer', 'edit-customer', 'delete-customer'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                        <div class="col-auto navbar-vertical-label">{{ __('Customers') }}</div>
                        
                    </div>
                    <a wire:navigate.hover wire:current.exact="active" class="nav-link"
                        href="{{ route('reseller.customers.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-people"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Customer List') }}</span>
                        </div>
                    </a>
                    @can('create-customer')
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('reseller.customers.create') }}" role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-person-plus"></i></span>
                                <span class="nav-link-text ps-1">{{ __('New Customer') }}</span>
                            </div>
                        </a>
                    @endcan
                </li>
            @endcanany

            {{-- ── Billing & Payments ── --}}
            @canany(['payment-collection', 'payment-collection-edit', 'payment-collection-invoice', 'payment-history',
                'payment-collection-report', 'collection-list', 'without-collection-list', 'amount-collection'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                        <div class="col-auto navbar-vertical-label">{{ __('Finance') }}</div>
                    </div>
                    @can('payment-collection')
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-collection') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-cash-coin"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Payment Collection') }}</span>
                            </div>
                        </a>
                    @endcan
                    @can('payment-collection-edit')
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('collection-edit') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-pencil-square"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Collection Edit') }}</span>
                            </div>
                        </a>
                    @endcan
                    @can('payment-collection-invoice')
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-invoice') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-receipt"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Payment Invoice') }}</span>
                            </div>
                        </a>
                    @endcan
                    @canany(['payment-collection-report', 'collection-list', 'without-collection-list',
                        'amount-collection'])
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('collection-report.index') }}" role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-bar-chart-line"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Collection Report') }}</span>
                            </div>
                        </a>
                    @endcanany
                </li>
            @endcanany

            {{-- ── Wallet & Vouchers (always visible to all resellers) ── --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                    <div class="col-auto navbar-vertical-label">{{ __('Outreach') }}</div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('reseller.wallet.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wallet2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('My Wallet') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('reseller.vouchers.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-ticket-perforated"></i></span>
                        <span class="nav-link-text ps-1">{{ __('My Vouchers') }}</span>
                    </div>
                </a>
            </li>

            {{-- ── Packages (if permitted) ── --}}
            @can('package-setup')
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                        <div class="col-auto navbar-vertical-label">{{ __('Admin') }}</div>
                    </div>
                    <a wire:navigate.hover wire:current="active" class="nav-link"
                        href="{{ route('reseller.packages.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-box-seam"></i></span>
                            <span class="nav-link-text ps-1">{{ __('My Packages') }}</span>
                        </div>
                    </a>
                </li>
            @endcan

            <li class="nav-item mt-2">
                <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                    @csrf
                    <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start text-danger fw-semibold" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-box-arrow-right text-danger"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Log Out') }}</span>
                        </div>
                    </button>
                </form>
            </li>

        </ul>
    @else
        @php
            $allPluginNavItems = \App\Support\Hooks::applyFilters('sidebar.nav.items', []);
            $subItemsByParent = [];
            $sectionItems = [];

            foreach ($allPluginNavItems as $navEntry) {
                if (!empty($navEntry['permission']) && !auth()->user()->can($navEntry['permission'])) {
                    continue;
                }
                if (!empty($navEntry['roles'])) {
                    $allowedRoles = (array) $navEntry['roles'];
                    if (!in_array('*', $allowedRoles) && !auth()->user()->hasAnyRole($allowedRoles)) {
                        continue;
                    }
                }
                if (!empty($navEntry['parent'])) {
                    $subItemsByParent[strtolower($navEntry['parent'])][] = $navEntry;
                } else {
                    $sec = $navEntry['section'] ?? __('Plugin Extensions');
                    $sectionItems[$sec][] = $navEntry;
                }
            }
        @endphp

        <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 1. CUSTOMERS                                                   --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                    <div class="col-auto navbar-vertical-label">{{ __('Customers') }}</div>
                </div>

                {{-- Dashboard --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('dashboard') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-speedometer2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Dashboard') }}</span>
                    </div>
                </a>

                {{-- Customers --}}
                <a wire:navigate.hover wire:current="active" class="nav-link {{ request()->routeIs('admin.customers') || request()->routeIs('customers.index') || request()->routeIs('new-customer') ? 'active' : '' }}" href="{{ route('admin.customers') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-people"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Customers') }}</span>
                    </div>
                </a>

                {{-- Support Tickets --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin-tickets') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-chat-left-text"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Support Tickets') }}</span>
                    </div>
                </a>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 2. NETWORK                                                     --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                    <div class="col-auto navbar-vertical-label">{{ __('Network') }}</div>
                </div>

                {{-- Routers --}}
                <a wire:navigate.hover wire:current="active" class="nav-link {{ request()->routeIs('admin.routers') || request()->routeIs('mikrotik-sync') || request()->routeIs('admin.add-router') ? 'active' : '' }}" href="{{ route('admin.routers') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-router"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Routers') }}</span>
                    </div>
                </a>

                {{-- Hotspot Manager --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-hotspot-manager') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wifi"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Hotspot Manager') }}</span>
                    </div>
                </a>

                {{-- Captive Portal Customizer --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.portal-customizer') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-palette"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Captive Portal') }}</span>
                    </div>
                </a>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 3. FINANCE                                                     --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                    <div class="col-auto navbar-vertical-label">{{ __('Finance') }}</div>
                </div>

                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-collection') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-cash-coin"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Payment Collection') }}</span>
                    </div>
                </a>

                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('collection-edit') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-pencil-square"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Collection Edit') }}</span>
                    </div>
                </a>

                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-invoice') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-receipt"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Payment Invoice') }}</span>
                    </div>
                </a>

                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.unmatched-payments') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-patch-question"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Unmatched Payments') }}</span>
                        @php
                            $pendingUnmatchedCount = 0;
                            try {
                                if (\Illuminate\Support\Facades\Schema::hasTable('unmatched_payments')) {
                                    $pendingUnmatchedCount = \App\Models\UnmatchedPayment::where('status', 'pending')->count();
                                }
                            } catch (\Throwable $e) {}
                        @endphp
                        @if($pendingUnmatchedCount > 0)
                            <span class="badge rounded-pill ms-auto bg-warning">{{ $pendingUnmatchedCount }}</span>
                        @endif
                    </div>
                </a>

                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.payment-gateways') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-credit-card-2-front"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Payment Gateways') }}</span>
                    </div>
                </a>

                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.expenses') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wallet2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Expense Management') }}</span>
                    </div>
                </a>

                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.profit-summary') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Profit & Loss') }}</span>
                    </div>
                </a>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 4. OUTREACH                                                    --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                    <div class="col-auto navbar-vertical-label">{{ __('Outreach') }}</div>
                </div>

                {{-- SMS & Email Hub (Merged 5-Tab Interface) --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.sms-email') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-chat-square-quote"></i></span>
                        <span class="nav-link-text ps-1">{{ __('SMS & Email') }}</span>
                    </div>
                </a>

                {{-- Reseller Setup --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.resellers.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-person-badge"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Reseller Setup') }}</span>
                    </div>
                </a>

                {{-- Reseller Vouchers --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.vouchers') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-ticket-perforated"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Reseller Vouchers') }}</span>
                    </div>
                </a>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 5. INSIGHTS/REPORTS                                            --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                    <div class="col-auto navbar-vertical-label">{{ __('Insights & reports') }}</div>
                </div>

                {{-- Collections Report --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('collection-report.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-bar-chart-line"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Collections Report') }}</span>
                    </div>
                </a>

                {{-- Customer Summary --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('customer-summary') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-pie-chart"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Customer Summary') }}</span>
                    </div>
                </a>

                {{-- DIS Summary --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('dis-report') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-graph-down"></i></span>
                        <span class="nav-link-text ps-1">{{ __('DIS Summary') }}</span>
                    </div>
                </a>

                {{-- Logs & Audits Dropdown --}}
                <a class="nav-link dropdown-indicator collapsed" href="#logsSetupDropdown" role="button"
                    data-bs-toggle="collapse" aria-expanded="false" aria-controls="logsSetupDropdown">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-journal-text"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Logs & Audits') }}</span>
                    </div>
                </a>
                <ul class="nav collapse" id="logsSetupDropdown">
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.activity-logs') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Activity Logs') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.login-logs') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Login Logs') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.system-logs') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('System Logs') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-log-viewer') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Router Logs') }}</span></div>
                        </a>
                    </li>
                </ul>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 6. ADMIN                                                       --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                    <div class="col-auto navbar-vertical-label">{{ __('Admin') }}</div>
                </div>

                {{-- Package Setup --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.packages') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-box2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Packages') }}</span>
                    </div>
                </a>

                {{-- Purchase Requests --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.purchase-requests') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-cart-check"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Purchase Requests') }}</span>
                    </div>
                </a>

                {{-- Address Setup --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('address-setup') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-buildings"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Address Setup') }}</span>
                    </div>
                </a>

                {{-- My Profile --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('profile.show') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-person-circle"></i></span>
                        <span class="nav-link-text ps-1">{{ __('My Profile') }}</span>
                    </div>
                </a>

                {{-- Log Out --}}
                <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                    @csrf
                    <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start text-danger fw-medium" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-box-arrow-right text-danger"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Log Out') }}</span>
                        </div>
                    </button>
                </form>
            </li>

            {{-- ── Dynamic Plugin Extensions (if any) ── --}}
            @if(!empty($sectionItems))
                @foreach($sectionItems as $sectionTitle => $items)
                    <li class="nav-item">
                        <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                            <div class="col-auto navbar-vertical-label">{{ ucfirst(strtolower($sectionTitle)) }}</div>
                        </div>
                        @foreach($items as $idx => $pItem)
                            @php
                                $targetUrl = isset($pItem['route']) && \Illuminate\Support\Facades\Route::has($pItem['route']) 
                                    ? route($pItem['route']) 
                                    : ($pItem['url'] ?? '#');
                                $itemIcon = $pItem['icon'] ?? 'bi bi-puzzle';
                                $itemTitle = $pItem['title'] ?? 'Plugin Menu';
                            @endphp
                            <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ $targetUrl }}" role="button">
                                <div class="d-flex align-items-center">
                                    <span class="nav-link-icon"><i class="{{ $itemIcon }}"></i></span>
                                    <span class="nav-link-text ps-1">{{ __($itemTitle) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </li>
                @endforeach
            @endif

        </ul>
    @endif
@else
    <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
        <li class="nav-item">
            <div class="row navbar-vertical-label-wrapper mt-3 mb-1">
                <div class="col-auto navbar-vertical-label">{{ __('Account') }}</div>
            </div>
            <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('profile.show') }}" role="button">
                <div class="d-flex align-items-center">
                    <span class="nav-link-icon"><i class="bi bi-person"></i></span>
                    <span class="nav-link-text ps-1">{{ __('Profile') }}</span>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                @csrf
                <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start text-danger fw-medium" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-box-arrow-right text-danger"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Log Out') }}</span>
                    </div>
                </button>
            </form>
        </li>
    </ul>
@endif

<div class="text-center py-2 mt-auto" style="font-size: 11px; color: #94a3b8; font-weight: 500;">
    &copy; {{ date('Y') }} HSmart WiFi Billing
</div>
