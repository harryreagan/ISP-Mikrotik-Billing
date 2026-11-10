<div class="container-fluid py-4" style="max-width: 1400px;">
    {{-- Header & Action Bar --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-box-seam text-primary me-2"></i>Internet &amp; WiFi Packages
            </h2>
            <p class="text-muted small mb-0">Configure Hotspot and PPPoE plans, speeds, prices and auto-sync router profiles.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" wire:click="openCreateModal" class="btn btn-primary px-4 py-2 fw-bold shadow-sm d-flex align-items-center">
                <i class="bi bi-plus-circle-fill me-2 fs-5"></i>
                <span>Create Package</span>
            </button>
        </div>
    </div>

    {{-- Feedback Alerts --}}
    @if($feedbackMessage)
        <div class="alert alert-{{ $feedbackType === 'error' ? 'danger' : ($feedbackType === 'info' ? 'info' : 'success') }} alert-dismissible fade show shadow-sm border-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi {{ $feedbackType === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' }} fs-4 me-2"></i>
                <div>{{ $feedbackMessage }}</div>
            </div>
            <button type="button" wire:click="$set('feedbackMessage', null)" class="btn-close" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter Bar & Search --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 14px;">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center justify-content-between">
                {{-- Filter Chips --}}
                <div class="col-12 col-md-auto">
                    <div class="btn-group" role="group">
                        <button type="button" 
                                wire:click="setTab('all')" 
                                class="btn btn-sm {{ $tab === 'all' ? 'btn-primary fw-bold' : 'btn-outline-secondary' }} px-3 py-2">
                            All Packages
                        </button>
                        <button type="button" 
                                wire:click="setTab('hotspot')" 
                                class="btn btn-sm {{ $tab === 'hotspot' ? 'btn-primary fw-bold' : 'btn-outline-secondary' }} px-3 py-2">
                            <i class="bi bi-wifi me-1"></i> Hotspot
                        </button>
                        <button type="button" 
                                wire:click="setTab('pppoe')" 
                                class="btn btn-sm {{ $tab === 'pppoe' ? 'btn-primary fw-bold' : 'btn-outline-secondary' }} px-3 py-2">
                            <i class="bi bi-hdd-network me-1"></i> PPPoE Fiber
                        </button>
                    </div>
                </div>

                {{-- Search Box --}}
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search" 
                               class="form-control bg-light border-start-0" 
                               placeholder="Search packages by name or speed...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Packages Cards Grid --}}
    @if($packages->isEmpty())
        <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 16px;">
            <div class="card-body">
                <div class="d-inline-flex p-3 rounded-circle bg-light text-primary mb-3">
                    <i class="bi bi-box2 fs-1"></i>
                </div>
                <h4 class="fw-bold text-dark">No Packages Configured Yet</h4>
                <p class="text-muted small mx-auto" style="max-width: 450px;">
                    Create your first Hotspot or PPPoE bandwidth package to start selling internet vouchers and automated billing.
                </p>
                <button type="button" wire:click="openCreateModal" class="btn btn-primary px-4 py-2 fw-bold">
                    <i class="bi bi-plus-lg me-1"></i> Create First Package
                </button>
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($packages as $pkg)
                <div class="col-12 col-md-6 col-xl-4" wire:key="pkg-card-{{ $pkg->id }}">
                    <div class="card border-0 shadow-sm h-100 position-relative" style="border-radius: 16px; transition: transform 0.2s;">
                        {{-- Header Banner --}}
                        <div class="card-header border-0 bg-transparent pt-4 px-4 pb-0 d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge {{ $pkg->type === 'pppoe' ? 'bg-indigo text-white' : 'bg-warning text-dark' }} px-3 py-1 rounded-pill fw-bold text-uppercase" style="{{ $pkg->type === 'pppoe' ? 'background:#4F46E5;' : '' }}">
                                    <i class="bi {{ $pkg->type === 'pppoe' ? 'bi-hdd-network' : 'bi-wifi' }} me-1"></i>
                                    {{ $pkg->type === 'pppoe' ? 'PPPoE Fiber' : 'Hotspot' }}
                                </span>
                                @if(!$pkg->is_active)
                                    <span class="badge bg-secondary text-white rounded-pill ms-1">Inactive</span>
                                @endif
                                @if($pkg->show_public)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-1">Public</span>
                                @endif
                            </div>

                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px;">
                                    <li>
                                        <button class="dropdown-item" wire:click="openEditModal({{ $pkg->id }})">
                                            <i class="bi bi-pencil me-2 text-primary"></i> Edit Package
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" wire:click="retrySync({{ $pkg->id }})">
                                            <i class="bi bi-arrow-repeat me-2 text-info"></i> Sync to Routers
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" wire:click="duplicatePackage({{ $pkg->id }})">
                                            <i class="bi bi-copy me-2 text-warning"></i> Duplicate
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" wire:click="toggleActive({{ $pkg->id }})">
                                            <i class="bi bi-power me-2 {{ $pkg->is_active ? 'text-secondary' : 'text-success' }}"></i>
                                            {{ $pkg->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" wire:click="deletePackage({{ $pkg->id }})" wire:confirm="Are you sure you want to delete this package?">
                                            <i class="bi bi-trash me-2"></i> Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- Card Body --}}
                        <div class="card-body p-4">
                            <h4 class="fw-bold text-dark mb-1">{{ $pkg->package }}</h4>
                            <div class="display-6 fw-bold text-dark my-2" style="font-size: 1.8rem;">
                                <span class="fs-5 text-muted">KES</span> {{ number_format($pkg->price, 0) }}
                                <span class="fs-6 text-muted fw-normal">/ {{ $pkg->formatted_validity }}</span>
                            </div>

                            @if($pkg->description)
                                <p class="text-muted small mb-3">{{ Str::limit($pkg->description, 80) }}</p>
                            @endif

                            {{-- Specs Pills --}}
                            <div class="row g-2 mb-3 pt-2">
                                <div class="col-6">
                                    <div class="p-2 rounded bg-light small">
                                        <div class="text-muted" style="font-size:0.75rem;">Speed Limit</div>
                                        <div class="fw-bold text-dark"><i class="bi bi-speedometer2 text-primary me-1"></i>{{ $pkg->formatted_speed }}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded bg-light small">
                                        <div class="text-muted" style="font-size:0.75rem;">Data Cap</div>
                                        <div class="fw-bold text-dark">
                                            <i class="bi bi-pie-chart text-success me-1"></i>
                                            {{ $pkg->data_limit_mb ? ($pkg->data_limit_mb >= 1024 ? round($pkg->data_limit_mb/1024, 1) . ' GB' : $pkg->data_limit_mb . ' MB') : 'Unlimited' }}
                                        </div>
                                    </div>
                                </div>
                                @if($pkg->type === 'hotspot')
                                    <div class="col-12">
                                        <div class="p-2 rounded bg-light small d-flex justify-content-between align-items-center">
                                            <span class="text-muted" style="font-size:0.75rem;">Shared Devices:</span>
                                            <span class="fw-bold text-dark"><i class="bi bi-phone text-info me-1"></i>{{ $pkg->shared_users }} {{ Str::plural('device', $pkg->shared_users) }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Linked Routers & Sync Status --}}
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between align-items-center small mb-2">
                                    <span class="text-muted fw-semibold">Linked Routers:</span>
                                    <button wire:click="retrySync({{ $pkg->id }})" class="btn btn-link btn-sm p-0 text-decoration-none" title="Sync router profiles">
                                        <i class="bi bi-arrow-repeat me-1"></i>Sync
                                    </button>
                                </div>

                                @if($pkg->routers->isEmpty())
                                    <span class="badge bg-light text-muted border">All tenant routers</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($pkg->routers as $rtr)
                                            @php
                                                $sync = $rtr->pivot->sync_status ?? 'pending';
                                            @endphp
                                            <span class="badge rounded-pill {{ $sync === 'synced' ? 'bg-success-subtle text-success border border-success' : ($sync === 'failed' ? 'bg-danger-subtle text-danger border border-danger' : 'bg-warning-subtle text-warning border border-warning') }}" 
                                                  title="{{ $rtr->pivot->sync_error ?: 'Profile active on router' }}">
                                                <i class="bi {{ $sync === 'synced' ? 'bi-check2' : ($sync === 'failed' ? 'bi-x' : 'bi-clock') }} me-1"></i>
                                                {{ $rtr->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Card Footer --}}
                        <div class="card-footer bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center" style="border-radius: 0 0 16px 16px;">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       wire:click="toggleActive({{ $pkg->id }})" 
                                       {{ $pkg->is_active ? 'checked' : '' }} 
                                       id="activeSwitch{{ $pkg->id }}">
                                <label class="form-check-label small fw-semibold text-muted" for="activeSwitch{{ $pkg->id }}">
                                    {{ $pkg->is_active ? 'Active' : 'Inactive' }}
                                </label>
                            </div>

                            <button type="button" wire:click="openEditModal({{ $pkg->id }})" class="btn btn-outline-primary btn-sm px-3 fw-bold">
                                Edit
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $packages->links() }}
        </div>
    @endif

    {{-- Create / Edit Package Modal --}}
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
                    <div class="modal-header border-0 bg-primary text-white p-4">
                        <div>
                            <h4 class="modal-title fw-bold mb-1">
                                {{ $editingId ? 'Edit Package' : 'Create Internet Package' }}
                            </h4>
                            <p class="small text-white-50 mb-0">Define plan parameters and auto-sync router profiles.</p>
                        </div>
                        <button type="button" wire:click="$set('showModal', false)" class="btn-close btn-close-white" aria-label="Close"></button>
                    </div>

                    <form wire:submit.prevent="savePackage">
                        <div class="modal-body p-4">
                            {{-- Package Type Selector --}}
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Package Type</label>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="card p-3 border {{ $type === 'hotspot' ? 'border-primary bg-primary-subtle' : 'bg-light' }}" 
                                             style="cursor: pointer; border-radius: 12px;"
                                             wire:click="$set('type', 'hotspot')">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-wifi fs-3 text-warning me-3"></i>
                                                <div>
                                                    <div class="fw-bold text-dark">Hotspot WiFi</div>
                                                    <div class="text-muted small">Captive portal, shared devices</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card p-3 border {{ $type === 'pppoe' ? 'border-primary bg-primary-subtle' : 'bg-light' }}" 
                                             style="cursor: pointer; border-radius: 12px;"
                                             wire:click="$set('type', 'pppoe')">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-hdd-network fs-3 text-primary me-3"></i>
                                                <div>
                                                    <div class="fw-bold text-dark">PPPoE Fiber</div>
                                                    <div class="text-muted small">Dedicated home/business link</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                {{-- Package Name --}}
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-bold text-dark">Package Name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="name" class="form-control form-control-lg @error('name') is-invalid @enderror" placeholder="e.g. 10 Mbps Unlimited (24 Hrs)">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                {{-- Price (KES) --}}
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold text-dark">Price (KES) <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light fw-bold">KES</span>
                                        <input type="number" step="0.5" wire:model="price" class="form-control @error('price') is-invalid @enderror">
                                    </div>
                                    @error('price') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- Speeds: Download & Upload --}}
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark">Download Speed <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" wire:model="speed_download" class="form-control @error('speed_download') is-invalid @enderror">
                                        <select wire:model="speed_download_unit" class="form-select" style="max-width: 100px;">
                                            <option value="Mbps">Mbps</option>
                                            <option value="Kbps">Kbps</option>
                                        </select>
                                    </div>
                                    @error('speed_download') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark">Upload Speed <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" wire:model="speed_upload" class="form-control @error('speed_upload') is-invalid @enderror">
                                        <select wire:model="speed_upload_unit" class="form-select" style="max-width: 100px;">
                                            <option value="Mbps">Mbps</option>
                                            <option value="Kbps">Kbps</option>
                                        </select>
                                    </div>
                                    @error('speed_upload') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- Validity Duration --}}
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark">Validity Duration <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" min="1" wire:model="validity_value" class="form-control @error('validity_value') is-invalid @enderror">
                                        <select wire:model="validity_unit" class="form-select">
                                            <option value="minutes">Minutes</option>
                                            <option value="hours">Hours</option>
                                            <option value="days">Days</option>
                                            <option value="months">Months (30 Days)</option>
                                        </select>
                                    </div>
                                    @error('validity_value') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- Shared Devices or Burst Speed --}}
                                @if($type === 'hotspot')
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold text-dark">Simultaneous Devices</label>
                                        <input type="number" min="1" max="50" wire:model="shared_users" class="form-control" placeholder="1">
                                        <div class="form-text">How many phones/laptops can use one voucher code.</div>
                                    </div>
                                @else
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold text-dark">Burst Limit (Optional)</label>
                                        <input type="text" wire:model="burst_speed" class="form-control" placeholder="e.g. 20M/20M 10M/10M 16/16">
                                        <div class="form-text">MikroTik burst-limit / threshold / burst-time.</div>
                                    </div>
                                @endif

                                {{-- Data Limit --}}
                                <div class="col-12">
                                    <div class="card p-3 bg-light border-0" style="border-radius: 12px;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-bold text-dark mb-0">Data Limit / Bandwidth Cap</label>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" wire:model.live="unlimited_data" id="unlimitedSwitch">
                                                <label class="form-check-label fw-semibold text-primary" for="unlimitedSwitch">Unlimited Data</label>
                                            </div>
                                        </div>
                                        @if(!$unlimited_data)
                                            <div class="input-group mt-2">
                                                <input type="number" wire:model="data_limit_mb" class="form-control" placeholder="e.g. 5000">
                                                <span class="input-group-text bg-white">MB (Megabytes)</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Router Multi-Select grouped by location --}}
                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark">Link to Routers</label>
                                    <div class="card p-3 border" style="max-height: 180px; overflow-y: auto; border-radius: 12px;">
                                        <div class="form-check mb-2 pb-2 border-bottom">
                                            <input class="form-check-input" type="checkbox" value="all" wire:model="selected_routers" id="router_all">
                                            <label class="form-check-label fw-bold text-primary" for="router_all">
                                                ★ All Routers (Synchronize everywhere)
                                            </label>
                                        </div>

                                        @foreach($allRouters as $location => $rGroup)
                                            <div class="fw-bold small text-muted text-uppercase mt-2 mb-1">{{ $location }}</div>
                                            @foreach($rGroup as $r)
                                                <div class="form-check ms-2">
                                                    <input class="form-check-input" type="checkbox" value="{{ $r->id }}" wire:model="selected_routers" id="router_{{ $r->id }}">
                                                    <label class="form-check-label small" for="router_{{ $r->id }}">
                                                        <strong>{{ $r->name }}</strong> ({{ $r->ip ?: 'WireGuard' }})
                                                    </label>
                                                </div>
                                            @endforeach
                                        @endforeach
                                    </div>
                                    <div class="form-text">Decides where this plan is sold and which router profile is created.</div>
                                </div>

                                {{-- Description --}}
                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark">Description &amp; Highlights</label>
                                    <textarea wire:model="description" class="form-control" rows="2" placeholder="e.g. High-speed streaming, YouTube 1080p, no buffering"></textarea>
                                </div>

                                {{-- Toggles: Show Public & Sort Order --}}
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch pt-2">
                                        <input class="form-check-input" type="checkbox" wire:model="show_public" id="showPublicSwitch">
                                        <label class="form-check-label fw-semibold text-dark" for="showPublicSwitch">
                                            Show on Captive Portal &amp; Public Page
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Sort Order</span>
                                        <input type="number" wire:model="sort_order" class="form-control" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer border-0 p-4 bg-light">
                            <button type="button" wire:click="$set('showModal', false)" class="btn btn-outline-secondary px-4">Cancel</button>
                            <button type="submit" class="btn btn-primary px-5 fw-bold">
                                <i class="bi bi-check2-circle me-1"></i> Save &amp; Sync to Routers
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

