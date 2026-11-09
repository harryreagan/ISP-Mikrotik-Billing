@php
    $siteName = siteUrlSettings('site_name') ?? 'HSmart WiFi Billing';

    $activeStep = 'details';
    if ($errors->has('code') || (session('pending_verification_email') && request('tab') === 'verify')) {
        $activeStep = 'verify';
    } elseif (request('tab') === 'done' || session('verification_completed')) {
        $activeStep = 'done';
    }

    $pendingEmail = session('pending_verification_email', old('email', ''));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Your Account – {{ $siteName }}</title>
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
            flex: 1 1 48%;
            min-height: 100vh;
            background-color: var(--navy);
            background-image: url('{{ asset('images/gallery/fiber-light.jpg') }}');
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
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
            background-color: rgba(22, 27, 46, 0.90);
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
            margin-bottom: 28px;
        }
        .benefit-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        .benefit-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: rgba(244, 123, 6, 0.2);
            color: #F47B06;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .benefit-item h4 {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }
        .benefit-item p {
            font-size: 13px;
            color: #9CA3AF;
            line-height: 1.4;
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

        /* ─── RIGHT FORM PANEL ─── */
        .form-panel {
            flex: 1 1 52%;
            min-height: 100vh;
            background: var(--bg-page);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 32px;
        }
        .form-card {
            width: 100%;
            max-width: 480px;
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
            font-size: 30px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .form-header p {
            font-size: 14px;
            color: var(--secondary);
            margin-bottom: 20px;
        }

        /* Progress Steps */
        .steps-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        .step-pill {
            flex: 1;
            height: 5px;
            border-radius: 3px;
            background: var(--border);
        }
        .step-pill.active {
            background: var(--primary);
        }
        .steps-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            height: 50px;
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
            font-size: 12px;
            margin: 18px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Input Grid */
        .input-row {
            display: flex;
            gap: 12px;
            margin-bottom: 14px;
        }
        .input-col {
            flex: 1;
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(109, 63, 224, 0.15);
        }
        .form-input.is-invalid {
            border-color: #DC2626;
            background-color: #FEF2F2;
        }

        .checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: var(--ink);
            margin: 16px 0 20px;
            line-height: 1.4;
        }
        .checkbox-row a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .checkbox-row a:hover {
            text-decoration: underline;
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

        /* Dedicated Switch Link */
        .switch-prompt {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
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

        /* 6-box OTP */
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin: 20px 0 24px;
        }
        .otp-box {
            width: 100%;
            max-width: 58px;
            height: 62px;
            border: 1px solid var(--border);
            border-radius: 12px;
            text-align: center;
            font-family: inherit;
            font-size: 24px;
            font-weight: 700;
            color: var(--ink);
            background: #ffffff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .otp-box:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(109, 63, 224, 0.15);
        }

        .resend-text {
            text-align: center;
            font-size: 14px;
            color: var(--secondary);
            margin-top: 16px;
        }
        .btn-link {
            background: none;
            border: none;
            color: var(--accent);
            font-family: inherit;
            font-size: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 16px;
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
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }

        /* Success Step */
        .success-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #10B981;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 800;
            margin: 0 auto 20px;
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
                padding: 28px 20px;
                border: none;
                box-shadow: none;
                background: transparent;
            }
            .input-row {
                flex-direction: column;
                gap: 0;
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
            <!-- Official HSmart ISP Logo Card -->
            <div class="brand-badge">
                <img src="{{ asset('images/hsmart-isp-logo.jpg') }}" alt="HSmart ISP">
            </div>

            <div class="hero-middle">
                <div class="hero-pill">
                    <span>🚀 Launch in 5 Minutes</span>
                </div>
                <h1 class="hero-title">Launch & scale your ISP business with ease.</h1>
                <p class="hero-text">
                    Join forward-thinking Kenyan network entrepreneurs running automated billing, hotspot vouchers, and fiber customer portals on HSmart.
                </p>

                <div class="benefit-grid">
                    <div class="benefit-item">
                        <div class="benefit-icon">⚡</div>
                        <div>
                            <h4>Instant M-Pesa Paybill & Till Integration</h4>
                            <p>Customers pay via M-Pesa STK push and receive automated internet access in seconds.</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">📡</div>
                        <div>
                            <h4>Full MikroTik RouterOS API Control</h4>
                            <p>Centralized queue monitoring, PPPoE secret creation, and zero manual provisioning.</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">💼</div>
                        <div>
                            <h4>Reseller Distribution & Self-Care Portals</h4>
                            <p>Equip field agents with voucher generation tools and give customers clear billing visibility.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Card -->
            <div class="hero-footer-card">
                <div style="font-weight: 700; font-size: 14px; color: #fff;">Need quick onboarding help?</div>
                <div style="font-size: 12px; color: #9CA3AF; margin-left: auto;">support@hsmart.co.ke</div>
            </div>
        </div>
    </div>

    <!-- ─── RIGHT FORM PANEL ─── -->
    <div class="form-panel">
        <div class="form-card">
            <!-- Mobile Brand -->
            <div class="mobile-brand">
                <img src="{{ asset('images/hsmart-isp-logo.jpg') }}" alt="HSmart ISP">
            </div>

            @if($activeStep === 'details')
                <!-- ─── STEP 1: REGISTRATION DETAILS ─── -->
                <div class="steps-bar">
                    <div class="step-pill active"></div>
                    <div class="step-pill"></div>
                    <div class="step-pill"></div>
                </div>
                <div class="steps-labels">
                    <span style="color:var(--primary)">1. Details</span>
                    <span>2. Verify</span>
                    <span>3. Done</span>
                </div>

                <div class="form-header">
                    <h1>Create Account</h1>
                    <p>Enter your details to register your ISP & WiFi business.</p>
                </div>

                <!-- Google Sign Up -->
                <a href="{{ route('auth.google') }}" class="btn-google">
                    <svg viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                    </svg>
                    Sign up with Google
                </a>

                <div class="divider">or use your email</div>

                <form method="POST" action="{{ route('auth.register.step1') }}">
                    @csrf
                    <div class="input-row">
                        <div class="input-col">
                            <label class="form-label" for="reg-name">Full name</label>
                            <input class="form-input {{ $errors->has('name') ? 'is-invalid' : '' }}" 
                                   id="reg-name" 
                                   name="name" 
                                   type="text" 
                                   placeholder="Harry Reagan" 
                                   value="{{ old('name') }}" 
                                   required 
                                   autocomplete="name">
                            @if($errors->has('name'))
                                <span class="field-error">{{ $errors->first('name') }}</span>
                            @endif
                        </div>
                        <div class="input-col">
                            <label class="form-label" for="reg-biz">Business name</label>
                            <input class="form-input {{ $errors->has('business_name') ? 'is-invalid' : '' }}" 
                                   id="reg-biz" 
                                   name="business_name" 
                                   type="text" 
                                   placeholder="HSmart Networks" 
                                   value="{{ old('business_name') }}">
                            @if($errors->has('business_name'))
                                <span class="field-error">{{ $errors->first('business_name') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="input-row">
                        <div class="input-col">
                            <label class="form-label" for="reg-mobile">Phone (M-Pesa)</label>
                            <input class="form-input {{ $errors->has('mobile') ? 'is-invalid' : '' }}" 
                                   id="reg-mobile" 
                                   name="mobile" 
                                   type="tel" 
                                   placeholder="0712 345 678" 
                                   value="{{ old('mobile') }}" 
                                   autocomplete="tel">
                            @if($errors->has('mobile'))
                                <span class="field-error">{{ $errors->first('mobile') }}</span>
                            @endif
                        </div>
                        <div class="input-col">
                            <label class="form-label" for="reg-email">Email address</label>
                            <input class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}" 
                                   id="reg-email" 
                                   name="email" 
                                   type="email" 
                                   placeholder="you@hsmart.co.ke" 
                                   value="{{ old('email') }}" 
                                   required 
                                   autocomplete="email">
                            @if($errors->has('email'))
                                <span class="field-error">{{ $errors->first('email') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-password">Password (min 8 characters)</label>
                        <input class="form-input {{ $errors->has('password') ? 'is-invalid' : '' }}" 
                               id="reg-password" 
                               name="password" 
                               type="password" 
                               placeholder="Create a strong password" 
                               required 
                               autocomplete="new-password">
                        @if($errors->has('password'))
                            <span class="field-error">{{ $errors->first('password') }}</span>
                        @endif
                    </div>

                    <div class="checkbox-row">
                        <input type="checkbox" name="terms" id="terms" {{ old('terms') ? 'checked' : '' }} required>
                        <label for="terms">
                            I agree to the <a href="{{ route('terms.show') }}" target="_blank">Terms of Service</a> and <a href="{{ route('policy.show') }}" target="_blank">Privacy Policy</a>
                        </label>
                    </div>
                    @if($errors->has('terms'))
                        <span class="field-error" style="margin-top:-14px; margin-bottom:14px;">{{ $errors->first('terms') }}</span>
                    @endif

                    <button class="btn-submit" type="submit">Continue to Verification</button>

                    <!-- Dedicated Link to Login -->
                    <div class="switch-prompt">
                        Already have an account? 
                        <a href="{{ route('login') }}" class="switch-link">Sign in</a>
                    </div>
                </form>

            @elseif($activeStep === 'verify')
                <!-- ─── STEP 2: EMAIL 6-DIGIT CODE VERIFICATION ─── -->
                <div class="steps-bar">
                    <div class="step-pill active"></div>
                    <div class="step-pill active"></div>
                    <div class="step-pill"></div>
                </div>
                <div class="steps-labels">
                    <span style="color:var(--primary)">1. Details</span>
                    <span style="color:var(--primary)">2. Verify</span>
                    <span>3. Done</span>
                </div>

                <div class="form-header">
                    <h1>Check Your Email</h1>
                    <p>
                        We sent a 6-digit verification code to <strong style="color:var(--ink)">{{ $pendingEmail ?: 'your email address' }}</strong>. Enter it below to activate your account.
                    </p>
                </div>

                @if(session('status'))
                    <div class="alert-box alert-success">{{ session('status') }}</div>
                @endif

                @if($errors->has('code'))
                    <div class="alert-box alert-error">{{ $errors->first('code') }}</div>
                @endif

                <form method="POST" action="{{ route('auth.verify.code') }}" id="verify-form">
                    @csrf
                    <input type="hidden" name="email" value="{{ $pendingEmail }}">
                    <input type="hidden" name="code" id="verify-hidden-code" value="">

                    <div class="otp-inputs">
                        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 1" autofocus>
                        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2">
                        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3">
                        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4">
                        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 5">
                        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 6">
                    </div>

                    <button class="btn-submit" type="submit">Verify & Activate Account</button>
                </form>

                <div class="resend-text">
                    Did not receive the email? 
                    <form method="POST" action="{{ route('auth.verify.resend') }}" style="display:inline;">
                        @csrf
                        <input type="hidden" name="email" value="{{ $pendingEmail }}">
                        <button type="submit" class="btn-link">Resend code</button>
                    </form>
                </div>

            @else
                <!-- ─── STEP 3: SUCCESS & ALL SET ─── -->
                <div class="steps-bar">
                    <div class="step-pill active"></div>
                    <div class="step-pill active"></div>
                    <div class="step-pill active"></div>
                </div>

                <div style="text-align:center; padding: 20px 0;">
                    <div class="success-icon">✓</div>
                    <h1 style="font-size:28px; font-weight:800; color:var(--ink); margin-bottom:8px;">You Are All Set!</h1>
                    <p style="font-size:15px; color:var(--secondary); margin-bottom:28px;">
                        Your email address is verified and your HSmart ISP account is active.
                    </p>

                    <a href="{{ route('dashboard') }}" class="btn-submit" style="text-decoration:none;">Go to Dashboard</a>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    // 6-box OTP auto-advance & paste handler
    var otpInputs = [].slice.call(document.querySelectorAll('.otp-box'));
    function syncOtp() {
        var code = otpInputs.map(function(b){ return b.value || ''; }).join('');
        var hiddenInput = document.getElementById('verify-hidden-code');
        if (hiddenInput) hiddenInput.value = code;
    }

    otpInputs.forEach(function(box, idx) {
        box.addEventListener('input', function() {
            box.value = box.value.replace(/\D/g, '').slice(0, 1);
            syncOtp();
            if (box.value && otpInputs[idx + 1]) {
                otpInputs[idx + 1].focus();
            }
        });
        box.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !box.value && otpInputs[idx - 1]) {
                otpInputs[idx - 1].focus();
            }
        });
        box.addEventListener('paste', function(e) {
            var data = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            if (!data) return;
            e.preventDefault();
            data.split('').forEach(function(ch, j) {
                if (otpInputs[j]) otpInputs[j].value = ch;
            });
            syncOtp();
            if (otpInputs[Math.min(data.length, 5)]) {
                otpInputs[Math.min(data.length, 5)].focus();
            }
        });
    });

    var vForm = document.getElementById('verify-form');
    if (vForm) {
        vForm.addEventListener('submit', function() {
            syncOtp();
        });
    }
</script>
</body>
</html>
