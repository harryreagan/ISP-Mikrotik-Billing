<div class="ui-page-wrap">
    {{-- Header & Action Bar --}}
    <x-ui.page-header title="Internet & WiFi Packages" subtitle="Configure Hotspot and PPPoE plans, speeds, prices and auto-sync router profiles.">
        <x-slot:actions>
            <button type="button" wire:click="openCreateModal" class="btn btn-primary d-flex align-items-center">
                <i class="bi bi-plus-lg me-1"></i>
                <span>Create Package</span>
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Feedback Alerts --}}
    @if($feedbackMessage)
        <div class="alert alert-{{ $feedbackType === 'error' ? 'danger' : ($feedbackType === 'info' ? 'info' : 'success') }} alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi {{ $feedbackType === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' }} fs-5 me-2"></i>
                <div>{{ $feedbackMessage }}</div>
            </div>
            <button type="button" wire:click="$set('feedbackMessage', null)" class="btn-close" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter Bar & Search --}}
    <div class="card">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center justify-content-between">
                {{-- Filter Chips --}}
                <div class="col-12 col-md-auto">
                    <div class="btn-group" role="group">
                        <button type="button" 
                                wire:click="setTab('all')" 
                                class="btn btn-sm {{ $tab === 'all' ? 'btn-primary' : 'btn-secondary' }}">
                            All Packages
                        </button>
                        <button type="button" 
                                wire:click="setTab('hotspot')" 
                                class="btn btn-sm {{ $tab === 'hotspot' ? 'btn-primary' : 'btn-secondary' }}">
                            <i class="bi bi-wifi me-1"></i> Hotspot
                        </button>
                        <button type="button" 
                                wire:click="setTab('pppoe')" 
                                class="btn btn-sm {{ $tab === 'pppoe' ? 'btn-primary' : 'btn-secondary' }}">
                            <i class="bi bi-hdd-network me-1"></i> PPPoE Fiber
                        </button>
                    </div>
                </div>

                {{-- Search Box --}}
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search" 
                               class="form-control border-start-0" 
                               placeholder="Search packages by name or speed...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Packages Cards Grid --}}
    @if($packages->isEmpty())
        <div class="card">
            <x-ui.empty-state icon="bi-box2" title="No Packages Configured Yet" description="Create your first Hotspot or PPPoE bandwidth package to start selling internet vouchers and automated billing.">
                <button type="button" wire:click="openCreateModal" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Create First Package
                </button>
            </x-ui.empty-state>
        </div>
    @else
        <div class="row g-4">
            @foreach($packages as $pkg)
                <div class="col-12 col-md-6 col-xl-4" wire:key="pkg-card-{{ $pkg->id }}">
                    <div class="card h-100 position-relative">
                        {{-- Header Banner --}}
                        <div class="card-header border-0 bg-transparent pt-4 px-4 pb-0 d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge {{ $pkg->type === 'pppoe' ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning' }}">
                                    <i class="bi {{ $pkg->type === 'pppoe' ? 'bi-hdd-network' : 'bi-wifi' }} me-1"></i>
                                    {{ $pkg->type === 'pppoe' ? 'PPPoE Fiber' : 'Hotspot' }}
                                </span>
                                @if(!$pkg->is_active)
                                    <span class="badge bg-secondary-subtle text-secondary ms-1">Inactive</span>
                                @endif
                                @if($pkg->show_public)
                                    <span class="badge bg-success-subtle text-success ms-1">Public</span>
                                @endif
                            </div>

                            <div class="dropdown">
                                <button class="btn btn-secondary btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                                    <li>
                                        <button class="dropdown-item" wire:click="openEditModal({{ $pkg->id }})">
                                            <i class="bi bi-pencil me-2 text-orange-500"></i> Edit Package
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
                            <h4 class="ui-section-title mb-1">{{ $pkg->package }}</h4>
                            @if($pkg->includes_tv)
                                <div class="mb-1">
                                    <span class="badge rounded-pill" style="background: #4f46e5; color: #fff; font-size: 11px;">
                                        <i class="bi bi-tv me-1"></i>{{ $pkg->tv_channels ?: 'Smart TV & Live Channels' }}
                                    </span>
                                </div>
                            @endif
                            <div class="text-2xl fw-semibold text-dark my-2 tabular-nums">
                                <span class="text-sm text-muted">KES</span> {{ number_format($pkg->price, 0) }}
                                <span class="text-xs text-muted fw-normal">/ {{ $pkg->formatted_validity }}</span>
                            </div>

                            @if($pkg->description)
                                <p class="text-muted small mb-3">{{ Str::limit($pkg->description, 80) }}</p>
                            @endif

                            {{-- Specs Pills --}}
                            <div class="row g-2 mb-3 pt-2">
                                <div class="col-6">
                                    <div class="p-2.5 rounded-xl bg-slate-50 small">
                                        <div class="text-muted" style="font-size:0.75rem;">Speed Limit</div>
                                        <div class="fw-semibold text-dark"><i class="bi bi-speedometer2 text-orange-500 me-1"></i>{{ $pkg->formatted_speed }}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2.5 rounded-xl bg-slate-50 small">
                                        <div class="text-muted" style="font-size:0.75rem;">Data Cap</div>
                                        <div class="fw-semibold text-dark">
                                            <i class="bi bi-pie-chart text-success me-1"></i>
                                            {{ $pkg->data_limit_mb ? ($pkg->data_limit_mb >= 1024 ? round($pkg->data_limit_mb/1024, 1) . ' GB' : $pkg->data_limit_mb . ' MB') : 'Unlimited' }}
                                        </div>
                                    </div>
                                </div>
                                @if($pkg->type === 'hotspot')
                                    <div class="col-12">
                                        <div class="p-2.5 rounded-xl bg-slate-50 small d-flex justify-content-between align-items-center">
                                            <span class="text-muted" style="font-size:0.75rem;">Shared Devices:</span>
                                            <span class="fw-semibold text-dark"><i class="bi bi-phone text-info me-1"></i>{{ $pkg->shared_users }} {{ Str::plural('device', $pkg->shared_users) }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Linked Routers & Sync Status --}}
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between align-items-center small mb-2">
                                    <span class="text-muted fw-semibold">Linked Routers:</span>
                                    <button wire:click="retrySync({{ $pkg->id }})" class="btn btn-link btn-sm p-0 text-decoration-none text-orange-600" title="Sync router profiles">
                                        <i class="bi bi-arrow-repeat me-1"></i>Sync
                                    </button>
                                </div>

                                @if($pkg->routers->isEmpty())
                                    <span class="badge bg-secondary-subtle text-secondary">All tenant routers</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($pkg->routers as $rtr)
                                            @php
                                                $sync = $rtr->pivot->sync_status ?? 'pending';
                                            @endphp
                                            <span class="badge {{ $sync === 'synced' ? 'bg-success-subtle text-success' : ($sync === 'failed' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') }}" 
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
                        <div class="card-footer border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       wire:click="toggleActive({{ $pkg->id }})" 
                                       {{ $pkg->is_active ? 'checked' : '' }} 
                                       id="activeSwitch{{ $pkg->id }}">
                                <label class="form-check-label small fw-semibold text-muted" for="activeSwitch{{ $pkg->id }}">
                                    {{ $pkg->is_active ? 'Active' : 'Inactive' }}
                                </label>
                            </div>

                            <button type="button" wire:click="openEditModal({{ $pkg->id }})" class="btn btn-secondary btn-sm px-3">
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
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.5); z-index: 1055; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" style="max-height: 92vh;">
                <div class="modal-content" style="max-height: 92vh; display: flex; flex-direction: column;">
                    <div class="modal-header" style="flex-shrink: 0;">
                        <div>
                            <h4 class="modal-title mb-0">
                                {{ $editingId ? 'Edit Package' : 'Create Internet Package' }}
                            </h4>
                            <p class="small text-muted mb-0">Define plan parameters and auto-sync router profiles.</p>
                        </div>
                        <button type="button" wire:click="$set('showModal', false)" class="btn-close" aria-label="Close"></button>
                    </div>

                    <form wire:submit.prevent="savePackage" style="display: flex; flex-direction: column; flex: 1 1 auto; overflow: hidden;">
                        <div class="modal-body p-4" style="overflow-y: auto; flex: 1 1 auto;">
                            @if($modalError)
                                <div class="alert alert-danger py-2 px-3 small mb-3">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $modalError }}
                                </div>
                            @endif

                            @if($errors->any())
                                <div class="alert alert-danger py-2 px-3 small mb-3">
                                    <strong>Please fix the following:</strong>
                                    <ul class="mb-0 mt-1 ps-3">
                                        @foreach($errors->all() as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Package Type Selector (Real Radio Inputs + Instant Visual State) --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Package Type <span class="text-danger">*</span></label>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label
                                            for="pkgTypeHotspot"
                                            wire:click="selectType('hotspot')"
                                            class="d-block p-3 rounded-3 w-100 mb-0"
                                            style="cursor: pointer; transition: all 0.15s ease; border: {{ $type === 'hotspot' ? '2px solid #f97316' : '1.5px solid #cbd5e1' }}; background: {{ $type === 'hotspot' ? '#fff7ed' : '#ffffff' }}; box-shadow: {{ $type === 'hotspot' ? '0 0 0 3px rgba(249,115,22,0.12)' : 'none' }};"
                                        >
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <input
                                                        type="radio"
                                                        id="pkgTypeHotspot"
                                                        name="package_type"
                                                        value="hotspot"
                                                        wire:model.live="type"
                                                        class="form-check-input me-3 mt-0"
                                                        style="width: 18px; height: 18px; accent-color: #f97316; cursor: pointer;"
                                                    >
                                                    <i class="bi bi-wifi fs-4 text-warning me-2"></i>
                                                    <div>
                                                        <div class="fw-bold text-dark">Hotspot WiFi</div>
                                                        <div class="text-muted small">Captive portal, shared devices</div>
                                                    </div>
                                                </div>
                                                @if($type === 'hotspot')
                                                    <span class="badge rounded-pill" style="background: #f97316; color: #fff; font-size: 10px;">Selected</span>
                                                @endif
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <label
                                            for="pkgTypePppoe"
                                            wire:click="selectType('pppoe')"
                                            class="d-block p-3 rounded-3 w-100 mb-0"
                                            style="cursor: pointer; transition: all 0.15s ease; border: {{ $type === 'pppoe' ? '2px solid #0284c7' : '1.5px solid #cbd5e1' }}; background: {{ $type === 'pppoe' ? '#f0f9ff' : '#ffffff' }}; box-shadow: {{ $type === 'pppoe' ? '0 0 0 3px rgba(2,132,199,0.12)' : 'none' }};"
                                        >
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <input
                                                        type="radio"
                                                        id="pkgTypePppoe"
                                                        name="package_type"
                                                        value="pppoe"
                                                        wire:model.live="type"
                                                        class="form-check-input me-3 mt-0"
                                                        style="width: 18px; height: 18px; accent-color: #0284c7; cursor: pointer;"
                                                    >
                                                    <i class="bi bi-hdd-network fs-4 text-info me-2"></i>
                                                    <div>
                                                        <div class="fw-bold text-dark">PPPoE Fiber</div>
                                                        <div class="text-muted small">Dedicated home/business link</div>
                                                    </div>
                                                </div>
                                                @if($type === 'pppoe')
                                                    <span class="badge rounded-pill" style="background: #0284c7; color: #fff; font-size: 10px;">Selected</span>
                                                @endif
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                {{-- Package Name --}}
                                <div class="col-12 col-md-8">
                                    <label class="form-label">Package Name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. 10 Mbps Unlimited (24 Hrs)">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                {{-- Price (KES) --}}
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Price (KES) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">KES</span>
                                        <input type="number" step="0.5" wire:model="price" class="form-control @error('price') is-invalid @enderror">
                                    </div>
                                    @error('price') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- Speeds: Download & Upload --}}
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Download Speed <span class="text-danger">*</span></label>
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
                                    <label class="form-label">Upload Speed <span class="text-danger">*</span></label>
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
                                    <label class="form-label">Validity Duration <span class="text-danger">*</span></label>
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
                                        <label class="form-label">Simultaneous Devices</label>
                                        <input type="number" min="1" max="50" wire:model="shared_users" class="form-control" placeholder="1">
                                        <div class="form-text">How many phones/laptops can use one voucher code.</div>
                                    </div>
                                @else
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Burst Limit (Optional)</label>
                                        <input type="text" wire:model="burst_speed" class="form-control" placeholder="e.g. 20M/20M 10M/10M 16/16">
                                        <div class="form-text">MikroTik burst-limit / threshold / burst-time.</div>
                                    </div>
                                @endif

                                {{-- Data Limit --}}
                                <div class="col-12">
                                    <div class="card p-3 bg-slate-50">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label mb-0">Data Limit / Bandwidth Cap</label>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" wire:model.live="unlimited_data" id="unlimitedSwitch">
                                                <label class="form-check-label fw-semibold text-orange-600" for="unlimitedSwitch">Unlimited Data</label>
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

                                {{-- TV Package Feature --}}
                                <div class="col-12">
                                    <div class="card p-3" style="background: #f5f3ff; border: 1.5px solid #ddd6fe;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 fw-bold text-indigo-900 d-flex align-items-center gap-2">
                                                <i class="bi bi-tv fs-5 text-indigo-600"></i>
                                                <span>Smart TV &amp; IPTV Channels Included</span>
                                            </label>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" wire:model.live="includes_tv" id="includesTvSwitch" style="accent-color: #6366f1;">
                                                <label class="form-check-label fw-bold text-indigo-700" for="includesTvSwitch">Include TV</label>
                                            </div>
                                        </div>
                                        <div class="small text-muted mb-2">Showcase this package on the Captive Portal as a TV streaming package with live channels.</div>
                                        @if($includes_tv)
                                            <div class="mt-2">
                                                <label class="form-label small fw-semibold text-indigo-900">TV Channels / Streaming Perks</label>
                                                <input type="text" wire:model="tv_channels" class="form-control" placeholder="e.g. 50+ HD Live Sports, DSTV Channels, Movies, Premier League">
                                                <div class="form-text text-indigo-700 small">Highlight what channels or streaming access is included with this pass.</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Router Multi-Select grouped by location --}}
                                <div class="col-12">
                                    <label class="form-label">Link to Routers</label>
                                    <div class="card p-3" style="max-height: 180px; overflow-y: auto;">
                                        <div class="form-check mb-2 pb-2 border-bottom">
                                            <input class="form-check-input" type="checkbox" value="all" wire:model="selected_routers" id="router_all">
                                            <label class="form-check-label fw-semibold text-orange-600" for="router_all">
                                                ★ All Routers (Synchronize everywhere)
                                            </label>
                                        </div>

                                        @foreach($allRouters as $location => $rGroup)
                                            <div class="fw-semibold small text-muted mt-2 mb-1">{{ $location }}</div>
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
                                    <label class="form-label">Description &amp; Highlights</label>
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

                        <div class="modal-footer" style="flex-shrink: 0; background: #ffffff; border-top: 1px solid #e2e8f0;">
                            <button type="button" wire:click="$set('showModal', false)" class="btn btn-secondary">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="savePackage">
                                <span wire:loading.remove wire:target="savePackage">
                                    <i class="bi bi-check2-circle me-1"></i> Save &amp; Sync to Routers
                                </span>
                                <span wire:loading wire:target="savePackage">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

