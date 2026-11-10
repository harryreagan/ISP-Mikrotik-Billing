@if (auth()->user()->roles()->exists() || auth()->user()->permissions()->exists() || in_array(strtolower(auth()->user()->role ?? ''), ['admin', 'superadmin', 'super_admin']))
    @if (auth()->user()->hasRole('Reseller'))
        <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">

            {{-- ── Dashboard (always visible) ── --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('RESELLER PANEL') }}</div>
                    <div class="col ps-0">
                        <hr class="mb-0 navbar-vertical-divider" />
                    </div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('reseller.dashboard') }}"
                    role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-speedometer2 me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Dashboard') }}</span>
                    </div>
                </a>
            </li>

            {{-- ── Customers ── --}}
            @canany(['view-customer', 'create-customer', 'edit-customer', 'delete-customer'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">{{ __('CUSTOMERS') }}</div>
                        <div class="col ps-0">
                            <hr class="mb-0 navbar-vertical-divider" />
                        </div>
                    </div>
                    <a wire:navigate.hover wire:current.exact="active" class="nav-link"
                        href="{{ route('reseller.customers.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-people-fill"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Customer List') }}</span>
                        </div>
                    </a>
                    @can('create-customer')
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('reseller.customers.create') }}" role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-person-fill-add"></i></span>
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
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">{{ __('FINANCE') }}</div>
                        <div class="col ps-0">
                            <hr class="mb-0 navbar-vertical-divider" />
                        </div>
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
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('OUTREACH') }}</div>
                    <div class="col ps-0">
                        <hr class="mb-0 navbar-vertical-divider" />
                    </div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('reseller.wallet.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wallet2 text-success"></i></span>
                        <span class="nav-link-text ps-1">{{ __('My Wallet') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('reseller.vouchers.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-ticket-perforated-fill text-warning"></i></span>
                        <span class="nav-link-text ps-1">{{ __('My Vouchers') }}</span>
                    </div>
                </a>
            </li>

            {{-- ── Packages (if permitted) ── --}}
            @can('package-setup')
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">{{ __('ADMIN') }}</div>
                        <div class="col ps-0">
                            <hr class="mb-0 navbar-vertical-divider" />
                        </div>
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
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('CUSTOMERS') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>

                {{-- Dashboard --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('dashboard') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-speedometer2 me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Dashboard') }}</span>
                    </div>
                </a>

                {{-- New Customer --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('new-customer') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-person-fill-add"></i></span>
                        <span class="nav-link-text ps-1">{{ __('New Customer') }}</span>
                    </div>
                </a>

                {{-- Customer List --}}
                <a wire:navigate.hover wire:current.exact="active" class="nav-link" href="{{ route('customers.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-people-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Customer List') }}</span>
                    </div>
                </a>

                {{-- Support Tickets --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin-tickets') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-chat-left-text-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Support Tickets') }}</span>
                    </div>
                </a>

                {{-- Customer Reviews --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.reviews') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-chat-heart"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Customer Reviews') }}</span>
                    </div>
                </a>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 2. NETWORK                                                     --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('NETWORK') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>

                {{-- Mikrotik Sync --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-sync') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-router-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('MikroTik Sync') }}</span>
                    </div>
                </a>

                {{-- Hotspot Manager --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-hotspot-manager') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wifi"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Hotspot Manager') }}</span>
                    </div>
                </a>

                {{-- Mikrotik Setup Dropdown --}}
                <a class="nav-link dropdown-indicator collapsed" href="#mikrotikSetup" role="button"
                    data-bs-toggle="collapse" aria-expanded="false" aria-controls="mikrotikSetup">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-tools"></i></span>
                        <span class="nav-link-text ps-1">{{ __('MikroTik Setup') }}</span>
                    </div>
                </a>
                <ul class="nav collapse" id="mikrotikSetup">
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-ip-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('IP & Pool') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-pppoe-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('PPPoE Server') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-radius-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('RADIUS') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-firewall-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Firewall') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-walled-garden') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Walled Garden') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-queue-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Queues') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-vpn-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('VPN Server') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-interface-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Interfaces & VLAN') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-traffic-monitor') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Live Traffic') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-backup-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Backup & Restore') }}</span></div>
                        </a>
                    </li>
                </ul>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 3. FINANCE                                                     --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('FINANCE') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
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
                        <span class="nav-link-icon"><i class="bi bi-patch-question-fill text-warning"></i></span>
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
                            <span class="badge rounded-pill ms-auto bg-warning text-dark">{{ $pendingUnmatchedCount }}</span>
                        @endif
                    </div>
                </a>

                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.payment-gateways') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-credit-card-2-front-fill text-primary"></i></span>
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
                        <span class="nav-link-icon"><i class="bi bi-graph-up-arrow text-success"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Profit & Loss') }}</span>
                    </div>
                </a>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 4. OUTREACH                                                    --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('OUTREACH') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>

                {{-- SMS Management Dropdown --}}
                <a class="nav-link dropdown-indicator collapsed" href="#smsSetupDropdown" role="button"
                    data-bs-toggle="collapse" aria-expanded="false" aria-controls="smsSetupDropdown">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-envelope-check"></i></span>
                        <span class="nav-link-text ps-1">{{ __('SMS Management') }}</span>
                    </div>
                </a>
                <ul class="nav collapse" id="smsSetupDropdown">
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('sms-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('SMS Setup') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('sms-bridge.index') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('SMS Bridge') }}</span></div>
                        </a>
                    </li>
                </ul>

                {{-- Reseller Setup --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.resellers.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-person-badge-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Reseller Setup') }}</span>
                    </div>
                </a>

                {{-- Reseller Vouchers --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.vouchers') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-ticket-perforated-fill text-warning"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Reseller Vouchers') }}</span>
                    </div>
                </a>
            </li>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- 5. INSIGHTS/REPORTS                                            --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('INSIGHTS/REPORTS') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>

                {{-- Collections Report --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('collection-report.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-bar-chart-line-fill text-info"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Collections Report') }}</span>
                    </div>
                </a>

                {{-- Customer Summary --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('customer-summary') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-pie-chart-fill text-primary"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Customer Summary') }}</span>
                    </div>
                </a>

                {{-- DIS Summary --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('dis-report') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-graph-down text-danger"></i></span>
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
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('ADMIN') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>

                {{-- Package Setup --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('package-list-setup') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-box2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Package Setup') }}</span>
                    </div>
                </a>

                {{-- Purchase Requests --}}
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.purchase-requests') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-cart-check-fill"></i></span>
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
            </li>

            {{-- ── Dynamic Plugin Extensions (if any) ── --}}
            @if(!empty($sectionItems))
                @foreach($sectionItems as $sectionTitle => $items)
                    <li class="nav-item">
                        <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                            <div class="col-auto navbar-vertical-label">{{ strtoupper($sectionTitle) }}</div>
                            <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
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
            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                <div class="col-auto navbar-vertical-label">{{ __('ACCOUNT') }}</div>
                <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
            </div>
            <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('profile.show') }}" role="button">
                <div class="d-flex align-items-center">
                    <span class="nav-link-icon"><i class="bi bi-person-fill"></i></span>
                    <span class="nav-link-text ps-1">{{ __('Profile') }}</span>
                </div>
            </a>
        </li>
    </ul>
@endif

<div class="text-center py-2 mt-auto" style="font-size:0.72rem; color:#888; font-weight:600;">
    &copy; {{ date('Y') }} HSmart WiFi Billing
</div>
