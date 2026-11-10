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

// ── Kenya Payment Gateways (Strict Rate Limiting & Safe Payload Logging) ──

// 1. M-Pesa SMS Forwarder Webhook
Route::middleware(['throttle:60,1', 'webhook.log'])->post('/payment/mfs/sms-receiver', [WebhookPaymentController::class, 'handleSmsReceiver']);

// 2. M-Pesa Daraja (STK Callback, C2B, STK Push & Polling Status)
Route::prefix('payment/daraja')->group(function () {
    Route::post('/stk-callback', [DarajaPaymentController::class, 'handleStkCallback'])->middleware(['throttle:60,1', 'webhook.log']);
    Route::post('/validation', [DarajaPaymentController::class, 'handleC2bValidation'])->middleware(['throttle:60,1', 'webhook.log']);
    Route::post('/confirmation', [DarajaPaymentController::class, 'handleC2bConfirmation'])->middleware(['throttle:60,1', 'webhook.log']);
    Route::post('/stk-push', [DarajaPaymentController::class, 'initiateStkPush'])->middleware('throttle:30,1');
    Route::get('/status', [DarajaPaymentController::class, 'checkPaymentStatus'])->middleware('throttle:120,1');
});

// 3. PayHero Webhook
Route::middleware(['throttle:60,1', 'webhook.log'])->post('/payment/payhero/webhook', [PayHeroPaymentController::class, 'handleWebhook']);

// 4. KopoKopo Webhook
Route::middleware(['throttle:60,1', 'webhook.log'])->post('/payment/kopokopo/webhook', [KopoKopoPaymentController::class, 'handleWebhook']);



