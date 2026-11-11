<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class CustomAuthController extends Controller
{
    protected EmailVerificationService $verificationService;

    public function __construct(EmailVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Handle forgot password submission for Email OR Phone Number.
     */
    public function handleForgotPassword(Request $request)
    {
        $input = trim((string) $request->input('identity', $request->input('email', '')));
        if (empty($input)) {
            return back()->withErrors(['identity' => 'Please enter your email address or phone number.'])->withInput();
        }

        $isEmail = str_contains($input, '@');
        $normalizedPhone = \App\Services\Auth\OtpVerificationService::normalizeKenyanPhone($input);

        // 1. Phone number entered: dispatch SMS OTP and redirect to OTP reset screen
        if (!$isEmail && $normalizedPhone) {
            $user = User::where('phone', $normalizedPhone)
                ->orWhere('mobile', $normalizedPhone)
                ->orWhere('phone', $input)
                ->orWhere('mobile', $input)
                ->first();

            if (!$user) {
                return back()->withErrors(['identity' => 'We cannot find an account associated with that mobile number.'])->withInput();
            }

            $res = \App\Services\Auth\OtpVerificationService::generateAndSendOtp($normalizedPhone, 'password_reset', $user->tenant_id, $request->ip());

            if (in_array($res['status'], ['rate_limited', 'cooldown'])) {
                return back()->withErrors(['identity' => $res['message']])->withInput();
            }

            session([
                'password_reset_phone'     => $normalizedPhone,
                'password_reset_challenge' => $res['challenge_id'] ?? null,
            ]);

            return redirect()->route('password.reset.otp')->with('status', $res['message']);
        }

        // 2. Email address entered: clean stale tokens and send reset link
        $email = strtolower($input);
        $user = User::where('email', $email)->first();
        if (!$user) {
            return back()->withErrors(['identity' => 'We cannot find an account with that email address.'])->withInput();
        }

        // Always delete any existing token to prevent SQL duplicate key 1062 errors
        try {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $email)->delete();
        } catch (\Throwable $e) {}

        try {
            $status = \Illuminate\Support\Facades\Password::broker()->sendResetLink(['email' => $email]);
            if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
                return back()->with('status', __($status));
            }
            return back()->withErrors(['identity' => __($status)])->withInput();
        } catch (\Throwable $e) {
            Log::error("[ForgotPassword] Error dispatching reset link: " . $e->getMessage());
            return back()->withErrors(['identity' => 'Unable to send password reset email. Please try again.'])->withInput();
        }
    }

    /**
     * Show Phone SMS OTP Password Reset screen.
     */
    public function showPasswordResetOtpForm()
    {
        $phone = session('password_reset_phone');
        if (empty($phone)) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password-otp', compact('phone'));
    }

    /**
     * Process Phone SMS OTP Password Reset.
     */
    public function handlePasswordResetOtp(Request $request)
    {
        $request->validate([
            'phone'                 => 'required|string',
            'code'                  => 'required|string|size:6',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $phone = \App\Services\Auth\OtpVerificationService::normalizeKenyanPhone($request->phone);
        $code = trim((string) $request->code);

        $verify = \App\Services\Auth\OtpVerificationService::verifyOtp($phone, $code, 'password_reset');

        if ($verify['status'] !== 'verified') {
            return back()->withErrors(['code' => $verify['message']])->withInput();
        }

        $user = User::where('phone', $phone)->orWhere('mobile', $phone)->first();
        if (!$user) {
            return redirect()->route('password.request')->withErrors(['identity' => 'User account not found.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        session()->forget(['password_reset_phone', 'password_reset_challenge']);

        return redirect()->route('login')->with('status', 'Your password has been successfully reset! You can now sign in.');
    }

    /**
     * Show combined auth page.
     */
    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        if ($request->routeIs('register')) {
            return view('auth.register');
        }

        return view('auth.login');
    }

    /**
     * Step 1: Validate sign-up details, create unverified user, and send 6-digit code.
     */
    public function registerStep1(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:25'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'You must agree to the Terms of Service and Privacy Policy to continue.',
        ]);

        $email = strtolower(trim($request->email));

        // Check if account already exists and is verified
        $existing = User::where('email', $email)->first();
        if ($existing && $existing->email_verified_at) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'This email address is already registered. Please sign in instead.'])
                ->with('active_tab', 'details');
        }

        // Create or update in UNVERIFIED state
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $request->name,
                'mobile' => $request->mobile,
                'password' => Hash::make($request->password),
                'email_verified_at' => null,
            ]
        );

        // Ensure personal team exists
        if (class_exists(Team::class) && \Illuminate\Support\Facades\Schema::hasTable('teams')) {
            try {
                if (method_exists($user, 'personalTeam') && !$user->personalTeam()) {
                    $teamName = $request->business_name ?: explode(' ', $user->name, 2)[0] . "'s Team";
                    $team = Team::forceCreate([
                        'user_id' => $user->id,
                        'name' => $teamName,
                        'personal_team' => true,
                    ]);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'current_team_id')) {
                        $user->current_team_id = $team->id;
                        $user->save();
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Personal team creation skipped: " . $e->getMessage());
            }
        }

        // Assign default Admin role if Spatie roles exist
        if (Role::where('name', 'Admin')->exists() && !$user->hasAnyRole(Role::all())) {
            $user->assignRole('Admin');
        }

        // Generate and send 6-digit code via Email and real SMS
        $result = $this->verificationService->generateAndSendCode($email, $request->ip(), $user->mobile);

        session([
            'pending_verification_email' => $email,
            'signup_business_name' => $request->business_name,
        ]);

        return redirect()->route('register', ['tab' => 'verify'])
            ->with('status', $result['message']);
    }

    /**
     * Step 2: Verify 6-digit email verification code.
     */
    public function verifyCode(Request $request)
    {
        $email = strtolower(trim($request->input('email', session('pending_verification_email', ''))));

        if (empty($email)) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Session expired. Please enter your details again.']);
        }

        // Collect code digits (either from single hidden field or array of 6 digit boxes)
        $code = $request->input('code');
        if (is_array($code)) {
            $code = implode('', $code);
        }
        $code = preg_replace('/\D/', '', (string) $code);

        if (strlen($code) !== 6) {
            return back()
                ->withInput()
                ->withErrors(['code' => 'Please enter all 6 digits of your verification code.'])
                ->with('active_tab', 'verify');
        }

        $result = $this->verificationService->verifyCode($email, $code);

        if ($result['status'] !== 'success') {
            return back()
                ->withInput()
                ->withErrors(['code' => $result['message']])
                ->with('active_tab', 'verify');
        }

        // Log the user in
        $user = $result['user'] ?? User::where('email', $email)->first();
        if ($user) {
            // Ensure tenant exists and is awarded 20 free SMS credits
            if (empty($user->tenant_id)) {
                try {
                    $bizName = session('signup_business_name') ?: (($user->name ?: 'ISP') . ' Networks');
                    $baseSlug = \Illuminate\Support\Str::slug($bizName);
                    $subdomain = $baseSlug . '-' . $user->id;
                    $billingCode = strtoupper(\Illuminate\Support\Str::random(6));

                    $tenantId = \Illuminate\Support\Facades\DB::table('tenants')->insertGetId([
                        'name'         => $bizName,
                        'subdomain'    => $subdomain,
                        'billing_code' => $billingCode,
                        'country'      => 'Kenya',
                        'county'       => 'Nairobi',
                        'is_active'    => 1,
                        'sms_balance'  => 20.00,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    \Illuminate\Support\Facades\DB::table('sms_wallets')->insert([
                        'tenant_id'             => $tenantId,
                        'balance'               => 20.00,
                        'low_balance_threshold' => 50.00,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);

                    $user->tenant_id = $tenantId;
                    $user->save();
                } catch (\Throwable $e) {
                    Log::warning("Tenant setup failed for registered user {$user->id}: " . $e->getMessage());
                }
            }
            Auth::login($user, true);
        }

        session()->forget('pending_verification_email');
        session(['verification_completed' => true]);

        return redirect()->route('register', ['tab' => 'done']);
    }

    /**
     * Resend verification code.
     */
    public function resendCode(Request $request)
    {
        $email = strtolower(trim($request->input('email', session('pending_verification_email', ''))));

        if (empty($email)) {
            return back()
                ->withErrors(['code' => 'Please provide your email address to resend the code.'])
                ->with('active_tab', 'verify');
        }

        $user = User::where('email', $email)->first();
        $result = $this->verificationService->generateAndSendCode($email, $request->ip(), $user?->mobile);

        if (in_array($result['status'], ['cooldown', 'rate_limited'])) {
            return back()
                ->withErrors(['code' => $result['message']])
                ->with('active_tab', 'verify');
        }

        return back()
            ->with('status', $result['message'])
            ->with('active_tab', 'verify');
    }

    /**
     * Redirect to Google OAuth provider.
     */
    public function redirectToGoogle()
    {
        if (empty(config('services.google.client_id')) || empty(config('services.google.client_secret'))) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google Sign-In is not yet configured. Please sign in with your email and password.']);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle callback from Google OAuth provider.
     */
    public function handleGoogleCallback(Request $request)
    {
        if ($request->has('error') || $request->has('denied')) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google sign-in was cancelled or declined. Please try again.']);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed: ' . $e->getMessage());
            return redirect()->route('login')
                ->withErrors(['email' => 'Unable to sign in with Google at this time. Please try again or use your password.']);
        }

        $email = strtolower(trim($googleUser->getEmail() ?? ''));
        if (empty($email)) {
            return redirect()->route('login')
                ->withErrors(['email' => 'No email address was returned by Google.']);
        }

        // Verify that Google marks the email as verified
        $raw = $googleUser->getRaw();
        if (isset($raw['email_verified']) && !$raw['email_verified']) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your Google email address is unverified by Google.']);
        }

        // 1. Existing user with this email
        $user = User::where('email', $email)->first();
        if ($user) {
            // Link Google ID if not set
            if (!$user->google_id) {
                $user->google_id = $googleUser->getId();
            }
            // If user was previously unverified, Google's verified status activates them
            if (is_null($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
            $user->save();

            Auth::login($user, true);
            return redirect()->intended(route('dashboard'));
        }

        // 2. New user: auto-create with verified email (skip code verification)
        $user = User::create([
            'name' => $googleUser->getName() ?: 'User',
            'email' => $email,
            'google_id' => $googleUser->getId(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(32)),
        ]);

        // Create personal team
        $team = Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0] . "'s Team",
            'personal_team' => true,
        ]);
        $user->current_team_id = $team->id;

        // Auto-provision Tenant with 20 SMS credits
        if (empty($user->tenant_id)) {
            try {
                $baseSlug = \Illuminate\Support\Str::slug($user->name);
                $subdomain = $baseSlug . '-' . $user->id;
                $billingCode = strtoupper(\Illuminate\Support\Str::random(6));

                $tenantId = \Illuminate\Support\Facades\DB::table('tenants')->insertGetId([
                    'name'         => $user->name . ' Networks',
                    'subdomain'    => $subdomain,
                    'billing_code' => $billingCode,
                    'country'      => 'Kenya',
                    'county'       => 'Nairobi',
                    'is_active'    => 1,
                    'sms_balance'  => 20.00,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                \Illuminate\Support\Facades\DB::table('sms_wallets')->insert([
                    'tenant_id'             => $tenantId,
                    'balance'               => 20.00,
                    'low_balance_threshold' => 50.00,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);

                $user->tenant_id = $tenantId;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Tenant setup failed for Google user: " . $e->getMessage());
            }
        }

        $user->save();

        // Assign default Admin role
        if (Role::where('name', 'Admin')->exists()) {
            $user->assignRole('Admin');
        }

        Auth::login($user, true);

        return redirect()->route('dashboard');
    }

    /**
     * Request 6-digit OTP for passwordless login (phone or email).
     */
    public function requestLoginOtp(Request $request)
    {
        $input = trim((string) $request->input('destination'));
        if (empty($input)) {
            return response()->json(['status' => 'error', 'message' => 'Please enter your phone number or email address.'], 422);
        }

        $isEmail = str_contains($input, '@');
        $normalizedPhone = \App\Services\Auth\OtpVerificationService::normalizeKenyanPhone($input);

        $user = User::where(function ($q) use ($input, $isEmail, $normalizedPhone) {
            if ($isEmail) {
                $q->where('email', strtolower($input));
            } else {
                $q->where('phone', $normalizedPhone)
                  ->orWhere('mobile', $normalizedPhone)
                  ->orWhere('phone', $input)
                  ->orWhere('mobile', $input);
            }
        })->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'No account was found for this phone or email.'], 404);
        }

        $dest = $isEmail ? $user->email : ($user->phone ?: $user->mobile);
        $result = \App\Services\Auth\OtpVerificationService::generateAndSendOtp($dest, 'login', $user->tenant_id, $request->ip());

        return response()->json($result);
    }

    /**
     * Sign in using 6-digit OTP code.
     */
    public function loginWithOtp(Request $request)
    {
        $input = trim((string) $request->input('destination'));
        $code = trim((string) $request->input('code'));

        if (empty($input) || empty($code)) {
            return response()->json(['status' => 'error', 'message' => 'Both destination and OTP code are required.'], 422);
        }

        $isEmail = str_contains($input, '@');
        $normalizedPhone = \App\Services\Auth\OtpVerificationService::normalizeKenyanPhone($input);

        $user = User::where(function ($q) use ($input, $isEmail, $normalizedPhone) {
            if ($isEmail) {
                $q->where('email', strtolower($input));
            } else {
                $q->where('phone', $normalizedPhone)
                  ->orWhere('mobile', $normalizedPhone)
                  ->orWhere('phone', $input)
                  ->orWhere('mobile', $input);
            }
        })->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Account not found.'], 404);
        }

        $dest = $isEmail ? $user->email : ($user->phone ?: $user->mobile);
        $verify = \App\Services\Auth\OtpVerificationService::verifyOtp($dest, $code, 'login');

        if ($verify['status'] !== 'verified') {
            return response()->json($verify, 422);
        }

        // Auto-verify phone/email if not yet marked
        if ($isEmail && is_null($user->email_verified_at)) {
            $user->email_verified_at = now();
            $user->save();
        } elseif (!$isEmail && is_null($user->phone_verified_at)) {
            $user->phone_verified_at = now();
            $user->save();
        }

        Auth::login($user, true);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful.',
            'redirect' => route('dashboard'),
        ]);
    }

    /**
     * Request Password Reset via SMS OTP or Email.
     */
    public function requestForgotPasswordOtp(Request $request)
    {
        $input = trim((string) $request->input('destination'));
        if (empty($input)) {
            return response()->json(['status' => 'error', 'message' => 'Please enter your phone number or email.'], 422);
        }

        $isEmail = str_contains($input, '@');
        $normalizedPhone = \App\Services\Auth\OtpVerificationService::normalizeKenyanPhone($input);

        $user = User::where(function ($q) use ($input, $isEmail, $normalizedPhone) {
            if ($isEmail) {
                $q->where('email', strtolower($input));
            } else {
                $q->where('phone', $normalizedPhone)
                  ->orWhere('mobile', $normalizedPhone);
            }
        })->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'No account associated with that number or email.'], 404);
        }

        $dest = $isEmail ? $user->email : ($user->phone ?: $user->mobile);
        $result = \App\Services\Auth\OtpVerificationService::generateAndSendOtp($dest, 'password_reset', $user->tenant_id, $request->ip());

        return response()->json($result);
    }

    /**
     * Reset password using verified OTP code.
     */
    public function resetPasswordWithOtp(Request $request)
    {
        $request->validate([
            'destination' => 'required|string',
            'code'        => 'required|string|size:6',
            'password'    => 'required|string|min:8|confirmed',
        ]);

        $input = trim((string) $request->input('destination'));
        $code = trim((string) $request->input('code'));
        $newPass = $request->input('password');

        $isEmail = str_contains($input, '@');
        $normalizedPhone = \App\Services\Auth\OtpVerificationService::normalizeKenyanPhone($input);

        $user = User::where(function ($q) use ($input, $isEmail, $normalizedPhone) {
            if ($isEmail) {
                $q->where('email', strtolower($input));
            } else {
                $q->where('phone', $normalizedPhone)
                  ->orWhere('mobile', $normalizedPhone);
            }
        })->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Account not found.'], 404);
        }

        $dest = $isEmail ? $user->email : ($user->phone ?: $user->mobile);
        $verify = \App\Services\Auth\OtpVerificationService::verifyOtp($dest, $code, 'password_reset');

        if ($verify['status'] !== 'verified') {
            return response()->json($verify, 422);
        }

        $user->password = Hash::make($newPass);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been successfully reset. Please sign in with your new password.',
            'redirect' => route('login'),
        ]);
    }
}

