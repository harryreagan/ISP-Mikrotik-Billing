<x-app-layout>
    @php
        $hour = (int) now()->timezone('Africa/Nairobi')->format('H');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $userName = Auth::user()->name ?? 'Administrator';
        $userFirstName = explode(' ', trim($userName))[0];
    @endphp

    {{-- Personalized Greeting Header & Year Selector --}}
    <x-ui.page-header :title="$greeting . ', ' . $userFirstName" :subtitle="__('Here is what is happening across your WiFi network and M-Pesa collections today.')">
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2">
                <div class="d-flex align-items-center bg-white dark:bg-slate-800 rounded px-2 py-1 border shadow-xs">
                    <label class="small text-muted me-2 mb-0 fw-semibold"><i class="bi bi-calendar3 me-1"></i>Year:</label>
                    <select class="form-select form-select-sm border-0 py-0 ps-1 pe-4 font-semibold text-slate-800" style="width: auto; cursor: pointer;" onchange="window.location.href='?year='+this.value">
                        @foreach(($availableYears ?? [2024, 2025, 2026, 2027]) as $y)
                            <option value="{{ $y }}" {{ ($selectedYear ?? now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.badge tone="success">
                    <i class="bi bi-circle-fill me-1" style="font-size: 6px;"></i> {{ __('System operational') }}
                </x-ui.badge>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $tenantId = auth()->user()->tenant_id;
        $hasRouter = \App\Models\RouterList::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->exists();
        $hasPayments = \Illuminate\Support\Facades\Schema::hasTable('payment_gateways') && \Illuminate\Support\Facades\DB::table('payment_gateways')->exists();
        $hasSms = \Illuminate\Support\Facades\Schema::hasTable('isp_sms_gateways') && \Illuminate\Support\Facades\DB::table('isp_sms_gateways')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->exists();
        $hasPackage = \App\Models\PackageList::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->exists();
        $hasPortal = \Illuminate\Support\Facades\Schema::hasTable('isp_portal_settings') && \Illuminate\Support\Facades\DB::table('isp_portal_settings')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->exists();
        $completedCount = ($hasRouter?1:0) + ($hasPayments?1:0) + ($hasSms?1:0) + ($hasPackage?1:0) + ($hasPortal?1:0);
        $progressPct = round(($completedCount / 5) * 100);
    @endphp

    {{-- Modern Stat Cards Row (Reconciled from raw ledger) --}}
    <div class="row g-2 g-sm-3 mb-4">
        <!-- Card 1: Total Global/Tenant Revenue -->
        <div class="col-6 col-xl-3">
            <x-ui.stat-card :label="(!empty($billInformationData['is_super_admin']) ? __('Total global revenue') : __('Total revenue')) . ' (' . ($selectedYear ?? now()->year) . ')'" :value="'KES ' . number_format($billInformationData['total_revenue'] ?? 0, 0)" color="green">
                <x-slot:icon><i class="bi bi-graph-up-arrow"></i></x-slot:icon>
                <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                    <span><i class="bi bi-check-circle-fill me-1 text-success"></i>{{ __('Reconciled ledger') }}</span>
                    <span>{{ $selectedYear ?? now()->year }} YTD</span>
                </div>
            </x-ui.stat-card>
        </div>

        <!-- Card 2: Hotspot Revenue -->
        <div class="col-6 col-xl-3">
            <x-ui.stat-card :label="__('Hotspot revenue')" :value="'KES ' . number_format($billInformationData['hotspot_total'] ?? 0, 0)" color="orange">
                <x-slot:icon><i class="bi bi-wifi"></i></x-slot:icon>
                <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                    <span><i class="bi bi-calendar3 me-1"></i>{{ __('Today:') }} KES {{ number_format($billInformationData['hotspot_today'] ?? 0, 0) }}</span>
                    <span><i class="bi bi-arrow-up-right me-1"></i>{{ __('Hotspot') }}</span>
                </div>
            </x-ui.stat-card>
        </div>

        <!-- Card 3: PPPoE Collections -->
        <div class="col-6 col-xl-3">
            <x-ui.stat-card :label="__('PPPoE revenue')" :value="'KES ' . number_format($billInformationData['paid_amount'] ?? 0, 0)" color="blue">
                <x-slot:icon><i class="bi bi-wallet2"></i></x-slot:icon>
                <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                    <span><i class="bi bi-calendar3 me-1"></i>{{ __('Today:') }} KES {{ number_format($billInformationData['today_paid_amount'] ?? 0, 0) }}</span>
                    <span><i class="bi bi-arrow-up-right me-1"></i>{{ __('PPPoE') }}</span>
                </div>
            </x-ui.stat-card>
        </div>

        <!-- Card 4: Platform Subscriptions (Super Admin) OR Arrears/Due (Tenant) -->
        <div class="col-6 col-xl-3">
            @if(!empty($billInformationData['is_super_admin']))
                <x-ui.stat-card :label="__('Platform subscriptions')" :value="'KES ' . number_format($billInformationData['subscription_revenue'] ?? 0, 0)" color="purple">
                    <x-slot:icon><i class="bi bi-building-check"></i></x-slot:icon>
                    <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                        <span><i class="bi bi-award-fill me-1"></i>{{ __('Tenant plans') }}</span>
                        <span>{{ __('Platform') }}</span>
                    </div>
                </x-ui.stat-card>
            @else
                <x-ui.stat-card :label="__('Pending due / arrears')" :value="'KES ' . number_format($billInformationData['due_amount'] ?? 0, 0)" color="purple">
                    <x-slot:icon><i class="bi bi-exclamation-triangle-fill"></i></x-slot:icon>
                    <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                        <span><i class="bi bi-people me-1"></i>{{ ($customersData['active'] ?? 0) . ' PPPoE users' }}</span>
                        <span>{{ __('Arrears') }}</span>
                    </div>
                </x-ui.stat-card>
            @endif
        </div>
    </div>

    @php
        $tenantId = auth()->user()?->tenant_id ?: (session('tenant_id') ?: (session('impersonated_tenant_id') ?: 1));
        
        $totalRoutersCount = 0;
        $onlineRoutersCount = 0;
        $hasActiveRouters = false;

        // 1. Check live systemOverview from DashboardController
        if (!empty($systemOverview) && is_array($systemOverview)) {
            foreach ($systemOverview as $rData) {
                if (is_array($rData) && ($rData['status'] ?? false)) {
                    $hasActiveRouters = true;
                    $totalRoutersCount++;
                    if (!empty($rData['is_online'])) {
                        $onlineRoutersCount++;
                    }
                }
            }
        }

        // 2. Query Router model (identical to RoutersManager.php) to ensure 100% exact parity with /admin/routers
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('routers')) {
                $allTenantRouters = \App\Models\Router::where('tenant_id', $tenantId)->get();
                if ($allTenantRouters->isNotEmpty()) {
                    $dbTotal = $allTenantRouters->count();
                    $dbOnline = $allTenantRouters->filter(function ($r) {
                        $st = strtolower($r->status ?? ($r->action ?? ''));
                        $isOnline = in_array($st, ['online', 'connected', 'ready', 'active']);
                        $seenRecently = empty($r->last_seen_at) || \Carbon\Carbon::parse($r->last_seen_at)->gt(now()->subMinutes(15)) || ($r->updated_at && $r->updated_at->gt(now()->subMinutes(15)));
                        return $isOnline && $seenRecently;
                    })->count();

                    $totalRoutersCount = max($totalRoutersCount, $dbTotal);
                    $onlineRoutersCount = max($onlineRoutersCount, $dbOnline);
                    if ($totalRoutersCount > 0) {
                        $hasActiveRouters = true;
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 3. Fallback to router_lists if routers table had nothing
        if ($totalRoutersCount === 0) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('router_lists')) {
                    $rlQuery = \Illuminate\Support\Facades\DB::table('router_lists');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('router_lists', 'tenant_id') && $tenantId) {
                        $rlQuery->where('tenant_id', $tenantId);
                    }
                    $legacyRouters = $rlQuery->get();
                    if ($legacyRouters->isNotEmpty()) {
                        $totalRoutersCount = $legacyRouters->count();
                        $onlineRoutersCount = $legacyRouters->where('action', 'connected')->count();
                        $hasActiveRouters = true;
                    }
                }
            } catch (\Throwable $e) {}
        }
    @endphp

    {{-- Hotspot Sessions, Users & Income Overview Stat Cards Row (2 per row on mobile) --}}
    <div class="row g-2 g-sm-3 mb-4">
        <!-- Card 1: Active In-Session Hotspot -->
        <div class="col-6 col-xl-3">
            <x-ui.stat-card :label="__('Active hotspot in-session')" :value="$hotspotOverview['live_sessions'] ?? 0" :sub="__('Live connected hotspot users')" color="navy">
                <x-slot:icon><i class="bi bi-wifi"></i></x-slot:icon>
                <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                    <span>{{ __('Online now:') }} <strong>{{ $hotspotOverview['live_sessions'] ?? 0 }}</strong></span>
                    <span>{{ __('Vouchers today:') }} <strong>{{ $hotspotOverview['today_sales'] ?? 0 }}</strong></span>
                </div>
            </x-ui.stat-card>
        </div>

        <!-- Card 2: Hotspot Users: Active vs Expired -->
        <div class="col-6 col-xl-3">
            <x-ui.stat-card :label="__('Hotspot users')" :value="($hotspotOverview['active_users'] ?? 0) . ' Active'" :sub="($hotspotOverview['total_users'] ?? 0) . ' total registered'" color="purple">
                <x-slot:icon><i class="bi bi-people"></i></x-slot:icon>
                <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                    <span><i class="bi bi-check-circle-fill me-1 text-success"></i>{{ __('Active:') }} <strong>{{ $hotspotOverview['active_users'] ?? 0 }}</strong></span>
                    <span><i class="bi bi-hourglass-bottom me-1 text-warning"></i>{{ __('Expired:') }} <strong>{{ $hotspotOverview['expired_users'] ?? 0 }}</strong></span>
                </div>
            </x-ui.stat-card>
        </div>

        <!-- Card 3: Income Today (Reconciled M-Pesa & Cash Collections) -->
        <div class="col-6 col-xl-3">
            <x-ui.stat-card :label="__('Income today')" :value="'KES ' . number_format($revenueReport['today_revenue'] ?? 0, 2)" :sub="__('Total collections today.')" color="green">
                <x-slot:icon><i class="bi bi-cash-coin"></i></x-slot:icon>
                <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                    <span>{{ __('Hotspot:') }} <strong>KES {{ number_format($revenueReport['today_hotspot'] ?? 0, 0) }}</strong></span>
                    <span>{{ __('PPPoE:') }} <strong>KES {{ number_format($revenueReport['today_pppoe'] ?? 0, 0) }}</strong></span>
                </div>
            </x-ui.stat-card>
        </div>

        <!-- Card 4: Online Routers / Total -->
        <div class="col-6 col-xl-3">
            <a href="{{ route('admin.routers') }}" class="text-decoration-none d-block">
                <x-ui.stat-card :label="__('Online routers')" :value="$onlineRoutersCount . ' / ' . $totalRoutersCount" :sub="__('Live heartbeat verified routers.')" color="blue">
                    <x-slot:icon><i class="bi bi-router"></i></x-slot:icon>
                    <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                        <span><i class="bi bi-broadcast me-1 text-success"></i>{{ __('Online:') }} <strong>{{ $onlineRoutersCount }}</strong></span>
                        <span><i class="bi bi-hdd-network me-1 text-secondary"></i>{{ __('Total:') }} <strong>{{ $totalRoutersCount }}</strong></span>
                    </div>
                </x-ui.stat-card>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Routers Section Header with Quick Actions --}}
        <div class="col-12 mb-n2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h2 class="ui-section-title mb-0">{{ __('MikroTik routers & telemetry') }}</h2>
                    @if ($hasActiveRouters)
                        <x-ui.badge :tone="$onlineRoutersCount > 0 ? 'success' : 'neutral'">
                            {{ $onlineRoutersCount }} {{ __('online') }} / {{ $totalRoutersCount }} {{ __('total') }}
                        </x-ui.badge>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="secondary" :href="route('admin.routers')">
                        <i class="bi bi-hdd-network me-1"></i> {{ __('View all routers') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" :href="route('admin.router-wizard')">
                        <i class="bi bi-plus-lg me-1"></i> {{ __('Add router') }}
                    </x-ui.button>
                </div>
            </div>
        </div>

        @if (!$hasActiveRouters)
            <div class="col-12 mb-1">
                <x-ui.card :padding="false">
                    <x-ui.empty-state
                        icon="bi-router"
                        :title="__('No active MikroTik routers connected')"
                        :description="__('Connect your MikroTik core router via API (Port 8728) or WireGuard to monitor real-time CPU, RAM, and live PPPoE interfaces.')"
                    >
                        <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mt-3">
                            <a href="{{ route('admin.router-wizard') }}" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm px-3 py-2">
                                <i class="bi bi-plus-lg"></i>
                                <span>{{ __('Add Router') }}</span>
                            </a>
                            <a href="{{ route('admin.routers') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 px-3 py-2">
                                <i class="bi bi-hdd-network"></i>
                                <span>{{ __('View All Routers') }}</span>
                            </a>
                        </div>
                    </x-ui.empty-state>
                </x-ui.card>
            </div>
        @endif

        {{-- Unified Row for Routers and Graphs to allow dynamic "weight" adjustment --}}
        @if (is_array($systemOverview))
            @foreach ($systemOverview as $routerName => $routerData)
                @php
                    if (!is_array($routerData) || !($routerData['status'] ?? false)) {
                        continue;
                    }

                    $isRouterOnline = (bool) ($routerData['is_online'] ?? true);

                    $info = is_array($routerData['data'] ?? null) 
                        ? ($routerData['data'][0] ?? $routerData['data']) 
                        : [];
                    if (!is_array($info)) {
                        $info = [];
                    }

                    $cpuLoad = (int) filter_var($info['cpu-load'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
                    $cpuColor = $cpuLoad > 80 ? 'bg-danger' : ($cpuLoad > 50 ? 'bg-warning' : 'bg-success');

                    // Memory Math
                    $memTotalRaw = (int) ($info['total-memory'] ?? 0);
                    $memFreeRaw = (int) ($info['free-memory'] ?? 0);
                    $memUsed = max(0, $memTotalRaw - $memFreeRaw);
                    $memPct = $memTotalRaw > 0
                        ? round(($memUsed / $memTotalRaw) * 100)
                        : (int) ($routerData['memory_load'] ?? 0);

                    // HDD Math
                    $hddTotalRaw = (int) ($info['total-hdd-space'] ?? 0);
                    $hddFreeRaw = (int) ($info['free-hdd-space'] ?? 0);
                    $hddUsed = max(0, $hddTotalRaw - $hddFreeRaw);
                    $hddPct = $hddTotalRaw > 0 ? round(($hddUsed / $hddTotalRaw) * 100) : 0;

                    $activeTotal = (int) ($routerData['active_total'] ?? 0);
                    $activePpp = (int) ($routerData['active_ppp'] ?? 0);
                    $activeHs = (int) ($routerData['active_hs'] ?? 0);

                    $cardId = 'router_' . \Illuminate\Support\Str::slug($routerName);

                    if (!function_exists('formatRouterBytesLg')) {
                        function formatRouterBytesLg($bytes) {
                            if ($bytes <= 0) return '0 B';
                            $k = 1024;
                            $sizes = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
                            $i = floor(log($bytes) / log($k));
                            return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
                        }
                    }
                @endphp

            <div class="col-12 col-md-6 col-lg-4 col-xxl-4 d-flex flex-fill">
                {{-- Refined Router Card with Online/Offline Status & Full Analytics --}}
                <div class="card w-100 overflow-hidden d-flex flex-column router-overview-card" style="min-height: 460px;">
                    <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-3 overflow-hidden">
                            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: {{ $isRouterOnline ? '#ecfdf5' : '#fef2f2' }};">
                                <i class="bi bi-hdd-network {{ $isRouterOnline ? 'text-success' : 'text-danger' }}"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex align-items-center gap-2">
                                    <h3 class="ui-section-title mb-0 text-truncate" title="{{ $routerName }}">{{ $routerName }}</h3>
                                    <x-ui.badge :tone="$isRouterOnline ? 'success' : 'danger'">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 5px;"></i>
                                        {{ $isRouterOnline ? __('Online') : __('Offline') }}
                                    </x-ui.badge>
                                </div>
                                <div class="text-muted mt-1 d-flex gap-1 flex-wrap align-items-center" style="font-size: 0.6875rem;">
                                    <span>{{ $info['board-name'] ?? 'MikroTik' }}</span>
                                    @if(!empty($routerData['ip']))
                                        <span>•</span>
                                        <span class="font-monospace">{{ $routerData['ip'] }}</span>
                                    @endif
                                    <span>•</span>
                                    <x-ui.badge :tone="$isRouterOnline ? 'info' : 'neutral'">
                                        {{ __(':type', ['type' => $routerData['type'] ?? 'API']) }}
                                    </x-ui.badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body router-card-body p-0 d-flex flex-column h-100">
                        <div class="px-4 py-3 border-bottom router-card-subheader">
                            <div class="row text-center mb-3 g-2">
                                <div class="col-4">
                                    <small class="text-muted d-block mb-1 fw-medium" style="font-size: 0.6875rem;">{{ __('Uptime') }}</small>
                                    <span class="fw-semibold text-dark uptime-clock"
                                          style="font-size: 0.75rem;"
                                          data-uptime="{{ $info['uptime'] ?? '0s' }}">
                                        {{ $isRouterOnline ? str_replace(['w', 'd', 'h', 'm', 's'], ['w ', 'd ', 'h ', 'm ', 's '], $info['uptime'] ?? 'Active') : __('Offline') }}
                                    </span>
                                </div>
                                <div class="col-4 border-start">
                                    <small class="text-muted d-block mb-1 fw-medium" style="font-size: 0.6875rem;">{{ __('Active sessions') }}</small>
                                    <span class="fw-semibold text-dark" style="font-size: 0.75rem;">
                                        {{ $activeTotal }} <span class="text-muted fw-normal" style="font-size: 0.6875rem;">({{ $activePpp }}P / {{ $activeHs }}H)</span>
                                    </span>
                                </div>
                                <div class="col-4 border-start">
                                    <small class="text-muted d-block mb-1 fw-medium" style="font-size: 0.6875rem;">{{ __('Version') }}</small>
                                    <span class="fw-semibold text-dark" style="font-size: 0.75rem;">{{ $info['version'] ?? 'N/A' }}</span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between text-dark mb-1" style="font-size:0.75rem; font-weight:500;">
                                        <span>{{ __('CPU usage') }}</span>
                                        <span class="text-slate-600 fw-semibold">
                                            @if(($info['cpu-frequency'] ?? '-') !== '-')
                                                {{ ($info['cpu-count'] ?? '1') . ' × ' . $info['cpu-frequency'] }} MHz
                                            @endif
                                            <span class="text-muted fw-normal">({{ $cpuLoad }}%)</span>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius:10px; background: rgba(0,0,0,0.05);">
                                        <div class="progress-bar {{ $cpuColor }}" role="progressbar" style="width: {{ $cpuLoad }}%;"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between text-dark mb-1" style="font-size:0.75rem; font-weight:500;">
                                        <span>{{ __('Memory usage') }}</span>
                                        <span>
                                            @if($memTotalRaw > 0)
                                                {{ formatRouterBytesLg($memUsed) }} / {{ formatRouterBytesLg($memTotalRaw) }}
                                            @endif
                                            <span class="text-muted fw-normal ms-1">({{ $memPct }}%)</span>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius:10px; background: rgba(0,0,0,0.05);">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $memPct }}%;"></div>
                                    </div>
                                </div>
                                <div class="mb-1">
                                    <div class="d-flex justify-content-between text-dark mb-1" style="font-size:0.75rem; font-weight:500;">
                                        <span>{{ __('Storage usage') }}</span>
                                        <span>
                                            @if($hddTotalRaw > 0)
                                                {{ formatRouterBytesLg($hddUsed) }} / {{ formatRouterBytesLg($hddTotalRaw) }} <span class="text-muted fw-normal ms-1">({{ $hddPct }}%)</span>
                                            @else
                                                <span class="text-muted fw-normal">{{ $routerData['last_seen_at'] ? __('Last seen :time', ['time' => $routerData['last_seen_at']]) : ($isRouterOnline ? __('Connected') : __('Unreachable')) }}</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius:10px; background: rgba(0,0,0,0.05);">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $hddPct }}%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex-grow-1 overflow-auto">
                            <div class="accordion accordion-flush" id="acc_{{ $cardId }}">

                                {{-- Part 1: Platform & Architecture --}}
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2.5 px-4 fw-medium router-accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#plat_{{ $cardId }}" style="font-size: 0.75rem;">
                                            <i class="bi bi-info-square me-2 text-muted"></i> {{ __('Platform & architecture') }}
                                        </button>
                                    </h2>
                                    <div id="plat_{{ $cardId }}" class="accordion-collapse collapse" data-bs-parent="#acc_{{ $cardId }}">
                                        <div class="accordion-body p-3 router-accordion-body border-top">
                                            <div class="d-flex flex-column gap-2" style="font-size: 0.75rem;">
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Board name') }}</span><span class="fw-semibold">{{ $info['board-name'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Platform') }}</span><span class="fw-semibold">{{ $info['platform'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Architecture') }}</span><span class="fw-semibold">{{ $info['architecture-name'] ?? 'N/A' }}</span></div>
                                                @if(!empty($routerData['location']))
                                                    <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Location') }}</span><span class="fw-semibold">{{ $routerData['location'] }}</span></div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Part 2: System & Software --}}
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2.5 px-4 fw-medium router-accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#sys_{{ $cardId }}" style="font-size: 0.75rem;">
                                            <i class="bi bi-gear-wide-connected me-2 text-muted"></i> {{ __('System diagnostics & build') }}
                                        </button>
                                    </h2>
                                    <div id="sys_{{ $cardId }}" class="accordion-collapse collapse" data-bs-parent="#acc_{{ $cardId }}">
                                        <div class="accordion-body p-3 router-accordion-body border-top">
                                            <div class="d-flex flex-column gap-2" style="font-size: 0.75rem;">
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('OS version') }}</span><span class="fw-semibold">{{ $info['version'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Factory OS') }}</span><span class="fw-semibold">{{ $info['factory-software'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Last seen / build') }}</span><span class="fw-semibold">{{ $info['build-time'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Uptime') }}</span><span class="fw-semibold text-dark">{{ $info['uptime'] ?? 'N/A' }}</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Part 3: Hardware Information --}}
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2.5 px-4 fw-medium router-accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#hw_{{ $cardId }}" style="font-size: 0.75rem;">
                                            <i class="bi bi-gpu-card me-2 text-muted"></i> {{ __('Hardware information') }}
                                        </button>
                                    </h2>
                                    <div id="hw_{{ $cardId }}" class="accordion-collapse collapse" data-bs-parent="#acc_{{ $cardId }}">
                                        <div class="accordion-body p-3 router-accordion-body border-top">
                                            <div class="d-flex flex-column gap-2" style="font-size: 0.75rem;">
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('CPU') }}</span><span class="fw-semibold">{{ $info['cpu'] ?? 'N/A' }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('CPU count/freq/load') }}</span><span class="fw-semibold">{{ ($info['cpu-count'] ?? '?') }} / {{ ($info['cpu-frequency'] ?? '?') }} / {{ $info['cpu-load'] ?? '0' }}%</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('HDD') }}</span><span class="fw-semibold text-dark">{{ formatRouterBytesLg($hddUsed) }} / {{ formatRouterBytesLg($hddTotalRaw) }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Write total') }}</span><span class="fw-semibold">{{ $info['write-sect-total'] ?? '0' }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Write since reboot') }}</span><span class="fw-semibold">{{ $info['write-sect-since-reboot'] ?? '0' }}</span></div>
                                                <div class="d-flex justify-content-between"><span class="text-muted">{{ __('Temp / voltage') }}</span><span class="fw-semibold text-dark"><span>{{ isset($info['temperature']) ? $info['temperature'].'°C' : 'N/A' }}</span> | <span>{{ isset($info['voltage']) ? $info['voltage'].'V' : 'N/A' }}</span></span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="px-4 py-2.5 border-top bg-slate-50 d-flex justify-content-between align-items-center" style="font-size: 0.75rem;">
                            <span class="text-muted">
                                @if(!empty($routerData['last_seen_at']))
                                    <i class="bi bi-clock me-1"></i>{{ __('Seen :time', ['time' => $routerData['last_seen_at']]) }}
                                @else
                                    <i class="bi bi-shield-check me-1"></i>{{ $isRouterOnline ? __('Live telemetry') : __('Offline status') }}
                                @endif
                            </span>
                            <a href="{{ route('admin.routers') }}" class="text-decoration-none fw-semibold" style="color: var(--accent);">
                                {{ __('View all routers') }} <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        @endif

        {{-- Analytical Graphs Section --}}
        <div class="col-12 col-md-6 col-lg-4 col-xxl-4 d-flex flex-fill">
            <x-ui.card :title="__('Customer segmentation')" class="w-100 d-flex flex-column" style="min-height: 460px;">
                <div class="d-flex align-items-center justify-content-center flex-grow-1" id="customers"></div>
            </x-ui.card>
        </div>

        {{-- Billing Overview: full-width, two-panel --}}
        <div class="col-12 d-flex flex-fill">
            <x-ui.card :title="__('Billing overview')" :padding="false" class="w-100 overflow-hidden">
                <x-slot:actions>
                    <x-ui.badge tone="success">{{ __('Active') }}</x-ui.badge>
                    <x-ui.badge tone="info">{{ __('Free') }}</x-ui.badge>
                    <x-ui.badge tone="danger">{{ __('Inactive') }}</x-ui.badge>
                    <x-ui.badge tone="warning">{{ __('Pending') }}</x-ui.badge>
                </x-slot:actions>
                <div class="row g-0">
                    {{-- LEFT: Global Summary Chart (all 8 metrics) --}}
                    <div class="col-12 col-lg-7 border-end">
                        <div class="px-4 pt-3 pb-0">
                            <p class="mb-0 text-muted fw-medium" style="font-size: 0.75rem;">{{ __('Global summary') }}</p>
                        </div>
                        <div id="billInformation" class="px-2"></div>
                    </div>
                    {{-- RIGHT: Per-Status Grouped Chart + Mini-tiles --}}
                    <div class="col-12 col-lg-5 d-flex flex-column">
                        <div class="px-4 pt-3 pb-0">
                            <p class="mb-0 text-muted fw-medium" style="font-size: 0.75rem;">{{ __('By status') }}</p>
                        </div>
                        <div id="billByStatus" class="px-2" style="flex:1;"></div>
                        {{-- Summary mini-tiles --}}
                        <div class="row g-0 border-top">
                            @php
                                $tileConfig = [
                                    'active'   => ['color'=>'#16a34a','bg'=>'#ecfdf5','icon'=>'bi-check-circle-fill'],
                                    'free'     => ['color'=>'#0284c7','bg'=>'#f0f9ff','icon'=>'bi-gift-fill'],
                                    'inactive' => ['color'=>'#e11d48','bg'=>'#fff1f2','icon'=>'bi-x-circle-fill'],
                                    'pending'  => ['color'=>'#d97706','bg'=>'#fffbeb','icon'=>'bi-hourglass-split'],
                                ];
                            @endphp
                            @foreach(['active','free','inactive','pending'] as $st)
                            @php $tc = $tileConfig[$st]; $bd = $billInformationData['by_status'][$st]; @endphp
                            <div class="col-6 p-2.5" style="border-right:{{ $loop->odd ? '1px solid rgba(226,232,240,0.7)' : 'none' }};border-bottom:{{ $loop->iteration <= 2 ? '1px solid rgba(226,232,240,0.7)' : 'none' }};">
                                <div class="rounded-3 p-2.5" style="background:{{ $tc['bg'] }};">
                                    <div class="d-flex align-items-center gap-1.5 mb-1">
                                        <i class="bi {{ $tc['icon'] }}" style="color:{{ $tc['color'] }};font-size:11px;"></i>
                                        <span class="fw-semibold" style="font-size:11px;color:{{ $tc['color'] }};text-transform:capitalize;">{{ __($st) }}</span>
                                    </div>
                                    <div style="font-size:11px;color:#64748b;">
                                        <div>{{ __('Rent:') }} <strong style="color:#1e293b;">{{ number_format($bd['monthly_rent'],0) }}</strong></div>
                                        <div>{{ __('Adv:') }} <strong style="color:#16a34a;">{{ number_format($bd['advance'],0) }}</strong></div>
                                        <div>{{ __('Due:') }} <strong style="color:#e11d48;">{{ number_format(abs($bd['due_amount']),0) }}</strong></div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="col-12 col-md-12 col-lg-12 col-xxxl-12 d-flex flex-fill">
            <x-ui.card :title="__('Income & revenue overview')" class="w-100 d-flex flex-column" style="min-height: 460px;">
                <div id="income_revenue"></div>

                {{-- Reconciled Month-by-Month Ledger Table --}}
                @if(isset($revenueReport) && !empty($revenueReport['months']))
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-journal-check text-primary me-1"></i>
                                {{ __('Reconciled Revenue Ledger (:year)', ['year' => $selectedYear ?? now()->year]) }}
                            </h6>
                            <small class="text-muted">{{ __('Verified single source of truth from raw successful transactions & collections') }}</small>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill">
                            <i class="bi bi-shield-check me-1"></i>{{ __('100% Reconciled') }}
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">{{ __('Month') }}</th>
                                    <th class="text-end">{{ __('Hotspot Revenue') }}</th>
                                    <th class="text-end">{{ __('PPPoE Revenue') }}</th>
                                    @if(!empty($revenueReport['summary']['is_super_admin']))
                                        <th class="text-end">{{ __('Subscriptions') }}</th>
                                    @endif
                                    <th class="text-end pe-3">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($revenueReport['months'] as $m)
                                <tr>
                                    <td class="ps-3 fw-medium text-dark">
                                        <span class="d-inline-block rounded-circle bg-primary me-2" style="width: 6px; height: 6px; vertical-align: middle;"></span>
                                        {{ __($m['name']) }}
                                    </td>
                                    <td class="text-end text-muted font-monospace">
                                        {{ number_format($m['hotspot'], 2) }}
                                    </td>
                                    <td class="text-end text-muted font-monospace">
                                        {{ number_format($m['pppoe'], 2) }}
                                    </td>
                                    @if(!empty($revenueReport['summary']['is_super_admin']))
                                        <td class="text-end text-muted font-monospace">
                                            {{ number_format($m['subscription'] ?? 0, 2) }}
                                        </td>
                                    @endif
                                    <td class="text-end pe-3 fw-semibold text-dark font-monospace">
                                        KES {{ number_format($m['total'], 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light border-top border-2">
                                <tr class="fw-bold">
                                    <td class="ps-3 text-uppercase text-dark">{{ __('Annual Total') }}</td>
                                    <td class="text-end text-primary font-monospace">KES {{ number_format($revenueReport['summary']['hotspot'], 2) }}</td>
                                    <td class="text-end text-info font-monospace">KES {{ number_format($revenueReport['summary']['pppoe'], 2) }}</td>
                                    @if(!empty($revenueReport['summary']['is_super_admin']))
                                        <td class="text-end text-warning font-monospace">KES {{ number_format($revenueReport['summary']['subscription'] ?? 0, 2) }}</td>
                                    @endif
                                    <td class="text-end pe-3 text-success font-monospace fs-6">KES {{ number_format($revenueReport['summary']['total'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @endif
            </x-ui.card>
        </div>
    </div>
    @push('scripts')
        <script>
            document.addEventListener('livewire:navigated', function () {
                requestAnimationFrame(() => {
                    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

                    // for destroying existing charts
                    if (window.chart1) chart1.destroy();
                    if (window.chart2) chart2.destroy();
                    if (window.chart2b) chart2b.destroy();
                    if (window.chart3) chart3.destroy();

                    // ✅ 1st chart: customers
                    const customersEl = document.querySelector("#customers");
                    if (customersEl) {
                        const customersData = @json(array_values($customersData));
                        const customers = {
                            series: customersData,
                            chart: {
                                height: 360,
                                type: 'radialBar',
                            },
                            theme: {
                                mode: isDark ? 'dark' : 'light'
                            },
                            plotOptions: {
                                radialBar: {
                                    offsetY: 0,
                                    startAngle: 0,
                                    endAngle: 270,
                                    hollow: {
                                        margin: 5,
                                        size: '30%',
                                        background: 'transparent',
                                    },
                                    dataLabels: {
                                        name: { show: false },
                                        value: { show: false }
                                    },
                                    barLabels: {
                                        enabled: true,
                                        useSeriesColors: true,
                                        offsetX: -8,
                                        fontSize: '16px',
                                        formatter: function (seriesName, opts) {
                                            return seriesName + ":  " + opts.w.globals.series[opts.seriesIndex]
                                        },
                                    },
                                }
                            },
                            labels: ['{{ __("Total") }}', '{{ __("Active") }}', '{{ __("Pending") }}', '{{ __("Free") }}', '{{ __("Temporary Disable") }}', '{{ __("Inactive") }}', '{{ __("Recent") }}']
                        };
                        window.chart1 = new ApexCharts(customersEl, customers);
                        chart1.render();
                    }

                    // ✅ 2a chart: billInformation — Global Summary (all 8 metrics)
                    const billEl = document.querySelector("#billInformation");
                    if (billEl) {
                        const billData = @json(array_values(array_filter(
                            $billInformationData,
                            fn($k) => $k !== 'by_status',
                            ARRAY_FILTER_USE_KEY
                        )));
                        const billInformation = {
                            series: [{ name: 'Amount', data: billData }],
                            chart: {
                                type: 'bar',
                                height: 340,
                                toolbar: { show: false },
                                fontFamily: 'inherit',
                                animations: { enabled: true, easing: 'easeinout', speed: 600 },
                            },
                            theme: {
                                mode: isDark ? 'dark' : 'light'
                            },
                            plotOptions: {
                                bar: {
                                    distributed: true,
                                    columnWidth: '55%',
                                    borderRadius: 5,
                                    borderRadiusApplication: 'end',
                                    dataLabels: { position: 'top' },
                                },
                            },
                            legend: { show: false },
                            colors: ['#16a34a','#ef4444','#4f46e5','#0ea5e9','#38bdf8','#f59e0b','#fb923c','#dc2626'],
                            dataLabels: {
                                enabled: true,
                                formatter: val => val !== 0 ? (Math.abs(val) >= 1000 ? (Math.abs(val)/1000).toFixed(1)+'k' : Math.abs(val).toFixed(0)) : '',
                                offsetY: -20,
                                style: { fontSize: '10px', fontWeight: 700, colors: [isDark ? '#f8fafc' : '#374151'] },
                            },
                            yaxis: {
                                labels: {
                                    formatter: val => val >= 1000 ? (val/1000).toFixed(1)+'k' : val.toFixed(0),
                                    style: { fontSize: '11px', colors: '#9ca3af' },
                                },
                            },
                            xaxis: {
                                categories: [
                                    '{{ __("Monthly Rent") }}',
                                    '{{ __("Previous Due") }}',
                                    '{{ __("Advance") }}',
                                    '{{ __("Total PPPoE") }}',
                                    '{{ __("Today PPPoE") }}',
                                    '{{ __("Hotspot Total") }}',
                                    '{{ __("Hotspot Today") }}',
                                    '{{ __("Total Due") }}'
                                ],
                                labels: {
                                    style: { fontSize: '11px', fontWeight: 600,
                                        colors: ['#16a34a','#ef4444','#4f46e5','#0ea5e9','#38bdf8','#f59e0b','#fb923c','#dc2626'] },
                                    rotate: -30,
                                    rotateAlways: false,
                                    trim: true,
                                },
                                axisBorder: { show: false },
                                axisTicks: { show: false },
                            },
                            grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
                            tooltip: {
                                y: { formatter: val => 'KES ' + Math.abs(val).toLocaleString(undefined, {minimumFractionDigits:2}) },
                            },
                        };
                        window.chart2 = new ApexCharts(billEl, billInformation);
                        chart2.render();
                    }

                    // ✅ 2b chart: billByStatus — Per-status grouped chart
                    if (window.chart2b) chart2b.destroy();
                    const billStatusEl = document.querySelector("#billByStatus");
                    if (billStatusEl) {
                        const byStatus = @json($billInformationData['by_status']);
                        const statusKeys   = ['active', 'free', 'inactive', 'pending'];
                        const statusLabels = ['{{ __("Active") }}', '{{ __("Free") }}', '{{ __("Inactive") }}', '{{ __("Pending") }}'];

                        const rentData = statusKeys.map(s => byStatus[s].monthly_rent);
                        const advData  = statusKeys.map(s => byStatus[s].advance);
                        const dueData  = statusKeys.map(s => Math.abs(byStatus[s].due_amount));

                        const billByStatus = {
                            series: [
                                { name: '{{ __("Monthly Rent") }}', data: rentData },
                                { name: '{{ __("Advance") }}',      data: advData  },
                                { name: '{{ __("Due Amount") }}',   data: dueData  },
                            ],
                            chart: {
                                type: 'bar',
                                height: 260,
                                toolbar: { show: false },
                                fontFamily: 'inherit',
                                animations: { enabled: true, easing: 'easeinout', speed: 600 },
                            },
                            theme: {
                                mode: isDark ? 'dark' : 'light'
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: false,
                                    columnWidth: '65%',
                                    borderRadius: 5,
                                    borderRadiusApplication: 'end',
                                    dataLabels: { position: 'top' },
                                },
                            },
                            colors: ['#16a34a', '#4f46e5', '#dc2626'],
                            dataLabels: {
                                enabled: true,
                                formatter: val => val > 0 ? val.toLocaleString(undefined,{maximumFractionDigits:0}) : '',
                                offsetY: -18,
                                style: { fontSize: '9px', fontWeight: 600, colors: [isDark ? '#f8fafc' : '#374151'] },
                            },
                            stroke: { show: true, width: 2, colors: ['transparent'] },
                            xaxis: {
                                categories: statusLabels,
                                labels: {
                                    style: { fontSize: '12px', fontWeight: 600,
                                        colors: ['#16a34a','#4f46e5','#dc2626','#ea580c'] },
                                },
                                axisBorder: { show: false },
                                axisTicks: { show: false },
                            },
                            yaxis: {
                                labels: {
                                    formatter: val => val >= 1000 ? (val/1000).toFixed(1)+'k' : val,
                                    style: { fontSize: '11px', colors: '#9ca3af' },
                                },
                            },
                            legend: {
                                position: 'top',
                                horizontalAlign: 'center',
                                fontSize: '11px',
                                markers: { width: 10, height: 10, radius: 3 },
                                itemMargin: { horizontal: 6 },
                            },
                            grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
                            tooltip: {
                                shared: true,
                                intersect: false,
                                y: { formatter: val => 'KES ' + val.toLocaleString(undefined,{minimumFractionDigits:2}) },
                            },
                        };
                        window.chart2b = new ApexCharts(billStatusEl, billByStatus);
                        chart2b.render();
                    }

                    // ✅ 3rd chart: income_revenue
                    const revenueEl = document.querySelector("#income_revenue");
                    if (revenueEl) {
                        const chartData = {!! json_encode($results) !!};
                        const cashflowData = Object.values(chartData).map(item => item.cashflow_previous_year);
                        const incomeData = Object.values(chartData).map(item => item.income_current_year);
                        const revenueData = Object.values(chartData).map(item => item.revenue_difference);

                        const income_revenue = {
                            chart: { height: 350, type: "line", stacked: false },
                            theme: {
                                mode: isDark ? 'dark' : 'light'
                            },
                            dataLabels: { enabled: false },
                            stroke: { width: [1, 1, 4] },
                            title: {
                                text: '{{ __("Income Revenue Analysis (:prev - :curr)", ["prev" => now()->subYear()->year, "curr" => now()->year]) }}',
                                align: 'left',
                                offsetX: 60
                            },
                            series: [
                                { name: '{{ __("Income In Previous Year") }}', type: 'column', data: cashflowData },
                                { name: "{{ __('Income In Current Year') }}", type: 'column', data: incomeData },
                                { name: "{{ __('Revenue Difference') }}", type: 'line', data: revenueData },
                            ],
                            xaxis: {
                                categories: [
                                    "{{ __('January') }}", "{{ __('February') }}", "{{ __('March') }}", "{{ __('April') }}", "{{ __('May') }}", "{{ __('June') }}",
                                    "{{ __('July') }}", "{{ __('August') }}", "{{ __('September') }}", "{{ __('October') }}", "{{ __('November') }}", "{{ __('December') }}"
                                ]
                            },
                            yaxis: [
                                {
                                    seriesName: "{{ __('Income In Current Year') }}",
                                    axisTicks: { show: true },
                                    axisBorder: { show: true, color: '#008FFB' },
                                    labels: { style: { colors: '#008FFB' } },
                                    title: {
                                        text: "{{ __('Income In Year :year', ['year' => now()->subYear()->year]) }}",
                                        style: { color: '#008FFB' }
                                    }
                                },
                                {
                                    seriesName: '{{ __("Income In Previous Year") }}',
                                    opposite: true,
                                    axisTicks: { show: true },
                                    axisBorder: { show: true, color: '#00E396' },
                                    labels: { style: { colors: '#00E396' } },
                                    title: {
                                        text: "{{ __('Income In Year :year', ['year' => now()->year]) }}",
                                        style: { color: '#00E396' }
                                    }
                                },
                                {
                                    opposite: true,
                                    seriesName: "{{ __('Revenue Difference') }}",
                                    axisTicks: { show: true },
                                    axisBorder: { show: true, color: '#FEB019' },
                                    labels: { style: { colors: '#FEB019' } },
                                    title: {
                                        text: "{{ __('Revenue Difference') }}",
                                        style: { color: '#FEB019' }
                                    }
                                }
                            ],
                            tooltip: {
                                shared: false,
                                intersect: true,
                                x: { show: false }
                            },
                            legend: {
                                horizontalAlign: "center",
                                offsetX: 40
                            }
                        };
                        window.chart3 = new ApexCharts(revenueEl, income_revenue);
                        chart3.render();
                    }

                    // Live theme update listener for ApexCharts
                    const updateChartsTheme = () => {
                        const activeTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
                        const labelColor = activeTheme === 'dark' ? '#f8fafc' : '#374151';

                        if (window.chart1) window.chart1.updateOptions({ theme: { mode: activeTheme } });
                        if (window.chart2) {
                            window.chart2.updateOptions({
                                theme: { mode: activeTheme },
                                dataLabels: { style: { colors: [labelColor] } }
                            });
                        }
                        if (window.chart2b) {
                            window.chart2b.updateOptions({
                                theme: { mode: activeTheme },
                                dataLabels: { style: { colors: [labelColor] } }
                            });
                        }
                        if (window.chart3) window.chart3.updateOptions({ theme: { mode: activeTheme } });
                    };

                    if (window.themeObserver) window.themeObserver.disconnect();
                    window.themeObserver = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.attributeName === 'data-bs-theme') {
                                updateChartsTheme();
                            }
                        });
                    });
                    window.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

                    // ✅ 4th: initUptimeClocks
                    if (window.uptimeInterval) clearInterval(window.uptimeInterval);
                    window.uptimeInterval = setInterval(() => {
                        document.querySelectorAll('.uptime-clock').forEach(clock => {
                            let uptime = clock.getAttribute('data-uptime');
                            if (!uptime || uptime === 'N/A') return;

                            // Parse MikroTik uptime (e.g., 1w2d3h4m5s)
                            const regex = /(?:(\d+)w)?(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/;
                            const matches = uptime.match(regex);

                            let w = parseInt(matches[1]) || 0;
                            let d = parseInt(matches[2]) || 0;
                            let h = parseInt(matches[3]) || 0;
                            let m = parseInt(matches[4]) || 0;
                            let s = parseInt(matches[5]) || 0;

                            s++;
                            if (s >= 60) { s = 0; m++; }
                            if (m >= 60) { m = 0; h++; }
                            if (h >= 24) { h = 0; d++; }
                            if (d >= 7) { d = 0; w++; }

                            // Rebuild raw data
                            let newRaw = (w ? w+'w' : '') + (d ? d+'d' : '') + (h ? h+'h' : '') + (m ? m+'m' : '') + s + 's';
                            clock.setAttribute('data-uptime', newRaw);

                            // Rebuild display string
                            let display = (w ? w+'w ' : '') + (d ? d+'d ' : '') + (h ? h+'h ' : '') + (m ? m+'m ' : '') + s + 's';
                            clock.innerText = display;
                        });
                    }, 1000);
                });
            });
        </script>
    @endpush

    @push('styles')
        <style>
            /* Dark Mode Support for Router Overview Cards */
            .router-overview-card {
                background-color: #ffffff;
                border: 1px solid rgba(0, 0, 0, 0.08) !important;
            }
            .router-card-subheader {
                background-color: #ffffff;
                border-bottom-color: rgba(0, 0, 0, 0.08) !important;
            }
            .router-accordion-button {
                background-color: #ffffff !important;
                color: var(--bs-body-color) !important;
            }
            .router-accordion-body {
                background-color: #ffffff !important;
                border-top-color: rgba(0, 0, 0, 0.05) !important;
            }

            /* Dark Mode overrides */
            [data-bs-theme="dark"] .router-overview-card {
                background-color: #1e293b !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .router-card-body.bg-light {
                background-color: #0f172a !important;
            }
            [data-bs-theme="dark"] .router-card-subheader {
                background-color: #1e293b !important;
                border-bottom-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .router-overview-card .text-dark {
                color: #f1f5f9 !important;
            }
            [data-bs-theme="dark"] .router-overview-card .progress {
                background-color: rgba(255, 255, 255, 0.1) !important;
            }
            [data-bs-theme="dark"] .router-accordion-button {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
            }
            [data-bs-theme="dark"] .router-accordion-body {
                background-color: #1e293b !important;
                color: #cbd5e1 !important;
                border-top-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .router-overview-card .accordion-button:not(.collapsed) {
                background-color: #0f172a !important;
                color: #38bdf8 !important;
                box-shadow: none;
            }
            [data-bs-theme="dark"] .router-overview-card .accordion-button::after {
                filter: invert(1) grayscale(1);
            }

            /* General card border support in dark mode */
            [data-bs-theme="dark"] .card {
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .border-end {
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .border-top {
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-bs-theme="dark"] .table-light {
                --bs-table-bg: #1e293b;
                --bs-table-color: #f1f5f9;
            }
        </style>
    @endpush

</x-app-layout>
