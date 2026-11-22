@php
    $siteName = siteUrlSettings('site_name') ?? 'HSmart WiFi Billing';
    $siteLogo = siteUrlSettings('site_logo');

    // Determine initial view tab:
    // If validation error in register fields -> 'details'
    // If validation error in verify code or session has pending verification or step=verify -> 'verify'
    // If step=done or session('verification_completed') -> 'done'
    // Otherwise use $initialTab or query 'tab'
    $activeTab = session('active_tab') ?? request('tab') ?? $initialTab ?? 'signin';
    if ($errors->has('code') || session('pending_verification_email') && request('tab') === 'verify') {
        $activeTab = 'verify';
    } elseif ($errors->has('name') || $errors->has('business_name') || $errors->has('mobile') || $errors->has('terms') || ($errors->has('email') && request('tab') === 'details')) {
        $activeTab = 'details';
    } elseif (request('tab') === 'done' || session('verification_completed')) {
        $activeTab = 'done';
    }

    $pendingEmail = session('pending_verification_email', old('email', ''));
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in or create an account – {{ $siteName }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{margin:0}a{text-decoration:none}
.lg{font-family:'Jost','Inter',sans-serif;min-height:860px;background:#FAFAF8;display:flex;align-items:center;justify-content:center;padding:32px;box-sizing:border-box;color:#111}
.bx{display:flex;width:100%;max-width:1040px;min-height:700px;background:#fff;border:1px solid #E6E3F5;border-radius:28px;overflow:hidden;box-shadow:0 30px 70px rgba(22,27,46,.12);animation:up .8s both}
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
.rp{flex:1 1 54%;padding:44px 52px;display:flex;flex-direction:column;justify-content:center;gap:18px}
.rp h1{margin:0;font-size:38px;line-height:1.1;font-weight:800}
.rp .sub{margin:0;color:#666;font-size:16px}
.lb{display:block;font-weight:600;font-size:14px;margin-bottom:8px}
.in{height:54px;border:1px solid #D9D6EC;border-radius:14px;padding:0 18px;font:inherit;font-size:16px;width:100%;box-sizing:border-box;background:#fff;transition:border-color .2s, outline .2s}
.in:focus{outline:3px solid rgba(109,63,224,.25);border-color:#6D3FE0}
.in.is-invalid{border-color:#C62828;background-color:#FFF8F8}
.err-msg{display:block;color:#C62828;font-size:13px;font-weight:600;margin-top:6px}
.alert-box{padding:12px 16px;border-radius:12px;font-size:14px;line-height:1.4;margin-bottom:8px}
.alert-error{background:#FFEBEE;border:1px solid #FFCDD2;color:#C62828}
.alert-success{background:#E8F5E9;border:1px solid #C8E6C9;color:#2E7D32}
.rw{display:flex;justify-content:space-between;align-items:center;font-size:14px;gap:12px;flex-wrap:wrap}
.rw label{display:flex;gap:8px;align-items:center;cursor:pointer}
.pl{color:#6D3FE0;font-weight:600}
.sb{height:54px;border-radius:14px;background:#F47B06;color:#111;font:inherit;font-weight:800;font-size:17px;border:0;width:100%;cursor:pointer;transition:transform .2s,box-shadow .2s;display:flex;align-items:center;justify-content:center;text-decoration:none;box-sizing:border-box}
.sb:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(244,123,6,.35)}
.ft{text-align:center;color:#666;font-size:15px}
@keyframes up{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
@keyframes float{50%{transform:translateY(-10px)}}
@media (max-width:860px){.lp{display:none}.rp{padding:40px 24px}}
@media (prefers-reduced-motion:reduce){*{animation:none!important}}

[hidden]{display:none!important}
.two{display:flex;gap:14px;flex-wrap:wrap}.two>div{flex:1 1 180px}
.tb{display:flex;background:#F3F2FA;border-radius:999px;padding:4px;align-self:flex-start}
.tb a{padding:10px 24px;border-radius:999px;font-weight:700;font-size:15px;color:#444;text-decoration:none;transition:all .2s ease}
.tb a.on{background:#111;color:#fff}
.sp{display:flex;gap:8px}.sp i{flex:1;height:6px;border-radius:3px;background:#E6E3F5;display:block}.sp i.on{background:#F47B06}
.sl{display:flex;justify-content:space-between;font-size:12px;color:#777;letter-spacing:.06em;text-transform:uppercase;margin-top:-8px}
.gb{height:54px;border-radius:14px;border:1px solid #D9D6EC;background:#fff;display:flex;align-items:center;justify-content:center;gap:12px;font:inherit;font-weight:700;font-size:16px;width:100%;cursor:pointer;color:#111;text-decoration:none;box-sizing:border-box;transition:box-shadow .2s}
.gb:hover{box-shadow:0 8px 20px rgba(0,0,0,.1)}
.gb svg{width:22px;height:22px}
.or{display:flex;align-items:center;gap:14px;color:#888;font-size:14px}.or:before,.or:after{content:"";flex:1;height:1px;background:#E6E3F5}
.ots{display:flex;gap:10px;justify-content:space-between}.ot{width:100%;max-width:60px;height:64px;border:1px solid #D9D6EC;border-radius:14px;text-align:center;font:inherit;font-size:26px;font-weight:700;box-sizing:border-box}.ot:focus{outline:3px solid rgba(109,63,224,.25);border-color:#6D3FE0}
.bk{font-weight:600;font-size:14px;color:#6D3FE0;align-self:flex-start;cursor:pointer}
.bo{height:54px;border-radius:14px;border:2px solid #111;background:#fff;color:#111;font:inherit;font-weight:800;font-size:17px;width:100%;cursor:pointer;display:flex;align-items:center;justify-content:center;text-decoration:none;box-sizing:border-box}
.big{width:76px;height:76px;border-radius:50%;background:#10B981;color:#fff;display:flex;align-items:center;justify-content:center;font-size:38px;font-weight:800;margin:0 auto;animation:pop .6s both}
@keyframes pop{from{transform:scale(.4);opacity:0}to{transform:none;opacity:1}}
</style>
</head>
<body>
<div class="lg">
  <div class="bx">

    <!-- ─── LEFT PANEL ─── -->
    <div class="lp">
      <div style="margin-bottom: 24px;">
        <x-hsmart-logo color="light" height="40px" />
      </div>
      <div>
        <h2>Run your WiFi business from one dashboard.</h2>
        <p>Customers, packages, routers and M-Pesa payments, all in one place.</p>
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

    <!-- ─── RIGHT PANEL 1: SIGN IN ─── -->
    <div class="rp" id="v-signin" {{ $activeTab === 'signin' ? '' : 'hidden' }}>
      <div class="tb">
        <a href="{{ route('login', ['tab' => 'signin']) }}" class="on" data-go="signin">Sign in</a>
        <a href="{{ route('login', ['tab' => 'details']) }}" class="" data-go="details">Sign up</a>
      </div>

      <div>
        <h1>Welcome back</h1>
        <p class="sub" style="margin-top:10px">Sign in to manage your network and payments.</p>
      </div>

      @if(session('status'))
        <div class="alert-box alert-success">{{ session('status') }}</div>
      @endif

      @if($errors->has('email') && $activeTab === 'signin')
        <div class="alert-box alert-error">{{ $errors->first('email') }}</div>
      @endif

      <!-- Google Sign In -->
      <a href="{{ route('auth.google') }}" class="gb">
        <svg viewBox="0 0 48 48" aria-hidden="true">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
        </svg>
        Continue with Google
      </a>

      <div class="or">or sign in with email</div>

      <form method="POST" action="{{ route('login') }}" style="display:flex;flex-direction:column;gap:18px;">
        @csrf
        <div>
          <label class="lb" for="si-u">Email or username</label>
          <input class="in {{ $errors->has('email') && $activeTab === 'signin' ? 'is-invalid' : '' }}" id="si-u" name="email" type="text" placeholder="you@example.com" value="{{ old('email') }}" required autocomplete="username">
          @if($errors->has('email') && $activeTab === 'signin')
            <span class="err-msg">{{ $errors->first('email') }}</span>
          @endif
        </div>

        <div>
          <label class="lb" for="si-p">Password</label>
          <input class="in {{ $errors->has('password') && $activeTab === 'signin' ? 'is-invalid' : '' }}" id="si-p" name="password" type="password" placeholder="Enter your password" required autocomplete="current-password">
          @if($errors->has('password') && $activeTab === 'signin')
            <span class="err-msg">{{ $errors->first('password') }}</span>
          @endif
        </div>

        <div class="rw">
          <label><input type="checkbox" name="remember" id="remember_me"> Remember me</label>
          <a href="{{ route('password.request') }}" class="pl">Forgot your password?</a>
        </div>

        <button class="sb" type="submit">Sign in</button>
      </form>
    </div>

    <!-- ─── RIGHT PANEL 2: SIGN UP (STEP 1 OF 3) ─── -->
    <div class="rp" id="v-details" {{ $activeTab === 'details' ? '' : 'hidden' }}>
      <div class="tb">
        <a href="{{ route('login', ['tab' => 'signin']) }}" class="" data-go="signin">Sign in</a>
        <a href="{{ route('login', ['tab' => 'details']) }}" class="on" data-go="details">Sign up</a>
      </div>

      <div>
        <div class="sp"><i class="on"></i><i class=""></i><i class=""></i></div>
      </div>
      <div class="sl"><span>Details</span><span>Verify</span><span>Done</span></div>

      <div>
        <h1>Create your account</h1>
        <p class="sub" style="margin-top:8px">Step 1 of 3 · Your details</p>
      </div>

      <!-- Google Sign Up -->
      <a href="{{ route('auth.google') }}" class="gb">
        <svg viewBox="0 0 48 48" aria-hidden="true">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
        </svg>
        Sign up with Google
      </a>

      <div class="or">or use your email</div>

      <form method="POST" action="{{ route('auth.register.step1') }}" style="display:flex;flex-direction:column;gap:16px;">
        @csrf
        <div class="two">
          <div>
            <label class="lb" for="su-n">Full name</label>
            <input class="in {{ $errors->has('name') ? 'is-invalid' : '' }}" id="su-n" name="name" type="text" placeholder="Your name" value="{{ old('name') }}" required autocomplete="name">
            @if($errors->has('name'))
              <span class="err-msg">{{ $errors->first('name') }}</span>
            @endif
          </div>
          <div>
            <label class="lb" for="su-b">Business name</label>
            <input class="in {{ $errors->has('business_name') ? 'is-invalid' : '' }}" id="su-b" name="business_name" type="text" placeholder="Your ISP or WiFi business" value="{{ old('business_name') }}">
            @if($errors->has('business_name'))
              <span class="err-msg">{{ $errors->first('business_name') }}</span>
            @endif
          </div>
        </div>

        <div class="two">
          <div>
            <label class="lb" for="su-ph">Phone number</label>
            <input class="in {{ $errors->has('mobile') ? 'is-invalid' : '' }}" id="su-ph" name="mobile" type="tel" placeholder="0712 345 678" value="{{ old('mobile') }}" autocomplete="tel">
            @if($errors->has('mobile'))
              <span class="err-msg">{{ $errors->first('mobile') }}</span>
            @endif
          </div>
          <div>
            <label class="lb" for="su-e">Email</label>
            <input class="in {{ $errors->has('email') && $activeTab === 'details' ? 'is-invalid' : '' }}" id="su-e" name="email" type="email" placeholder="you@example.com" value="{{ old('email') }}" required autocomplete="email">
            @if($errors->has('email') && $activeTab === 'details')
              <span class="err-msg">{{ $errors->first('email') }}</span>
            @endif
          </div>
        </div>

        <div>
          <label class="lb" for="su-p">Password</label>
          <input class="in {{ $errors->has('password') && $activeTab === 'details' ? 'is-invalid' : '' }}" id="su-p" name="password" type="password" placeholder="Create a password (min 8 characters)" required autocomplete="new-password">
          @if($errors->has('password') && $activeTab === 'details')
            <span class="err-msg">{{ $errors->first('password') }}</span>
          @endif
        </div>

        <div class="rw">
          <label>
            <input type="checkbox" name="terms" id="terms" {{ old('terms') ? 'checked' : '' }} required>
            I agree to the <a href="{{ route('terms.show') }}" target="_blank" class="pl">Terms</a> and <a href="{{ route('policy.show') }}" target="_blank" class="pl">Privacy Policy</a>
          </label>
          @if($errors->has('terms'))
            <span class="err-msg" style="width:100%;">{{ $errors->first('terms') }}</span>
          @endif
        </div>

        <button class="sb" type="submit">Continue</button>
        <div class="ft" style="font-size:14px">We will email you a 6-digit code to verify your address.</div>
      </form>
    </div>

    <!-- ─── RIGHT PANEL 3: VERIFY EMAIL (STEP 2 OF 3) ─── -->
    <div class="rp" id="v-verify" {{ $activeTab === 'verify' ? '' : 'hidden' }}>
      <div>
        <div class="sp"><i class="on"></i><i class="on"></i><i class=""></i></div>
      </div>
      <div class="sl"><span>Details</span><span>Verify</span><span>Done</span></div>

      <a href="#" class="bk" data-go="details">← Change details</a>

      <div>
        <h1>Check your email</h1>
        <p class="sub" style="margin-top:10px">
          We sent a 6-digit code to <b style="color:#111" id="display-verify-email">{{ $pendingEmail ?: 'your email address' }}</b>. Enter it below to verify your account.
        </p>
      </div>

      @if(session('status'))
        <div class="alert-box alert-success">{{ session('status') }}</div>
      @endif

      @if($errors->has('code'))
        <div class="alert-box alert-error">{{ $errors->first('code') }}</div>
      @endif

      <form method="POST" action="{{ route('auth.verify.code') }}" id="verify-form" style="display:flex;flex-direction:column;gap:20px;">
        @csrf
        <input type="hidden" name="email" id="verify-email-input" value="{{ $pendingEmail }}">
        <input type="hidden" name="code" id="verify-hidden-code" value="">

        <div class="ots">
          <input class="ot" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 1" autocomplete="one-time-code" autofocus>
          <input class="ot" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2">
          <input class="ot" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3">
          <input class="ot" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4">
          <input class="ot" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 5">
          <input class="ot" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 6">
        </div>

        <button class="sb" type="submit">Verify and continue</button>
      </form>

      <div class="ft">
        Did not get it? 
        <form method="POST" action="{{ route('auth.verify.resend') }}" style="display:inline;" id="resend-form">
          @csrf
          <input type="hidden" name="email" value="{{ $pendingEmail }}">
          <button type="submit" style="background:none;border:none;padding:0;color:#6D3FE0;font-weight:600;font-family:inherit;font-size:inherit;cursor:pointer;text-decoration:underline;">Resend code</button>
        </form>
        · Check your spam folder
      </div>
    </div>

    <!-- ─── RIGHT PANEL 4: ALL SET (STEP 3 OF 3) ─── -->
    <div class="rp" id="v-done" {{ $activeTab === 'done' ? '' : 'hidden' }}>
      <div>
        <div class="sp"><i class="on"></i><i class="on"></i><i class="on"></i></div>
      </div>
      <div class="sl"><span>Details</span><span>Verify</span><span>Done</span></div>

      <div class="big">✓</div>

      <div style="text-align:center;">
        <h1>You are all set</h1>
        <p class="sub" style="margin-top:10px">Your email is verified and your account is ready.</p>
      </div>

      <div style="display:flex;flex-direction:column;gap:14px;margin-top:10px;">
        <a href="{{ route('dashboard') }}" class="sb">Go to dashboard</a>
        <a href="{{ route('mikrotik-sync') }}" class="bo">Connect your first router</a>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  // Navigation between tab panels
  function go(v){
    document.querySelectorAll('.rp').forEach(function(e){ e.hidden = (e.id !== 'v-' + v); });
    window.scrollTo(0,0);
    // Update URL parameter without reload
    if(window.history && window.history.replaceState){
      var url = new URL(window.location);
      url.searchParams.set('tab', v);
      window.history.replaceState({}, '', url);
    }
  }

  document.addEventListener('click', function(e){
    var t = e.target.closest('[data-go]');
    if(t){
      e.preventDefault();
      go(t.getAttribute('data-go'));
    }
  });

  // 6-digit code box handling (auto-advance, backspace, paste)
  var o = [].slice.call(document.querySelectorAll('.ot'));
  function syncCode(){
    var code = o.map(function(b){ return b.value || ''; }).join('');
    var hiddenInput = document.getElementById('verify-hidden-code');
    if(hiddenInput) hiddenInput.value = code;
  }

  o.forEach(function(b, i){
    b.addEventListener('input', function(){
      b.value = b.value.replace(/\D/g, '').slice(0, 1);
      syncCode();
      if(b.value && o[i + 1]) o[i + 1].focus();
    });
    b.addEventListener('keydown', function(e){
      if(e.key === 'Backspace' && !b.value && o[i - 1]){
        o[i - 1].focus();
      }
    });
    b.addEventListener('paste', function(e){
      var d = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if(!d) return;
      e.preventDefault();
      d.split('').forEach(function(ch, j){
        if(o[j]) o[j].value = ch;
      });
      syncCode();
      if(o[Math.min(d.length, 5)]) o[Math.min(d.length, 5)].focus();
    });
  });

  // Form submit sync for verify-form
  var vForm = document.getElementById('verify-form');
  if(vForm){
    vForm.addEventListener('submit', function(){
      syncCode();
    });
  }
})();
</script>
</body>
</html>

