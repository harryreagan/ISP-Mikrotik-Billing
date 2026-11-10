<?php

namespace App\Services;

use App\Models\MpesaStkTransaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayHeroPaymentService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;
    protected ?string $channelId;

    public function __construct()
    {
        $this->baseUrl = rtrim(siteUrlSettings('payhero_base_url') ?: config('services.payhero.base_url', 'https://backend.payhero.co.ke/api/v2'), '/');
        
        $rawKey = siteUrlSettings('payhero_api_key') ?: config('services.payhero.api_key', env('PAYHERO_API_KEY'));
        $rawSecret = siteUrlSettings('payhero_api_secret') ?: config('services.payhero.api_secret', env('PAYHERO_API_SECRET'));
        $rawChannel = siteUrlSettings('payhero_channel_id') ?: config('services.payhero.channel_id', env('PAYHERO_CHANNEL_ID'));

        $this->apiKey = $this->safeDecrypt($rawKey);
        $this->apiSecret = $this->safeDecrypt($rawSecret);
        $this->channelId = $this->safeDecrypt($rawChannel);
    }

    protected function safeDecrypt(?string $value): ?string
    {
        if (empty($value)) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->channelId);
    }

    protected function getClient()
    {
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey && $this->apiSecret) {
            return Http::withHeaders($headers)->withBasicAuth($this->apiKey, $this->apiSecret);
        }

        // Single Bearer / API token fallback
        return Http::withHeaders(array_merge($headers, [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]));
    }

    /**
     * Initiate STK Push via PayHero.
     */
    public function initiatePayment(string $phone, float $amount, string $customerReference): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'PayHero gateway is not configured.'];
        }

        $cleanPhone = normalizeKenyanPhone($phone);
        if (!$cleanPhone) {
            return ['success' => false, 'message' => 'Invalid Kenyan mobile phone number.'];
        }

        $callbackUrl = siteUrlSettings('payhero_callback_url') 
            ?: (config('services.payhero.callback_url') ?: url('/api/payment/payhero/webhook'));

        if (str_contains($callbackUrl, 'localhost') || str_contains($callbackUrl, '127.0.0.1')) {
            $callbackUrl = 'https://isp.hsmart.co.ke/api/payment/payhero/webhook';
        }

        $extRef = 'HSM-' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $customerReference), 0, 15) . '-' . time();

        $payload = [
            'amount'             => (float) $amount,
            'phone_number'       => $cleanPhone,
            'channel_id'         => (int) $this->channelId,
            'provider'           => 'm-pesa',
            'external_reference' => $extRef,
            'callback_url'       => $callbackUrl,
        ];

        try {
            $response = $this->getClient()->post("{$this->baseUrl}/payments", $payload);
            $res = $response->json();

            if ($response->successful()) {
                $checkoutId = $res['reference'] ?? $res['checkout_request_id'] ?? $extRef;

                MpesaStkTransaction::create([
                    'gateway'             => 'payhero',
                    'checkout_request_id' => $checkoutId,
                    'amount'              => $amount,
                    'phone'               => $cleanPhone,
                    'reference'           => $customerReference,
                    'status'              => 'pending',
                ]);

                return [
                    'success'             => true,
                    'message'             => $res['message'] ?? 'STK push prompt sent to mobile.',
                    'reference'           => $checkoutId,
                    'external_reference'  => $extRef,
                ];
            }

            Log::error('PayHero initiation failed: ' . $response->body());
            return [
                'success' => false,
                'message' => $res['message'] ?? $res['error'] ?? 'PayHero payment failed to initiate.',
            ];
        } catch (\Exception $e) {
            Log::error('PayHero initiation exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection error contacting PayHero: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reverse-verify transaction status with PayHero before fulfillment.
     */
    public function verifyStatus(string $reference): array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/transaction-status", [
                'reference' => $reference,
            ]);

            Log::info("PayHero reverse-verification response for [{$reference}]: " . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'verified' => true,
                    'data'     => $data,
                ];
            }

            return ['verified' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error('PayHero verifyStatus failed: ' . $e->getMessage());
            return ['verified' => false, 'error' => $e->getMessage()];
        }
    }
}

