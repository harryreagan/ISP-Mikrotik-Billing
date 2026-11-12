<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" data-double-top-nav="data-double-top-nav" style="display: none;">
    <div class="w-100">
        <div class="d-flex flex-between-center">
            {{-- logo and Site name --}}
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

<nav class="navbar navbar-light navbar-vertical navbar-expand-xl" style="display: none;">
    <script>
        var navbarStyle = localStorage.getItem("navbarStyle");
        if (navbarStyle && navbarStyle !== 'transparent') {
            document.querySelector('.navbar-vertical').classList.add(`navbar-${navbarStyle}`);
        }
    </script>
    <div class="d-flex align-items-center px-3 border-bottom border-200" style="height: 64px;">
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
