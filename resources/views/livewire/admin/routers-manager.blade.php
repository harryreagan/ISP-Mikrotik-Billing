<div class="container-fluid py-4" wire:poll.15s="$refresh">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Routers</h3>
            <p class="text-muted small mb-0">Monitor online status, live CPU/RAM telemetry, connected subscribers, and manage router fleet.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.add-router') }}" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i>Add Router
            </a>
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

    {{-- 4 Clean Analytics Summary Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Routers --}}
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">Total Fleet</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-primary" style="width: 36px; height: 36px;">
                            <i class="bi bi-hdd-network fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ $totalRouters }}</h3>
                    <small class="text-muted">Configured Routers</small>
                </div>
            </div>
        </div>

        {{-- Online Routers --}}
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">Online Status</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width: 36px; height: 36px;">
                            <i class="bi bi-activity fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h3 class="fw-bold text-success mb-0">{{ $onlineRouters }}</h3>
                        <span class="text-muted small">/ {{ $totalRouters }} Active</span>
                    </div>
                    <small class="text-muted">Live Heartbeat Verified</small>
                </div>
            </div>
        </div>

        {{-- Connected Subscribers --}}
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">Subscribers</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info" style="width: 36px; height: 36px;">
                            <i class="bi bi-people fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ $totalClients }}</h3>
                    <small class="text-muted">Active Customer Sessions</small>
                </div>
            </div>
        </div>

        {{-- System Health / CPU --}}
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small text-uppercase fw-semibold">System Health</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width: 36px; height: 36px;">
                            <i class="bi bi-cpu fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h3 class="fw-bold {{ $avgCpu > 80 ? 'text-danger' : 'text-primary' }} mb-0">{{ $avgCpu }}%</h3>
                        <span class="badge {{ $avgCpu > 80 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }} small">
                            {{ $avgCpu > 80 ? 'Heavy Load' : 'Healthy' }}
                        </span>
                    </div>
                    <small class="text-muted">Avg Online CPU Load</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center justify-content-between">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control bg-light border-start-0" placeholder="Search routers by name, location, or IP...">
                    </div>
                </div>
                <div class="col-12 col-md-auto d-flex align-items-center gap-2">
                    <div class="btn-group" role="group">
                        <button type="button" wire:click="$set('filterStatus', 'all')" class="btn btn-sm {{ $filterStatus === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            All ({{ $totalRouters }})
                        </button>
                        <button type="button" wire:click="$set('filterStatus', 'online')" class="btn btn-sm {{ $filterStatus === 'online' ? 'btn-success text-white' : 'btn-outline-secondary' }}">
                            Online ({{ $onlineRouters }})
                        </button>
                        <button type="button" wire:click="$set('filterStatus', 'offline')" class="btn btn-sm {{ $filterStatus === 'offline' ? 'btn-secondary text-white' : 'btn-outline-secondary' }}">
                            Offline ({{ $totalRouters - $onlineRouters }})
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Routers Cards Grid --}}
    <div class="row g-3">
        @forelse($routers as $router)
            @php
                $isOnline = $router->status === 'online' && $router->last_seen_at && $router->last_seen_at->gt(now()->subMinutes(5));
                $cpu = (int) ($router->cpu_load ?? 0);
                $ram = (int) ($router->memory_load ?? 0);
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div class="overflow-hidden me-2">
                            <h6 class="fw-bold text-dark text-truncate mb-0">{{ $router->name ?: $router->router_name }}</h6>
                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $router->location ?: 'Location not set' }}</small>
                        </div>
                        <div>
                            @if($isOnline)
                                <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i>Online
                                </span>
                            @elseif($router->status === 'rebooting')
                                <span class="badge bg-warning bg-opacity-25 text-warning px-2 py-1">
                                    <i class="bi bi-arrow-repeat me-1"></i>Rebooting
                                </span>
                            @else
                                <span class="badge bg-light text-muted border px-2 py-1">
                                    <i class="bi bi-dash-circle me-1"></i>Offline
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="card-body p-3">
                        {{-- Connection & IP Details --}}
                        <div class="bg-light rounded p-2 mb-3 small">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Cloud Tunnel IP:</span>
                                <span class="font-monospace fw-bold text-dark">{{ $router->vpn_ip ?: '10.10.0.x' }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Direct / Public IP:</span>
                                <span class="font-monospace text-muted">{{ $router->ip ?: ($router->ip_address ?: '—') }}</span>
                            </div>
                        </div>

                        {{-- Live Hardware Telemetry (CPU & RAM) --}}
                        <div class="row g-2 mb-3">
                            {{-- CPU --}}
                            <div class="col-6">
                                <div class="border rounded p-2 text-center h-100 bg-white">
                                    <div class="d-flex align-items-center justify-content-between small text-muted mb-1">
                                        <span class="fw-semibold">CPU</span>
                                        <span class="fw-bold {{ $cpu > 75 ? 'text-danger' : ($cpu > 40 ? 'text-warning' : 'text-success') }}">{{ $cpu }}%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar {{ $cpu > 75 ? 'bg-danger' : ($cpu > 40 ? 'bg-warning' : 'bg-success') }}" style="width: {{ max($cpu, 5) }}%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 11px;">
                                        <span>Load</span>
                                        <span class="font-monospace fw-semibold">{{ $cpu }}%</span>
                                    </div>
                                </div>
                            </div>
                            {{-- RAM --}}
                            <div class="col-6">
                                <div class="border rounded p-2 text-center h-100 bg-white">
                                    <div class="d-flex align-items-center justify-content-between small text-muted mb-1">
                                        <span class="fw-semibold">RAM</span>
                                        <span class="fw-bold {{ $ram > 85 ? 'text-danger' : ($ram > 70 ? 'text-warning' : 'text-primary') }}">{{ $ram }}%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar {{ $ram > 85 ? 'bg-danger' : ($ram > 70 ? 'bg-warning' : 'bg-primary') }}" style="width: {{ max($ram, 5) }}%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 11px;">
                                        @if(!empty($router->total_memory) && $router->total_memory > 0)
                                            <span class="font-monospace">{{ round(($router->total_memory - ($router->free_memory ?? 0)) / 1048576, 1) }}M</span>
                                            <span class="text-muted">/ {{ round($router->total_memory / 1048576, 0) }}M</span>
                                        @else
                                            <span>Used</span>
                                            <span class="font-monospace fw-semibold">{{ $ram }}%</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Telemetry details table --}}
                        <div class="space-y-1 small text-muted mb-3">
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                <span>Router Model:</span>
                                <span class="text-dark fw-semibold">
                                    {{ $router->board ?: ($router->model ?: 'MikroTik RouterOS') }}
                                    @if(!empty($router->model) && $router->model !== $router->board)
                                        <span class="text-muted fw-normal">({{ $router->model }})</span>
                                    @endif
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                <span>Serial Number:</span>
                                <span class="badge bg-light text-dark font-monospace border fw-semibold">
                                    {{ $router->serial_number ?: '—' }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                <span>RouterOS:</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary font-monospace">
                                    v{{ $router->version ?: '7.x' }}
                                    @if(!empty($router->architecture))
                                        <span class="text-secondary fw-normal">({{ $router->architecture }})</span>
                                    @endif
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                <span>Uptime:</span>
                                <span class="text-dark font-monospace fw-semibold">{{ $router->uptime ?: '—' }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                <span>Connected:</span>
                                <div>
                                    <span class="badge {{ ($router->port_roles['active_total'] ?? 0) > 0 ? 'bg-success text-white' : 'bg-light text-dark border' }} font-monospace px-2 py-1">
                                        <i class="bi bi-broadcast me-1 {{ ($router->port_roles['active_total'] ?? 0) > 0 ? 'text-white' : 'text-success' }}"></i><strong>{{ $router->port_roles['active_total'] ?? 0 }}</strong> Active
                                    </span>
                                    <span class="text-muted small ms-1" style="font-size: 11px;">
                                        ({{ $router->port_roles['active_hs'] ?? 0 }} HS, {{ $router->port_roles['active_ppp'] ?? 0 }} PPPoE)
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                <span>Subscribers:</span>
                                <a href="{{ route('admin.customers') }}" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none">
                                    <i class="bi bi-people me-1"></i>{{ $router->subscribers_count ?? 0 }} Registered
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span>Last Seen:</span>
                                <span class="text-dark">{{ $router->last_seen_at ? $router->last_seen_at->diffForHumans() : 'Never' }}</span>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top gap-1 flex-wrap">
                            <button type="button" 
                                    wire:click="pollRouterTelemetry({{ $router->id }})" 
                                    wire:loading.attr="disabled"
                                    class="btn btn-outline-secondary btn-sm px-2" 
                                    title="Check Live Telemetry">
                                <span wire:loading.remove wire:target="pollRouterTelemetry({{ $router->id }})">
                                    <i class="bi bi-arrow-repeat me-1"></i>Sync
                                </span>
                                <span wire:loading wire:target="pollRouterTelemetry({{ $router->id }})">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Syncing...
                                </span>
                            </button>

                            <button type="button" 
                                    wire:click="openPortsModal({{ $router->id }})" 
                                    class="btn btn-outline-info btn-sm px-2 text-dark" 
                                    title="Inspect & Configure Ports">
                                <i class="bi bi-ethernet me-1 text-info"></i>Ports
                            </button>

                            <button type="button" 
                                    wire:click="rebootRouter({{ $router->id }})" 
                                    wire:confirm="Are you sure you want to reboot {{ $router->name }}? Active customer sessions will briefly drop."
                                    class="btn btn-outline-warning btn-sm px-2 text-dark" 
                                    title="Reboot Router">
                                <i class="bi bi-power me-1"></i>Reboot
                            </button>

                            <button type="button" 
                                    wire:click="editRouter({{ $router->id }})" 
                                    class="btn btn-outline-primary btn-sm px-2" 
                                    title="Edit Router">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </button>

                            <button type="button" 
                                    wire:click="deleteRouter({{ $router->id }})" 
                                    wire:confirm="Are you sure you want to remove {{ $router->name }} from system?"
                                    class="btn btn-outline-danger btn-sm px-2" 
                                    title="Delete Router">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 py-5 text-center text-muted">
                <div class="card border-0 shadow-sm p-5">
                    <i class="bi bi-router fs-1 d-block mb-3 text-secondary"></i>
                    <h5 class="fw-bold text-dark">No Routers Found</h5>
                    <p class="small text-muted mb-4">Add your MikroTik router using the 4-step wizard to monitor health, subscribers, and automate billing.</p>
                    <div>
                        <a href="{{ route('admin.add-router') }}" class="btn btn-primary px-4 py-2">
                            <i class="bi bi-plus-lg me-1"></i>Add First Router
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $routers->links() }}
    </div>

    {{-- EDIT ROUTER MODAL --}}
    @if($showEditModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.6); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Router</h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal"></button>
                    </div>
                    <form wire:submit.prevent="updateRouter">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Router Name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="edit_name" class="form-control @error('edit_name') is-invalid @enderror" required>
                                @error('edit_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Location / Branch</label>
                                <input type="text" wire:model="edit_location" class="form-control @error('edit_location') is-invalid @enderror" placeholder="e.g. Westlands Mall or Tower 3">
                                @error('edit_location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-8">
                                    <label class="form-label fw-bold small">Management IP</label>
                                    <input type="text" wire:model="edit_ip" class="form-control @error('edit_ip') is-invalid @enderror" placeholder="e.g. 10.10.0.50">
                                    @error('edit_ip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-bold small">API Port</label>
                                    <input type="number" wire:model="edit_api_port" class="form-control @error('edit_api_port') is-invalid @enderror" required>
                                    @error('edit_api_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">API Username</label>
                                    <input type="text" wire:model="edit_username" class="form-control @error('edit_username') is-invalid @enderror" required>
                                    @error('edit_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Password (Optional)</label>
                                    <input type="password" wire:model="edit_password" class="form-control" placeholder="Leave blank to keep">
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

    {{-- PORTS CONFIGURATION MODAL --}}
    @if($showPortsModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.65); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light">
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0">
                                <i class="bi bi-ethernet me-2 text-info"></i>Interface Ports &amp; Service Roles — {{ $portsRouterName }}
                            </h5>
                            <small class="text-muted">{{ $portsRouterModel }} &bull; WireGuard Tunnel: <span class="font-monospace text-dark">{{ $portsRouterIp }}</span></small>
                        </div>
                        <button type="button" class="btn-close" wire:click="closePortsModal"></button>
                    </div>

                    <form wire:submit.prevent="applyPortConfiguration">
                        <div class="modal-body p-4">
                            {{-- Visual Ethernet Port Rack (Faceplate) --}}
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label fw-bold small text-uppercase text-muted mb-0">Physical Interface Link Status</label>
                                    <div class="d-flex align-items-center gap-3 small text-muted">
                                        <span><span class="d-inline-block rounded-circle bg-success me-1" style="width: 8px; height: 8px;"></span>Link Up (Active)</span>
                                        <span><span class="d-inline-block rounded-circle bg-secondary me-1" style="width: 8px; height: 8px;"></span>Unplugged (Down)</span>
                                    </div>
                                </div>

                                <div class="bg-dark rounded p-3 shadow-inner">
                                    <div class="row g-2">
                                        @foreach($portInterfaces as $iface)
                                            @php
                                                $iName = $iface['name'];
                                                $isWan = ($iName === $portRoleWan);
                                                $isHs  = ($iName === $portRoleHotspot);
                                                $isPpp = ($iName === $portRolePppoe);
                                                $isLinkUp = !empty($iface['running']);
                                            @endphp
                                            <div class="col-6 col-md-3">
                                                <div class="rounded border p-2 text-center {{ $isLinkUp ? 'bg-black border-success' : 'bg-black bg-opacity-50 border-secondary' }}" style="border-width: 2px !important;">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="badge {{ $isLinkUp ? 'bg-success' : 'bg-secondary' }}" style="font-size: 9px;">
                                                            {{ $isLinkUp ? 'LINK UP' : 'DOWN' }}
                                                        </span>
                                                        <span class="text-muted font-monospace" style="font-size: 10px;">{{ strtoupper($iface['type'] ?? 'ETH') }}</span>
                                                    </div>

                                                    {{-- RJ45 Icon / Port Name --}}
                                                    <div class="my-1">
                                                        <i class="bi bi-ethernet fs-4 {{ $isLinkUp ? 'text-success' : 'text-secondary' }}"></i>
                                                        <div class="fw-bold font-monospace text-light small">{{ $iName }}</div>
                                                    </div>

                                                    {{-- Assigned Role Badge --}}
                                                    <div class="mt-1">
                                                        @if($isWan)
                                                            <span class="badge bg-primary w-100" style="font-size: 10px;">WAN / Uplink</span>
                                                        @elseif($isHs)
                                                            <span class="badge bg-warning text-dark w-100" style="font-size: 10px;">HOTSPOT</span>
                                                        @elseif($isPpp)
                                                            <span class="badge bg-info text-white w-100" style="font-size: 10px;">PPPoE</span>
                                                        @else
                                                            <span class="badge bg-secondary text-white w-100" style="font-size: 10px;">LAN / Spare</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Reconfiguration Form --}}
                            <div class="border rounded p-3 bg-light">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-sliders me-2 text-primary"></i>Reconfigure Port Roles &amp; Subnets</h6>

                                <div class="row g-3">
                                    {{-- WAN Port --}}
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold small text-primary">
                                            <i class="bi bi-globe me-1"></i>WAN (Internet Uplink) Port <span class="text-danger">*</span>
                                        </label>
                                        <select wire:model="portRoleWan" class="form-select bg-white" required>
                                            @foreach($portInterfaces as $iface)
                                                <option value="{{ $iface['name'] }}">
                                                    {{ $iface['name'] }} {{ $iface['running'] ? '(Active Link)' : '(Unplugged)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text small">Obtains upstream internet via DHCP client.</div>
                                    </div>

                                    {{-- Hotspot Port --}}
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold small text-warning">
                                            <i class="bi bi-wifi me-1"></i>Hotspot Port <span class="text-danger">*</span>
                                        </label>
                                        <select wire:model="portRoleHotspot" class="form-select bg-white" required>
                                            @foreach($portInterfaces as $iface)
                                                <option value="{{ $iface['name'] }}">
                                                    {{ $iface['name'] }} {{ $iface['running'] ? '(Active Link)' : '(Unplugged)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text small">Serves Captive Portal &amp; customer vouchers.</div>
                                    </div>

                                    {{-- PPPoE Port --}}
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-bold small text-info">
                                            <i class="bi bi-router me-1"></i>PPPoE Server Port <span class="text-danger">*</span>
                                        </label>
                                        <select wire:model="portRolePppoe" class="form-select bg-white" required>
                                            @foreach($portInterfaces as $iface)
                                                <option value="{{ $iface['name'] }}">
                                                    {{ $iface['name'] }} {{ $iface['running'] ? '(Active Link)' : '(Unplugged)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text small">Authenticates PPPoE client subscriber routers.</div>
                                    </div>

                                    {{-- Hotspot Subnet --}}
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold small">Hotspot Subnet</label>
                                        <input type="text" wire:model="portHotspotSubnet" class="form-control font-monospace bg-white" placeholder="10.5.50.0/24" required>
                                        <div class="form-text small">Router gateway will be assigned <code>10.5.50.1</code> with DHCP pool.</div>
                                    </div>

                                    {{-- PPPoE Pool --}}
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold small">PPPoE Pool Subnet</label>
                                        <input type="text" wire:model="portPppoePool" class="form-control font-monospace bg-white" placeholder="10.0.0.0/24" required>
                                        <div class="form-text small">IP pool allocated to authenticated PPPoE tunnels.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-outline-secondary" wire:click="closePortsModal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-semibold px-4" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="applyPortConfiguration">
                                    <i class="bi bi-check2-circle me-1"></i>Apply Port Configuration
                                </span>
                                <span wire:loading wire:target="applyPortConfiguration">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Applying to Router...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

