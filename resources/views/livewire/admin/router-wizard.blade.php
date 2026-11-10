<div class="container-fluid py-4">
    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-magic text-warning me-2"></i>MikroTik Router Onboarding Wizard</h3>
            <p class="text-muted mb-0">Automatic interface detection, role assignment, and zero-touch RouterOS provisioning.</p>
        </div>
        <div>
            <a href="{{ route('mikrotik-sync') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Router List
            </a>
        </div>
    </div>

    {{-- Step Tracker --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <div class="row text-center g-2">
                <div class="col-3">
                    <div class="p-2 rounded {{ $step >= 1 ? 'bg-primary text-white' : 'bg-light text-muted' }} fw-bold">
                        <i class="bi bi-hdd-network me-1"></i>1. Connection
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 rounded {{ $step >= 2 ? 'bg-primary text-white' : 'bg-light text-muted' }} fw-bold">
                        <i class="bi bi-cpu me-1"></i>2. Interfaces
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 rounded {{ $step >= 3 ? 'bg-primary text-white' : 'bg-light text-muted' }} fw-bold">
                        <i class="bi bi-diagram-3 me-1"></i>3. Role Mapping
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 rounded {{ $step >= 4 ? 'bg-primary text-white' : 'bg-light text-muted' }} fw-bold">
                        <i class="bi bi-check-circle me-1"></i>4. Auto-Config
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Step 1: Connection & Test --}}
    @if($step === 1)
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-link-45deg text-primary me-2"></i>Step 1: Router Connection & Discovery Mode</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Router Name / Label <span class="text-danger">*</span></label>
                        <input type="text" wire:model="router_name" class="form-control" placeholder="e.g. Town-Center-Node">
                        @error('router_name') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Connection Architecture</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" wire:click="$set('connection_mode', 'wireguard')" class="btn {{ $connection_mode === 'wireguard' ? 'btn-primary' : 'btn-outline-primary' }}">
                                <i class="bi bi-shield-lock me-1"></i>WireGuard Cloud Tunnel (Recommended for NAT)
                            </button>
                            <button type="button" wire:click="$set('connection_mode', 'direct')" class="btn {{ $connection_mode === 'direct' ? 'btn-primary' : 'btn-outline-primary' }}">
                                <i class="bi bi-globe me-1"></i>Direct Public IP / VPN
                            </button>
                        </div>
                    </div>

                    @if($connection_mode === 'direct')
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Router IP / Host <span class="text-danger">*</span></label>
                            <input type="text" wire:model="host" class="form-control" placeholder="e.g. 197.248.100.5">
                            @error('host') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">API Port</label>
                            <input type="number" wire:model="api_port" class="form-control" placeholder="8728">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">API Username <span class="text-danger">*</span></label>
                            <input type="text" wire:model="username" class="form-control" placeholder="admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">API Password</label>
                            <input type="password" wire:model="password" class="form-control" placeholder="••••••••">
                        </div>
                    @else
                        <div class="col-12">
                            <div class="alert alert-info border-0 mb-0 d-flex align-items-center">
                                <i class="bi bi-info-circle-fill fs-3 me-3 text-info"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Zero-Configuration WireGuard Cloud Tunnel</h6>
                                    <p class="mb-0 small">Perfect for routers behind CGNAT, Starlink, Safaricom LTE, or dynamic IPs. HSmart will automatically provision a secure WireGuard peer and inject API access directly over the tunnel.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="col-12 mt-4 d-flex justify-content-between align-items-center">
                        <button type="button" wire:click="testConnection" class="btn btn-outline-primary">
                            <i class="bi bi-broadcast me-1"></i>Test Connection
                        </button>

                        <button type="button" wire:click="goToStep2" class="btn btn-primary px-4">
                            Next: Interface Detection <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>

                    @if($connection_tested)
                        <div class="col-12 mt-3">
                            <div class="alert {{ $test_status === 'success' ? 'alert-success' : 'alert-danger' }} mb-0">
                                <i class="bi {{ $test_status === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }} me-2"></i>
                                {{ $test_message }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Step 2: Detected Interfaces & Hardware --}}
    @if($step === 2)
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-cpu text-primary me-2"></i>Step 2: Detected Hardware & Interfaces</h5>
                <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                    <i class="bi bi-check2-circle me-1"></i>Hardware: {{ $model }} ({{ $ros_version }})
                </span>
            </div>
            <div class="card-body p-4">
                {{-- Detected configuration pills --}}
                <div class="mb-4">
                    <h6 class="fw-bold text-muted small text-uppercase mb-2">Detected Existing Configuration</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @if(!empty($existing_config['hotspot_servers']))
                            <span class="badge bg-warning-subtle text-dark border p-2"><i class="bi bi-wifi me-1"></i>Hotspot: {{ implode(', ', $existing_config['hotspot_servers']) }}</span>
                        @endif
                        @if(!empty($existing_config['pppoe_servers']))
                            <span class="badge bg-info-subtle text-dark border p-2"><i class="bi bi-globe me-1"></i>PPPoE: {{ implode(', ', $existing_config['pppoe_servers']) }}</span>
                        @endif
                        @if(!empty($existing_config['ip_pools']))
                            <span class="badge bg-secondary-subtle text-dark border p-2"><i class="bi bi-diagram-2 me-1"></i>IP Pools: {{ implode(', ', $existing_config['ip_pools']) }}</span>
                        @endif
                        @if(empty($existing_config['hotspot_servers']) && empty($existing_config['pppoe_servers']))
                            <span class="badge bg-light text-muted border p-2"><i class="bi bi-shield-check me-1"></i>Clean Router: No conflicting hotspot/pppoe servers found.</span>
                        @endif
                    </div>
                </div>

                {{-- Interface Table --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-light">
                            <tr>
                                <th>Interface Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>MAC Address</th>
                                <th>Comment / Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($interfaces as $if)
                                <tr>
                                    <td class="fw-bold text-primary">{{ $if['name'] }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ strtoupper($if['type']) }}</span></td>
                                    <td>
                                        <span class="badge {{ $if['running'] ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $if['running'] ? 'Running' : 'Down / Disabled' }}
                                        </span>
                                    </td>
                                    <td><code>{{ $if['mac'] ?: 'N/A' }}</code></td>
                                    <td class="text-muted">{{ $if['comment'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted">No interfaces detected.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" wire:click="$set('step', 1)" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </button>
                    <button type="button" wire:click="goToStep3" class="btn btn-primary px-4">
                        Next: Role Mapping <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Step 3: Role Mapping --}}
    @if($step === 3)
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-diagram-3 text-primary me-2"></i>Step 3: Interface Role Assignment</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">WAN / Internet Uplink Interface <span class="text-danger">*</span></label>
                        <select wire:model="wan_interface" class="form-select">
                            @foreach($interfaces as $if)
                                <option value="{{ $if['name'] }}">{{ $if['name'] }} ({{ $if['type'] }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Interface connected to your ISP uplink or fiber modem.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">LAN / Bridge Interface</label>
                        <select wire:model="lan_interface" class="form-select">
                            @foreach($interfaces as $if)
                                <option value="{{ $if['name'] }}">{{ $if['name'] }} ({{ $if['type'] }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Master bridge or switch interface for local network.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hotspot Distribution Interface</label>
                        <select wire:model="hotspot_interfaces" class="form-select">
                            @foreach($interfaces as $if)
                                <option value="{{ $if['name'] }}">{{ $if['name'] }} ({{ $if['type'] }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Where WiFi Access Points are plugged in for captive portal.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">PPPoE Server Interface</label>
                        <select wire:model="pppoe_interfaces" class="form-select">
                            @foreach($interfaces as $if)
                                <option value="{{ $if['name'] }}">{{ $if['name'] }} ({{ $if['type'] }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Where client CPEs, ONUs, or switches for home internet connect.</small>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" wire:click="$set('step', 2)" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </button>
                    <button type="button" wire:click="goToStep4" class="btn btn-primary px-4">
                        Next: Auto-Configuration <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Step 4: Mode & Final Auto-Configure --}}
    @if($step === 4)
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-check-circle text-primary me-2"></i>Step 4: Provisioning & Auto-Configuration</h5>
            </div>
            <div class="card-body p-4">
                @if(!$is_finished)
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Services to Activate</label>
                            <select wire:model="service_mode" class="form-select">
                                <option value="both">Both Hotspot & PPPoE Server</option>
                                <option value="hotspot">Hotspot Only (Captive Portal & Vouchers)</option>
                                <option value="pppoe">PPPoE Server Only (Fixed Bandwidth Subscribers)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Configuration Strategy</label>
                            <select wire:model="config_action" class="form-select">
                                <option value="auto">Auto-Configure (Generate Pools, NAT, RADIUS, Firewall)</option>
                                <option value="existing">Use Existing Router Rules (Sync Only)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Hotspot DHCP Subnet</label>
                            <input type="text" wire:model="hotspot_subnet" class="form-control" placeholder="10.5.50.0/24">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">PPPoE Subscriber Pool</label>
                            <input type="text" wire:model="pppoe_pool" class="form-control" placeholder="10.0.0.0/24">
                        </div>

                        <div class="col-12 mt-4">
                            <div class="card bg-light border p-3">
                                <h6 class="fw-bold mb-2">Summary of Rules to be Applied:</h6>
                                <ul class="mb-0 small text-muted">
                                    <li>Automated WireGuard VPN Tunnel setup (RouterOS v7) with persistent keepalive.</li>
                                    <li>RADIUS Authentication & Accounting client pointed to HSmart Cloud FreeRADIUS.</li>
                                    <li>Walled Garden rules for M-Pesa STK push and captive portal domains.</li>
                                    <li>Firewall Input filter rules allowing secure API and CoA disconnects.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-between mt-4">
                            <button type="button" wire:click="$set('step', 3)" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </button>
                            <button type="button" wire:click="finalizeRouter" class="btn btn-success btn-lg px-4">
                                <i class="bi bi-lightning-charge-fill me-1"></i>Complete & Generate Setup Script
                            </button>
                        </div>
                    </div>
                @else
                    {{-- Provisioning Success Modal / View --}}
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3.5rem;"></i>
                        </div>
                        <h4 class="fw-bold">Router Provisioned Successfully!</h4>
                        <p class="text-muted">Copy the 1-click terminal command below and paste it into your MikroTik Winbox Terminal.</p>

                        <div class="card bg-dark text-start p-3 my-4 mx-auto" style="max-width: 850px;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-warning small fw-bold"><i class="bi bi-terminal me-1"></i>RouterOS Terminal Command</span>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ addslashes($provision_command) }}'); alert('Command copied to clipboard!');" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-clipboard me-1"></i>Copy Command
                                </button>
                            </div>
                            <code class="text-light text-break" style="font-size: 0.9rem;">
                                {{ $provision_command }}
                            </code>
                        </div>

                        <div class="d-flex justify-content-center gap-3">
                            <a href="{{ route('mikrotik-sync') }}" class="btn btn-primary px-4">
                                <i class="bi bi-hdd-network me-1"></i>View in Router List
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

