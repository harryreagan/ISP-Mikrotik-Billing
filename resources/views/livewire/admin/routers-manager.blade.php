<div wire:poll.15s="$refresh">
    {{-- Header --}}
    <x-ui.page-header title="Routers" subtitle="Monitor online status, live CPU/RAM telemetry, connected subscribers, and manage router fleet.">
        <x-slot:actions>
            <x-ui.button variant="primary" :href="route('admin.add-router')">
                <i class="bi bi-plus-lg"></i> Add router
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

    {{-- 4 Clean Analytics Summary Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Routers --}}
        <div class="col-6 col-lg-3">
            <x-ui.stat-card label="Total fleet" :value="$totalRouters" sub="Configured routers" color="navy">
                <x-slot:icon><i class="bi bi-hdd-network"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Online Routers --}}
        <div class="col-6 col-lg-3">
            <x-ui.stat-card label="Online status" :value="$onlineRouters . ' / ' . $totalRouters" sub="Live heartbeat verified" color="green">
                <x-slot:icon><i class="bi bi-activity"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- Connected Subscribers --}}
        <div class="col-6 col-lg-3">
            <x-ui.stat-card label="Subscribers" :value="$totalClients" sub="Active customer sessions" color="purple">
                <x-slot:icon><i class="bi bi-people"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>

        {{-- System Health / CPU --}}
        <div class="col-6 col-lg-3">
            <x-ui.stat-card label="System health" :value="$avgCpu . '%'" :sub="($avgCpu > 80 ? 'Heavy load' : 'Healthy') . ' • Avg online CPU load'" color="blue">
                <x-slot:icon><i class="bi bi-cpu"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
    </div>

    {{-- Filter & Search Toolbar --}}
    <x-ui.card class="mb-4 p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control border-start-0" placeholder="Search routers by name, location, or IP...">
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex align-items-center gap-1.5">
                <button type="button" wire:click="$set('filterStatus', 'all')" class="btn btn-sm {{ $filterStatus === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    All ({{ $totalRouters }})
                </button>
                <button type="button" wire:click="$set('filterStatus', 'online')" class="btn btn-sm {{ $filterStatus === 'online' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Online ({{ $onlineRouters }})
                </button>
                <button type="button" wire:click="$set('filterStatus', 'offline')" class="btn btn-sm {{ $filterStatus === 'offline' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Offline ({{ $totalRouters - $onlineRouters }})
                </button>
            </div>
        </div>
    </x-ui.card>

    {{-- Routers Cards Grid --}}
    <div class="row g-3">
        @forelse($routers as $router)
            @php
                $isOnline = $router->status === 'online' && $router->last_seen_at && $router->last_seen_at->gt(now()->subMinutes(5));
                $cpu = (int) ($router->cpu_load ?? 0);
                $ram = (int) ($router->memory_load ?? 0);
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <x-ui.card :padding="false" class="h-100 d-flex flex-column overflow-hidden">
                    <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="overflow-hidden me-2">
                            <h3 class="ui-section-title text-truncate mb-0">{{ $router->name ?: $router->router_name }}</h3>
                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $router->location ?: 'Location not set' }}</small>
                        </div>
                        <div>
                            @if($isOnline)
                                <x-ui.badge tone="success"><i class="bi bi-check-circle-fill me-1"></i>Online</x-ui.badge>
                            @elseif($router->status === 'rebooting')
                                <x-ui.badge tone="warning"><i class="bi bi-arrow-repeat me-1"></i>Rebooting</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral"><i class="bi bi-dash-circle me-1"></i>Offline</x-ui.badge>
                            @endif
                        </div>
                    </div>

                    <div class="p-4 d-flex flex-column flex-grow-1 justify-content-between">
                        <div>
                            {{-- Connection & IP Details --}}
                            <div class="bg-light rounded-3 p-2.5 mb-3 small">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted">Cloud tunnel IP:</span>
                                    <span class="font-monospace fw-semibold text-dark">{{ $router->vpn_ip ?: '10.10.0.x' }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Direct / public IP:</span>
                                    <span class="font-monospace text-muted">{{ $router->ip ?: ($router->ip_address ?: '—') }}</span>
                                </div>
                            </div>

                            {{-- Live Hardware Telemetry (CPU & RAM) --}}
                            <div class="row g-2 mb-3">
                                {{-- CPU --}}
                                <div class="col-6">
                                    <div class="border rounded-3 p-2.5 text-center h-100">
                                        <div class="d-flex align-items-center justify-content-between small text-muted mb-1">
                                            <span class="fw-medium">CPU</span>
                                            <span class="fw-semibold {{ $cpu > 75 ? 'text-danger' : ($cpu > 40 ? 'text-warning' : 'text-success') }}">{{ $cpu }}%</span>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar {{ $cpu > 75 ? 'bg-danger' : ($cpu > 40 ? 'bg-warning' : 'bg-success') }}" style="width: {{ max($cpu, 5) }}%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 11px;">
                                            <span>Load</span>
                                            <span class="font-monospace fw-medium">{{ $cpu }}%</span>
                                        </div>
                                    </div>
                                </div>
                                {{-- RAM --}}
                                <div class="col-6">
                                    <div class="border rounded-3 p-2.5 text-center h-100">
                                        <div class="d-flex align-items-center justify-content-between small text-muted mb-1">
                                            <span class="fw-medium">RAM</span>
                                            <span class="fw-semibold {{ $ram > 85 ? 'text-danger' : ($ram > 70 ? 'text-warning' : 'text-primary') }}">{{ $ram }}%</span>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar {{ $ram > 85 ? 'bg-danger' : ($ram > 70 ? 'bg-warning' : 'bg-primary') }}" style="width: {{ max($ram, 5) }}%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 11px;">
                                            @if(!empty($router->total_memory) && $router->total_memory > 0)
                                                <span class="font-monospace">{{ round(($router->total_memory - ($router->free_memory ?? 0)) / 1048576, 1) }}M</span>
                                                <span class="text-muted">/ {{ round($router->total_memory / 1048576, 0) }}M</span>
                                            @else
                                                <span>Used</span>
                                                <span class="font-monospace fw-medium">{{ $ram }}%</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Telemetry details table --}}
                            <div class="space-y-1 small text-muted mb-3">
                                <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom">
                                    <span>Router model:</span>
                                    <span class="text-dark fw-medium">
                                        {{ $router->board ?: ($router->model ?: 'MikroTik RouterOS') }}
                                        @if(!empty($router->model) && $router->model !== $router->board)
                                            <span class="text-muted fw-normal">({{ $router->model }})</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom">
                                    <span>Serial number:</span>
                                    <x-ui.badge tone="neutral" class="font-monospace">{{ $router->serial_number ?: '—' }}</x-ui.badge>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom">
                                    <span>RouterOS:</span>
                                    <x-ui.badge tone="info" class="font-monospace">
                                        v{{ $router->version ?: '7.x' }}
                                        @if(!empty($router->architecture))
                                            ({{ $router->architecture }})
                                        @endif
                                    </x-ui.badge>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom">
                                    <span>Uptime:</span>
                                    <span class="text-dark font-monospace fw-medium">{{ $router->uptime ?: '—' }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom">
                                    <span>Active online:</span>
                                    <div>
                                        @php $activeCount = $router->live_active ?? ($router->port_roles['active_total'] ?? ($router->subscribers ?? 0)); @endphp
                                        <x-ui.badge :tone="$activeCount > 0 ? 'success' : 'neutral'" class="font-monospace">
                                            <i class="bi bi-broadcast me-1"></i><strong>{{ $activeCount }}</strong> online
                                        </x-ui.badge>
                                        @if(($router->live_hs ?? 0) > 0 || ($router->live_ppp ?? 0) > 0)
                                            <span class="text-muted small ms-1" style="font-size: 11px;">
                                                ({{ $router->live_hs ?? 0 }} HS, {{ $router->live_ppp ?? 0 }} PPPoE)
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom">
                                    <span>Subscribers:</span>
                                    <a href="{{ route('admin.customers') }}" class="text-decoration-none">
                                        <x-ui.badge tone="info"><i class="bi bi-people me-1"></i>{{ $router->subscribers_count ?? 0 }} registered</x-ui.badge>
                                    </a>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1.5">
                                    <span>Last seen:</span>
                                    <span class="text-dark">{{ $router->last_seen_at ? $router->last_seen_at->diffForHumans() : 'Never' }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top gap-1 flex-wrap">
                            <button type="button" 
                                    wire:click="pollRouterTelemetry({{ $router->id }})" 
                                    wire:loading.attr="disabled"
                                    class="btn btn-outline-secondary btn-sm px-2" 
                                    title="Check live telemetry">
                                <span wire:loading.remove wire:target="pollRouterTelemetry({{ $router->id }})">
                                    <i class="bi bi-arrow-repeat me-1"></i>Sync
                                </span>
                                <span wire:loading wire:target="pollRouterTelemetry({{ $router->id }})">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Syncing...
                                </span>
                            </button>

                            <button type="button" 
                                    wire:click="openHotspotCheck({{ $router->id }})" 
                                    wire:loading.attr="disabled"
                                    class="btn btn-outline-primary btn-sm px-2" 
                                    title="Run hotspot health check">
                                <span wire:loading.remove wire:target="openHotspotCheck({{ $router->id }})">
                                    <i class="bi bi-shield-check me-1"></i>Hotspot check
                                </span>
                                <span wire:loading wire:target="openHotspotCheck({{ $router->id }})">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Checking...
                                </span>
                            </button>

                            <button type="button" 
                                    wire:click="openPortalModal({{ $router->id }})" 
                                    class="btn btn-outline-info btn-sm px-2" 
                                    title="Captive portal & QR preview">
                                <i class="bi bi-qr-code me-1"></i>Portal
                            </button>

                            <button type="button" 
                                    wire:click="openPortsModal({{ $router->id }})" 
                                    class="btn btn-outline-secondary btn-sm px-2" 
                                    title="Inspect & configure ports">
                                <i class="bi bi-ethernet me-1"></i>Ports
                            </button>

                            <button type="button" 
                                    wire:click="rebootRouter({{ $router->id }})" 
                                    wire:confirm="Are you sure you want to reboot {{ $router->name }}? Active customer sessions will briefly drop."
                                    class="btn btn-outline-warning btn-sm px-2" 
                                    title="Reboot router">
                                <i class="bi bi-power me-1"></i>Reboot
                            </button>

                            <button type="button" 
                                    wire:click="editRouter({{ $router->id }})" 
                                    wire:loading.attr="disabled"
                                    class="btn btn-outline-secondary btn-sm px-2" 
                                    title="Edit router">
                                <span wire:loading.remove wire:target="editRouter({{ $router->id }})">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </span>
                                <span wire:loading wire:target="editRouter({{ $router->id }})">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Loading...
                                </span>
                            </button>

                            <button type="button" 
                                    wire:click="deleteRouter({{ $router->id }})" 
                                    wire:confirm="Are you sure you want to remove {{ $router->name }} from system?"
                                    class="btn btn-outline-danger btn-sm px-2" 
                                    title="Delete router">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        @empty
            <div class="col-12">
                <x-ui.card :padding="false">
                    <x-ui.empty-state icon="bi-router" title="No routers found" description="Add your MikroTik router using the 4-step wizard to monitor health, subscribers, and automate billing.">
                        <x-ui.button variant="primary" :href="route('admin.add-router')">
                            <i class="bi bi-plus-lg"></i> Add first router
                        </x-ui.button>
                    </x-ui.empty-state>
                </x-ui.card>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $routers->links() }}
    </div>

    {{-- EDIT ROUTER MODAL --}}
    @if($showEditModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.6); z-index: 1055;" wire:click.self="closeEditModal" wire:keydown.escape="closeEditModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header border-bottom">
                        <h5 class="modal-title font-semibold text-slate-800"><i class="bi bi-pencil-square me-2 text-orange-500"></i>Edit router</h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal" aria-label="Close"></button>
                    </div>
                    <form wire:submit.prevent="updateRouter">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label text-sm fw-medium text-slate-700">Router name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="edit_name" class="form-control @error('edit_name') is-invalid @enderror" required>
                                @error('edit_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-sm fw-medium text-slate-700">Location / branch</label>
                                <input type="text" wire:model="edit_location" class="form-control @error('edit_location') is-invalid @enderror" placeholder="e.g. Westlands Mall or Tower 3">
                                @error('edit_location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-8">
                                    <label class="form-label text-sm fw-medium text-slate-700">Management IP</label>
                                    <input type="text" wire:model="edit_ip" class="form-control @error('edit_ip') is-invalid @enderror" placeholder="e.g. 10.10.0.50">
                                    @error('edit_ip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-4">
                                    <label class="form-label text-sm fw-medium text-slate-700">API port</label>
                                    <input type="number" wire:model="edit_api_port" class="form-control @error('edit_api_port') is-invalid @enderror" required>
                                    @error('edit_api_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label text-sm fw-medium text-slate-700">API username</label>
                                    <input type="text" wire:model="edit_username" class="form-control @error('edit_username') is-invalid @enderror" required>
                                    @error('edit_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-sm fw-medium text-slate-700">Password (optional)</label>
                                    <input type="password" wire:model="edit_password" class="form-control" placeholder="Leave blank to keep">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer border-top bg-light">
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="closeEditModal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm px-3" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="updateRouter,saveRouter">Save changes</span>
                                <span wire:loading wire:target="updateRouter,saveRouter">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- PORTS CONFIGURATION MODAL --}}
    @if($showPortsModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(2px); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0">
                                <i class="bi bi-ethernet me-2 text-orange-500"></i>Interface ports &amp; service roles — {{ $portsRouterName }}
                            </h5>
                            <small class="text-muted">{{ $portsRouterModel }} &bull; WireGuard tunnel: <span class="font-monospace text-dark">{{ $portsRouterIp }}</span></small>
                        </div>
                        <button type="button" class="btn-close" wire:click="closePortsModal"></button>
                    </div>

                    <form wire:submit.prevent="applyPortConfiguration">
                        <div class="modal-body p-4">
                            {{-- Visual Ethernet Port Rack (Faceplate) --}}
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label mb-0">Physical interface link status</label>
                                    <div class="d-flex align-items-center gap-3 small text-muted">
                                        <span><span class="d-inline-block rounded-circle bg-success me-1" style="width: 8px; height: 8px;"></span>Link up (active)</span>
                                        <span><span class="d-inline-block rounded-circle bg-secondary me-1" style="width: 8px; height: 8px;"></span>Unplugged (down)</span>
                                    </div>
                                </div>

                                <div class="bg-dark rounded-3 p-3">
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
                                                <div class="rounded-3 border p-2 text-center {{ $isLinkUp ? 'bg-black border-success' : 'bg-black bg-opacity-50 border-secondary' }}">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="badge {{ $isLinkUp ? 'bg-success' : 'bg-secondary' }}" style="font-size: 9px;">
                                                            {{ $isLinkUp ? 'Link up' : 'Down' }}
                                                        </span>
                                                        <span class="text-muted font-monospace" style="font-size: 10px;">{{ strtoupper($iface['type'] ?? 'ETH') }}</span>
                                                    </div>

                                                    {{-- RJ45 Icon / Port Name --}}
                                                    <div class="my-1">
                                                        <i class="bi bi-ethernet fs-4 {{ $isLinkUp ? 'text-success' : 'text-secondary' }}"></i>
                                                        <div class="fw-semibold font-monospace text-light small">{{ $iName }}</div>
                                                    </div>

                                                    {{-- Assigned Role Badge & Quick Switcher --}}
                                                    <div class="mt-1">
                                                        <select class="form-select form-select-sm text-center py-0 px-1 border-0 fw-bold"
                                                            style="font-size: 10px; height: 26px; border-radius: 6px; cursor: pointer;
                                                            {{ $isWan ? 'background-color: #2563eb; color: #fff;' : ($isHs ? 'background-color: #f59e0b; color: #111;' : ($isPpp ? 'background-color: #06b6d4; color: #fff;' : 'background-color: #475569; color: #fff;')) }}"
                                                            wire:change="setPortRole('{{ $iName }}', $event.target.value)"
                                                            title="Change role for {{ $iName }}"
                                                        >
                                                            <option value="wan" {{ $isWan ? 'selected' : '' }} style="background-color: #fff; color: #000;">WAN (Uplink)</option>
                                                            <option value="hotspot" {{ $isHs ? 'selected' : '' }} style="background-color: #fff; color: #000;">Hotspot</option>
                                                            <option value="pppoe" {{ $isPpp ? 'selected' : '' }} style="background-color: #fff; color: #000;">PPPoE</option>
                                                            <option value="lan" {{ (!$isWan && !$isHs && !$isPpp) ? 'selected' : '' }} style="background-color: #fff; color: #000;">LAN (Spare)</option>
                                                        </select>
                                                    </div>

                                                    @if(!$isLinkUp && ($isHs || $isPpp))
                                                        <div class="text-warning text-center mt-1" style="font-size: 8.5px; line-height: 1.1;">
                                                            <i class="bi bi-info-circle me-0.5"></i>Will activate when plugged in
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Reconfiguration Form --}}
                            <div class="border rounded-3 p-3 bg-light">
                                <h6 class="ui-section-title mb-3"><i class="bi bi-sliders me-2 text-orange-500"></i>Reconfigure port roles &amp; subnets</h6>

                                <div class="row g-3">
                                    {{-- WAN Port --}}
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">
                                            <i class="bi bi-globe me-1"></i>WAN (internet uplink) port <span class="text-danger">*</span>
                                        </label>
                                        <select wire:model.live="portRoleWan" class="form-select" required>
                                            @foreach($portInterfaces as $iface)
                                                <option value="{{ $iface['name'] }}">
                                                    {{ $iface['name'] }} {{ $iface['running'] ? '(Active link)' : '(Unplugged)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text small">Obtains upstream internet via DHCP client.</div>
                                    </div>

                                    {{-- Hotspot Port --}}
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">
                                            <i class="bi bi-wifi me-1"></i>Hotspot port <span class="text-danger">*</span>
                                        </label>
                                        <select wire:model.live="portRoleHotspot" class="form-select" required>
                                            @foreach($portInterfaces as $iface)
                                                <option value="{{ $iface['name'] }}">
                                                    {{ $iface['name'] }} {{ $iface['running'] ? '(Active link)' : '(Unplugged)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text small">Serves captive portal &amp; customer vouchers.</div>
                                    </div>

                                    {{-- PPPoE Port --}}
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">
                                            <i class="bi bi-router me-1"></i>PPPoE server port <span class="text-danger">*</span>
                                        </label>
                                        <select wire:model.live="portRolePppoe" class="form-select" required>
                                            @foreach($portInterfaces as $iface)
                                                <option value="{{ $iface['name'] }}">
                                                    {{ $iface['name'] }} {{ $iface['running'] ? '(Active link)' : '(Unplugged)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text small">Authenticates PPPoE client subscriber routers.</div>
                                    </div>

                                    {{-- Hotspot Subnet --}}
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Hotspot subnet</label>
                                        <input type="text" wire:model="portHotspotSubnet" class="form-control font-monospace" placeholder="10.5.50.0/24" required>
                                        <div class="form-text small">Router gateway will be assigned <code>10.5.50.1</code> with DHCP pool.</div>
                                    </div>

                                    {{-- PPPoE Pool --}}
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">PPPoE pool subnet</label>
                                        <input type="text" wire:model="portPppoePool" class="form-control font-monospace" placeholder="10.0.0.0/24" required>
                                        <div class="form-text small">IP pool allocated to authenticated PPPoE tunnels.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <x-ui.button variant="secondary" wire:click="closePortsModal">Cancel</x-ui.button>
                            <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="applyPortConfiguration">
                                    <i class="bi bi-check2-circle me-1"></i>Apply port configuration
                                </span>
                                <span wire:loading wire:target="applyPortConfiguration">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Applying to router...
                                </span>
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- 1. HOTSPOT HEALTH CHECK MODAL --}}
    @if($showHealthModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.6); z-index: 1055;" wire:click.self="closeHealthModal" wire:keydown.escape="closeHealthModal">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header border-bottom d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="modal-title font-semibold text-slate-800 d-flex align-items-center gap-2">
                                <i class="bi bi-shield-check text-orange-500"></i>
                                Hotspot health check &mdash; {{ $healthRouterName }}
                            </h5>
                            <div class="text-muted small">Diagnostic verification for captive portal &amp; client onboarding</div>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeHealthModal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto;">
                        {{-- Top Metric Summary --}}
                        <div class="row g-2 mb-4">
                            <div class="col-6 col-sm-3">
                                <div class="p-3 rounded-3 border bg-light text-center">
                                    <div class="text-muted small fw-medium">Checks run</div>
                                    <div class="fs-4 fw-bold text-dark">{{ count($healthChecks) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="p-3 rounded-3 border bg-success-subtle text-center">
                                    <div class="text-success small fw-medium">Passed</div>
                                    <div class="fs-4 fw-bold text-success">{{ $healthPassedCount }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="p-3 rounded-3 border {{ $healthFailedCount > 0 ? 'bg-danger-subtle' : 'bg-light' }} text-center">
                                    <div class="{{ $healthFailedCount > 0 ? 'text-danger' : 'text-muted' }} small fw-medium">Attention needed</div>
                                    <div class="fs-4 fw-bold {{ $healthFailedCount > 0 ? 'text-danger' : 'text-dark' }}">{{ $healthFailedCount }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="p-3 rounded-3 border bg-light text-center d-flex flex-column justify-content-center">
                                    <button type="button" wire:click="runHotspotCheck" wire:loading.attr="disabled" class="btn btn-outline-secondary btn-sm">
                                        <span wire:loading.remove wire:target="runHotspotCheck">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Re-run check
                                        </span>
                                        <span wire:loading wire:target="runHotspotCheck">
                                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>Checking...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Tenda Router Mode Warning Callout Card --}}
                        @if($tendaWarning)
                            <div class="card border-warning mb-4 shadow-sm" style="border-left: 5px solid #f59e0b !important; background-color: #fffbeb;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="p-2 rounded-circle bg-warning text-white flex-shrink-0" style="width: 40px; height: 40px; display: grid; place-items: center;">
                                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold text-warning-emphasis mb-1">
                                                The Tenda is in router mode; set it to AP mode (use LAN port, disable DHCP)
                                            </h6>
                                            <p class="small text-secondary mb-2">
                                                The Tenda router at <strong>{{ $tendaWarning['ip'] }}</strong> (MAC: <code>{{ $tendaWarning['mac'] }}</code>) is connected via its WAN port and acting as a second router. It is hiding connected phones behind its single IP, so phones never see the captive portal sign-in page!
                                            </p>
                                            <div class="p-2.5 rounded-2 bg-white border border-warning-subtle small">
                                                <div class="fw-bold text-dark mb-1"><i class="bi bi-list-check me-1 text-warning"></i>Step-by-step instructions to switch Tenda into Access Point (AP) mode:</div>
                                                <ol class="mb-0 ps-3 text-secondary" style="font-size: 12px; line-height: 1.6;">
                                                    @foreach($tendaWarning['steps'] as $step)
                                                        <li>{{ $step }}</li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Diagnostics List (Checks a to i) --}}
                        <div class="vstack gap-2">
                            @foreach($healthChecks as $key => $check)
                                <div class="p-3 rounded-3 border d-flex align-items-start gap-3 {{ $check['passed'] ? 'bg-white' : 'bg-white border-danger-subtle' }}">
                                    <div class="flex-shrink-0 mt-0.5">
                                        @if($check['passed'])
                                            <span class="badge bg-success rounded-circle p-1.5" style="width: 24px; height: 24px; display: inline-grid; place-items: center;">
                                                <i class="bi bi-check-lg" style="font-size: 13px;"></i>
                                            </span>
                                        @else
                                            <span class="badge bg-danger rounded-circle p-1.5" style="width: 24px; height: 24px; display: inline-grid; place-items: center;">
                                                <i class="bi bi-x-lg" style="font-size: 13px;"></i>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <div class="fw-semibold text-slate-800 text-sm">
                                                <span class="badge bg-secondary-subtle text-secondary me-1 font-monospace" style="font-size: 10px;">{{ strtoupper($key) }}</span>
                                                {{ $check['title'] }}
                                            </div>
                                            <span class="badge {{ $check['passed'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ $check['passed'] ? 'Passed' : 'Action needed' }}
                                            </span>
                                        </div>
                                        <div class="text-secondary small mb-1">{{ $check['detail'] }}</div>
                                        @if(!$check['passed'] && !empty($check['fix']))
                                            <div class="p-2 rounded bg-light border text-dark small" style="font-size: 11.5px;">
                                                <strong class="text-orange-600"><i class="bi bi-tools me-1"></i>Fix:</strong> {{ $check['fix'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="modal-footer border-top d-flex justify-content-between">
                        <x-ui.button variant="secondary" wire:click="closeHealthModal">Close</x-ui.button>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" wire:click="openPortalModal({{ $healthRouterId }})" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-qr-code me-1"></i>Preview portal &amp; QR
                            </button>
                            @if($healthFailedCount > 0)
                                <button type="button" wire:click="openFixConfirmation" class="btn btn-warning btn-sm fw-bold">
                                    <i class="bi bi-magic me-1"></i>Fix automatically
                                </button>
                            @else
                                <span class="badge bg-success-subtle text-success py-2 px-3 fw-semibold">
                                    <i class="bi bi-check-circle-fill me-1"></i>All checks passing
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 2. FIX CONFIRMATION MODAL --}}
    @if($showFixConfirmationModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.7); z-index: 1060;" wire:click.self="closeFixConfirmation" wire:keydown.escape="closeFixConfirmation">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header border-bottom">
                        <h5 class="modal-title font-semibold text-slate-800 d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock-fill text-orange-500"></i>
                            Confirm automatic hotspot fix
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeFixConfirmation" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info border-info-subtle small mb-3">
                            <i class="bi bi-shield-check me-1 fw-bold"></i>
                            <strong>Safety guarantee:</strong> Only Hotspot, DHCP, DNS, and Walled Garden rules on the Hotspot subnet will be updated. Your <strong>WAN port</strong> and <strong>WireGuard VPN connection</strong> are strictly protected and will not be touched.
                        </div>

                        <div class="fw-semibold text-dark text-sm mb-2">The following actions will be applied to {{ $healthRouterName }}:</div>
                        <ul class="list-group list-group-flush border rounded-3 mb-3 small">
                            @forelse($pendingFixes as $pf)
                                <li class="list-group-item d-flex align-items-start gap-2 py-2">
                                    <i class="bi bi-check2-circle text-success mt-0.5"></i>
                                    <div>
                                        <div class="fw-medium text-dark">{{ $pf['title'] }}</div>
                                        <div class="text-secondary" style="font-size: 11px;">{{ $pf['action'] }}</div>
                                    </div>
                                </li>
                            @empty
                                <li class="list-group-item text-muted py-2">All software settings are already correct.</li>
                            @endforelse
                            <li class="list-group-item d-flex align-items-start gap-2 py-2">
                                <i class="bi bi-file-earmark-code text-primary mt-0.5"></i>
                                <div>
                                    <div class="fw-medium text-dark">Upload branded captive portal (hotspot/login.html)</div>
                                    <div class="text-secondary" style="font-size: 11px;">Upload responsive login page with voucher and M-Pesa options.</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer border-top">
                        <x-ui.button variant="secondary" wire:click="closeFixConfirmation">Cancel</x-ui.button>
                        <button type="button" wire:click="applyAutomaticFix" wire:loading.attr="disabled" class="btn btn-warning fw-bold">
                            <span wire:loading.remove wire:target="applyAutomaticFix">
                                <i class="bi bi-check2-circle me-1"></i>Apply safe fixes now
                            </span>
                            <span wire:loading wire:target="applyAutomaticFix">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>Applying fixes...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 3. CAPTIVE PORTAL MODAL --}}
    @if($showPortalModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.6); z-index: 1055;" wire:click.self="closePortalModal" wire:keydown.escape="closePortalModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header border-bottom">
                        <div>
                            <h5 class="modal-title font-semibold text-slate-800 d-flex align-items-center gap-2">
                                <i class="bi bi-qr-code text-orange-500"></i>
                                Captive portal &mdash; {{ $portalRouterName }}
                            </h5>
                            <div class="text-muted small">Live customer portal preview and RouterOS deployment</div>
                        </div>
                        <button type="button" class="btn-close" wire:click="closePortalModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <div class="mb-3">
                            <div class="p-3 bg-white border rounded-3 d-inline-block shadow-sm">
                                <img src="{{ $portalQrUrl }}" alt="Portal QR Code" class="img-fluid" style="width: 200px; height: 200px;" />
                            </div>
                        </div>
                        <div class="fw-semibold text-dark mb-1">Scan with phone camera to test</div>
                        <div class="text-muted small mb-3">Scan this code directly from your smartphone to experience the exact customer checkout flow before going live.</div>

                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <a href="{{ $portalPreviewUrl }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Open preview in browser
                            </a>
                        </div>

                        <div class="border-top pt-3 text-start">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-semibold text-dark text-sm">Push login.html to router</div>
                                    <div class="text-muted small">Uploads branded template directly to MikroTik storage.</div>
                                </div>
                                <button type="button" wire:click="pushCaptivePortal" wire:loading.attr="disabled" class="btn btn-primary btn-sm">
                                    <span wire:loading.remove wire:target="pushCaptivePortal">
                                        <i class="bi bi-upload me-1"></i>Push now
                                    </span>
                                    <span wire:loading wire:target="pushCaptivePortal">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>Uploading...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <x-ui.button variant="secondary" wire:click="closePortalModal">Close</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

