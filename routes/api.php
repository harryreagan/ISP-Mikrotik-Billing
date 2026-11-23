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

// CORS preflight handler for Captive Portal cross-origin requests from MikroTik (http://hotspot.hsmart.lan / 10.5.50.1)
Route::options('/payment/{any}', function () {
    return response('', 204)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With, Authorization');
})->where('any', '.*');

Route::options('/portal/{any}', function () {
    return response('', 204)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With, Authorization');
})->where('any', '.*');

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

// Returning User & Captive Portal Session Endpoints
Route::post('/portal/returning-user', [\App\Http\Controllers\Portal\CaptivePortalApiController::class, 'returningUser'])->middleware('throttle:60,1');
Route::get('/portal/check-session', [\App\Http\Controllers\Portal\CaptivePortalApiController::class, 'checkSession'])->middleware('throttle:120,1');
Route::get('/portal/network-devices', [\App\Http\Controllers\Portal\CaptivePortalApiController::class, 'getNetworkDevices'])->middleware('throttle:60,1');

// 3. PayHero (Webhook, STK Push & Polling)
Route::prefix('payment/payhero')->group(function () {
    Route::post('/stk-push', [PayHeroPaymentController::class, 'initiatePortalPayment'])->middleware('throttle:30,1');
    Route::get('/status', [PayHeroPaymentController::class, 'checkPortalStatus'])->middleware('throttle:120,1');
});
Route::middleware(['throttle:60,1', 'webhook.log'])->post('/payment/payhero/webhook', [PayHeroPaymentController::class, 'handleWebhook']);

// 4. KopoKopo Webhook
Route::middleware(['throttle:60,1', 'webhook.log'])->post('/payment/kopokopo/webhook', [KopoKopoPaymentController::class, 'handleWebhook']);

// 5. Router Hello Heartbeat for Onboarding & Online Health & Network Move Detection
Route::get('/router-hello/{token}', function (\Illuminate\Http\Request $request, string $token) {
    $cleanToken = preg_replace('/[^a-zA-Z0-9_\-]/', '', $token);
    if (empty($cleanToken) || strlen($cleanToken) < 8) {
        return response()->json(['status' => 'ignored'], 200);
    }

    $router = \App\Services\ProvisioningTokenService::validateToken($cleanToken);

    if ($router) {
        $incomingIp = $request->header('CF-Connecting-IP')
            ?: ($request->header('X-Forwarded-For') ? trim(explode(',', $request->header('X-Forwarded-For'))[0]) : $request->ip());
        $updates = [
            'status'           => 'online',
            'last_seen_at'     => now(),
            'vpn_connected_at' => $router->vpn_connected_at ?: now(),
        ];

        // Network Move Detection: router moved to a new public IP or LAN (ignoring Cloudflare proxy edge hops)
        if (!empty($incomingIp)) {
            if (!empty($router->last_known_ip) && $router->last_known_ip !== $incomingIp) {
                \Illuminate\Support\Facades\Log::warning("[RouterMoved] Router #{$router->id} ({$router->name}) public IP changed from '{$router->last_known_ip}' to '{$incomingIp}'. Triggering health sync.");
                $updates['network_changed_at'] = now();
            }
            $updates['last_known_ip'] = $incomingIp;
        }

        $router->update($updates);
        \Illuminate\Support\Facades\Log::info("[RouterHello] Router #{$router->id} ({$router->name}) signaled online from IP {$incomingIp}");

        // Automatically flush and provision any pending hotspot activations for this router
        try {
            app(\App\Services\RouterPendingActivationService::class)->processPendingForRouter($router);
        } catch (\Throwable $actEx) {
            \Illuminate\Support\Facades\Log::warning("[RouterHello] Pending activation flush note: " . $actEx->getMessage());
        }
    }

    return response()->json(['status' => 'ok', 'timestamp' => now()->timestamp], 200);
})->middleware('throttle:60,1');

// ── Hospitality & External POS WiFi Ticket API ──
Route::prefix('v1/hospitality')->middleware(['throttle:120,1'])->group(function () {
    Route::get('/stats', [\App\Http\Controllers\Api\HospitalityApiController::class, 'getStats']);
    Route::post('/ticket', [\App\Http\Controllers\Api\HospitalityApiController::class, 'generateTicket']);
    Route::post('/verify-guest', [\App\Http\Controllers\Api\HospitalityApiController::class, 'verifyGuest']);
    Route::get('/tickets', [\App\Http\Controllers\Api\HospitalityApiController::class, 'listTickets']);
    Route::post('/ticket/{code}/revoke', [\App\Http\Controllers\Api\HospitalityApiController::class, 'revokeTicket']);
});





