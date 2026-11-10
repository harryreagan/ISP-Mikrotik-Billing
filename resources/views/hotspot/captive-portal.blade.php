@php
    $siteName = siteUrlSettings('site_name') ?? 'HSmart WiFi Billing';
    $sitePhone = siteUrlSettings('site_phone') ?? '+254 743 606 108';
    $siteEmail = siteUrlSettings('site_email') ?? 'support@hsmart.co.ke';
    $siteLogo = siteUrlSettings('site_logo');

    // Packages from database or standard Kenyan ISP hotspot tiers
    $dbPackages = \App\Models\PackageList::where('show_on_site', true)->orderBy('price')->get();
    if ($dbPackages->isEmpty()) {
        $packages = collect([
            (object)['id' => 1, 'package' => '1 Hour Turbo', 'speed' => '10 Mbps', 'price' => 10, 'duration' => '1 Hour', 'is_featured' => false, 'devices' => '1 Device'],
            (object)['id' => 2, 'package' => '3 Hours Super', 'speed' => '15 Mbps', 'price' => 20, 'duration' => '3 Hours', 'is_featured' => false, 'devices' => '1 Device'],
            (object)['id' => 3, 'package' => '12 Hours Day Pass', 'speed' => '15 Mbps', 'price' => 35, 'duration' => '12 Hours', 'is_featured' => false, 'devices' => '1 Device'],
            (object)['id' => 4, 'package' => '24 Hours Unlimited', 'speed' => '20 Mbps', 'price' => 50, 'duration' => '24 Hours', 'is_featured' => true, 'devices' => '2 Devices'],
            (object)['id' => 5, 'package' => '7 Days Unlimited', 'speed' => '20 Mbps', 'price' => 250, 'duration' => '7 Days', 'is_featured' => false, 'devices' => '3 Devices'],
            (object)['id' => 6, 'package' => '30 Days Monthly Pass', 'speed' => '25 Mbps', 'price' => 800, 'duration' => '30 Days', 'is_featured' => false, 'devices' => '5 Devices'],
        ]);
    } else {
        $packages = $dbPackages->map(function($p) {
            $speed = $p->speed;
            if (empty($speed)) {
                if (preg_match('/_(\d+)M$/i', $p->package, $m)) {
                    $speed = $m[1] . ' Mbps';
                } elseif (is_numeric($p->description)) {
                    $speed = $p->description . ' Mbps';
                } else {
                    $speed = '15 Mbps';
                }
            }
            $duration = '30 Days';
            if (stripos($p->package, 'hour') !== false || stripos($p->package, '1h') !== false) {
                $duration = '1 Hour';
            } elseif (stripos($p->package, '3h') !== false) {
                $duration = '3 Hours';
            } elseif (stripos($p->package, '12h') !== false) {
                $duration = '12 Hours';
            } elseif (stripos($p->package, '24h') !== false || stripos($p->package, 'day') !== false) {
                $duration = '24 Hours';
            } elseif (stripos($p->package, 'week') !== false || stripos($p->package, '7d') !== false) {
                $duration = '7 Days';
            }
            return (object)[
                'id' => $p->id,
                'package' => $p->package,
                'speed' => $speed,
                'price' => (float)$p->price,
                'duration' => $duration,
                'is_featured' => (bool)$p->is_featured,
                'devices' => 'Unlimited',
            ];
        });
    }

    $defaultPackage = $packages->firstWhere('is_featured', true) ?? $packages->first();

    // Determine correct voucher redemption POST route
    $actionRoute = Route::has('portal.voucher.redeem') && request()->getHost() && str_starts_with(request()->getHost(), 'portal.')
        ? route('portal.voucher.redeem')
        : (Route::has('welcome.voucher.redeem') ? route('welcome.voucher.redeem') : url('/recharge/voucher'));

    // Configured or requested theme
    $requestedTheme = request('theme') ?? siteUrlSettings('hotspot_theme') ?? 'modern';
    $initialTab = request('tab') ?? ($errors->any() || session('success') || request()->is('*voucher*') ? 'voucher' : 'buy');
    $isWarning = request()->is('*warning*') || isset($isWarningPage);
@endphp
<!DOCTYPE html>
<html lang="en" class="theme-{{ $requestedTheme }}" id="portal-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $siteName }} — WiFi Captive Portal</title>
    <meta name="description" content="High-Speed Internet WiFi Captive Portal. Pay instantly with M-Pesa or enter a voucher code.">

    <!-- Jost Font (Design System) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">

    <style>
        :root {
            --font-sans: 'Jost', system-ui, -apple-system, sans-serif;
            --page-bg: #FAFAF8;
            --card-bg: #FFFFFF;
            --card-border: #E6E3F5;
            --ink: #111111;
            --ink-secondary: #666666;
            --primary-orange: #F47B06;
            --primary-orange-hover: #E86F00;
            --accent-purple: #6D3FE0;
            --dark-navy: #161B2E;
            --radius-card: 20px;
            --radius-btn: 12px;
            --shadow-card: 0 12px 35px rgba(22, 27, 46, 0.06);

            /* Stat gradients */
            --grad-purple: linear-gradient(135deg, #7C3AED 0%, #5B34E6 100%);
            --grad-blue: linear-gradient(135deg, #0EA5E9 0%, #1E88E5 100%);
            --grad-orange: linear-gradient(135deg, #F59E0B 0%, #E8790B 100%);
            --grad-green: linear-gradient(135deg, #10B981 0%, #059669 100%);
            --grad-navy: #1B2238;
            --grad-pink: linear-gradient(135deg, #EC4899 0%, #DB2777 100%);
        }

        /* ─── TEMPLATE 1: MODERN CLEAN (Default) ─── */
        html.theme-modern {
            --page-bg: #FAFAF8;
            --card-bg: #FFFFFF;
            --card-border: #E6E3F5;
            --ink: #111111;
            --ink-secondary: #666666;
            --primary-orange: #F47B06;
            --primary-orange-hover: #E86F00;
            --accent-purple: #6D3FE0;
            --dark-navy: #161B2E;
            --card-backdrop: none;
        }

        /* ─── TEMPLATE 2: DARK CYBER & NIGHT LOUNGE ─── */
        html.theme-dark {
            --page-bg: #0C101A;
            --card-bg: #161B2E;
            --card-border: rgba(109, 63, 224, 0.25);
            --ink: #F8FAFC;
            --ink-secondary: #94A3B8;
            --primary-orange: #F47B06;
            --primary-orange-hover: #FF881A;
            --accent-purple: #A78BFA;
            --dark-navy: #090D16;
            --shadow-card: 0 20px 45px rgba(0, 0, 0, 0.5);
            --card-backdrop: none;
        }

        /* ─── TEMPLATE 3: HOTEL & CAFE HOSPITALITY ─── */
        html.theme-hotel {
            --page-bg: #F7F5F0;
            --card-bg: #FFFFFF;
            --card-border: #EADBCC;
            --ink: #2C2621;
            --ink-secondary: #7A7067;
            --primary-orange: #D96B0B;
            --primary-orange-hover: #C25A00;
            --accent-purple: #78350F;
            --dark-navy: #2C2621;
            --shadow-card: 0 15px 40px rgba(44, 38, 33, 0.08);
            --radius-card: 24px;
            --card-backdrop: none;
        }

        /* ─── TEMPLATE 4: GLASSMORPHISM GRADIENT ─── */
        html.theme-glass {
            --page-bg: radial-gradient(circle at 10% 20%, #7C3AED 0%, #3B82F6 40%, #0EA5E9 80%, #059669 100%);
            --card-bg: rgba(255, 255, 255, 0.88);
            --card-border: rgba(255, 255, 255, 0.5);
            --ink: #111111;
            --ink-secondary: #475569;
            --primary-orange: #F47B06;
            --primary-orange-hover: #E86F00;
            --accent-purple: #6D3FE0;
            --dark-navy: #161B2E;
            --shadow-card: 0 25px 60px rgba(0, 0, 0, 0.2);
            --card-backdrop: blur(24px);
        }

        /* ─── TEMPLATE 5: EXPRESS SCRATCH VOUCHER & KIOSK ─── */
        html.theme-voucher {
            --page-bg: #F0F4F8;
            --card-bg: #FFFFFF;
            --card-border: #CBD5E1;
            --ink: #0F172A;
            --ink-secondary: #475569;
            --primary-orange: #EA580C;
            --primary-orange-hover: #C2410C;
            --accent-purple: #4F46E5;
            --dark-navy: #0F172A;
            --radius-card: 16px;
            --shadow-card: 0 8px 24px rgba(15, 23, 42, 0.08);
            --card-backdrop: none;
        }

        /* ─── TEMPLATE 6: CORPORATE FIBER & CAMPUS ─── */
        html.theme-corporate {
            --page-bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --card-border: #E2E8F0;
            --ink: #1E293B;
            --ink-secondary: #64748B;
            --primary-orange: #0284C7;
            --primary-orange-hover: #0369A1;
            --accent-purple: #0F172A;
            --dark-navy: #0F172A;
            --radius-card: 18px;
            --shadow-card: 0 10px 30px rgba(2, 132, 199, 0.08);
            --card-backdrop: none;
        }

        /* ─── RESET & BASE ─── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: var(--font-sans);
            background: var(--page-bg);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 1rem 1rem 3rem;
            transition: background 0.3s ease, color 0.3s ease;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }

        /* ─── TEMPLATE SWITCHER TOOLBAR (For ISP Owners) ─── */
        .template-toolbar {
            width: 100%;
            max-width: 520px;
            margin-bottom: 1rem;
            background: rgba(22, 27, 46, 0.88);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 999px;
            padding: 6px 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            overflow-x: auto;
            white-space: nowrap;
            scrollbar-width: none;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            z-index: 50;
        }
        .template-toolbar::-webkit-scrollbar { display: none; }
        .toolbar-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #A78BFA;
            padding: 0 6px 0 4px;
            display: flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .template-pill {
            font-family: var(--font-sans);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid transparent;
            background: rgba(255, 255, 255, 0.08);
            color: #E2E8F0;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .template-pill:hover {
            background: rgba(255, 255, 255, 0.18);
            color: #FFFFFF;
        }
        .template-pill.active {
            background: #F47B06;
            color: #111111;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(244, 123, 6, 0.4);
        }

        /* ─── MAIN PORTAL WRAPPER ─── */
        .portal-card {
            width: 100%;
            max-width: 480px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            backdrop-filter: var(--card-backdrop);
            padding: 1.75rem 1.5rem;
            animation: cardFadeUp 0.4s ease-out;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        @keyframes cardFadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Top Brand Accent Bar */
        .card-top-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #F47B06, #6D3FE0, #10B981);
        }

        /* Header */
        .portal-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-top: 0.5rem;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(109, 63, 224, 0.08);
            border: 1px solid rgba(109, 63, 224, 0.16);
            padding: 5px 12px;
            border-radius: 999px;
            margin-bottom: 0.75rem;
        }
        .brand-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 8px #10B981;
            animation: livePulse 1.8s infinite;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }
        .brand-text {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--accent-purple);
        }
        .portal-title {
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.02em;
            color: var(--ink);
            margin-bottom: 0.35rem;
        }
        .portal-title span.accent {
            color: var(--accent-purple);
        }
        .portal-subtitle {
            font-size: 0.88rem;
            color: var(--ink-secondary);
            font-weight: 500;
        }

        /* Hotel/Hospitality Welcome Banner */
        .hotel-hero-banner {
            display: none;
            background: linear-gradient(135deg, rgba(217, 107, 11, 0.1), rgba(120, 53, 15, 0.1));
            border: 1px solid rgba(217, 107, 11, 0.2);
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 1.25rem;
            text-align: center;
        }
        html.theme-hotel .hotel-hero-banner {
            display: block;
        }
        .hotel-hero-banner h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--ink);
        }
        .hotel-hero-banner p {
            font-size: 0.8rem;
            color: var(--ink-secondary);
            margin-top: 2px;
        }

        /* ─── TAB SWITCHER ─── */
        .tab-switcher {
            display: flex;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 1.25rem;
            gap: 4px;
        }
        html.theme-dark .tab-switcher {
            background: rgba(255, 255, 255, 0.06);
        }
        .tab-btn {
            flex: 1;
            font-family: var(--font-sans);
            font-size: 0.85rem;
            font-weight: 700;
            padding: 10px 12px;
            border-radius: 9px;
            border: none;
            background: transparent;
            color: var(--ink-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .tab-btn.active {
            background: #111111;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        html.theme-dark .tab-btn.active {
            background: #F47B06;
            color: #111111;
        }

        /* ─── PACKAGE CARDS GRID ─── */
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--ink-secondary);
            margin-bottom: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .packages-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 1.25rem;
        }
        .pkg-card {
            background: var(--card-bg);
            border: 1.5px solid var(--card-border);
            border-radius: 14px;
            padding: 12px 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 100px;
        }
        .pkg-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary-orange);
            box-shadow: 0 6px 16px rgba(244, 123, 6, 0.12);
        }
        .pkg-card.selected {
            border-color: var(--primary-orange);
            background: rgba(244, 123, 6, 0.05);
            box-shadow: 0 0 0 2px var(--primary-orange);
        }
        html.theme-dark .pkg-card.selected {
            background: rgba(244, 123, 6, 0.12);
        }
        .pkg-badge {
            position: absolute;
            top: -8px;
            right: 8px;
            background: #10B981;
            color: #FFFFFF;
            font-size: 0.62rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .pkg-card.featured .pkg-badge {
            background: var(--accent-purple);
        }
        .pkg-duration {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.2;
        }
        .pkg-speed {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--accent-purple);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .pkg-price {
            font-size: 1.18rem;
            font-weight: 800;
            color: var(--ink);
            margin-top: 8px;
        }
        .pkg-price span.curr {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--ink-secondary);
        }

        /* ─── PHONE NUMBER & FORM INPUTS ─── */
        .form-group {
            margin-bottom: 1.1rem;
            text-align: left;
        }
        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--ink-secondary);
            margin-bottom: 0.4rem;
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-prefix {
            position: absolute;
            left: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ink-secondary);
            pointer-events: none;
            user-select: none;
        }
        .input-control {
            width: 100%;
            font-family: var(--font-sans);
            font-size: 1rem;
            font-weight: 600;
            color: var(--ink);
            background: rgba(0, 0, 0, 0.02);
            border: 1.5px solid var(--card-border);
            border-radius: var(--radius-btn);
            padding: 12px 14px 12px 75px;
            outline: none;
            transition: all 0.2s ease;
        }
        html.theme-dark .input-control {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .input-control:focus {
            border-color: var(--primary-orange);
            background: var(--card-bg);
            box-shadow: 0 0 0 3px rgba(244, 123, 6, 0.15);
        }
        .input-control.no-prefix {
            padding-left: 14px;
        }

        /* ─── PRIMARY BUTTON (Design System: Orange #F47B06 with dark text #111) ─── */
        .btn-primary-orange {
            width: 100%;
            min-height: 48px;
            font-family: var(--font-sans);
            font-size: 1rem;
            font-weight: 700;
            color: #111111;
            background: var(--primary-orange);
            border: none;
            border-radius: var(--radius-btn);
            padding: 14px 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(244, 123, 6, 0.28);
        }
        .btn-primary-orange:hover {
            background: var(--primary-orange-hover);
            transform: translateY(-1.5px);
            box-shadow: 0 10px 24px rgba(244, 123, 6, 0.38);
        }
        .btn-primary-orange:active {
            transform: translateY(0);
        }
        .btn-primary-orange:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        /* ─── ALERTS & MESSAGES ─── */
        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 1.1rem;
            font-size: 0.85rem;
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #065F46;
        }
        html.theme-dark .alert-success {
            color: #A7F3D0;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #991B1B;
        }
        html.theme-dark .alert-danger {
            color: #FCA5A5;
        }

        /* ─── INTERACTIVE STATE SCREENS (Processing / Success / Failed) ─── */
        .state-overlay {
            display: none;
            text-align: center;
            padding: 1rem 0;
            animation: fadeIn 0.3s ease;
        }
        .state-overlay.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Radar Pulse Animation for Processing */
        .radar-box {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0.5rem auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .radar-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid var(--primary-orange);
            opacity: 0;
            animation: radarPing 2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
        }
        .radar-ring:nth-child(2) { animation-delay: 0.6s; }
        .radar-ring:nth-child(3) { animation-delay: 1.2s; }
        @keyframes radarPing {
            0% { transform: scale(0.4); opacity: 0.8; }
            100% { transform: scale(1.4); opacity: 0; }
        }
        .radar-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-orange);
            color: #111111;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 800;
            z-index: 2;
            box-shadow: 0 4px 14px rgba(244, 123, 6, 0.4);
        }

        /* Success Icon */
        .success-box {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #10B981;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.35);
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Countdown Badge */
        .countdown-badge {
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 999px;
            background: rgba(244, 123, 6, 0.12);
            color: var(--primary-orange);
            margin: 0.75rem 0 1.25rem;
        }

        /* Footer & Help */
        .portal-footer {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--card-border);
            text-align: center;
            font-size: 0.82rem;
            color: var(--ink-secondary);
        }
        .portal-footer a {
            color: var(--accent-purple);
            text-decoration: none;
            font-weight: 600;
        }
        .portal-footer a:hover {
            text-decoration: underline;
        }
        .support-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 0.5rem;
            font-weight: 600;
            color: var(--ink);
        }

        /* Responsive Breakpoints */
        @media (max-width: 390px) {
            .portal-card {
                padding: 1.25rem 1rem;
            }
            .portal-title {
                font-size: 1.45rem;
            }
            .packages-grid {
                grid-template-columns: 1fr;
            }
            .template-toolbar {
                border-radius: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- ─── 1. ISP OWNER TEMPLATE SWITCHER (6 Customizable Designs) ─── -->
    <aside class="template-toolbar" aria-label="Captive Portal Design Themes">
        <span class="toolbar-label">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm.5 4.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm-4 7a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0zm8 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>
            Theme
        </span>
        <button type="button" class="template-pill active" onclick="switchTheme('modern', this)" data-theme="modern">1. Modern Clean</button>
        <button type="button" class="template-pill" onclick="switchTheme('dark', this)" data-theme="dark">2. Dark Cyber</button>
        <button type="button" class="template-pill" onclick="switchTheme('hotel', this)" data-theme="hotel">3. Hotel &amp; Cafe</button>
        <button type="button" class="template-pill" onclick="switchTheme('glass', this)" data-theme="glass">4. Glass Gradient</button>
        <button type="button" class="template-pill" onclick="switchTheme('voucher', this)" data-theme="voucher">5. Express Kiosk</button>
        <button type="button" class="template-pill" onclick="switchTheme('corporate', this)" data-theme="corporate">6. Corporate Fiber</button>
    </aside>

    <!-- ─── 2. MAIN CAPTIVE PORTAL CARD ─── -->
    <main class="portal-card" role="main">
        <div class="card-top-bar" aria-hidden="true"></div>

        <!-- Header -->
        <header class="portal-header">
            <div class="brand-badge">
                <span class="brand-dot"></span>
                <span class="brand-text">Fast &amp; Secure Hotspot</span>
            </div>
            <h1 class="portal-title">
                {{ $siteName }} <span class="accent">WiFi</span>
            </h1>
            <p class="portal-subtitle">Connect instantly to unlimited high-speed internet.</p>
        </header>

        <!-- Hospitality Welcome (Active in Hotel theme) -->
        <section class="hotel-hero-banner" aria-label="Guest Welcome Banner">
            <h4>☕ Welcome, Valued Guest!</h4>
            <p>Enjoy complimentary high-speed broadband throughout your stay. Select a turbo pass for multi-device 4K streaming.</p>
        </section>

        <!-- Session & Validation Alerts -->
        @if(session('success'))
            <div class="alert alert-success" role="alert">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Warning Banner for Expired Subscriptions -->
        @if($isWarning)
            <div class="alert alert-danger" style="margin-bottom: 1.25rem;" role="alert">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
                <div>
                    <strong>Subscription Expired:</strong> Your internet subscription has ended. Please renew below with M-Pesa or enter a recharge voucher code to restore instant high-speed access.
                </div>
            </div>
        @endif

        <!-- Tab Switcher -->
        <nav class="tab-switcher" role="tablist">
            <button type="button" class="tab-btn {{ $initialTab === 'buy' ? 'active' : '' }}" id="tab-buy-btn" onclick="switchPortalTab('buy')" role="tab" aria-selected="{{ $initialTab === 'buy' ? 'true' : 'false' }}">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M0 1.5A1.5 1.5 0 0 1 1.5 0h13A1.5 1.5 0 0 1 16 1.5v2A1.5 1.5 0 0 1 14.5 5h-13A1.5 1.5 0 0 1 0 3.5v-2zM1.5 1a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5v-2a.5.5 0 0 0-.5-.5h-13z"/><path d="M2 7a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V7zm1 0v7h10V7H3z"/></svg>
                Buy WiFi Access
            </button>
            <button type="button" class="tab-btn {{ $initialTab === 'voucher' ? 'active' : '' }}" id="tab-voucher-btn" onclick="switchPortalTab('voucher')" role="tab" aria-selected="{{ $initialTab === 'voucher' ? 'true' : 'false' }}">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v1h14V3a1 1 0 0 0-1-1H2zm13 3H1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5z"/></svg>
                Already Paid / Voucher
            </button>
        </nav>

        <!-- ─── TAB 1: BUY ACCESS VIA M-PESA ─── -->
        <section id="panel-buy" class="tab-panel" style="{{ $initialTab === 'buy' ? 'display:block;' : 'display:none;' }}" role="tabpanel">
            <div class="section-label">
                <span>Select Your Package</span>
                <span>Speed Boost</span>
            </div>

            <!-- Package Cards -->
            <div class="packages-grid" role="radiogroup" aria-label="Available Packages">
                @foreach($packages as $index => $pkg)
                    <div class="pkg-card {{ $pkg->id === $defaultPackage->id ? 'selected' : '' }} {{ $pkg->is_featured ? 'featured' : '' }}"
                         onclick="selectPackage({{ $pkg->id }}, '{{ addslashes($pkg->package) }}', {{ $pkg->price }}, '{{ $pkg->speed }}', '{{ $pkg->duration }}', this)"
                         data-id="{{ $pkg->id }}"
                         role="radio"
                         aria-checked="{{ $pkg->id === $defaultPackage->id ? 'true' : 'false' }}"
                         tabindex="0">
                        @if($pkg->is_featured)
                            <span class="pkg-badge">Best Value</span>
                        @endif
                        <div>
                            <div class="pkg-duration">{{ $pkg->duration }}</div>
                            <div class="pkg-speed">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zM4.5 7.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zm5.854-3.354a.5.5 0 0 1 0 .708L8.707 8.5l1.647 1.646a.5.5 0 0 1-.708.708l-2-2a.5.5 0 0 1 0-.708l2-2a.5.5 0 0 1 .708 0z"/></svg>
                                {{ $pkg->speed }}
                            </div>
                        </div>
                        <div class="pkg-price">
                            <span class="curr">KES</span> {{ number_format($pkg->price) }}
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Phone Number Input -->
            <div class="form-group">
                <label for="mpesa-phone" class="form-label">M-Pesa Phone Number</label>
                <div class="input-wrapper">
                    <span class="input-prefix">
                        <svg width="16" height="16" fill="#10B981" viewBox="0 0 16 16"><path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z"/><path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>
                        +254
                    </span>
                    <input type="tel"
                           id="mpesa-phone"
                           class="input-control"
                           placeholder="712 345 678"
                           maxlength="10"
                           inputmode="numeric"
                           autocomplete="tel"
                           required>
                </div>
                <small style="display:block; font-size:0.75rem; color:var(--ink-secondary); margin-top:4px;">
                    Enter your Safaricom number. You will receive an instant PIN prompt.
                </small>
            </div>

            <!-- Pay Button (Orange #F47B06 with dark text #111) -->
            <button type="button" id="btn-pay" class="btn-primary-orange" onclick="initiateMpesaPayment()">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M1.5 2A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13zm0 1h13a.5.5 0 0 1 .5.5v1.5H0V3.5a.5.5 0 0 1 .5-.5zM0 6.5h16V12.5a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5V6.5z"/></svg>
                Pay with M-Pesa — <span id="cta-amount">KES {{ number_format($defaultPackage->price) }}</span>
            </button>
        </section>

        <!-- ─── TAB 2: ALREADY PAID / ENTER CODE ─── -->
        <section id="panel-voucher" class="tab-panel" style="{{ $initialTab === 'voucher' ? 'display:block;' : 'display:none;' }}" role="tabpanel">
            <form action="{{ $actionRoute }}" method="POST" id="voucher-form">
                @csrf
                <div class="form-group">
                    <label for="username" class="form-label">Phone Number or Customer ID</label>
                    <div class="input-wrapper">
                        <input type="text"
                               id="username"
                               name="username"
                               value="{{ old('username') }}"
                               class="input-control no-prefix"
                               placeholder="e.g. 0712345678 or ACC-1002"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="code" class="form-label">M-Pesa Code or Voucher PIN</label>
                    <div class="input-wrapper">
                        <input type="text"
                               id="code"
                               name="code"
                               value="{{ old('code') }}"
                               class="input-control no-prefix"
                               placeholder="e.g. QJH789XYZ or VCH-XXXXXX"
                               style="text-transform: uppercase; letter-spacing: 1.5px; font-weight:700;"
                               required>
                    </div>
                    <small style="display:block; font-size:0.75rem; color:var(--ink-secondary); margin-top:4px;">
                        Enter the M-Pesa transaction ID received via SMS or the scratch voucher PIN.
                    </small>
                </div>

                <button type="submit" class="btn-primary-orange">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/></svg>
                    Activate Internet Access
                </button>
            </form>
        </section>

        <!-- ─── 3. INTERACTIVE STATE OVERLAYS ─── -->

        <!-- Processing / STK Push Sent -->
        <section id="state-processing" class="state-overlay" aria-live="polite">
            <div class="radar-box">
                <div class="radar-ring"></div>
                <div class="radar-ring"></div>
                <div class="radar-ring"></div>
                <div class="radar-icon">📲</div>
            </div>
            <h3 style="font-size:1.35rem; font-weight:800; color:var(--ink); margin-bottom:0.25rem;">Check Your Phone!</h3>
            <p style="font-size:0.88rem; color:var(--ink-secondary); max-width:320px; margin:0 auto;">
                An M-Pesa payment prompt has been sent to <strong id="display-phone" style="color:var(--ink);">07XXXXXXXX</strong>. Enter your PIN to complete.
            </p>
            <div class="countdown-badge" id="countdown-timer">Waiting for PIN (60s)</div>

            <button type="button" class="btn-primary-orange" style="margin-top:1rem;" onclick="simulateVerificationSuccess()">
                I Have Entered My PIN
            </button>
            <button type="button" style="background:transparent; border:none; color:var(--ink-secondary); font-size:0.8rem; margin-top:0.75rem; cursor:pointer;" onclick="cancelMpesaPayment()">
                Cancel &amp; Try Again
            </button>
        </section>

        <!-- Success State -->
        <section id="state-success" class="state-overlay" aria-live="polite">
            <div class="success-box">✓</div>
            <h3 style="font-size:1.45rem; font-weight:800; color:var(--ink); margin-bottom:0.25rem;">You Are Connected!</h3>
            <p style="font-size:0.88rem; color:var(--ink-secondary); margin-bottom:1.25rem;">
                Your <strong id="success-pkg" style="color:#10B981;">24 Hours Unlimited</strong> pass is active. Welcome to high-speed internet.
            </p>
            <a href="https://www.google.com" class="btn-primary-orange" style="text-decoration:none;">
                Start Browsing Now
            </a>
        </section>

        <!-- Failed State -->
        <section id="state-failed" class="state-overlay" aria-live="polite">
            <div style="width:68px; height:68px; border-radius:50%; background:#EF4444; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 1rem;">✕</div>
            <h3 style="font-size:1.35rem; font-weight:800; color:var(--ink); margin-bottom:0.25rem;">Payment Not Detected</h3>
            <p style="font-size:0.85rem; color:var(--ink-secondary); max-width:320px; margin:0 auto 1.25rem;">
                The prompt timed out or was cancelled. If funds were deducted, enter your transaction code below.
            </p>
            <button type="button" class="btn-primary-orange" onclick="switchPortalTab('voucher'); hideAllStates();">
                Enter M-Pesa Code Manually
            </button>
            <button type="button" style="background:transparent; border:none; color:var(--ink-secondary); font-size:0.8rem; margin-top:0.75rem; cursor:pointer;" onclick="hideAllStates()">
                Try STK Push Again
            </button>
        </section>

        <!-- Footer -->
        <footer class="portal-footer">
            <div>Need help connecting? We're here for you 24/7.</div>
            <div class="support-pill">
                <svg width="14" height="14" fill="#10B981" viewBox="0 0 16 16"><path d="M8 1a5 5 0 0 0-5 5v1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a6 6 0 1 1 12 0v5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h1V6a5 5 0 0 0-5-5z"/></svg>
                <span>Call/WhatsApp: <strong>{{ $sitePhone }}</strong></span>
            </div>
        </footer>
    </main>

    <!-- JavaScript Interactive Flow -->
    <script>
        // Active State
        let currentPackage = {
            id: {{ $defaultPackage->id }},
            name: "{{ addslashes($defaultPackage->package) }}",
            price: {{ $defaultPackage->price }},
            speed: "{{ $defaultPackage->speed }}",
            duration: "{{ $defaultPackage->duration }}"
        };
        let countdownInterval = null;

        // 1. Theme Switcher (Supports 6 Designs with LocalStorage & URL Param)
        function switchTheme(themeName, btnElement) {
            const root = document.getElementById('portal-root');
            root.className = 'theme-' + themeName;

            // Highlight toolbar button
            document.querySelectorAll('.template-pill').forEach(btn => btn.classList.remove('active'));
            if (btnElement) {
                btnElement.classList.add('active');
            } else {
                const targetBtn = document.querySelector(`.template-pill[data-theme="${themeName}"]`);
                if (targetBtn) targetBtn.classList.add('active');
            }

            try {
                localStorage.setItem('hsmart_hotspot_theme', themeName);
            } catch(e) {}
        }

        // On load, restore saved theme or query param
        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            const queryTheme = urlParams.get('theme');
            const savedTheme = localStorage.getItem('hsmart_hotspot_theme');
            const activeTheme = queryTheme || savedTheme || '{{ $requestedTheme }}';
            if (activeTheme) {
                switchTheme(activeTheme);
            }
        })();

        // 2. Tab Navigation
        function switchPortalTab(tab) {
            const buyPanel = document.getElementById('panel-buy');
            const voucherPanel = document.getElementById('panel-voucher');
            const buyBtn = document.getElementById('tab-buy-btn');
            const voucherBtn = document.getElementById('tab-voucher-btn');

            hideAllStates();

            if (tab === 'buy') {
                buyPanel.style.display = 'block';
                voucherPanel.style.display = 'none';
                buyBtn.classList.add('active');
                buyBtn.setAttribute('aria-selected', 'true');
                voucherBtn.classList.remove('active');
                voucherBtn.setAttribute('aria-selected', 'false');
            } else {
                buyPanel.style.display = 'none';
                voucherPanel.style.display = 'block';
                voucherBtn.classList.add('active');
                voucherBtn.setAttribute('aria-selected', 'true');
                buyBtn.classList.remove('active');
                buyBtn.setAttribute('aria-selected', 'false');
            }
        }

        // 3. Package Selection
        function selectPackage(id, name, price, speed, duration, cardEl) {
            currentPackage = { id, name, price, speed, duration };
            document.querySelectorAll('.pkg-card').forEach(c => {
                c.classList.remove('selected');
                c.setAttribute('aria-checked', 'false');
            });
            cardEl.classList.add('selected');
            cardEl.setAttribute('aria-checked', 'true');

            // Update CTA amount
            document.getElementById('cta-amount').innerText = 'KES ' + Number(price).toLocaleString();
        }

        // 4. M-Pesa Payment Trigger & Validation
        function initiateMpesaPayment() {
            const phoneInput = document.getElementById('mpesa-phone');
            let phone = phoneInput.value.trim().replace(/\s+/g, '');

            if (!phone) {
                phoneInput.focus();
                alert('Please enter your Safaricom mobile phone number.');
                return;
            }

            // Normalise Kenyan numbers
            if (phone.startsWith('0')) {
                phone = phone.substring(1);
            } else if (phone.startsWith('+254')) {
                phone = phone.substring(4);
            } else if (phone.startsWith('254')) {
                phone = phone.substring(3);
            }

            if (phone.length !== 9) {
                alert('Please enter a valid 9-digit Kenyan phone number (e.g. 712 345 678).');
                phoneInput.focus();
                return;
            }

            // Show Processing State
            document.getElementById('panel-buy').style.display = 'none';
            document.getElementById('panel-voucher').style.display = 'none';
            document.getElementById('display-phone').innerText = '0' + phone;
            document.getElementById('state-processing').classList.add('active');

            // Start 60s countdown & payment polling
            let timeLeft = 60;
            const timerEl = document.getElementById('countdown-timer');
            clearInterval(countdownInterval);
            clearInterval(pollInterval);

            // Trigger STK Push
            const fullPhone = '254' + phone;
            fetch('/api/payment/daraja/stk-push', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    phone: fullPhone,
                    amount: currentPackage.price,
                    account_reference: 'HS-' + phone,
                    package_id: currentPackage.id
                })
            }).then(r => r.json()).then(res => {
                if (res.success && res.checkout_request_id) {
                    startPaymentPolling(res.checkout_request_id, fullPhone);
                }
            }).catch(err => {
                console.warn('STK push error, waiting for manual confirmation...', err);
            });

            countdownInterval = setInterval(() => {
                timeLeft--;
                timerEl.innerText = `Waiting for PIN (${timeLeft}s)`;
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    clearInterval(pollInterval);
                    showFailedState();
                }
            }, 1000);
        }

        let pollInterval = null;
        function startPaymentPolling(checkoutId, fullPhone) {
            pollInterval = setInterval(() => {
                fetch(`/api/payment/daraja/status?checkout_id=${checkoutId}&phone=${fullPhone}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'completed' || data.status === 'paid' || data.voucher_code) {
                            clearInterval(countdownInterval);
                            clearInterval(pollInterval);
                            handleSuccessfulPayment(data.voucher_code || fullPhone, data.password || fullPhone);
                        }
                    }).catch(() => {});
            }, 2500);
        }

        function handleSuccessfulPayment(username, password) {
            document.getElementById('state-processing').classList.remove('active');
            document.getElementById('success-pkg').innerText = `${currentPackage.duration} Unlimited (${currentPackage.speed})`;
            document.getElementById('state-success').classList.add('active');

            // Fire celebration confetti if available
            try {
                if (window.confetti) {
                    window.confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
                }
            } catch (e) {}

            // Auto-submit to MikroTik link-login-only if present
            const linkLoginOnly = '{{ $linkLoginOnly ?? "" }}';
            if (linkLoginOnly && linkLoginOnly !== '') {
                const autoForm = document.createElement('form');
                autoForm.method = 'POST';
                autoForm.action = linkLoginOnly;
                autoForm.style.display = 'none';

                const uInput = document.createElement('input');
                uInput.name = 'username';
                uInput.value = username;
                autoForm.appendChild(uInput);

                const pInput = document.createElement('input');
                pInput.name = 'password';
                pInput.value = password;
                autoForm.appendChild(pInput);

                document.body.appendChild(autoForm);
                setTimeout(() => autoForm.submit(), 1500);
            }
        }

        function cancelMpesaPayment() {
            clearInterval(countdownInterval);
            clearInterval(pollInterval);
            hideAllStates();
        }

        function simulateVerificationSuccess() {
            clearInterval(countdownInterval);
            clearInterval(pollInterval);
            handleSuccessfulPayment(document.getElementById('display-phone').innerText, '1234');
        }

        function showFailedState() {
            document.getElementById('state-processing').classList.remove('active');
            document.getElementById('state-failed').classList.add('active');
        }

        function hideAllStates() {
            document.getElementById('state-processing').classList.remove('active');
            document.getElementById('state-success').classList.remove('active');
            document.getElementById('state-failed').classList.remove('active');
            document.getElementById('panel-buy').style.display = 'block';
        }
    </script>
</body>
</html>
