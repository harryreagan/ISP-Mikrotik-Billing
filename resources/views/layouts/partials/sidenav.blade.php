<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" data-double-top-nav="data-double-top-nav" style="display: none;">
    <div class="w-100">
        <div class="d-flex flex-between-center">
            <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarDoubleTop" aria-controls="navbarDoubleTop" aria-expanded="false" aria-label="Toggle Navigation">
                <span class="navbar-toggle-icon">
                    <span class="toggle-line"></span>
                </span>
            </button>
            <a class="navbar-brand me-1 me-sm-3" href="{{ route('dashboard') }}">
                <div class="d-flex align-items-center">
                    <x-hsmart-logo color="dark" height="34px" />
                </div>
            </a>
            @include('layouts.partials.searchbar')
            @include('layouts.partials.userpanel')
        </div>

        <hr class="my-2 d-none d-lg-block" />

        <div class="collapse navbar-collapse scrollbar py-lg-2" id="navbarDoubleTop">
            @include('layouts.partials.navpanel')
        </div>
    </div>
</nav>

<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" style="display: none;">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarStandard" aria-controls="navbarStandard" aria-expanded="false" aria-label="Toggle Navigation">
        <span class="navbar-toggle-icon">
            <span class="toggle-line"></span>
        </span>
    </button>
    <a class="navbar-brand me-1 me-sm-3" href="{{ route('dashboard') }}">
        <div class="d-flex align-items-center">
            <x-hsmart-logo color="dark" height="34px" />
        </div>
    </a>
    <div class="collapse navbar-collapse scrollbar" id="navbarStandard">
        @include('layouts.partials.navpanel')
    </div>
    @include('layouts.partials.userpanel')
</nav>

{{-- Desktop Sidebar (Strictly >= 1200px) --}}
<nav class="navbar navbar-light navbar-vertical navbar-expand-xl" style="display: none;">
    <script>
        var navbarStyle = localStorage.getItem("navbarStyle");
        if (navbarStyle && navbarStyle !== 'transparent') {
            document.querySelector('.navbar-vertical').classList.add(`navbar-${navbarStyle}`);
        }
    </script>
    <div class="d-flex align-items-center justify-content-between px-3 border-bottom border-200" style="height: 64px;">
        <a class="navbar-brand m-0 p-0 text-decoration-none d-flex align-items-center" href="{{ route('dashboard') }}" style="text-decoration: none;">
            <x-hsmart-logo color="dark" height="40px" />
        </a>
    </div>
    <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
        <div class="navbar-vertical-content scrollbar">
            @include('layouts.partials.sidebarpanel')
        </div>
    </div>
</nav>

{{-- Dedicated Mobile Sidebar Drawer & Backdrop (< 1200px) --}}
<div id="mobileDrawerBackdrop" class="mobile-drawer-backdrop" aria-hidden="true" style="display: none;"></div>

<div id="mobileSidebarDrawer" class="mobile-sidebar-drawer navbar-vertical" aria-label="Mobile Navigation" role="dialog" aria-modal="true" style="display: none;">
    <div class="mobile-drawer-header d-flex align-items-center justify-content-between px-3 border-bottom" style="height: 64px; flex-shrink: 0; background: #ffffff;">
        <a class="navbar-brand m-0 p-0 text-decoration-none d-flex align-items-center" href="{{ route('dashboard') }}">
            <x-hsmart-logo color="dark" height="36px" />
        </a>
        <button type="button" class="btn btn-sm btn-link text-secondary p-1 text-decoration-none" id="mobileDrawerCloseBtn" aria-label="Close menu" style="font-size: 1.25rem; width: 36px; height: 36px; display: inline-grid; place-items: center; border-radius: 8px;">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="mobile-drawer-body scrollbar" style="flex: 1 1 auto; overflow-y: auto; overflow-x: hidden; padding-bottom: 2.5rem; background: #ffffff;">
        @include('layouts.partials.sidebarpanel')
    </div>
</div>
