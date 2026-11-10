<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <!-- ===============================================-->
    <!--    Document Title-->
    <!-- ===============================================-->
    <title>{{ siteUrlSettings('site_title') ?? config('app.name') }}</title>

    <link rel="shortcut icon" href="{{ site_image(siteUrlSettings('site_favicon'), 'images/favicon.png') }}" type="image/x-icon">

    <!-- ===============================================-->
    <!--    Favicon-->
    <!-- ===============================================-->
    {{-- <link rel="apple-touch-icon" sizes="180x180" href="{{asset('/images/favicons/apple-touch-icon.png')}}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{asset('/images/favicons/favicon-32x32.png')}}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{asset('/images/favicons/favicon-16x16.png')}}">
    <link rel="shortcut icon" type="image/x-icon" href="{{asset('/images/favicons/favicon.ico')}}">
    <meta name="msapplication-TileImage" content="{{asset('/images/favicons/mstile-150x150.png')}}"> --}}

    <!-- Jost Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/sass/app.scss', 'resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @filamentStyles
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
    @livewireStyles
    {!! \App\Support\Hooks::applyFilters('layout.head', '') !!}

    <style>
        :root {
            --font-sans: 'Jost', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --primary: #F47B06;
            --primary-hover: #E86F00;
            --accent-purple: #6D3FE0;
            --card-border: #E6E3F5;
            --bg-page: #FAFAF8;
            --dark-navy: #161B2E;
        }

        body, .font-sans, .fi-body, table, input, button, select, textarea, .nav-link, .dropdown-item {
            font-family: var(--font-sans) !important;
        }

        /* Sidebar as White Rounded Card */
        .navbar-vertical {
            background: #ffffff !important;
            border: 1px solid var(--card-border) !important;
            border-radius: 20px !important;
            margin: 14px 0 14px 14px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04) !important;
        }

        .navbar-vertical .navbar-brand {
            padding: 18px 20px !important;
            border-bottom: 1px solid #f0edf8 !important;
        }

        .navbar-vertical-label {
            font-size: 0.68rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.08em !important;
            text-transform: uppercase !important;
            color: #8882a5 !important;
        }

        .navbar-vertical .nav-link {
            border-radius: 10px !important;
            margin: 2px 10px !important;
            font-weight: 600 !important;
            color: #333333 !important;
            transition: all 0.2s ease !important;
        }

        .navbar-vertical .nav-link:hover {
            background: #fff4eb !important;
            color: #F47B06 !important;
        }

        .navbar-vertical .nav-link.active {
            background: #F47B06 !important;
            color: #111111 !important;
            font-weight: 700 !important;
        }

        /* Top Bar Styling */
        .navbar-top {
            background: #ffffff !important;
            border: 1px solid var(--card-border) !important;
            border-radius: 16px !important;
            margin: 14px 14px 14px 0 !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03) !important;
            padding: 0 20px !important;
            min-height: 58px !important;
            height: 58px !important;
            display: flex !important;
            align-items: center !important;
        }

        /* Toolbar Icon Buttons */
        .btn-toolbar-icon {
            width: 36px !important;
            height: 36px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 10px !important;
            color: #6B7280 !important;
            background: transparent !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
            padding: 0 !important;
            border: none !important;
        }
        .btn-toolbar-icon:hover {
            background: #F3F4F6 !important;
            color: #F47B06 !important;
        }
        .btn-toolbar-icon:focus {
            box-shadow: none !important;
        }
        .dropdown-caret-none::after {
            display: none !important;
        }

        .dark .navbar-top {
            background: #1A2035 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        .dark .btn-toolbar-icon {
            color: #9CA3AF !important;
        }
        .dark .btn-toolbar-icon:hover {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #F47B06 !important;
        }

        /* Cards Design System */
        .card {
            border: 1px solid var(--card-border) !important;
            border-radius: 18px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        /* Status Badges */
        .badge.bg-success, .badge-subtle-success, .status-active, .status-paid {
            background-color: #ECFDF5 !important;
            color: #059669 !important;
            border: 1px solid #A7F3D0 !important;
            border-radius: 999px !important;
            font-weight: 600 !important;
        }

        .badge.bg-warning, .badge-subtle-warning, .status-pending {
            background-color: #F3F4F6 !important;
            color: #4B5563 !important;
            border: 1px solid #E5E7EB !important;
            border-radius: 999px !important;
            font-weight: 600 !important;
        }

        .badge.bg-danger, .badge-subtle-danger, .status-expired, .status-overdue {
            background-color: #FEF2F2 !important;
            color: #DC2626 !important;
            border: 1px solid #FECACA !important;
            border-radius: 999px !important;
            font-weight: 600 !important;
        }

        /* Active Tab: Black pill with white text */
        .nav-tabs .nav-link.active, .nav-pills .nav-link.active, [role="tab"].active, [role="tab"][aria-selected="true"] {
            background-color: #111111 !important;
            color: #ffffff !important;
            border-radius: 999px !important;
            border: none !important;
            font-weight: 700 !important;
        }

        /* Tables */
        .table {
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }

        .table thead th {
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            color: #666666 !important;
            background-color: #FAFAF8 !important;
            border-bottom: 1px solid var(--card-border) !important;
        }

        .table th.text-end, .table td.text-end, .table td:has(.currency-amount) {
            text-align: right !important;
        }

        /* Small pill row actions */
        .btn-action-pill, .table .btn-sm {
            border-radius: 999px !important;
            padding: 4px 12px !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
        }

        /* Primary Button */
        .btn-primary, .btn-primary:focus {
            background-color: #F47B06 !important;
            border-color: #F47B06 !important;
            color: #111111 !important;
            font-weight: 700 !important;
            border-radius: 12px !important;
        }

        .btn-primary:hover {
            background-color: #E86F00 !important;
            border-color: #E86F00 !important;
            color: #111111 !important;
        }

        /* Skeleton loading animation */
        @keyframes skeleton-loading {
            0% { background-color: #f0edf8; }
            50% { background-color: #f9f8fc; }
            100% { background-color: #f0edf8; }
        }
        .skeleton {
            animation: skeleton-loading 1.4s ease-in-out infinite;
            border-radius: 8px;
        }
    </style>

    <!-- ===============================================-->
    <!--    Stylesheets-->
    <!-- ===============================================-->
    <script>
        @php
            $tz = config('app.timezone', 'Africa/Nairobi');
            $phoneCountry = 'ke';
            if (class_exists('IntlTimeZone')) {
                $region = \IntlTimeZone::getRegion($tz);
                if ($region && strlen($region) === 2) {
                    $phoneCountry = strtolower($region);
                }
            }
        @endphp
        window.sitePhoneCountry = '{{ $phoneCountry }}';
        var isRTL = JSON.parse(localStorage.getItem('isRTL'));
        if (isRTL) {
            document.querySelector('html').setAttribute('dir', 'rtl');
        } else {
            document.querySelector('html').setAttribute('dir', 'ltr');
        }

        // for sidebar collapse and expand
        if (JSON.parse(localStorage.getItem("isNavbarVerticalCollapsed"))) {
            document.documentElement.classList.add("navbar-vertical-collapsed");
        } else {
            document.documentElement.classList.remove("navbar-vertical-collapsed");
        }

        // for theme dark and light
        (() => {
            const isTheme = localStorage.getItem('theme') || 'auto';
            let appliedTheme = isTheme;
            if (isTheme === 'auto') {
                appliedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', appliedTheme);
            if (appliedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
</head>


<body class="font-sans antialiased fi-body">
    @if(session('is_impersonating'))
        <div style="background: linear-gradient(90deg, #1e1b4b 0%, #312e81 100%); color: #ffffff; padding: 10px 24px; display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 600; z-index: 99999; position: sticky; top: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.2); border-bottom: 2px solid #818cf8;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="background: #ef4444; color: #fff; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.5px;">Impersonating</span>
                <span>You are currently managing <strong>{{ Auth::user()->name }}</strong> (ISP Admin &bull; {{ Auth::user()->email }})</span>
            </div>
            <a href="{{ route('sso.exit-impersonation') }}" style="background: #ffffff; color: #1e1b4b; padding: 6px 14px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s;">
                <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Exit & Return to Super Admin</span>
            </a>
        </div>
    @endif
    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
        <div class="container-fluid" data-layout="container">
            <script>
                var isFluid = JSON.parse(localStorage.getItem('isFluid'));
                if (isFluid == null || isFluid == false) {
                    var container = document.querySelector('[data-layout="container"]');
                    if (container) {
                        container.classList.remove('container-fluid');
                        container.classList.add('container');
                    }
                }
            </script>
            @include('layouts.partials.sidenav')
            <div class="content">
                @include('layouts.partials.mobile-button-nav')


                @include('layouts.partials.topnav')

                <script>
                    var storedNavbarPosition = localStorage.getItem('navbarPosition');
                    var navVerticalEl = document.querySelector('.navbar-vertical');
                    var navTopVerticalEl = document.querySelector('.content .navbar-top');
                    var navTopEl = document.querySelector('[data-layout] .navbar-top:not([data-double-top-nav])');
                    var navDoubleTopEl = document.querySelector('[data-double-top-nav]');
                    var navTopComboEl = document.querySelector('.content [data-navbar-top="combo"]');

                    if (localStorage.getItem('navbarPosition') === 'double-top') {
                        document.documentElement.classList.toggle('double-top-nav-layout');
                    }

                    if (storedNavbarPosition === 'top') {
                        if (navTopEl) navTopEl.removeAttribute('style');
                        if (navTopVerticalEl) navTopVerticalEl.remove();
                        if (navVerticalEl) navVerticalEl.remove();
                        if (navTopComboEl) navTopComboEl.remove();
                        if (navDoubleTopEl) navDoubleTopEl.remove();
                    } else if (storedNavbarPosition === 'combo') {
                        if (navVerticalEl) navVerticalEl.removeAttribute('style');
                        if (navTopComboEl) navTopComboEl.removeAttribute('style');
                        if (navTopEl) navTopEl.remove();
                        if (navTopVerticalEl) navTopVerticalEl.remove();
                        if (navDoubleTopEl) navDoubleTopEl.remove();
                    } else if (storedNavbarPosition === 'double-top') {
                        if (navDoubleTopEl) navDoubleTopEl.removeAttribute('style');
                        if (navTopVerticalEl) navTopVerticalEl.remove();
                        if (navVerticalEl) navVerticalEl.remove();
                        if (navTopEl) navTopEl.remove();
                        if (navTopComboEl) navTopComboEl.remove();
                    } else {
                        if (navVerticalEl) navVerticalEl.removeAttribute('style');
                        if (navTopVerticalEl) navTopVerticalEl.removeAttribute('style');
                        if (navTopEl) navTopEl.remove();
                        if (navDoubleTopEl) navDoubleTopEl.remove();
                        if (navTopComboEl) navTopComboEl.remove();
                    }
                </script>
                {{ $slot }}
                <footer class="footer">
                    <div class="row g-0 justify-content-between fs-10 mt-4 mb-3 px-3">
                        <div class="col-12 col-sm-auto text-center">
                            <p class="mb-0 text-600">&copy; {{ date('Y') }} <strong>HSmart WiFi Billing</strong>. All rights reserved.</p>
                        </div>
                        <div class="col-12 col-sm-auto text-center">
                            <p class="mb-0 text-600">v2.0 (Kenyan Localization)</p>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </main>

    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->
    @include('layouts.partials.customize')

    @stack('modals')

    @livewireScripts
    @filamentScripts
    <script data-navigate-once>
        function handleNavbarHover() {
            const navbarCollapse = document.getElementById('navbarVerticalCollapse');
            const localStorageCollapsed = localStorage.getItem('isNavbarVerticalCollapsed');

            if (navbarCollapse && localStorageCollapsed === 'true') {
                // Remove previous listeners to avoid duplication
                navbarCollapse.removeEventListener('mouseenter', addHoverClass);
                navbarCollapse.removeEventListener('mouseleave', removeHoverClass);

                navbarCollapse.addEventListener('mouseenter', addHoverClass);
                navbarCollapse.addEventListener('mouseleave', removeHoverClass);
            }

            function addHoverClass() {
                document.documentElement.classList.add('navbar-vertical-collapsed-hover');
            }

            function removeHoverClass() {
                document.documentElement.classList.remove('navbar-vertical-collapsed-hover');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof $ !== 'undefined') {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                // remove invalid feedback and error
                $('input, textarea, select').on('focus', function() {
                    $(this).removeClass('is-invalid'); // remove invalid class
                    $(this).nextAll('.invalid-feedback').remove(); // remove invalid feedback
                });
            }

            handleNavbarHover();
        });


        // Run on Livewire navigate
        document.addEventListener('livewire:navigated', handleNavbarHover);

        // For Livewire SPA support to maintain sidebar state
        document.addEventListener('livewire:navigated', () => {
            const isCollapsedStorage = localStorage.getItem("isNavbarVerticalCollapsed");
            if (isCollapsedStorage === 'true' || isCollapsedStorage === true) {
                document.documentElement.classList.add("navbar-vertical-collapsed");
            } else {
                document.documentElement.classList.remove("navbar-vertical-collapsed");
            }
        });

        // For theme toggle
        function themeToggle() {
            return {
                theme: localStorage.getItem('theme') || 'auto',

                init() {
                    this.applyTheme(this.theme);

                    // For Livewire SPA support
                    window.addEventListener('livewire:navigated', () => {
                        this.applyTheme(this.theme);
                    });
                },

                setTheme(theme) {
                    this.theme = theme;
                    localStorage.setItem('theme', theme);
                    this.applyTheme(theme);
                },

                applyTheme(theme) {
                    let applied = theme;
                    if (theme === 'auto') {
                        const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                        applied = isDark ? 'dark' : 'light';
                    }
                    document.documentElement.setAttribute('data-bs-theme', applied);
                    if (applied === 'dark') {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                }
            }
        }

        function layoutController() {
            return {
                isFluid: JSON.parse(localStorage.getItem('isFluid')), // default is container

                initLayout() {
                    this.applyLayout();

                    // Livewire wire:navigate support
                    window.addEventListener('livewire:navigated', () => {
                        this.applyLayout();
                    });
                },

                toggleLayout() {
                    this.isFluid = !this.isFluid;
                    localStorage.setItem('isFluid', this.isFluid);
                    this.applyLayout();
                },

                applyLayout() {
                    const container = document.querySelector('[data-layout]');
                    if (container) {
                        container.classList.toggle('container', !this.isFluid);
                        container.classList.toggle('container-fluid', this.isFluid);
                    }
                }
            }
        }

        // For side nav style
        function verticalNavbarStyle() {
            return {
                isNavbarStyle: localStorage.getItem('navbarStyle') || 'transparent',

                initNavStyle() {
                    this.applyNavbarStyle(this.isNavbarStyle);

                    // For Livewire SPA support
                    window.addEventListener('livewire:navigated', () => {
                        this.applyNavbarStyle(this.isNavbarStyle);
                    });
                },

                setNavbarStyle(style) {
                    this.isNavbarStyle = style;
                    localStorage.setItem('navbarStyle', style);
                    this.applyNavbarStyle(style);
                },

                applyNavbarStyle(style) {
                    const navbarVertical = document.querySelector('.navbar-vertical');
                    if (!navbarVertical) return;
                    // Remove previous navbar-* class
                    navbarVertical.classList.forEach((cls) => {
                        if (cls.startsWith('navbar-') && cls !== 'navbar' && cls !== 'navbar-light' && cls !==
                            'navbar-vertical' && cls !== 'navbar-expand-xl') {
                            navbarVertical.classList.remove(cls);
                        }
                    });

                    // Add the new class
                    if (style && style !== 'transparent') {
                        navbarVertical.classList.add(`navbar-${style}`);
                    } else {
                        // If style is 'transparent', remove all specific styles
                        navbarVertical.classList.remove('navbar-inverted', 'navbar-card', 'navbar-vibrant');
                    }
                }
            }
        }

        // For navbar position

        function navbarPosition() {
            return {
                isNavbarPosition: 'vertical', // default is vertical
                initNavPosition() {
                    this.isNavbarPosition = localStorage.getItem('navbarPosition') || 'vertical';

                    // Livewire SPA support
                    window.addEventListener('livewire:navigated', () => {
                        this.isNavbarPosition = localStorage.getItem('navbarPosition') || 'vertical';
                    });
                },

                setNavbarPosition(position) {
                    localStorage.setItem('navbarPosition', position);
                    location.reload();
                }
            }
        }

        // For RTL toggle
        function rtlController() {
            return {
                isRTL: JSON.parse(localStorage.getItem('isRTL')) || false,

                initRTL() {
                    this.applyRTL();

                    // Livewire SPA support
                    window.addEventListener('livewire:navigated', () => {
                        this.applyRTL();
                    });
                },

                toggleRTL() {
                    this.isRTL = !this.isRTL;
                    localStorage.setItem('isRTL', this.isRTL);
                    this.applyRTL();
                },

                applyRTL() {
                    document.querySelector('html').setAttribute('dir', this.isRTL ? 'rtl' : 'ltr');
                }
            }
        }

        /* -------------------------------------------------------------------------- */
        /*                         Auto-Dynamic DataTables                            */
        /* -------------------------------------------------------------------------- */
        var dataTablesInitTimeout = null;
        function initDynamicDataTables() {
            if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') return;

            // Debounce to prevent multiple rapid initializations during morphing
            if (dataTablesInitTimeout) clearTimeout(dataTablesInitTimeout);

            dataTablesInitTimeout = setTimeout(() => {
                $('table.data-table').each(function() {
                    const $table = $(this);

                    // Skip if the table is no longer in the document
                    if (!document.body.contains(this)) return;

                    const rowCount = $table.find('tbody tr').length;

                    // Always ensure it's destroyed before re-thinking (morph.updating usually handles this, but safety first)
                    if ($.fn.DataTable.isDataTable(this)) {
                        $table.DataTable().destroy();
                    }

                    if (rowCount > 20) {
                        $table.DataTable({
                            responsive: true,
                            pageLength: 20,
                            lengthMenu: [[10, 20, 50, 100, -1], [10, 20, 50, 100, "All"]],
                            dom: '<"d-flex justify-content-between align-items-center mb-2"Bf>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
                            buttons: ['copy', 'excel', 'pdf', 'print'],
                            destroy: true
                        });
                    }
                });
            }, 150); // 150ms debounce for stability
        }

        document.addEventListener('DOMContentLoaded', initDynamicDataTables);
        document.addEventListener('livewire:navigated', initDynamicDataTables);
        window.addEventListener('reinit-datatables', initDynamicDataTables);

        // Livewire 3 hooks for robust 3rd party integration (e.g., DataTables)
        document.addEventListener('livewire:init', () => {
            // 1. Destroy ALL DataTables on the page BEFORE any component morph starts.
            // Broad destruction is safer in Livewire 3 to prevent parent-child node conflicts.
            Livewire.hook('commit.prepare', ({ component }) => {
                $('table.data-table').each(function() {
                    if ($.fn.DataTable.isDataTable(this)) {
                        $(this).DataTable().destroy();
                    }
                });
            });

           // 2. Re-initialize AFTER the ENTIRE morph cycle is finished
           // commit.respond fires after the response is received and the DOM has been fully patched.
           Livewire.hook('commit.respond', ({ component }) => {
               // Wait a bit longer to be absolutely sure the DOM has settled across all browsers
               setTimeout(() => {
                   // Only re-init if the component is still present in the DOM
                   if (component.el && document.body.contains(component.el)) {
                       initDynamicDataTables();
                   }
               }, 300);
           });
        });
    </script>

    @stack('scripts')
    {!! \App\Support\Hooks::applyFilters('layout.scripts', '') !!}

    {{-- Attribution notice — required by LICENSE. Do not remove. See: https://codepagol.com --}}
    <div id="codepagol-attribution" style="display:none" aria-hidden="true"
         data-credit="Powered by CodePagol | https://codepagol.com"></div>

</body>
</html>
