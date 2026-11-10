<?php

namespace App\Services;

use App\Models\MpesaStkTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KopoKopoPaymentService
{
    protected string $baseUrl;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $tillNumber;
    protected ?string $webhookSecret;
    protected string $env;

    public function __construct()
    {
        $this->env = siteUrlSettings('kopokopo_env') ?: config('services.kopokopo.env', env('KOPOKOPO_ENV', 'sandbox'));
        $this->baseUrl = $this->env === 'production'
            ? 'https://api.kopokopo.com'
            : 'https://api-sandbox.kopokopo.com';

        $rawClient = siteUrlSettings('kopokopo_client_id') ?: config('services.kopokopo.client_id', env('KOPOKOPO_CLIENT_ID'));
        $rawSecret = siteUrlSettings('kopokopo_client_secret') ?: config('services.kopokopo.client_secret', env('KOPOKOPO_CLIENT_SECRET'));
        $rawTill   = siteUrlSettings('kopokopo_till_number') ?: config('services.kopokopo.till_number', env('KOPOKOPO_TILL_NUMBER'));
        $rawWhSec  = siteUrlSettings('kopokopo_webhook_secret') ?: config('services.kopokopo.webhook_secret', env('KOPOKOPO_WEBHOOK_SECRET'));

        $this->clientId      = $this->safeDecrypt($rawClient);
        $this->clientSecret  = $this->safeDecrypt($rawSecret);
        $this->tillNumber    = $this->safeDecrypt($rawTill);
        $this->webhookSecret = $this->safeDecrypt($rawWhSec);
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
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    /**
     * OAuth2 Token generation with Redis/Laravel Cache caching.
     */
    public function getAccessToken(): ?string
    {
        if (!$this->isConfigured()) {
            Log::error('KopoKopo: Missing Client ID or Client Secret.');
            return null;
        }

        $cacheKey = 'kopokopo_access_token_' . md5($this->clientId . $this->env);

        return Cache::remember($cacheKey, 7000, function () {
            try {
                $response = Http::asForm()
                    ->timeout(15)
                    ->post("{$this->baseUrl}/oauth/token", [
                        'client_id'     => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'grant_type'    => 'client_credentials',
                    ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('KopoKopo OAuth error: ' . $response->body());
                return null;
            } catch (\Exception $e) {
                Log::error('KopoKopo OAuth exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Initiate STK Push via KopoKopo.
     */
    public function initiateStkPush(string $phone, float $amount, string $customerReference, string $customerName = 'Customer'): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to obtain KopoKopo authorization token.'];
        }

        $cleanPhone = normalizeKenyanPhone($phone);
        if (!$cleanPhone) {
            return ['success' => false, 'message' => 'Invalid Kenyan mobile phone number.'];
        }

        $formattedPhone = '+' . $cleanPhone;
        $nameParts = explode(' ', trim($customerName));
        $firstName = $nameParts[0] ?: 'Valued';
        $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : 'Customer';

        $callbackUrl = siteUrlSettings('kopokopo_callback_url') 
            ?: (config('services.kopokopo.callback_url') ?: url('/api/payment/kopokopo/webhook'));

        if (str_contains($callbackUrl, 'localhost') || str_contains($callbackUrl, '127.0.0.1')) {
            $callbackUrl = 'https://isp.hsmart.co.ke/api/payment/kopokopo/webhook';
        }

        $payload = [
            'payment_channel' => 'M-PESA STK Push',
            'till_number'     => $this->tillNumber ?: 'K' . substr($cleanPhone, -6),
            'subscriber'      => [
                'first_name'   => substr($firstName, 0, 30),
                'last_name'    => substr($lastName, 0, 30),
                'phone_number' => $formattedPhone,
            ],
            'amount' => [
                'currency' => 'KES',
                'value'    => number_format($amount, 2, '.', ''),
            ],
            'metadata' => [
                'customer_reference' => $customerReference,
            ],
            '_links' => [
                'callback_url' => $callbackUrl,
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept'       => 'application/vnd.kopokopo.v4.hal+json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(20)
                ->post("{$this->baseUrl}/api/v1/incoming_payments", $payload);

            if ($response->status() === 201) {
                // KopoKopo returns location header with payment resource URL
                $location = $response->header('Location');
                $paymentId = basename(parse_url($location, PHP_URL_PATH));

                MpesaStkTransaction::create([
                    'gateway'             => 'kopokopo',
                    'checkout_request_id' => $paymentId,
                    'amount'              => $amount,
                    'phone'               => $cleanPhone,
                    'reference'           => $customerReference,
                    'status'              => 'pending',
                ]);

                return [
                    'success'    => true,
                    'message'    => 'STK prompt sent to mobile via KopoKopo.',
                    'payment_id' => $paymentId,
                    'location'   => $location,
                ];
            }

            Log::error('KopoKopo STK Push failed: ' . $response->body());
            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to initiate KopoKopo STK Push.',
            ];
        } catch (\Exception $e) {
            Log::error('KopoKopo STK Push exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Network error: ' . $e->getMessage()];
        }
    }

    /**
     * Reverse-verify transaction status with KopoKopo API.
     */
    public function verifyPayment(string $paymentId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['verified' => false, 'error' => 'No access token'];
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.kopokopo.v4.hal+json'])
                ->get("{$this->baseUrl}/api/v1/incoming_payments/{$paymentId}");

            Log::info("KopoKopo verifyPayment response for [{$paymentId}]: " . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['data']['attributes']['status'] ?? null;
                $isSuccess = in_array(strtolower((string) $status), ['success', 'successful', 'completed'], true);

                return [
                    'verified' => $isSuccess,
                    'status'   => $status,
                    'data'     => $data,
                ];
            }

            return ['verified' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error('KopoKopo verifyPayment exception: ' . $e->getMessage());
            return ['verified' => false, 'error' => $e->getMessage()];
        }
    }
}

