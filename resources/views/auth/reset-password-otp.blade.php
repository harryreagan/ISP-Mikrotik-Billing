<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Enter Verification Code - HSmart ISP</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <style>
        *, ::after, ::before { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #0d1117;
            color: #E5E7EB;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #161b22;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 36px 32px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        .header { text-align: center; margin-bottom: 24px; }
        .logo-wrap { display: flex; justify-content: center; margin-bottom: 20px; }
        h1 { font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 8px; }
        p.subtitle { font-size: 14px; color: #9CA3AF; line-height: 1.5; }
        .phone-badge {
            display: inline-block;
            background: rgba(249, 115, 22, 0.15);
            color: #fb923c;
            border: 1px solid rgba(249, 115, 22, 0.3);
            border-radius: 8px;
            padding: 4px 12px;
            font-weight: 600;
            font-size: 13px;
            margin-top: 6px;
        }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #D1D5DB; margin-bottom: 6px; }
        input[type="text"], input[type="password"] {
            width: 100%;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            color: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
        }
        .otp-input {
            letter-spacing: 6px;
            font-size: 20px;
            text-align: center;
            font-weight: 700;
        }
        .btn-submit {
            width: 100%;
            background: #ea580c;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .btn-submit:hover { background: #c2410c; }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.4;
        }
        .alert-success { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .footer-links { text-align: center; margin-top: 20px; font-size: 13px; color: #9CA3AF; }
        .footer-links a { color: #f97316; text-decoration: none; font-weight: 600; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <div class="logo-wrap">
            <x-hsmart-logo color="light" height="38px" />
        </div>
        <h1>Enter Verification Code</h1>
        <p class="subtitle">We have dispatched a 6-digit SMS OTP reset code to:</p>
        <div class="phone-badge">{{ substr($phone, 0, 4) . '****' . substr($phone, -3) }}</div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.reset.otp.submit') }}">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}">

        <div class="form-group">
            <label for="code">6-Digit SMS Verification Code</label>
            <input type="text" id="code" name="code" class="otp-input" maxlength="6" inputmode="numeric" placeholder="••••••" required autofocus autocomplete="one-time-code">
        </div>

        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" minlength="8" placeholder="At least 8 characters" required>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" placeholder="Confirm your new password" required>
        </div>

        <button type="submit" class="btn-submit">Reset Password &amp; Sign In</button>
    </form>

    <div class="footer-links">
        Didn't receive code? <a href="{{ route('password.request') }}">Try another number or email</a>
    </div>
</div>

</body>
</html>

