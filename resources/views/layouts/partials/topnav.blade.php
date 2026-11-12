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
<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" style="display: none;">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle menu" style="width: 40px; height: 40px; display: inline-grid; place-items: center; border-radius: 8px; flex-shrink: 0;">
        <span class="navbar-toggle-icon">
            <span class="toggle-line"></span>
        </span>
    </button>
    <!-- Breadcrumb on Left -->
    <div class="d-none d-md-flex align-items-center me-3 text-truncate" style="max-width: 320px;">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-0 align-items-center" style="font-size: 0.8125rem; font-weight: 500;">
                <li class="breadcrumb-item d-flex align-items-center text-truncate">
                    <a href="{{ route('dashboard') }}" class="text-secondary text-decoration-none d-flex align-items-center gap-1 text-truncate" style="color: #64748b;">
                        <span class="bi bi-house-door-fill" style="color: #94a3b8; font-size: 13px;"></span>
                        <span class="text-truncate">{{ $topBrandName }}</span>
                    </a>
                </li>
                <li class="breadcrumb-item active text-dark fw-bold text-truncate" aria-current="page" style="color: #0f172a;">
                    {{ $header ?? __('Dashboard') }}
                </li>
            </ol>
        </nav>
    </div>
    <a class="navbar-brand me-1 me-sm-2 d-md-none text-truncate" href="{{ route('dashboard') }}">
        <div class="d-flex align-items-center">
            <span class="font-sans-serif fw-bold text-truncate" style="color: #F57C00;">{{ $topBrandName }}</span>
        </div>
    </a>
    <div class="collapse navbar-collapse scrollbar" id="navbarStandard">
        @include('layouts.partials.searchbar')
    </div>
    @include('layouts.partials.userpanel')
</nav>

<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" style="display: none;" data-move-target="#navbarVerticalNav" data-navbar-top="combo">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle menu" style="width: 40px; height: 40px; display: inline-grid; place-items: center; border-radius: 8px; flex-shrink: 0;">
        <span class="navbar-toggle-icon">
            <span class="toggle-line"></span>
        </span>
    </button>
    <!-- Breadcrumb on Left -->
    <div class="d-none d-md-flex align-items-center me-3 text-truncate" style="max-width: 320px;">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-0 align-items-center" style="font-size: 0.8125rem; font-weight: 500;">
                <li class="breadcrumb-item d-flex align-items-center text-truncate">
                    <a href="{{ route('dashboard') }}" class="text-secondary text-decoration-none d-flex align-items-center gap-1 text-truncate" style="color: #64748b;">
                        <span class="bi bi-house-door-fill" style="color: #94a3b8; font-size: 13px;"></span>
                        <span class="text-truncate">{{ $topBrandName }}</span>
                    </a>
                </li>
                <li class="breadcrumb-item active text-dark fw-bold text-truncate" aria-current="page" style="color: #0f172a;">
                    {{ $header ?? __('Dashboard') }}
                </li>
            </ol>
        </nav>
    </div>
    <a class="navbar-brand me-1 me-sm-2 d-md-none text-truncate" href="{{ route('dashboard') }}">
        <div class="d-flex align-items-center">
            <span class="font-sans-serif fw-bold text-truncate" style="color: #F57C00;">{{ $topBrandName }}</span>
        </div>
    </a>
    @include('layouts.partials.navpanel')
    @include('layouts.partials.userpanel')
</nav>
