<div class="container-fluid py-4">
    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Customers</h3>
            <p class="text-muted small mb-0">Manage PPPoE and Hotspot subscribers, track online sessions, billing cycles, and send payment reminders.</p>
        </div>
        <div>
            <button type="button" wire:click="openCreateModal" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-person-plus-fill me-1"></i>New Customer
            </button>
        </div>
    </div>

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
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">Subscribers</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-primary" style="width: 36px; height: 36px;">
                            <i class="bi bi-people fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ $totalCustomers }}</h3>
                    <small class="text-muted">Registered Accounts</small>
                </div>
            </div>
        </div>

        {{-- Active / Online --}}
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">Active</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width: 36px; height: 36px;">
                            <i class="bi bi-check-circle fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-success mb-0">{{ $activeCustomers }}</h3>
                    <small class="text-muted">Operational Service</small>
                </div>
            </div>
        </div>

        {{-- Suspended / Due --}}
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">Suspended</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger" style="width: 36px; height: 36px;">
                            <i class="bi bi-slash-circle fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-danger mb-0">{{ $suspendedCustomers }}</h3>
                    <small class="text-muted">Service Blocked / Overdue</small>
                </div>
            </div>
        </div>

        {{-- Monthly Revenue --}}
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">PPPoE Revenue</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width: 36px; height: 36px;">
                            <i class="bi bi-currency-exchange fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">KES {{ number_format($monthlyRevenue, 0) }}</h3>
                    <small class="text-muted">Monthly Package Billings</small>
                </div>
            </div>
        </div>

        {{-- Outstanding Balance --}}
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">Unpaid Balance</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning" style="width: 36px; height: 36px;">
                            <i class="bi bi-wallet2 fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold {{ $outstandingBalance > 0 ? 'text-danger' : 'text-dark' }} mb-0">
                        KES {{ number_format($outstandingBalance, 0) }}
                    </h3>
                    <small class="text-muted">Total Dues Pending</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center justify-content-between">
                {{-- Search Box --}}
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control bg-light border-start-0" placeholder="Search by name, phone, account #, username...">
                    </div>
                </div>

                {{-- Router Filter --}}
                <div class="col-12 col-md-3">
                    <select wire:model.live="filterRouter" class="form-select bg-light">
                        <option value="all">All Routers</option>
                        @foreach($routers as $r)
                            <option value="{{ $r->id }}">{{ $r->name ?: $r->router_name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Chips --}}
                <div class="col-12 col-md-auto d-flex align-items-center gap-1">
                    <button type="button" wire:click="$set('filterType', 'all')" class="btn btn-sm {{ $filterType === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        All
                    </button>
                    <button type="button" wire:click="$set('filterType', 'pppoe')" class="btn btn-sm {{ $filterType === 'pppoe' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        PPPoE
                    </button>
                    <button type="button" wire:click="$set('filterType', 'hotspot')" class="btn btn-sm {{ $filterType === 'hotspot' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        Hotspot
                    </button>
                    <button type="button" wire:click="$set('filterType', 'active')" class="btn btn-sm {{ $filterType === 'active' ? 'btn-success text-white' : 'btn-outline-secondary' }}">
                        Active
                    </button>
                    <button type="button" wire:click="$set('filterType', 'suspended')" class="btn btn-sm {{ $filterType === 'suspended' ? 'btn-danger text-white' : 'btn-outline-secondary' }}">
                        Suspended
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Customers Table --}}
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th style="width: 20%;">Customer</th>
                        <th style="width: 12%;">Service / Plan</th>
                        <th style="width: 13%;">Router &amp; IP</th>
                        <th style="width: 13%;">Status</th>
                        <th style="width: 14%;">Expiry / Due</th>
                        <th style="width: 10%;">Balance</th>
                        <th style="width: 18%;" class="text-end pe-3">Actions</th>
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
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold me-2 {{ $isSuspended ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }}" style="width: 36px; height: 36px; font-size: 13px;">
                                        {{ strtoupper(substr($customer->customer_name ?: 'CU', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 160px;">{{ $customer->customer_name }}</div>
                                        <div class="small text-muted font-monospace">{{ $customer->mobile ?: $customer->customer_unique_id }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- Service Type & Package --}}
                            <td>
                                <div>
                                    @if($customer->service_type === 'hotspot')
                                        <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-25 px-2 py-1 mb-1">
                                            Hotspot
                                        </span>
                                    @else
                                        <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 px-2 py-1 mb-1">
                                            PPPoE
                                        </span>
                                    @endif
                                    <div class="small fw-semibold text-dark text-truncate" style="max-width: 140px;">
                                        {{ $customer->package->name ?? ($customer->package_name ?: 'Default') }}
                                    </div>
                                    <small class="text-muted">{{ $rent > 0 ? 'KES ' . number_format($rent, 0) : '—' }}</small>
                                </div>
                            </td>

                            {{-- Router & IP --}}
                            <td>
                                <div class="small">
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 130px;">
                                        <i class="bi bi-router me-1 text-muted"></i>{{ $secret?->router_name ?: 'Main Gateway' }}
                                    </div>
                                    <div class="font-monospace text-muted">{{ $secret?->username ?: 'user_' . $customer->id }}</div>
                                    <div class="font-monospace text-secondary" style="font-size: 11px;">{{ $secret?->caller_id ?: ($secret?->ppp_remote_ip ?: 'DHCP') }}</div>
                                </div>
                            </td>

                            {{-- Status Badge --}}
                            <td>
                                @if($isSuspended)
                                    <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25 py-1 px-2">
                                        <i class="bi bi-dash-circle me-1"></i>Suspended
                                    </span>
                                @elseif($isExpired)
                                    <span class="badge bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 py-1 px-2">
                                        <i class="bi bi-clock me-1"></i>Expired
                                    </span>
                                @else
                                    <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 py-1 px-2">
                                        <i class="bi bi-check-circle-fill me-1"></i>Active
                                    </span>
                                @endif
                            </td>

                            {{-- Expiry / Due Date --}}
                            <td>
                                @if($bill && $bill->auto_disable_date)
                                    @php $exp = Carbon::parse($bill->auto_disable_date); @endphp
                                    <div class="small">
                                        <div class="fw-semibold text-dark">{{ $exp->format('d M Y') }}</div>
                                        <div class="small {{ $exp->isPast() ? 'text-danger fw-bold' : 'text-muted' }}">
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
                                    <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25 py-1 px-2 font-monospace">
                                        KES {{ number_format($dueAmount, 0) }}
                                    </span>
                                @else
                                    <span class="text-muted small font-monospace">KES 0.00</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    {{-- Remind Button (Sends SMS Reminder) --}}
                                    <button type="button" 
                                            wire:click="sendPaymentReminder({{ $customer->id }})" 
                                            class="btn btn-outline-primary btn-sm px-2" 
                                            title="Send Payment Reminder SMS">
                                        <i class="bi bi-bell me-1"></i>Remind
                                    </button>

                                    {{-- Suspend / Unblock Toggle --}}
                                    @if($isSuspended)
                                        <button type="button" 
                                                wire:click="toggleSuspend({{ $customer->id }})" 
                                                class="btn btn-outline-success btn-sm px-2" 
                                                title="Unblock Customer">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    @else
                                        <button type="button" 
                                                wire:click="toggleSuspend({{ $customer->id }})" 
                                                wire:confirm="Suspend internet access for {{ $customer->customer_name }}?" 
                                                class="btn btn-outline-warning btn-sm px-2 text-dark" 
                                                title="Suspend Customer">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    @endif

                                    {{-- Kick Session --}}
                                    <button type="button" 
                                            wire:click="kickSession({{ $customer->id }})" 
                                            class="btn btn-outline-secondary btn-sm px-2" 
                                            title="Disconnect Active Session">
                                        <i class="bi bi-box-arrow-right"></i>
                                    </button>

                                    {{-- Edit --}}
                                    <button type="button" 
                                            wire:click="openEditModal({{ $customer->id }})" 
                                            class="btn btn-outline-info btn-sm px-2" 
                                            title="Edit Customer">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    {{-- Delete --}}
                                    <button type="button" 
                                            wire:click="deleteCustomer({{ $customer->id }})" 
                                            wire:confirm="Delete customer {{ $customer->customer_name }}?" 
                                            class="btn btn-outline-danger btn-sm px-2" 
                                            title="Delete Customer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                <h6 class="fw-bold text-dark">No customers found</h6>
                                <p class="small text-muted mb-3">Add customers or adjust your filter query.</p>
                                <button type="button" wire:click="openCreateModal" class="btn btn-primary btn-sm px-3">
                                    <i class="bi bi-plus-lg me-1"></i>Add New Customer
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="card-footer bg-white py-3 border-top">
            {{ $customers->links() }}
        </div>
    </div>

    {{-- CREATE CUSTOMER MODAL --}}
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.6); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus-fill me-2 text-primary"></i>New Customer</h5>
                        <button type="button" class="btn-close" wire:click="closeCreateModal"></button>
                    </div>
                    <form wire:submit.prevent="storeCustomer">
                        <div class="modal-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Customer Full Name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="new_name" class="form-control @error('new_name') is-invalid @enderror" placeholder="e.g. John Kamau" required>
                                    @error('new_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Mobile Phone (Kenyan) <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="new_phone" class="form-control @error('new_phone') is-invalid @enderror" placeholder="e.g. 0712345678 or 254712345678" required>
                                    @error('new_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold small">Service Type <span class="text-danger">*</span></label>
                                    <select wire:model.live="new_type" class="form-select @error('new_type') is-invalid @enderror">
                                        <option value="pppoe">🔌 PPPoE (Home/Office Fiber)</option>
                                        <option value="hotspot">📶 Hotspot (Voucher/WiFi)</option>
                                    </select>
                                    @error('new_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold small">Target Router <span class="text-danger">*</span></label>
                                    <select wire:model="new_router_id" class="form-select @error('new_router_id') is-invalid @enderror" required>
                                        <option value="">Select Router</option>
                                        @foreach($routers as $r)
                                            <option value="{{ $r->id }}">{{ $r->name ?: $r->router_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('new_router_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold small">Internet Package <span class="text-danger">*</span></label>
                                    <select wire:model="new_package_id" class="form-select @error('new_package_id') is-invalid @enderror" required>
                                        <option value="">Select Package</option>
                                        @foreach($packages as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} (KES {{ number_format($p->price, 0) }})</option>
                                        @endforeach
                                    </select>
                                    @error('new_package_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">PPPoE / Hotspot Username <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="new_username" class="form-control font-monospace @error('new_username') is-invalid @enderror" placeholder="e.g. kamau_fiber" required>
                                    @error('new_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Account Password <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="new_password" class="form-control font-monospace @error('new_password') is-invalid @enderror" required>
                                    @error('new_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Initial Validity Period (Days) <span class="text-danger">*</span></label>
                                <input type="number" wire:model="new_validity_days" class="form-control" min="1" max="365" required>
                                <div class="form-text small">Account will be due for renewal {{ $new_validity_days }} days from today.</div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-outline-secondary" wire:click="closeCreateModal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-semibold px-4">Create Customer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- EDIT CUSTOMER MODAL --}}
    @if($showEditModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.6); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Customer</h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal"></button>
                    </div>
                    <form wire:submit.prevent="updateCustomer">
                        <div class="modal-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Full Name</label>
                                    <input type="text" wire:model="edit_name" class="form-control" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Mobile Phone</label>
                                    <input type="text" wire:model="edit_phone" class="form-control" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold small">Service Type</label>
                                    <select wire:model="edit_type" class="form-select">
                                        <option value="pppoe">PPPoE</option>
                                        <option value="hotspot">Hotspot</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold small">Assigned Router</label>
                                    <select wire:model="edit_router_id" class="form-select">
                                        <option value="">Select Router</option>
                                        @foreach($routers as $r)
                                            <option value="{{ $r->id }}">{{ $r->name ?: $r->router_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold small">Internet Package</label>
                                    <select wire:model="edit_package_id" class="form-select">
                                        <option value="">Select Package</option>
                                        @foreach($packages as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} (KES {{ number_format($p->price, 0) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Username</label>
                                    <input type="text" wire:model="edit_username" class="form-control font-monospace" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Password</label>
                                    <input type="text" wire:model="edit_password" class="form-control font-monospace" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Account Status</label>
                                    <select wire:model="edit_status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended / Blocked</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Expiry / Renewal Date</label>
                                    <input type="date" wire:model="edit_expiry_date" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-outline-secondary" wire:click="closeEditModal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-semibold px-4">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

