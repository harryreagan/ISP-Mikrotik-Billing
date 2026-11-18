<?php

use App\Http\Middleware\CheckSiteStatus;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocaleFromSession::class,
        ]);
        $middleware->append(CheckSiteStatus::class);
        $middleware->alias([
            'admin'              => \App\Http\Middleware\EnsureAdminAccess::class,
            'reseller'           => \App\Http\Middleware\EnsureUserIsReseller::class,
            'restrict.profile'   => \App\Http\Middleware\RestrictToProfileIfNoPermissions::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'webhook.log'        => \App\Http\Middleware\LogSafeWebhookPayload::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/payment/bkash/callback',
            '/payment/nagad/callback',
            '/payment/sslcommerz/callback',
            '/payment/mock/submit',
            '/payment/*/callback',
            '/payment/paybridge/*',
            '/api/payment/*',
            '/api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            try {
                if (app()->bound('auth') && app()->bound('hash')) {
                    $user = auth()->user();
                    \Illuminate\Support\Facades\Log::withContext([
                        'tenant_id' => $user?->tenant_id ?? (session()->has('tenant_id') ? session('tenant_id') : 'platform'),
                        'user_id'   => $user?->id ?? 'guest',
                        'user_role' => $user?->role ?? ($user?->roles?->first()?->name ?? 'guest'),
                        'url'       => request()->fullUrl(),
                        'method'    => request()->method(),
                    ]);
                }
            } catch (\Throwable $ignored) {}
        });
    })->create();
