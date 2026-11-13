<div>
    {{-- Page Header --}}
    <x-ui.page-header title="Customers" subtitle="Manage PPPoE and hotspot subscribers, track online sessions, billing cycles, and send payment reminders.">
        <x-slot:actions>
            <x-ui.button variant="primary" wire:click="openCreateModal">
                <i class="bi bi-person-plus-fill"></i> New customer
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Session Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- 5 Clean Analytics Summary Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Customers --}}
        <div class="col-6 col-lg">
            <x-ui.stat-card label="Subscribers" :value="$totalCustomers" sub="Registered accounts" color="purple">
                <x-slot:icon><i class="bi bi-people"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Active / Online --}}
        <div class="col-6 col-lg">
            <x-ui.stat-card label="Active" :value="$activeCustomers" sub="Operational service" color="green">
                <x-slot:icon><i class="bi bi-check-circle"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Suspended / Due --}}
        <div class="col-6 col-lg">
            <x-ui.stat-card label="Suspended" :value="$suspendedCustomers" sub="Service blocked / overdue" color="pink">
                <x-slot:icon><i class="bi bi-slash-circle"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Monthly Revenue --}}
        <div class="col-6 col-lg">
            <x-ui.stat-card label="PPPoE revenue" :value="'KES ' . number_format($monthlyRevenue, 0)" sub="Monthly package billings" color="blue">
                <x-slot:icon><i class="bi bi-currency-exchange"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Outstanding Balance --}}
        <div class="col-12 col-sm-6 col-lg">
            <x-ui.stat-card label="Unpaid balance" :value="'KES ' . number_format($outstandingBalance, 0)" sub="Total dues pending" color="orange">
                <x-slot:icon><i class="bi bi-wallet2"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
    </div>

    {{-- Filter & Search Toolbar --}}
    <x-ui.card class="mb-4 p-3">
        <div class="row g-2 align-items-center justify-content-between">
            {{-- Search Box --}}
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control border-start-0" placeholder="Search by name, phone, account #, username...">
                </div>
            </div>

            {{-- Router Filter --}}
            <div class="col-12 col-md-3">
                <select wire:model.live="filterRouter" class="form-select">
                    <option value="all">All routers</option>
                    @foreach($routers as $r)
                        <option value="{{ $r->id }}">{{ $r->name ?: $r->router_name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Chips --}}
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-center gap-1.5">
                <button type="button" wire:click="$set('filterType', 'all')" class="btn btn-sm {{ $filterType === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    All
                </button>
                <button type="button" wire:click="$set('filterType', 'pppoe')" class="btn btn-sm {{ $filterType === 'pppoe' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    PPPoE
                </button>
                <button type="button" wire:click="$set('filterType', 'hotspot')" class="btn btn-sm {{ $filterType === 'hotspot' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Hotspot
                </button>
                <button type="button" wire:click="$set('filterType', 'active')" class="btn btn-sm {{ $filterType === 'active' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Active
                </button>
                <button type="button" wire:click="$set('filterType', 'suspended')" class="btn btn-sm {{ $filterType === 'suspended' ? 'btn-danger text-white' : 'btn-outline-secondary' }}">
                    Suspended
                </button>
            </div>
        </div>
    </x-ui.card>

    {{-- Customers Table --}}
    <x-ui.card :padding="false" class="overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 20%;">Customer</th>
                        <th style="width: 12%;">Service / plan</th>
                        <th style="width: 13%;">Router &amp; IP</th>
                        <th style="width: 13%;">Status</th>
                        <th style="width: 14%;">Expiry / due</th>
                        <th style="width: 10%;">Balance</th>
                        <th style="width: 18%;" class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        @php
                            $secret = $customer->pppUser;
                            $bill = $billings[$customer->customer_unique_id] ?? null;
                            $isSuspended = in_array($customer->status, ['disable', 'suspended', 'inactive']);
                            $isExpired = $bill && $bill->auto_disable_date && Carbon::parse($bill->auto_disable_date)->isPast();
                            $dueAmount = $bill ? (float) ($bill->total_due_amount ?: $bill->due_amount) : 0;
                            $rent = $bill ? (float) $bill->monthly_rent : (float) ($customer->package->price ?? 0);
                        @endphp
                        <tr>
                            {{-- Customer Name & Phone --}}
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold me-2.5" style="width: 34px; height: 34px; font-size: 12px; background: {{ $isSuspended ? '#fff1f2' : '#fff7ed' }}; color: {{ $isSuspended ? '#e11d48' : '#ea580c' }};">
                                        {{ strtoupper(substr($customer->customer_name ?: 'CU', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 160px;">{{ $customer->customer_name }}</div>
                                        <div class="small text-muted font-monospace" style="font-size: 0.75rem;">{{ $customer->mobile ?: $customer->customer_unique_id }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- Service Type & Package --}}
                            <td>
                                <div>
                                    @if($customer->service_type === 'hotspot')
                                        <x-ui.badge tone="info" class="mb-1">Hotspot</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="neutral" class="mb-1">PPPoE</x-ui.badge>
                                    @endif
                                    <div class="small fw-medium text-dark text-truncate" style="max-width: 140px;">
                                        {{ $customer->package->name ?? ($customer->package_name ?: 'Default') }}
                                    </div>
                                    <small class="text-muted">{{ $rent > 0 ? 'KES ' . number_format($rent, 0) : '—' }}</small>
                                </div>
                            </td>

                            {{-- Router & IP --}}
                            <td>
                                <div class="small">
                                    <div class="fw-medium text-dark text-truncate" style="max-width: 130px;">
                                        <i class="bi bi-router me-1 text-muted"></i>{{ $secret?->router_name ?: 'Main Gateway' }}
                                    </div>
                                    <div class="font-monospace text-muted" style="font-size: 0.75rem;">{{ $secret?->username ?: 'user_' . $customer->id }}</div>
                                    <div class="font-monospace text-secondary" style="font-size: 11px;">{{ $secret?->caller_id ?: ($secret?->ppp_remote_ip ?: 'DHCP') }}</div>
                                </div>
                            </td>

                            {{-- Status Badge --}}
                            <td>
                                @if($isSuspended)
                                    <x-ui.badge tone="danger"><i class="bi bi-dash-circle me-1"></i>Suspended</x-ui.badge>
                                @elseif($isExpired)
                                    <x-ui.badge tone="warning"><i class="bi bi-clock me-1"></i>Expired</x-ui.badge>
                                @else
                                    <x-ui.badge tone="success"><i class="bi bi-check-circle-fill me-1"></i>Active</x-ui.badge>
                                @endif
                            </td>

                            {{-- Expiry / Due Date --}}
                            <td>
                                @if($bill && $bill->auto_disable_date)
                                    @php $exp = Carbon::parse($bill->auto_disable_date); @endphp
                                    <div class="small">
                                        <div class="fw-medium text-dark">{{ $exp->format('d M Y') }}</div>
                                        <div class="small {{ $exp->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">
                                            {{ $exp->diffForHumans() }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">No expiry date</span>
                                @endif
                            </td>

                            {{-- Balance --}}
                            <td>
                                @if($dueAmount > 0)
                                    <x-ui.badge tone="danger" class="font-monospace">KES {{ number_format($dueAmount, 0) }}</x-ui.badge>
                                @else
                                    <span class="text-muted small font-monospace">KES 0.00</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    {{-- Remind Button (Sends SMS Reminder) --}}
                                    <button type="button" 
                                            wire:click="sendPaymentReminder({{ $customer->id }})" 
                                            class="btn btn-outline-secondary btn-sm px-2" 
                                            title="Send payment reminder SMS">
                                        <i class="bi bi-bell me-1"></i>Remind
                                    </button>

                                    {{-- Suspend / Unblock Toggle --}}
                                    @if($isSuspended)
                                        <button type="button" 
                                                wire:click="toggleSuspend({{ $customer->id }})" 
                                                class="btn btn-outline-success btn-sm px-2" 
                                                title="Unblock customer">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    @else
                                        <button type="button" 
                                                wire:click="toggleSuspend({{ $customer->id }})" 
                                                wire:confirm="Suspend internet access for {{ $customer->customer_name }}?" 
                                                class="btn btn-outline-warning btn-sm px-2" 
                                                title="Suspend customer">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    @endif

                                    {{-- Kick Session --}}
                                    <button type="button" 
                                            wire:click="kickSession({{ $customer->id }})" 
                                            class="btn btn-outline-secondary btn-sm px-2" 
                                            title="Disconnect active session">
                                        <i class="bi bi-box-arrow-right"></i>
                                    </button>

                                    {{-- Edit --}}
                                    <button type="button" 
                                            wire:click="openEditModal({{ $customer->id }})" 
                                            class="btn btn-outline-secondary btn-sm px-2" 
                                            title="Edit customer">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    {{-- Delete --}}
                                    <button type="button" 
                                            wire:click="deleteCustomer({{ $customer->id }})" 
                                            wire:confirm="Delete customer {{ $customer->customer_name }}?" 
                                            class="btn btn-outline-danger btn-sm px-2" 
                                            title="Delete customer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-0">
                                <x-ui.empty-state icon="bi-people" title="No customers found" description="Add customers or adjust your filter query.">
                                    <x-ui.button variant="primary" wire:click="openCreateModal">
                                        <i class="bi bi-plus-lg"></i> Add new customer
                                    </x-ui.button>
                                </x-ui.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="card-footer bg-transparent py-3 px-4 border-top">
            {{ $customers->links() }}
        </div>
    </x-ui.card>

    {{-- CREATE CUSTOMER MODAL --}}
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(2px); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2 text-orange-500"></i>New customer</h5>
                        <button type="button" class="btn-close" wire:click="closeCreateModal"></button>
                    </div>
                    <form wire:submit.prevent="storeCustomer">
                        <div class="modal-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Customer full name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="new_name" class="form-control @error('new_name') is-invalid @enderror" placeholder="e.g. John Kamau" required>
                                    @error('new_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label">Mobile phone (Kenyan) <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="new_phone" class="form-control @error('new_phone') is-invalid @enderror" placeholder="e.g. 0712345678 or 254712345678" required>
                                    @error('new_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Service type <span class="text-danger">*</span></label>
                                    <select wire:model.live="new_type" class="form-select @error('new_type') is-invalid @enderror">
                                        <option value="pppoe">PPPoE (Home/Office Fiber)</option>
                                        <option value="hotspot">Hotspot (Voucher/WiFi)</option>
                                    </select>
                                    @error('new_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label">Target router <span class="text-danger">*</span></label>
                                    <select wire:model="new_router_id" class="form-select @error('new_router_id') is-invalid @enderror" required>
                                        <option value="">Select router</option>
                                        @foreach($routers as $r)
                                            <option value="{{ $r->id }}">{{ $r->name ?: $r->router_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('new_router_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label d-flex justify-content-between align-items-center">
                                        <span>Internet package ({{ strtoupper($new_type) }}) <span class="text-danger">*</span></span>
                                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;">{{ count($createPackages ?? $packages) }} available</span>
                                    </label>
                                    <select wire:model="new_package_id" class="form-select @error('new_package_id') is-invalid @enderror" required>
                                        <option value="">Select {{ strtoupper($new_type) }} package</option>
                                        @foreach(($createPackages ?? $packages) as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} (KES {{ number_format($p->price, 0) }})</option>
                                        @endforeach
                                    </select>
                                    @error('new_package_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <small class="text-muted" style="font-size: 11px;">Auto-filtered for {{ strtoupper($new_type) }}</small>
                                        <a href="{{ route('admin.packages') }}" class="text-decoration-none small" style="font-size: 11px; color: #ea580c;">+ New package</a>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">PPPoE / hotspot username <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="new_username" class="form-control font-monospace @error('new_username') is-invalid @enderror" placeholder="e.g. kamau_fiber" required>
                                    @error('new_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label">Account password <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="new_password" class="form-control font-monospace @error('new_password') is-invalid @enderror" required>
                                    @error('new_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Initial validity period (days) <span class="text-danger">*</span></label>
                                <input type="number" wire:model="new_validity_days" class="form-control" min="1" max="365" required>
                                <div class="form-text small">Account will be due for renewal {{ $new_validity_days }} days from today.</div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <x-ui.button variant="secondary" wire:click="closeCreateModal">Cancel</x-ui.button>
                            <x-ui.button type="submit" variant="primary">Create customer</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- EDIT CUSTOMER MODAL --}}
    @if($showEditModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(2px); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-orange-500"></i>Edit customer</h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal"></button>
                    </div>
                    <form wire:submit.prevent="updateCustomer">
                        <div class="modal-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Full name</label>
                                    <input type="text" wire:model="edit_name" class="form-control" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Mobile phone</label>
                                    <input type="text" wire:model="edit_phone" class="form-control" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Service type</label>
                                    <select wire:model="edit_type" class="form-select">
                                        <option value="pppoe">PPPoE</option>
                                        <option value="hotspot">Hotspot</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Assigned router</label>
                                    <select wire:model="edit_router_id" class="form-select">
                                        <option value="">Select router</option>
                                        @foreach($routers as $r)
                                            <option value="{{ $r->id }}">{{ $r->name ?: $r->router_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label d-flex justify-content-between align-items-center">
                                        <span>Internet package ({{ strtoupper($edit_type) }})</span>
                                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;">{{ count($editPackages ?? $packages) }} available</span>
                                    </label>
                                    <select wire:model="edit_package_id" class="form-select">
                                        <option value="">Select package</option>
                                        @foreach(($editPackages ?? $packages) as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} (KES {{ number_format($p->price, 0) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" wire:model="edit_username" class="form-control font-monospace" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="text" wire:model="edit_password" class="form-control font-monospace" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Account status</label>
                                    <select wire:model="edit_status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended / blocked</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Expiry / renewal date</label>
                                    <input type="date" wire:model="edit_expiry_date" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <x-ui.button variant="secondary" wire:click="closeEditModal">Cancel</x-ui.button>
                            <x-ui.button type="submit" variant="primary">Save changes</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

