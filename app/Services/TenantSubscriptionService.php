<?php

namespace App\Services;

use App\Models\TenantSubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantSubscriptionService
{
    // Platform Direct Safaricom Daraja Credentials (Super Admin)
    public const DARAJA_SHORTCODE = '4674551';
    public const DARAJA_ENV = 'production';

    // Platform PayHero Fallback Channel
    public const PAYHERO_BASE_URL = 'https://backend.payhero.co.ke/api/v2';
    public const PAYHERO_CHANNEL_ID = 14863; // Paybill 8114356

    public static function getDarajaConsumerKey(): string
    {
        return env('PLATFORM_DARAJA_KEY', base64_decode('djFOMlpGdkZLbVRFdUtYbDg0S0huWFhZZDlaaG4xaXB1cFRXS3ZYRDBYWkwyamt6'));
    }

    public static function getDarajaConsumerSecret(): string
    {
        return env('PLATFORM_DARAJA_SECRET', base64_decode('alpustlSTjBSemxDQXFTbXExcGk1ODExcEdiNllya0NTbVBwbkFETVhhVXBEY2NOWkYzOUdUeE5rbVdnUTBtUw=='));
    }

    public static function getDarajaPasskey(): string
    {
        return env('PLATFORM_DARAJA_PASSKEY', base64_decode('ZDAzMzExODI1ZmJhOTIxODE1ODhhOTI4MjQ2ZTk0MWJiMmYyYTY0NzFiNzBmYzQxOTRjMTQwNmY4OTY1OTk4MQ=='));
    }

    public static function getPayHeroBasicAuth(): string
    {
        return env('PLATFORM_PAYHERO_AUTH', base64_decode('QmFzaWMgUVd4RU1WSlVVbGg1Y2t0cWFtNVphbXd6UlZBNlVWUlJVM2xNWm5KcmJVdEVUbmxpY3paMVlrVkRVbGxtUm1oTVIxWXdlbTVCVmxWMVVUZEVVUT09'));
    }

    public static function getPaystackPublicKey(): string
    {
        return env('PAYSTACK_PUBLIC_KEY', base64_decode('cGtfbGl2ZV8wYzMzODZkMmVhN2Y5MjZjYjAxYzIyYTExYWQ4MGRhNmFiYWMzYTMz'));
    }

    public static function getPaystackSecretKey(): string
    {
        return env('PAYSTACK_SECRET_KEY', base64_decode('c2tfbGl2ZV80MWUyMzAxZjRlOTRiY2YxNmU5ZjBiOGQ0NTY3YTdiZGRlZmY4Zjhh'));
    }

    /**
     * Get platform plans definition
     */
    public static function getPlans(): array
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('platform_plans')) {
                $dbPlans = DB::table('platform_plans')->where('is_active', true)->orderBy('sort_order')->get();
                if ($dbPlans->isNotEmpty()) {
                    return $dbPlans->map(function ($p) {
                        return [
                            'slug' => $p->slug,
                            'name' => $p->name,
                            'base_price_kes' => $p->base_price_kes,
                            'router_limit' => $p->router_limit,
                            'customer_limit' => $p->customer_limit,
                            'features' => is_string($p->features) ? (json_decode($p->features, true) ?: []) : ($p->features ?: []),
                            'is_popular' => (bool) $p->is_popular,
                            'badge' => $p->badge,
                        ];
                    })->toArray();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch platform plans: " . $e->getMessage());
        }

        // Default platform plans fallback
        return [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'base_price_kes' => 400,
                'router_limit' => 2,
                'customer_limit' => 150,
                'features' => [
                    '2 routers included',
                    '150 customers capacity',
                    'Mobile money, cards & bank',
                    'SMS, WhatsApp & email alerts',
                    'Unlimited admin users',
                ],
                'is_popular' => false,
                'badge' => null,
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'base_price_kes' => 1000,
                'router_limit' => 5,
                'customer_limit' => 500,
                'features' => [
                    '5 routers included',
                    '500 customers capacity',
                    'Resellers and vouchers',
                    'SMS, WhatsApp & email alerts',
                    'Unlimited admin users',
                ],
                'is_popular' => true,
                'badge' => 'POPULAR',
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'base_price_kes' => null,
                'router_limit' => null,
                'customer_limit' => null,
                'features' => [
                    'Unlimited routers',
                    'Unlimited customers',
                    'Dedicated server / VPS',
                    'Custom captive portal branding',
                    '24/7 Priority SLA support',
                ],
                'is_popular' => false,
                'badge' => null,
            ],
        ];
    }

    /**
     * Get details of a single plan
     */
    public static function getPlan(string $slug): ?array
    {
        $plans = self::getPlans();
        foreach ($plans as $p) {
            if ($p['slug'] === $slug) return $p;
        }
        return null;
    }

    /**
     * Fetch tenant subscription state and usage
     */
    public static function getTenantSubscription(int $tenantId): array
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            return [
                'status' => 'inactive',
                'plan_slug' => 'starter',
                'plan_name' => 'Starter',
                'expires_at' => null,
                'days_left' => 0,
                'router_count' => 0,
                'router_limit' => 2,
                'customer_count' => 0,
                'customer_limit' => 150,
            ];
        }

        $planSlug = $tenant->plan_slug ?: 'starter';
        $plan = self::getPlan($planSlug) ?: self::getPlans()[0];

        $expiresAt = $tenant->subscription_expires_at ? Carbon::parse($tenant->subscription_expires_at) : null;
        $now = Carbon::now();

        $status = $tenant->subscription_status ?: 'trial';
        $daysLeft = 0;

        if ($expiresAt) {
            if ($expiresAt->isPast()) {
                $status = 'expired';
                $daysLeft = 0;
            } else {
                $daysLeft = (int) $now->diffInDays($expiresAt, false);
                if ($daysLeft <= 0 && $expiresAt->isFuture()) {
                    $daysLeft = 1; // Less than 24h remaining
                }
            }
        } elseif ($status === 'trial') {
            $trialEnd = Carbon::parse($tenant->created_at)->addDays(14);
            if ($trialEnd->isPast()) {
                $status = 'expired';
                $daysLeft = 0;
            } else {
                $daysLeft = (int) $now->diffInDays($trialEnd, false);
                $expiresAt = $trialEnd;
            }
        }

        // Router usage
        $routerCount = 0;
        try {
            $routerCount = DB::table('routers')->where('tenant_id', $tenantId)->count();
        } catch (\Throwable $e) {}

        // Customer usage
        $customerCount = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('customers_infos')) {
                $customerCount = DB::table('customers_infos')->where('tenant_id', $tenantId)->count();
            }
        } catch (\Throwable $e) {}

        $routerLimit = (int) ($tenant->router_limit ?: ($plan['router_limit'] ?: 2));
        $customerLimit = (int) ($tenant->customer_limit ?: ($plan['customer_limit'] ?: 150));

        return [
            'tenant' => $tenant,
            'status' => $status,
            'plan_slug' => $planSlug,
            'plan_name' => $plan['name'] ?? ucfirst($planSlug),
            'plan_price' => $plan['base_price_kes'] ?? 400,
            'expires_at' => $expiresAt ? $expiresAt->toDateTimeString() : null,
            'expires_at_formatted' => $expiresAt ? $expiresAt->format('d M Y, h:i A') : 'Not set',
            'days_left' => $daysLeft,
            'router_count' => $routerCount,
            'router_limit' => $routerLimit,
            'customer_count' => $customerCount,
            'customer_limit' => $customerLimit,
        ];
    }

    /**
     * Get Daraja OAuth access token
     */
    protected static function getDarajaAccessToken(): ?string
    {
        try {
            $url = (self::DARAJA_ENV === 'production')
                ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
                : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

            $response = Http::timeout(15)
                ->withBasicAuth(self::getDarajaConsumerKey(), self::getDarajaConsumerSecret())
                ->get($url);

            if ($response->successful()) {
                $body = $response->json();
                return $body['access_token'] ?? null;
            }

            Log::error("Daraja OAuth failed: HTTP " . $response->status() . " - " . $response->body());
        } catch (\Throwable $e) {
            Log::error("Daraja OAuth exception: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Initiate M-Pesa STK Push for subscription.
     * Uses Platform Direct Safaricom Daraja credentials, with PayHero fallback.
     */
    public static function initiateMpesaStk(int $tenantId, string $planSlug, string $phone): array
    {
        $plan = self::getPlan($planSlug);
        if (!$plan || empty($plan['base_price_kes'])) {
            return ['status' => 'error', 'message' => 'Invalid plan or enterprise custom tier selected.'];
        }

        // Format phone to 254XXXXXXXXX
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            $phone = '254' . $phone;
        }

        if (strlen($phone) !== 12 || !str_starts_with($phone, '254')) {
            return ['status' => 'error', 'message' => 'Please enter a valid Safaricom phone number (e.g. 0712345678 or 254712345678).'];
        }

        $amount = (float) $plan['base_price_kes'];
        $reference = 'SUB-' . strtoupper(Str::random(10));

        // Create pending payment record
        $payment = TenantSubscriptionPayment::create([
            'tenant_id' => $tenantId,
            'plan_slug' => $planSlug,
            'billing_cycle' => 'monthly',
            'amount' => $amount,
            'currency' => 'KES',
            'gateway' => 'mpesa',
            'reference' => $reference,
            'phone' => $phone,
            'status' => 'pending',
            'metadata' => [
                'plan_name' => $plan['name'],
                'initiated_at' => now()->toIso8601String(),
            ],
        ]);

        // Attempt 1: Direct Safaricom Daraja STK Push
        $token = self::getDarajaAccessToken();
        if ($token) {
            try {
                $timestamp = date('YmdHis');
                $password = base64_encode(self::DARAJA_SHORTCODE . self::getDarajaPasskey() . $timestamp);
                $callbackUrl = 'https://pos.hsmart.co.ke/api/mpesa/stk/callback'; // POS verified callback

                $url = (self::DARAJA_ENV === 'production')
                    ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
                    : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

                $darajaPayload = [
                    'BusinessShortCode' => self::DARAJA_SHORTCODE,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => (int) $amount,
                    'PartyA' => $phone,
                    'PartyB' => self::DARAJA_SHORTCODE,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $callbackUrl,
                    'AccountReference' => 'HSmart ISP',
                    'TransactionDesc' => "ISP Plan {$plan['name']}",
                ];

                $darajaResp = Http::timeout(20)
                    ->withToken($token)
                    ->post($url, $darajaPayload);

                $body = $darajaResp->json();
                Log::info("Daraja STK push response for tenant {$tenantId}:", ['body' => $body]);

                if ($darajaResp->successful() && ($body['ResponseCode'] ?? null) === '0') {
                    $checkoutId = $body['CheckoutRequestID'] ?? null;
                    if ($checkoutId) {
                        $payment->update([
                            'external_reference' => $checkoutId,
                            'metadata' => array_merge($payment->metadata ?? [], ['checkout_request_id' => $checkoutId, 'gateway_provider' => 'daraja_direct']),
                        ]);
                    }

                    return [
                        'status' => 'success',
                        'message' => 'STK prompt sent to your phone. Enter your M-Pesa PIN to complete payment.',
                        'reference' => $reference,
                        'external_reference' => $checkoutId,
                        'amount' => $amount,
                        'phone' => $phone,
                    ];
                }

                Log::warning("Daraja direct returned non-zero code, falling back to PayHero: " . json_encode($body));
            } catch (\Throwable $e) {
                Log::warning("Daraja direct STK exception, falling back to PayHero: " . $e->getMessage());
            }
        }

        // Attempt 2: PayHero Platform Gateway Fallback
        try {
            $response = Http::timeout(20)->withHeaders([
                'Authorization' => self::getPayHeroBasicAuth(),
                'Content-Type' => 'application/json',
            ])->post(self::PAYHERO_BASE_URL . '/payments', [
                'amount' => (int) $amount,
                'phone_number' => $phone,
                'channel_id' => (int) self::PAYHERO_CHANNEL_ID,
                'provider' => 'm-pesa',
                'external_reference' => $reference,
                'callback_url' => url('/api/subscription/callback'),
            ]);

            $body = $response->json();
            Log::info("PayHero subscription STK response for tenant {$tenantId}:", ['body' => $body]);

            if ($response->successful() && ($body['success'] ?? false || !empty($body['CheckoutRequestID']) || !empty($body['reference']))) {
                $extRef = $body['CheckoutRequestID'] ?? $body['reference'] ?? null;
                if ($extRef) {
                    $payment->update([
                        'external_reference' => $extRef,
                        'metadata' => array_merge($payment->metadata ?? [], ['gateway_provider' => 'payhero']),
                    ]);
                }

                return [
                    'status' => 'success',
                    'message' => 'STK prompt sent to your phone. Enter your M-Pesa PIN to complete payment.',
                    'reference' => $reference,
                    'external_reference' => $extRef,
                    'amount' => $amount,
                    'phone' => $phone,
                ];
            }

            $errMsg = $body['message'] ?? $body['error'] ?? 'Failed to initiate M-Pesa payment. Please try again or use Paystack.';
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $errMsg])]);
            return ['status' => 'error', 'message' => $errMsg];
        } catch (\Throwable $e) {
            Log::error("PayHero subscription exception: " . $e->getMessage());
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $e->getMessage()])]);
            return ['status' => 'error', 'message' => 'Payment gateway timeout. Please check your network and try again.'];
        }
    }

    /**
     * Initiate Paystack transaction for subscription.
     * Supports Cards (Visa, Mastercard, Verve), Bank Transfer & M-Pesa worldwide.
     */
    public static function initiatePaystack(int $tenantId, string $planSlug, string $email): array
    {
        $plan = self::getPlan($planSlug);
        if (!$plan || empty($plan['base_price_kes'])) {
            return ['status' => 'error', 'message' => 'Invalid plan or enterprise custom tier selected.'];
        }

        $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'message' => 'Please provide a valid email address for your payment receipt.'];
        }

        $amount = (float) $plan['base_price_kes'];
        $amountCents = (int) round($amount * 100);
        $reference = 'SUB-PS-' . strtoupper(Str::random(10));

        // Create pending payment record
        $payment = TenantSubscriptionPayment::create([
            'tenant_id' => $tenantId,
            'plan_slug' => $planSlug,
            'billing_cycle' => 'monthly',
            'amount' => $amount,
            'currency' => 'KES',
            'gateway' => 'paystack',
            'reference' => $reference,
            'phone' => null,
            'status' => 'pending',
            'metadata' => [
                'plan_name' => $plan['name'],
                'email' => $email,
                'initiated_at' => now()->toIso8601String(),
            ],
        ]);

        try {
            $callbackUrl = route('subscription.paystack.verify', ['reference' => $reference]);
            $response = Http::timeout(20)
                ->withToken(self::getPaystackSecretKey())
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email' => $email,
                    'amount' => $amountCents,
                    'currency' => 'KES',
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata' => [
                        'tenant_id' => $tenantId,
                        'plan_slug' => $planSlug,
                        'purpose' => 'isp_subscription',
                    ],
                ]);

            $result = $response->json();
            Log::info("Paystack subscription initialization for tenant {$tenantId}:", ['result' => $result]);

            if ($response->successful() && ($result['status'] ?? false)) {
                $accessCode = $result['data']['access_code'] ?? null;
                $authUrl = $result['data']['authorization_url'] ?? null;

                $payment->update([
                    'external_reference' => $result['data']['reference'] ?? $reference,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'access_code' => $accessCode,
                        'authorization_url' => $authUrl,
                    ]),
                ]);

                return [
                    'status' => 'success',
                    'message' => 'Paystack checkout initialized.',
                    'access_code' => $accessCode,
                    'authorization_url' => $authUrl,
                    'reference' => $reference,
                    'public_key' => self::getPaystackPublicKey(),
                    'email' => $email,
                    'amount' => $amountCents,
                    'plan_name' => $plan['name'],
                ];
            }

            $errMsg = $result['message'] ?? 'Failed to initialize Paystack checkout.';
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $errMsg])]);
            return ['status' => 'error', 'message' => $errMsg];
        } catch (\Throwable $e) {
            Log::error("Paystack initialization exception: " . $e->getMessage());
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $e->getMessage()])]);
            return ['status' => 'error', 'message' => 'Paystack connection error. Please try again.'];
        }
    }

    /**
     * Verify Paystack transaction
     */
    public static function verifyPaystack(string $reference): array
    {
        try {
            $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
            $response = Http::timeout(20)
                ->withToken(self::getPaystackSecretKey())
                ->get($url);

            $result = $response->json();
            Log::info("Paystack verification response for {$reference}:", ['result' => $result]);

            if ($response->successful() && ($result['status'] ?? false) && ($result['data']['status'] ?? '') === 'success') {
                $amount = (float) (($result['data']['amount'] ?? 0) / 100);
                $extRef = $result['data']['reference'] ?? $reference;

                self::fulfillPayment($reference, $extRef, $amount, $result['data']);

                return [
                    'status' => 'completed',
                    'message' => 'Payment verified successfully and subscription activated!',
                    'reference' => $extRef,
                    'amount' => $amount,
                ];
            }

            $msg = $result['data']['gateway_response'] ?? $result['message'] ?? 'Payment verification failed or transaction not completed.';
            return ['status' => 'failed', 'message' => $msg];
        } catch (\Throwable $e) {
            Log::error("Paystack verify error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error verifying Paystack transaction: ' . $e->getMessage()];
        }
    }

    /**
     * Check payment status and fulfill if successful
     */
    public static function checkPaymentStatus(string $reference): array
    {
        $payment = TenantSubscriptionPayment::where('reference', $reference)
            ->orWhere('external_reference', $reference)
            ->latest('id')
            ->first();

        if (!$payment) {
            return ['status' => 'error', 'message' => 'Payment record not found.'];
        }

        if ($payment->status === 'completed') {
            return [
                'status' => 'completed',
                'message' => 'Payment confirmed and subscription activated!',
                'plan_slug' => $payment->plan_slug,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at ? $payment->paid_at->toDateTimeString() : now()->toDateTimeString(),
            ];
        }

        if ($payment->status === 'failed' || $payment->status === 'cancelled') {
            return ['status' => 'failed', 'message' => 'Payment was cancelled or failed. Please try again.'];
        }

        // If Paystack payment, probe Paystack API directly
        if ($payment->gateway === 'paystack') {
            $vResult = self::verifyPaystack($payment->reference);
            if ($vResult['status'] === 'completed') {
                return $vResult;
            }
        }

        // If PayHero / M-Pesa payment, probe PayHero API
        try {
            $extRef = $payment->external_reference ?: $payment->reference;
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => self::getPayHeroBasicAuth(),
            ])->get(self::PAYHERO_BASE_URL . '/payments/' . $extRef);

            if ($response->successful()) {
                $data = $response->json();
                $payStatus = strtoupper($data['status'] ?? $data['Status'] ?? '');
                $receipt = $data['MpesaReceiptNumber'] ?? $data['receipt'] ?? $data['mpesa_receipt'] ?? null;

                if (in_array($payStatus, ['SUCCESS', 'COMPLETED', 'CONFIRMED', 'PAID'])) {
                    self::fulfillPayment($payment->reference, $receipt ?: ($extRef ?: 'MPESA-' . Str::random(8)), (float) ($data['amount'] ?? $payment->amount), $data);
                    return [
                        'status' => 'completed',
                        'message' => 'Payment confirmed and subscription activated!',
                        'receipt' => $receipt,
                    ];
                } elseif (in_array($payStatus, ['FAILED', 'CANCELLED', 'REJECTED'])) {
                    $payment->update(['status' => 'failed']);
                    return ['status' => 'failed', 'message' => 'Transaction was cancelled or declined.'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Status poll error: " . $e->getMessage());
        }

        return ['status' => 'pending', 'message' => 'Waiting for payment confirmation...'];
    }

    /**
     * Fulfill subscription payment idempotently
     */
    public static function fulfillPayment(string $reference, string $externalRef, ?float $amount = null, array $meta = []): bool
    {
        $payment = TenantSubscriptionPayment::where('reference', $reference)
            ->orWhere('external_reference', $reference)
            ->latest('id')
            ->first();

        if (!$payment) {
            Log::warning("Subscription fulfillment failed: payment reference {$reference} not found.");
            return false;
        }

        if ($payment->status === 'completed') {
            return true; // Already fulfilled idempotently
        }

        $plan = self::getPlan($payment->plan_slug) ?: self::getPlans()[0];
        $tenantId = $payment->tenant_id;

        DB::transaction(function () use ($payment, $plan, $tenantId, $externalRef, $meta) {
            $payment->update([
                'status' => 'completed',
                'external_reference' => $externalRef,
                'paid_at' => now(),
                'metadata' => array_merge($payment->metadata ?? [], $meta),
            ]);

            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if ($tenant) {
                $currentExpiry = $tenant->subscription_expires_at ? Carbon::parse($tenant->subscription_expires_at) : null;
                $newExpiry = ($currentExpiry && $currentExpiry->isFuture())
                    ? $currentExpiry->addDays(30)
                    : now()->addDays(30);

                $routerLimit = (int) ($plan['router_limit'] ?: 2);
                $customerLimit = (int) ($plan['customer_limit'] ?: 150);

                DB::table('tenants')->where('id', $tenantId)->update([
                    'plan_slug' => $payment->plan_slug,
                    'subscription_status' => 'active',
                    'subscription_started_at' => $tenant->subscription_started_at ?: now(),
                    'subscription_expires_at' => $newExpiry,
                    'router_limit' => $routerLimit,
                    'customer_limit' => $customerLimit,
                    'updated_at' => now(),
                ]);
            }
        });

        // Trigger SMS notification to ISP owner
        try {
            $user = DB::table('users')->where('tenant_id', $tenantId)->first();
            $phone = $user->phone ?? ($payment->phone ?? null);
            if ($phone && class_exists('\App\Services\SmsService')) {
                $msg = "HSmart: Your {$plan['name']} subscription payment of KES " . number_format($payment->amount) . " was successful (Ref: {$externalRef}). Valid for 30 days.";
                \App\Services\SmsService::sendDirect($phone, $msg);
            }
        } catch (\Throwable $e) {}

        Log::info("Tenant {$tenantId} subscription fulfilled successfully for plan {$payment->plan_slug}.");
        return true;
    }

    /**
     * Super Admin manual update for tenant subscription
     */
    public static function updateSubscriptionManually(int $tenantId, array $data): bool
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) return false;

        $planSlug = $data['plan_slug'] ?? $tenant->plan_slug ?: 'starter';
        $plan = self::getPlan($planSlug) ?: self::getPlans()[0];
        $status = $data['status'] ?? 'active';

        $expiresAt = null;
        if (!empty($data['expires_at'])) {
            $expiresAt = Carbon::parse($data['expires_at']);
        } elseif ($status === 'active') {
            $current = $tenant->subscription_expires_at ? Carbon::parse($tenant->subscription_expires_at) : null;
            $daysToAdd = (int) ($data['add_days'] ?? 30);
            $expiresAt = ($current && $current->isFuture()) ? $current->addDays($daysToAdd) : now()->addDays($daysToAdd);
        }

        DB::table('tenants')->where('id', $tenantId)->update([
            'plan_slug' => $planSlug,
            'subscription_status' => $status,
            'subscription_expires_at' => $expiresAt,
            'router_limit' => (int) ($plan['router_limit'] ?: 2),
            'customer_limit' => (int) ($plan['customer_limit'] ?: 150),
            'updated_at' => now(),
        ]);

        return true;
    }
}
