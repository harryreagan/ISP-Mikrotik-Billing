<div class="zoom-in">
    <x-slot name="header">🛜 {{ __('Hotspot Manager') }}</x-slot>

    @push('styles')
    <style>
        /* ── Voucher Print Styles ── */
        @media print {
            body > *:not(#voucher-print-area) {
                display: none !important;
            }
            #voucher-print-area {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: #fff;
                margin: 0;
                padding: 0;
            }
            #voucher-print-area, #voucher-print-area * {
                visibility: visible !important;
            }
            .no-print { display: none !important; }
        }
        .voucher-card {
            border: 2px dashed #6366f1;
            border-radius: 10px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #f8f9ff 0%, #eef2ff 100%);
            text-align: center;
            min-width: 160px;
        }
        .voucher-code {
            font-family: 'Courier New', monospace;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 3px;
            color: #3730a3;
            word-break: break-all;
        }
        .online-dot { width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block; animation: pulse 1.5s infinite; margin-right:4px; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
        .hs-table thead th { background:#f8fafc; color:#64748b; font-size:.75rem; font-weight:500; text-transform:none; letter-spacing:0; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
        .dark .hs-table thead th { background:rgba(15, 18, 32, 0.5); color:#94a3b8; border-bottom-color: rgba(51, 65, 85, 0.5); }
        .hs-table tbody td { padding: 10px 16px; font-size: .875rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
        .dark .hs-table tbody td { border-bottom-color: rgba(51, 65, 85, 0.4); color: #cbd5e1; }
        .hs-table tbody tr:hover { background-color: rgba(248, 250, 252, 0.7); }
        .dark .hs-table tbody tr:hover { background-color: rgba(30, 41, 59, 0.4); }
        .icon-action { cursor: pointer; padding: 2px 4px; border-radius: 4px; font-size: .875rem; }
        .icon-delete { color: #e11d48; }
        .icon-lock { color: #475569; }
        .icon-edit { color: #475569; font-size: .75rem; margin-left: 4px; }
        .icon-print { color: #475569; margin-right: 5px; }
        .icon-qr { color: #475569; }
        .icon-comment { color: #64748b; opacity: 0.7; margin-right: 4px; }
        .badge-profile { background: #f0f9ff; color: #0284c7; font-weight: 500; font-size: 11px; padding: 2px 8px; border-radius: 9999px; border: 1px solid rgba(186, 230, 253, 0.7); }
        .log-item { font-size: .8125rem; border-left: 3px solid #cbd5e1; padding-left: 10px; margin-bottom: 8px; }
        .log-item.hotspot { border-left-color: #f97316; }
        .resource-mini { font-size: .75rem; color: #64748b; }
        .resource-val { font-weight: 600; color: #1e293b; font-variant-numeric: tabular-nums; }
        .dark .resource-val { color: #f8fafc; }
        
        /* Premium Voucher */
        .voucher-premium {
            width: 250px;
            border-radius: 16px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.05);
            border: 1px solid rgba(79, 70, 229, 0.15);
            position: relative;
            margin: 5px;
            display: inline-block;
            text-align: center;
        }
        .voucher-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed, #ec4899);
        }
        .voucher-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 8px;
            text-align: center;
            font-weight: 800;
            font-size: .85rem;
            text-transform: uppercase;
        }
        .voucher-body { padding: 12px; display: flex; flex-direction: column; align-items: center; }
        .voucher-qr { width: 80px; height: 80px; margin-bottom: 8px; background: #f1f5f9; }
        .voucher-user { font-size: 1.25rem; font-weight: 900; color: #1e293b; letter-spacing: 1px; margin-bottom: 2px; }
        .voucher-pass { font-size: .9rem; color: #64748b; margin-bottom: 8px; }
        .voucher-info { width: 100%; border-top: 1px dashed #e2e8f0; padding-top: 8px; font-size: .75rem; display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }
    </style>
    @endpush

    @push('scripts')
    {{-- Scripts handled by Vite/NPM --}}
    @endpush

    <x-ui.page-header
        :title="__('Hotspot manager')"
        :subtitle="__('Manage hotspot users, vouchers, active sessions, profiles, and sales')"
    />

    {{-- ── Router Selector ── --}}
    <div class="ui-filter-bar mb-4">
        <i class="bi bi-wifi text-orange-500"></i>
        <select class="form-select form-select-sm w-auto fw-medium" wire:model.live="selectedRouter" id="hs-router-select">
            <option value="">{{ __('-- Select Router --') }}</option>
            @foreach($routers as $r)
                @php $rName = $r->router_name ?: $r->name; @endphp
                <option value="{{ $rName }}">{{ $rName }} ({{ $r->vpn_ip ?: ($r->ip_address ?: $r->ip) }}){{ $r->status === 'online' ? ' • Online' : '' }}</option>
            @endforeach
        </select>
        @if($selectedRouter)
            <button class="btn btn-sm btn-secondary no-print" wire:click="loadData" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="loadData"><i class="bi bi-arrow-clockwise"></i> {{ __('Refresh') }}</span>
                <span wire:loading wire:target="loadData"><span class="spinner-border spinner-border-sm"></span></span>
            </button>
            <span class="badge bg-success ms-auto">
                <span class="online-dot"></span> {{ __(':count Online', ['count' => $onlineCount]) }}
            </span>
        @endif
    </div>

    @if(!$selectedRouter)
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>{{ __('Select a connected router to manage Hotspot.') }}</div>
    @else

    {{-- ── Navigation Tabs ── --}}
    <ul class="nav nav-tabs mb-4 no-print flex-wrap">
        @foreach([
            ['dashboard','bi-speedometer2','Dashboard'],
            ['packages','bi-box-seam','Packages & Portal Preview'],
            ['users','bi-people','Users'],
            ['sessions','bi-activity','Sessions'],
            ['vouchers','bi-ticket-perforated','Vouchers'],
            ['profiles','bi-person-badge','Profiles'],
            ['income','bi-cash-coin','Income'],
            ['report','bi-bar-chart-line','Reports'],
            ['setup','bi-gear-fill','Setup'],
        ] as [$tab, $icon, $label])
        <li class="nav-item">
            <button class="nav-link {{ $activeTab===$tab?'active':'' }}" wire:click="$set('activeTab','{{ $tab }}')">
                <i class="bi {{ $icon }} me-1"></i>{{ __($label) }}
                @if($tab==='sessions')<span class="badge bg-success ms-1 rounded-pill">{{ $onlineCount }}</span>@endif
            </button>
        </li>
        @endforeach
    </ul>

    {{-- ====================================================================== --}}
    {{-- DASHBOARD TAB --}}
    {{-- ====================================================================== --}}
    @if($activeTab==='dashboard')
    <div class="row g-3 mb-4">
        {{-- Resources Bar --}}
        @if($routerResources)
        <div class="col-12">
            <div class="card overflow-hidden">
                <div class="card-body py-2.5 px-4 d-flex align-items-center gap-4 flex-wrap">
                    <div class="resource-mini"><i class="bi bi-cpu me-1"></i>{{ __('CPU:') }} <span class="resource-val">{{ $routerResources['cpu-load'] ?? 0 }}%</span></div>
                    <div class="resource-mini"><i class="bi bi-memory me-1"></i>{{ __('MEM:') }} <span class="resource-val">
                        {{ number_format(($routerResources['free-memory'] ?? 0)/1048576, 1) }}MB / {{ number_format(($routerResources['total-memory'] ?? 0)/1048576, 1) }}MB</span>
                    </div>
                    <div class="resource-mini"><i class="bi bi-hdd me-1"></i>{{ __('HDD:') }} <span class="resource-val">
                        {{ number_format(($routerResources['free-hdd-space'] ?? 0)/1048576, 1) }}MB</span>
                    </div>
                    <div class="resource-mini"><i class="bi bi-clock-history me-1"></i>{{ __('Uptime:') }} <span class="resource-val">{{ $routerResources['uptime'] ?? '—' }}</span></div>
                    <div class="resource-mini ms-auto"><i class="bi bi-info-circle me-1"></i>{{ $routerResources['board-name'] ?? 'MikroTik' }} ({{ $routerResources['version'] ?? '?' }})</div>
                </div>
            </div>
        </div>
        @endif

        <div class="col-6 col-md-3">
            <x-ui.stat-card
                :label="__('Total Users')"
                :value="count($users)"
                color="purple"
            >
                <x-slot:icon><i class="bi bi-people-fill"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
        <div class="col-6 col-md-3">
            <x-ui.stat-card
                :label="__('Online Now')"
                :value="$onlineCount"
                color="green"
            >
                <x-slot:icon><i class="bi bi-wifi"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
        <div class="col-6 col-md-3">
            <x-ui.stat-card
                :label="__('Unused Vouchers')"
                :value="$totalVouchers - $usedVouchers"
                color="orange"
            >
                <x-slot:icon><i class="bi bi-ticket-perforated"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
        <div class="col-6 col-md-3">
            <x-ui.stat-card
                :label="__('This Month Income')"
                :value="(siteUrlSettings('site_currency') ?? 'KES ') . number_format($monthIncome,0)"
                color="blue"
            >
                <x-slot:icon><i class="bi bi-cash-coin"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
    </div>

    <div class="row g-3">
        {{-- Sales Chart --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-graph-up text-primary me-1"></i>{{ __('Last 7 Days Sales') }}</div>
                <div class="card-body">
                    <div id="salesChart" style="min-height: 200px;"></div>
                </div>
            </div>
        </div>

        {{-- Active Sessions Mini --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-activity text-success me-1"></i>{{ __('Active Sessions') }}</span>
                    <button class="btn btn-xs btn-secondary" wire:click="refreshSessions"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
                <div class="card-body p-0" style="max-height:350px;overflow-y:auto">
                    <table class="table table-sm align-middle mb-0 hs-table">
                        <thead><tr><th>{{ __('User') }}</th><th>{{ __('IP') }}</th><th>{{ __('MAC') }}</th><th>{{ __('Uptime') }}</th></tr></thead>
                        <tbody>
                        @forelse($sessions as $s)
                            <tr>
                                <td><strong class="text-slate-800">{{ $s['user'] ?? '-' }}</strong></td>
                                <td><code class="small">{{ $s['address'] ?? '-' }}</code></td>
                                <td><code class="small">{{ $s['mac-address'] ?? '-' }}</code></td>
                                <td><span class="badge bg-secondary">{{ $s['uptime'] ?? '-' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No active sessions') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Hotspot Logs --}}
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-file-earmark-text text-warning me-1"></i>{{ __('Live Hotspot Log') }}</span>
                    <button class="btn btn-xs btn-secondary" wire:click="refreshLogs"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
                <div class="card-body p-3" style="max-height:350px;overflow-y:auto">
                    @forelse($hsLogs as $log)
                    <div class="log-item {{ str_contains(strtolower($log['topics']), 'hotspot') ? 'hotspot' : '' }}">
                        <div class="d-flex justify-content-between x-small text-muted mb-1">
                            <span>{{ $log['topics'] }}</span>
                            <span>{{ Carbon\Carbon::parse($log['time'])->format('H:i:s') }}</span>
                        </div>
                        <div class="small">{{ $log['message'] }}</div>
                    </div>
                    @empty
                    <div class="text-center text-muted p-3 small">{{ __('No logs found.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Today Income / Stats --}}
        <div class="col-lg-3">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cash me-1 text-success"></i>{{ __('Today Income') }}</div>
                <div class="card-body text-center py-4">
                    <div class="text-2xl font-semibold tabular-nums text-emerald-600">{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($todayIncome,2) }}</div>
                    <small class="text-muted">{{ now()->format('d M Y') }}</small>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><i class="bi bi-server me-1 text-primary"></i>{{ __('Hotspot Servers') }}</div>
                <div class="card-body p-0">
                    @forelse($servers as $sv)
                    <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                        <div><strong class="small">{{ $sv['name'] ?? '-' }}</strong><br><code class="x-small text-muted">{{ $sv['interface'] ?? '' }}</code></div>
                        <span class="badge rounded-pill {{ ($sv['disabled'] ?? 'false')==='false'?'bg-success':'bg-danger' }}">{{ ($sv['disabled'] ?? 'false')==='false'?__('ON'):__('OFF') }}</span>
                    </div>
                    @empty
                    <div class="text-center text-muted p-3 small">{{ __('No servers') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ====================================================================== --}}
    {{-- PACKAGES & LIVE PORTAL PREVIEW TAB --}}
    {{-- ====================================================================== --}}
    @if($activeTab==='packages')
    <div class="row g-4 mb-4">
        {{-- Left: Hotspot Packages List & Management --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-box-seam text-primary me-2"></i>{{ __('Hotspot Packages') }}
                        </h5>
                        <small class="text-muted">{{ __('Packages displayed on your captive portal for WiFi customers.') }}</small>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm rounded-3 shadow-sm px-3" wire:click="openNewPackageModal">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Create Package') }}
                    </button>
                </div>
                <div class="card-body pt-1">
                    @if($hotspotPackages->isEmpty())
                        <div class="text-center py-5 bg-light rounded-4 my-2">
                            <i class="bi bi-box-seam display-4 text-muted opacity-50"></i>
                            <h6 class="mt-3 fw-bold">{{ __('No Hotspot Packages Yet') }}</h6>
                            <p class="text-muted small mb-3">{{ __('Create your first package (e.g., 1 Hour KES 10, 24 Hours KES 50) to sell access to your WiFi users.') }}</p>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" wire:click="openNewPackageModal">
                                <i class="bi bi-plus-circle me-1"></i>{{ __('Create Hotspot Package') }}
                            </button>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr style="font-size: 0.8rem;">
                                        <th>{{ __('Package') }}</th>
                                        <th>{{ __('Price') }}</th>
                                        <th>{{ __('Speed') }}</th>
                                        <th>{{ __('Validity') }}</th>
                                        <th>{{ __('Router') }}</th>
                                        <th class="text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($hotspotPackages as $pkg)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $pkg->package }}</div>
                                            @if($pkg->plan_label && $pkg->plan_label !== $pkg->package)
                                                <small class="text-muted">{{ $pkg->plan_label }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success fs-6 fw-bold px-2 py-1">
                                                KES {{ number_format($pkg->price, 2) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-speedometer me-1 text-primary"></i>{{ $pkg->mikrotik_rate_limit ?: ($pkg->speed ?: 'Unlimited') }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info-emphasis">
                                                <i class="bi bi-clock-history me-1"></i>{{ $pkg->validity_value ?: 1 }} {{ ucfirst($pkg->validity_unit ?: 'hours') }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary small">
                                                {{ $pkg->router_name ?: __('All') }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-light border text-primary" 
                                                        wire:click="editHotspotPackage({{ $pkg->id }})" title="{{ __('Edit') }}">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-light border text-success" 
                                                        wire:click="pushPackageToRouter({{ $pkg->id }})" title="{{ __('Push Profile to MikroTik') }}">
                                                    <i class="bi bi-cloud-arrow-up"></i>
                                                </button>
                                                <button type="button" class="btn btn-light border text-danger" 
                                                        wire:click="deleteHotspotPackage({{ $pkg->id }})" 
                                                        wire:confirm="{{ __('Are you sure you want to delete this package?') }}" title="{{ __('Delete') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Live Portal Preview --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 20px;">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-eye-fill text-primary me-2"></i>{{ __('Live Portal Preview') }}
                        </h5>
                        <small class="text-muted">{{ __('Interactive look at how your packages appear to customers.') }}</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" 
                                wire:click="$set('portalPreviewDevice', 'mobile')" 
                                class="btn {{ $portalPreviewDevice === 'mobile' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            <i class="bi bi-phone me-1"></i>{{ __('Mobile') }}
                        </button>
                        <button type="button" 
                                wire:click="$set('portalPreviewDevice', 'desktop')" 
                                class="btn {{ $portalPreviewDevice === 'desktop' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            <i class="bi bi-display me-1"></i>{{ __('Desktop') }}
                        </button>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex align-items-center justify-content-between mb-3 p-2 bg-light rounded-3 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small fw-semibold text-muted">{{ __('Portal Template:') }}</span>
                            <select class="form-select form-select-sm w-auto rounded-3" wire:model.live="portalTemplate">
                                @foreach($templatesCatalog as $tKey => $tMeta)
                                    <option value="{{ $tKey }}">{{ $tMeta['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <a href="{{ route('admin.portal-customizer') }}" class="btn btn-link btn-sm text-decoration-none p-0">
                            {{ __('Customize Colors & Logo') }} <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <div class="p-3 bg-light rounded-4 d-flex justify-content-center align-items-center" style="min-height: 580px;">
                        <div class="border rounded-4 shadow-sm overflow-hidden bg-white d-flex justify-content-center transition-all" 
                             style="{{ $portalPreviewDevice === 'mobile' ? 'width: 375px; height: 550px;' : 'width: 100%; height: 550px;' }}; transition: width 0.3s ease;">
                            <iframe srcdoc="{{ $portalPreviewHtml }}" 
                                    class="w-100 h-100 border-0" 
                                    sandbox="allow-scripts allow-forms allow-same-origin"
                                    title="Captive Portal Live Preview"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Package Modal (Add / Edit) --}}
    @if($showPackageModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-box-seam text-primary me-2"></i>
                        {{ $editingPackageId ? __('Edit Hotspot Package') : __('Create New Hotspot Package') }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closePackageModal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit="saveHotspotPackage">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">{{ __('Package Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" wire:model="pkg_package" placeholder="e.g. 1 Hour Fast, 24 Hours Unlimited">
                            @error('pkg_package') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small">{{ __('Price (KES)') }} <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light fw-bold">KES</span>
                                    <input type="number" step="1" class="form-control" wire:model="pkg_price" placeholder="50">
                                </div>
                                @error('pkg_price') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small">{{ __('Speed / Rate Limit') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="pkg_rate_limit" placeholder="e.g. 5M/5M">
                                <small class="text-muted" style="font-size: 10px;">{{ __('Format: Rx/Tx e.g. 5M/5M') }}</small>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small">{{ __('Validity Duration') }} <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control form-control-sm" wire:model="pkg_validity_value">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small">{{ __('Unit') }}</label>
                                <select class="form-select form-select-sm" wire:model="pkg_validity_unit">
                                    <option value="minutes">{{ __('Minutes') }}</option>
                                    <option value="hours">{{ __('Hours') }}</option>
                                    <option value="days">{{ __('Days') }}</option>
                                    <option value="weeks">{{ __('Weeks') }}</option>
                                    <option value="months">{{ __('Months') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">{{ __('Shared Users (Simultaneous Logins)') }}</label>
                            <input type="number" min="1" class="form-control form-control-sm" wire:model="pkg_shared_users">
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="pushToMikrotikSwitch" wire:model="pkg_push_to_mikrotik">
                            <label class="form-check-label small fw-semibold" for="pushToMikrotikSwitch">
                                {{ __('Automatically push profile to MikroTik Router (:router)', ['router' => $selectedRouter ?: 'None']) }}
                            </label>
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="showOnSiteSwitch" wire:model="pkg_show_on_site">
                            <label class="form-check-label small fw-semibold" for="showOnSiteSwitch">
                                {{ __('Display on Captive Portal for purchase') }}
                            </label>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <button type="button" class="btn btn-light rounded-3 btn-sm" wire:click="closePackageModal">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="btn btn-primary rounded-3 btn-sm px-3">
                                <i class="bi bi-check-lg me-1"></i>{{ $editingPackageId ? __('Save Changes') : __('Create Package') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endif
    {{-- ====================================================================== --}}
    @if($activeTab==='users')
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-people me-1 text-primary"></i>{!! __('Hotspot Users on :router', ['router' => '<strong>' . e($selectedRouter) . '</strong>']) !!} <span class="badge bg-secondary ms-1">{{ count($users) }}</span></span>
                    <button type="button" class="btn btn-sm btn-primary no-print" wire:click="startAddUser">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Add User') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 hs-table data-table" wire:key="tbl-users-{{ $selectedRouter }}">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Server') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Profile') }}</th>
                                    <th>{{ __('MAC Address') }}</th>
                                    <th>{{ __('Uptime') }}</th>
                                    <th>{{ __('Bytes In') }}</th>
                                    <th>{{ __('Bytes Out') }}</th>
                                    <th>{{ __('Comment') }}</th>
                                    <th class="text-end no-print">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $u)
                            @php 
                                $bytesInRaw = (int)($u['bytes-in'] ?? 0);
                                $bytesOutRaw = (int)($u['bytes-out'] ?? 0);
                                $bytesIn = $bytesInRaw > 1073741824 ? number_format($bytesInRaw/1073741824,2).'GB' : number_format($bytesInRaw/1048576,1).'MB';
                                $bytesOut = $bytesOutRaw > 1073741824 ? number_format($bytesOutRaw/1073741824,2).'GB' : number_format($bytesOutRaw/1048576,1).'MB';
                            @endphp
                            <tr wire:key="u-{{ $u['.id'] ?? $loop->index }}">
                                <td><span class="badge bg-light text-dark shadow-sm border">{{ $u['server'] ?? 'all' }}</span></td>
                                <td>
                                    <strong>{{ $u['name'] ?? '-' }}</strong>
                                    @if(isset($u['password']) && $u['password']) <br><code class="text-danger small">{{ $u['password'] }}</code> @endif
                                </td>
                                <td><span class="badge badge-profile">{{ $u['profile'] ?? '-' }}</span></td>
                                <td><code class="small">{{ $u['mac-address'] ?? '—' }}</code></td>
                                <td><small class="fw-bold">{{ $u['uptime'] ?? '0s' }}</small></td>
                                <td><small class="text-success fw-bold">{{ $bytesIn }}</small></td>
                                <td><small class="text-danger fw-bold">{{ $bytesOut }}</small></td>
                                <td><small class="text-muted">{{ $u['comment'] ?? '—' }}</small></td>
                                <td class="text-end no-print">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info" wire:click="printHotspotUserSlip(@js($u['name'] ?? ''))" title="{{ __('Print credentials') }}"><i class="bi bi-printer"></i></button>
                                        <button type="button" class="btn btn-outline-warning" wire:click="editUserByName(@js($u['name'] ?? ''))"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn btn-outline-danger" wire:click="confirmAction('removeUser', @js($u['name'] ?? ''), @js(__('Permanently delete this hotspot user?')))"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-5">{{ __('No hotspot users found on this router.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif


    {{-- ====================================================================== --}}
    {{-- SESSIONS TAB --}}
    {{-- ====================================================================== --}}
    @if($activeTab==='sessions')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-activity text-success me-1"></i>{!! __('Active Sessions on :router', ['router' => '<strong>' . e($selectedRouter) . '</strong>']) !!}</span>
            <button class="btn btn-sm btn-outline-success no-print" wire:click="refreshSessions" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="refreshSessions"><i class="bi bi-arrow-clockwise me-1"></i>{{ __('Refresh') }}</span>
                <span wire:loading wire:target="refreshSessions"><span class="spinner-border spinner-border-sm"></span></span>
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 hs-table data-table" wire:key="tbl-sessions-{{ $selectedRouter }}">
                    <thead><tr>
                        <th>#</th><th>{{ __('User') }}</th><th>{{ __('IP Address') }}</th><th>{{ __('MAC Address') }}</th><th>{{ __('Uptime') }}</th><th>{{ __('Download') }}</th><th>{{ __('Upload') }}</th><th>{{ __('Server') }}</th><th class="no-print">{{ __('Action') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($sessions as $s)
                    <tr wire:key="sess-{{ $loop->index }}">
                        <td class="text-muted small">{{ $loop->iteration }}</td>
                        <td><strong>{{ $s['user'] ?? '-' }}</strong></td>
                        <td><code>{{ $s['address'] ?? '-' }}</code></td>
                        <td><code class="small">{{ $s['mac-address'] ?? '-' }}</code></td>
                        <td><span class="badge bg-success">{{ $s['uptime'] ?? '-' }}</span></td>
                        <td><small>{{ number_format((int)($s['bytes-in'] ?? 0)/1048576,2) }} MB</small></td>
                        <td><small>{{ number_format((int)($s['bytes-out'] ?? 0)/1048576,2) }} MB</small></td>
                        <td><span class="badge bg-info text-dark small">{{ $s['server'] ?? '-' }}</span></td>
                        <td class="no-print">
                            <button type="button" class="btn btn-xs btn-outline-warning" wire:click="confirmAction('disconnectSession', @js($s['user'] ?? ''), @js(__('Disconnect this user\'s session?')))">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">{{ __('No active hotspot sessions.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- ====================================================================== --}}
    {{-- VOUCHERS TAB --}}
    {{-- ====================================================================== --}}
    @if($activeTab==='vouchers')
    <div class="row g-3">
        {{-- Generator Form --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white"><i class="bi bi-ticket-perforated-fill me-1"></i>{{ __('Voucher Generator') }}</div>
                <div class="card-body">
                    <form wire:submit.prevent="generateVouchers" id="voucher-gen-form">
                        <div class="mb-2">
                            <label class="form-label">{!! __('User Profile :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                            <select class="form-select form-select-sm" wire:model.defer="v_profile" id="v_profile">
                                <option value="">{{ __('-- Select Profile --') }}</option>
                                @foreach($userProfiles as $p)
                                    <option value="{{ $p['name'] }}">{{ $p['name'] }}</option>
                                @endforeach
                            </select>
                            @error('v_profile')<div class="text-danger small">{{ $message }}</div>@enderror
                            @if($v_profile)
                                @php $localPkg = collect($hotspotPackages)->firstWhere('package', $v_profile); @endphp
                                @if($localPkg)
                                    <div class="x-small text-success mt-1"><i class="bi bi-check-circle"></i> {{ __('Linked to DB Package:') }} {{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ $localPkg->price }}</div>
                                @endif
                            @endif
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">{!! __('Count :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                                <input type="number" class="form-control form-control-sm" wire:model.defer="v_count" min="1" max="500">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{!! __('User Length :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                                <input type="number" class="form-control form-control-sm" wire:model.defer="v_length" min="3" max="20">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">{{ __('Type') }}</label>
                                <select class="form-select form-select-sm" wire:model.defer="v_type">
                                    <option value="numeric">{{ __('Numeric (1234)') }}</option>
                                    <option value="lower">{{ __('Lowercase (abcd)') }}</option>
                                    <option value="upper">{{ __('Uppercase (ABCD)') }}</option>
                                    <option value="mixed">{{ __('Mixed (aBcD)') }}</option>
                                    <option value="alphanumeric_lower">{{ __('AlphaNum Lower (a1b2)') }}</option>
                                    <option value="alphanumeric_upper">{{ __('AlphaNum Upper (A1B2)') }}</option>
                                    <option value="alphanumeric_mixed">{{ __('AlphaNum Mixed (a1B2)') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('Prefix') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model.defer="v_prefix" placeholder="HS-" maxlength="5">
                            </div>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" wire:model.live="v_user_equals_pass" id="v_user_equals_pass">
                            <label class="form-check-label small fw-bold" for="v_user_equals_pass">{{ __('Password = Username (PIN style)') }}</label>
                        </div>
                        @if(!$v_user_equals_pass)
                        <div class="mb-3 p-2 border rounded bg-light">
                            <label class="form-label text-primary"><i class="bi bi-key"></i> {!! __('Password Length :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                            <input type="number" class="form-control form-control-sm" wire:model.defer="v_pwd_length" min="3" max="20">
                        </div>
                        @endif
                        <div class="mb-2">
                            <label class="form-label fw-semibold"><i class="bi bi-clock-history me-1"></i>{{ __('Validity Type') }}</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" wire:model.live="v_validity_type" value="uptime" id="v_vt_uptime" autocomplete="off">
                                <label class="btn btn-outline-primary btn-sm" for="v_vt_uptime">
                                    <i class="bi bi-wifi me-1"></i>{{ __('Uptime') }}
                                    <small class="d-block text-muted" style="font-size:0.65rem">{{ __('Only online time') }}</small>
                                </label>
                                <input type="radio" class="btn-check" wire:model.live="v_validity_type" value="realtime" id="v_vt_realtime" autocomplete="off">
                                <label class="btn btn-outline-danger btn-sm" for="v_vt_realtime">
                                    <i class="bi bi-hourglass-split me-1"></i>{{ __('Real-time') }}
                                    <small class="d-block text-muted" style="font-size:0.65rem">{{ __('Countdown from login') }}</small>
                                </label>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">{{ __('Price') }} ({{ siteUrlSettings('site_currency') ?? 'KES ' }})</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" wire:model.defer="v_price" min="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">
                                    {{ $v_validity_type === 'realtime' ? __('Validity Duration') : __('Limit Uptime') }}
                                    @if($v_validity_type === 'realtime')
                                        <span class="badge bg-danger ms-1" style="font-size:0.6rem">RT</span>
                                    @endif
                                </label>
                                <select class="form-select form-select-sm" wire:model.defer="v_limit_uptime">
                                    <option value="">{{ __('No Limit') }}</option>
                                    <option value="1h">{{ __('1 Hour') }}</option>
                                    <option value="2h">{{ __('2 Hours') }}</option>
                                    <option value="3h">{{ __('3 Hours') }}</option>
                                    <option value="4h">{{ __('4 Hours') }}</option>
                                    <option value="5h">{{ __('5 Hours') }}</option>
                                    <option value="6h">{{ __('6 Hours') }}</option>
                                    <option value="12h">{{ __('12 Hours') }}</option>
                                    <option value="1d">{{ __('1 Day') }}</option>
                                    <option value="2d">{{ __('2 Days') }}</option>
                                    <option value="3d">{{ __('3 Days') }}</option>
                                    <option value="7d">{{ __('7 Days') }}</option>
                                    <option value="30d">{{ __('30 Days') }}</option>
                                </select>
                                @if($v_validity_type === 'realtime')
                                    <div class="form-text text-danger"><small><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Time starts after login, offline time is also deducted') }}</small></div>
                                @endif
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Batch Name') }}</label>
                            <input type="text" class="form-control form-control-sm" wire:model.defer="v_batch_name" placeholder="{{ __('Auto-generated if blank') }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Comment') }}</label>
                            <input type="text" class="form-control form-control-sm" wire:model.defer="v_comment" placeholder="{{ __('e.g. Daily voucher') }}">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" wire:model.defer="v_push_to_router" id="push-to-router">
                            <label class="form-check-label small" for="push-to-router">{{ __('Push to MikroTik router') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100" wire:loading.attr="disabled" wire:target="generateVouchers">
                            <span wire:loading.remove wire:target="generateVouchers"><i class="bi bi-magic me-1"></i>{{ __('Generate Vouchers') }}</span>
                            <span wire:loading wire:target="generateVouchers"><span class="spinner-border spinner-border-sm me-1"></span>{{ __('Generating...') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Voucher List + Print --}}
        <div class="col-lg-8">
            {{-- Stats row --}}
            <div class="row g-2 mb-3">
                <div class="col-4">
                    <div class="card text-center py-2">
                        <div class="fw-bold fs-5 text-primary">{{ $totalVouchers }}</div>
                        <small class="text-muted">{{ __('Total') }}</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card text-center py-2">
                        <div class="fw-bold fs-5 text-success">{{ $totalVouchers - $usedVouchers }}</div>
                        <small class="text-muted">{{ __('Unused') }}</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card text-center py-2">
                        <div class="fw-bold fs-5 text-warning">{{ $usedVouchers }}</div>
                        <small class="text-muted">{{ __('Used') }}</small>
                    </div>
                </div>
            </div>

            {{-- Batch overview --}}
            @if($voucherBatches->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <span class="small fw-bold"><i class="bi bi-collection me-1"></i>{{ __('Batches') }}</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0 hs-table">
                        <thead><tr><th>{{ __('Batch') }}</th><th>{{ __('Profile') }}</th><th>{{ __('Price') }}</th><th>{{ __('Unused') }}</th><th>{{ __('Used') }}</th><th class="no-print">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                        @foreach($voucherBatches as $b)
                        <tr wire:key="batch-row-{{ $b->batch_name }}-{{ $loop->index }}">
                            <td><code class="small">{{ $b->batch_name }}</code></td>
                            <td><span class="badge badge-profile">{{ $b->profile }}</span></td>
                            <td><small>{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($b->price,0) }}</small></td>
                            <td><span class="badge bg-success">{{ $b->unused_count }}</span></td>
                            <td><span class="badge bg-secondary">{{ $b->used_count }}</span></td>
                            <td class="no-print">
                                <button class="btn btn-xs btn-outline-primary"
                                    wire:click="triggerPrintBatch('{{ $b->batch_name }}')" title="{{ __('Print batch') }}">
                                    <span wire:loading.remove wire:target="triggerPrintBatch('{{ $b->batch_name }}')"><i class="bi bi-printer"></i></span>
                                    <span wire:loading wire:target="triggerPrintBatch('{{ $b->batch_name }}')"><span class="spinner-border spinner-border-sm"></span></span>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-danger"
                                    wire:click="confirmAction('deleteVoucherBatch', @js($b->batch_name), @js(__('Delete all UNUSED vouchers in batch \':name\'?', ['name' => $b->batch_name])))">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Filter --}}
            <div class="d-flex gap-2 mb-2 flex-wrap no-print">
                <div class="btn-group btn-group-sm">
                    @foreach(['all','unused','used','expired'] as $f)
                    <button class="btn {{ $voucherFilter===$f?'btn-primary':'btn-outline-secondary' }}"
                        wire:click="$set('voucherFilter','{{ $f }}')">{{ __(ucfirst($f)) }}</button>
                    @endforeach
                </div>
                <input type="text" class="form-control form-control-sm w-auto ms-auto" wire:model.live.debounce.300ms="voucherSearch" placeholder="{{ __('🔍 Search code / batch...') }}">
                <button class="btn btn-sm btn-outline-primary" wire:click="forceSyncVouchers" wire:loading.attr="disabled" title="{{ __('Sync DB with Router') }}">
                    <span wire:loading.remove wire:target="forceSyncVouchers"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Sync Vouchers') }}</span>
                    <span wire:loading wire:target="forceSyncVouchers" class="spinner-border spinner-border-sm"></span>
                </button>
                <button class="btn btn-sm btn-outline-success" wire:click="triggerPrintAll" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="triggerPrintAll"><i class="bi bi-printer me-1"></i>{{ __('Print All') }}</span>
                    <span wire:loading wire:target="triggerPrintAll" class="spinner-border spinner-border-sm"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmAction('deleteAllFilteredVouchers', '', @js(__('Are you sure you want to delete ALL vouchers matching the current filter?')))" wire:loading.attr="disabled">
                    <i class="bi bi-trash me-1"></i>{{ __('Delete All') }}
                </button>
            </div>

            {{-- Voucher Table --}}
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:420px;overflow-y:auto">
                        <table class="table table-sm align-middle mb-0 hs-table" wire:key="tbl-vouchers-{{ $selectedRouter }}">
                            <thead>
                                <tr>
                                    <th width="60"></th>
                                    <th>{{ __('Server') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th class="text-center">{{ __('Print') }}</th>
                                    <th>{{ __('Profile') }}</th>
                                    <th>{{ __('MAC Address') }}</th>
                                    <th>{{ __('Uptime') }}</th>
                                    <th>{{ __('Bytes In') }}</th>
                                    <th>{{ __('Bytes Out') }}</th>
                                    <th>{{ __('Comment') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($vouchers as $v)
                            @php 
                                $rUser = collect($users)->firstWhere('name', $v->username);
                                $uptime = $rUser['uptime'] ?? '00:00:00';
                                $bIn = (int)($rUser['bytes-in'] ?? 0);
                                $bOut = (int)($rUser['bytes-out'] ?? 0);
                                
                                $formatBytes = function($b) {
                                    if ($b == 0) return '0 Byte';
                                    if ($b < 1024) return $b . ' Byte';
                                    if ($b < 1048576) return number_format($b/1024, 0) . ' KiB';
                                    if ($b < 1073741824) return number_format($b/1048576, 0) . ' MiB';
                                    return number_format($b/1073741824, 1) . ' GiB';
                                };
                            @endphp
                            <tr wire:key="vc-{{ $v->id }}">
                                <td>
                                    <div class="d-flex gap-1 justify-content-center no-print">
                                        <button type="button" class="btn btn-link p-1 text-danger border-0" 
                                                wire:click="confirmAction('deleteSingleVoucher', {{ $v->id }}, @js(__('Permanent delete voucher :name?', ['name' => $v->username])))" title="{{ __('Remove') }}">
                                            <i class="bi bi-dash-square-fill icon-action"></i>
                                        </button>
                                        
                                        @php $isUserDisabled = collect($users)->firstWhere('name', $v->username)['disabled'] ?? 'false'; @endphp
                                        <button type="button" class="btn btn-link p-1 border-0" 
                                                wire:click="toggleUserStatus('{{ $v->username }}', '{{ $isUserDisabled }}')"
                                                title="{{ $isUserDisabled === 'true' ? __('Enable') : __('Disable') }}">
                                            <i class="bi bi-{{ $isUserDisabled === 'true' ? 'lock-fill text-danger' : 'unlock-fill text-success' }} icon-action"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>all</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <strong>{{ $v->username }}</strong>
                                        <button type="button" class="btn btn-link p-1 ms-1 border-0" wire:click="editVoucher({{ $v->id }})" title="{{ __('Edit') }}">
                                            <i class="bi bi-pencil-square icon-edit icon-action"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1 no-print">
                                        <button type="button" class="btn btn-link p-1 border-0" wire:click="triggerPrintSingle({{ $v->id }}, 'no' )" title="{{ __('Print card') }}">
                                            <i class="bi bi-printer icon-print icon-action"></i>
                                        </button>
                                        <button type="button" class="btn btn-link p-1 border-0" wire:click="triggerPrintSingle({{ $v->id }}, 'yes' )" title="{{ __('Print QR only') }}">
                                            <i class="bi bi-qr-code icon-qr icon-action"></i>
                                        </button>
                                    </div>
                                </td>
                                <td><span class="badge-profile">{{ $v->profile }}</span></td>
                                <td><small>{{ $v->mac_address ?: '' }}</small></td>
                                <td><small>{{ $uptime }}</small></td>
                                <td><small>{{ $formatBytes($bIn) }}</small></td>
                                <td><small>{{ $formatBytes($bOut) }}</small></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-search icon-comment"></i>
                                        <small class="text-muted text-truncate" style="max-width: 150px;">{{ $v->batch_name }} {{ $v->note }}</small>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">{{ __('No vouchers found.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-2 no-print">{{ $vouchers->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    @endif

    {{-- ====================================================================== --}}
    {{-- PROFILES TAB --}}
    {{-- ====================================================================== --}}
    @if($activeTab==='profiles')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header {{ $editUserProfileId ? 'bg-warning text-dark' : 'bg-info text-dark' }} d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-{{ $editUserProfileId ? 'pencil-square' : 'plus-circle' }} me-1"></i>
                        {{ $editUserProfileId ? __('Edit User Profile') : __('Add User Profile') }}
                    </span>
                    @if($editUserProfileId)
                    <button type="button" class="btn btn-sm btn-outline-dark" wire:click="startAddUserProfile" title="{{ __('Clear form / Add new') }}">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('New') }}
                    </button>
                    @endif
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="addUserProfile">
                        <div class="mb-2">
                            <label class="form-label">{!! __('Profile Name :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                            <input type="text" class="form-control form-control-sm" wire:model="up_name" placeholder="1Hour / 1Day">
                            @error('up_name')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">{{ __('Shared Users') }}</label>
                                <input type="number" class="form-control form-control-sm" wire:model="up_shared_users" min="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('Rate Limit') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="up_rate_limit" placeholder="2M/2M">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">{!! __('Session Timeout :info', ['info' => '<small class="text-muted">(ex: 60m,24h,1d,1d1h1m)</small>']) !!}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="up_session_timeout" placeholder="60m">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{!! __('Idle Timeout :info', ['info' => '<small class="text-muted">(ex: 60m,24h,1d,1d1h1m)</small>']) !!}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="up_idle_timeout" placeholder="30m">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">{{ __('Status Auto-Refresh') }}</label>
                                <select class="form-select form-select-sm" wire:model.live="up_status_autorefresh">
                                    <option value="">{{ __('None') }}</option>
                                    <option value="1m">{{ __('1 minute') }}</option>
                                    <option value="3m">{{ __('3 minutes') }}</option>
                                    <option value="5m">{{ __('5 minutes') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('Address Pool') }}</label>
                                <select class="form-select form-select-sm" wire:model.live="up_address_pool">
                                    <option value="none">{{ __('None') }}</option>
                                    @foreach($ipPools as $pool)
                                        <option value="{{ $pool['name'] }}">{{ $pool['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-info btn-sm flex-fill text-dark" wire:loading.attr="disabled" wire:target="addUserProfile">
                                <span wire:loading.remove wire:target="addUserProfile"><i class="bi bi-{{ $editUserProfileId ? 'save' : 'plus-lg' }} me-1"></i>{{ $editUserProfileId ? __('Update') : __('Add Profile') }}</span>
                                <span wire:loading wire:target="addUserProfile"><span class="spinner-border spinner-border-sm me-1"></span>{{ __('Saving...') }}</span>
                            </button>
                            @if($editUserProfileId)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelEditUserProfile">{{ __('Cancel') }}</button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- Linked packages from billing DB --}}
            <div class="card mt-3 border-0 shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="small fw-bold"><i class="bi bi-box-seam me-1"></i>{{ __('Packages in DB') }}</span>
                    <button class="btn btn-xs btn-primary" wire:click="syncDatabasePackages" wire:loading.attr="disabled">
                        <i class="bi bi-arrow-repeat me-1"></i>{{ __('Sync to Router') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    @forelse($hotspotPackages as $pkg)
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom small">
                        <div>
                            <strong>{{ $pkg->package }}</strong>
                            @if($pkg->mikrotik_rate_limit)<br><code class="x-small text-danger">{{ $pkg->mikrotik_rate_limit }}</code>@endif
                        </div>
                        <span class="badge bg-light text-dark border">{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($pkg->price,0) }}</span>
                    </div>
                    @empty
                    <div class="p-3 text-center text-muted x-small">{{ __('No packages in database.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-badge me-1 text-info"></i>{!! __('User Profiles on :router', ['router' => '<strong>' . e($selectedRouter) . '</strong>']) !!}
                        <span class="badge bg-secondary ms-1">{{ count($userProfiles) }}</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-info text-dark" wire:click="startAddUserProfile">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Add Profile') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 hs-table data-table" wire:key="tbl-up-{{ $selectedRouter }}">
                            <thead><tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Rate Limit') }}</th>
                                <th>{{ __('Shared') }}</th>
                                <th>{{ __('Session TO') }}</th>
                                <th>{{ __('Idle TO') }}</th>
                                <th>{{ __('Address Pool') }}</th>
                                <th class="no-print">{{ __('Actions') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($userProfiles as $p)
                            <tr wire:key="up-{{ $loop->index }}">
                                <td><strong class="text-primary">{{ $p['name'] ?? '-' }}</strong></td>
                                <td>
                                    @if($p['rate-limit'] ?? '')
                                        <code class="text-danger small">{{ $p['rate-limit'] }}</code>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-primary">{{ $p['shared-users'] ?? '1' }}</span></td>
                                <td>
                                    @php $st = $p['session-timeout'] ?? ''; @endphp
                                    <span class="badge {{ $st && $st !== '0s' ? 'bg-success' : 'bg-light text-muted border' }}">
                                        {{ ($st && $st !== '0s') ? $st : '∞' }}
                                    </span>
                                </td>
                                <td>
                                    @php $it = $p['idle-timeout'] ?? ''; @endphp
                                    <span class="badge {{ $it && $it !== '0s' ? 'bg-warning text-dark' : 'bg-light text-muted border' }}">
                                        {{ ($it && $it !== '0s') ? $it : '∞' }}
                                    </span>
                                </td>
                                <td>
                                    @php $pool = $p['address-pool'] ?? 'none'; @endphp
                                    <span class="badge {{ $pool !== 'none' && $pool !== '' ? 'bg-info text-dark' : 'bg-light text-muted border' }}">
                                        {{ $pool ?: 'none' }}
                                    </span>
                                </td>
                                <td class="no-print">
                                    <button class="btn btn-warning btn-sm" wire:click="editUserProfileByName(@js($p['name'] ?? ''))" title="{{ __('Edit') }}"><i class="bi bi-pencil-square"></i></button>
                                    @if(($p['default'] ?? 'no') === 'no')
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmAction('removeUserProfile', @js($p['name'] ?? ''), @js(__('Remove profile \':name\'?', ['name' => $p['name'] ?? ''])))"><i class="bi bi-trash"></i></button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No user profiles found.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


        </div>
    </div>
    @endif

    {{-- ====================================================================== --}}
    {{-- INCOME TAB --}}
    {{-- ====================================================================== --}}
    @if($activeTab==='income')
    <div class="row g-3">
        {{-- Record Sale Form --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white"><i class="bi bi-cash-coin me-1"></i>{{ __('Record Sale / Payment') }}</div>
                <div class="card-body">
                    <form wire:submit.prevent="recordSale">
                        <div class="mb-2">
                            <label class="form-label">{!! __('Voucher Code :info', ['info' => '<small class="text-muted">(' . __('Optional') . ')</small>']) !!}</label>
                            <input type="text" class="form-control form-control-sm" wire:model.live="s_voucher_code" placeholder="{{ __('Auto-fills user & price') }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{!! __('Username :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                            <input type="text" class="form-control form-control-sm" wire:model.defer="s_username" placeholder="{{ __('Hotspot username') }}">
                            @error('s_username')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{!! __('Profile :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                            <select class="form-select form-select-sm" wire:model.defer="s_profile">
                                <option value="">{{ __('-- Profile --') }}</option>
                                @foreach($userProfiles as $p)
                                    <option value="{{ $p['name'] }}">{{ $p['name'] }}</option>
                                @endforeach
                            </select>
                            @error('s_profile')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-7">
                                <label class="form-label">{!! __('Amount (:currency) :info', ['currency' => siteUrlSettings('site_currency') ?? 'KES ', 'info' => '<span class="text-danger">*</span>']) !!}</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" wire:model.defer="s_amount">
                                @error('s_amount')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-5">
                                <label class="form-label">{{ __('Method') }}</label>
                                <select class="form-select form-select-sm" wire:model.defer="s_payment_method">
                                    <option value="cash">{{ __('Cash') }}</option>
                                    <option value="bkash">{{ __('bKash') }}</option>
                                    <option value="nagad">{{ __('Nagad') }}</option>
                                    <option value="rocket">{{ __('Rocket') }}</option>
                                    <option value="card">{{ __('Card') }}</option>
                                    <option value="online">{{ __('Online') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Date') }}</label>
                            <input type="date" class="form-control form-control-sm" wire:model.defer="s_date">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Note') }}</label>
                            <input type="text" class="form-control form-control-sm" wire:model.defer="s_note" placeholder="{{ __('Optional note') }}">
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100" wire:loading.attr="disabled" wire:target="recordSale">
                            <span wire:loading.remove wire:target="recordSale"><i class="bi bi-plus-lg me-1"></i>{{ __('Record Sale') }}</span>
                            <span wire:loading wire:target="recordSale"><span class="spinner-border spinner-border-sm me-1"></span>{{ __('Saving...') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Sales list --}}
        <div class="col-lg-8">
            <div class="row g-2 mb-3">
                <div class="col-4">
                    <div class="card text-center py-3">
                        <div class="text-xl font-semibold tabular-nums text-emerald-600">{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($todayIncome,2) }}</div>
                        <small class="text-muted">{{ __('Today') }}</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card text-center py-3">
                        <div class="text-xl font-semibold tabular-nums text-sky-600">{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($monthIncome,2) }}</div>
                        <small class="text-muted">{{ __('This Month') }}</small>
                    </div>
                </div>
                <div class="col-4 d-flex align-items-center gap-1 flex-wrap">
                    <input type="date" class="form-control form-control-sm" style="width: auto" wire:model.live="report_from">
                    <span class="small">{{ __('To') }}</span>
                    <input type="date" class="form-control form-control-sm" style="width: auto" wire:model.live="report_to">
                    <button class="btn btn-sm btn-secondary py-1 px-2" style="font-size: 0.75rem" wire:click="$set('report_from', '{{ now()->startOfYear()->toDateString() }}'); $set('report_to', '{{ now()->toDateString() }}')">{{ __('This Year') }}</button>
                    <button class="btn btn-sm btn-secondary py-1 px-2" style="font-size: 0.75rem" wire:click="$set('report_from', '{{ now()->startOfMonth()->toDateString() }}'); $set('report_to', '{{ now()->toDateString() }}')">{{ __('This Month') }}</button>
                    <button class="btn btn-sm btn-secondary py-1 px-2" style="font-size: 0.75rem" wire:click="$set('report_from', '{{ now()->toDateString() }}'); $set('report_to', '{{ now()->toDateString() }}')">{{ __('Today') }}</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="bi bi-receipt me-1"></i>{{ __('Sales Records') }}</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 hs-table data-table" wire:key="tbl-sales-{{ $selectedRouter }}">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('User') }}</th><th>{{ __('Profile') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Method') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Note') }}</th><th class="no-print">{{ __('Del') }}</th></tr></thead>
                            <tbody>
                            @forelse($sales as $s)
                            <tr wire:key="sale-{{ $s->id }}">
                                <td><small>{{ $s->sale_date->format('d M Y') }}</small></td>
                                <td><strong class="small">{{ $s->username }}</strong></td>
                                <td><span class="badge badge-profile small">{{ $s->profile }}</span></td>
                                <td><strong class="text-emerald-600 tabular-nums">{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($s->amount,2) }}</strong></td>
                                <td><span class="badge bg-secondary">{{ $s->payment_method }}</span></td>
                                <td><code class="small">{{ $s->voucher_code ?? '—' }}</code></td>
                                <td><small class="text-muted">{{ $s->note }}</small></td>
                                <td class="no-print">
                                    <button class="btn btn-xs btn-outline-danger" wire:click="confirmAction('deleteSale', {{ $s->id }}, @js(__('Delete this sale record?')))"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No sales recorded for selected period.') }}</td></tr>
                            @endforelse
                            </tbody>
                            @if($sales->isNotEmpty())
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end fw-semibold small">{{ __('Total:') }}</td>
                                    <td><strong class="text-emerald-600 tabular-nums">{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($sales->sum('amount'),2) }}</strong></td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ====================================================================== --}}
    {{-- REPORT TAB --}}
    {{-- ====================================================================== --}}
    @if($activeTab==='report')
    @php
        $r = $reportData;
        $dailyData = $r['daily'] ?? collect();
        $byProfile = $r['byProfile'] ?? collect();
    @endphp
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label small">{{ __('From') }}</label>
            <input type="date" class="form-control form-control-sm" wire:model.live="report_from">
        </div>
        <div class="col-md-4">
            <label class="form-label small">{{ __('To') }}</label>
            <input type="date" class="form-control form-control-sm" wire:model.live="report_to">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-1">
            <button class="btn btn-sm btn-secondary" wire:click="$set('report_from', '{{ now()->startOfMonth()->toDateString() }}'); $set('report_to', '{{ now()->toDateString() }}')">{{ __('This Month') }}</button>
            <button class="btn btn-sm btn-secondary" wire:click="$set('report_from', '{{ now()->subMonth()->startOfMonth()->toDateString() }}'); $set('report_to', '{{ now()->subMonth()->endOfMonth()->toDateString() }}')">{{ __('Last Month') }}</button>
            <button class="btn btn-sm btn-primary ms-auto" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <x-ui.stat-card
                :label="__('Total Income (:from → :to)', ['from' => e($r['from'] ?? ''), 'to' => e($r['to'] ?? '')])"
                :value="(siteUrlSettings('site_currency') ?? 'KES ') . number_format($r['total'] ?? 0, 2)"
                color="green"
            >
                <x-slot:icon><i class="bi bi-cash-stack"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
        <div class="col-md-4">
            <x-ui.stat-card
                :label="__('Total Sales')"
                :value="$r['count'] ?? 0"
                color="purple"
            >
                <x-slot:icon><i class="bi bi-receipt"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
        <div class="col-md-4">
            <x-ui.stat-card
                :label="__('Avg Per Sale')"
                :value="(siteUrlSettings('site_currency') ?? 'KES ') . ($r['count'] > 0 ? number_format(($r['total'] ?? 0) / $r['count'], 2) : '0.00')"
                color="orange"
            >
                <x-slot:icon><i class="bi bi-graph-up-arrow"></i></x-slot:icon>
            </x-ui.stat-card>
        </div>
    </div>

    <div class="row g-3">
        {{-- Daily breakdown --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header"><i class="bi bi-bar-chart me-1 text-primary"></i>{{ __('Daily Income Breakdown') }}</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 hs-table data-table">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Sales Count') }}</th><th>{{ __('Total Amount') }}</th><th>{{ __('% of Period') }}</th></tr></thead>
                            <tbody>
                            @php $grandTotal = $r['total'] ?? 0; @endphp
                            @forelse($dailyData as $date => $daySales)
                            @php $dayTotal = $daySales->sum('amount'); @endphp
                            <tr>
                                <td><strong class="small">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</strong></td>
                                <td><span class="badge bg-primary">{{ $daySales->count() }}</span></td>
                                <td><strong class="text-success">{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($dayTotal,2) }}</strong></td>
                                <td>
                                    @if($grandTotal > 0)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-fill" style="height:6px">
                                            <div class="progress-bar bg-success" style="width:{{ round($dayTotal/$grandTotal*100) }}%"></div>
                                        </div>
                                        <small>{{ round($dayTotal/$grandTotal*100) }}%</small>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No data for selected period.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- By Profile --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header"><i class="bi bi-pie-chart me-1 text-info"></i>{{ __('Income by Profile') }}</div>
                <div class="card-body p-0">
                    @forelse($byProfile as $bp)
                    <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge badge-profile">{{ $bp['profile'] }}</span>
                            <small class="text-muted ms-1">×{{ $bp['count'] }}</small>
                        </div>
                        <strong class="text-success small">{{ siteUrlSettings('site_currency') ?? 'KES ' }}{{ number_format($bp['total'],2) }}</strong>
                    </div>
                    @empty
                    <div class="text-center text-muted py-4 small">{{ __('No profile data.') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Voucher summary --}}
            <div class="card mt-3 border-0 shadow-sm">
                <div class="card-header"><i class="bi bi-ticket me-1 text-warning"></i>{{ __('Voucher Summary') }}</div>
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between small border-bottom py-1"><span>{{ __('Total Vouchers') }}</span><strong>{{ $totalVouchers }}</strong></div>
                    <div class="d-flex justify-content-between small border-bottom py-1"><span>{{ __('Used') }}</span><strong class="text-warning">{{ $usedVouchers }}</strong></div>
                    <div class="d-flex justify-content-between small py-1"><span>{{ __('Unused') }}</span><strong class="text-success">{{ $totalVouchers - $usedVouchers }}</strong></div>
                </div>
            </div>
        </div>
    </div>
    @endif {{-- end report tab --}}

    {{-- ====================================================================== --}}
    {{-- SETUP TAB --}}
    {{-- ====================================================================== --}}
    @if($activeTab==='setup')
    <div class="row g-3">
        {{-- ── Left Column: Server Overview + Server Profiles ── --}}
        <div class="col-lg-6">
            {{-- Hotspot Servers --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-server text-primary"></i>
                    <strong>{{ __('Hotspot Servers') }}</strong>
                    <span class="badge bg-secondary ms-auto">{{ count($servers) }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 hs-table" wire:key="setup-srv">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Interface') }}</th><th>{{ __('Address Pool') }}</th><th>{{ __('Profile') }}</th></tr></thead>
                            <tbody>
                                @forelse($servers as $s)
                                <tr wire:key="s-{{ $loop->index }}">
                                    <td><strong>{{ $s['name'] ?? '-' }}</strong></td>
                                    <td><span class="badge bg-secondary">{{ $s['interface'] ?? '-' }}</span></td>
                                    <td><code>{{ $s['address-pool'] ?? 'none' }}</code></td>
                                    <td><small>{{ $s['profile'] ?? '-' }}</small></td>
                                </tr>
                                @empty
                                Middle:
                                <tr><td colspan="4" class="text-center text-muted py-3">{{ __('No hotspot servers found.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Server Profiles --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text text-info"></i>
                    <strong>{{ __('Server Profiles') }}</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 hs-table" wire:key="setup-sprof">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Hotspot Address') }}</th><th>{{ __('DNS Name') }}</th><th>{{ __('Login By') }}</th></tr></thead>
                            <tbody>
                                @forelse($profiles as $p)
                                <tr wire:key="sp-{{ $loop->index }}">
                                    <td><strong>{{ $p['name'] ?? '-' }}</strong></td>
                                    <td><code>{{ $p['hotspot-address'] ?? '-' }}</code></td>
                                    <td><small>{{ $p['dns-name'] ?? '-' }}</small></td>
                                    <td><small class="text-muted">{{ $p['login-by'] ?? '-' }}</small></td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">{{ __('No server profiles found.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Right Column: Quick Stats + Link to Profiles Tab ── --}}
        <div class="col-lg-6">
            {{-- Active Sessions Summary --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-activity text-success"></i>
                    <strong>{{ __('Live Status') }}</strong>
                    <button class="btn btn-xs btn-outline-success ms-auto" wire:click="refreshSessions">
                        <i class="bi bi-arrow-clockwise"></i> {{ __('Refresh') }}
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="fs-4 fw-bold text-success">{{ $onlineCount }}</div>
                                <small class="text-muted">{{ __('Online') }}</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="fs-4 fw-bold text-primary">{{ count($users) }}</div>
                                <small class="text-muted">{{ __('Total Users') }}</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="fs-4 fw-bold text-info">{{ count($userProfiles) }}</div>
                                <small class="text-muted">{{ __('Profiles') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- User Profiles Quick View (Read-only, manage in Profiles tab) --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-person-badge text-info"></i>
                    <strong>{{ __('User Profiles') }}</strong>
                    <span class="badge bg-info text-dark ms-auto">{{ count($userProfiles) }}</span>
                    <button class="btn btn-xs btn-outline-primary" wire:click="$set('activeTab','profiles')">
                        <i class="bi bi-pencil-square me-1"></i>{{ __('Manage') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:300px;overflow-y:auto">
                        <table class="table table-sm align-middle mb-0 hs-table" wire:key="setup-uprof-ro">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Rate Limit') }}</th><th>{{ __('Shared') }}</th><th>{{ __('Session Timeout') }}</th></tr></thead>
                            <tbody>
                                @forelse($userProfiles as $p)
                                <tr wire:key="up-ro-{{ $loop->index }}">
                                    <td><strong>{{ $p['name'] ?? '-' }}</strong></td>
                                    <td><code class="text-danger small">{{ $p['rate-limit'] ?? '—' }}</code></td>
                                    <td><span class="badge bg-primary">{{ $p['shared-users'] ?? 1 }}</span></td>
                                    <td><small class="text-muted">{{ $p['session-timeout'] ?? '—' }}</small></td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3 small">{!! __('No profiles. Go to :link to add.', ['link' => '<strong>' . __('Profiles') . '</strong>']) !!}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ====================================================================== --}}
    {{-- USER MODAL — lives outside all tab conditionals so it works from any tab --}}
    {{-- (editVoucher, editUserByName, startAddUser all dispatch open-modal) --}}
    {{-- ====================================================================== --}}
    <div class="modal fade" id="userModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-{{ $editUserId ? 'pencil-square' : 'person-plus' }} me-1 text-orange-500"></i>
                        {{ $editUserId ? __('Edit Hotspot User') : __('Add Hotspot User') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form wire:submit.prevent="addUser" id="user-form">
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label fw-semibold">{!! __('Username :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                                <input type="text" class="form-control form-control-sm @error('u_name') is-invalid @enderror"
                                       wire:model="u_name" id="u_name" placeholder="{{ __('e.g. john123') }}">
                                @error('u_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">{!! __('Password :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                                <input type="text" class="form-control form-control-sm @error('u_password') is-invalid @enderror"
                                       wire:model="u_password" id="u_password" placeholder="{{ __('Password') }}">
                                @error('u_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">{!! __('Profile :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                                <select class="form-select form-select-sm @error('u_profile') is-invalid @enderror"
                                        wire:model="u_profile" id="u_profile">
                                    @forelse($userProfiles as $p)
                                        <option value="{{ $p['name'] }}">{{ $p['name'] }}</option>
                                    @empty
                                        <option value="default">default</option>
                                    @endforelse
                                </select>
                                @error('u_profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold"><i class="bi bi-clock-history me-1"></i>{{ __('Validity Type') }}</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" wire:model.live="u_validity_type" value="uptime" id="u_vt_uptime" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="u_vt_uptime">
                                        <i class="bi bi-wifi me-1"></i>{{ __('Uptime') }}
                                        <small class="d-block text-muted" style="font-size:0.65rem">{{ __('Only online time') }}</small>
                                    </label>
                                    <input type="radio" class="btn-check" wire:model.live="u_validity_type" value="realtime" id="u_vt_realtime" autocomplete="off">
                                    <label class="btn btn-outline-danger btn-sm" for="u_vt_realtime">
                                        <i class="bi bi-hourglass-split me-1"></i>{{ __('Real-time') }}
                                        <small class="d-block text-muted" style="font-size:0.65rem">{{ __('Countdown from login') }}</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">
                                    {{ $u_validity_type === 'realtime' ? __('Validity Duration') : __('Limit Uptime') }}
                                    @if($u_validity_type === 'realtime')
                                        <span class="badge bg-danger ms-1" style="font-size:0.6rem">RT</span>
                                    @endif
                                    <small class="text-muted">(ex: 60m)</small>
                                </label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="u_limit_uptime" placeholder="60m">
                                @if($u_validity_type === 'realtime')
                                    <div class="form-text text-danger"><small><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Time starts after login, offline time is also deducted') }}</small></div>
                                @else
                                    <div class="form-text">{{ __('Leave blank for unlimited.') }}</div>
                                @endif
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('Limit Bytes (Total)') }}</label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="u_limit_bytes" placeholder="{{ __('e.g. 1073741824 (1GB)') }}">
                                <div class="form-text">{{ __('Bytes. Leave blank for unlimited.') }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Comment') }}</label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="u_comment" placeholder="{{ __('Optional note...') }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" form="user-form" class="btn btn-primary btn-sm"
                                wire:loading.attr="disabled" wire:target="addUser">
                            <span wire:loading.remove wire:target="addUser">
                                <i class="bi bi-{{ $editUserId ? 'save' : 'plus-lg' }} me-1"></i>
                                {{ $editUserId ? __('Update User') : __('Add User') }}
                            </span>
                            <span wire:loading wire:target="addUser">
                                <span class="spinner-border spinner-border-sm me-1"></span>{{ __('Saving...') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Print area: inside router branch so Users + Vouchers tabs can print from any active tab --}}
    <div id="voucher-print-area" style="display:none">
        <div style="text-align:center;margin-bottom:12px;font-size:1.1rem;font-weight:700">{!! __('🛜 Hotspot Vouchers &mdash; :router', ['router' => e($selectedRouter)]) !!}</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-start" id="voucher-cards-container">
        </div>
    </div>

    @endif {{-- end $selectedRouter --}}

    @push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            let salesChart = null;

            const initChart = () => {
                const el = document.getElementById('salesChart');
                if (!el) return;

                if (salesChart) salesChart.destroy();

                const rawChart = @json($chartData ?? ['labels' => [], 'data' => []]);
                const chartData = {
                    labels: Array.isArray(rawChart.labels) ? rawChart.labels : [],
                    data:   Array.isArray(rawChart.data)   ? rawChart.data   : [],
                };
                const options = {
                    series: [{
                        name: '{{ __('Daily Sales') }}',
                        data: chartData.data
                    }],
                    chart: {
                        height: 200,
                        type: 'area',
                        toolbar: { show: false },
                        zoom: { enabled: false },
                        sparkline: { enabled: false }
                    },
                    colors: ['#6366f1'],
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', width: 3 },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.45,
                            opacityTo: 0.05,
                            stops: [20, 100]
                        }
                    },
                    xaxis: {
                        categories: chartData.labels,
                        axisBorder: { show: false },
                        axisTicks: { show: false }
                    },
                    yaxis: { show: false },
                    grid: { show: false }
                };

                salesChart = new ApexCharts(el, options);
                salesChart.render();
            };

            initChart();
            Livewire.on('reinit-chart', () => { setTimeout(initChart, 100); });

            Livewire.on('print-vouchers', (...args) => {
                let vouchers = [];
                let batch = 'Voucher';
                let router = '';

                // Log arguments for debugging
                console.log('print-vouchers triggered with args:', args);

                const firstArg = args[0];
                if (firstArg) {
                    if (Array.isArray(firstArg)) {
                        // Case 1: First argument is the vouchers array (Livewire 3 positional/spread args)
                        vouchers = firstArg;
                        batch = args[1] || 'Voucher';
                        router = args[2] || '';
                    } else if (typeof firstArg === 'object') {
                        // Case 2: First argument is an object with vouchers property (Livewire 3 named arguments wrapped in object)
                        if (firstArg.vouchers && Array.isArray(firstArg.vouchers)) {
                            vouchers = firstArg.vouchers;
                            batch = firstArg.batch || 'Voucher';
                            router = firstArg.router || '';
                        }
                        // Case 3: First argument is a nested array of arguments
                        else if (firstArg[0] && firstArg[0].vouchers && Array.isArray(firstArg[0].vouchers)) {
                            vouchers = firstArg[0].vouchers;
                            batch = firstArg[0].batch || 'Voucher';
                            router = firstArg[0].router || '';
                        }
                    }
                }

                const container = document.getElementById('voucher-cards-container');
                const area = document.getElementById('voucher-print-area');
                if (!container || !area || !vouchers || !vouchers.length) {
                    console.warn('print-vouchers: missing DOM or empty vouchers', { container, area, vouchers });
                    return;
                }

                container.innerHTML = '';

                vouchers.forEach((v, index) => {
                    const id = 'qr-' + index;
                    const cardHtml = `
                        <div class="voucher-premium" style="break-inside: avoid; margin-bottom: 8px;">
                            <div class="voucher-header">
                                <i class="bi bi-wifi text-primary me-1"></i>
                                {{ siteUrlSettings('site_title') ?? config('app.name') }}
                            </div>
                            <div class="voucher-body">
                                ${v.qr_code === 'yes' ? `<canvas id="${id}" class="voucher-qr"></canvas>` : ''}
                                ${v.username === v.password ? `
                                    <div class="voucher-pass" style="font-size: 0.72rem; color: #64748b; margin-bottom: 2px; text-transform: uppercase; font-weight: bold; width:100%;text-align:center;">
                                        {{ __('User & Pass') }}
                                    </div>
                                    <div class="voucher-user" style="font-size: 1.35rem; font-weight: 900; color: #1e293b; letter-spacing: 1.5px; margin-bottom: 8px; width:100%;text-align:center;">
                                        ${v.username}
                                    </div>
                                ` : `
                                    <div class="voucher-user" style="width:100%;text-align:center;">${v.username}</div>
                                    <div class="voucher-pass" style="width:100%;text-align:center;">
                                        <span style="color:#64748b;font-weight:normal;">{{ __('Password:') }}</span> ${v.password}
                                    </div>
                                `}
                                <div class="voucher-info">
                                    <div class="voucher-info-item" style="width:100%;text-align:center;">
                                        <span class="voucher-info-label">{{ __('Profile') }}</span>
                                        <span class="voucher-info-value">${v.profile}</span>
                                    </div>
                                    <div class="voucher-info-item" style="width:100%;text-align:center;">
                                        <span class="voucher-info-label">{{ __('Price') }}</span>
                                        <span class="voucher-info-value">{{ siteUrlSettings('site_currency') ?? 'KES ' }}${v.price}</span>
                                    </div>
                                </div>
                                <div class="voucher-footer" style="width:100%;text-align:center;">
                                    {{ str_replace(['http://', 'https://'], '', config('app.url')) }} • ${router}
                                </div>
                            </div>
                        </div>`;

                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = cardHtml;
                    container.appendChild(wrapper.firstElementChild);

                    if (v.qr_code === 'yes') {
                        const qrCanvas = document.getElementById(id);
                        if (qrCanvas && typeof QRCode !== 'undefined') {
                            QRCode.toCanvas(qrCanvas, v.username, {
                                width: 80,
                                margin: 1,
                                color: {
                                    dark: "#1e293b",
                                    light: "#ffffff"
                                }
                            }, (err) => { if (err) console.error(err); });
                        }
                    }
                });

                // Temporarily move print area to body to bypass nested wrapper overflow/display issues
                const originalParent = area.parentNode;
                const originalNextSibling = area.nextSibling;
                document.body.appendChild(area);

                area.style.display = 'block';

                setTimeout(() => {
                    try {
                        if (typeof Window.prototype.print === 'function') {
                            Window.prototype.print.call(window);
                        } else {
                            window.print();
                        }
                    } catch (e) {
                        console.error('Native print call failed, attempting print-js fallback:', e);
                        // Fallback using print-js
                        if (typeof window.print === 'function') {
                            try {
                                window.print({
                                    printable: 'voucher-print-area',
                                    type: 'html',
                                    targetStyles: ['*']
                                });
                            } catch (err) {
                                console.error('print-js fallback also failed:', err);
                            }
                        }
                    } finally {
                        area.style.display = 'none';
                        
                        // Move print area back to original position
                        if (originalNextSibling) {
                            originalParent.insertBefore(area, originalNextSibling);
                        } else {
                            originalParent.appendChild(area);
                        }
                    }
                }, 500);
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            window.addEventListener('open-modal', event => {
                const modalId = event.detail;
                const modalEl = document.getElementById(modalId);
                if (modalEl) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });

            window.addEventListener('close-modal', event => {
                const modalId = event.detail;
                const modalEl = document.getElementById(modalId);
                if (modalEl) {
                    const instance = bootstrap.Modal.getInstance(modalEl);
                    if (instance) instance.hide();
                }
            });
        });
    </script>
    @endpush
</div>
