<div x-data="{ isOpen: false, copied: false, countdown: 900 }"
     x-init="setInterval(() => { if (countdown > 0) countdown--; }, 1000)">
    <!-- Header -->
    <x-slot name="header">
        {{ __('MikroTik router fleet & provisioning') }}
    </x-slot>

    <x-ui.page-header :title="__('MikroTik router fleet & provisioning')" :subtitle="__('Manage WireGuard tunnels, FreeRADIUS provisioning scripts, and direct IP routers.')">
        <x-slot:actions>
            <x-ui.badge tone="info">
                <i class="bi bi-shield-check me-1"></i> {{ __('WireGuard + FreeRADIUS enabled') }}
            </x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <!-- Provisioning Script Modal -->
    @if ($show_script_modal && $provision_fetch_command)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(2px); z-index: 1055;"
             wire:poll.3s="checkRouterStatus({{ $active_router_id }})">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content overflow-hidden">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0">
                                <i class="bi bi-terminal me-2 text-orange-500"></i>{{ __('One-line MikroTik bootstrap script') }}
                            </h5>
                            <small class="text-muted">{{ __('Paste into your MikroTik Winbox Terminal or SSH session') }}</small>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeScriptModal"></button>
                    </div>

                    <div class="modal-body p-4">
                        <!-- Instructions alert -->
                        <div class="alert alert-info d-flex align-items-center justify-content-between mb-3 rounded-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill text-info fs-5 me-3"></i>
                                <div>
                                    <strong class="small">{{ __('Single-use provisioning token active') }}</strong><br>
                                    <span class="text-muted" style="font-size: 0.75rem;">
                                        {{ __('Configures WireGuard tunnel, FreeRADIUS AAA, DNS, firewall, and hotspot/pppoe automatically.') }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-end ps-3">
                                <x-ui.badge tone="warning" class="font-monospace">
                                    <i class="bi bi-clock me-1"></i><span x-text="Math.floor(countdown / 60) + ':' + (countdown % 60 < 10 ? '0' : '') + (countdown % 60)"></span>
                                </x-ui.badge>
                            </div>
                        </div>

                        <!-- Terminal Command Box -->
                        <label class="form-label mb-1">{{ __('Terminal command:') }}</label>
                        <div class="position-relative mb-3">
                            <pre class="p-3 rounded-3 text-white m-0"
                                 style="background: #0f172a; font-family: monospace; font-size: 0.8125rem; line-height: 1.5; white-space: pre-wrap; word-break: break-all;"><code>{{ $provision_fetch_command }}</code></pre>
                            
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ addslashes($provision_fetch_command) }}'); copied = true; setTimeout(() => copied = false, 2500);"
                                    class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2">
                                <i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i>
                                <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy command') }}'"></span>
                            </button>
                        </div>

                        <!-- Waiting status badge with spinner -->
                        <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="spinner-grow spinner-grow-sm text-primary me-2" role="status"></div>
                                <span class="text-muted small">
                                    {{ __('Listening for MikroTik handshake... Window will close automatically when online.') }}
                                </span>
                            </div>
                            <x-ui.button variant="secondary" wire:click="closeScriptModal">
                                {{ __('Done / close') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-ui.card class="mb-4">
        <!-- Add Router Toggle Button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <button
                @click="isOpen = !isOpen; if (!isOpen) { $wire.set('RouterListId', null); }"
                class="btn btn-primary"
                type="button">
                <i class="bi" :class="isOpen ? 'bi-x-lg me-1' : 'bi-plus-lg me-1'"></i>
                <span x-text="isOpen ? '{{ __('Close form') }}' : '{{ __('Add new MikroTik router') }}'"></span>
            </button>
            <span class="text-muted small">
                <i class="bi bi-activity text-success me-1"></i>{{ count($routers) }} {{ __('routers in fleet') }}
            </span>
        </div>

        <!-- Form Section -->
        <div x-show="isOpen" x-transition x-cloak class="mb-4">
            <div class="border rounded-3 bg-light p-4">
                <form wire:submit.prevent="submit">
                    <div class="row g-3">
                        <!-- Connection Type Selector -->
                        <div class="col-12 col-md-4">
                            <label class="form-label">{{ __('Connection mode') }}</label>
                            <select class="form-select" wire:model.live="connection_type">
                                <option value="wireguard">WireGuard tunnel (cloud gateway, no static IP)</option>
                                <option value="direct">Direct public IP / port forward</option>
                            </select>
                            <x-input-error for='connection_type' />
                        </div>

                        <!-- Router Name -->
                        <div class="col-12 col-md-4">
                            <label class="form-label">{{ __('Router friendly name *') }}</label>
                            <input class="form-control" type="text" wire:model="router_name" placeholder="e.g. Core-MikroTik-Main" required>
                            <x-input-error for='router_name' />
                        </div>

                        <!-- Management Username -->
                        <div class="col-12 col-md-4">
                            <label class="form-label">{{ __('Management username *') }}</label>
                            <input class="form-control" type="text" wire:model="username" placeholder="admin" required>
                            <x-input-error for='username' />
                        </div>

                        @if ($connection_type === 'direct')
                            <!-- Direct IP Fields -->
                            <div class="col-12 col-md-4">
                                <label class="form-label">{{ __('Public IP address *') }}</label>
                                <input class="form-control" type="text" wire:model="ip_address" placeholder="e.g. 197.248.185.227">
                                <x-input-error for='ip_address' />
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">{{ __('API port') }}</label>
                                <input class="form-control" type="number" wire:model="api_port" placeholder="8728">
                                <x-input-error for='api_port' />
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">{{ __('SSH port') }}</label>
                                <input class="form-control" type="number" wire:model="ssh_port" placeholder="22">
                                <x-input-error for='ssh_port' />
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">{{ __('Password') }}</label>
                                <input class="form-control" type="password" wire:model="password" placeholder="••••••••">
                                <x-input-error for='password' />
                            </div>
                        @endif

                        <!-- Interface Selectors -->
                        <div class="col-12 col-md-3">
                            <label class="form-label">{{ __('WAN interface') }}</label>
                            <input class="form-control" type="text" wire:model="wan_interface" placeholder="ether1">
                            <small class="text-muted" style="font-size: 0.75rem;">Connected to ISP uplink</small>
                            <x-input-error for='wan_interface' />
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">{{ __('LAN interface') }}</label>
                            <input class="form-control" type="text" wire:model="lan_interface" placeholder="ether2">
                            <small class="text-muted" style="font-size: 0.75rem;">Connected to APs / users</small>
                            <x-input-error for='lan_interface' />
                        </div>

                        <!-- Services Checkboxes -->
                        <div class="col-12 col-md-6">
                            <label class="form-label">{{ __('Services to provision') }}</label>
                            <div class="d-flex gap-3 pt-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="svc_hotspot" wire:model="services_hotspot">
                                    <label class="form-check-label fw-medium" for="svc_hotspot">Hotspot server</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="svc_pppoe" wire:model="services_pppoe">
                                    <label class="form-check-label fw-medium" for="svc_pppoe">PPPoE server</label>
                                </div>
                            </div>
                        </div>

                        @if ($services_hotspot)
                            <div class="col-12 col-md-6">
                                <label class="form-label">{{ __('Hotspot subnet') }}</label>
                                <input class="form-control" type="text" wire:model="hotspot_subnet" placeholder="10.5.50.0/24">
                            </div>
                        @endif

                        @if ($services_pppoe)
                            <div class="col-12 col-md-6">
                                <label class="form-label">{{ __('PPPoE pool subnet') }}</label>
                                <input class="form-control" type="text" wire:model="pppoe_pool" placeholder="10.0.0.0/24">
                            </div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <x-ui.button variant="secondary" @click="isOpen = false">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button variant="primary" type="submit">
                            <i class="bi bi-magic"></i>
                            {{ $RouterListId ? __('Update router') : __('Save & generate script') }}
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Router Cards Grid -->
        <div class="row g-3">
            @forelse ($routers as $router)
                <div class="col-12 col-sm-6 col-lg-4 col-xl-4">
                    <div class="card h-100 overflow-hidden d-flex flex-column">
                        <!-- Header -->
                        <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-start">
                            <div class="overflow-hidden">
                                <h3 class="ui-section-title mb-1 text-truncate">{{ $router->name ?: $router->router_name }}</h3>
                                <div class="d-flex align-items-center gap-1">
                                    @if ($router->vpn_ip)
                                        <x-ui.badge tone="info"><i class="bi bi-shield-shaded me-1"></i>VPN: {{ $router->vpn_ip }}</x-ui.badge>
                                    @elseif ($router->ip)
                                        <small class="text-muted"><i class="bi bi-globe me-1"></i>{{ $router->ip }}</small>
                                    @else
                                        <small class="text-muted">Awaiting bootstrap</small>
                                    @endif
                                </div>
                            </div>

                            <div>
                                @if ($router->status === 'online')
                                    <x-ui.badge tone="success"><i class="bi bi-check-circle-fill me-1"></i>Online</x-ui.badge>
                                @elseif ($router->status === 'onboarding')
                                    <x-ui.badge tone="warning"><i class="bi bi-hourglass-split me-1"></i>Pending</x-ui.badge>
                                @else
                                    <x-ui.badge tone="danger"><i class="bi bi-dash-circle-fill me-1"></i>Offline</x-ui.badge>
                                @endif
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div class="space-y-2 mb-3 small">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">{{ __('Connection') }}:</span>
                                    <span class="fw-medium text-dark text-capitalize">
                                        {{ $router->connection_type === 'wireguard' || $router->vpn_ip ? 'WireGuard tunnel' : 'Direct IP' }}
                                    </span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">{{ __('Interfaces') }}:</span>
                                    <x-ui.badge tone="neutral">
                                        WAN: {{ $router->wan_interface ?: 'ether1' }} | LAN: {{ $router->lan_interface ?: 'ether2' }}
                                    </x-ui.badge>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">{{ __('Active subscribers') }}:</span>
                                    <a href="{{ route('customers.index') }}" class="text-decoration-none">
                                        <x-ui.badge tone="success"><i class="bi bi-people-fill me-1"></i>{{ $router->user_list_count ?? 0 }}</x-ui.badge>
                                    </a>
                                </div>

                                @if ($router->last_seen_at)
                                    <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.75rem;">
                                        <span>{{ __('Last seen') }}:</span>
                                        <span>{{ $router->last_seen_at->diffForHumans() }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Actions -->
                            <div class="pt-3 border-top d-flex justify-content-between align-items-center gap-2">
                                <!-- One-Line Script Trigger -->
                                <button class="btn btn-sm btn-outline-secondary"
                                        title="{{ __('Get RouterOS bootstrap script') }}"
                                        wire:click="generateBootstrapScript({{ $router->id }})">
                                    <i class="bi bi-terminal me-1"></i>{{ __('Get script') }}
                                </button>

                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary p-1 px-2" title="{{ __('Edit') }}"
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
                <div class="col-12">
                    <x-ui.empty-state icon="bi-router" :title="__('No routers added yet')" :description="__('Add your first MikroTik router to generate the one-line bootstrap script.')">
                        <x-ui.button variant="primary" @click="isOpen = true">
                            <i class="bi bi-plus-lg"></i> {{ __('Add router now') }}
                        </x-ui.button>
                    </x-ui.empty-state>
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $routers->links() }}
        </div>
    </x-ui.card>
</div>
