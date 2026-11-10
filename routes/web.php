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
    return view('landing');
})->name('welcome');

Route::get('/all-packages', [MainSiteController::class, 'allPackages'])->name('all-packages');
Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'bn'])) {
        session()->put('main_site_locale', $locale);
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

// MikroTik Automated Provisioning Endpoints (.rsc Bootstrap & Done Callback)
Route::prefix('provision')->group(function () {
    Route::get('/{token}/bootstrap', [\App\Http\Controllers\Api\ProvisioningController::class, 'bootstrap'])->middleware('throttle:60,1');
    Route::get('/{token}/done', [\App\Http\Controllers\Api\ProvisioningController::class, 'done'])->middleware('throttle:60,1');
});

Route::get('/portal', function () {
    return redirect('/portal/login');
});

Route::get('/billing', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

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
        Route::get('/admin/routers', \App\Livewire\Admin\RoutersManager::class)->name('admin.routers');
        Route::get('/mikrotik', \App\Livewire\Admin\RoutersManager::class)->name('mikrotik-sync');
        Route::get('/routers', \App\Livewire\Admin\RoutersManager::class)->name('mikrotik');
        Route::get('/admin/packages', \App\Livewire\Admin\PackagesManager::class)->name('admin.packages');
        Route::get('/packages-list', fn() => redirect()->route('admin.packages'))->name('packages');
        Route::get('/packages', fn() => redirect()->route('admin.packages'))->name('package-list-setup');
        Route::get('/admin/add-router', \App\Livewire\Admin\AddRouterWizard::class)->name('admin.add-router');
        Route::get('/router-wizard', fn() => redirect()->route('admin.add-router'))->name('admin.router-wizard');
        Route::get('/admin/router-wizard', fn() => redirect()->route('admin.add-router'))->name('router-wizard');
        Route::get('/admin/sms-email', \App\Livewire\Admin\SmsEmailHub::class)->name('admin.sms-email');
        Route::get('/admin/sms-gateways', fn() => redirect()->route('admin.sms-email', ['tab' => 'gateways']))->name('admin.sms-gateways');
        Route::get('/sms', fn() => redirect()->route('admin.sms-email'))->name('sms-setup');
        Route::get('/payment-gateways', fn() => redirect()->route('admin.payment-gateways'));
        Route::get('/admin/portal-customizer', \App\Livewire\Admin\CaptivePortalCustomizer::class)->name('admin.portal-customizer');
        Route::post('/captive-portal/preview', function(\Illuminate\Http\Request $request) {
            $template = $request->input('template', 'midnight');
            $settings = $request->input('settings', []);
            $plans = \App\Models\PackageList::where('is_active', true)->orWhereNull('is_active')->take(6)->get()->toArray();
            $html = \App\Services\CaptivePortalTemplates::render($template, $settings, $plans);
            return response($html)->header('Content-Type', 'text/html');
        })->name('captive-portal.preview');

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

        // payment routes
        Route::get('/payment-collection', PaymentCollection::class)->name('payment-collection');
        Route::get('/payment-collection-edit', CollectionEdit::class)->name('collection-edit');
        Route::get('/payment-invoice', Invoice::class)->name('payment-invoice');

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

            // Customer Reviews Management
            Route::get('reviews', ManageReviews::class)->name('reviews');

            // Reseller Vouchers Audit
            Route::get('vouchers', AdminVoucherList::class)->name('vouchers');

            // Kenyan Payment Gateways & Unmatched Payments
            Route::get('payment-gateways', \App\Livewire\Admin\PaymentGatewaysManager::class)->name('payment-gateways');
            Route::get('unmatched-payments', \App\Livewire\Admin\UnmatchedPayments::class)->name('unmatched-payments');

            // Package purchase requests
            Route::get('purchase-requests', ManagePurchaseRequests::class)->name('purchase-requests');
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


