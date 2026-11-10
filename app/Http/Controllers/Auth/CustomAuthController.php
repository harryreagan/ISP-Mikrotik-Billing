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
        if (!$user->personalTeam()) {
            $teamName = $request->business_name ?: explode(' ', $user->name, 2)[0] . "'s Team";
            $team = Team::forceCreate([
                'user_id' => $user->id,
                'name' => $teamName,
                'personal_team' => true,
            ]);
            $user->current_team_id = $team->id;
            $user->save();
        }

        // Assign default Admin role if Spatie roles exist
        if (Role::where('name', 'Admin')->exists() && !$user->hasAnyRole(Role::all())) {
            $user->assignRole('Admin');
        }

        // Generate and send 6-digit code
        $result = $this->verificationService->generateAndSendCode($email, $request->ip());

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

        $result = $this->verificationService->generateAndSendCode($email, $request->ip());

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
        $user->save();

        // Assign default Admin role
        if (Role::where('name', 'Admin')->exists()) {
            $user->assignRole('Admin');
        }

        Auth::login($user, true);

        return redirect()->route('dashboard');
    }
}

