<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\MpesaStkTransaction;
use App\Models\PackageList;
use App\Models\PrepaidUser;
use App\Models\Router;
use App\Models\RouterList;
use App\Models\UnmatchedPayment;
use App\Services\PaymentService;
use App\Services\PayHeroPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayHeroPaymentController extends Controller
{
    protected PaymentService $paymentService;
    protected PayHeroPaymentService $payheroService;

    public function __construct(PaymentService $paymentService, PayHeroPaymentService $payheroService)
    {
        $this->paymentService = $paymentService;
        $this->payheroService = $payheroService;
    }

    protected function withCors($response)
    {
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With, Authorization');
    }

    /**
     * Initiate Portal STK Push via PayHero with strict tenant isolation.
     */
    public function initiatePortalPayment(Request $request)
    {
        $request->validate([
            'phone'  => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $phone = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($request->phone) : $request->phone;
        $amount = (float) $request->amount;
        $customerRef = $request->account_reference ?: ('HS-' . substr(preg_replace('/[^0-9]/', '', $phone), -4));

        // Resolve Tenant strictly bypassing session TenantScope for unauthenticated captive portal requests
        $tenantId = null;
        if ($request->filled('router_id') && (int) $request->router_id > 0) {
            $router = \App\Models\Router::withoutGlobalScopes()->find((int) $request->router_id)
                ?: RouterList::withoutGlobalScopes()->find((int) $request->router_id);
            if ($router && $router->tenant_id) {
                $tenantId = (int) $router->tenant_id;
            }
        }
        if (!$tenantId && $request->filled('tenant_id') && (int) $request->tenant_id > 0) {
            $tenantId = (int) $request->tenant_id;
        }
        if (!$tenantId && ($request->filled('plan_id') || $request->filled('package_id'))) {
            $pid = (int) ($request->plan_id ?: $request->package_id);
            $plan = PackageList::withoutGlobalScopes()->find($pid);
            if ($plan && $plan->tenant_id) {
                $tenantId = (int) $plan->tenant_id;
            }
        }
        if (!$tenantId && auth()->check() && auth()->user()->tenant_id) {
            $tenantId = (int) auth()->user()->tenant_id;
        }
        if (!$tenantId) {
            $tenantId = 1;
        }

        $isTvBind = $request->boolean('is_tv_bind') || $request->input('device_type') === 'tv';
        $targetMac = $request->input('target_mac') ? strtoupper(trim((string)$request->input('target_mac'))) : null;
        $deviceName = $request->input('device_name') ? trim((string)$request->input('device_name')) : 'Smart TV';

        $customerRef = ($isTvBind && $targetMac)
            ? ('TV-' . substr(preg_replace('/[^0-9A-Fa-f]/', '', $targetMac), -6))
            : ($request->account_reference ?: ('HS-' . substr(preg_replace('/[^0-9]/', '', $phone), -4)));

        $meta = [
            'package_id'  => $request->plan_id ?? $request->package_id ?? null,
            'router_id'   => $request->router_id ? (int) $request->router_id : null,
            'mac'         => $request->mac ?: null,
            'ip'          => $request->ip ?: null,
            'tenant_id'   => $tenantId,
            'is_tv_bind'  => $isTvBind,
            'target_mac'  => $targetMac,
            'device_name' => $deviceName,
        ];

        try {
            $service = PayHeroPaymentService::forTenant($tenantId);
            $result = $service->initiatePayment($phone, $amount, $customerRef, null, null, null, $meta);

            if (!empty($result['success'])) {
                return $this->withCors(response()->json([
                    'success'             => true,
                    'checkout_request_id' => $result['checkout_request_id'] ?? $result['reference'] ?? null,
                    'reference'           => $result['reference'] ?? $result['checkout_request_id'] ?? null,
                    'external_reference'  => $result['external_reference'] ?? null,
                    'channel_id'          => $result['channel_id'] ?? null,
                    'message'             => $result['message'] ?? 'STK push sent to your phone. Please enter your M-Pesa PIN.',
                ]));
            }

            return $this->withCors(response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initiate STK push.',
            ], 422));

        } catch (\Throwable $e) {
            Log::error('[PayHeroPaymentController] initiatePortalPayment error: ' . $e->getMessage());
            return $this->withCors(response()->json([
                'success' => false,
                'message' => 'Payment service error: ' . $e->getMessage(),
            ], 500));
        }
    }

    /**
     * Check payment status for polling from Captive Portal.
     */
    public function checkPortalStatus(Request $request)
    {
        $checkoutId = trim((string) ($request->query('checkout_id') ?: $request->query('reference') ?: ''));
        $reference  = trim((string) ($request->query('reference') ?: ''));
        $phone      = trim((string) ($request->query('phone') ?: ''));
        $reqTenant  = (int) ($request->query('tenant_id') ?: 0);

        $stkTx = null;
        if ($checkoutId || $reference) {
            $stkTx = MpesaStkTransaction::withoutGlobalScopes()
                ->where(function ($q) use ($checkoutId, $reference) {
                    if ($checkoutId) {
                        $q->where('checkout_request_id', $checkoutId)
                          ->orWhere('merchant_request_id', $checkoutId)
                          ->orWhere('reference', $checkoutId);
                    }
                    if ($reference && $reference !== $checkoutId) {
                        $q->orWhere('merchant_request_id', $reference)
                          ->orWhere('checkout_request_id', $reference)
                          ->orWhere('reference', $reference);
                    }
                })
                ->latest('id')
                ->first();
        }

        if ($stkTx) {
            // 1. Completed in DB (set by webhook or prior poll)
            if ($stkTx->status === 'completed') {
                $code = strtoupper($stkTx->mpesa_receipt_number ?: $stkTx->checkout_request_id);
                $meta = is_array($stkTx->raw_callback) ? $stkTx->raw_callback : [];
                return $this->withCors(response()->json([
                    'status'       => 'completed',
                    'voucher_code' => $code,
                    'password'     => $code,
                    'is_tv_bind'   => !empty($meta['is_tv_bind']),
                    'target_mac'   => $meta['target_mac'] ?? null,
                    'device_name'  => $meta['device_name'] ?? null,
                    'message'      => !empty($meta['is_tv_bind'])
                        ? 'Payment confirmed! Your TV / Device has been authorized on Wi-Fi.'
                        : 'Payment confirmed! Connecting you to Wi-Fi...',
                ]));
            }

            // 2. Failed or cancelled by user
            if ($stkTx->status === 'failed') {
                return $this->withCors(response()->json([
                    'status'  => 'failed',
                    'message' => $stkTx->result_desc ?: 'Payment was cancelled or failed. Please try again.',
                ]));
            }

            // 3. Pending: actively poll PayHero /transaction-status using PayHero's reference (merchant_request_id)
            if ($stkTx->status === 'pending') {
                try {
                    $meta = is_array($stkTx->raw_callback) ? $stkTx->raw_callback : [];
                    $tenantId = (int) ($stkTx->tenant_id ?: ($meta['tenant_id'] ?? ($reqTenant ?: 1)));
                    $service = PayHeroPaymentService::forTenant($tenantId);
                    $verifyKey = $stkTx->merchant_request_id ?: ($meta['merchant_reference'] ?? $stkTx->checkout_request_id);
                    $verifyResult = $service->verifyStatus($verifyKey);

                    if (!empty($verifyResult['is_paid'])) {
                        $vData = $verifyResult['data'] ?? [];
                        $vResp = (isset($vData['response']) && is_array($vData['response'])) ? $vData['response'] : $vData;
                        $apiReceipt = strtoupper(trim((string) (
                            $vResp['MpesaReceiptNumber']
                            ?? $vResp['mpesa_receipt_number']
                            ?? $vData['mpesa_receipt_number']
                            ?? $vResp['provider_reference']
                            ?? $vData['provider_reference']
                            ?? $stkTx->checkout_request_id
                        )));
                        $apiAmount = (float) ($vResp['Amount'] ?? $vResp['amount'] ?? $vData['amount'] ?? $stkTx->amount);

                        MpesaStkTransaction::withoutGlobalScopes()->where('id', $stkTx->id)->update([
                            'status'               => 'completed',
                            'mpesa_receipt_number' => $apiReceipt,
                            'result_code'          => 0,
                            'result_desc'          => 'Payment confirmed via PayHero status check.',
                        ]);
                        $stkTx->status = 'completed';
                        $stkTx->mpesa_receipt_number = $apiReceipt;

                        $this->fulfillSuccessfulTransaction($stkTx, $apiReceipt, $apiAmount);

                        $meta = is_array($stkTx->raw_callback) ? $stkTx->raw_callback : [];
                        return $this->withCors(response()->json([
                            'status'       => 'completed',
                            'voucher_code' => $apiReceipt,
                            'password'     => $apiReceipt,
                            'is_tv_bind'   => !empty($meta['is_tv_bind']),
                            'target_mac'   => $meta['target_mac'] ?? null,
                            'device_name'  => $meta['device_name'] ?? null,
                            'message'      => !empty($meta['is_tv_bind'])
                                ? 'Payment confirmed! Your TV / Device has been authorized on Wi-Fi.'
                                : 'Payment confirmed! Connecting you to Wi-Fi...',
                        ]));
                    } elseif (!empty($verifyResult['is_failed'])) {
                        $desc = $verifyResult['message'] ?: 'Payment cancelled or failed.';
                        MpesaStkTransaction::withoutGlobalScopes()->where('id', $stkTx->id)->update([
                            'status'      => 'failed',
                            'result_desc' => $desc,
                        ]);
                        return $this->withCors(response()->json([
                            'status'  => 'failed',
                            'message' => $desc,
                        ]));
                    }
                } catch (\Throwable $e) {
                    Log::warning('[PayHero checkPortalStatus] Verification poll exception: ' . $e->getMessage());
                }
            }
        }

        // 4. Fallback: check recent completed transaction for this phone & tenant within last 3 minutes
        if ($phone && !$stkTx) {
            $cleanPhone = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($phone) : $phone;
            $recentTx = MpesaStkTransaction::withoutGlobalScopes()
                ->where('status', 'completed')
                ->where(function ($q) use ($phone, $cleanPhone) {
                    $q->where('phone', $cleanPhone)->orWhere('phone', $phone);
                })
                ->when($reqTenant > 0, fn($q) => $q->where('tenant_id', $reqTenant))
                ->where('updated_at', '>=', now()->subMinutes(3))
                ->latest('id')
                ->first();

            if ($recentTx && $recentTx->mpesa_receipt_number) {
                $code = strtoupper($recentTx->mpesa_receipt_number);
                return $this->withCors(response()->json([
                    'status'       => 'completed',
                    'voucher_code' => $code,
                    'password'     => $code,
                    'message'      => 'Payment confirmed! Connecting you to Wi-Fi...',
                ]));
            }
        }

        return $this->withCors(response()->json(['status' => 'pending']));
    }

    /**
     * Helper to fulfill payment after successful STK confirmation.
     * Decouples financial recording (hotspot_sales / collection_summaries) from router reachability.
     */
    protected function fulfillSuccessfulTransaction(MpesaStkTransaction $stkTx, string $receipt, float $amount): void
    {
        $receipt = strtoupper(trim($receipt));
        $meta = is_array($stkTx->raw_callback) ? $stkTx->raw_callback : [];
        $tenantId = (int) ($stkTx->tenant_id ?: ($meta['tenant_id'] ?? 1));
        $routerId = !empty($meta['router_id']) ? (int) $meta['router_id'] : ($stkTx->router_id ?: null);
        $packageId = $meta['package_id'] ?? null;
        $clientIp = $meta['ip'] ?? null;
        $clientMac = $meta['mac'] ?? null;

        $isTvBind = !empty($meta['is_tv_bind']) 
            || !empty($meta['target_mac']) 
            || str_starts_with((string)$stkTx->reference, 'HSM-TV') 
            || str_starts_with((string)$stkTx->reference, 'TV-');
        $targetMac = !empty($meta['target_mac']) ? strtoupper(trim((string)$meta['target_mac'])) : null;
        $deviceName = !empty($meta['device_name']) ? trim((string)$meta['device_name']) : 'Smart TV';

        $effectiveMac = ($isTvBind && $targetMac) ? $targetMac : ($clientMac ? strtoupper(trim($clientMac)) : null);
        $hotspotUsername = ($isTvBind && $targetMac) ? ('TV-' . $targetMac) : ($stkTx->phone ?: $receipt);
        $saleNote = ($isTvBind && $targetMac)
            ? ('TV Bind: ' . $deviceName . ' (' . $receipt . ')')
            : ('M-Pesa Captive Portal STK (' . $receipt . ')');

        // Ensure STK transaction has tenant_id and router_id recorded
        if (!$stkTx->tenant_id || !$stkTx->router_id) {
            MpesaStkTransaction::withoutGlobalScopes()->where('id', $stkTx->id)->update([
                'tenant_id' => $tenantId,
                'router_id' => $routerId,
            ]);
        }

        // Determine whether this transaction is an explicit Hotspot purchase
        $isHotspot = !empty($packageId) 
            || !empty($clientMac) 
            || !empty($routerId) 
            || $isTvBind
            || str_contains((string)$stkTx->reference, 'HSM-HS') 
            || str_starts_with((string)$stkTx->reference, 'HS-')
            || !empty($meta['package_id']);

        // Check customer in CustomersInfo for PPPoE / regular subscriber ONLY if NOT a hotspot purchase
        $customer = null;
        if (!$isHotspot) {
            if ($stkTx->reference) {
                $customer = CustomersInfo::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('customer_unique_id', $stkTx->reference)
                    ->where('customer_unique_id', 'not like', 'HS-%')
                    ->first();
                if (!$customer) {
                    $customer = CustomersInfo::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->whereHas('pppUser', fn($q) => $q->withoutGlobalScopes()->where('username', $stkTx->reference))
                        ->first();
                }
            }

            if (!$customer && $stkTx->phone) {
                $normalizedPhone = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($stkTx->phone) : $stkTx->phone;
                $customer = CustomersInfo::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('customer_unique_id', 'not like', 'HS-%')
                    ->where(function ($q) use ($normalizedPhone) {
                        $q->where('mobile', $normalizedPhone)
                          ->orWhere('mobile', '+' . $normalizedPhone);
                    })
                    ->first();
            }

            if ($customer) {
                // Check idempotency for CollectionSummary
                if (!CollectionSummary::withoutGlobalScopes()->where('transaction_id', $receipt)->exists()) {
                    try {
                        $this->paymentService->fulfill(
                            $customer,
                            $amount,
                            'payhero',
                            $receipt,
                            ['checkout_request_id' => $stkTx->checkout_request_id, 'phone' => $stkTx->phone]
                        );
                    } catch (\Throwable $e) {
                        Log::error('fulfillSuccessfulTransaction customer error: ' . $e->getMessage());
                    }
                }
                return;
            }
        }

        // ── HOTSPOT PURCHASE ──
        // 1. Resolve package duration & data limits
        $durationInfo = \App\Services\MikrotikDuration::parse('1h');
        $limitBytes = null;
        $profileName = 'default';
        $pkg = null;
        if ($packageId) {
            $pkg = PackageList::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($packageId) ?: PackageList::withoutGlobalScopes()->find($packageId);
            if ($pkg) {
                $profileName = $pkg->package ?: ($pkg->name ?: 'default');
                $durationVal = $pkg->validity_value ?: ($pkg->duration_value ?: (!empty($pkg->duration) ? $pkg->duration : '1'));
                $durationUnit = $pkg->validity_unit ?: ($pkg->duration_unit ?: 'hours');
                $durationInfo = \App\Services\MikrotikDuration::parse($durationVal, $durationUnit);

                $limitMb = (int) ($pkg->data_limit_mb ?: ($pkg->data_cap_mb ?: 0));
                if ($limitMb > 0) {
                    $limitBytes = $limitMb * 1048576;
                } elseif (!empty($pkg->volume) && is_numeric($pkg->volume) && $pkg->volume > 0) {
                    $unit = strtolower($pkg->volume_unit ?? 'mb');
                    $bytes = (int) $pkg->volume;
                    if ($unit === 'gb') $bytes *= 1073741824;
                    elseif ($unit === 'mb') $bytes *= 1048576;
                    elseif ($unit === 'kb') $bytes *= 1024;
                    $limitBytes = $bytes;
                }
            }
        }
        if ($stkTx && $stkTx->service_type !== 'hotspot') {
            MpesaStkTransaction::withoutGlobalScopes()->where('id', $stkTx->id)->update(['service_type' => 'hotspot']);
        }
        $durationSeconds = $durationInfo['seconds'] > 0 ? $durationInfo['seconds'] : 3600;
        $durationMinutes = (int) round($durationSeconds / 60);
        $limitUptime = $durationInfo['mikrotik'];

        // 2. Resolve Router (permanent identity)
        $routerObj = null;
        $routerName = 'Hotspot';
        if ($routerId) {
            $routerObj = \App\Models\Router::withoutGlobalScopes()->find($routerId);
            if ($routerObj) {
                $routerName = $routerObj->name;
            }
        }

        // 3. Stacking calculation (if user has active session, add duration to current expires_at)
        // Check if THIS specific transaction already has a recorded sale (idempotency guard)
        $existingThisSale = HotspotSale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('voucher_code', $receipt)
            ->first();

        if ($existingThisSale) {
            // Already fulfilled! Use recorded duration and expiration. Do NOT re-stack onto itself!
            $startedAt = $existingThisSale->started_at ?: now();
            $expiresAt = $existingThisSale->expires_at ? \Carbon\Carbon::parse($existingThisSale->expires_at) : now()->addSeconds($durationSeconds);
            $totalMinutes = (int) ($existingThisSale->duration_minutes ?: $durationMinutes);
            $remSeconds = max(60, now()->diffInSeconds($expiresAt, false));
            $limitUptime = \App\Services\MikrotikDuration::secondsToMikrotik($remSeconds);
        } else {
            // Look for existing ACTIVE session to stack onto, BUT EXCLUDE THIS RECEIPT!
            $existingActive = HotspotSale::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('voucher_code', '!=', $receipt)
                ->where(function ($q) use ($stkTx, $effectiveMac, $hotspotUsername) {
                    if ($stkTx->phone) $q->where('phone', $stkTx->phone);
                    if ($effectiveMac) $q->orWhere('mac_address', strtoupper($effectiveMac));
                    if ($hotspotUsername) $q->orWhere('username', $hotspotUsername);
                })
                ->where('expires_at', '>', now())
                ->whereIn('status', ['completed', 'active', 'pending_router'])
                ->latest('expires_at')
                ->first();

            if ($existingActive && $existingActive->expires_at) {
                $startedAt = $existingActive->started_at ?: now();
                $expiresAt = \Carbon\Carbon::parse($existingActive->expires_at)->addSeconds($durationSeconds);
                $totalMinutes = (int) $existingActive->duration_minutes + $durationMinutes;
                $remSeconds = max(60, now()->diffInSeconds($expiresAt, false));
                $limitUptime = \App\Services\MikrotikDuration::secondsToMikrotik($remSeconds);
            } else {
                $startedAt = now();
                $expiresAt = now()->addSeconds($durationSeconds);
                $totalMinutes = $durationMinutes;
            }
        }

        // 4. Financial Record: Save to hotspot_sales FIRST
        $hotspotSale = null;
        try {
            $hotspotSale = HotspotSale::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id'    => $tenantId,
                    'voucher_code' => $receipt,
                ],
                [
                    'router_id'        => $routerId,
                    'router_name'      => $routerName,
                    'profile'          => $profileName,
                    'username'         => $hotspotUsername,
                    'phone'            => $stkTx->phone,
                    'mac_address'      => $effectiveMac ? strtoupper($effectiveMac) : null,
                    'ip_address'       => $clientIp,
                    'amount'           => $amount,
                    'payment_method'   => 'mpesa_payhero',
                    'status'           => 'pending_router',
                    'duration_minutes' => $totalMinutes,
                    'started_at'       => $startedAt,
                    'expires_at'       => $expiresAt,
                    'note'             => $saleNote,
                    'sale_date'        => now()->timezone('Africa/Nairobi')->toDateString(),
                ]
            );
        } catch (\Throwable $hse) {
            Log::warning('[PayHero] HotspotSale save note: ' . $hse->getMessage());
        }

        // 5. Financial Record: Save to collection_summaries & prepaid_users FIRST
        try {
            if ($hotspotSale) {
                \App\Models\PrepaidUser::upsertFromHotspotSale(
                    $hotspotSale,
                    $pkg,
                    'Mpesa STK + ' . strtoupper($receipt)
                );
            }
        } catch (\Throwable $pe) {
            Log::warning('[PayHero] PrepaidUser upsert note: ' . $pe->getMessage());
        }

        try {
            if (!CollectionSummary::withoutGlobalScopes()->where('transaction_id', $receipt)->exists()) {
                $maxInvoice = CollectionSummary::withoutGlobalScopes()->max('invoice_no');
                $invoiceNo = $maxInvoice ? ($maxInvoice + 1) : 100001;
                $subLabel = ($isTvBind && $targetMac)
                    ? ($deviceName . ' [' . $targetMac . '] (' . ($stkTx->phone ?: $receipt) . ')')
                    : ($receipt . ($stkTx->phone ? ' (' . $stkTx->phone . ')' : ''));

                CollectionSummary::withoutGlobalScopes()->create([
                    'tenant_id'                     => $tenantId,
                    'customer_collection_unique_id' => $hotspotUsername,
                    'subscriber_name'               => $subLabel,
                    'phone'                         => $stkTx->phone,
                    'mac_address'                   => $effectiveMac ? strtoupper($effectiveMac) : null,
                    'router_id'                     => $routerId,
                    'collection_date'               => now(),
                    'collection_amount'             => $amount,
                    'collected_by'                  => $isTvBind ? 'Smart TV M-Pesa (PAYHERO)' : 'Hotspot M-Pesa (PAYHERO)',
                    'payment_type'                  => 'hotspot',
                    'service_type'                  => 'hotspot',
                    'package_id'                    => $pkg?->id ?: ($packageId ? (int) $packageId : null),
                    'payment_method'                => 'payhero',
                    'transaction_id'                => $receipt,
                    'payment_status'                => 'paid',
                    'invoice_no'                    => $invoiceNo,
                    'bill_month'                    => now()->format('F Y'),
                ]);
            }
        } catch (\Throwable $ce) {
            Log::warning('[PayHero] CollectionSummary save note: ' . $ce->getMessage());
        }

        // 6. Router Provisioning (Instant or Queue via RouterPendingActivationService)
        try {
            $this->provisionHotspotVoucherOnRouter(
                $stkTx,
                $receipt,
                $routerObj,
                $tenantId,
                $routerId,
                $limitUptime,
                $limitBytes,
                $clientIp,
                $effectiveMac,
                $expiresAt,
                $hotspotSale?->id,
                $profileName,
                $pkg,
                $isTvBind,
                $targetMac,
                $deviceName
            );
        } catch (\Throwable $he) {
            Log::warning('[PayHero] Hotspot router provisioning note: ' . $he->getMessage());
        }
    }

    /**
     * Provision a paid Hotspot user on the MikroTik router and authorize the client's IP/MAC immediately.
     * If the router is unreachable or offline, enqueues the activation for automatic background retry.
     */
    protected function provisionHotspotVoucherOnRouter(
        MpesaStkTransaction $stkTx,
        string $receipt,
        ?\App\Models\Router $routerObj,
        int $tenantId,
        ?int $routerId,
        string $limitUptime,
        ?int $limitBytes,
        ?string $clientIp,
        ?string $clientMac,
        \Carbon\Carbon $expiresAt,
        ?int $saleId,
        string $profileName = 'default',
        ?PackageList $pkg = null,
        bool $isTvBind = false,
        ?string $targetMac = null,
        ?string $deviceName = 'Smart TV'
    ): void {
        $routersQuery = \App\Models\Router::withoutGlobalScopes()->whereNull('deleted_at')->where(function ($q) {
            $q->where('status', 'online')
              ->orWhere('action', 'connected')
              ->orWhereNotNull('vpn_ip')
              ->orWhereNotNull('last_known_ip');
        });
        $routersQuery->where('tenant_id', $tenantId);
        if ($routerId) {
            $routersQuery->where('id', $routerId);
        }
        $routers = $routersQuery->get();

        if ($routers->isEmpty()) {
            Log::warning("[PayHero] No online router found for tenant #{$tenantId} / router #{$routerId}. Enqueuing pending activation.");
            \App\Services\RouterPendingActivationService::enqueue(
                $tenantId,
                $routerId,
                $receipt,
                $stkTx->phone ?: $receipt,
                $receipt,
                'Hotspot Plan',
                $limitUptime,
                $limitBytes,
                $clientIp,
                $clientMac,
                $expiresAt,
                $saleId,
                'Router offline / no route available'
            );
            return;
        }

        $provisionedAny = false;
        $lastError = null;

        foreach ($routers as $router) {
            $host = $router->vpn_ip ?: ($router->last_known_ip ?: $router->ip);
            if (!$host) continue;
            $port = (int) ($router->api_port ?: 8728);
            $user = $router->api_user ?: ($router->username ?: 'admin');
            $pass = \App\Services\CaptivePortalTemplates::decryptRouterPassword($router->api_password ?: $router->password);

            try {
                $api = new \App\Services\MikrotikApiService($host, $port, $user, $pass, 15);
                $code = strtoupper(trim($receipt));

                // 0. Ensure package profile exists on router if package was resolved
                $resolvedProfile = 'default';
                if ($pkg && !empty($profileName) && $profileName !== 'default') {
                    try {
                        $rateLimitStr = $pkg->getRateLimitString();
                        $existingProf = $api->executeCommand('/ip/hotspot/user/profile/print', [], ['name' => $profileName]);
                        if (is_array($existingProf) && !empty($existingProf[0]['.id'])) {
                            $resolvedProfile = $profileName;
                            if ($rateLimitStr) {
                                $api->executeCommand('/ip/hotspot/user/profile/set', [
                                    '.id'                => $existingProf[0]['.id'],
                                    'rate-limit'         => $rateLimitStr,
                                    'shared-users'       => (string) max(1, (int) ($pkg->shared_users ?: 1)),
                                    'status-autorefresh' => '1m',
                                ]);
                            }
                        } else {
                            $addProfRes = $api->executeCommand('/ip/hotspot/user/profile/add', [
                                'name'               => $profileName,
                                'rate-limit'         => $rateLimitStr,
                                'session-timeout'    => $pkg->getSessionTimeoutString(),
                                'idle-timeout'       => '5m',
                                'shared-users'       => (string) max(1, (int) ($pkg->shared_users ?: 1)),
                                'status-autorefresh' => '1m',
                            ]);
                            if (!is_string($addProfRes) || !str_starts_with($addProfRes, 'Error:')) {
                                $resolvedProfile = $profileName;
                            }
                        }
                    } catch (\Throwable $pe) {}
                }

                // 1. Add / Update Hotspot voucher user (username = receipt, password = receipt)
                $existingUsers = $api->executeCommand('/ip/hotspot/user/print');
                $matchedUser = null;
                if (is_array($existingUsers)) {
                    foreach ($existingUsers as $eu) {
                        if (strtoupper($eu['name'] ?? '') === $code) {
                            $matchedUser = $eu;
                            break;
                        }
                    }
                }

                $comment = 'M-Pesa ' . ($stkTx->phone ?: '') . ' exp:' . $expiresAt->toDateTimeString();
                $userPayload = [
                    'name'         => $code,
                    'password'     => $code,
                    'profile'      => $resolvedProfile,
                    'limit-uptime' => $limitUptime,
                    'comment'      => $comment,
                ];
                if ($limitBytes && $limitBytes > 0) {
                    $userPayload['limit-bytes-total'] = (string) $limitBytes;
                }

                if ($matchedUser && !empty($matchedUser['.id'])) {
                    $userPayload['.id'] = $matchedUser['.id'];
                    $api->executeCommand('/ip/hotspot/user/set', $userPayload);
                    try {
                        $api->executeCommand('/ip/hotspot/user/reset-counters', [], ['.id' => $matchedUser['.id']]);
                    } catch (\Throwable $rce) {}
                } else {
                    $addRes = $api->executeCommand('/ip/hotspot/user/add', $userPayload);
                    try {
                        if (is_array($addRes) && !empty($addRes['after']['ret'])) {
                            $api->executeCommand('/ip/hotspot/user/reset-counters', [], ['.id' => $addRes['after']['ret']]);
                        }
                    } catch (\Throwable $rce) {}
                }

                // 2. Remove stale cookies so mac-cookie never bypasses limit-uptime
                try {
                    $cookies = $api->executeCommand('/ip/hotspot/cookie/print');
                    if (is_array($cookies)) {
                        foreach ($cookies as $ck) {
                            if (strtoupper($ck['user'] ?? '') === $code) {
                                $api->executeCommand('/ip/hotspot/cookie/remove', ['.id' => $ck['.id']]);
                            }
                        }
                    }
                } catch (\Throwable $ckEx) {}

                // 3. Directly authorize the client session on MikroTik if valid IP or MAC is known
                $loginIp = $clientIp && filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : null;
                $loginMac = $clientMac && preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $clientMac) ? strtoupper($clientMac) : null;

                if (!$loginIp && $loginMac) {
                    try {
                        $hosts = $api->executeCommand('/ip/hotspot/host/print');
                        if (is_array($hosts)) {
                            foreach ($hosts as $h) {
                                if (strtoupper(trim((string)($h['mac-address'] ?? ''))) === $loginMac && !empty($h['address'])) {
                                    $loginIp = $h['address'];
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $he) {}
                }

                if ($loginIp) {
                    $loginParams = [
                        'user'     => $code,
                        'password' => $code,
                        'ip'       => $loginIp,
                    ];
                    if ($loginMac) {
                        $loginParams['mac-address'] = $loginMac;
                    }
                    $api->executeCommand('/ip/hotspot/active/login', $loginParams);
                }

                // 4. If TV / Device binding, set bypass in /ip/hotspot/ip-binding
                if ($isTvBind && $targetMac) {
                    try {
                        $existingBindings = $api->executeCommand('/ip/hotspot/ip-binding/print');
                        $matchedBinding = null;
                        if (is_array($existingBindings)) {
                            foreach ($existingBindings as $b) {
                                if (strtoupper(trim((string)($b['mac-address'] ?? ''))) === $targetMac) {
                                    $matchedBinding = $b;
                                    break;
                                }
                            }
                        }

                        $bindingComment = "TV: {$deviceName} (" . ($stkTx->phone ?: '') . ") exp:{$expiresAt->toDateTimeString()}";
                        $bindingPayload = [
                            'mac-address' => $targetMac,
                            'type'        => 'bypassed',
                            'comment'     => $bindingComment,
                        ];

                        if ($matchedBinding && !empty($matchedBinding['.id'])) {
                            $bindingPayload['.id'] = $matchedBinding['.id'];
                            $api->executeCommand('/ip/hotspot/ip-binding/set', $bindingPayload);
                        } else {
                            $api->executeCommand('/ip/hotspot/ip-binding/add', $bindingPayload);
                        }

                        // Remove stale host entry so bypass takes effect immediately
                        $hosts = $api->executeCommand('/ip/hotspot/host/print');
                        if (is_array($hosts)) {
                            foreach ($hosts as $h) {
                                if (strtoupper(trim((string)($h['mac-address'] ?? ''))) === $targetMac && !empty($h['.id'])) {
                                    $api->executeCommand('/ip/hotspot/host/remove', ['.id' => $h['.id']]);
                                }
                            }
                        }

                        Log::info("[PayHero] Bound TV MAC {$targetMac} ({$deviceName}) as bypassed on router {$router->name}");
                    } catch (\Throwable $tve) {
                        Log::warning("[PayHero] TV ip-binding exception on router {$router->name}: " . $tve->getMessage());
                    }
                }

                $provisionedAny = true;
                Log::info("[PayHero] Provisioned Hotspot user '{$code}' ({$limitUptime}) on router {$router->name}" . ($clientIp ? " and logged in IP {$clientIp}" : ''));
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("Hotspot user add error on {$router->name}: " . $e->getMessage());
            }
        }

        if ($provisionedAny) {
            if ($saleId) {
                HotspotSale::withoutGlobalScopes()->where('id', $saleId)->update([
                    'status'     => 'completed',
                    'sync_error' => null,
                ]);
            }
        } else {
            // Queue pending activation for automatic retry as soon as router reconnects
            \App\Services\RouterPendingActivationService::enqueue(
                $tenantId,
                $routerId,
                $receipt,
                $stkTx->phone ?: $receipt,
                $receipt,
                'Hotspot Plan',
                $limitUptime,
                $limitBytes,
                $clientIp,
                $clientMac,
                $expiresAt,
                $saleId,
                $lastError ?: 'Router connection failed'
            );
            if ($saleId) {
                HotspotSale::withoutGlobalScopes()->where('id', $saleId)->update([
                    'status'     => 'pending_router',
                    'sync_error' => $lastError ?: 'Router connection failed',
                ]);
            }
        }
    }

    /**
     * Handle PayHero Webhook Notification (supports PascalCase & lowercase payloads, bypassing session TenantScope).
     */
    public function handleWebhook(Request $request)
    {
        \App\Services\Security\SafePayloadLogger::info('PayHero Webhook Received', $request->all());

        $payload = $request->all();
        $responseObj = (isset($payload['response']) && is_array($payload['response'])) ? $payload['response'] : $payload;

        // Parse BOTH PascalCase (PayHero v2 live format) and lowercase keys
        $rawStatus = $responseObj['Status']
            ?? $responseObj['status']
            ?? (is_string($payload['status'] ?? null) ? $payload['status'] : '');
        $status = strtoupper(trim((string) $rawStatus));

        $resultCode = $responseObj['ResultCode'] ?? $responseObj['result_code'] ?? $payload['result_code'] ?? null;
        $resultDesc = (string) ($responseObj['ResultDesc'] ?? $responseObj['result_desc'] ?? $payload['result_desc'] ?? '');

        $merchantReqId = trim((string) ($responseObj['MerchantRequestID'] ?? $responseObj['merchant_request_id'] ?? $responseObj['reference'] ?? $payload['reference'] ?? ''));
        $checkoutReqId = trim((string) ($responseObj['CheckoutRequestID'] ?? $responseObj['checkout_request_id'] ?? $payload['checkout_request_id'] ?? ''));
        $externalRef   = trim((string) ($responseObj['ExternalReference'] ?? $responseObj['external_reference'] ?? $payload['external_reference'] ?? ''));
        $receipt       = strtoupper(trim((string) ($responseObj['MpesaReceiptNumber'] ?? $responseObj['mpesa_receipt_number'] ?? $payload['mpesa_receipt_number'] ?? '')));
        $amount        = (float) ($responseObj['Amount'] ?? $responseObj['amount'] ?? $payload['amount'] ?? 0);
        $phone         = (string) ($responseObj['Phone'] ?? $responseObj['phone_number'] ?? $responseObj['phone'] ?? $payload['phone_number'] ?? '');

        $lookupKeys = array_values(array_filter([$merchantReqId, $checkoutReqId, $externalRef, $receipt]));

        $stkTx = null;
        if (!empty($lookupKeys)) {
            $stkTx = MpesaStkTransaction::withoutGlobalScopes()
                ->where(function ($q) use ($lookupKeys) {
                    $q->whereIn('checkout_request_id', $lookupKeys)
                      ->orWhereIn('merchant_request_id', $lookupKeys)
                      ->orWhereIn('reference', $lookupKeys)
                      ->orWhereIn('mpesa_receipt_number', $lookupKeys);
                })
                ->latest('id')
                ->first();
        }

        // 1. Check success vs failed/cancelled
        $isSuccess = in_array($status, ['SUCCESS', 'COMPLETED', 'SUCCESSFUL'], true)
            || ($resultCode !== null && is_numeric($resultCode) && (int) $resultCode === 0);

        if (!$isSuccess) {
            Log::warning("PayHero webhook non-success status: '{$status}' (ResultCode={$resultCode}, Desc='{$resultDesc}') for ref={$merchantReqId}/{$checkoutReqId}");
            if ($stkTx) {
                $existingMeta = is_array($stkTx->raw_callback) ? $stkTx->raw_callback : [];
                MpesaStkTransaction::withoutGlobalScopes()->where('id', $stkTx->id)->update([
                    'status'       => 'failed',
                    'result_code'  => is_numeric($resultCode) ? (int) $resultCode : null,
                    'result_desc'  => $resultDesc ?: ('Payment ' . ($status ?: 'failed')),
                    'raw_callback' => array_merge($existingMeta, ['webhook' => $payload]),
                ]);
            }
            return response()->json(['status' => 'ignored', 'message' => 'Transaction was not successful'], 200);
        }

        // 2. Reverse-verify with PayHero API when possible
        $verifyRef = $merchantReqId ?: ($checkoutReqId ?: $externalRef);
        $apiAmount = $amount;
        $apiReceipt = $receipt;
        if ($verifyRef) {
            $verifyResult = $this->payheroService->verifyStatus($verifyRef);
            if (!empty($verifyResult['verified'])) {
                $vData = $verifyResult['data'] ?? [];
                $vResp = (isset($vData['response']) && is_array($vData['response'])) ? $vData['response'] : $vData;
                if (!empty($verifyResult['is_failed'])) {
                    Log::warning("PayHero reverse-verification confirmed transaction FAILED for ref={$verifyRef}");
                    return response()->json(['status' => 'ignored', 'message' => 'PayHero API reported failure'], 200);
                }
                $vReceipt = strtoupper(trim((string) ($vResp['MpesaReceiptNumber'] ?? $vResp['mpesa_receipt_number'] ?? $vData['mpesa_receipt_number'] ?? '')));
                $vAmount  = (float) ($vResp['Amount'] ?? $vResp['amount'] ?? $vData['amount'] ?? 0);
                if ($vReceipt) $apiReceipt = $vReceipt;
                if ($vAmount > 0) $apiAmount = $vAmount;
            }
        }

        $finalReceipt = $apiReceipt ?: ($receipt ?: $verifyRef);
        $finalAmount  = $apiAmount > 0 ? $apiAmount : $amount;

        // 3. Preserve original router/tenant/mac/ip metadata while updating STK transaction to completed
        if ($stkTx) {
            $existingMeta = is_array($stkTx->raw_callback) ? $stkTx->raw_callback : [];
            $mergedMeta = array_merge($existingMeta, ['webhook' => $payload]);

            MpesaStkTransaction::withoutGlobalScopes()->where('id', $stkTx->id)->update([
                'status'               => 'completed',
                'mpesa_receipt_number' => $finalReceipt,
                'result_code'          => 0,
                'result_desc'          => $resultDesc ?: 'Success',
                'raw_callback'         => $mergedMeta,
            ]);

            $stkTx->status = 'completed';
            $stkTx->mpesa_receipt_number = $finalReceipt;
            $stkTx->raw_callback = $mergedMeta;
        }

        // 4. Fulfill transaction & provision Hotspot user on MikroTik immediately
        if ($stkTx) {
            $this->fulfillSuccessfulTransaction($stkTx, $finalReceipt, $finalAmount);
        }

        return response()->json([
            'status'           => 'success',
            'message'          => 'PayHero payment fulfilled successfully',
            'transaction_code' => $finalReceipt,
        ]);
    }
}
