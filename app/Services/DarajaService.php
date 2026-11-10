<?php

namespace App\Services;

use App\Models\MpesaStkTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DarajaService
{
    protected string $env;
    protected string $baseUrl;
    protected ?string $consumerKey;
    protected ?string $consumerSecret;
    protected ?string $passkey;
    protected ?string $shortcode;

    public function __construct()
    {
        $this->loadCredentials();
    }

    /**
     * Load credentials from siteUrlSettings (encrypted or raw) or .env fallback.
     */
    protected function loadCredentials(): void
    {
        $this->env = siteUrlSettings('daraja_env') ?: config('services.daraja.env', env('MPESA_ENV', 'sandbox'));
        $this->baseUrl = $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $rawKey = siteUrlSettings('daraja_consumer_key') ?: config('services.daraja.consumer_key', env('MPESA_CONSUMER_KEY'));
        $rawSecret = siteUrlSettings('daraja_consumer_secret') ?: config('services.daraja.consumer_secret', env('MPESA_CONSUMER_SECRET'));
        $rawPasskey = siteUrlSettings('daraja_passkey') ?: config('services.daraja.passkey', env('MPESA_PASSKEY'));
        $rawShortcode = siteUrlSettings('daraja_shortcode') ?: config('services.daraja.shortcode', env('MPESA_SHORTCODE'));

        $this->consumerKey = $this->safeDecrypt($rawKey);
        $this->consumerSecret = $this->safeDecrypt($rawSecret);
        $this->passkey = $this->safeDecrypt($rawPasskey);
        $this->shortcode = $this->safeDecrypt($rawShortcode);
    }

    /**
     * Safely decrypt encrypted credential strings, returning plain text if not encrypted.
     */
    protected function safeDecrypt(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Check if Daraja is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->consumerKey) && !empty($this->consumerSecret) && !empty($this->shortcode) && !empty($this->passkey);
    }

    /**
     * Generate or fetch cached OAuth2 Access Token from Safaricom.
     */
    public function getAccessToken(): ?string
    {
        if (!$this->isConfigured()) {
            Log::error('DarajaService: Missing credentials. Cannot generate access token.');
            return null;
        }

        $cacheKey = 'mpesa_daraja_access_token_' . md5($this->consumerKey . $this->env);

        return Cache::remember($cacheKey, 3300, function () {
            try {
                $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->timeout(15)
                    ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('Daraja OAuth error: ' . $response->body());
                return null;
            } catch (\Exception $e) {
                Log::error('Daraja OAuth exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Initiate STK Push (Lipa Na M-Pesa Online).
     */
    public function initiateStkPush(string $phone, float $amount, string $accountReference, string $description = 'Internet Bill'): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to obtain M-Pesa authorization token.',
            ];
        }

        $normalizedPhone = normalizeKenyanPhone($phone);
        if (!$normalizedPhone) {
            return [
                'success' => false,
                'message' => 'Invalid Kenyan mobile phone number.',
            ];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        $callbackUrl = siteUrlSettings('daraja_callback_url') 
            ?: (config('services.daraja.callback_url') ?: url('/api/payment/daraja/stk-callback'));

        // Safaricom requires public HTTPS callback URL
        if (str_contains($callbackUrl, 'localhost') || str_contains($callbackUrl, '127.0.0.1')) {
            $callbackUrl = 'https://isp.hsmart.co.ke/api/payment/daraja/stk-callback';
        }

        $cleanRef = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $accountReference), 0, 12) ?: 'HSMART';
        $cleanDesc = substr($description, 0, 30);
        $intAmount = (int) ceil($amount);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $intAmount,
            'PartyA'             => $normalizedPhone,
            'PartyB'             => $this->shortcode,
            'PhoneNumber'        => $normalizedPhone,
            'CallBackURL'        => $callbackUrl,
            'AccountReference'   => $cleanRef,
            'TransactionDesc'    => $cleanDesc,
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", $payload);

            $res = $response->json();

            if ($response->successful() && isset($res['ResponseCode']) && $res['ResponseCode'] == '0') {
                // Record pending STK transaction
                MpesaStkTransaction::create([
                    'gateway'             => 'daraja',
                    'merchant_request_id' => $res['MerchantRequestID'] ?? null,
                    'checkout_request_id' => $res['CheckoutRequestID'] ?? null,
                    'amount'              => $intAmount,
                    'phone'               => $normalizedPhone,
                    'reference'           => $accountReference,
                    'status'              => 'pending',
                ]);

                return [
                    'success'             => true,
                    'message'             => $res['CustomerMessage'] ?? 'STK push prompt sent to mobile.',
                    'checkout_request_id' => $res['CheckoutRequestID'] ?? null,
                    'merchant_request_id' => $res['MerchantRequestID'] ?? null,
                ];
            }

            Log::error('Daraja STK Push failed: ' . json_encode($res));
            return [
                'success' => false,
                'message' => $res['errorMessage'] ?? $res['ResponseDescription'] ?? 'Failed to initiate STK Push.',
            ];
        } catch (\Exception $e) {
            Log::error('Daraja STK Push exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Network error connecting to Safaricom: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Query status of an STK Push transaction.
     */
    public function queryStkStatus(string $checkoutRequestId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to obtain access token'];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                    'BusinessShortCode' => $this->shortcode,
                    'Password'          => $password,
                    'Timestamp'         => $timestamp,
                    'CheckoutRequestID' => $checkoutRequestId,
                ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Daraja queryStkStatus failed: ' . $e->getMessage());
            return ['ResultCode' => 999, 'ResultDesc' => $e->getMessage()];
        }
    }
}

