<?php

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'portal.')) {
            config(['session.cookie' => 'portal_session']);
        }

        // Dynamically register Telescope only if the package is installed
        if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class) && class_exists(\App\Providers\TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        // ── Register Multi-Tenant Isolation Policies ──────────────────────────
        Gate::policy(\App\Models\Router::class, \App\Policies\RouterPolicy::class);
        Gate::policy(\App\Models\PackageList::class, \App\Policies\PackagePolicy::class);
        Gate::policy(\App\Models\CustomersInfo::class, \App\Policies\CustomerPolicy::class);
        Gate::policy(\App\Models\PPPSecrets::class, \App\Policies\PppSecretPolicy::class);
        Gate::policy(\App\Models\HotspotVoucher::class, \App\Policies\VoucherPolicy::class);
        Gate::policy(\App\Models\Voucher::class, \App\Policies\VoucherPolicy::class);
        Gate::policy(\App\Models\PaymentChannel::class, \App\Policies\PaymentChannelPolicy::class);
        Gate::policy(\App\Models\IspExpense::class, \App\Policies\ExpensePolicy::class);
        Gate::policy(\App\Models\SupportTicket::class, \App\Policies\TicketPolicy::class);
        Gate::policy(\App\Models\Reseller::class, \App\Policies\ResellerPolicy::class);

        // ── Force HTTPS in production or behind HTTPS reverse proxy ──────────
        if (app()->environment('production') || config('app.env') === 'production' || str_starts_with((string)config('app.url'), 'https://') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
            if (isset($_SERVER)) {
                $_SERVER['HTTPS'] = 'on';
            }
        }

        // Attribution Guard disabled for HSmart WiFi Billing
        // if (! app()->runningInConsole() && ! app()->runningUnitTests()) {
        //     $this->enforceAttribution();
        // }

        // Set dynamic application locale from database settings
        try {
            if (class_exists(\App\Models\MainSiteData::class) && \Illuminate\Support\Facades\Schema::hasTable('main_site_data')) {
                if (app()->runningInConsole()) {
                    $locale = \App\Models\MainSiteData::getValue('site_locale', 'en');
                } else {
                    $host = request()->getHost();
                    if ($host && (str_starts_with($host, 'billing.') || str_starts_with($host, 'portal.'))) {
                        $locale = \App\Models\MainSiteData::getValue('site_locale', 'en');
                    } else {
                        $locale = \App\Models\MainSiteData::getValue('main_site_locale', 'en');
                    }
                }
                \Illuminate\Support\Facades\App::setLocale($locale);
            }
        } catch (\Throwable $e) {
            // Avoid issues during setup or migrations
        }

        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole('Super Admin') ? true : null;
            }

            return null;
        });

        Gate::define('viewLogViewer', function ($user = null) {
            return auth()->check() && hasAccess(['Super Admin'], ['all-customer']);
        });

        // Optimize Livewire components in the specified directory
        if (app()->bound('blaze')) {
            Blaze::optimize()->in(resource_path('views/components'));
        }

        // ISP Hub Connector Observer
        if (class_exists(\App\Models\PaymentSummary::class)) {
            \App\Models\PaymentSummary::observe(\App\Observers\PaymentHubObserver::class);
        }

        // ISP Hub Console Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\HubTestCommand::class,
                \App\Console\Commands\HubSyncCommand::class,
            ]);
        }


        // Register Kenyan SMS Drivers into SmsBridge
        if (class_exists(\Codepagol\SmsBridge\Facades\SmsBridge::class)) {
            \Codepagol\SmsBridge\Facades\SmsBridge::extend('africastalking', function ($app) {
                $gw = new \App\Services\Sms\Gateways\AfricasTalkingGateway();
                $dbGw = \Codepagol\SmsBridge\Models\SmsGateway::where('name', 'africastalking')->first();
                if ($dbGw) {
                    $gw->setCredentials([
                        'username'  => $dbGw->username ?: siteUrlSettings('africastalking_username') ?: env('AFRICASTALKING_USERNAME', 'sandbox'),
                        'api_key'   => $dbGw->api_key ?: siteUrlSettings('africastalking_api_key') ?: env('AFRICASTALKING_API_KEY', ''),
                        'sender_id' => $dbGw->sender_id ?: siteUrlSettings('africastalking_sender_id') ?: env('AFRICASTALKING_SENDER_ID', ''),
                    ]);
                }
                return $gw;
            });

            \Codepagol\SmsBridge\Facades\SmsBridge::extend('textsms', function ($app) {
                $gw = new \App\Services\Sms\Gateways\TextSmsGateway();
                $dbGw = \Codepagol\SmsBridge\Models\SmsGateway::where('name', 'textsms')->first();
                if ($dbGw) {
                    $gw->setCredentials([
                        'username'  => $dbGw->username ?: siteUrlSettings('textsms_partner_id') ?: env('TEXTSMS_PARTNER_ID', ''),
                        'api_key'   => $dbGw->api_key ?: siteUrlSettings('textsms_api_key') ?: env('TEXTSMS_API_KEY', ''),
                        'sender_id' => $dbGw->sender_id ?: siteUrlSettings('textsms_shortcode') ?: env('TEXTSMS_SHORTCODE', ''),
                        'url'       => $dbGw->api_url ?: siteUrlSettings('textsms_base_url') ?: env('TEXTSMS_BASE_URL', 'https://sms.textsms.co.ke'),
                    ]);
                }
                return $gw;
            });
        }

        Paginator::useBootstrapFive();

        Auth::provider('pppoe_provider', function ($app, array $config) {
            return new class($app['hash'], $config['model']) extends EloquentUserProvider
            {
                public function retrieveByCredentials(array $credentials)
                {
                    if (empty($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
                        return null;
                    }

                    $username = trim((string)($credentials['username'] ?? ''));
                    if ($username === '') {
                        return null;
                    }

                    // 1. Direct match on PPPSecrets username
                    $user = $this->createModel()->newQuery()->where('username', $username)->first();
                    if ($user) {
                        return $user;
                    }

                    // 2. Normalize Kenyan phone (e.g. 0712345678 <-> 254712345678)
                    $cleanPhone = preg_replace('/\D/', '', $username);
                    $phoneVariants = array_values(array_filter([
                        $username,
                        $cleanPhone,
                        str_starts_with($cleanPhone, '254') ? '0' . substr($cleanPhone, 3) : null,
                        str_starts_with($cleanPhone, '0') ? '254' . substr($cleanPhone, 1) : null,
                    ]));

                    // 3. Match via CustomersInfo phone or account_no
                    if (!empty($phoneVariants)) {
                        try {
                            $customer = \App\Models\CustomersInfo::where(function($q) use ($phoneVariants, $username) {
                                $q->whereIn('phone', $phoneVariants)
                                  ->orWhereIn('alt_phone', $phoneVariants)
                                  ->orWhere('account_no', $username);
                            })->whereNotNull('ppp_user_id')->first();

                            if ($customer && $customer->ppp_user_id) {
                                return $this->createModel()->newQuery()->find($customer->ppp_user_id);
                            }
                        } catch (\Throwable $e) {}
                    }

                    // 4. Match caller_id in PPPSecrets
                    return $this->createModel()->newQuery()->where('caller_id', $username)->first();
                }

                public function validateCredentials(Authenticatable $user, array $credentials)
                {
                    $plain = $credentials['password'];
                    // getAuthPassword() calls the model's custom getter, returning decrypted password if encrypted,
                    // or the raw value if it is legacy plaintext / bcrypt hash.
                    $auth_password = $user->getAuthPassword();

                    // Check plain text (includes newly decrypted passwords and legacy plain text)
                    if ($plain === $auth_password) {
                        return true;
                    }

                    // Fallback to standard hashing only if it looks like a legacy bcrypt hash
                    if (str_starts_with($auth_password, '$2y$') || str_starts_with($auth_password, '$2a$')) {
                        return parent::validateCredentials($user, $credentials);
                    }

                    return false;
                }
            };
        });

        // Listen to Login Event
        Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $user = $event->user;
            if ($user) {
                $ip = request()->ip();
                $userAgent = request()->userAgent();
                $username = $user instanceof \App\Models\PPPSecrets ? $user->username : ($user->email ?? $user->name ?? 'Unknown');

                \App\Models\UserLoginLog::create([
                    'authenticatable_id' => $user->id,
                    'authenticatable_type' => get_class($user),
                    'username' => $username,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'action' => 'login',
                ]);

                // Track client portal login count and session duration
                if ($user instanceof \App\Models\PPPSecrets) {
                    $user->increment('login_count');
                    session(['portal_login_time' => now()]);
                }
            }
        });

        // Listen to Logout Event
        Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            $user = $event->user;
            if ($user) {
                $ip = request()->ip();
                $userAgent = request()->userAgent();
                $username = $user instanceof \App\Models\PPPSecrets ? $user->username : ($user->email ?? $user->name ?? 'Unknown');

                \App\Models\UserLoginLog::create([
                    'authenticatable_id' => $user->id,
                    'authenticatable_type' => get_class($user),
                    'username' => $username,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'action' => 'logout',
                ]);
            }
        });
    }

    /**
     * Attribution Guard.
     *
     * This application is open-source under the CodePagol license.
     * The license requires that the "Powered by CodePagol" credit remain
     * visible in the UI. This method verifies that the attribution has not
     * been removed from the distributed view files.
     *
     * See: /LICENSE
     */
    private function enforceAttribution(): void
    {
        // No-op: Attribution check disabled for HSmart WiFi Billing
    }
}
