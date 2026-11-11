<?php

namespace App\Services;

use App\Models\MpesaStkTransaction;
use App\Models\PaymentChannel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayHeroPaymentService
{
    // Platform Master Admin Credentials (shared across ISP tenants)
    const MASTER_BASE_URL    = 'https://backend.payhero.co.ke/api/v2';
    const MASTER_ACCOUNT_ID  = '4174';
    const MASTER_USERNAME    = 'AlD1RTRXyrKjjnZjl3EP';
    const MASTER_PASSWORD    = 'QTQSyLfrkmKDNybz6ubECRYfFhLGV0znAVUuQ7DQ';
    const MASTER_BASIC_AUTH  = 'Basic QWxEMVJUUlh5cktqam5ZamwzRVA6UVRRU3lMZnJrbUtETnliczZ1YkVDUllmRmhMR1Ywem5BVlV1UTdEUQ==';

    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;
    protected ?string $channelId;
    protected ?int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->baseUrl = rtrim(siteUrlSettings('payhero_base_url') ?: config('services.payhero.base_url', self::MASTER_BASE_URL), '/');

        // Check for tenant-specific or environment overrides; default to master credentials
        $rawKey     = siteUrlSettings('payhero_api_key') ?: config('services.payhero.api_key', env('PAYHERO_API_KEY', self::MASTER_USERNAME));
        $rawSecret  = siteUrlSettings('payhero_api_secret') ?: config('services.payhero.api_secret', env('PAYHERO_API_SECRET', self::MASTER_PASSWORD));
        $rawChannel = siteUrlSettings('payhero_channel_id') ?: config('services.payhero.channel_id', env('PAYHERO_CHANNEL_ID', self::MASTER_ACCOUNT_ID));

        $this->apiKey    = $this->safeDecrypt($rawKey);
        $this->apiSecret = $this->safeDecrypt($rawSecret);
        $this->channelId = $this->safeDecrypt($rawChannel);

        if ($tenantId) {
            $this->loadTenantChannel($tenantId);
        }
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

    public static function forTenant(?int $tenantId = null): self
    {
        return new self($tenantId);
    }

    protected function loadTenantChannel(int $tenantId): void
    {
        $channel = PaymentChannel::where('tenant_id', $tenantId)
            ->where('gateway', 'payhero')
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first();

        if ($channel) {
            if ($channel->payhero_channel_id) {
                $this->channelId = (string) $channel->payhero_channel_id;
            }
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    protected function getClient()
    {
        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => self::MASTER_BASIC_AUTH,
        ];

        return Http::withHeaders($headers)->baseUrl($this->baseUrl);
    }

    /**
     * Retrieve all payment channels configured on PayHero.
     */
    public function getChannels(): array
    {
        try {
            $response = $this->getClient()->get('/payment_channels');
            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('PayHero getChannels failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve active payment channels from PayHero.
     */
    public function getActiveChannels(): array
    {
        try {
            $res = $this->getChannels();
            $list = $res['payment_channels'] ?? $res['data'] ?? (isset($res[0]) ? $res : []);
            
            $active = array_values(array_filter($list, function ($c) {
                return !empty($c['is_active']);
            }));

            return !empty($active) ? $active : $list;
        } catch (\Throwable $e) {
            Log::warning('PayHero getActiveChannels failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Register a new settlement channel (Till, Paybill, or Bank) under master account.
     */
    public function registerChannel(array $data): array
    {
        $channelType = in_array(strtolower($data['channel_type'] ?? ''), ['till', 'buy_goods']) ? 'till' : 'paybill';
        $shortCode   = trim((string) ($data['short_code'] ?? ''));
        $accountNo   = trim((string) ($data['account_number'] ?? $shortCode));
        $description = trim((string) ($data['description'] ?? "ISP Channel {$shortCode}"));

        $payload = [
            'channel_type'   => $channelType,
            'account_id'     => (int) self::MASTER_ACCOUNT_ID,
            'short_code'     => $shortCode,
            'account_number' => $accountNo ?: $shortCode,
            'description'    => $description,
        ];

        Log::info('PayHero Register Channel Payload:', $payload);

        try {
            $response = $this->getClient()->post('/payment_channels', $payload);
            $res = $response->json();

            Log::info('PayHero Register Channel Response:', ['status' => $response->status(), 'body' => $res]);

            if ($response->successful()) {
                return array_merge(['success' => true], is_array($res) ? $res : []);
            }

            // If already registered or error, search active channels on PayHero
            $activeChannels = $this->getActiveChannels();
            foreach ($activeChannels as $ch) {
                $chShort = (string) ($ch['short_code'] ?? ($ch['paybill'] ?? ($ch['till'] ?? '')));
                $chAcc   = (string) ($ch['account_number'] ?? '');
                if ($shortCode === $chShort || $shortCode === $chAcc) {
                    return [
                        'success'    => true,
                        'channel_id' => (int) $ch['id'],
                        'id'         => (int) $ch['id'],
                        'message'    => 'Existing active channel matched.',
                    ];
                }
            }

            return [
                'success' => false,
                'message' => $res['message'] ?? $res['error_message'] ?? 'Failed to register channel on PayHero.',
            ];
        } catch (\Throwable $e) {
            Log::error('PayHero registerChannel exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Initiate STK Push via PayHero with automatic channel resolution.
     */
    public function initiatePayment(string $phone, float $amount, string $customerReference, ?int $channelId = null, ?string $shortCode = null, ?string $channelType = null): array
    {
        $cleanPhone = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($phone) : null;
        if (!$cleanPhone) {
            $raw = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($raw, '0') && strlen($raw) === 10) {
                $cleanPhone = '254' . substr($raw, 1);
            } elseif ((str_starts_with($raw, '7') || str_starts_with($raw, '1')) && strlen($raw) === 9) {
                $cleanPhone = '254' . $raw;
            } elseif (str_starts_with($raw, '254') && strlen($raw) === 12) {
                $cleanPhone = $raw;
            }
        }

        if (!$cleanPhone) {
            return ['success' => false, 'message' => 'Invalid Kenyan mobile phone number.'];
        }

        // 1. Resolve target channel ID
        $resolvedChannelId = null;

        if ($channelId && is_numeric($channelId) && (int)$channelId > 0 && strlen((string)$channelId) <= 6) {
            $resolvedChannelId = (int) $channelId;
        }

        // Check tenant's default active channel
        if (!$resolvedChannelId && $this->tenantId) {
            $channel = PaymentChannel::where('tenant_id', $this->tenantId)
                ->where('gateway', 'payhero')
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->first();

            if ($channel && $channel->payhero_channel_id) {
                $resolvedChannelId = (int) $channel->payhero_channel_id;
            }
        }

        // If not found by tenant, match by short code against active channels
        if (!$resolvedChannelId && !empty($shortCode)) {
            $activeChannels = $this->getActiveChannels();
            foreach ($activeChannels as $ch) {
                $chShort = (string) ($ch['short_code'] ?? ($ch['paybill'] ?? ($ch['till'] ?? '')));
                $chAcc   = (string) ($ch['account_number'] ?? '');
                if ((string)$shortCode === $chShort || (string)$shortCode === $chAcc) {
                    $resolvedChannelId = (int) $ch['id'];
                    break;
                }
            }
        }

        // Fallback: check all active PayHero channels
        if (!$resolvedChannelId) {
            $activeChannels = $this->getActiveChannels();
            if (!empty($activeChannels[0]['id'])) {
                $resolvedChannelId = (int) $activeChannels[0]['id'];
            }
        }

        if (!$resolvedChannelId) {
            return [
                'success' => false,
                'message' => 'No active PayHero settlement channel could be resolved. Please configure a payment channel.',
            ];
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
            'channel_id'         => (int) $resolvedChannelId,
            'provider'           => 'm-pesa',
            'external_reference' => $extRef,
            'callback_url'       => $callbackUrl,
        ];

        Log::info('PayHero Initiation Payload:', $payload);

        try {
            $response = $this->getClient()->post('/payments', $payload);
            $res = $response->json();

            Log::info('PayHero Initiation Response:', ['status' => $response->status(), 'body' => $res]);

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
                    'message'             => $res['message'] ?? 'STK push prompt sent to phone. Enter M-Pesa PIN.',
                    'checkout_request_id' => $checkoutId,
                    'reference'           => $checkoutId,
                    'external_reference'  => $extRef,
                ];
            }

            return [
                'success' => false,
                'message' => $res['message'] ?? $res['error_message'] ?? $res['error'] ?? 'PayHero payment failed to initiate.',
            ];
        } catch (\Throwable $e) {
            Log::error('PayHero initiation exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection error contacting PayHero: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reverse-verify transaction status with PayHero API.
     */
    public function verifyStatus(string $reference): array
    {
        try {
            $response = $this->getClient()->get('/transaction-status', [
                'reference' => $reference,
            ]);

            Log::info("PayHero reverse-verification for [{$reference}]: " . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                $respObj = $data['response'] ?? $data;
                $status = strtoupper(trim((string) ($respObj['status'] ?? $data['status'] ?? '')));

                return [
                    'verified' => true,
                    'status'   => $status,
                    'is_paid'  => in_array($status, ['SUCCESS', 'COMPLETED', 'SUCCESSFUL'], true),
                    'data'     => $data,
                ];
            }

            return ['verified' => false, 'status' => 'PENDING', 'is_paid' => false, 'error' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('PayHero verifyStatus failed: ' . $e->getMessage());
            return ['verified' => false, 'status' => 'ERROR', 'is_paid' => false, 'error' => $e->getMessage()];
        }
    }
}
