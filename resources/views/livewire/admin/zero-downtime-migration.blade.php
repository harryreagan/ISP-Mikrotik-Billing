<div>
    {{-- Header --}}
    <x-ui.page-header title="Zero-Downtime Live Migration" subtitle="Migrate subscribers and vouchers from another MikroTik or billing system with 0% downtime.">
        <x-slot:actions>
            <a href="{{ route('admin.routers') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-hdd-network me-1"></i> {{ __('View Routers') }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Zero-Downtime Guarantee Banner --}}
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3 rounded-4" style="background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%);">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-shield-check"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-dark">{{ __('100% Zero-Downtime Guarantee') }}</h6>
                <small class="text-muted">{{ __('Existing PPPoE tunnels and Hotspot sessions are never kicked or dropped during migration. Customers continue browsing without a single dropped packet.') }}</small>
            </div>
        </div>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill font-monospace">
            <i class="bi bi-check2-circle me-1"></i>{{ __('ACTIVE SESSION PRESERVATION: ON') }}
        </span>
    </div>

    {{-- Navigation Tabs --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link {{ $activeTab === 'live' ? 'active fw-bold text-primary border-bottom border-primary border-3' : 'text-muted' }}" 
                            wire:click="$set('activeTab', 'live')" type="button">
                        <i class="bi bi-router me-1"></i> {{ __('Live MikroTik API Discovery') }}
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link {{ $activeTab === 'file' ? 'active fw-bold text-primary border-bottom border-primary border-3' : 'text-muted' }}" 
                            wire:click="$set('activeTab', 'file')" type="button">
                        <i class="bi bi-file-earmark-code me-1"></i> {{ __('Export Script / CSV Import (.rsc)') }}
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            {{-- TAB 1: LIVE MIKROTIK SCAN & MIGRATE --}}
            @if($activeTab === 'live')
                <div class="row g-4">
                    <div class="col-12 col-lg-5">
                        <div class="p-3 bg-light rounded-4 border">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="bi bi-sliders text-primary me-1"></i> {{ __('Source Router Credentials') }}
                            </h6>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">{{ __('Select Existing Router or Enter Remote') }}</label>
                                <select class="form-select form-select-sm" wire:model.live="selectedRouterId">
                                    <option value="">{{ __('-- Enter Custom Remote Router --') }}</option>
                                    @foreach($routers as $r)
                                        <option value="{{ $r->id }}">
                                            {{ $r->name ?: $r->router_name }} ({{ $r->vpn_ip ?: $r->ip }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-8">
                                    <label class="form-label small fw-semibold text-secondary">{{ __('Host IP / VPN IP') }}</label>
                                    <input type="text" class="form-control form-control-sm font-monospace" wire:model="customHost" placeholder="10.10.0.X or 192.168.88.1">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small fw-semibold text-secondary">{{ __('API Port') }}</label>
                                    <input type="number" class="form-control form-control-sm font-monospace" wire:model="customPort" placeholder="8728">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-secondary">{{ __('API Username') }}</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="customUser" placeholder="admin">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-secondary">{{ __('API Password') }}</label>
                                    <input type="password" class="form-control form-control-sm" wire:model="customPassword">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">{{ __('Target ISP Tenant (Isolation Scope)') }}</label>
                                <select class="form-select form-select-sm" wire:model="targetTenantId">
                                    @foreach($tenants as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }} (ID #{{ $t->id }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="button" class="btn btn-primary w-100 btn-sm shadow-sm" wire:click="scanRouter" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="scanRouter">
                                    <i class="bi bi-search me-1"></i> {{ __('Scan & Discover Live Accounts') }}
                                </span>
                                <span wire:loading wire:target="scanRouter">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>{{ __('Connecting to Router...') }}
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- Right Column: Discovery & Migration Execution --}}
                    <div class="col-12 col-lg-7">
                        @if($scanError)
                            <div class="alert alert-danger border-0 rounded-4 p-3 shadow-sm mb-3">
                                <i class="bi bi-exclamation-octagon-fill me-2"></i> {{ $scanError }}
                            </div>
                        @endif

                        @if($migrationResults)
                            <div class="alert alert-success border-0 rounded-4 p-3 shadow-sm mb-3">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                                    <h6 class="fw-bold mb-0 text-success">{{ __('Zero-Downtime Migration Complete!') }}</h6>
                                </div>
                                <p class="small mb-2 text-dark">{{ $migrationResults['message'] }}</p>
                                <div class="row g-2 text-center small">
                                    <div class="col-4">
                                        <div class="bg-white p-2 rounded-3 border">
                                            <div class="text-muted">{{ __('PPPoE Migrated') }}</div>
                                            <div class="fw-bold fs-6 text-primary">{{ $migrationResults['migrated_pppoe'] }}</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-white p-2 rounded-3 border">
                                            <div class="text-muted">{{ __('Hotspot Migrated') }}</div>
                                            <div class="fw-bold fs-6 text-info">{{ $migrationResults['migrated_hotspot'] }}</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-white p-2 rounded-3 border">
                                            <div class="text-muted">{{ __('Plans Auto-Created') }}</div>
                                            <div class="fw-bold fs-6 text-success">{{ $migrationResults['created_packages'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($scanResults)
                            <div class="card border border-2 border-primary-subtle rounded-4 p-3 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold text-dark mb-0">
                                        <i class="bi bi-diagram-3 text-primary me-1"></i> {{ __('Discovery Results for :host', ['host' => $scanResults['host']]) }}
                                    </h6>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                        {{ __('Ready for Zero-Downtime Import') }}
                                    </span>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6 col-md-3">
                                        <div class="p-2.5 bg-light rounded-3 text-center border">
                                            <small class="text-muted d-block">{{ __('PPPoE Secrets') }}</small>
                                            <span class="fw-bold fs-5 text-dark">{{ $scanResults['ppp_secrets_count'] }}</span>
                                            <span class="badge bg-success-subtle text-success d-block mt-1 font-monospace" style="font-size: 10px;">
                                                {{ $scanResults['ppp_active_count'] }} online now
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-2.5 bg-light rounded-3 text-center border">
                                            <small class="text-muted d-block">{{ __('Hotspot Users') }}</small>
                                            <span class="fw-bold fs-5 text-dark">{{ $scanResults['hs_users_count'] }}</span>
                                            <span class="badge bg-info-subtle text-info d-block mt-1 font-monospace" style="font-size: 10px;">
                                                {{ $scanResults['hs_active_count'] }} online now
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-2.5 bg-light rounded-3 text-center border">
                                            <small class="text-muted d-block">{{ __('Profiles Found') }}</small>
                                            <span class="fw-bold fs-5 text-primary">{{ count($scanResults['ppp_profiles']) }}</span>
                                            <small class="text-muted d-block mt-1" style="font-size: 10px;">Auto-mapped</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-2.5 bg-light rounded-3 text-center border">
                                            <small class="text-muted d-block">{{ __('IP Bindings') }}</small>
                                            <span class="fw-bold fs-5 text-secondary">{{ $scanResults['hs_bindings_count'] }}</span>
                                            <small class="text-muted d-block mt-1" style="font-size: 10px;">Bypassed</small>
                                        </div>
                                    </div>
                                </div>

                                {{-- Sample table --}}
                                @if(!empty($scanResults['sample_ppp']))
                                    <div class="mb-3">
                                        <small class="fw-semibold text-secondary d-block mb-1">{{ __('Sample PPPoE Accounts Discovered (First 10):') }}</small>
                                        <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11px;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Username</th>
                                                        <th>Profile</th>
                                                        <th>Remote IP</th>
                                                        <th>Live Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($scanResults['sample_ppp'] as $sp)
                                                        @php $isLive = in_array($sp['name'] ?? '', $scanResults['active_ppp_users']); @endphp
                                                        <tr>
                                                            <td class="font-monospace fw-semibold">{{ $sp['name'] ?? '' }}</td>
                                                            <td><span class="badge bg-light text-dark border">{{ $sp['profile'] ?? 'default' }}</span></td>
                                                            <td class="font-monospace text-muted">{{ $sp['remote-address'] ?? 'dynamic' }}</td>
                                                            <td>
                                                                @if($isLive)
                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                                        <i class="bi bi-circle-fill me-1" style="font-size: 6px;"></i>Online
                                                                    </span>
                                                                @else
                                                                    <span class="badge bg-secondary-subtle text-secondary">Offline</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif

                                <div class="border-top pt-3 d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>{{ __('Clicking below will import all accounts into HSmart. No users will be disconnected.') }}
                                    </small>
                                    <button type="button" class="btn btn-success btn-sm px-3 shadow-sm" wire:click="executeMigration" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="executeMigration">
                                            <i class="bi bi-lightning-charge-fill me-1"></i> {{ __('Start Zero-Downtime Migration') }}
                                        </span>
                                        <span wire:loading wire:target="executeMigration">
                                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>{{ __('Migrating Accounts...') }}
                                        </span>
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="p-5 text-center bg-light rounded-4 border border-dashed">
                                <i class="bi bi-cpu fs-1 text-muted mb-2 d-block"></i>
                                <h6 class="fw-semibold text-secondary mb-1">{{ __('No Router Scanned Yet') }}</h6>
                                <p class="text-muted small mb-0">{{ __('Select an online router or enter remote MikroTik API credentials on the left, then click "Scan & Discover".') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- TAB 2: FILE IMPORT (.RSC / .CSV) --}}
            @if($activeTab === 'file')
                <div class="row g-4">
                    <div class="col-12 col-lg-6">
                        <div class="p-4 bg-light rounded-4 border">
                            <h6 class="fw-bold text-dark mb-2">
                                <i class="bi bi-file-earmark-arrow-up text-primary me-1"></i> {{ __('Import MikroTik /export (.rsc) or Billing CSV') }}
                            </h6>
                            <p class="small text-muted mb-3">
                                {{ __('On your old MikroTik, run "/export file=migration" and upload the .rsc file here. HSmart will parse all /ppp secret and /ip hotspot user entries and import them without disrupting active users.') }}
                            </p>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">{{ __('Assign to Router') }}</label>
                                <select class="form-select form-select-sm" wire:model="selectedRouterId">
                                    @foreach($routers as $r)
                                        <option value="{{ $r->id }}">{{ $r->name ?: $r->router_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">{{ __('Target Tenant') }}</label>
                                <select class="form-select form-select-sm" wire:model="targetTenantId">
                                    @foreach($tenants as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-secondary">{{ __('Upload Script (.rsc, .txt, .csv)') }}</label>
                                <input type="file" class="form-control form-control-sm" wire:model="rscFile">
                                @error('rscFile') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" wire:click="importFile" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="importFile">
                                    <i class="bi bi-upload me-1"></i> {{ __('Import File with Zero Downtime') }}
                                </span>
                                <span wire:loading wire:target="importFile">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>{{ __('Importing...') }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="p-4 bg-white rounded-4 border shadow-sm">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="bi bi-info-circle text-info me-1"></i> {{ __('How Zero-Downtime Migration Works') }}
                            </h6>
                            <ol class="small text-secondary ps-3 mb-0" style="line-height: 1.8;">
                                <li><strong>{{ __('Active Connection Preservation:') }}</strong> {{ __('MikroTik keeps live sessions inside memory (/ppp active and /ip hotspot active). Importing credentials never resets those sessions.') }}</li>
                                <li><strong>{{ __('Seamless Transition:') }}</strong> {{ __('When a client renews or reconnects, HSmart responds instantly with their package speeds and rate-limits.') }}</li>
                                <li><strong>{{ __('Password Continuity:') }}</strong> {{ __('Client passwords are preserved exactly as configured on the previous system, so users don\'t need to reconfigure their routers or phones.') }}</li>
                                <li><strong>{{ __('Lifecycle Takeover:') }}</strong> {{ __('Once imported, HSmart automatically handles STK push renewals, reminders (3d, 1d, today), and automated session expirations.') }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

