@php
    $locale = app()->getLocale();
    if (!in_array($locale, ['en', 'sw', 'fr'])) {
        $locale = 'en';
    }

    $landingConfig = config('landing');
    $brandName = $landingConfig['brand_name'] ?? 'HSmart ISP';
    $whatsappNumber = $landingConfig['whatsapp_number'] ?? '254743606108';
    $whatsappPlaceholder = $landingConfig['whatsapp_display_placeholder'] ?? '[YOUR NUMBER]';
    $supportEmail = $landingConfig['support_email'] ?? 'support@hsmart.co.ke';

    // Currency Detection via Cloudflare CF-IPCountry, Accept-Language, or Cookie (Default: KES)
    $detectedCurrency = \App\Services\Currency\CurrencyDetector::detect(request());
    $currencies = \App\Services\Currency\CurrencyDetector::getRates();
    // Guarantee KES as default display for HSmart ISP unless explicitly overridden
    $defaultCurrency = (request()->cookie('hsmart_currency') || request()->cookie('hsmart_landing_currency') || request()->has('currency')) ? $detectedCurrency : 'KES';

    $plansBaseKes = $landingConfig['plans_base_kes'] ?? ['starter' => 400, 'business' => 1000, 'enterprise' => null];
    $previewBaseKes = $landingConfig['preview_base_kes'] ?? [
        'pppoe_today' => 12400,
        'hotspot_today' => 6850,
        'total_revenue' => 398835,
        'pending_due' => 34568,
        'toast_amount' => 50,
    ];

    $loginUrl = Route::has('login') ? route('login') : url('/login');
    $registerUrl = Route::has('register') ? route('register') : url('/register');
    $privacyUrl = Route::has('policy.show') ? route('policy.show') : '#privacy';
    $termsUrl = Route::has('terms.show') ? route('terms.show') : '#terms';
    $whatsAppUrl = 'https://wa.me/' . preg_replace('/\D/', '', $whatsappNumber) . '?text=' . urlencode("Hello {$brandName}, I would like to learn more.");

    // Query platform subscription plans from database
    $pricingPlans = [];
    $landingFaqs = null;

    // 1. Check main_site_data first (where SuperAdmin live edits are stored)
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('main_site_data')) {
            $rawPlans = \Illuminate\Support\Facades\DB::table('main_site_data')->where('type', 'pricing_plans')->value('value');
            if ($rawPlans) {
                $decodedPlans = json_decode($rawPlans, true);
                if (is_array($decodedPlans) && !empty($decodedPlans)) {
                    $pricingPlans = $decodedPlans;
                }
            }
        }
    } catch (\Throwable $e) {}

    // 2. Next check platform_plans if main_site_data is empty
    if (empty($pricingPlans)) {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('platform_plans')) {
                $pricingPlans = \App\Models\Plan::active()->get()->toArray();
            }
        } catch (\Throwable $e) {}
    }

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('main_site_data')) {
            $rawFaqs = \Illuminate\Support\Facades\DB::table('main_site_data')->where('type', 'landing_faqs')->value('value');
            if ($rawFaqs) {
                $decodedFaqs = json_decode($rawFaqs, true);
                if (is_array($decodedFaqs) && !empty($decodedFaqs)) {
                    $landingFaqs = $decodedFaqs;
                }
            }
        }
    } catch (\Throwable $e) {}

    $allTranslations = [
        'en' => trans('landing', [], 'en'),
        'sw' => trans('landing', [], 'sw'),
        'fr' => trans('landing', [], 'fr'),
    ];
    $t = $allTranslations[$locale] ?? $allTranslations['en'];
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $t['meta']['title'] }}</title>
    <meta name="description" content="{{ $t['meta']['description'] }}">

    <!-- Preconnect & Preload Jost Font for Fast 3G Rendering -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --font-sans: 'Jost', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --bg-page: #FAFAF8;
            --card-bg: #FFFFFF;
            --card-border: #E6E3F5;
            --card-radius: 18px;
            --ink: #111111;
            --text-secondary: #475569;
            --primary-orange: #F57C00;
            --primary-orange-hover: #E06D00;
            --accent-purple: #6D3FE0;
            --accent-purple-soft: #EFEAFE;
            --dark-navy: #0F172A;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            background: var(--bg-page);
            color: var(--ink);
            font-family: var(--font-sans);
            font-size: 16px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            padding-bottom: calc(78px + env(safe-area-inset-bottom, 0px));
        }

        @media (min-width: 768px) {
            body {
                padding-bottom: 0;
            }
        }

        a {
            text-decoration: none;
            color: inherit;
            transition: color 0.18s ease, background-color 0.18s ease, border-color 0.18s ease;
        }

        button, select {
            font-family: var(--font-sans);
        }

        /* Visible accessible focus ring everywhere */
        a:focus-visible,
        button:focus-visible,
        select:focus-visible,
        summary:focus-visible {
            outline: 3px solid var(--accent-purple);
            outline-offset: 2px;
        }

        .w {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
        }

        @media (min-width: 640px) {
            .w {
                padding: 0 24px;
            }
        }

        @media (min-width: 1024px) {
            .w {
                padding: 0 32px;
            }
        }

        /* Buttons: min 44px tap target, high contrast (#111111 on #F57C00 orange = 7.4:1 contrast) */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 48px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.01em;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
            text-align: center;
            line-height: 1.2;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
        }

        .btn-orange {
            background: var(--primary-orange);
            color: #111111;
        }

        .btn-orange:hover {
            background: var(--primary-orange-hover);
            color: #111111;
        }

        .btn-outline {
            background: transparent;
            border-color: #111111;
            color: #111111;
        }

        .btn-outline:hover {
            background: #111111;
            color: #FFFFFF;
        }

        .btn-outline-light {
            background: transparent;
            border-color: #FFFFFF;
            color: #FFFFFF;
        }

        .btn-outline-light:hover {
            background: #FFFFFF;
            color: #111111;
        }

        .btn-login-portal {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 42px;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #0F172A;
            background: #F1F5F9;
            border: 1.5px solid #CBD5E1;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-login-portal:hover {
            background: #0F172A;
            color: #FFFFFF;
            border-color: #0F172A;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.18);
            transform: translateY(-1px);
        }

        .btn-login-portal svg {
            stroke: currentColor;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .btn-login-portal:hover svg {
            transform: translateX(2px);
        }

        /* ─── HEADER (56px mobile bar, 44x44 hamburger, horizontal desktop) ─── */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(250, 250, 248, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(230, 227, 245, 0.8);
        }

        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 56px;
            gap: 12px;
        }

        @media (min-width: 768px) {
            .header-bar {
                min-height: 68px;
            }
        }

        .brand-link {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
        }

        .desktop-nav {
            display: none;
            align-items: center;
            gap: 24px;
            font-size: 16px;
            font-weight: 600;
        }

        @media (min-width: 900px) {
            .desktop-nav {
                display: flex;
            }
        }

        .desktop-nav a:not(.btn) {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0 4px;
            color: var(--ink);
        }

        .desktop-nav a:not(.btn):hover {
            color: var(--accent-purple);
        }

        .lang-switcher {
            display: inline-flex;
            align-items: center;
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 2px;
            gap: 2px;
        }

        .lang-btn {
            min-width: 44px;
            min-height: 40px;
            padding: 4px 8px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
        }

        .lang-btn.active {
            background: var(--accent-purple-soft);
            color: #4C2BD6;
        }

        .hamburger-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: #FFFFFF;
            color: var(--ink);
            cursor: pointer;
            padding: 0;
        }

        @media (min-width: 900px) {
            .hamburger-btn {
                display: none;
            }
        }

        .mobile-menu {
            display: none;
            border-top: 1px solid var(--card-border);
            background: #FFFFFF;
            padding: 16px;
        }

        .mobile-menu.is-open {
            display: block;
        }

        @media (min-width: 900px) {
            .mobile-menu {
                display: none !important;
            }
        }

        .mobile-menu-links {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .mobile-menu-links a:not(.btn) {
            display: flex;
            align-items: center;
            min-height: 44px;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
        }

        .mobile-menu-links a:not(.btn):hover {
            background: #F8FAFC;
            color: var(--accent-purple);
        }

        .mobile-menu-Controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 12px 16px;
            margin-top: 4px;
            border-top: 1px solid #F1F5F9;
            gap: 12px;
        }

        /* ─── HERO SECTION ─── */
        .hero {
            padding: 28px 0 48px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
            align-items: center;
        }

        @media (min-width: 960px) {
            .hero {
                padding: 56px 0 76px;
                grid-template-columns: 1.05fr 0.95fr;
                gap: 48px;
            }
        }

        .hero-copy {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .hero-badge {
            display: inline-block;
            align-self: flex-start;
            background: var(--accent-purple-soft);
            color: #4C2BD6;
            padding: 7px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(32px, 5vw, 56px);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            text-wrap: balance;
        }

        .hero h1 .accent {
            color: var(--accent-purple);
            display: block;
        }

        .hero-lead {
            margin: 0;
            font-size: 17px;
            line-height: 1.6;
            color: var(--text-secondary);
            max-width: 560px;
        }

        .hero-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .hero-actions .btn {
            width: 100%;
            min-height: 48px;
        }

        @media (min-width: 640px) {
            .hero-actions {
                flex-direction: row;
                width: auto;
            }
            .hero-actions .btn {
                width: auto;
            }
        }

        .hero-ticks {
            list-style: none;
            margin: 4px 0 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #1E293B;
        }

        @media (min-width: 640px) {
            .hero-ticks {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 12px 22px;
            }
        }

        .hero-ticks li {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tick-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #DCFCE7;
            color: #15803D;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* ─── DASHBOARD PREVIEW CARD ─── */
        .dash-wrap {
            position: relative;
            width: 100%;
        }

        .dash {
            border: 1px solid var(--card-border);
            border-radius: 22px;
            background: #FFFFFF;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        @media (min-width: 640px) {
            .dash {
                padding: 20px;
                gap: 14px;
            }
        }

        .dots {
            display: flex;
            gap: 6px;
        }

        .dots i {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #E2E8F0;
            display: block;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        @media (min-width: 640px) {
            .stat-grid {
                gap: 12px;
            }
        }

        .tile {
            border-radius: 14px;
            padding: 12px;
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        @media (min-width: 640px) {
            .tile {
                padding: 14px;
            }
        }

        .tile-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
            opacity: 0.92;
            text-transform: uppercase;
        }

        .tile-val {
            font-size: clamp(18px, 3.8vw, 24px);
            font-weight: 800;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tile-val small {
            font-size: 13px;
            opacity: 0.85;
            font-weight: 600;
        }

        .tile-sub {
            font-size: 12px;
            opacity: 0.9;
        }

        .g1 { background: linear-gradient(135deg, #7C3AED, #5B34E6); }
        .g2 { background: linear-gradient(135deg, #0284C7, #1D4ED8); }
        .g3 { background: linear-gradient(135deg, #D97706, #B45309); }
        .g4 { background: linear-gradient(135deg, #059669, #047857); }
        .g5 { background: #0F172A; }
        .g6 { background: linear-gradient(135deg, #DB2777, #BE185D); }

        .rt {
            background: var(--dark-navy);
            color: #FFFFFF;
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rt-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 14px;
            gap: 8px;
        }

        .rt-badge {
            font-size: 11px;
            font-weight: 700;
            background: #10B981;
            color: #052E16;
            padding: 3px 10px;
            border-radius: 999px;
        }

        .rt-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #CBD5E1;
        }

        .pb {
            height: 6px;
            border-radius: 3px;
            background: #1E293B;
            overflow: hidden;
            margin-top: 5px;
        }

        .pb i {
            display: block;
            height: 100%;
            background: #10B981;
        }

        .toast-card {
            background: var(--dark-navy);
            color: #FFFFFF;
            border-radius: 14px;
            padding: 12px 16px;
            display: flex;
            gap: 12px;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        @media (min-width: 960px) {
            .toast-card {
                position: absolute;
                left: -20px;
                bottom: -18px;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.25);
            }
        }

        .toast-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--primary-orange);
            color: #111111;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .toast-card b {
            display: block;
            font-size: 14px;
            line-height: 1.3;
        }

        .toast-card span {
            font-size: 12px;
            color: #CBD5E1;
        }

        /* ─── SECTIONS & HEADINGS ─── */
        .sec {
            padding: 56px 0;
        }

        @media (min-width: 768px) {
            .sec {
                padding: 84px 0;
            }
        }

        .sec-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 40px;
        }

        .sec-head h2 {
            margin: 0 0 12px;
            font-size: clamp(26px, 3.6vw, 42px);
            line-height: 1.12;
            font-weight: 800;
            letter-spacing: -0.02em;
            text-wrap: balance;
        }

        .sec-head p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 17px;
            line-height: 1.6;
        }

        /* ─── FEATURES GRID (1 col phone, 2 col tablet, 3 col desktop) ─── */
        .features-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 640px) {
            .features-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .features-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 24px;
            }
        }

        .f-card {
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: var(--card-radius);
            padding: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .f-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 32px rgba(109, 63, 224, 0.08);
        }

        .f-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
        }

        .f-card h3 {
            margin: 16px 0 8px;
            font-size: 19px;
            font-weight: 700;
            line-height: 1.3;
        }

        .f-card p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1.6;
        }

        /* ─── PAY YOUR WAY STRIP ─── */
        .pay-strip {
            margin-top: 36px;
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: var(--card-radius);
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        @media (min-width: 768px) {
            .pay-strip {
                flex-direction: row;
                justify-content: space-between;
            }
        }

        .pay-strip-label {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .pay-chips {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .pay-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }

        .pay-chip-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-orange);
        }

        /* ─── STEPS SECTION (Dark Navy #0F172A, Stacked on Phones) ─── */
        .steps-sec {
            background: var(--dark-navy);
            color: #FFFFFF;
        }

        .steps-sec .sec-head p {
            color: #CBD5E1;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 28px;
        }

        @media (min-width: 768px) {
            .steps-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 36px;
            }
        }

        .step-card {
            border-top: 3px solid var(--primary-orange);
            padding-top: 18px;
        }

        .step-num {
            display: inline-block;
            font-size: 38px;
            font-weight: 800;
            line-height: 1;
            color: var(--primary-orange);
            margin-bottom: 8px;
        }

        .step-card h3 {
            margin: 0 0 8px;
            font-size: 20px;
            font-weight: 700;
            color: #FFFFFF;
        }

        .step-card p {
            margin: 0;
            color: #CBD5E1;
            font-size: 16px;
            line-height: 1.6;
        }

        /* ─── PRICING SECTION (Business Plan First on Phones) ─── */
        .currency-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 32px;
        }

        .currency-bar label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .currency-select {
            min-height: 44px;
            padding: 8px 14px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: #FFFFFF;
            color: var(--ink);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            align-items: stretch;
        }

        @media (min-width: 900px) {
            .pricing-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                max-width: 1100px;
                margin: 0 auto;
            }
        }

        .p-card {
            position: relative;
            border: 1px solid var(--card-border);
            border-radius: 22px;
            padding: 28px 24px;
            background: #FFFFFF;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Business plan first on mobile phones */
        .p-card.popular {
            order: -1;
            border: 2px solid var(--accent-purple);
            background: linear-gradient(145deg, #6D3FE0, #4C2BD6);
            color: #FFFFFF;
        }

        @media (min-width: 900px) {
            .p-card.popular {
                order: 0;
                transform: translateY(-6px);
            }
        }

        .popular-badge {
            position: absolute;
            top: 18px;
            right: 18px;
            background: var(--primary-orange);
            color: #111111;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .p-card h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
        }

        .p-price {
            font-size: 34px;
            font-weight: 800;
            line-height: 1.15;
            font-variant-numeric: tabular-nums;
        }

        .p-price small {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .p-card.popular .p-price small {
            color: #E2E8F0;
        }

        .p-features {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-grow: 1;
            font-size: 16px;
            color: #334155;
        }

        .p-card.popular .p-features {
            color: #F1F5F9;
        }

        .p-features li {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pricing-note {
            text-align: center;
            margin: 28px 0 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        /* ─── FAQ ACCORDION ─── */
        .faq-wrap {
            max-width: 820px;
            margin: 0 auto;
        }

        .fq {
            border-bottom: 1px solid #E2E8F0;
        }

        .fq summary {
            min-height: 54px;
            padding: 16px 0;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            list-style: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .fq summary::-webkit-details-marker {
            display: none;
        }

        .fq-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--accent-purple-soft);
            color: var(--accent-purple);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .fq[open] .fq-icon {
            transform: rotate(45deg);
        }

        .fq p {
            margin: 0 0 18px;
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1.65;
        }

        /* ─── CTA BANNER ─── */
        .cta-sec {
            background: linear-gradient(125deg, #6D3FE0, #4C2BD6);
            color: #FFFFFF;
            padding: 56px 0;
        }

        .cta-inner {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
        }

        @media (min-width: 768px) {
            .cta-inner {
                flex-direction: row;
                align-items: center;
            }
        }

        .cta-title {
            margin: 0;
            font-size: clamp(26px, 3.4vw, 38px);
            font-weight: 800;
            line-height: 1.15;
            max-width: 620px;
            text-wrap: balance;
        }

        .cta-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .cta-actions .btn {
            width: 100%;
        }

        @media (min-width: 640px) {
            .cta-actions {
                flex-direction: row;
                width: auto;
            }
            .cta-actions .btn {
                width: auto;
            }
        }

        /* ─── FOOTER ─── */
        .site-footer {
            background: var(--dark-navy);
            color: #CBD5E1;
            padding: 56px 0 32px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
        }

        @media (min-width: 640px) {
            .footer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 960px) {
            .footer-grid {
                grid-template-columns: 1.6fr 1fr 1fr 1.2fr;
            }
        }

        .site-footer h4 {
            margin: 0 0 12px;
            color: #FFFFFF;
            font-size: 16px;
            font-weight: 700;
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .footer-links a {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            font-size: 15px;
            color: #CBD5E1;
        }

        .footer-links a:hover {
            color: var(--primary-orange);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            margin-top: 36px;
            padding-top: 20px;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            justify-content: space-between;
        }

        @media (min-width: 640px) {
            .footer-bottom {
                flex-direction: row;
                align-items: center;
            }
        }

        .footer-legal {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-legal a {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            color: #CBD5E1;
            text-decoration: underline;
        }

        /* ─── STICKY MOBILE BOTTOM BAR (Phones Only) ─── */
        .mobile-sticky-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 60;
            background: rgba(255, 255, 255, 0.98);
            border-top: 1px solid var(--card-border);
            padding: 10px 16px calc(10px + env(safe-area-inset-bottom, 0px));
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.08);
        }

        @media (min-width: 768px) {
            .mobile-sticky-bar {
                display: none;
            }
        }

        .mobile-sticky-bar .btn-trial {
            flex: 1;
            min-height: 48px;
        }

        .whatsapp-icon-btn {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #25D366;
            color: #052E16;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ic {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
    </style>
</head>
<body>

<div class="pg" id="top">
    <!-- ─── HEADER ─── -->
    <header class="site-header">
        <div class="w header-bar">
            <a href="#top" class="brand-link" aria-label="{{ $brandName }}">
                <x-hsmart-logo brandName="HSmart" color="dark" height="34px" />
            </a>

            <!-- Desktop Navigation -->
            <nav class="desktop-nav" aria-label="Primary Navigation">
                <a href="#features" data-i18n="nav.features">{{ $t['nav']['features'] }}</a>
                <a href="#how" data-i18n="nav.how">{{ $t['nav']['how'] }}</a>
                <a href="#pricing" data-i18n="nav.pricing">{{ $t['nav']['pricing'] }}</a>
                <a href="#faq" data-i18n="nav.faq">{{ $t['nav']['faq'] }}</a>

                <div class="lang-switcher" role="group" aria-label="{{ $t['nav']['language'] }}">
                    <button type="button" class="lang-btn {{ $locale === 'en' ? 'active' : '' }}" data-lang-switch="en">EN</button>
                    <button type="button" class="lang-btn {{ $locale === 'sw' ? 'active' : '' }}" data-lang-switch="sw">SW</button>
                    <button type="button" class="lang-btn {{ $locale === 'fr' ? 'active' : '' }}" data-lang-switch="fr">FR</button>
                </div>

                <a href="{{ $loginUrl }}" class="btn-login-portal" data-i18n="nav.login">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    <span>Portal Login</span>
                </a>

                <a href="{{ $registerUrl }}" class="btn btn-orange" style="min-height: 42px; padding: 10px 20px;" data-i18n="nav.trial">
                    {{ $t['nav']['trial'] }}
                </a>
            </nav>

            <!-- 44x44 Mobile Hamburger Button -->
            <button
                type="button"
                id="mobileMenuBtn"
                class="hamburger-btn"
                aria-expanded="false"
                aria-controls="mobileMenuPanel"
                aria-label="{{ $t['nav']['open_menu'] }}"
            >
                <svg class="ic" viewBox="0 0 24 24" aria-hidden="true">
                    <path id="hamburgerIconPath" d="M4 7h16M4 12h16M4 17h16"></path>
                </svg>
            </button>
        </div>

        <!-- Collapsible Mobile Menu -->
        <div id="mobileMenuPanel" class="mobile-menu" role="region" aria-label="Mobile Navigation">
            <nav class="mobile-menu-links">
                <a href="#features" class="mobile-nav-link" data-i18n="nav.features">{{ $t['nav']['features'] }}</a>
                <a href="#how" class="mobile-nav-link" data-i18n="nav.how">{{ $t['nav']['how'] }}</a>
                <a href="#pricing" class="mobile-nav-link" data-i18n="nav.pricing">{{ $t['nav']['pricing'] }}</a>
                <a href="#faq" class="mobile-nav-link" data-i18n="nav.faq">{{ $t['nav']['faq'] }}</a>
            </nav>

            <div class="mobile-menu-Controls">
                <span style="font-size: 14px; font-weight: 600; color: var(--text-secondary);" data-i18n="nav.language">{{ $t['nav']['language'] }}</span>
                <div class="lang-switcher" role="group" aria-label="{{ $t['nav']['language'] }}">
                    <button type="button" class="lang-btn {{ $locale === 'en' ? 'active' : '' }}" data-lang-switch="en">EN</button>
                    <button type="button" class="lang-btn {{ $locale === 'sw' ? 'active' : '' }}" data-lang-switch="sw">SW</button>
                    <button type="button" class="lang-btn {{ $locale === 'fr' ? 'active' : '' }}" data-lang-switch="fr">FR</button>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 14px;">
                <a href="{{ $loginUrl }}" class="btn btn-login-portal" style="width: 100%; justify-content: center;" data-i18n="nav.login">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    <span>Portal Login</span>
                </a>
                <a href="{{ $registerUrl }}" class="btn btn-orange" style="width: 100%;" data-i18n="nav.trial">
                    {{ $t['nav']['trial'] }}
                </a>
            </div>
        </div>
    </header>

    <main>
        <!-- ─── HERO SECTION ─── -->
        <section class="w hero">
            <div class="hero-copy">
                <span class="hero-badge" data-i18n="hero.badge">{{ $t['hero']['badge'] }}</span>
                <h1>
                    <span data-i18n="hero.title_line1">{{ $t['hero']['title_line1'] }}</span>
                    <span class="accent" data-i18n="hero.title_line2">{{ $t['hero']['title_line2'] }}</span>
                </h1>
                <p class="hero-lead" data-i18n="hero.subtitle">{{ $t['hero']['subtitle'] }}</p>

                <div class="hero-actions">
                    <a href="{{ $registerUrl }}" class="btn btn-orange" data-i18n="hero.cta_primary">{{ $t['hero']['cta_primary'] }}</a>
                    <a href="#how" class="btn btn-outline" data-i18n="hero.cta_secondary">{{ $t['hero']['cta_secondary'] }}</a>
                </div>

                <ul class="hero-ticks">
                    <li>
                        <span class="tick-icon" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                        </span>
                        <span data-i18n="hero.tick_1">{{ $t['hero']['tick_1'] }}</span>
                    </li>
                    <li>
                        <span class="tick-icon" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                        </span>
                        <span data-i18n="hero.tick_2">{{ $t['hero']['tick_2'] }}</span>
                    </li>
                    <li>
                        <span class="tick-icon" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                        </span>
                        <span data-i18n="hero.tick_3">{{ $t['hero']['tick_3'] }}</span>
                    </li>
                </ul>
            </div>

            <!-- Dashboard Preview Card (2-column stat grid on mobile, neutral currency constant) -->
            <div class="dash-wrap">
                <div class="dash">
                    <div class="dots" aria-hidden="true"><i></i><i></i><i></i></div>
                    <div class="stat-grid">
                        <div class="tile g1">
                            <span class="tile-label" data-i18n="preview.active_pppoe">{{ $t['preview']['active_pppoe'] }}</span>
                            <div class="tile-val">482<small> / 560</small></div>
                            <span class="tile-sub" data-i18n="preview.new_this_month">{{ $t['preview']['new_this_month'] }}</span>
                        </div>
                        <div class="tile g2">
                            <span class="tile-label" data-i18n="preview.today_pppoe">{{ $t['preview']['today_pppoe'] }}</span>
                            <div class="tile-val" data-price-kes="{{ $previewBaseKes['pppoe_today'] }}">KES {{ number_format($previewBaseKes['pppoe_today']) }}</div>
                            <span class="tile-sub" data-i18n="preview.pppoe_only">{{ $t['preview']['pppoe_only'] }}</span>
                        </div>
                        <div class="tile g3">
                            <span class="tile-label" data-i18n="preview.hotspot_sales">{{ $t['preview']['hotspot_sales'] }}</span>
                            <div class="tile-val" data-price-kes="{{ $previewBaseKes['hotspot_today'] }}">KES {{ number_format($previewBaseKes['hotspot_today']) }}</div>
                            <span class="tile-sub" data-i18n="preview.hotspot_only">{{ $t['preview']['hotspot_only'] }}</span>
                        </div>
                        <div class="tile g4">
                            <span class="tile-label" data-i18n="preview.total_revenue">{{ $t['preview']['total_revenue'] }}</span>
                            <div class="tile-val" data-price-kes="{{ $previewBaseKes['total_revenue'] }}">KES {{ number_format($previewBaseKes['total_revenue']) }}</div>
                            <span class="tile-sub">
                                <span data-i18n="preview.pending_due">{{ $t['preview']['pending_due'] }}</span>
                                <strong data-price-kes="{{ $previewBaseKes['pending_due'] }}">KES {{ number_format($previewBaseKes['pending_due']) }}</strong>
                            </span>
                        </div>
                    </div>

                    <div class="rt">
                        <div class="rt-head">
                            <span data-i18n="preview.router_title">{{ $t['preview']['router_title'] }}</span>
                            <span class="rt-badge" data-i18n="preview.online">{{ $t['preview']['online'] }}</span>
                        </div>
                        <div>
                            <div class="rt-row"><span data-i18n="preview.cpu">{{ $t['preview']['cpu'] }}</span><span>2%</span></div>
                            <div class="pb"><i style="width: 8%;"></i></div>
                        </div>
                        <div>
                            <div class="rt-row"><span data-i18n="preview.memory">{{ $t['preview']['memory'] }}</span><span>6%</span></div>
                            <div class="pb"><i style="width: 22%;"></i></div>
                        </div>
                    </div>

                    <div class="toast-card">
                        <span class="toast-icon" aria-hidden="true">
                            <svg class="ic" viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"></path></svg>
                        </span>
                        <div>
                            <b data-i18n="preview.toast_title">{{ $t['preview']['toast_title'] }}</b>
                            <span>
                                <strong data-price-kes="{{ $previewBaseKes['toast_amount'] }}">$1</strong> ·
                                <span data-i18n="preview.toast_sub">{{ $t['preview']['toast_sub'] }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─── FEATURES SECTION ─── -->
        <section class="w sec" id="features">
            <div class="sec-head">
                <h2 data-i18n="features.title">{{ $t['features']['title'] }}</h2>
                <p data-i18n="features.subtitle">{{ $t['features']['subtitle'] }}</p>
            </div>

            <div class="features-grid">
                <article class="f-card">
                    <div class="f-icon g1" aria-hidden="true">
                        <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2"></rect><path d="M16 12.5h3"></path></svg>
                    </div>
                    <h3 data-i18n="features.c1_title">{{ $t['features']['c1_title'] }}</h3>
                    <p data-i18n="features.c1_desc">{{ $t['features']['c1_desc'] }}</p>
                </article>

                <article class="f-card">
                    <div class="f-icon g2" aria-hidden="true">
                        <svg class="ic" viewBox="0 0 24 24"><path d="M2 9a15 15 0 0 1 20 0M5 13a10 10 0 0 1 14 0M8.5 16.5a5 5 0 0 1 7 0"></path><circle cx="12" cy="20" r="1"></circle></svg>
                    </div>
                    <h3 data-i18n="features.c2_title">{{ $t['features']['c2_title'] }}</h3>
                    <p data-i18n="features.c2_desc">{{ $t['features']['c2_desc'] }}</p>
                </article>

                <article class="f-card">
                    <div class="f-icon g3" aria-hidden="true">
                        <svg class="ic" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3 20a6 6 0 0 1 12 0M17 11a3 3 0 0 0 0-6M21 20a5 5 0 0 0-4-5"></path></svg>
                    </div>
                    <h3 data-i18n="features.c3_title">{{ $t['features']['c3_title'] }}</h3>
                    <p data-i18n="features.c3_desc">{{ $t['features']['c3_desc'] }}</p>
                </article>

                <article class="f-card">
                    <div class="f-icon g4" aria-hidden="true">
                        <svg class="ic" viewBox="0 0 24 24"><path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 21h4"></path></svg>
                    </div>
                    <h3 data-i18n="features.c4_title">{{ $t['features']['c4_title'] }}</h3>
                    <p data-i18n="features.c4_desc">{{ $t['features']['c4_desc'] }}</p>
                </article>

                <article class="f-card">
                    <div class="f-icon g5" aria-hidden="true">
                        <svg class="ic" viewBox="0 0 24 24"><path d="M4 20V4M4 20h16M8 16v-4M12 16V8M16 16v-6"></path></svg>
                    </div>
                    <h3 data-i18n="features.c5_title">{{ $t['features']['c5_title'] }}</h3>
                    <p data-i18n="features.c5_desc">{{ $t['features']['c5_desc'] }}</p>
                </article>

                <article class="f-card">
                    <div class="f-icon g6" aria-hidden="true">
                        <svg class="ic" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"></path></svg>
                    </div>
                    <h3 data-i18n="features.c6_title">{{ $t['features']['c6_title'] }}</h3>
                    <p data-i18n="features.c6_desc">{{ $t['features']['c6_desc'] }}</p>
                </article>
            </div>

            <!-- Pay Your Way Strip -->
            <div class="pay-strip" aria-label="Supported payment options">
                <span class="pay-strip-label" data-i18n="pay_strip.label">{{ $t['pay_strip']['label'] }}</span>
                <div class="pay-chips">
                    <span class="pay-chip"><i class="pay-chip-dot" aria-hidden="true"></i><span data-i18n="pay_strip.mpesa">{{ $t['pay_strip']['mpesa'] }}</span></span>
                    <span class="pay-chip"><i class="pay-chip-dot" aria-hidden="true"></i><span data-i18n="pay_strip.airtel">{{ $t['pay_strip']['airtel'] }}</span></span>
                    <span class="pay-chip"><i class="pay-chip-dot" aria-hidden="true"></i><span data-i18n="pay_strip.mtn">{{ $t['pay_strip']['mtn'] }}</span></span>
                    <span class="pay-chip"><i class="pay-chip-dot" aria-hidden="true"></i><span data-i18n="pay_strip.cards">{{ $t['pay_strip']['cards'] }}</span></span>
                    <span class="pay-chip"><i class="pay-chip-dot" aria-hidden="true"></i><span data-i18n="pay_strip.bank">{{ $t['pay_strip']['bank'] }}</span></span>
                    <span class="pay-chip"><span data-i18n="pay_strip.more">{{ $t['pay_strip']['more'] }}</span></span>
                </div>
            </div>
        </section>

        <!-- ─── STEPS SECTION (Dark Navy #0F172A) ─── -->
        <section class="steps-sec sec" id="how">
            <div class="w">
                <div class="sec-head">
                    <h2 data-i18n="steps.title">{{ $t['steps']['title'] }}</h2>
                    <p data-i18n="steps.subtitle">{{ $t['steps']['subtitle'] }}</p>
                </div>

                <div class="steps-grid">
                    <div class="step-card">
                        <span class="step-num">1</span>
                        <h3 data-i18n="steps.s1_title">{{ $t['steps']['s1_title'] }}</h3>
                        <p data-i18n="steps.s1_desc">{{ $t['steps']['s1_desc'] }}</p>
                    </div>
                    <div class="step-card">
                        <span class="step-num">2</span>
                        <h3 data-i18n="steps.s2_title">{{ $t['steps']['s2_title'] }}</h3>
                        <p data-i18n="steps.s2_desc">{{ $t['steps']['s2_desc'] }}</p>
                    </div>
                    <div class="step-card">
                        <span class="step-num">3</span>
                        <h3 data-i18n="steps.s3_title">{{ $t['steps']['s3_title'] }}</h3>
                        <p data-i18n="steps.s3_desc">{{ $t['steps']['s3_desc'] }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─── PRICING SECTION ─── -->
        <section class="w sec" id="pricing">
            <div class="sec-head">
                <h2 data-i18n="pricing.title">{{ $t['pricing']['title'] }}</h2>
                <p data-i18n="pricing.subtitle">{{ $t['pricing']['subtitle'] }}</p>
            </div>

            <div class="currency-bar">
                <label for="currencySelect" data-i18n="pricing.currency_label">{{ $t['pricing']['currency_label'] }}</label>
                <select id="currencySelect" class="currency-select" aria-label="{{ $t['pricing']['currency_label'] }}">
                    @foreach($currencies as $code => $cInfo)
                        <option value="{{ $code }}" {{ $code === $defaultCurrency ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pricing-grid">
                @forelse($pricingPlans as $plan)
                    @php
                        $pName = $plan['name'] ?? 'Plan';
                        $basePriceKes = null;
                        if (array_key_exists('base_price_kes', $plan) && $plan['base_price_kes'] !== null && $plan['base_price_kes'] !== '') {
                            $basePriceKes = (int) $plan['base_price_kes'];
                        } elseif (isset($plan['price'])) {
                            if (is_numeric($plan['price'])) {
                                $basePriceKes = (int) $plan['price'];
                            } elseif (is_string($plan['price']) && strtolower(trim($plan['price'])) !== 'custom') {
                                $digits = preg_replace('/[^\d]/', '', $plan['price']);
                                if ($digits !== '') {
                                    $basePriceKes = (int) $digits;
                                }
                            }
                        }
                        $isCustom = ($basePriceKes === null || (isset($plan['price']) && strtolower(trim((string)$plan['price'])) === 'custom'));
                        $period = isset($plan['period']) ? trim($plan['period']) : '/month';
                        if ($period !== '' && !str_starts_with($period, '/') && !in_array(strtolower($period), ['one-time', 'none', 'custom'])) {
                            $period = '/' . $period;
                        }
                        if (in_array(strtolower($period), ['none', 'custom', ''])) {
                            $period = '';
                        }

                        $isPopular = !empty($plan['is_popular']) || !empty($plan['highlighted']) || !empty($plan['badge']);
                        $badgeText = !empty($plan['badge']) ? $plan['badge'] : ($isPopular ? 'POPULAR' : null);
                        $planSlug = $plan['slug'] ?? \Illuminate\Support\Str::slug($pName);
                        $planTargetUrl = auth()->check() ? url('/subscription?plan=' . $planSlug) : ($registerUrl . '?plan=' . $planSlug);
                        $btnUrl = $isCustom ? $whatsAppUrl : (auth()->check() ? url('/subscription?plan=' . $planSlug) : (!empty($plan['button_url']) ? $plan['button_url'] : $planTargetUrl));
                        $btnText = !empty($plan['button_text']) ? $plan['button_text'] : ($isCustom ? 'Talk to us' : 'Choose Plan');
                        $rawFeatures = is_array($plan['features'] ?? null) ? $plan['features'] : [];

                        $builtFeatures = [];
                        if (isset($plan['router_limit']) && $plan['router_limit'] !== '' && $plan['router_limit'] !== null) {
                            $rLimit = (int) $plan['router_limit'];
                            $rText = $rLimit === 0 ? 'Unlimited routers' : ($rLimit . ' ' . ($rLimit === 1 ? 'router' : 'routers'));
                            $hasRouterBullet = false;
                            foreach ($rawFeatures as $f) {
                                if (preg_match('/router/i', (string)$f)) {
                                    $hasRouterBullet = true;
                                    break;
                                }
                            }
                            if (!$hasRouterBullet) {
                                $builtFeatures[] = $rText;
                            }
                        }

                        if (isset($plan['customer_limit']) && $plan['customer_limit'] !== '' && $plan['customer_limit'] !== null) {
                            $cLimit = (int) $plan['customer_limit'];
                            $cText = $cLimit === 0 ? 'Unlimited customers' : ($cLimit . ' ' . ($cLimit === 1 ? 'customer' : 'customers'));
                            $hasCustomerBullet = false;
                            foreach ($rawFeatures as $f) {
                                if (preg_match('/customer|subscriber|client/i', (string)$f)) {
                                    $hasCustomerBullet = true;
                                    break;
                                }
                            }
                            if (!$hasCustomerBullet) {
                                $builtFeatures[] = $cText;
                            }
                        }

                        $features = array_merge($builtFeatures, $rawFeatures);
                    @endphp
                    <div class="p-card {{ $isPopular ? 'popular' : '' }}">
                        @if($badgeText)
                            <span class="popular-badge">{{ $badgeText }}</span>
                        @endif
                        <h3>{{ $pName }}</h3>
                        <div class="p-price">
                            @if(!$isCustom)
                                <span data-price-plan data-price-kes="{{ $basePriceKes }}">KES {{ number_format($basePriceKes) }}</span>
                                @if(!empty($period))
                                    <small>{{ $period }}</small>
                                @endif
                            @else
                                <span>Custom</span>
                                @if(!empty($period))
                                    <small>{{ $period }}</small>
                                @endif
                            @endif
                        </div>
                        @if(!empty($features))
                            <ul class="p-features">
                                @foreach($features as $feature)
                                    <li>
                                        <span class="tick-icon" aria-hidden="true">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                <path d="M5 12l5 5L20 7"/>
                                            </svg>
                                        </span>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <a href="{{ $btnUrl }}" class="btn {{ $isPopular ? 'btn-orange' : 'btn-outline' }}">
                            {{ $btnText }}
                        </a>
                    </div>
                @empty
                    <div style="text-align: center; padding: 40px; color: #64748b; font-size: 14px; grid-column: 1 / -1;">
                        <p>No pricing plans currently published. Contact us directly for a custom quote.</p>
                        <a href="{{ $whatsAppUrl }}" class="btn btn-orange" style="margin-top: 15px; display: inline-block;">Talk to us</a>
                    </div>
                @endforelse
            </div>

            <p id="approxBilledNote" class="text-center mt-3" style="display: {{ $defaultCurrency !== 'KES' ? 'block' : 'none' }}; font-size: 0.8125rem; color: #64748b; margin-top: 0.75rem; text-align: center;">
                Approximate. You are billed in KES.
            </p>

            <p class="pricing-note" data-i18n="pricing.note">{{ $t['pricing']['note'] }}</p>
        </section>

        <!-- ─── FAQ SECTION ─── -->
        <section class="w sec" id="faq">
            <div class="sec-head">
                <h2 data-i18n="faq.title">{{ $t['faq']['title'] }}</h2>
            </div>

            <div class="faq-wrap">
                @if(!empty($landingFaqs))
                    @foreach($landingFaqs as $idx => $faq)
                        <details class="fq" {{ $idx === 0 ? 'open' : '' }}>
                            <summary>
                                <span>{{ $faq['q'] ?? '' }}</span>
                                <span class="fq-icon" aria-hidden="true">+</span>
                            </summary>
                            <p>{{ $faq['a'] ?? '' }}</p>
                        </details>
                    @endforeach
                @else
                    @for($i = 1; $i <= 6; $i++)
                        <details class="fq" {{ $i === 1 ? 'open' : '' }}>
                            <summary>
                                <span data-i18n="faq.q{{ $i }}">{{ $t['faq']['q' . $i] }}</span>
                                <span class="fq-icon" aria-hidden="true">+</span>
                            </summary>
                            <p data-i18n="faq.a{{ $i }}">{{ $t['faq']['a' . $i] }}</p>
                        </details>
                    @endfor
                @endif
            </div>
        </section>

        <!-- ─── CTA BANNER ─── -->
        <section class="cta-sec">
            <div class="w cta-inner">
                <h2 class="cta-title" data-i18n="cta.title">{{ $t['cta']['title'] }}</h2>
                <div class="cta-actions">
                    <a href="{{ $registerUrl }}" class="btn btn-orange" data-i18n="cta.primary">{{ $t['cta']['primary'] }}</a>
                    <a href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light" data-i18n="cta.secondary">{{ $t['cta']['secondary'] }}</a>
                </div>
            </div>
        </section>
    </main>

    <!-- ─── FOOTER ─── -->
    <footer class="site-footer">
        <div class="w">
            <div class="footer-grid">
                <div>
                    <div style="margin-bottom: 12px;">
                        <x-hsmart-logo brandName="HSmart" color="light" height="36px" />
                    </div>
                    <p style="margin: 0; font-size: 15px; color: #CBD5E1; max-width: 320px;" data-i18n="footer.tagline">
                        {{ $t['footer']['tagline'] }}
                    </p>
                </div>

                <div>
                    <h4 data-i18n="footer.product">{{ $t['footer']['product'] }}</h4>
                    <div class="footer-links">
                        <a href="#features" data-i18n="nav.features">{{ $t['nav']['features'] }}</a>
                        <a href="#how" data-i18n="nav.how">{{ $t['nav']['how'] }}</a>
                        <a href="#pricing" data-i18n="nav.pricing">{{ $t['nav']['pricing'] }}</a>
                    </div>
                </div>

                <div>
                    <h4 data-i18n="footer.company">{{ $t['footer']['company'] }}</h4>
                    <div class="footer-links">
                        <a href="#faq" data-i18n="nav.faq">{{ $t['nav']['faq'] }}</a>
                        <a href="{{ $loginUrl }}" data-i18n="nav.login">{{ $t['nav']['login'] }}</a>
                        <a href="{{ $registerUrl }}" data-i18n="nav.trial">{{ $t['nav']['trial'] }}</a>
                    </div>
                </div>

                <div>
                    <h4 data-i18n="footer.contact">{{ $t['footer']['contact'] }}</h4>
                    <div class="footer-links">
                        <a href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer" data-i18n="footer.whatsapp">{{ $t['footer']['whatsapp'] }}</a>
                        <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                        <a href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer">{{ $whatsappPlaceholder }}</a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <span>© {{ date('Y') }} {{ $brandName }}. <span data-i18n="footer.rights">{{ $t['footer']['rights'] }}</span></span>
                <div class="footer-legal">
                    <a href="{{ $privacyUrl }}" data-i18n="footer.privacy">{{ $t['footer']['privacy'] }}</a>
                    <a href="{{ $termsUrl }}" data-i18n="footer.terms">{{ $t['footer']['terms'] }}</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ─── STICKY BOTTOM BAR ON PHONES ONLY ─── -->
    <div class="mobile-sticky-bar" role="region" aria-label="Quick actions">
        <a href="{{ $registerUrl }}" class="btn btn-orange btn-trial" data-i18n="sticky.trial">
            {{ $t['sticky']['trial'] }}
        </a>
        <a
            href="{{ $whatsAppUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="whatsapp-icon-btn"
            aria-label="{{ $t['sticky']['whatsapp_aria'] }}"
        >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm5.82 14.01c-.24.68-1.41 1.3-1.95 1.38-.5.08-1.13.11-1.83-.11-.42-.13-.97-.31-1.67-.61-2.94-1.27-4.86-4.23-5.01-4.43-.15-.2-1.2-1.59-1.2-3.04 0-1.45.76-2.16 1.03-2.46.27-.3.59-.37.79-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.24.59.82 2.01.89 2.16.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.44.52-.15.15-.3.31-.13.61.17.3.76 1.25 1.63 2.03 1.12 1 2.06 1.31 2.36 1.46.3.15.47.12.64-.07.17-.2.74-.86.94-1.16.2-.3.4-.25.67-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.12.07.72-.17 1.4z"/>
            </svg>
        </a>
    </div>
</div>

<script>
(function () {
    const CONFIG = {
        DEFAULT_CURRENCY: @json($defaultCurrency),
        WHATSAPP_NUMBER: @json($whatsappNumber),
        CURRENCIES: @json($currencies),
        TRANSLATIONS: @json($allTranslations),
    };

    function formatPrice(amountInKes, currencyCode) {
        const code = (currencyCode && CONFIG.CURRENCIES[currencyCode]) ? currencyCode : CONFIG.DEFAULT_CURRENCY;
        const cfg = CONFIG.CURRENCIES[code] || CONFIG.CURRENCIES.KES;
        const rate = Number(cfg.rate_from_kes || 1);
        const converted = Math.round(Number(amountInKes) * rate);
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: code,
                maximumFractionDigits: 0
            }).format(converted);
        } catch (e) {
            return code + ' ' + converted.toLocaleString('en-US');
        }
    }

    function formatPlanPrice(kesAmount, currencyCode) {
        if (kesAmount === null || kesAmount === undefined || isNaN(kesAmount) || kesAmount <= 0) {
            return 'Custom';
        }
        const code = (currencyCode && CONFIG.CURRENCIES[currencyCode]) ? currencyCode : CONFIG.DEFAULT_CURRENCY;
        const cfg = CONFIG.CURRENCIES[code] || CONFIG.CURRENCIES.KES;
        const rate = Number(cfg.rate_from_kes || 1);
        const converted = Math.round(Number(kesAmount) * rate);
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: code,
                maximumFractionDigits: 0
            }).format(converted);
        } catch (e) {
            return code + ' ' + converted.toLocaleString('en-US');
        }
    }

    function applyCurrency(currencyCode) {
        const code = (currencyCode && CONFIG.CURRENCIES[currencyCode]) ? currencyCode : CONFIG.DEFAULT_CURRENCY;
        document.querySelectorAll('[data-price-kes]').forEach(function (el) {
            const kes = parseFloat(el.getAttribute('data-price-kes') || '0');
            el.textContent = formatPrice(kes, code);
        });
        document.querySelectorAll('[data-price-plan]').forEach(function (el) {
            const kes = parseFloat(el.getAttribute('data-price-kes') || '0');
            el.textContent = formatPlanPrice(kes, code);
        });

        const noteEl = document.getElementById('approxBilledNote');
        if (noteEl) {
            noteEl.style.display = (code !== 'KES') ? 'block' : 'none';
        }

        const select = document.getElementById('currencySelect');
        if (select && select.value !== code) {
            select.value = code;
        }
        try {
            document.cookie = "hsmart_currency=" + encodeURIComponent(code) + ";path=/;max-age=" + (30 * 86400) + ";SameSite=Lax";
            localStorage.setItem('hsmart_landing_currency', code);
        } catch (e) {}
    }

    function getNested(obj, path) {
        return path.split('.').reduce(function (acc, part) {
            return acc && acc[part] !== undefined ? acc[part] : null;
        }, obj);
    }

    function applyLanguage(lang) {
        const dict = CONFIG.TRANSLATIONS[lang] || CONFIG.TRANSLATIONS.en;
        document.documentElement.setAttribute('lang', lang);
        if (dict.meta && dict.meta.title) {
            document.title = dict.meta.title;
        }
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            const key = el.getAttribute('data-i18n');
            const val = getNested(dict, key);
            if (val) {
                el.textContent = val;
            }
        });
        document.querySelectorAll('[data-lang-switch]').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-lang-switch') === lang);
        });
        try {
            localStorage.setItem('hsmart_landing_lang', lang);
        } catch (e) {}
    }

    // Mobile Menu Toggle
    const menuBtn = document.getElementById('mobileMenuBtn');
    const menuPanel = document.getElementById('mobileMenuPanel');
    const iconPath = document.getElementById('hamburgerIconPath');

    if (menuBtn && menuPanel) {
        menuBtn.addEventListener('click', function () {
            const isOpen = menuPanel.classList.toggle('is-open');
            menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (iconPath) {
                iconPath.setAttribute('d', isOpen ? 'M6 6l12 12M6 18L18 6' : 'M4 7h16M4 12h16M4 17h16');
            }
        });

        document.querySelectorAll('.mobile-nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                menuPanel.classList.remove('is-open');
                menuBtn.setAttribute('aria-expanded', 'false');
                if (iconPath) {
                    iconPath.setAttribute('d', 'M4 7h16M4 12h16M4 17h16');
                }
            });
        });
    }

    // Currency Switcher
    const currencySelect = document.getElementById('currencySelect');
    if (currencySelect) {
        currencySelect.addEventListener('change', function (e) {
            applyCurrency(e.target.value);
        });
    }

    // Language Switcher
    document.querySelectorAll('[data-lang-switch]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetLang = btn.getAttribute('data-lang-switch');
            applyLanguage(targetLang);
        });
    });

    // Initialize saved preferences or defaults (prioritize explicit cookie choice)
    let initialCurrency = 'KES';
    let initialLang = @json($locale);
    try {
        const cookieMatch = document.cookie.match(/(?:^|; )hsmart_currency=([^;]*)/);
        const cookieCurr = cookieMatch ? decodeURIComponent(cookieMatch[1]) : null;
        if (cookieCurr && CONFIG.CURRENCIES[cookieCurr]) {
            initialCurrency = cookieCurr;
        } else if (CONFIG.DEFAULT_CURRENCY && CONFIG.CURRENCIES[CONFIG.DEFAULT_CURRENCY]) {
            initialCurrency = CONFIG.DEFAULT_CURRENCY;
        }
        const savedLang = localStorage.getItem('hsmart_landing_lang');
        if (savedLang && CONFIG.TRANSLATIONS[savedLang]) {
            initialLang = savedLang;
        }
    } catch (e) {}

    applyCurrency(initialCurrency);
    applyLanguage(initialLang);
})();
</script>
</body>
</html>
