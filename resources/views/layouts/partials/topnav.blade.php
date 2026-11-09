<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" style="display: none;">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
        <span class="navbar-toggle-icon">
            <span class="toggle-line"></span>
        </span>
    </button>
    <!-- Breadcrumb on Left -->
    <div class="d-none d-md-flex align-items-center me-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-0" style="font-size: 0.85rem; font-weight: 600;">
                <li class="breadcrumb-item"><a href="{{ url('/') }}" style="color: #666; text-decoration: none;">HSmart</a></li>
                <li class="breadcrumb-item active" aria-current="page" style="color: #111; font-weight: 700;">{{ $header ?? __('Dashboard') }}</li>
            </ol>
        </nav>
    </div>
    <a class="navbar-brand me-1 me-sm-3 d-md-none" href="{{ url('/') }}">
        <div class="d-flex align-items-center">
            <span class="font-sans-serif fw-bold" style="color: #F47B06;">HSmart</span>
        </div>
    </a>
    <div class="collapse navbar-collapse scrollbar" id="navbarStandard">
        @include('layouts.partials.searchbar')
    </div>
    @include('layouts.partials.userpanel')
</nav>

<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" style="display: none;" data-move-target="#navbarVerticalNav" data-navbar-top="combo">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
        <span class="navbar-toggle-icon">
            <span class="toggle-line"></span>
        </span>
    </button>
    <!-- Breadcrumb on Left -->
    <div class="d-none d-md-flex align-items-center me-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-0" style="font-size: 0.85rem; font-weight: 600;">
                <li class="breadcrumb-item"><a href="{{ url('/') }}" style="color: #666; text-decoration: none;">HSmart</a></li>
                <li class="breadcrumb-item active" aria-current="page" style="color: #111; font-weight: 700;">{{ $header ?? __('Dashboard') }}</li>
            </ol>
        </nav>
    </div>
    <a class="navbar-brand me-1 me-sm-3 d-md-none" href="{{ url('/') }}">
        <div class="d-flex align-items-center">
            <span class="font-sans-serif fw-bold" style="color: #F47B06;">HSmart</span>
        </div>
    </a>
    @include('layouts.partials.navpanel')
    @include('layouts.partials.userpanel')
</nav>
