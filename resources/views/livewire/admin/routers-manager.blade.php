<div class="container-fluid py-4">
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
                                <div class="border rounded p-2 text-center h-100">
                                    <div class="d-flex align-items-center justify-content-between small text-muted mb-1">
                                        <span>CPU</span>
                                        <span class="fw-bold {{ $cpu > 75 ? 'text-danger' : 'text-dark' }}">{{ $cpu }}%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar {{ $cpu > 75 ? 'bg-danger' : 'bg-primary' }}" style="width: {{ $cpu }}%"></div>
                                    </div>
                                </div>
                            </div>
                            {{-- RAM --}}
                            <div class="col-6">
                                <div class="border rounded p-2 text-center h-100">
                                    <div class="d-flex align-items-center justify-content-between small text-muted mb-1">
                                        <span>RAM</span>
                                        <span class="fw-bold {{ $ram > 85 ? 'text-danger' : 'text-dark' }}">{{ $ram }}%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar {{ $ram > 85 ? 'bg-danger' : 'bg-info' }}" style="width: {{ $ram }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Telemetry details table --}}
                        <div class="space-y-1 small text-muted mb-3">
                            <div class="d-flex justify-content-between py-1 border-bottom border-light">
                                <span>Router Model:</span>
                                <span class="text-dark fw-semibold">{{ $router->model ?: 'MikroTik RouterOS' }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom border-light">
                                <span>Uptime:</span>
                                <span class="text-dark">{{ $router->uptime ?: '—' }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom border-light">
                                <span>Subscribers:</span>
                                <a href="{{ route('admin.customers') }}" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none">
                                    <i class="bi bi-people me-1"></i>{{ $router->subscribers_count ?? 0 }} Users
                                </a>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span>Last Seen:</span>
                                <span class="text-dark">{{ $router->last_seen_at ? $router->last_seen_at->diffForHumans() : 'Never' }}</span>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top gap-1">
                            <button type="button" 
                                    wire:click="pollRouterTelemetry({{ $router->id }})" 
                                    class="btn btn-outline-secondary btn-sm px-2" 
                                    title="Check Live Telemetry">
                                <i class="bi bi-arrow-repeat me-1"></i>Sync
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
</div>
