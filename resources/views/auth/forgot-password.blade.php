@php
    $siteName = siteUrlSettings('site_name') ?? 'HSmart WiFi Billing';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password – {{ $siteName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Jost', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --bg-page: #f8fafc;
            --ink: #1e293b;
            --secondary: #64748b;
            --primary: #f97316;
            --primary-hover: #ea580c;
            --accent: #f97316;
            --navy: #0f172a;
            --border: rgba(226, 232, 240, 0.85);
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
            max-width: 520px;
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
        }

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
        .back-link {
            display: inline-block;
            font-size: 14px;
            font-weight: 700;
            color: var(--accent);
            text-decoration: none;
            margin-bottom: 20px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .form-header h1 {
            font-size: 30px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }
        .form-header p {
            font-size: 14px;
            color: var(--secondary);
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 8px;
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
                padding: 30px 20px;
                border: none;
                box-shadow: none;
                background: transparent;
            }
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="hero-panel">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div style="margin-bottom: 24px;">
                <x-hsmart-logo color="light" height="42px" />
            </div>

            <div class="hero-middle">
                <div class="hero-pill">
                    <span>🔒 Account Security</span>
                </div>
                <h1 class="hero-title">Reset your password quickly & securely.</h1>
                <p class="hero-text">
                    Regain access to your router management, customer profiles, and real-time M-Pesa collections in seconds.
                </p>
            </div>

            <div style="font-size: 13px; color: #9CA3AF;">
                Need assistance? Contact support@hsmart.co.ke
            </div>
        </div>
    </div>

    <div class="form-panel">
        <div class="form-card">
            <div class="mobile-brand" style="margin-bottom: 24px; text-align: center;">
                <x-hsmart-logo color="dark" height="40px" />
            </div>

            <a href="{{ route('login') }}" class="back-link">← Back to Sign In</a>

            <div class="form-header">
                <h1>Forgot Password</h1>
                <p>Enter your phone number or email address and we will dispatch a secure SMS verification code or password reset link.</p>
            </div>

            @if(session('status'))
                <div class="alert-box alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="identity">Phone Number or Email Address</label>
                    <input class="form-input {{ ($errors->has('identity') || $errors->has('email')) ? 'is-invalid' : '' }}" 
                           id="identity" 
                           type="text" 
                           name="identity" 
                           value="{{ old('identity', old('email')) }}" 
                           required 
                           autofocus 
                           placeholder="e.g. 0712345678 or you@example.com" 
                           autocomplete="username">
                    @if($errors->has('identity'))
                        <span class="field-error">{{ $errors->first('identity') }}</span>
                    @elseif($errors->has('email'))
                        <span class="field-error">{{ $errors->first('email') }}</span>
                    @endif
                </div>

                <button type="submit" class="btn-submit">Continue to Reset</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
