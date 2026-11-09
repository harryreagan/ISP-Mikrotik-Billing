@php
    $siteName = siteUrlSettings('site_name') ?? 'HSmart WiFi Billing';
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set New Password – {{ $siteName }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{margin:0}a{text-decoration:none}
.lg{font-family:'Jost','Inter',sans-serif;min-height:860px;background:#FAFAF8;display:flex;align-items:center;justify-content:center;padding:32px;box-sizing:border-box;color:#111}
.bx{display:flex;width:100%;max-width:1040px;min-height:650px;background:#fff;border:1px solid #E6E3F5;border-radius:28px;overflow:hidden;box-shadow:0 30px 70px rgba(22,27,46,.12);animation:up .8s both}
.lp{flex:1 1 46%;background:linear-gradient(160deg,#161B2E,#2B2170);color:#fff;padding:44px;display:flex;flex-direction:column;justify-content:space-between;gap:28px}
.logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px}
.lm{width:34px;height:34px;border-radius:9px;background:#F47B06;display:inline-block}
.lp h2{margin:0;font-size:36px;line-height:1.1;font-weight:700}
.lp p{margin:12px 0 0;color:#C9C6E8;line-height:1.6;font-size:16px}
.tl{border-radius:14px;padding:14px 16px;color:#fff;background:linear-gradient(135deg,#7C3AED,#5B34E6);max-width:240px}
.tl span{font-size:11px;letter-spacing:.08em;opacity:.85}.tl b{display:block;font-size:26px}
.fl{background:#fff;color:#111;border-radius:16px;padding:14px 18px;display:flex;gap:12px;align-items:center;max-width:290px;box-shadow:0 18px 40px rgba(0,0,0,.25);animation:float 4s ease-in-out infinite}
.fl b{display:block;font-size:14px}.fl span{font-size:12px;color:#666}
.ok{width:34px;height:34px;border-radius:50%;background:#10B981;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800}
.rp{flex:1 1 54%;padding:44px 52px;display:flex;flex-direction:column;justify-content:center;gap:20px}
.rp h1{margin:0;font-size:36px;line-height:1.1;font-weight:800}
.rp .sub{margin:0;color:#666;font-size:16px;line-height:1.5}
.lb{display:block;font-weight:600;font-size:14px;margin-bottom:8px}
.in{height:54px;border:1px solid #D9D6EC;border-radius:14px;padding:0 18px;font:inherit;font-size:16px;width:100%;box-sizing:border-box;background:#fff}
.in:focus{outline:3px solid rgba(109,63,224,.25);border-color:#6D3FE0}
.in.is-invalid{border-color:#C62828;background-color:#FFF8F8}
.err-msg{display:block;color:#C62828;font-size:13px;font-weight:600;margin-top:6px}
.alert-box{padding:12px 16px;border-radius:12px;font-size:14px;line-height:1.4}
.alert-error{background:#FFEBEE;border:1px solid #FFCDD2;color:#C62828}
.alert-success{background:#E8F5E9;border:1px solid #C8E6C9;color:#2E7D32}
.sb{height:54px;border-radius:14px;background:#F47B06;color:#111;font:inherit;font-weight:800;font-size:17px;border:0;width:100%;cursor:pointer;transition:transform .2s,box-shadow .2s;display:flex;align-items:center;justify-content:center}
.sb:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(244,123,6,.35)}
.bk{font-weight:600;font-size:14px;color:#6D3FE0;align-self:flex-start}
@keyframes up{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
@keyframes float{50%{transform:translateY(-10px)}}
@media (max-width:860px){.lp{display:none}.rp{padding:40px 24px}}
</style>
</head>
<body>
<div class="lg">
  <div class="bx">
    <div class="lp">
      <div class="logo">
        <span class="lm"></span>
        {{ $siteName }}
      </div>
      <div>
        <h2>Security Credentials</h2>
        <p>Set a strong password to protect your network operations.</p>
      </div>
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="tl">
          <span>ACTIVE USERS</span>
          <b>482</b>
        </div>
        <div class="fl">
          <span class="ok">✓</span>
          <div>
            <b>M-Pesa payment received</b>
            <span>KES 50 · customer connected</span>
          </div>
        </div>
      </div>
    </div>

    <div class="rp">
      <a href="{{ route('login') }}" class="bk">← Back to Sign in</a>

      <div>
        <h1>Set new password</h1>
        <p class="sub" style="margin-top:8px">Please enter your email and choose a new secure password.</p>
      </div>

      @if(session('status'))
        <div class="alert-box alert-success">{{ session('status') }}</div>
      @endif

      <form method="POST" action="{{ route('password.update') }}" style="display:flex;flex-direction:column;gap:18px;">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
          <label class="lb" for="email">Email address</label>
          <input class="in {{ $errors->has('email') ? 'is-invalid' : '' }}" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus placeholder="you@example.com" autocomplete="username">
          @if($errors->has('email'))
            <span class="err-msg">{{ $errors->first('email') }}</span>
          @endif
        </div>

        <div>
          <label class="lb" for="password">New password</label>
          <input class="in {{ $errors->has('password') ? 'is-invalid' : '' }}" id="password" type="password" name="password" required autocomplete="new-password" placeholder="Create a new password (min 8 characters)">
          @if($errors->has('password'))
            <span class="err-msg">{{ $errors->first('password') }}</span>
          @endif
        </div>

        <div>
          <label class="lb" for="password_confirmation">Confirm new password</label>
          <input class="in" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm your new password">
        </div>

        <button type="submit" class="sb">Update Password &amp; Sign In</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
