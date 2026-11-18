@php
    $topBrandName = 'HSmart';
    if (auth()->check()) {
        $u = auth()->user();
        $tName = $u->tenant?->name ?? null;
        $rComp = $u->reseller?->company ?? null;
        $pBrand = null;
        try {
            if ($u->tenant_id && \Illuminate\Support\Facades\Schema::hasTable('isp_portal_settings')) {
                $pBrand = \Illuminate\Support\Facades\DB::table('isp_portal_settings')
                    ->where('tenant_id', $u->tenant_id)
                    ->value('brand_name');
            }
        } catch (\Throwable $e) {}
        $defaults = ['hsmart', 'hsmart isp', 'hsmart wifi', 'hsmart wifi billing', 'default isp', 'default tenant', 'isp networks'];
        $cands = array_filter([
            $rComp,
            $pBrand,
            $tName,
            $u->business_name ?? ($u->company ?? null),
            $u->name,
        ], fn($v) => !empty($v) && !in_array(strtolower(trim((string) $v)), $defaults));
        $topBrandName = reset($cands) ?: ($tName ?: ($u->name ?: 'HSmart'));
    }
@endphp

<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg d-flex align-items-center justify-content-between gap-1 gap-sm-2 w-100 min-w-0 flex-nowrap" style="display: none;">
    <div class="d-flex align-items-center gap-1 gap-sm-2 min-w-0 flex-shrink-0">
        <!-- Hamburger on Mobile / Tablet -->
        <button class="btn navbar-toggler-humburger-icon navbar-toggler p-0 d-xl-none border-0" type="button" id="mobileMenuToggleBtn" data-mobile-menu-toggle="true" onclick="window.toggleMobileDrawer && window.toggleMobileDrawer(event)" aria-label="Toggle navigation" style="width: 38px; height: 38px; display: inline-grid; place-items: center; border-radius: 8px; flex-shrink: 0; background: transparent; cursor: pointer;">
            <span class="navbar-toggle-icon" style="pointer-events: none;">
                <span class="toggle-line" style="pointer-events: none;"></span>
            </span>
        </button>

        <!-- Brand Title: Hidden on screens < md, small & truncated on md+ -->
        <div class="d-none d-md-flex align-items-center text-truncate me-2" style="max-width: 220px;">
            <a href="{{ route('dashboard') }}" class="text-decoration-none text-truncate d-flex align-items-center gap-1.5" style="max-width: 220px;">
                <span class="fw-semibold text-truncate" style="font-size: 0.875rem; color: var(--text-primary, #0f172a); max-width: 220px;">{{ $topBrandName }}</span>
            </a>
        </div>

        <!-- Breadcrumb on md+ screens -->
        <div class="d-none d-lg-flex align-items-center text-truncate" style="max-width: 280px;">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 py-0 align-items-center" style="font-size: 0.8125rem; font-weight: 500;">
                    <li class="breadcrumb-item d-flex align-items-center text-truncate">
                        <a href="{{ route('dashboard') }}" class="text-secondary text-decoration-none d-flex align-items-center gap-1 text-truncate" style="color: var(--text-secondary, #64748b);">
                            <span class="bi bi-house-door-fill" style="color: var(--text-secondary, #94a3b8); font-size: 13px;"></span>
                        </a>
                    </li>
                    <li class="breadcrumb-item active fw-bold text-truncate" aria-current="page" style="color: var(--text-primary, #0f172a);">
                        {{ $header ?? __('Dashboard') }}
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Right Side Toolbar: Search & User Controls -->
    <div class="d-flex align-items-center gap-1 ms-auto flex-shrink-0 flex-nowrap">
        <div class="collapse navbar-collapse scrollbar d-none d-md-block" id="navbarStandard">
            @include('layouts.partials.searchbar')
        </div>
        @include('layouts.partials.userpanel')
    </div>
</nav>

<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg d-flex align-items-center justify-content-between gap-2 w-100 min-w-0" style="display: none;" data-move-target="#navbarVerticalNav" data-navbar-top="combo">
    <div class="d-flex align-items-center gap-2 min-w-0">
        <button class="btn navbar-toggler-humburger-icon navbar-toggler p-0 d-xl-none border-0" type="button" id="mobileMenuToggleBtnCombo" data-mobile-menu-toggle="true" onclick="window.toggleMobileDrawer && window.toggleMobileDrawer(event)" aria-label="Toggle navigation" style="width: 38px; height: 38px; display: inline-grid; place-items: center; border-radius: 8px; flex-shrink: 0; background: transparent; cursor: pointer;">
            <span class="navbar-toggle-icon" style="pointer-events: none;">
                <span class="toggle-line" style="pointer-events: none;"></span>
            </span>
        </button>

        <div class="d-none d-md-flex align-items-center text-truncate me-2" style="max-width: 220px;">
            <a href="{{ route('dashboard') }}" class="text-decoration-none text-truncate d-flex align-items-center gap-1.5" style="max-width: 220px;">
                <span class="fw-semibold text-truncate" style="font-size: 0.875rem; color: var(--text-primary, #0f172a); max-width: 220px;">{{ $topBrandName }}</span>
            </a>
        </div>
    </div>
    <div class="d-flex align-items-center gap-1 ms-auto flex-shrink-0">
        @include('layouts.partials.navpanel')
        @include('layouts.partials.userpanel')
    </div>
</nav>
