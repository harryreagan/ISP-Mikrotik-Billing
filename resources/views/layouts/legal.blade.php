<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Legal & Compliance' }} – HSmart ISP</title>

    <!-- Preconnect & Preload Jost Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}" type="image/x-icon">

    <style>
        :root {
            --font-sans: 'Jost', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --bg-page: #F8FAFC;
            --card-bg: #FFFFFF;
            --card-border: #E2E8F0;
            --ink-primary: #0F172A;
            --ink-secondary: #334155;
            --ink-muted: #64748B;
            --brand-orange: #F57C00;
            --brand-orange-hover: #E06D00;
            --brand-blue: #1E40AF;
            --brand-purple: #6D3FE0;
            --dark-navy: #0B1120;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg-page);
            color: var(--ink-secondary);
            font-family: var(--font-sans);
            font-size: 15px;
            line-height: 1.75;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        /* ── Header Navbar ── */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--card-border);
        }

        .header-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-link {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink-secondary);
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.15s ease;
        }

        .nav-link:hover {
            color: var(--brand-orange);
            background: rgba(245, 124, 0, 0.06);
        }

        .btn-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            background: var(--brand-orange);
            color: #FFFFFF !important;
            transition: all 0.18s ease;
            box-shadow: 0 4px 12px rgba(245, 124, 0, 0.25);
        }

        .btn-cta:hover {
            background: var(--brand-orange-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(245, 124, 0, 0.35);
        }

        /* ── Hero Banner ── */
        .legal-hero {
            background: linear-gradient(180deg, #FFFFFF 0%, #F1F5F9 100%);
            border-bottom: 1px solid var(--card-border);
            padding: 48px 24px 32px;
            text-align: center;
        }

        .legal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(245, 124, 0, 0.12);
            color: var(--brand-orange);
            margin-bottom: 14px;
        }

        .legal-hero h1 {
            font-size: 34px;
            font-weight: 800;
            color: var(--ink-primary);
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .legal-hero p {
            font-size: 14px;
            color: var(--ink-muted);
            max-width: 600px;
            margin: 0 auto;
        }

        /* ── Tab Navigation ── */
        .legal-tabs {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 28px;
            flex-wrap: wrap;
        }

        .legal-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid var(--card-border);
            background: #FFFFFF;
            color: var(--ink-secondary);
            transition: all 0.15s ease;
        }

        .legal-tab:hover {
            border-color: #CBD5E1;
            background: #F8FAFC;
        }

        .legal-tab.active {
            background: var(--ink-primary);
            color: #FFFFFF;
            border-color: var(--ink-primary);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }

        /* ── Main Content Container ── */
        .legal-main {
            max-width: 920px;
            margin: 36px auto 60px;
            padding: 0 20px;
        }

        .legal-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 44px 48px;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.04);
        }

        @media (max-width: 640px) {
            .legal-card {
                padding: 24px 20px;
                border-radius: 16px;
            }
            .legal-hero h1 {
                font-size: 26px;
            }
        }

        /* ── Document Prose Typography ── */
        .prose h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--ink-primary);
            margin-bottom: 20px;
            border-bottom: 2px solid #F1F5F9;
            padding-bottom: 12px;
        }

        .prose h2, .prose h3 {
            font-size: 19px;
            font-weight: 700;
            color: var(--ink-primary);
            margin-top: 32px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .prose h2::before, .prose h3::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 18px;
            background: var(--brand-orange);
            border-radius: 2px;
        }

        .prose p {
            margin-bottom: 16px;
            color: var(--ink-secondary);
            font-size: 15px;
            line-height: 1.8;
        }

        .prose ul, .prose ol {
            margin: 16px 0 20px 24px;
            color: var(--ink-secondary);
        }

        .prose li {
            margin-bottom: 8px;
            line-height: 1.7;
        }

        .prose hr {
            border: none;
            border-top: 1px solid #E2E8F0;
            margin: 32px 0;
        }

        .prose strong {
            color: var(--ink-primary);
            font-weight: 700;
        }

        .prose a {
            color: #2563EB;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .prose a:hover {
            color: var(--brand-orange);
        }

        .prose table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }

        .prose th, .prose td {
            padding: 10px 14px;
            text-align: left;
            border: 1px solid #E2E8F0;
        }

        .prose th {
            background: #F8FAFC;
            font-weight: 700;
            color: var(--ink-primary);
        }

        .prose code {
            background: #F1F5F9;
            color: #0F172A;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 13px;
            font-family: monospace;
        }

        /* ── Site Footer ── */
        .site-footer {
            background: var(--dark-navy);
            color: #94A3B8;
            padding: 48px 24px 32px;
            font-size: 13px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .footer-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .footer-links {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .footer-link {
            color: #94A3B8;
            transition: color 0.15s ease;
        }

        .footer-link:hover {
            color: #FFFFFF;
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <header class="site-header">
        <div class="header-inner">
            <x-hsmart-logo height="36px" />

            <nav class="nav-links">
                <a href="{{ url('/') }}" class="nav-link">Home</a>
                <a href="{{ url('/#pricing') }}" class="nav-link">Pricing</a>
                <a href="{{ route('login') }}" class="nav-link">Sign In</a>
                <a href="{{ route('register') }}" class="btn-cta">Start Free Trial →</a>
            </nav>
        </div>
    </header>

    <!-- Hero Header -->
    <section class="legal-hero">
        <div class="legal-badge">⚖️ Official Platform Policies</div>
        <h1>{{ $heading ?? $title }}</h1>
        <p>{{ $subtitle ?? 'Please read these guidelines and terms carefully before operating your network on HSmart ISP.' }}</p>

        <!-- Tab Switcher -->
        <div class="legal-tabs">
            <a href="{{ url('/terms-of-service') }}" class="legal-tab {{ request()->is('terms*') ? 'active' : '' }}">
                📜 Terms of Service
            </a>
            <a href="{{ url('/privacy-policy') }}" class="legal-tab {{ request()->is('privacy*') ? 'active' : '' }}">
                🔒 Privacy Policy
            </a>
            <a href="{{ url('/cookie-policy') }}" class="legal-tab {{ request()->is('cookie*') ? 'active' : '' }}">
                🍪 Cookie Policy
            </a>
        </div>
    </section>

    <!-- Main Content Container -->
    <main class="legal-main">
        <article class="legal-card prose">
            @if(isset($slot) && !empty((string) $slot))
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </article>
    </main>

    <!-- Modern Dark Footer -->
    <footer class="site-footer">
        <div class="footer-inner">
            <div style="display: flex; align-items: center; gap: 16px;">
                <x-hsmart-logo color="light" height="32px" />
                <span style="font-size: 12px; color: #64748B;">&copy; {{ date('Y') }} Harry Smart Technologies Ltd. All rights reserved.</span>
            </div>

            <div class="footer-links">
                <a href="{{ url('/terms-of-service') }}" class="footer-link">Terms of Service</a>
                <a href="{{ url('/privacy-policy') }}" class="footer-link">Privacy Policy</a>
                <a href="{{ url('/cookie-policy') }}" class="footer-link">Cookie Policy</a>
                <a href="https://wa.me/254743606108" target="_blank" class="footer-link" style="color: #22C55E;">💬 WhatsApp Support</a>
            </div>
        </div>
    </footer>

</body>
</html>
