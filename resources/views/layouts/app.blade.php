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
            --bg-page: #f8fafc;
            --surface: #ffffff;
            --border-ring: rgba(226, 232, 240, 0.7);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-disabled: #94a3b8;
            --accent: #f97316;
            --accent-hover: #ea580c;
            --accent-tint: #fff7ed;
            --primary: #f97316;
            --primary-hover: #ea580c;
            --card-border: rgba(226, 232, 240, 0.7);
        }

        html.dark, [data-bs-theme="dark"] {
            --bg-page: #0f1220;
            --surface: #171b2e;
            --border-ring: rgba(51, 65, 85, 0.6);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-disabled: #64748b;
            --accent-tint: rgba(249, 115, 22, 0.14);
            --card-border: rgba(51, 65, 85, 0.6);
        }

        body, .font-sans, .fi-body, table, input, button, select, textarea, .nav-link, .dropdown-item {
            font-family: var(--font-sans) !important;
        }

        body, .main, .content {
            background-color: var(--bg-page) !important;
            color: var(--text-primary) !important;
        }

        /* Enforce sentence case globally where ALL CAPS was overused */
        .text-uppercase, .uppercase, th, .navbar-vertical-label, .form-label, label, .badge {
            text-transform: none !important;
            letter-spacing: normal !important;
        }

        /* Typography Tokens */
        .ui-page-title, h1.h3, h1.h4, .card-header h5, .card-header h4 {
            font-size: 1.125rem !important;
            font-weight: 600 !important;
            letter-spacing: -0.015em !important;
            color: var(--text-primary) !important;
        }

        .ui-page-subtitle {
            font-size: 0.75rem !important;
            color: var(--text-secondary) !important;
        }

        .ui-section-title, h5, h6 {
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            color: var(--text-primary) !important;
        }

        /* Stat Cards (Semantic Gradients Kept, Sized Cleanly) */
        .ui-stat-card, .stat-card {
            border-radius: 1rem !important;
            padding: 1.25rem !important;
            border: none !important;
            box-shadow: none !important;
        }
        .ui-stat-label, .stat-card .stat-label {
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            opacity: 0.88 !important;
            text-transform: none !important;
            letter-spacing: normal !important;
        }
        .ui-stat-value, .stat-card .stat-value {
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            font-variant-numeric: tabular-nums !important;
            line-height: 1.2 !important;
        }
        .ui-stat-sub {
            font-size: 11px !important;
            opacity: 0.78 !important;
        }
        .ui-stat-icon, .stat-card .stat-icon {
            font-size: 1.35rem !important;
            opacity: 0.35 !important;
        }

        @media (max-width: 575.98px) {
            .ui-stat-card, .stat-card {
                padding: 0.85rem !important;
                border-radius: 0.85rem !important;
            }
            .ui-stat-value, .stat-card .stat-value {
                font-size: 1.2rem !important;
            }
            .ui-stat-label, .stat-card .stat-label {
                font-size: 0.7rem !important;
            }
            .ui-stat-icon, .stat-card .stat-icon {
                font-size: 1.1rem !important;
            }
        }

        /* Sidebar: Calm White Surface, Thin 1px Right Border, No Heavy Shadow */
        .navbar-vertical {
            background: var(--surface) !important;
            border: none !important;
            border-right: 1px solid var(--border-ring) !important;
            border-radius: 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
            transition: width 0.2s cubic-bezier(0.4, 0, 0.2, 1), min-width 0.2s cubic-bezier(0.4, 0, 0.2, 1), max-width 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        .navbar-vertical .navbar-vertical-content,
        .navbar-vertical .scrollbar {
            background: var(--surface) !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            scrollbar-width: thin !important;
            scrollbar-color: rgba(203, 213, 225, 0.4) transparent !important;
            padding-bottom: 1.5rem !important;
        }

        .navbar-vertical .navbar-vertical-content::-webkit-scrollbar,
        .navbar-vertical .scrollbar::-webkit-scrollbar {
            width: 4px !important;
            background: transparent !important;
        }

        .navbar-vertical .navbar-vertical-content::-webkit-scrollbar-track,
        .navbar-vertical .scrollbar::-webkit-scrollbar-track {
            background: transparent !important;
        }

        .navbar-vertical .navbar-vertical-content::-webkit-scrollbar-thumb,
        .navbar-vertical .scrollbar::-webkit-scrollbar-thumb {
            background: rgba(203, 213, 225, 0.4) !important;
            border-radius: 9999px !important;
        }

        .navbar-vertical .navbar-vertical-content:hover::-webkit-scrollbar-thumb,
        .navbar-vertical .scrollbar:hover::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.7) !important;
        }

        .navbar-vertical .navbar-brand {
            padding: 0 16px !important;
            height: 64px !important;
            display: flex !important;
            align-items: center !important;
            border-bottom: 1px solid var(--border-ring) !important;
            background: var(--surface) !important;
        }

        .navbar-vertical-collapsed .navbar-vertical .navbar-brand {
            padding: 0 !important;
            justify-content: center !important;
        }

        .navbar-vertical-collapsed .navbar-vertical .brand-text-block {
            display: none !important;
        }

        .navbar-vertical .navbar-vertical-label-wrapper,
        .mobile-sidebar-drawer .navbar-vertical-label-wrapper,
        .navbar-vertical .row.navbar-vertical-label-wrapper,
        .mobile-sidebar-drawer .row.navbar-vertical-label-wrapper,
        .navbar-vertical-label-wrapper,
        .row.navbar-vertical-label-wrapper {
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 1.25rem !important;
            padding-right: 1rem !important;
            margin-top: 1rem !important;
            margin-bottom: 0.25rem !important;
            width: 100% !important;
            display: flex !important;
            box-sizing: border-box !important;
        }

        .navbar-vertical .navbar-vertical-label-wrapper > .col-auto,
        .mobile-sidebar-drawer .navbar-vertical-label-wrapper > .col-auto,
        .navbar-vertical-label-wrapper > .col-auto {
            padding-left: 0 !important;
            margin-left: 0 !important;
            flex: 0 0 auto !important;
            width: auto !important;
            box-sizing: border-box !important;
        }

        .navbar-vertical-label,
        .navbar-vertical .navbar-vertical-label,
        .mobile-sidebar-drawer .navbar-vertical-label {
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            color: #94a3b8 !important;
            padding-left: 0 !important;
            margin-left: 0 !important;
            white-space: nowrap !important;
            overflow: visible !important;
            display: inline-block !important;
        }

        .navbar-vertical-collapsed .navbar-vertical .navbar-vertical-label-wrapper {
            display: none !important;
        }

        .navbar-vertical-divider {
            display: none !important;
        }

        .navbar-vertical .nav-link,
        .mobile-sidebar-drawer .nav-link {
            border-radius: 0.5rem !important;
            margin: 2px 8px !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.8125rem !important;
            font-weight: 500 !important;
            color: #475569 !important;
            height: 40px !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.75rem !important;
            transition: background-color 0.15s ease, color 0.15s ease !important;
        }

        .mobile-sidebar-drawer .navbar-nav,
        .mobile-sidebar-drawer #navbarVerticalNav {
            padding-left: 0 !important;
            margin-left: 0 !important;
            width: 100% !important;
        }

        html.dark .navbar-vertical .nav-link,
        html.dark .mobile-sidebar-drawer .nav-link {
            color: #94a3b8 !important;
        }

        .navbar-vertical .nav-link .nav-link-icon,
        .mobile-sidebar-drawer .nav-link .nav-link-icon {
            font-size: 1rem !important;
            width: 1.25rem !important;
            height: 1.25rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #94a3b8 !important;
            flex-shrink: 0 !important;
        }

        .navbar-vertical .nav-link:hover,
        .mobile-sidebar-drawer .nav-link:hover {
            background: #f8fafc !important;
            color: var(--text-primary) !important;
        }

        html.dark .navbar-vertical .nav-link:hover,
        html.dark .mobile-sidebar-drawer .nav-link:hover {
            background: rgba(255, 255, 255, 0.04) !important;
            color: #f1f5f9 !important;
        }

        .navbar-vertical .nav-link.active,
        .mobile-sidebar-drawer .nav-link.active {
            background: #fff7ed !important;
            color: #ea580c !important;
            font-weight: 600 !important;
        }

        html.dark .navbar-vertical .nav-link.active,
        html.dark .mobile-sidebar-drawer .nav-link.active,
        [data-bs-theme="dark"] .navbar-vertical .nav-link.active,
        [data-bs-theme="dark"] .mobile-sidebar-drawer .nav-link.active {
            background: rgba(249, 115, 22, 0.16) !important;
            color: #f97316 !important;
            font-weight: 600 !important;
        }

        .navbar-vertical .nav-link.active .nav-link-icon,
        .navbar-vertical .nav-link.active i,
        .mobile-sidebar-drawer .nav-link.active .nav-link-icon,
        .mobile-sidebar-drawer .nav-link.active i {
            color: #ea580c !important;
        }

        html.dark .navbar-vertical .nav-link.active .nav-link-icon,
        html.dark .navbar-vertical .nav-link.active i,
        [data-bs-theme="dark"] .navbar-vertical .nav-link.active .nav-link-icon,
        [data-bs-theme="dark"] .navbar-vertical .nav-link.active i {
            color: #f97316 !important;
        }

        /* Top Bar Styling: Flat White, Thin 1px Bottom Border, Touch Cleanly */
        .navbar-top {
            background: var(--surface) !important;
            border: none !important;
            border-bottom: 1px solid var(--border-ring) !important;
            border-radius: 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
            padding: 0 1.25rem !important;
            min-height: 64px !important;
            height: 64px !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.75rem !important;
            flex-wrap: nowrap !important;
        }

        .navbar-top .navbar-nav-icons {
            flex-wrap: nowrap !important;
        }

        @media (max-width: 767.98px) {
            .navbar-top {
                padding: 0 0.75rem !important;
                min-height: 56px !important;
                height: 56px !important;
                gap: 0.35rem !important;
                flex-wrap: nowrap !important;
            }

            .content,
            .main .content,
            [data-layout="container"] .content {
                padding: 0 0.75rem 1.5rem 0.75rem !important;
            }

            .navbar-nav-icons {
                gap: 2px !important;
            }

            .navbar-top .btn-toolbar-icon {
                padding: 4px 5px !important;
                width: 30px !important;
                height: 30px !important;
                font-size: 13px !important;
            }

            .sms-balance-pill {
                padding: 3px 7px !important;
                font-size: 11px !important;
                gap: 4px !important;
            }
        }

        /* Layout Shell: Professional docked sidebar with zero overlap */
        .container,
        .container-fluid,
        [data-layout="container"] {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .content,
        .main .content,
        [data-layout="container"] .content {
            padding: 0 1.25rem 1.5rem 1.25rem !important;
            background-color: var(--bg-page) !important;
            flex: 1 1 0% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
        }

        @media (min-width: 1200px) {
            .navbar-vertical {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                width: 16rem !important;
                min-width: 16rem !important;
                max-width: 16rem !important;
                z-index: 1025 !important;
                background: var(--surface, #ffffff) !important;
                border-right: 1px solid var(--border-ring) !important;
                box-shadow: 1px 0 0 rgba(0, 0, 0, 0.04), 4px 0 20px rgba(15, 23, 42, 0.03) !important;
            }

            html.dark .navbar-vertical,
            [data-bs-theme="dark"] .navbar-vertical {
                background: var(--surface, #171b2e) !important;
                border-right: 1px solid var(--border-ring, rgba(51, 65, 85, 0.6)) !important;
                box-shadow: 1px 0 0 rgba(255, 255, 255, 0.06), 6px 0 28px rgba(0, 0, 0, 0.4) !important;
            }

            .content,
            .main .content,
            [data-layout="container"] .content,
            .navbar-vertical ~ .content {
                margin-left: 16rem !important;
                width: calc(100% - 16rem) !important;
                max-width: calc(100% - 16rem) !important;
            }

            .navbar-vertical-collapsed .navbar-vertical {
                width: 72px !important;
                min-width: 72px !important;
                max-width: 72px !important;
            }

            .navbar-vertical-collapsed .content,
            .navbar-vertical-collapsed .main .content,
            .navbar-vertical-collapsed [data-layout="container"] .content,
            .navbar-vertical-collapsed .navbar-vertical ~ .content {
                margin-left: 72px !important;
                width: calc(100% - 72px) !important;
                max-width: calc(100% - 72px) !important;
            }
        }

        @media (max-width: 1199.98px) {
            /* Desktop sidebar is strictly hidden below 1200px so it never leaks layout width */
            .navbar-vertical:not(.mobile-sidebar-drawer) {
                display: none !important;
                visibility: hidden !important;
                width: 0 !important;
                min-width: 0 !important;
                max-width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                position: fixed !important;
                left: -9999px !important;
                pointer-events: none !important;
            }

            .content,
            .main .content,
            [data-layout="container"] .content {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100vw !important;
                overflow-x: hidden !important;
            }
        }

        /* Mobile Drawer & Backdrop (< 1200px) */
        .mobile-drawer-backdrop {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(15, 23, 42, 0.45) !important;
            z-index: 10500 !important;
            opacity: 0;
            display: none;
            transition: opacity 0.25s ease-in-out;
            pointer-events: none;
        }

        .mobile-drawer-backdrop.is-active {
            display: block !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        .mobile-sidebar-drawer {
            position: fixed !important;
            top: 0 !important;
            bottom: 0 !important;
            left: 0 !important;
            width: 18rem !important; /* w-72 */
            max-width: 85vw !important; /* max-w-[85vw] */
            height: 100vh !important;
            background-color: #ffffff !important;
            z-index: 10505 !important;
            display: none;
            flex-direction: column !important;
            transform: translateX(-100%) !important;
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s ease !important;
            box-shadow: none !important;
            border-right: 1px solid #e2e8f0 !important;
            visibility: visible !important;
            pointer-events: auto !important;
        }

        html.dark .mobile-sidebar-drawer,
        html.dark .mobile-sidebar-drawer .mobile-drawer-header,
        html.dark .mobile-sidebar-drawer .mobile-drawer-body {
            background-color: #1e293b !important;
            border-right-color: #334155 !important;
        }

        .mobile-sidebar-drawer.is-open {
            display: flex !important;
            transform: translateX(0) !important;
            visibility: visible !important;
            pointer-events: auto !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.15) !important;
        }

        @media (min-width: 1200px) {
            .mobile-drawer-backdrop,
            .mobile-sidebar-drawer {
                display: none !important;
                pointer-events: none !important;
                visibility: hidden !important;
            }
        }

        /* SMS Balance Pill in Top Bar */
        .sms-balance-pill {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            height: 36px !important;
            padding: 0 12px !important;
            border-radius: 9999px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            line-height: 1 !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            transition: all 0.15s ease !important;
            background: #ffffff !important;
            color: #475569 !important;
            border: 1px solid #e2e8f0 !important;
            cursor: pointer !important;
        }

        .sms-balance-pill:hover {
            background: #f8fafc !important;
            color: #1e293b !important;
            border-color: #cbd5e1 !important;
        }

        .sms-balance-pill.pill-ok {
            background: #f0fdf4 !important;
            color: #15803d !important;
            border-color: #bbf7d0 !important;
        }

        .sms-balance-pill.pill-low {
            background: #fffbeb !important;
            color: #b45309 !important;
            border-color: #fde68a !important;
        }

        .sms-balance-pill.pill-empty {
            background: #f8fafc !important;
            color: #64748b !important;
            border-color: #e2e8f0 !important;
        }

        html.dark .sms-balance-pill {
            background: #1e293b !important;
            color: #94a3b8 !important;
            border-color: #334155 !important;
        }
        html.dark .sms-balance-pill.pill-ok {
            background: rgba(21, 128, 61, 0.15) !important;
            color: #4ade80 !important;
            border-color: rgba(74, 222, 128, 0.3) !important;
        }

        /* Toolbar Icon Buttons */
        .btn-toolbar-icon {
            width: 34px !important;
            height: 34px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 0.5rem !important;
            color: var(--text-secondary) !important;
            background: transparent !important;
            transition: all 0.15s ease !important;
            text-decoration: none !important;
            padding: 0 !important;
            border: none !important;
        }
        .btn-toolbar-icon:hover {
            background: #f1f5f9 !important;
            color: var(--text-primary) !important;
        }
        .btn-toolbar-icon:focus {
            box-shadow: none !important;
        }
        .dropdown-caret-none::after {
            display: none !important;
        }

        html.dark .btn-toolbar-icon:hover {
            background: rgba(255, 255, 255, 0.06) !important;
            color: #f1f5f9 !important;
        }

        /* Cards Design System: Flat Surface, Rounded-2xl, Thin Ring */
        .card, .ui-card {
            background-color: var(--surface) !important;
            border: 1px solid var(--border-ring) !important;
            border-radius: 1rem !important;
            box-shadow: none !important;
        }

        .card-header, .ui-card-header {
            background-color: transparent !important;
            border-bottom: 1px solid var(--border-ring) !important;
            padding: 1rem 1.25rem !important;
        }

        .card-body {
            padding: 1.25rem !important;
        }

        /* Soft Tint Status Badges */
        .badge, .ui-badge {
            border-radius: 9999px !important;
            padding: 0.15rem 0.55rem !important;
            font-size: 11px !important;
            font-weight: 500 !important;
            line-height: 1.4 !important;
        }

        .badge.bg-success, .badge.bg-success-subtle, .badge-subtle-success, .status-active, .status-paid, .ui-badge-success {
            background-color: #ecfdf5 !important;
            color: #047857 !important;
            border: 1px solid rgba(167, 243, 208, 0.6) !important;
        }

        .badge.bg-warning, .badge.bg-warning-subtle, .badge-subtle-warning, .status-pending, .ui-badge-warning {
            background-color: #fffbeb !important;
            color: #b45309 !important;
            border: 1px solid rgba(253, 230, 138, 0.6) !important;
        }

        .badge.bg-danger, .badge.bg-danger-subtle, .badge-subtle-danger, .status-expired, .status-overdue, .ui-badge-danger {
            background-color: #fff1f2 !important;
            color: #be123c !important;
            border: 1px solid rgba(254, 205, 211, 0.6) !important;
        }

        .badge.bg-info, .badge.bg-info-subtle, .badge.bg-primary, .badge.bg-primary-subtle, .ui-badge-info {
            background-color: #f0f9ff !important;
            color: #0369a1 !important;
            border: 1px solid rgba(186, 230, 253, 0.6) !important;
        }

        .badge.bg-secondary, .badge.bg-secondary-subtle, .badge.bg-light, .ui-badge-neutral {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid rgba(226, 232, 240, 0.7) !important;
        }

        /* Underline Tabs */
        .nav-tabs, .ui-tabs {
            border-bottom: 1px solid var(--border-ring) !important;
            gap: 0.5rem;
        }

        .nav-tabs .nav-link, .nav-pills .nav-link, [role="tab"] {
            background: transparent !important;
            color: var(--text-secondary) !important;
            border: none !important;
            border-bottom: 2px solid transparent !important;
            border-radius: 0 !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            padding: 0.6rem 0.75rem !important;
            margin-bottom: -1px !important;
            transition: color 0.15s ease, border-color 0.15s ease !important;
        }

        .nav-tabs .nav-link:hover, .nav-pills .nav-link:hover, [role="tab"]:hover {
            color: var(--text-primary) !important;
        }

        .nav-tabs .nav-link.active, .nav-pills .nav-link.active, [role="tab"].active, [role="tab"][aria-selected="true"] {
            background-color: transparent !important;
            color: var(--accent-hover) !important;
            border: none !important;
            border-bottom: 2px solid var(--accent) !important;
            border-radius: 0 !important;
            font-weight: 600 !important;
        }

        /* Tables: Clean Header & Subtle Dividers */
        .table, .hs-table {
            border-collapse: collapse !important;
            width: 100%;
        }

        .table thead th, .hs-table thead th {
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            text-transform: none !important;
            letter-spacing: normal !important;
            color: var(--text-secondary) !important;
            background-color: #f8fafc !important;
            border: none !important;
            border-bottom: 1px solid var(--border-ring) !important;
            padding: 0.65rem 1rem !important;
        }

        html.dark .table thead th, html.dark .hs-table thead th {
            background-color: rgba(255, 255, 255, 0.03) !important;
        }

        .table tbody td, .hs-table tbody td {
            padding: 0.75rem 1rem !important;
            font-size: 0.875rem !important;
            color: var(--text-primary) !important;
            border: none !important;
            border-bottom: 1px solid rgba(241, 245, 249, 0.9) !important;
            vertical-align: middle !important;
        }

        html.dark .table tbody td, html.dark .hs-table tbody td {
            border-bottom-color: rgba(51, 65, 85, 0.4) !important;
        }

        .table tbody tr:hover, .hs-table tbody tr:hover {
            background-color: #f8fafc !important;
        }

        html.dark .table tbody tr:hover, html.dark .hs-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.02) !important;
        }

        .table th.text-end, .table td.text-end, .table td:has(.currency-amount) {
            text-align: right !important;
        }

        /* Buttons: Rounded-lg, Clean Tokens */
        .btn, .ui-btn {
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            padding: 0.45rem 0.9rem !important;
            border-radius: 0.5rem !important;
            box-shadow: none !important;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease !important;
        }

        .btn-sm, .table .btn-sm, .btn-action-pill {
            border-radius: 0.5rem !important;
            padding: 0.3rem 0.65rem !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
        }

        .btn-primary, .btn-primary:focus, .btn-success, .btn-success:focus, .ui-btn-primary {
            background-color: var(--accent) !important;
            border-color: var(--accent) !important;
            color: #ffffff !important;
        }

        .btn-primary:hover, .btn-success:hover, .ui-btn-primary:hover {
            background-color: var(--accent-hover) !important;
            border-color: var(--accent-hover) !important;
            color: #ffffff !important;
        }

        .btn-secondary, .btn-light, .btn-outline-secondary, .btn-outline-primary, .ui-btn-secondary {
            background-color: var(--surface) !important;
            border: 1px solid #e2e8f0 !important;
            color: var(--text-primary) !important;
        }

        html.dark .btn-secondary, html.dark .btn-light, html.dark .btn-outline-secondary, html.dark .btn-outline-primary {
            border-color: rgba(51, 65, 85, 0.8) !important;
        }

        .btn-secondary:hover, .btn-light:hover, .btn-outline-secondary:hover, .btn-outline-primary:hover, .ui-btn-secondary:hover {
            background-color: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: var(--text-primary) !important;
        }

        .btn-danger, .btn-outline-danger:hover, .ui-btn-danger {
            background-color: #e11d48 !important;
            border-color: #e11d48 !important;
            color: #ffffff !important;
        }

        .ui-btn-ghost {
            background: transparent !important;
            border: none !important;
            color: var(--text-secondary) !important;
        }
        .ui-btn-ghost:hover {
            background: #f1f5f9 !important;
            color: var(--text-primary) !important;
        }

        /* Inputs & Selects */
        .form-control, .form-select {
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.5rem !important;
            padding: 0.45rem 0.75rem !important;
            font-size: 0.875rem !important;
            color: var(--text-primary) !important;
            background-color: var(--surface) !important;
            box-shadow: none !important;
        }

        html.dark .form-control, html.dark .form-select {
            border-color: rgba(51, 65, 85, 0.8) !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: #fb923c !important;
            box-shadow: 0 0 0 2px rgba(251, 146, 60, 0.25) !important;
            outline: none !important;
        }

        .form-label, label, .ui-form-label {
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            color: #475569 !important;
            margin-bottom: 0.35rem !important;
        }

        html.dark .form-label, html.dark label, html.dark .ui-form-label {
            color: #94a3b8 !important;
        }

        .ui-form-error {
            font-size: 0.75rem !important;
            color: #e11d48 !important;
        }

        .ui-form-help {
            font-size: 0.75rem !important;
            color: var(--text-secondary) !important;
        }

        /* Modals */
        .modal-content {
            border-radius: 1rem !important;
            border: 1px solid var(--border-ring) !important;
            background-color: var(--surface) !important;
            box-shadow: none !important;
        }

        /* Empty State */
        .ui-empty-icon {
            width: 48px;
            height: 48px;
            border-radius: 9999px;
            background-color: #f1f5f9;
            color: #94a3b8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        /* Remove heavy shadows utility-wide */
        .shadow, .shadow-sm, .shadow-lg, .shadow-xl {
            box-shadow: none !important;
        }

        /* Skeleton loading animation */
        @keyframes skeleton-loading {
            0% { background-color: #f1f5f9; }
            50% { background-color: #f8fafc; }
            100% { background-color: #f1f5f9; }
        }
        .skeleton {
            animation: skeleton-loading 1.4s ease-in-out infinite;
            border-radius: 0.5rem;
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
        <div class="container-fluid px-0" data-layout="container">
            <script>
                try {
                    localStorage.setItem('isFluid', 'true');
                    var storedPos = localStorage.getItem('navbarPosition');
                    if (!storedPos || storedPos === 'combo') {
                        localStorage.setItem('navbarPosition', 'vertical');
                    }
                } catch (e) {}
            </script>
            @include('layouts.partials.sidenav')
            <div class="content">
                @include('layouts.partials.mobile-button-nav')


                @include('layouts.partials.topnav')

                <script>
                    var navVerticalEl = document.querySelector('.navbar-vertical');
                    var navTopVerticalEl = document.querySelector('.content .navbar-top');
                    var navTopEl = document.querySelector('[data-layout] .navbar-top:not([data-double-top-nav])');
                    var navDoubleTopEl = document.querySelector('[data-double-top-nav]');
                    var navTopComboEl = document.querySelector('.content [data-navbar-top="combo"]');

                    if (navTopComboEl) navTopComboEl.remove();
                    if (navTopEl) navTopEl.remove();
                    if (navDoubleTopEl) navDoubleTopEl.remove();

                    if (navVerticalEl) navVerticalEl.removeAttribute('style');
                    if (navTopVerticalEl) navTopVerticalEl.removeAttribute('style');
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

        window.openMobileDrawer = function() {
            if (window.innerWidth >= 1200) return;
            const drawer = document.getElementById('mobileSidebarDrawer');
            const backdrop = document.getElementById('mobileDrawerBackdrop');
            if (!drawer || !backdrop) return;
            backdrop.style.display = 'block';
            drawer.style.display = 'flex';
            void drawer.offsetHeight; // force reflow
            backdrop.classList.add('is-active');
            drawer.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        };

        window.closeMobileDrawer = function() {
            const drawer = document.getElementById('mobileSidebarDrawer');
            const backdrop = document.getElementById('mobileDrawerBackdrop');
            if (backdrop) backdrop.classList.remove('is-active');
            if (drawer) drawer.classList.remove('is-open');
            document.body.style.overflow = '';
            setTimeout(function () {
                if (drawer && !drawer.classList.contains('is-open')) {
                    drawer.style.display = 'none';
                }
                if (backdrop && !backdrop.classList.contains('is-active')) {
                    backdrop.style.display = 'none';
                }
            }, 260);
        };

        window.toggleMobileDrawer = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const drawer = document.getElementById('mobileSidebarDrawer');
            if (drawer && drawer.classList.contains('is-open')) {
                window.closeMobileDrawer();
            } else {
                window.openMobileDrawer();
            }
        };

        function initMobileDrawer() {
            const drawer = document.getElementById('mobileSidebarDrawer');
            const backdrop = document.getElementById('mobileDrawerBackdrop');
            if (!drawer || !backdrop) return;

            // Bind direct clicks on known hamburger buttons
            document.querySelectorAll('#mobileMenuToggleBtn, #mobileMenuToggleBtnCombo, [data-bs-target="#navbarVerticalCollapse"], .navbar-toggler-humburger-icon, [data-mobile-menu-toggle]').forEach(function (btn) {
                btn.onclick = function (e) {
                    window.toggleMobileDrawer(e);
                };
            });

            // Close button inside drawer
            const closeBtn = document.getElementById('mobileDrawerCloseBtn') || document.getElementById('closeMobileSidebarBtn');
            if (closeBtn) {
                closeBtn.onclick = function (e) {
                    e.preventDefault();
                    window.closeMobileDrawer();
                };
            }

            // Tapping backdrop closes drawer
            backdrop.onclick = function (e) {
                e.preventDefault();
                window.closeMobileDrawer();
            };

            // Pressing Escape closes drawer
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
                    window.closeMobileDrawer();
                }
            });

            // Tapping any nav-link inside drawer closes it
            drawer.querySelectorAll('.nav-link').forEach(function (link) {
                link.onclick = function () {
                    window.closeMobileDrawer();
                };
            });
        }

        // Global Event Delegation fallback: immune to Livewire DOM refreshes & subpage switches
        if (!window._mobileDrawerDelegationBound) {
            window._mobileDrawerDelegationBound = true;
            document.addEventListener('click', function(e) {
                const toggle = e.target.closest('#mobileMenuToggleBtn, #mobileMenuToggleBtnCombo, .navbar-toggler-humburger-icon, [data-mobile-menu-toggle]');
                if (toggle) {
                    window.toggleMobileDrawer(e);
                    return;
                }
                const close = e.target.closest('#mobileDrawerCloseBtn, #closeMobileSidebarBtn, #mobileDrawerBackdrop');
                if (close) {
                    e.preventDefault();
                    window.closeMobileDrawer();
                    return;
                }
                const navLink = e.target.closest('#mobileSidebarDrawer .nav-link');
                if (navLink) {
                    window.closeMobileDrawer();
                }
            });
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
            initMobileDrawer();
        });


        // Run on Livewire navigate
        document.addEventListener('livewire:navigated', () => {
            handleNavbarHover();
            initMobileDrawer();
        });

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
            // Graceful handling of expired sessions in Livewire (never show 419 Page Expired modal)
            if (typeof Livewire !== 'undefined' && Livewire.hook) {
                Livewire.hook('request', ({ fail }) => {
                    fail(({ status, preventDefault }) => {
                        if (status === 419) {
                            preventDefault();
                            window.location.href = '/login?expired=1';
                        }
                    });
                });
            }
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

    <x-floating-support />

</body>
</html>
