<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Payment\DarajaPaymentController;
use App\Http\Controllers\Payment\KopoKopoPaymentController;
use App\Http\Controllers\Payment\PayHeroPaymentController;
use App\Http\Controllers\Payment\WebhookPaymentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['web', 'auth'])->get('/sms/balance', \App\Http\Controllers\Api\SmsBalanceController::class)->name('api.sms.balance');

// ── Kenya Payment Gateways (Strict Rate Limiting & Safe Payload Logging) ──

// 1. M-Pesa SMS Forwarder Webhook
Route::middleware(['throttle:60,1', 'webhook.log'])->post('/payment/mfs/sms-receiver', [WebhookPaymentController::class, 'handleSmsReceiver']);

// 2. M-Pesa Daraja (STK Callback, C2B, STK Push & Polling Status)
Route::prefix('payment/daraja')->group(function () {
    Route::post('/stk-callback', [DarajaPaymentController::class, 'handleStkCallback'])->middleware(['throttle:60,1', 'webhook.log']);
    Route::post('/validation', [DarajaPaymentController::class, 'handleC2bValidation'])->middleware(['throttle:60,1', 'webhook.log']);
    Route::post('/confirmation', [DarajaPaymentController::class, 'handleC2bConfirmation'])->middleware(['throttle:60,1', 'webhook.log']);
    Route::post('/stk-push', [PayHeroPaymentController::class, 'initiatePortalPayment'])->middleware('throttle:30,1');
    Route::get('/status', [PayHeroPaymentController::class, 'checkPortalStatus'])->middleware('throttle:120,1');
});

// Unified Hotspot & Portal Payment Endpoints
Route::post('/payment/stk-push', [PayHeroPaymentController::class, 'initiatePortalPayment'])->middleware('throttle:30,1');
Route::get('/payment/status', [PayHeroPaymentController::class, 'checkPortalStatus'])->middleware('throttle:120,1');

// 3. PayHero (Webhook, STK Push & Polling)
Route::prefix('payment/payhero')->group(function () {
    Route::post('/stk-push', [PayHeroPaymentController::class, 'initiatePortalPayment'])->middleware('throttle:30,1');
    Route::get('/status', [PayHeroPaymentController::class, 'checkPortalStatus'])->middleware('throttle:120,1');
});
Route::middleware(['throttle:60,1', 'webhook.log'])->post('/payment/payhero/webhook', [PayHeroPaymentController::class, 'handleWebhook']);

// 4. KopoKopo Webhook
Route::middleware(['throttle:60,1', 'webhook.log'])->post('/payment/kopokopo/webhook', [KopoKopoPaymentController::class, 'handleWebhook']);

// 5. Router Hello Heartbeat for Onboarding & Online Health
Route::get('/router-hello/{token}', function (string $token) {
    $cleanToken = preg_replace('/[^a-zA-Z0-9_\-]/', '', $token);
    if (empty($cleanToken) || strlen($cleanToken) < 8) {
        return response()->json(['status' => 'ignored'], 200);
    }

    $router = \App\Services\ProvisioningTokenService::validateToken($cleanToken);

    if ($router) {
        $router->update([
            'status'           => 'online',
            'last_seen_at'     => now(),
            'vpn_connected_at' => $router->vpn_connected_at ?: now(),
        ]);
        \Illuminate\Support\Facades\Log::info("[RouterHello] Router #{$router->id} ({$router->name}) signaled online via token");
    }

    return response()->json(['status' => 'ok', 'timestamp' => now()->timestamp], 200);
})->middleware('throttle:60,1');




