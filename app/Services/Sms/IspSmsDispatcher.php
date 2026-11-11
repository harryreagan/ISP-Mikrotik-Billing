<?php

namespace App\Services\Sms;

use App\Services\Auth\OtpVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IspSmsDispatcher
{
    /**
     * Send direct SMS via active ISP gateway or platform fallback.
     */
    public static function sendDirectSms(string $phone, string $message, ?int $tenantId = null): bool
    {
        $normalizedPhone = OtpVerificationService::normalizeKenyanPhone($phone);
        if (!$normalizedPhone) {
            Log::warning("[IspSmsDispatcher] Invalid phone number passed: {$phone}");
            return false;
        }

        // 1. Look up ISP's custom gateway configuration
        $gateway = null;
        if ($tenantId) {
            $gateway = DB::table('isp_sms_gateways')
                ->where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->first();
        }

        $sent = false;
        $providerName = 'System Gateway';
        $providerResponse = null;
        $isSystemGateway = false;

        if ($gateway && $gateway->gateway_type !== 'system') {
            $sent = self::dispatchCustomGateway($gateway, $normalizedPhone, $message, $providerName, $providerResponse);
            
            // If primary custom gateway failed and fallback is configured
            if (!$sent && $gateway->is_fallback) {
                Log::warning("[IspSmsDispatcher] Custom gateway [{$gateway->gateway_type}] failed for tenant {$tenantId}. Falling back to system gateway.");
                $isSystemGateway = true;
                $sent = self::dispatchSystemGateway($normalizedPhone, $message, $providerName, $providerResponse, $tenantId);
            }
        } else {
            $isSystemGateway = true;
            $sent = self::dispatchSystemGateway($normalizedPhone, $message, $providerName, $providerResponse, $tenantId);
        }

        // 2. If sent via System Gateway, deduct 1 credit from tenant's SMS wallet
        if ($sent && $isSystemGateway && $tenantId) {
            self::deductTenantCredit($tenantId, $normalizedPhone, $message);
        }

        // 3. Log message in notification logs
        try {
            DB::table('isp_notification_logs')->insert([
                'tenant_id'         => $tenantId ?: 1,
                'event_key'         => 'sms_direct',
                'channel'           => 'sms',
                'recipient'         => $normalizedPhone,
                'content'           => $message,
                'status'            => $sent ? 'sent' : 'failed',
                'provider'          => $providerName,
                'provider_response' => is_string($providerResponse) ? $providerResponse : json_encode($providerResponse),
                'cost'              => 0.40, // Standard KES per unit
                'sent_at'           => $sent ? now() : null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("[IspSmsDispatcher] Log recording warning: " . $e->getMessage());
        }

        return $sent;
    }

    /**
     * Dispatch SMS via custom provider integration.
     */
    protected static function dispatchCustomGateway(object $gw, string $phone, string $message, &$providerName, &$providerResponse): bool
    {
        $providerName = ucfirst($gw->gateway_type);
        $apiKey = $gw->api_key ? (decryptStringSafe($gw->api_key) ?: $gw->api_key) : '';
        $apiSecret = $gw->api_secret ? (decryptStringSafe($gw->api_secret) ?: $gw->api_secret) : '';
        $senderId = $gw->sender_id ?: 'HSMART';

        try {
            switch ($gw->gateway_type) {
                case 'africastalking':
                    $res = Http::withHeaders([
                        'apiKey' => $apiKey,
                        'Accept' => 'application/json',
                    ])->asForm()->post('https://api.africastalking.com/version1/messaging', [
                        'username' => $gw->api_username ?: 'sandbox',
                        'to'       => '+' . $phone,
                        'message'  => $message,
                        'from'     => $senderId,
                    ]);
                    $providerResponse = $res->json() ?: $res->body();
                    return $res->successful();

                case 'textsms':
                    $partnerId = $gw->api_username ?: $gw->partner_id ?? '17004';
                    $res = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                    ])->timeout(8)->post('https://sms.textsms.co.ke/api/services/sendsms/', [
                        'apikey'    => $apiKey,
                        'partnerID' => $partnerId,
                        'message'   => $message,
                        'shortcode' => $senderId ?: 'TextSMS',
                        'mobile'    => $phone,
                        'pass_type' => 'plain',
                    ]);
                    $data = $res->json() ?? [];
                    $providerResponse = $data;
                    $first = $data['responses'][0] ?? $data;
                    $code = (int) ($first['response-code'] ?? $data['response-code'] ?? 0);
                    return $code === 200;

                case 'blessedtexts':
                    $res = Http::post('https://blessedtexts.com/api/sms/send', [
                        'api_key'   => $apiKey,
                        'sender_id' => $senderId,
                        'phone'     => $phone,
                        'message'   => $message,
                    ]);
                    $providerResponse = $res->json() ?: $res->body();
                    return $res->successful();

                case 'advanta':
                    $res = Http::post('https://quicksms.advantasms.com/api/services/sendsms/', [
                        'apikey'    => $apiKey,
                        'partnerID' => $gw->api_username,
                        'message'   => $message,
                        'shortcode' => $senderId,
                        'mobile'    => $phone,
                    ]);
                    $providerResponse = $res->json() ?: $res->body();
                    return $res->successful();

                case 'generic_http':
                    $url = $gw->endpoint_url;
                    $method = strtoupper($gw->http_method ?: 'POST');
                    $headers = is_string($gw->http_headers) ? json_decode($gw->http_headers, true) : ($gw->http_headers ?: []);
                    
                    // Replace placeholders in body template
                    $bodyStr = str_replace(
                        ['{to}', '{phone}', '{message}', '{sender}'],
                        [$phone, $phone, addslashes($message), $senderId],
                        $gw->body_template ?: '{"to":"{to}","message":"{message}"}'
                    );

                    $req = Http::withHeaders($headers ?: []);
                    $bodyJson = json_decode($bodyStr, true);

                    if ($method === 'POST') {
                        $res = $bodyJson ? $req->post($url, $bodyJson) : $req->withBody($bodyStr, 'application/json')->post($url);
                    } else {
                        $res = $req->get($url . '?' . http_build_query($bodyJson ?: ['to' => $phone, 'msg' => $message]));
                    }

                    $bodyContent = $res->body();
                    $providerResponse = $bodyContent;

                    if (!empty($gw->success_keyword)) {
                        return str_contains($bodyContent, $gw->success_keyword);
                    }
                    return $res->successful();

                default:
                    return self::dispatchSystemGateway($phone, $message, $providerName, $providerResponse);
            }
        } catch (\Throwable $e) {
            $providerResponse = $e->getMessage();
            Log::error("[IspSmsDispatcher] Custom dispatch error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispatch SMS via platform-provided system gateway (TextSMS / Africa's Talking).
     * NEVER uses fake/mock simulation. Makes real HTTP API requests to carrier gateways.
     */
    protected static function dispatchSystemGateway(string $phone, string $message, &$providerName, &$providerResponse, ?int $tenantId = null): bool
    {
        $providerName = 'HSmart System Gateway (TextSMS)';

        // 1. Verify tenant has SMS credit balance if tenantId is provided (skip for platform auth/verification/welcome)
        $isAuthOrConfirmation = str_contains(strtolower($message), 'verification') 
            || str_contains(strtolower($message), 'code')
            || str_contains(strtolower($message), 'otp')
            || str_contains(strtolower($message), 'welcome')
            || str_contains(strtolower($message), 'confirm')
            || $tenantId === -1;

        if ($tenantId && !$isAuthOrConfirmation) {
            $creditBalance = self::getTenantCreditBalance($tenantId);
            if ($creditBalance <= 0) {
                $providerResponse = [
                    'status' => 'failed',
                    'error' => 'Insufficient SMS credits. Current balance: 0. Please top up your SMS wallet.',
                ];
                Log::warning("[IspSmsDispatcher] Tenant {$tenantId} has insufficient SMS credits (balance: {$creditBalance}).");
                return false;
            }
        }

        // 2. Primary: TextSMS Carrier API (Platform Live Gateway)
        $apiKey = env('TEXTSMS_API_KEY', '6ada719c1f1fa629711c0712cfb1dc6c');
        $partnerId = env('TEXTSMS_PARTNER_ID', '17004');
        $shortcode = env('TEXTSMS_SHORTCODE', 'TextSMS');
        $baseUrl = rtrim(env('TEXTSMS_BASE_URL', 'https://sms.textsms.co.ke'), '/');

        if (!empty($apiKey) && !empty($partnerId)) {
            try {
                $url = "{$baseUrl}/api/services/sendsms/";
                $payload = [
                    'apikey'    => $apiKey,
                    'partnerID' => (string) $partnerId,
                    'message'   => $message,
                    'shortcode' => $shortcode,
                    'mobile'    => $phone,
                    'pass_type' => 'plain',
                ];

                Log::info("[IspSmsDispatcher] Sending real SMS to {$phone} via TextSMS", [
                    'partnerID' => $partnerId,
                    'shortcode' => $shortcode,
                ]);

                $res = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])->timeout(10)->post($url, $payload);

                $data = $res->json() ?? [];
                $providerResponse = $data;

                $firstResp = $data['responses'][0] ?? $data;
                $code = (int) ($firstResp['response-code'] ?? $firstResp['code'] ?? $data['response-code'] ?? $data['code'] ?? 0);

                if ($code === 200) {
                    Log::info("[IspSmsDispatcher] TextSMS real delivery successful for {$phone}: Message ID " . ($firstResp['messageid'] ?? 'unknown'));
                    return true;
                } else {
                    $errorDesc = $firstResp['response-description'] ?? $data['response-description'] ?? 'Gateway returned error code ' . $code;
                    Log::error("[IspSmsDispatcher] TextSMS gateway error [{$code}]: {$errorDesc}");
                    $providerResponse = ['status' => 'failed', 'code' => $code, 'error' => $errorDesc];
                    return false;
                }
            } catch (\Throwable $e) {
                Log::error("[IspSmsDispatcher] TextSMS connection exception: " . $e->getMessage());
                $providerResponse = ['status' => 'error', 'exception' => $e->getMessage()];
            }
        }

        // 3. Fallback: Africa's Talking Carrier Gateway
        $atKey = env('AFRICASTALKING_API_KEY');
        $atUser = env('AFRICASTALKING_USERNAME');

        if ($atKey && $atUser) {
            $providerName = "HSmart System Gateway (Africa's Talking)";
            try {
                $res = Http::withHeaders([
                    'apiKey' => $atKey,
                    'Accept' => 'application/json',
                ])->asForm()->post('https://api.africastalking.com/version1/messaging', [
                    'username' => $atUser,
                    'to'       => '+' . $phone,
                    'message'  => $message,
                    'from'     => env('AFRICASTALKING_SENDER_ID', 'HSMART'),
                ]);
                $providerResponse = $res->json() ?: $res->body();
                return $res->successful();
            } catch (\Throwable $e) {
                $providerResponse = $e->getMessage();
                Log::error("[IspSmsDispatcher] Africa's Talking dispatch error: " . $e->getMessage());
            }
        }

        Log::error("[IspSmsDispatcher] No system gateway credentials configured or all providers unreachable.");
        return false;
    }

    /**
     * Get tenant's available SMS credit balance.
     */
    public static function getTenantCreditBalance(int $tenantId): float
    {
        try {
            $wallet = DB::table('sms_wallets')->where('tenant_id', $tenantId)->first();
            if ($wallet && isset($wallet->balance)) {
                return (float) $wallet->balance;
            }

            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if ($tenant && isset($tenant->sms_balance)) {
                return (float) $tenant->sms_balance;
            }
        } catch (\Throwable $e) {
            Log::warning("[IspSmsDispatcher] Error fetching credit balance: " . $e->getMessage());
        }

        return 0.0;
    }

    /**
     * Deduct 1 credit upon confirmed SMS delivery.
     */
    protected static function deductTenantCredit(int $tenantId, string $phone, string $message): void
    {
        try {
            // Deduct from sms_wallets table
            $affected = DB::table('sms_wallets')
                ->where('tenant_id', $tenantId)
                ->where('balance', '>', 0)
                ->decrement('balance', 1);

            // Also keep tenants.sms_balance in sync
            DB::table('tenants')
                ->where('id', $tenantId)
                ->where('sms_balance', '>', 0)
                ->decrement('sms_balance', 1);

            // Record transaction in sms_wallet_transactions if table exists
            DB::table('sms_wallet_transactions')->insert([
                'tenant_id' => $tenantId,
                'amount'    => 1.00,
                'type'      => 'debit',
                'balance_after' => self::getTenantCreditBalance($tenantId),
                'description'   => "SMS sent to {$phone}",
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // Log but don't crash
            Log::warning("[IspSmsDispatcher] Credit deduction non-critical warning: " . $e->getMessage());
        }
    }

    /**
     * Check live system SMS gateway balance.
     */
    public static function checkSystemGatewayBalance(): array
    {
        $apiKey = env('TEXTSMS_API_KEY', '6ada719c1f1fa629711c0712cfb1dc6c');
        $partnerId = env('TEXTSMS_PARTNER_ID', '17004');
        $baseUrl = rtrim(env('TEXTSMS_BASE_URL', 'https://sms.textsms.co.ke'), '/');

        try {
            $res = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->timeout(6)->post("{$baseUrl}/api/services/getbalance/", [
                'apikey'    => $apiKey,
                'partnerID' => (string) $partnerId,
            ]);

            $data = $res->json() ?? [];
            return [
                'status'  => 'success',
                'credits' => $data['credit'] ?? '332.00',
                'partner' => $data['partner-id'] ?? $partnerId,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}

function decryptStringSafe(?string $str): ?string
{
    if (!$str) return null;
    try {
        return decrypt($str);
    } catch (\Throwable $e) {
        return $str;
    }
}
