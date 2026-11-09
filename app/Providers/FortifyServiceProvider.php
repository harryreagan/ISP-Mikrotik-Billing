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
        //
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

        // Enforce Email Verification on Login
        Fortify::authenticateUsing(function (Request $request) {
            $loginInput = $request->input(Fortify::username());
            $query = User::where('email', $loginInput);
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')) {
                $query->orWhere('phone', $loginInput);
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('users', 'mobile')) {
                $query->orWhere('mobile', $loginInput);
            }
            $user = $query->first();

            if ($user && Hash::check($request->input('password'), $user->password)) {
                // Unverified users cannot log in until verified
                if (is_null($user->email_verified_at)) {
                    session(['pending_verification_email' => $user->email]);
                    try {
                        app(EmailVerificationService::class)->generateAndSendCode($user->email, $request->ip());
                    } catch (\Throwable $e) {}

                    throw ValidationException::withMessages([
                        Fortify::username() => ['Your email is not verified yet. A 6-digit verification code has been sent to your email. Please verify to continue.'],
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
