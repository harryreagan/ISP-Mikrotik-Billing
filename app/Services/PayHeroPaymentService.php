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
        $channel = PaymentChannel::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('gateway', 'payhero')
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($channel && !empty($channel->payhero_channel_id)) {
            $this->channelId = (string) $channel->payhero_channel_id;
            return;
        }

        // Secondary tenant-scoped fallback: tenants.payhero_channel_id column
        try {
            $tenantChannelId = \Illuminate\Support\Facades\DB::table('tenants')
                ->where('id', $tenantId)
                ->value('payhero_channel_id');
            if (!empty($tenantChannelId)) {
                $this->channelId = (string) $tenantChannelId;
            }
        } catch (\Throwable $e) {}
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
     * Initiate STK Push via PayHero with strict per-tenant channel isolation.
     */
    public function initiatePayment(string $phone, float $amount, string $customerReference, ?int $channelId = null, ?string $shortCode = null, ?string $channelType = null, array $meta = []): array
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

        // 1. STRICT TENANT-ISOLATED CHANNEL RESOLUTION (NEVER fall back to another merchant's PayHero channel!)
        $resolvedChannelId = null;
        $channelLabel = null;

        if ($channelId && is_numeric($channelId) && (int) $channelId > 0 && strlen((string) $channelId) <= 6) {
            $resolvedChannelId = (int) $channelId;
        }

        // Look up THIS tenant's active default channel bypassing session TenantScope (safe for public captive portal API)
        if (!$resolvedChannelId && $this->tenantId) {
            $channel = PaymentChannel::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('gateway', 'payhero')
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($channel) {
                $channelLabel = $channel->channel_name ?: ($channel->short_code . ' / ' . $channel->account_number);
                if (!empty($channel->payhero_channel_id) && is_numeric($channel->payhero_channel_id)) {
                    $resolvedChannelId = (int) $channel->payhero_channel_id;
                } elseif (!empty($channel->short_code)) {
                    // Match THIS tenant's exact short_code + account_number on PayHero
                    $activeChannels = $this->getActiveChannels();
                    foreach ($activeChannels as $ch) {
                        $chShort = (string) ($ch['short_code'] ?? '');
                        $chAcc   = (string) ($ch['account_number'] ?? '');
                        if ($chShort === (string) $channel->short_code && (empty($channel->account_number) || $chAcc === (string) $channel->account_number)) {
                            $resolvedChannelId = (int) $ch['id'];
                            $channel->updateQuietly(['payhero_channel_id' => $resolvedChannelId]);
                            break;
                        }
                    }
                }
            }

            // Secondary check: tenants.payhero_channel_id column for THIS tenant
            if (!$resolvedChannelId) {
                try {
                    $tenantRow = \Illuminate\Support\Facades\DB::table('tenants')->where('id', $this->tenantId)->first();
                    if ($tenantRow && !empty($tenantRow->payhero_channel_id) && is_numeric($tenantRow->payhero_channel_id)) {
                        $resolvedChannelId = (int) $tenantRow->payhero_channel_id;
                    }
                } catch (\Throwable $e) {}
            }
        }

        // Only match by explicit shortCode if caller supplied one
        if (!$resolvedChannelId && !empty($shortCode)) {
            $activeChannels = $this->getActiveChannels();
            foreach ($activeChannels as $ch) {
                $chShort = (string) ($ch['short_code'] ?? ($ch['paybill'] ?? ($ch['till'] ?? '')));
                $chAcc   = (string) ($ch['account_number'] ?? '');
                if ((string) $shortCode === $chShort || (string) $shortCode === $chAcc) {
                    $resolvedChannelId = (int) $ch['id'];
                    break;
                }
            }
        }

        if (!$resolvedChannelId) {
            Log::warning("PayHero isolation guard: Tenant #{$this->tenantId} has no active payment channel configured. Refusing cross-tenant fallback.");
            return [
                'success' => false,
                'message' => 'This ISP has not linked an active M-Pesa payment channel yet. Please configure Payment Gateways in your ISP dashboard.',
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

        Log::info("PayHero Initiation Payload (Tenant #{$this->tenantId}, Channel={$resolvedChannelId}):", $payload);

        try {
            $response = $this->getClient()->post('/payments', $payload);
            $res = $response->json();

            Log::info('PayHero Initiation Response:', ['status' => $response->status(), 'body' => $res]);

            if ($response->successful()) {
                $merchantRef = $res['reference'] ?? $extRef;
                $checkoutId  = $res['CheckoutRequestID'] ?? $res['checkout_request_id'] ?? $merchantRef;

                $storedMeta = array_merge($meta, [
                    'tenant_id'          => $this->tenantId ?: 1,
                    'channel_id'         => (int) $resolvedChannelId,
                    'channel_label'      => $channelLabel,
                    'external_reference' => $extRef,
                    'merchant_reference' => $merchantRef,
                    'checkout_request_id'=> $checkoutId,
                ]);

                MpesaStkTransaction::withoutGlobalScopes()->create([
                    'tenant_id'           => $this->tenantId ?: 1,
                    'gateway'             => 'payhero',
                    'merchant_request_id' => $merchantRef,
                    'checkout_request_id' => $checkoutId,
                    'amount'              => $amount,
                    'phone'               => $cleanPhone,
                    'reference'           => $extRef,
                    'status'              => 'pending',
                    'raw_callback'        => $storedMeta,
                ]);

                return [
                    'success'             => true,
                    'message'             => $res['message'] ?? 'STK push prompt sent to phone. Enter M-Pesa PIN.',
                    'checkout_request_id' => $checkoutId,
                    'reference'           => $merchantRef,
                    'external_reference'  => $extRef,
                    'channel_id'          => (int) $resolvedChannelId,
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
     * Reverse-verify transaction status with PayHero API (supports both PascalCase and lowercase responses).
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
                $respObj = (isset($data['response']) && is_array($data['response'])) ? $data['response'] : $data;
                $rawStatus = $respObj['Status'] ?? $respObj['status'] ?? (is_string($data['status'] ?? null) ? $data['status'] : '');
                $status = strtoupper(trim((string) $rawStatus));
                $resultCode = $respObj['ResultCode'] ?? $respObj['result_code'] ?? null;

                $isPaid = in_array($status, ['SUCCESS', 'COMPLETED', 'SUCCESSFUL'], true)
                    || ($resultCode !== null && is_numeric($resultCode) && (int) $resultCode === 0);

                $isFailed = in_array($status, ['FAILED', 'CANCELLED', 'CANCELED', 'REJECTED', 'TIMEOUT'], true)
                    || ($resultCode !== null && is_numeric($resultCode) && (int) $resultCode !== 0);

                return [
                    'verified'  => true,
                    'status'    => $status,
                    'is_paid'   => $isPaid,
                    'is_failed' => $isFailed,
                    'message'   => $respObj['ResultDesc'] ?? $respObj['result_desc'] ?? null,
                    'data'      => $data,
                ];
            }

            return ['verified' => false, 'status' => 'PENDING', 'is_paid' => false, 'is_failed' => false, 'error' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('PayHero verifyStatus failed: ' . $e->getMessage());
            return ['verified' => false, 'status' => 'ERROR', 'is_paid' => false, 'is_failed' => false, 'error' => $e->getMessage()];
        }
    }
}
