<div x-data="{ isOpen: false, copied: false, countdown: 900 }" class="zoom-in"
     x-init="setInterval(() => { if (countdown > 0) countdown--; }, 1000)">
    <!-- Header -->
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">{{ __('Mikrotik Router Fleet & Provisioning') }}</h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                <i class="bi bi-shield-check me-1"></i> {{ __('WireGuard + FreeRADIUS Enabled') }}
            </span>
        </div>
    </x-slot>

    <!-- Provisioning Script Modal -->
    @if ($show_script_modal && $provision_fetch_command)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(4px); z-index: 1055;"
             wire:poll.3s="checkRouterStatus({{ $active_router_id }})">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
                        <div>
                            <h5 class="modal-title fw-bold text-white mb-0">
                                <i class="bi bi-terminal me-2 text-info"></i>{{ __('One-Line MikroTik Bootstrap Script') }}
                            </h5>
                            <small class="text-white-50">{{ __('Paste into your MikroTik Winbox Terminal or SSH session') }}</small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeScriptModal"></button>
                    </div>

                    <div class="modal-body p-4 bg-light">
                        <!-- Instructions alert -->
                        <div class="alert alert-info border-info-subtle d-flex align-items-center justify-content-between mb-3 rounded-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill text-info fs-4 me-3"></i>
                                <div>
                                    <strong>{{ __('Single-Use Provisioning Token Active') }}</strong><br>
                                    <span class="text-muted" style="font-size: 0.85rem;">
                                        {{ __('Configures WireGuard tunnel, FreeRADIUS AAA, DNS, firewall, and hotspot/pppoe automatically.') }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-end ps-3">
                                <span class="badge bg-warning text-dark px-2 py-1" style="font-family: monospace; font-size: 0.9rem;">
                                    <i class="bi bi-clock me-1"></i><span x-text="Math.floor(countdown / 60) + ':' + (countdown % 60 < 10 ? '0' : '') + (countdown % 60)"></span>
                                </span>
                            </div>
                        </div>

                        <!-- Terminal Command Box -->
                        <label class="form-label fw-bold text-dark small mb-1">{{ __('Terminal Command:') }}</label>
                        <div class="position-relative mb-3">
                            <pre class="p-3 rounded-3 text-white m-0"
                                 style="background: #090d16; font-family: 'Courier New', Courier, monospace; font-size: 0.85rem; line-height: 1.5; white-space: pre-wrap; word-break: break-all; border: 1px solid #1e293b;"><code>{{ $provision_fetch_command }}</code></pre>
                            
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ addslashes($provision_fetch_command) }}'); copied = true; setTimeout(() => copied = false, 2500);"
                                    class="btn btn-sm btn-info position-absolute top-0 end-0 m-2 shadow-sm">
                                <i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i>
                                <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy Command') }}'"></span>
                            </button>
                        </div>

                        <!-- Waiting status badge with spinner -->
                        <div class="p-3 bg-white rounded-3 border d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="spinner-grow spinner-grow-sm text-primary me-2" role="status"></div>
                                <span class="text-muted small">
                                    {{ __('Listening for MikroTik handshake... Window will close automatically when online.') }}
                                </span>
                            </div>
                            <button type="button" wire:click="closeScriptModal" class="btn btn-sm btn-secondary">
                                {{ __('Done / Close') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <!-- Add Router Toggle Button -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button
                    @click="isOpen = !isOpen; if (!isOpen) { $wire.set('RouterListId', null); }"
                    class="btn btn-primary rounded-3 px-3 py-2 fw-semibold"
                    type="button">
                    <i class="bi" :class="isOpen ? 'bi-x-lg me-1' : 'bi-plus-lg me-1'"></i>
                    <span x-text="isOpen ? '{{ __('Close Form') }}' : '{{ __('Add New MikroTik Router') }}'"></span>
                </button>
                <span class="text-muted small">
                    <i class="bi bi-activity text-success me-1"></i>{{ count($routers) }} {{ __('Routers in Fleet') }}
                </span>
            </div>

            <!-- Form Section -->
            <div x-show="isOpen" x-transition x-cloak class="mb-4">
                <div class="card border border-primary-subtle bg-light rounded-4 p-4 shadow-sm">
                    <form wire:submit.prevent="submit">
                        <div class="row g-3">
                            <!-- Connection Type Selector -->
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold text-dark small">{{ __('Connection Mode') }}</label>
                                <select class="form-select" wire:model.live="connection_type">
                                    <option value="wireguard">⚡ WireGuard Tunnel (Cloud Gateway, No Static IP)</option>
                                    <option value="direct">🌐 Direct Public IP / Port Forward</option>
                                </select>
                                <x-input-error for='connection_type' />
                            </div>

                            <!-- Router Name -->
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold text-dark small">{{ __('Router Friendly Name *') }}</label>
                                <input class="form-control" type="text" wire:model="router_name" placeholder="e.g. Core-MikroTik-Main" required>
                                <x-input-error for='router_name' />
                            </div>

                            <!-- Management Username -->
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold text-dark small">{{ __('Management Username *') }}</label>
                                <input class="form-control" type="text" wire:model="username" placeholder="admin" required>
                                <x-input-error for='username' />
                            </div>

                            @if ($connection_type === 'direct')
                                <!-- Direct IP Fields -->
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold text-dark small">{{ __('Public IP Address *') }}</label>
                                    <input class="form-control" type="text" wire:model="ip_address" placeholder="e.g. 197.248.185.227">
                                    <x-input-error for='ip_address' />
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold text-dark small">{{ __('API Port') }}</label>
                                    <input class="form-control" type="number" wire:model="api_port" placeholder="8728">
                                    <x-input-error for='api_port' />
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold text-dark small">{{ __('SSH Port') }}</label>
                                    <input class="form-control" type="number" wire:model="ssh_port" placeholder="22">
                                    <x-input-error for='ssh_port' />
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold text-dark small">{{ __('Password') }}</label>
                                    <input class="form-control" type="password" wire:model="password" placeholder="••••••••">
                                    <x-input-error for='password' />
                                </div>
                            @endif

                            <!-- Interface Selectors -->
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-bold text-dark small">{{ __('WAN Interface') }}</label>
                                <input class="form-control" type="text" wire:model="wan_interface" placeholder="ether1">
                                <small class="text-muted" style="font-size: 0.75rem;">Connected to ISP uplink</small>
                                <x-input-error for='wan_interface' />
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label fw-bold text-dark small">{{ __('LAN Interface') }}</label>
                                <input class="form-control" type="text" wire:model="lan_interface" placeholder="ether2">
                                <small class="text-muted" style="font-size: 0.75rem;">Connected to APs / Users</small>
                                <x-input-error for='lan_interface' />
                            </div>

                            <!-- Services Checkboxes -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold text-dark small">{{ __('Services to Provision') }}</label>
                                <div class="d-flex gap-3 pt-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="svc_hotspot" wire:model="services_hotspot">
                                        <label class="form-check-label fw-semibold" for="svc_hotspot">Hotspot Server</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="svc_pppoe" wire:model="services_pppoe">
                                        <label class="form-check-label fw-semibold" for="svc_pppoe">PPPoE Server</label>
                                    </div>
                                </div>
                            </div>

                            @if ($services_hotspot)
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark small">{{ __('Hotspot Subnet') }}</label>
                                    <input class="form-control" type="text" wire:model="hotspot_subnet" placeholder="10.5.50.0/24">
                                </div>
                            @endif

                            @if ($services_pppoe)
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark small">{{ __('PPPoE Pool Subnet') }}</label>
                                    <input class="form-control" type="text" wire:model="pppoe_pool" placeholder="10.0.0.0/24">
                                </div>
                            @endif
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" @click="isOpen = false" class="btn btn-outline-secondary px-3">
                                {{ __('Cancel') }}
                            </button>
                            <button class="btn btn-primary px-4 fw-semibold shadow-sm" type="submit">
                                <i class="bi bi-magic me-1"></i>
                                {{ $RouterListId ? __('Update Router') : __('Save & Generate Script') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Router Cards Grid -->
            <div class="row g-3">
                @forelse ($routers as $router)
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden d-flex flex-column" style="transition: transform 0.2s ease;">
                            <!-- Header -->
                            <div class="px-3 py-3 text-white" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="overflow-hidden">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <h6 class="fw-bold mb-0 text-truncate text-white">{{ $router->name ?: $router->router_name }}</h6>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            @if ($router->vpn_ip)
                                                <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25" style="font-size: 0.75rem;">
                                                    <i class="bi bi-shield-shaded me-1"></i>VPN: {{ $router->vpn_ip }}
                                                </span>
                                            @elseif ($router->ip)
                                                <small class="text-white-50" style="font-size: 0.75rem;"><i class="bi bi-globe me-1"></i>{{ $router->ip }}</small>
                                            @else
                                                <small class="text-white-50" style="font-size: 0.75rem;">Awaiting bootstrap</small>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        @if ($router->status === 'online')
                                            <span class="badge bg-success text-white px-2 py-1 shadow-sm" style="font-size: 0.75rem;">
                                                <i class="bi bi-check-circle-fill me-1"></i>Online
                                            </span>
                                        @elseif ($router->status === 'onboarding')
                                            <span class="badge bg-warning text-dark px-2 py-1 shadow-sm" style="font-size: 0.75rem;">
                                                <i class="bi bi-hourglass-split me-1"></i>Pending
                                            </span>
                                        @else
                                            <span class="badge bg-danger text-white px-2 py-1 shadow-sm" style="font-size: 0.75rem;">
                                                <i class="bi bi-dash-circle-fill me-1"></i>Offline
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Body -->
                            <div class="card-body bg-light p-3 d-flex flex-column justify-content-between">
                                <div class="space-y-2 mb-3">
                                    <div class="d-flex justify-content-between align-items-center" style="font-size: 0.82rem;">
                                        <span class="text-muted">{{ __('Connection') }}:</span>
                                        <span class="fw-semibold text-dark text-capitalize">
                                            {{ $router->connection_type === 'wireguard' || $router->vpn_ip ? 'WireGuard Tunnel' : 'Direct IP' }}
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center" style="font-size: 0.82rem;">
                                        <span class="text-muted">{{ __('Interfaces') }}:</span>
                                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.75rem;">
                                            WAN: {{ $router->wan_interface ?: 'ether1' }} | LAN: {{ $router->lan_interface ?: 'ether2' }}
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center" style="font-size: 0.82rem;">
                                        <span class="text-muted">{{ __('Active Subscribers') }}:</span>
                                        <a href="{{ route('customers.index') }}" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 text-decoration-none" style="font-size: 0.75rem;">
                                            <i class="bi bi-people-fill me-1"></i>{{ $router->user_list_count ?? 0 }}
                                        </a>
                                    </div>

                                    @if ($router->last_seen_at)
                                        <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.75rem;">
                                            <span>{{ __('Last Seen') }}:</span>
                                            <span>{{ $router->last_seen_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Actions -->
                                <div class="pt-2 border-top d-flex justify-content-between align-items-center gap-2">
                                    <!-- One-Line Script Trigger -->
                                    <button class="btn btn-sm btn-dark px-2 py-1"
                                            title="{{ __('Get RouterOS Bootstrap Script') }}"
                                            wire:click="generateBootstrapScript({{ $router->id }})">
                                        <i class="bi bi-terminal me-1 text-info"></i>{{ __('Get Script') }}
                                    </button>

                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-info p-1 px-2" title="{{ __('Edit') }}"
                                                wire:click="edit({{ $router->id }})" @click="isOpen = true">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger p-1 px-2" title="{{ __('Delete') }}"
                                                wire:click="delete({{ $router->id }})"
                                                wire:confirm="Revoke WireGuard and delete this router?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 py-5 text-center text-muted">
                        <i class="bi bi-router fs-1 d-block mb-2 text-secondary"></i>
                        <h6 class="fw-bold">{{ __('No routers added yet') }}</h6>
                        <p class="small mb-3">{{ __('Add your first MikroTik router to generate the one-line bootstrap script.') }}</p>
                        <button @click="isOpen = true" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-plus-lg me-1"></i>{{ __('Add Router Now') }}
                        </button>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $routers->links() }}
            </div>
        </div>
    </div>
</div>
