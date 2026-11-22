<div class="ui-page-wrap">
    @php
        $titles = [
            'all'      => ['Prepaid Users', 'Unified real-time hub for Hotspot, Static, and PPPoE prepaid subscribers.'],
            'active'   => ['Active Users', 'Prepaid subscribers with active unexpired sessions and access.'],
            'expired'  => ['Expired Users', 'Expired subscribers removed from router sessions with disconnection reasons.'],
            'online'   => ['Online Users', 'Subscribers currently online via /ip/hotspot/active and /ppp/active polling.'],
            'offline'  => ['Offline Users', 'Active subscribers not currently passing traffic on the router.'],
            'activate' => ['Activate User', 'Manually activate a Prepaid Hotspot, PPPoE, or Static user onto a router.'],
            'roaming'  => ['Roaming Users', 'Subscribers who purchased on one router and roamed to another router in your network.'],
        ];
        [$pageTitle, $pageSub] = $titles[$section] ?? $titles['all'];
    @endphp

    {{-- Header & Action Buttons --}}
    <x-ui.page-header :title="$pageTitle" :subtitle="$pageSub">
        <x-slot:actions>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button"
                        wire:click="syncAllFromRouters"
                        wire:loading.attr="disabled"
                        wire:target="syncAllFromRouters"
                        class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                    <span wire:loading.remove wire:target="syncAllFromRouters"><i class="bi bi-arrow-repeat"></i> Sync all customers from router</span>
                    <span wire:loading wire:target="syncAllFromRouters"><span class="spinner-border spinner-border-sm"></span> Syncing routers...</span>
                </button>

                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-router me-1"></i>Sync by router
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                        @forelse($routers as $rtr)
                            <li>
                                <button type="button" class="dropdown-item" wire:click="syncFromRouter({{ $rtr->id }})">
                                    <i class="bi bi-hdd-network me-2 text-primary"></i>{{ $rtr->name }}
                                </button>
                            </li>
                        @empty
                            <li><span class="dropdown-item text-muted small">No routers configured</span></li>
                        @endforelse
                    </ul>
                </div>

                <button type="button" wire:click="exportCsv" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-download"></i> Export CSV
                </button>

                <button type="button" wire:click="openActivateModal" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-lg"></i> Activate User
                </button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Alerts --}}
    @if($statusMessage)
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>{{ $statusMessage }}</div>
            <button type="button" class="btn-close" wire:click="$set('statusMessage', null)"></button>
        </div>
    @endif
    @if($errorMessage)
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div>{{ $errorMessage }}</div>
            <button type="button" class="btn-close" wire:click="$set('errorMessage', null)"></button>
        </div>
    @endif

    {{-- Quick Section Pills & Roaming Setting Banner --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex flex-wrap gap-1.5">
            <a href="{{ route('admin.activation.prepaid-users') }}" wire:navigate class="btn btn-sm {{ $section === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                All <span class="badge bg-white text-dark ms-1">{{ $counts['all'] }}</span>
            </a>
            <a href="{{ route('admin.activation.active-users') }}" wire:navigate class="btn btn-sm {{ $section === 'active' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Active <span class="badge bg-success-subtle text-success ms-1">{{ $counts['active'] }}</span>
            </a>
            <a href="{{ route('admin.activation.online-users') }}" wire:navigate class="btn btn-sm {{ $section === 'online' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Online <span class="badge bg-success text-white ms-1">{{ $counts['online'] }}</span>
            </a>
            <a href="{{ route('admin.activation.offline-users') }}" wire:navigate class="btn btn-sm {{ $section === 'offline' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Offline <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $counts['offline'] }}</span>
            </a>
            <a href="{{ route('admin.activation.expired-users') }}" wire:navigate class="btn btn-sm {{ $section === 'expired' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Expired <span class="badge bg-danger-subtle text-danger ms-1">{{ $counts['expired'] }}</span>
            </a>
            <a href="{{ route('admin.activation.roaming-users') }}" wire:navigate class="btn btn-sm {{ $section === 'roaming' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Roaming <span class="badge bg-info-subtle text-info ms-1">{{ $counts['roaming'] }}</span>
            </a>
        </div>

        @if($section === 'roaming')
            <div class="d-flex align-items-center gap-2 bg-white border rounded-3 px-3 py-1.5 small">
                <span class="fw-semibold text-dark">Allow Multi-Router Roaming (Same Tenant):</span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" wire:click="toggleAllowRoaming" {{ $allowRoaming ? 'checked' : '' }} id="roamingToggle">
                    <label class="form-check-label fw-semibold {{ $allowRoaming ? 'text-success' : 'text-danger' }}" for="roamingToggle">
                        {{ $allowRoaming ? 'Enabled' : 'Disabled' }}
                    </label>
                </div>
            </div>
        @endif
    </div>

    {{-- Service Type Tabs + Search + Router Filter + Page Size --}}
    <x-ui.card class="mb-3 p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-xl-auto">
                <ul class="nav nav-pills gap-1">
                    <li class="nav-item">
                        <button type="button" wire:click="setTypeTab('all')" class="nav-link py-1.5 px-3 {{ $typeTab === 'all' ? 'active' : 'text-dark bg-light' }}">All Users</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" wire:click="setTypeTab('hotspot')" class="nav-link py-1.5 px-3 {{ $typeTab === 'hotspot' ? 'active' : 'text-dark bg-light' }}">Hotspot Users</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" wire:click="setTypeTab('static')" class="nav-link py-1.5 px-3 {{ $typeTab === 'static' ? 'active' : 'text-dark bg-light' }}">Static Users</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" wire:click="setTypeTab('pppoe')" class="nav-link py-1.5 px-3 {{ $typeTab === 'pppoe' ? 'active' : 'text-dark bg-light' }}">PPPoE Users</button>
                    </li>
                </ul>
            </div>

            <div class="col-12 col-xl-7">
                <div class="row g-2 align-items-center justify-content-end">
                    <div class="col-12 col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" wire:model.live.debounce.300ms="search" class="form-control border-start-0" placeholder="Search username, phone, MAC...">
                            @if(!empty($search))
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('search', '')"><i class="bi bi-x"></i></button>
                            @endif
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <select wire:model.live="routerFilter" class="form-select form-select-sm">
                            <option value="all">All Routers</option>
                            @foreach($routers as $rtr)
                                <option value="{{ $rtr->id }}">{{ $rtr->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <select wire:model.live="perPage" class="form-select form-select-sm">
                            <option value="15">15 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bulk Actions Toolbar --}}
        @if(count($selectedIds) > 0)
            <div class="mt-3 pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="small fw-semibold text-primary">
                    <i class="bi bi-check2-square me-1"></i>{{ count($selectedIds) }} user(s) selected
                </span>
                <div class="d-flex flex-wrap gap-1.5">
                    <button type="button" wire:click="openExtendModal" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-clock-history me-1"></i>Extend
                    </button>
                    <button type="button" wire:click="bulkEnable" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-check-circle me-1"></i>Enable
                    </button>
                    <button type="button" wire:click="bulkDisable" wire:confirm="Disable selected users and disconnect their active router sessions?" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-slash-circle me-1"></i>Disable
                    </button>
                    <button type="button" wire:click="openBulkSmsModal" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-chat-dots me-1"></i>Send SMS
                    </button>
                    <button type="button" wire:click="bulkDelete" wire:confirm="Permanently delete selected users from router and database?" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        @endif
    </x-ui.card>

    {{-- Main Table --}}
    <x-ui.card :padding="false" class="overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" style="white-space: nowrap;">
                <thead class="table-light">
                    <tr class="text-uppercase text-muted" style="font-size: 11px;">
                        <th style="width: 34px;" class="ps-3">
                            <input type="checkbox" class="form-check-input" wire:model.live="selectAll">
                        </th>
                        <th>Username</th>
                        <th>Plan name</th>
                        <th>Type</th>
                        <th>Created on</th>
                        <th>Expires on</th>
                        <th>Method</th>
                        <th>Router</th>
                        <th>Status</th>
                        <th>Last seen</th>
                        <th>Daily data used</th>
                        <th>Was connected?</th>
                        <th>Reconnection SMS sent?</th>
                        <th>Disconnection reason</th>
                        <th>FUP</th>
                        <th class="text-end pe-3">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $row)
                        @php
                            $effStatus = $row->effective_status;
                            $planName = $row->package?->name ?? ($row->plan_name ?: 'No package');
                            $statusClass = match ($effStatus) {
                                'online'   => 'bg-success text-white',
                                'offline'  => 'bg-secondary-subtle text-dark border',
                                'expired'  => 'bg-danger-subtle text-danger border border-danger-subtle',
                                'disabled' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                default    => 'bg-light text-dark',
                            };
                        @endphp
                        <tr wire:key="prepaid-row-{{ $row->id }}">
                            <td class="ps-3">
                                <input type="checkbox" class="form-check-input" value="{{ $row->id }}" wire:model.live="selectedIds">
                            </td>
                            <td>
                                <div class="fw-bold font-monospace text-dark">{{ $row->username }}</div>
                                <div class="text-muted" style="font-size: 11px;">
                                    {{ $row->phone ?: ($row->mac_address ?: '—') }}
                                </div>
                                @if($row->mac_address)
                                    <div class="font-monospace text-secondary" style="font-size: 10px;">{{ $row->mac_address }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-medium text-dark">{{ $planName }}</div>
                                @if($row->package)
                                    <div class="text-muted" style="font-size: 11px;">KES {{ number_format((float) $row->package->price, 0) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($row->type === 'hotspot')
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-wifi me-1"></i>Hotspot</span>
                                @elseif($row->type === 'static')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-hdd me-1"></i>Static</span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-ethernet me-1"></i>PPPoE</span>
                                @endif
                            </td>
                            <td>
                                @php $cAt = $row->started_at ?? $row->created_at; @endphp
                                <div>{{ $cAt ? $cAt->format('d M Y') : '—' }}</div>
                                <div class="text-muted" style="font-size: 11px;">{{ $cAt ? $cAt->format('H:i') : '' }}</div>
                            </td>
                            <td>
                                @if($row->type === 'hotspot' && $row->total_duration_seconds > 0)
                                    <div>
                                        <span class="badge {{ $row->remaining_seconds > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }}">
                                            <i class="bi bi-clock me-1"></i>Rem: {{ $row->formatted_remaining_duration }}
                                        </span>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size: 11px;">
                                        Online: {{ $row->formatted_used_duration }} / {{ round($row->total_duration_seconds / 60) }}m
                                    </div>
                                    @if($row->validity_expires_at)
                                        <div class="text-secondary" style="font-size: 10px;">
                                            Valid to: {{ $row->validity_expires_at->format('d M Y') }}
                                        </div>
                                    @endif
                                @elseif($row->expires_at)
                                    <div class="{{ $row->expires_at->isPast() ? 'text-danger fw-semibold' : 'text-dark' }}">
                                        {{ $row->expires_at->format('d M Y H:i') }}
                                    </div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $row->expires_at->diffForHumans() }}</div>
                                @else
                                    <span class="text-muted">Unlimited</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $row->method ?: 'Manual' }}</span>
                            </td>
                            <td>
                                <div class="fw-medium text-dark">
                                    <i class="bi bi-router me-1 text-muted"></i>{{ $row->router?->name ?? ($row->router_name ?: '—') }}
                                </div>
                                @if($row->is_roaming && ($row->roamingRouter?->name || $row->roaming_router_name))
                                    <span class="badge bg-info-subtle text-info border border-info-subtle mt-1" style="font-size: 10px;">
                                        <i class="bi bi-arrow-left-right me-1"></i>Roaming: {{ $row->roamingRouter?->name ?? $row->roaming_router_name }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $statusClass }}">
                                    @if($effStatus === 'online') <i class="bi bi-broadcast me-1"></i> @endif
                                    {{ ucfirst($effStatus) }}
                                </span>
                            </td>
                            <td>
                                <div>{{ $row->last_seen_at ? $row->last_seen_at->diffForHumans() : 'Never' }}</div>
                                @if($row->type === 'hotspot' && $row->total_duration_seconds > 0)
                                    <div class="text-muted" style="font-size: 10px;">
                                        <i class="bi bi-stopwatch me-1"></i>Uptime: {{ $row->formatted_used_duration }}
                                    </div>
                                @endif
                            </td>
                            <td class="font-monospace fw-medium">
                                {{ $row->formatted_daily_data }}
                            </td>
                            <td>
                                @if($row->was_connected)
                                    <span class="badge bg-success-subtle text-success">Yes</span>
                                @else
                                    <span class="badge bg-light text-muted border">No</span>
                                @endif
                            </td>
                            <td>
                                @if($row->reconnection_sms_sent)
                                    <span class="badge bg-info-subtle text-info">Yes</span>
                                @else
                                    <span class="badge bg-light text-muted border">No</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($row->disconnection_reason))
                                    <div class="d-flex flex-column align-items-start gap-1">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            {{ $row->disconnection_reason }}
                                        </span>
                                        <button type="button"
                                                wire:click="rectifyUser({{ $row->id }})"
                                                class="btn btn-link btn-sm p-0 text-decoration-none text-orange-600 fw-semibold"
                                                style="font-size: 11px;">
                                            <i class="bi bi-wrench-adjustable me-1"></i>Click to rectify
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" wire:click="toggleFup({{ $row->id }})" class="btn btn-sm py-0 px-2 {{ $row->fup_enabled ? 'btn-outline-warning' : 'btn-outline-secondary' }}" style="font-size: 11px;">
                                    {{ $row->fup_enabled ? 'Enabled' : 'Disabled' }}
                                </button>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" wire:click="openEditModal({{ $row->id }})" class="btn btn-outline-secondary btn-sm px-2" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" wire:click="openExtendModal({{ $row->id }})" class="btn btn-outline-primary btn-sm px-2" title="Extend validity">
                                        <i class="bi bi-clock-history"></i>
                                    </button>
                                    @if($effStatus === 'disabled' || $effStatus === 'expired')
                                        <button type="button" wire:click="enableUser({{ $row->id }})" class="btn btn-outline-success btn-sm px-2" title="Enable">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    @else
                                        <button type="button" wire:click="disableUser({{ $row->id }})" wire:confirm="Disable '{{ $row->username }}' and kick active router session?" class="btn btn-outline-warning btn-sm px-2" title="Disable">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="deleteUser({{ $row->id }})" wire:confirm="Delete '{{ $row->username }}' from router and DB?" class="btn btn-outline-danger btn-sm px-2" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16" class="p-0">
                                <x-ui.empty-state icon="bi-lightning-charge" title="No prepaid users in this view" description="Click Sync all customers from router or Activate User to add a subscriber.">
                                    <button type="button" wire:click="openActivateModal" class="btn btn-primary btn-sm mt-2">
                                        <i class="bi bi-plus-lg me-1"></i>Activate User
                                    </button>
                                </x-ui.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="card-footer bg-white py-2 px-3">
                {{ $users->links() }}
            </div>
        @endif
    </x-ui.card>

    {{-- Modal: Manual Activate User --}}
    @if($showActivateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,0.5); z-index: 1055; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Activate Prepaid User</h5>
                        <button type="button" class="btn-close" wire:click="$set('showActivateModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveManualActivation">
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Service Type</label>
                                    <select wire:model="act_type" class="form-select">
                                        <option value="hotspot">Hotspot</option>
                                        <option value="pppoe">PPPoE</option>
                                        <option value="static">Static</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Username / Voucher</label>
                                    <input type="text" wire:model="act_username" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Password</label>
                                    <input type="text" wire:model="act_password" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Package / Plan</label>
                                    <select wire:model.live="act_package_id" class="form-select">
                                        <option value="">— No Package (Custom Duration) —</option>
                                        @foreach($packages as $pkg)
                                            <option value="{{ $pkg->id }}">{{ $pkg->package }} ({{ strtoupper($pkg->type) }} - KES {{ number_format($pkg->price, 0) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Target Router</label>
                                    <select wire:model="act_router_id" class="form-select" required>
                                        @foreach($routers as $rtr)
                                            <option value="{{ $rtr->id }}">{{ $rtr->name }} ({{ $rtr->vpn_ip ?: $rtr->ip }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" wire:model="act_phone" class="form-control" placeholder="07XXXXXXXX">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">MAC Address (Optional)</label>
                                    <input type="text" wire:model="act_mac" class="form-control" placeholder="AA:BB:CC:DD:EE:FF">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Payment Method</label>
                                    <input type="text" wire:model="act_method" class="form-control" placeholder="Manual Cash">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Duration</label>
                                    <div class="input-group">
                                        <input type="number" min="1" wire:model="act_duration_value" class="form-control" required>
                                        <select wire:model="act_duration_unit" class="form-select">
                                            <option value="minutes">Minutes</option>
                                            <option value="hours">Hours</option>
                                            <option value="days">Days</option>
                                            <option value="months">Months</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex align-items-end gap-3">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" wire:model.live="act_fup_enabled" id="actFupSwitch">
                                        <label class="form-check-label" for="actFupSwitch">Enable FUP Cap</label>
                                    </div>
                                    @if($act_fup_enabled)
                                        <div class="flex-grow-1">
                                            <input type="number" wire:model="act_fup_limit_mb" class="form-control form-control-sm" placeholder="FUP Limit (MB)">
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showActivateModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Activate &amp; Push to Router</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Extend Validity --}}
    @if($showExtendModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,0.5); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Extend User Validity</h5>
                        <button type="button" class="btn-close" wire:click="$set('showExtendModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveExtend">
                        <div class="modal-body p-4">
                            <label class="form-label">Add Time / Days</label>
                            <div class="input-group">
                                <input type="number" min="1" wire:model="extend_value" class="form-control" required>
                                <select wire:model="extend_unit" class="form-select">
                                    <option value="minutes">Minutes</option>
                                    <option value="hours">Hours</option>
                                    <option value="days">Days</option>
                                    <option value="months">Months (30 Days)</option>
                                </select>
                            </div>
                            <div class="form-text mt-2">Updates database expiry, clears disconnection reason, and pushes updated limits onto the MikroTik router.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showExtendModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Apply Extension</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Edit User --}}
    @if($showEditModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,0.5); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Prepaid User ({{ $edit_username }})</h5>
                        <button type="button" class="btn-close" wire:click="$set('showEditModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveEditUser">
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" wire:model="edit_phone" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">MAC Address</label>
                                    <input type="text" wire:model="edit_mac" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Package / Plan</label>
                                    <select wire:model="edit_package_id" class="form-select">
                                        <option value="">— No Package —</option>
                                        @foreach($packages as $pkg)
                                            <option value="{{ $pkg->id }}">{{ $pkg->package }} ({{ strtoupper($pkg->type) }} - KES {{ number_format($pkg->price, 0) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Router</label>
                                    <select wire:model="edit_router_id" class="form-select">
                                        @foreach($routers as $rtr)
                                            <option value="{{ $rtr->id }}">{{ $rtr->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expires At</label>
                                    <input type="datetime-local" wire:model="edit_expires_at" class="form-control">
                                </div>
                                <div class="col-md-6 d-flex align-items-end gap-3">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" wire:model.live="edit_fup_enabled" id="editFupSwitch">
                                        <label class="form-check-label" for="editFupSwitch">FUP Enabled</label>
                                    </div>
                                    @if($edit_fup_enabled)
                                        <input type="number" wire:model="edit_fup_limit_mb" class="form-control form-control-sm" placeholder="MB Cap">
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showEditModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Bulk SMS --}}
    @if($showSmsModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,0.5); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-chat-dots me-2 text-primary"></i>Send Reconnection SMS</h5>
                        <button type="button" class="btn-close" wire:click="$set('showSmsModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="sendBulkSms">
                        <div class="modal-body p-4">
                            <label class="form-label">SMS Content ({{ count($selectedIds) }} selected)</label>
                            <textarea wire:model="smsMessage" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showSmsModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Send SMS Now</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

