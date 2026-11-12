<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LogoutResponse::class,
            fn () => new class implements \Laravel\Fortify\Contracts\LogoutResponse {
                public function toResponse($request)
                {
                    return $request->wantsJson()
                        ? new \Illuminate\Http\JsonResponse('', 204)
                        : redirect()->route('login');
                }
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Dedicated Auth Views
        Fortify::loginView(function () {
            return view('auth.login');
        });

        Fortify::registerView(function () {
            return view('auth.register');
        });

        Fortify::requestPasswordResetLinkView(function () {
            return view('auth.forgot-password');
        });

        Fortify::resetPasswordView(function ($request) {
            return view('auth.reset-password', ['request' => $request]);
        });

        // Support Phone OR Email login with Kenyan phone number normalization
        Fortify::authenticateUsing(function (Request $request) {
            $loginInput = trim((string) $request->input(Fortify::username()));
            $normalizedPhone = \App\Services\Auth\OtpVerificationService::normalizeKenyanPhone($loginInput);

            $user = User::where('email', strtolower($loginInput))
                ->orWhere(function ($q) use ($loginInput, $normalizedPhone) {
                    if ($normalizedPhone) {
                        $q->where('phone', $normalizedPhone)
                          ->orWhere('mobile', $normalizedPhone)
                          ->orWhere('phone', $loginInput)
                          ->orWhere('mobile', $loginInput);
                    } else {
                        $q->where('phone', $loginInput)
                          ->orWhere('mobile', $loginInput);
                    }
                })->first();

            if ($user && Hash::check($request->input('password'), $user->password)) {
                // Ensure either email or phone is verified
                if (is_null($user->email_verified_at) && is_null($user->phone_verified_at)) {
                    session(['pending_verification_email' => $user->email, 'pending_verification_phone' => $user->phone]);
                    try {
                        if ($user->email) {
                            app(EmailVerificationService::class)->generateAndSendCode($user->email, $request->ip());
                        } elseif ($user->phone) {
                            \App\Services\Auth\OtpVerificationService::generateAndSendOtp($user->phone, 'auth', $user->tenant_id, $request->ip());
                        }
                    } catch (\Throwable $e) {}

                    throw ValidationException::withMessages([
                        Fortify::username() => ['Account is not verified yet. A verification code has been dispatched. Please verify to continue.'],
                    ])->redirectTo(route('register', ['tab' => 'verify']));
                }

                return $user;
            }

            return null;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
