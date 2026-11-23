<?php

use App\Http\Controllers\Admin\ProfitSummaryController;
use App\Http\Controllers\Admin\ResellerController;
use App\Livewire\Admin\ExpenseManager;
use App\Livewire\Admin\ActivityLogViewer;
use App\Livewire\Admin\ManagePurchaseRequests;
use App\Livewire\Admin\LoginLogViewer;
use App\Livewire\Admin\ManageReviews;
use App\Livewire\Admin\AdminVoucherList;
use App\Livewire\Admin\SystemLogViewer;
use App\Http\Controllers\CollectionReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MainSiteController;
use App\Http\Controllers\Payment\BkashPaymentController;
use App\Http\Controllers\Payment\NagadPaymentController;
use App\Http\Controllers\Payment\PayBridgePaymentController;
use App\Http\Controllers\Payment\SslCommerzPaymentController;
use App\Http\Controllers\Portal\PortalVoucherController;
use App\Http\Controllers\Reseller\ResellerDashboardController;
use App\Livewire\AddressSetup;
use App\Livewire\Admin\ManageRole;
use App\Livewire\Admin\ManageTickets;
use App\Livewire\Admin\ManageUser;
use App\Livewire\CollectionEdit;
use App\Livewire\CommentSubmit;
use App\Livewire\CustomerList;
use App\Livewire\CustomerSummary;
use App\Livewire\EditCustomer;
use App\Livewire\MainSiteSetup;
use App\Livewire\Mikrotik\BackupManager;
use App\Livewire\Mikrotik\FirewallSetup;
use App\Livewire\Mikrotik\HotspotManager;
use App\Livewire\Mikrotik\InterfaceSetup;
use App\Livewire\Mikrotik\IpSetup;
use App\Livewire\Mikrotik\PppoeSetup;
use App\Livewire\Mikrotik\QueueSetup;
use App\Livewire\Mikrotik\RadiusSetup;
use App\Livewire\Mikrotik\RouterLogViewer;
use App\Livewire\Mikrotik\TrafficMonitor;
use App\Livewire\Mikrotik\VpnSetup;
use App\Livewire\Mikrotik\WalledGardenSetup;
use App\Livewire\MikrotikSync;
use App\Livewire\NewCustomer;
use App\Livewire\NotificationListAll;
use App\Livewire\PackageListSetup;
use App\Livewire\Payment\Invoice;
use App\Livewire\PaymentCollection;
use App\Livewire\Report\DisReport;
use App\Livewire\Reseller\ResellerCustomerList;
use App\Livewire\Reseller\ResellerPackageManagement;
use App\Livewire\Reseller\ResellerVoucherManagement;
use App\Livewire\Reseller\ResellerWalletManagement;
use App\Livewire\SMSSetup;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Supported primary and alias hosts
$baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url');

$isLocal = in_array($baseDomain, ['localhost', '127.0.0.1']) || app()->environment('local');
$validHosts = [
    $baseDomain,
    'isp.hsmart.co.ke',
    'billing.hsmart.co.ke',
    'localhost',
    '127.0.0.1',
];

// Catch-all redirect for external domains or direct IP requests (NAT Redirection for Expired Users)
$currentHost = request()->getHost();
if ($currentHost && !in_array($currentHost, $validHosts)) {
    Route::any('{any}', function () {
        return redirect()->away(config('app.url') . '/warning');
    })->where('any', '.*');
}

// ── Super Admin Single Sign-On (SSO) & Impersonation Entrypoint ──
Route::get('/sso/hub-login', [\App\Http\Controllers\Auth\HubSsoController::class, 'login'])->name('sso.hub-login');
Route::get('/sso/exit-impersonation', [\App\Http\Controllers\Auth\HubSsoController::class, 'exitImpersonation'])->name('sso.exit-impersonation');

// Public & Landing Routes
Route::get('/', function () {
    if (request()->getHost() === 'billing.hsmart.co.ke') {
        return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
    }
    $requestedLang = request('lang');
    if ($requestedLang && in_array($requestedLang, ['en', 'sw', 'fr'])) {
        session()->put('main_site_locale', $requestedLang);
    }
    $locale = session('main_site_locale', 'en');
    if (in_array($locale, ['en', 'sw', 'fr'])) {
        app()->setLocale($locale);
    }
    return view('landing');
})->name('welcome');

Route::get('/all-packages', [MainSiteController::class, 'allPackages'])->name('all-packages');
Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'sw', 'fr', 'bn'])) {
        session()->put('main_site_locale', $locale);
        app()->setLocale($locale);
    }
    return redirect()->back();
})->name('welcome.lang');

// Warning / Recharge page for expired users
Route::get('/warning', function () {
    return view('warning');
})->name('warning');

// Public voucher redemption route on main domain
Route::get('/recharge/voucher', [PortalVoucherController::class, 'showRechargeForm'])->name('welcome.voucher.recharge');
Route::post('/recharge/voucher', [PortalVoucherController::class, 'redeem'])->name('welcome.voucher.redeem');

// Captive Portal Public Live Preview & Direct Access (Strictly Isolated per ISP Tenant & Router)
Route::match(['get', 'post'], '/captive-portal/preview', function(\Illuminate\Http\Request $request) {
    $user = auth()->user();
    $tenantId = null;
    $routerId = null;

    // 1. Explicit query or input parameter (?tenant_id=...)
    if ($request->filled('tenant_id')) {
        $tenantId = (int) $request->input('tenant_id');
    }

    // 2. Explicit router_id lookup (?router_id=...)
    if ($request->filled('router_id')) {
        $routerId = (int) $request->input('router_id');
        $router = \App\Models\Router::find($routerId) ?: \App\Models\RouterList::find($routerId);
        if ($router && $router->tenant_id && !$tenantId) {
            $tenantId = (int) $router->tenant_id;
        }
    }

    // 3. Router setup_token or identity parameter (?token=... or ?identity=...)
    if (!$tenantId && ($request->filled('token') || $request->filled('identity'))) {
        $token = $request->input('token') ?: $request->input('identity');
        $router = \App\Models\Router::where('setup_token', $token)
            ->orWhere('name', $token)
            ->first();
        if ($router && $router->tenant_id) {
            $tenantId = (int) $router->tenant_id;
            $routerId = (int) $router->id;
        }
    }

    // 4. Authenticated admin/reseller viewing their own portal
    if (!$tenantId && $user) {
        $tenantId = (int) ($user->tenant_id ?: 1);
    }

    // 5. Hostname / subdomain resolution
    if (!$tenantId) {
        try {
            $host = $request->getHost();
            $sub = explode('.', $host)[0];
            $tenant = \App\Models\Tenant::where(function ($q) use ($host, $sub) {
                $q->where('custom_domain', $host)
                  ->orWhere('subdomain', $sub);
            })->first();
            if ($tenant) {
                $tenantId = (int) $tenant->id;
            }
        } catch (\Throwable $e) {}
    }

    // Default fallback
    $tenantId = $tenantId ?: 1;

    // ── Load Tenant's Isolated Portal Settings ──
    $portalSetting = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('isp_portal_settings')) {
            $portalSetting = \Illuminate\Support\Facades\DB::table('isp_portal_settings')
                ->where('tenant_id', $tenantId)
                ->first();
        }
    } catch (\Throwable $e) {}

    $template = $request->input('template') ?: ($portalSetting->template ?? 'midnight');
    $settings = $request->input('settings', []);
    if (empty($settings) && $portalSetting) {
        $settings = (array) $portalSetting;
    }

    // Tenant info
    $tenantObj = \App\Models\Tenant::find($tenantId);
    $tenantName = $tenantObj?->name ?: ($user && $user->tenant_id == $tenantId ? ($user->tenant?->name ?? $user->name) : 'HSmart WiFi');

    $settings['tenant_id'] = $tenantId;
    $settings['router_id'] = $routerId;

    if (empty($settings['brand_name']) || strtolower($settings['brand_name']) === 'high-speed wifi') {
        $settings['brand_name'] = $tenantName ?: 'High-Speed WiFi';
    }
    if (!isset($settings['logo_url'])) {
        $settings['logo_url'] = '';
    }

    // ── Strictly Isolated Hotspot Packages for THIS Tenant & Router ──
    $plansQuery = \App\Models\PackageList::forTenant($tenantId)
        ->where(function($q) {
            $q->where('type', 'hotspot')->orWhereNull('type');
        })
        ->where(function($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        });

    if ($routerId) {
        $plansQuery->where(function($q) use ($routerId) {
            $q->whereHas('routers', function($rq) use ($routerId) {
                $rq->where('routers.id', $routerId);
            })->orWhereDoesntHave('routers');
        });
    }

    $plans = $plansQuery->orderBy('price', 'asc')->take(8)->get()->toArray();

    // Fallback within tenant if no hotspot tagged packages exist
    if (empty($plans)) {
        $plans = \App\Models\PackageList::where('tenant_id', $tenantId)
            ->where(function($q) { $q->where('is_active', true)->orWhereNull('is_active'); })
            ->orderBy('price', 'asc')
            ->take(6)
            ->get()
            ->toArray();
    }

    $html = \App\Services\CaptivePortalTemplates::render($template, $settings, $plans);
    return response($html)->header('Content-Type', 'text/html');
})->name('captive-portal.preview');
Route::get('/captive-portal', fn(\Illuminate\Http\Request $r) => redirect()->route('captive-portal.preview', $r->query()))->name('captive-portal.live');
Route::get('/hotspot/preview', fn(\Illuminate\Http\Request $r) => redirect()->route('captive-portal.preview', $r->query()))->name('hotspot.preview');

// MikroTik Automated Provisioning Endpoints (.rsc Bootstrap, Done Callback & Hotspot Portal Template Fetch)
Route::prefix('provision')->group(function () {
    Route::get('/hotspot-portal/{router}/{file?}', function ($routerId, $file = 'login.html') {
        $router = \App\Models\Router::withoutGlobalScopes()->find($routerId);
        if (!$router) {
            abort(404, 'Router not found');
        }
        $html = \App\Services\CaptivePortalTemplates::renderForRouter($router, (string) $file);
        return response($html, 200, [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    })->name('provision.hotspot-portal');

    Route::get('/{token}/bootstrap', [\App\Http\Controllers\Api\ProvisioningController::class, 'bootstrap'])->middleware('throttle:60,1');
    Route::get('/{token}/done', [\App\Http\Controllers\Api\ProvisioningController::class, 'done'])->middleware('throttle:60,1');
});

Route::get('/portal', function (\Illuminate\Http\Request $r) {
    if ($r->has('router') || $r->has('router_id') || $r->has('mac') || $r->has('link-login-only')) {
        return redirect()->route('captive-portal.preview', $r->query());
    }
    return redirect('/portal/login');
});

// ── ISP Tenant Subscription & Automated Billing ──
Route::middleware(['auth'])->group(function () {
    Route::get('/subscription', [\App\Http\Controllers\TenantSubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('/billing', fn() => redirect()->route('subscription.index'))->name('billing');
    Route::post('/subscription/pay/mpesa', [\App\Http\Controllers\TenantSubscriptionController::class, 'initiateMpesa'])->name('subscription.pay.mpesa');
    Route::post('/subscription/pay/paystack', [\App\Http\Controllers\TenantSubscriptionController::class, 'initiatePaystack'])->name('subscription.pay.paystack');
    Route::get('/subscription/paystack/verify/{reference}', [\App\Http\Controllers\TenantSubscriptionController::class, 'verifyPaystack'])->name('subscription.paystack.verify');
    Route::get('/subscription/check-status/{reference}', [\App\Http\Controllers\TenantSubscriptionController::class, 'checkStatus'])->name('subscription.check-status');
});

// Subscription Webhook Callbacks (PayHero / Mpesa / Paystack)
Route::post('/api/subscription/callback', [\App\Http\Controllers\TenantSubscriptionController::class, 'webhookCallback'])->name('api.subscription.callback');
Route::post('/api/subscription/paystack/webhook', [\App\Http\Controllers\TenantSubscriptionController::class, 'paystackWebhook'])->name('api.subscription.paystack.webhook');

// Authentication & Verification Extensions (Email/Phone OTP, Password Reset & Google OAuth)
Route::post('/forgot-password', [\App\Http\Controllers\Auth\CustomAuthController::class, 'handleForgotPassword'])->name('password.email');
Route::get('/reset-password-otp', [\App\Http\Controllers\Auth\CustomAuthController::class, 'showPasswordResetOtpForm'])->name('password.reset.otp');
Route::post('/reset-password-otp', [\App\Http\Controllers\Auth\CustomAuthController::class, 'handlePasswordResetOtp'])->name('password.reset.otp.submit');

Route::post('/auth/register-step1', [\App\Http\Controllers\Auth\CustomAuthController::class, 'registerStep1'])->name('auth.register.step1');
Route::post('/auth/verify-code', [\App\Http\Controllers\Auth\CustomAuthController::class, 'verifyCode'])->name('auth.verify.code');
Route::post('/auth/resend-code', [\App\Http\Controllers\Auth\CustomAuthController::class, 'resendCode'])->name('auth.verify.resend');
Route::post('/auth/request-otp', [\App\Http\Controllers\Auth\CustomAuthController::class, 'requestLoginOtp'])->name('auth.request-otp');
Route::post('/auth/login-otp', [\App\Http\Controllers\Auth\CustomAuthController::class, 'loginWithOtp'])->name('auth.login-otp');
Route::post('/auth/forgot-password-otp', [\App\Http\Controllers\Auth\CustomAuthController::class, 'requestForgotPasswordOtp'])->name('auth.forgot-password-otp');
Route::post('/auth/reset-password-otp', [\App\Http\Controllers\Auth\CustomAuthController::class, 'resetPasswordWithOtp'])->name('auth.reset-password-otp');
Route::get('/auth/google/redirect', [\App\Http\Controllers\Auth\CustomAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\CustomAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// HSmart Live Chat Direct Desk Endpoints (Accessible for both authenticated merchants & visitors)
Route::get('/api/live-chat/messages', [\App\Http\Controllers\IspLiveChatController::class, 'messages'])->name('live-chat.messages');
Route::post('/api/live-chat/send', [\App\Http\Controllers\IspLiveChatController::class, 'send'])->name('live-chat.send');
Route::get('/api/live-chat/unread-count', [\App\Http\Controllers\IspLiveChatController::class, 'unreadCount'])->name('live-chat.unread-count');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'restrict.profile',
])->group(function () {
    Route::get('/system/db-backup/download/{filename}', function ($filename) {
        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(403, 'Invalid filename.');
        }
        $path = base_path('backups/'.$filename);
        if (file_exists($path)) {
            return response()->download($path);
        }
        abort(404, 'Backup file not found.');
    })->name('system.db-backup.download');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resources([
            'collection-report' => CollectionReportController::class,
        ]);
        Route::get('customers/data', [CustomerList::class, 'getData'])->name('customers.data');
        Route::get('customers/{id}/edit', [CustomerList::class, 'edit'])->name('customers.edit');
        Route::get('customers/{id}', [CustomerList::class, 'show'])->name('customers.show');
        Route::patch('customers/{id}', [CustomerList::class, 'update'])->name('customers.update');
        Route::get('/admin/customers', \App\Livewire\Admin\CustomersManager::class)->name('admin.customers');
        Route::get('customers', \App\Livewire\Admin\CustomersManager::class)->name('customers.index');

        Route::get('/new/customers', fn() => redirect()->route('admin.customers'))->name('customers-new');
        Route::get('/admin-users', ManageUser::class)->name('admin-users');
        Route::get('/admin-roles', ManageRole::class)->name('admin-roles');
        Route::get('/support-tickets', ManageTickets::class)->name('admin-tickets');
        Route::post('/api/hub/support/ticket', [\App\Http\Controllers\HubSupportController::class, 'submitTicket'])->name('hub.support.ticket');
        Route::get('/admin/routers', \App\Livewire\Admin\RoutersManager::class)->name('admin.routers');
        Route::get('/mikrotik', \App\Livewire\Admin\RoutersManager::class)->name('mikrotik-sync');
        Route::get('/routers', \App\Livewire\Admin\RoutersManager::class)->name('mikrotik');
        Route::get('/admin/migration', \App\Livewire\Admin\ZeroDowntimeMigration::class)->name('admin.migration');
        Route::get('/migration', fn() => redirect()->route('admin.migration'))->name('migration');
        Route::get('/admin/packages', \App\Livewire\Admin\PackagesManager::class)->name('admin.packages');
        Route::get('/packages-list', fn() => redirect()->route('admin.packages'))->name('packages');
        Route::get('/packages', fn() => redirect()->route('admin.packages'))->name('package-list-setup');
        Route::get('/admin/add-router', \App\Livewire\Admin\AddRouterWizard::class)->name('admin.add-router');
        Route::get('/router-wizard', fn() => redirect()->route('admin.add-router'))->name('admin.router-wizard');
        Route::get('/admin/router-wizard', fn() => redirect()->route('admin.add-router'))->name('router-wizard');
        Route::get('/admin/sms-email', \App\Livewire\Admin\SmsEmailHub::class)->name('admin.sms-email');
        Route::get('/admin/sms-gateways', fn() => redirect()->route('admin.sms-email', ['tab' => 'gateways']))->name('admin.sms-gateways');
        Route::get('/admin/notifications', \App\Livewire\Admin\NotificationSettingsManager::class)->name('admin.notifications');
        Route::get('/notification-settings', fn() => redirect()->route('admin.notifications'))->name('notification-settings');
        Route::get('/sms', fn() => redirect()->route('admin.sms-email'))->name('sms-setup');
        Route::get('/payment-gateways', fn() => redirect()->route('admin.payment-gateways'));
        Route::get('/admin/portal-customizer', \App\Livewire\Admin\CaptivePortalCustomizer::class)->name('admin.portal-customizer');
        Route::get('/admin/captive-portal', fn() => redirect()->route('admin.portal-customizer'))->name('admin.captive-portal');
        Route::get('/admin/hospitality-pos', \App\Livewire\Admin\HospitalityPosManager::class)->name('admin.hospitality-pos');

        // Mikrotik Setup Routes (Redirect old wizard/setup URLs to new Add Router wizard, keep HotspotManager intact)
        Route::prefix('mikrotik-setup')->group(function () {
            Route::get('/', fn() => redirect()->route('admin.add-router'))->name('mikrotik-setup.index');
            Route::get('/ip', fn() => redirect()->route('admin.add-router'))->name('mikrotik-ip-setup');
            Route::get('/pppoe', fn() => redirect()->route('admin.add-router'))->name('mikrotik-pppoe-setup');
            Route::get('/queue', fn() => redirect()->route('admin.add-router'))->name('mikrotik-queue-setup');
            Route::get('/firewall', fn() => redirect()->route('admin.add-router'))->name('mikrotik-firewall-setup');
            Route::get('/hotspot', HotspotManager::class)->name('mikrotik-hotspot-setup');
            Route::get('/hotspot-manager', HotspotManager::class)->name('mikrotik-hotspot-manager');
            Route::get('/radius', fn() => redirect()->route('admin.add-router'))->name('mikrotik-radius-setup');
            Route::get('/vpn', fn() => redirect()->route('admin.add-router'))->name('mikrotik-vpn-setup');
            Route::get('/interface', fn() => redirect()->route('admin.add-router'))->name('mikrotik-interface-setup');
            Route::get('/traffic', TrafficMonitor::class)->name('mikrotik-traffic-monitor');
            Route::get('/logs', RouterLogViewer::class)->name('mikrotik-log-viewer');
            Route::get('/backup', BackupManager::class)->name('mikrotik-backup-setup');
            Route::get('/walled-garden', WalledGardenSetup::class)->name('mikrotik-walled-garden');
        });

        Route::get('/address', AddressSetup::class)->name('address-setup');
        Route::get('/create-customer', NewCustomer::class)->name('new-customer');

        // payment routes (Unified Payments & Statements Hub)
        Route::get('/payment-collection', \App\Livewire\Admin\UnifiedTransactionsHub::class)->name('payment-collection');
        Route::get('/payment-collection-edit', \App\Livewire\Admin\UnifiedTransactionsHub::class)->name('collection-edit');
        Route::get('/payment-invoice', \App\Livewire\Admin\UnifiedTransactionsHub::class)->name('payment-invoice');
        Route::get('/transactions', \App\Livewire\Admin\UnifiedTransactionsHub::class)->name('transactions.hub');

        // all report
        Route::get('/customer-summary', CustomerSummary::class)->name('customer-summary');
        Route::get('/report/dis-report-table', [DisReport::class, 'getData'])->name('dis-report-table');
        Route::get('/report/dis-report', DisReport::class)->name('dis-report');

        // site settings (consolidated)
        Route::get('/site-settings', MainSiteSetup::class)
            ->middleware(DispatchServingFilamentEvent::class)
            ->name('site-settings');

        // main site content management (deprecate name but keeps route if needed or redirection)
        Route::get('/main-site-setup', function () {
            return redirect()->route('site-settings');
        });

        Route::get('/all-notifications', NotificationListAll::class)->name('notifications');
        // Route::get('/edit-customer', EditCustomer::class);
        // Route::get('/customers', CustomerList::class);

        Route::get('import-form', [ImportController::class, 'importForm'])->name('import.form');
        Route::post('collection-form', [ImportController::class, 'collectionForm'])->name('collection.form');
        Route::post('monthly-bill-form', [ImportController::class, 'monthlyBillForm'])->name('monthly.bill.form');
        // Route::post('import-preview', [ImportController::class, 'importView'])->name('import.preview');
        Route::post('import-store', [ImportController::class, 'import'])->name('import');

        // Route::get('/user/profile', [UserProfileController::class, 'index'])->name('user.profile');
        // Route::post('/user/profile/upload', [UserProfileController::class, 'uploadFile'])->name('user.profile.upload');
        // Route::get('/user/profile/update', [UserProfileController::class, 'update'])->name('user.profile.update');
        // Route::get('/user/password/update', [UserProfileController::class, 'updatePassword'])->name('user.password.update');

        Route::get('/submit-comment', CommentSubmit::class)->name('submit.comment');

        // Admin Management Routes (Restricted to Admins, inaccessible to Resellers)
        Route::prefix('admin')->middleware(['admin'])->name('admin.')->group(function () {
            Route::resource('resellers', ResellerController::class)->except(['show']);
            Route::post('resellers/{reseller}/adjust-balance', [ResellerController::class, 'adjustBalance'])->name('resellers.adjust-balance');
            Route::get('resellers/{reseller}/transactions', [ResellerController::class, 'getTransactionsJson'])->name('resellers.transactions');

            // Expense tracking
            Route::get('expenses', ExpenseManager::class)->name('expenses');

            // Profit & Loss summary
            Route::get('profit-summary', [ProfitSummaryController::class, 'index'])->name('profit-summary');

            // Activity Log Viewer
            Route::get('activity-logs', ActivityLogViewer::class)->name('activity-logs');

            // System Logs Viewer
            Route::get('system-logs', SystemLogViewer::class)->name('system-logs');

            // Login Logs Viewer
            Route::get('login-logs', LoginLogViewer::class)->name('login-logs');



            // Reseller Vouchers Audit
            Route::get('vouchers', AdminVoucherList::class)->name('vouchers');

            // Kenyan Payment Gateways & Unified Transactions / Unmatched Payments
            Route::get('payment-gateways', \App\Livewire\Admin\PaymentGatewaysManager::class)->name('payment-gateways');
            Route::get('unmatched-payments', \App\Livewire\Admin\UnifiedTransactionsHub::class)->name('unmatched-payments');
            Route::get('transactions', \App\Livewire\Admin\UnifiedTransactionsHub::class)->name('transactions');

            // Package purchase requests
            Route::get('purchase-requests', ManagePurchaseRequests::class)->name('purchase-requests');

            // Activation / Prepaid Users Hub
            Route::prefix('activation')->name('activation.')->group(function () {
                Route::get('prepaid-users', \App\Livewire\Admin\PrepaidUsersHub::class)->name('prepaid-users');
                Route::get('active-users', \App\Livewire\Admin\PrepaidUsersHub::class)->name('active-users');
                Route::get('expired-users', \App\Livewire\Admin\PrepaidUsersHub::class)->name('expired-users');
                Route::get('online-users', \App\Livewire\Admin\PrepaidUsersHub::class)->name('online-users');
                Route::get('offline-users', \App\Livewire\Admin\PrepaidUsersHub::class)->name('offline-users');
                Route::get('activate-user', \App\Livewire\Admin\PrepaidUsersHub::class)->name('activate-user');
                Route::get('roaming-users', \App\Livewire\Admin\PrepaidUsersHub::class)->name('roaming-users');
            });
        });

        // Reseller Portal Routes
        Route::prefix('reseller')->middleware(['reseller'])->name('reseller.')->group(function () {
            Route::get('/dashboard', [ResellerDashboardController::class, 'index'])->name('dashboard');
            Route::get('customers/data', [ResellerCustomerList::class, 'getData'])->name('customers.data');
            Route::get('customers', ResellerCustomerList::class)->name('customers.index');
            Route::get('customers/create', NewCustomer::class)->name('customers.create');
            Route::get('customers/{customerId}/edit', EditCustomer::class)->name('customers.edit');

            Route::get('packages', ResellerPackageManagement::class)->name('packages.index');

            // Vouchers & Wallet — always accessible to all resellers
            Route::get('vouchers', ResellerVoucherManagement::class)->name('vouchers.index');
            Route::get('wallet', ResellerWalletManagement::class)->name('wallet.index');
        });
    });

// Universal PayBridge endpoints & Public voucher redemption
Route::any('/payment/{gateway}/callback', [PayBridgePaymentController::class, 'callback'])->name('payment.paybridge.callback');
Route::any('/payment/{gateway}/success', [PayBridgePaymentController::class, 'success'])->name('payment.success');
Route::any('/payment/{gateway}/fail', [PayBridgePaymentController::class, 'fail'])->name('payment.fail');
Route::any('/payment/{gateway}/cancel', [PayBridgePaymentController::class, 'cancel'])->name('payment.cancel');
Route::any('/payment/{gateway}/webhook', [PayBridgePaymentController::class, 'webhook'])->name('payment.webhook');
Route::post('/payment/mock/submit', [BkashPaymentController::class, 'mockSubmit'])->name('payment.mock.submit');

Route::get('/portal/recharge/voucher', [PortalVoucherController::class, 'showRechargeForm'])->name('portal.voucher.recharge');
Route::post('/portal/recharge/voucher', [PortalVoucherController::class, 'redeem'])->name('portal.voucher.redeem');

// Legal Policies (Terms of Service, Privacy Policy, Cookie Policy)
Route::get('/cookie-policy', function () {
    $file = resource_path('markdown/cookies.md');
    $content = file_exists($file) ? \Illuminate\Support\Str::markdown(file_get_contents($file)) : '';
    return view('cookies', ['cookies' => $content]);
})->name('cookie.show');

Route::get('/cookies', fn() => redirect()->route('cookie.show'));
Route::get('/terms', fn() => redirect()->route('terms.show'));
Route::get('/privacy', fn() => redirect()->route('policy.show'));

// Dedicated White-Labeled Client Hospitality Reception & POS Console (Token-Authenticated, No ISP Login Required)
Route::get('/hospitality/portal/{token}', \App\Livewire\Hospitality\HospitalityClientConsole::class)->name('hospitality.client-console');




