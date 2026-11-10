@php
    $siteName = 'HSmart ISP';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In – {{ $siteName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Jost', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --bg-page: #FAFAF8;
            --ink: #111111;
            --secondary: #666666;
            --primary: #F47B06;
            --primary-hover: #E86F00;
            --accent: #6D3FE0;
            --navy: #161B2E;
            --border: #E6E3F5;
        }
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: var(--font-sans);
            color: var(--ink);
            background-color: var(--bg-page);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        .auth-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ─── LEFT HERO PANEL ─── */
        .hero-panel {
            flex: 1 1 50%;
            min-height: 100vh;
            background: linear-gradient(165deg, #0F1424 0%, #161B2E 50%, #1A2035 100%);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 56px;
            color: #ffffff;
            overflow: hidden;
        }
        /* Solid dark navy overlay (NO awkward gradients) */
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 10% 20%, rgba(109, 63, 224, 0.12) 0%, transparent 60%),
                        radial-gradient(circle at 90% 80%, rgba(244, 123, 6, 0.08) 0%, transparent 50%);
            z-index: 1;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            gap: 36px;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            background: #ffffff;
            padding: 10px 22px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            align-self: flex-start;
        }
        .brand-badge img {
            height: 44px;
            width: auto;
            object-fit: contain;
            display: block;
        }
        .hero-middle {
            max-width: 540px;
        }
        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(244, 123, 6, 0.16);
            border: 1px solid rgba(244, 123, 6, 0.45);
            color: #F47B06;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 6px 16px;
            border-radius: 999px;
            margin-bottom: 20px;
        }
        .hero-title {
            font-size: clamp(32px, 3.2vw, 44px);
            font-weight: 800;
            line-height: 1.15;
            color: #ffffff;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }
        .hero-text {
            font-size: 16px;
            line-height: 1.6;
            color: #D1D5DB;
            margin-bottom: 28px;
        }
        .feature-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            color: #E5E7EB;
            font-weight: 500;
        }
        .feature-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.5);
            color: #10B981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .hero-footer-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            backdrop-filter: blur(8px);
            max-width: 520px;
        }
        .pulse-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 10px #10B981;
            display: inline-block;
        }

        /* ─── RIGHT FORM PANEL ─── */
        .form-panel {
            flex: 1 1 50%;
            min-height: 100vh;
            background: var(--bg-page);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 32px;
        }
        .form-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 45px rgba(22, 27, 46, 0.06);
        }
        .mobile-brand {
            display: none;
            margin-bottom: 24px;
            text-align: center;
        }
        .mobile-brand img {
            height: 42px;
            width: auto;
            object-fit: contain;
        }
        .form-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }
        .form-header p {
            font-size: 15px;
            color: var(--secondary);
            margin-bottom: 24px;
        }

        /* Google Button */
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            height: 52px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 14px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-google:hover {
            border-color: #CBD5E1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }
        .btn-google svg {
            width: 20px;
            height: 20px;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            color: #888888;
            font-size: 13px;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Inputs */
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 8px;
        }
        .input-wrapper {
            position: relative;
        }
        .form-input {
            width: 100%;
            height: 52px;
            padding: 0 16px;
            font-family: inherit;
            font-size: 15px;
            color: var(--ink);
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(109, 63, 224, 0.15);
        }
        .form-input.is-invalid {
            border-color: #DC2626;
            background-color: #FEF2F2;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--secondary);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            padding: 4px;
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .remember-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--ink);
            font-weight: 500;
        }
        .forgot-link {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            height: 52px;
            background: var(--primary);
            color: var(--ink);
            border: none;
            border-radius: 14px;
            font-family: inherit;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(244, 123, 6, 0.35);
        }

        /* Registration callout */
        .switch-prompt {
            text-align: center;
            margin-top: 24px;
            font-size: 15px;
            color: var(--secondary);
        }
        .switch-link {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }
        .switch-link:hover {
            text-decoration: underline;
        }

        .security-badge {
            margin-top: 28px;
            text-align: center;
            font-size: 12px;
            color: #9CA3AF;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 18px;
        }
        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #B91C1C;
        }
        .alert-success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #047857;
        }
        .field-error {
            display: block;
            color: #DC2626;
            font-size: 13px;
            font-weight: 600;
            margin-top: 6px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 960px) {
            .hero-panel {
                display: none;
            }
            .form-panel {
                flex: 1 1 100%;
                padding: 32px 20px;
            }
            .mobile-brand {
                display: block;
            }
            .form-card {
                padding: 30px 24px;
                border: none;
                box-shadow: none;
                background: transparent;
            }
        }
    </style>
</head>
<body>

<div class="auth-container">
    <!-- ─── LEFT HERO PANEL ─── -->
    <div class="hero-panel">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <!-- Official HSmart ISP Logo -->
            <div style="margin-bottom: 24px;">
                <x-hsmart-logo color="light" height="44px" />
            </div>

            <div class="hero-middle">
                <div class="hero-pill">
                    <span>★ Leading ISP Automation</span>
                </div>
                <h1 class="hero-title">Automate your ISP network & M-Pesa billing.</h1>
                <p class="hero-text">
                    Control MikroTik routers, provision instant PPPoE bandwidth, and accept automated M-Pesa payments with zero delays across Kenya.
                </p>

                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-icon">✓</div>
                        <span>Instant automated M-Pesa STK Push & C2B collections</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">✓</div>
                        <span>Live MikroTik RouterOS API bandwidth & queue synchronization</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">✓</div>
                        <span>Reseller multi-tier vouchers, SMS gateway & wallet payouts</span>
                    </div>
                </div>
            </div>

            <!-- Trust / Uptime Footer -->
            <div class="hero-footer-card">
                <span class="pulse-dot"></span>
                <div>
                    <div style="font-weight: 700; font-size: 14px; color: #fff;">Automated M-Pesa Payment Engine</div>
                    <div style="font-size: 12px; color: #9CA3AF;">Africa/Nairobi (EAT) • Real-Time Telemetry</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── RIGHT DEDICATED SIGN IN FORM ─── -->
    <div class="form-panel">
        <div class="form-card">
            <!-- Mobile Brand Logo -->
            <div class="mobile-brand" style="margin-bottom:24px; text-align: center;">
                <x-hsmart-logo color="dark" height="40px" />
            </div>

            <div class="form-header">
                <h1>Sign In</h1>
                <p>Welcome back! Enter your credentials to access your ISP dashboard.</p>
            </div>

            @if(session('status'))
                <div class="alert-box alert-success">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-box alert-error">{{ $errors->first() }}</div>
            @endif

            <!-- Continue with Google Button -->
            <a href="{{ route('auth.google') }}" class="btn-google">
                <svg viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                </svg>
                Continue with Google
            </a>

            <div class="divider">or sign in with email</div>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="email">Email or username</label>
                    <div class="input-wrapper">
                        <input class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}" 
                               id="email" 
                               name="email" 
                               type="text" 
                               placeholder="you@example.com" 
                               value="{{ old('email') }}" 
                               required 
                               autocomplete="username" 
                               autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <input class="form-input {{ $errors->has('password') ? 'is-invalid' : '' }}" 
                               id="password" 
                               name="password" 
                               type="password" 
                               placeholder="Enter your password" 
                               required 
                               autocomplete="current-password">
                        <button type="button" class="toggle-password" id="btn-toggle-pw" onclick="togglePw()">Show</button>
                    </div>
                    @if($errors->has('password'))
                        <span class="field-error">{{ $errors->first('password') }}</span>
                    @endif
                </div>

                <div class="form-row">
                    <label class="remember-label">
                        <input type="checkbox" name="remember" id="remember_me"> Remember me
                    </label>
                    <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
                </div>

                <button class="btn-submit" type="submit">Sign In</button>

                <!-- Dedicated Link to Create Account -->
                <div class="switch-prompt">
                    Don't have an account? 
                    <a href="{{ route('register') }}" class="switch-link">Create an account</a>
                </div>

                <div class="security-badge" style="margin-top:28px;text-align:center;font-size:12px;color:#9CA3AF;">
                    &copy; {{ date('Y') }} HSmart Technologies Ltd. All rights reserved.
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function togglePw() {
        var input = document.getElementById('password');
        var btn = document.getElementById('btn-toggle-pw');
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = 'Hide';
        } else {
            input.type = 'password';
            btn.textContent = 'Show';
        }
    }
</script>
</body>
</html>
