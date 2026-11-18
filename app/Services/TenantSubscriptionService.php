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
            // If trial but no expires_at set, award 14 days from created_at
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
     * Initiate M-Pesa STK Push via PayHero platform credentials
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
            'gateway' => 'payhero',
            'reference' => $reference,
            'phone' => $phone,
            'status' => 'pending',
            'metadata' => [
                'plan_name' => $plan['name'],
                'initiated_at' => now()->toIso8601String(),
            ],
        ]);

        try {
            $response = Http::timeout(20)->withHeaders([
                'Authorization' => PayHeroPaymentService::MASTER_BASIC_AUTH,
                'Content-Type' => 'application/json',
            ])->post(PayHeroPaymentService::MASTER_BASE_URL . '/payments', [
                'amount' => (int) $amount,
                'phone_number' => $phone,
                'channel_id' => (int) PayHeroPaymentService::MASTER_ACCOUNT_ID,
                'provider' => 'm-pesa',
                'external_reference' => $reference,
                'callback_url' => url('/api/subscription/callback'),
            ]);

            $body = $response->json();
            Log::info("PayHero subscription STK response for tenant {$tenantId}:", ['body' => $body]);

            if ($response->successful() && ($body['success'] ?? false || !empty($body['CheckoutRequestID']) || !empty($body['reference']))) {
                $extRef = $body['CheckoutRequestID'] ?? $body['reference'] ?? null;
                if ($extRef) {
                    $payment->update(['external_reference' => $extRef]);
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

            $errMsg = $body['message'] ?? $body['error'] ?? 'Failed to initiate M-Pesa payment. Please try again.';
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $errMsg])]);
            return ['status' => 'error', 'message' => $errMsg];
        } catch (\Throwable $e) {
            Log::error("PayHero subscription exception: " . $e->getMessage());
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $e->getMessage()])]);
            return ['status' => 'error', 'message' => 'Payment gateway timeout. Please check your network and try again.'];
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

        // Probe PayHero API to check status
        try {
            $extRef = $payment->external_reference ?: $payment->reference;
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => PayHeroPaymentService::MASTER_BASIC_AUTH,
            ])->get(PayHeroPaymentService::MASTER_BASE_URL . '/payments/' . $extRef);

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
            Log::warning("PayHero status poll error: " . $e->getMessage());
        }

        return ['status' => 'pending', 'message' => 'Waiting for M-Pesa PIN confirmation...'];
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

        // Sync to POS isps table if database connection available
        try {
            if (\Illuminate\Support\Facades\Schema::connection('poss_db')->hasTable('isps')) {
                DB::connection('poss_db')->table('isps')->where('id', $tenantId)->update([
                    'billing_plan' => $plan['name'] ?? ucfirst($payment->plan_slug),
                    'plan_slug' => $payment->plan_slug,
                    'subscription_status' => 'active',
                    'subscription_expires_at' => now()->addDays(30),
                    'subscription_price' => $payment->amount,
                    'router_limit' => $plan['router_limit'] ?: 2,
                    'customer_limit' => $plan['customer_limit'] ?: 150,
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Ignore connection errors if not directly accessible
        }

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
