<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\MpesaStkTransaction;
use App\Models\PackageList;
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

        $meta = [
            'package_id' => $request->plan_id ?? $request->package_id ?? null,
            'router_id'  => $request->router_id ? (int) $request->router_id : null,
            'mac'        => $request->mac ?: null,
            'ip'         => $request->ip ?: null,
            'tenant_id'  => $tenantId,
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
                return $this->withCors(response()->json([
                    'status'       => 'completed',
                    'voucher_code' => $code,
                    'password'     => $code,
                    'message'      => 'Payment confirmed! Connecting you to Wi-Fi...',
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

                        return $this->withCors(response()->json([
                            'status'       => 'completed',
                            'voucher_code' => $apiReceipt,
                            'password'     => $apiReceipt,
                            'message'      => 'Payment confirmed! Connecting you to Wi-Fi...',
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
     */
    protected function fulfillSuccessfulTransaction(MpesaStkTransaction $stkTx, string $receipt, float $amount): void
    {
        $receipt = strtoupper(trim($receipt));
        $meta = is_array($stkTx->raw_callback) ? $stkTx->raw_callback : [];
        $tenantId = (int) ($stkTx->tenant_id ?: ($meta['tenant_id'] ?? 1));

        // Always provision Hotspot voucher & direct login on tenant's router(s) so customer connects immediately
        try {
            $this->provisionHotspotVoucherOnRouter($stkTx, $receipt);
        } catch (\Throwable $he) {
            Log::warning('[PayHero] Hotspot voucher router provisioning note: ' . $he->getMessage());
        }

        // Check idempotency bypassing TenantScope
        if (CollectionSummary::withoutGlobalScopes()->where('transaction_id', $receipt)->exists()) {
            return;
        }

        $customer = null;
        if ($stkTx->reference) {
            $customer = CustomersInfo::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_unique_id', $stkTx->reference)
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
                ->where(function ($q) use ($normalizedPhone) {
                    $q->where('mobile', $normalizedPhone)
                      ->orWhere('mobile', '+' . $normalizedPhone);
                })
                ->first();
        }

        if ($customer) {
            try {
                $this->paymentService->fulfill(
                    $customer,
                    $amount,
                    'payhero',
                    $receipt,
                    ['checkout_request_id' => $stkTx->checkout_request_id, 'phone' => $stkTx->phone]
                );
            } catch (\Throwable $e) {
                Log::error('fulfillSuccessfulTransaction error: ' . $e->getMessage());
            }
        } else {
            // 1. Record into hotspot_sales (powers Dashboard "Today's hotspot sales", "Total global revenue", and Hotspot Manager)
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('hotspot_sales')) {
                    $existsHs = \Illuminate\Support\Facades\DB::table('hotspot_sales')
                        ->where('voucher_code', $receipt)
                        ->exists();

                    if (!$existsHs) {
                        $routerName = 'Hotspot';
                        if (!empty($meta['router_id'])) {
                            $rObj = \App\Models\Router::withoutGlobalScopes()->find((int) $meta['router_id']);
                            if ($rObj && !empty($rObj->name)) {
                                $routerName = $rObj->name;
                            }
                        }

                        $profileName = 'Hotspot Plan';
                        if (!empty($meta['package_id'])) {
                            $pkg = PackageList::withoutGlobalScopes()->find((int) $meta['package_id']);
                            if ($pkg) {
                                $profileName = $pkg->package_name ?: ($pkg->name ?: 'Hotspot Plan');
                            }
                        }

                        $hsRow = [
                            'router_name'    => $routerName,
                            'voucher_code'   => $receipt,
                            'profile'        => $profileName,
                            'username'       => $stkTx->phone ?: $receipt,
                            'amount'         => $amount,
                            'payment_method' => 'mpesa_payhero',
                            'note'           => 'M-Pesa Captive Portal STK (' . $receipt . ')',
                            'sale_date'      => now()->timezone('Africa/Nairobi')->toDateString(),
                            'sold_by'        => null,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ];
                        if (\Illuminate\Support\Facades\Schema::hasColumn('hotspot_sales', 'tenant_id')) {
                            $hsRow['tenant_id'] = $tenantId;
                        }

                        \Illuminate\Support\Facades\DB::table('hotspot_sales')->insert($hsRow);
                    }
                }
            } catch (\Throwable $hse) {
                Log::warning('HotspotSale insert note: ' . $hse->getMessage());
            }

            // 2. Also record into collection_summaries (safe check for tenant_id column)
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('collection_summaries')) {
                    $maxInvoice = CollectionSummary::withoutGlobalScopes()->max('invoice_no');
                    $invoiceNo = $maxInvoice ? ($maxInvoice + 1) : 100001;

                    $colRow = [
                        'customer_collection_unique_id' => $stkTx->phone,
                        'collection_date'               => now(),
                        'collection_amount'             => $amount,
                        'collected_by'                  => 'Hotspot M-Pesa (PAYHERO)',
                        'payment_type'                  => 'hotspot',
                        'payment_method'                => 'payhero',
                        'transaction_id'                => $receipt,
                        'payment_status'                => 'paid',
                        'invoice_no'                    => $invoiceNo,
                        'bill_month'                    => now()->format('F Y'),
                        'created_at'                    => now(),
                        'updated_at'                    => now(),
                    ];
                    if (\Illuminate\Support\Facades\Schema::hasColumn('collection_summaries', 'tenant_id')) {
                        $colRow['tenant_id'] = $tenantId;
                    }

                    \Illuminate\Support\Facades\DB::table('collection_summaries')->insert($colRow);
                }
            } catch (\Throwable $ce) {
                Log::warning('CollectionSummary insert note: ' . $ce->getMessage());
            }
        }
    }

    /**
     * Provision a paid Hotspot user on the MikroTik router and authorize the client's IP/MAC immediately.
     */
    protected function provisionHotspotVoucherOnRouter(MpesaStkTransaction $stkTx, string $receipt): void
    {
        $meta = is_array($stkTx->raw_callback) ? $stkTx->raw_callback : [];
        $routerId  = !empty($meta['router_id']) ? (int) $meta['router_id'] : null;
        $tenantId  = (int) ($stkTx->tenant_id ?: ($meta['tenant_id'] ?? 1));
        $packageId = $meta['package_id'] ?? null;
        $clientIp  = $meta['ip'] ?? null;
        $clientMac = $meta['mac'] ?? null;

        $durationInfo = \App\Services\MikrotikDuration::parse('1h');
        $limitBytes = null;
        if ($packageId) {
            $pkg = PackageList::withoutGlobalScopes()->find($packageId);
            if ($pkg) {
                $durationVal = !empty($pkg->duration) ? $pkg->duration : (!empty($pkg->validity) ? $pkg->validity : '1h');
                $durationUnit = !empty($pkg->duration_unit) ? $pkg->duration_unit : null;
                $durationInfo = \App\Services\MikrotikDuration::parse($durationVal, $durationUnit);

                if (!empty($pkg->volume) && is_numeric($pkg->volume) && $pkg->volume > 0) {
                    $unit = strtolower($pkg->volume_unit ?? 'mb');
                    $bytes = (int) $pkg->volume;
                    if ($unit === 'gb') $bytes *= 1073741824;
                    elseif ($unit === 'mb') $bytes *= 1048576;
                    elseif ($unit === 'kb') $bytes *= 1024;
                    $limitBytes = $bytes;
                }
            }
        }
        $limitUptime = $durationInfo['mikrotik'];
        $expiresAt = now()->timezone('Africa/Nairobi')->addSeconds($durationInfo['seconds']);

        $routersQuery = \App\Models\Router::withoutGlobalScopes()->where(function ($q) {
            $q->where('status', 'online')
              ->orWhere('action', 'connected')
              ->orWhereNotNull('vpn_ip');
        });
        if ($routerId) {
            $routersQuery->where('id', $routerId);
        } else {
            $routersQuery->where('tenant_id', $tenantId);
        }
        $routers = $routersQuery->get();

        foreach ($routers as $router) {
            $host = $router->vpn_ip ?: $router->ip;
            if (!$host) continue;
            $port = (int) ($router->api_port ?: 8728);
            $user = $router->api_user ?: ($router->username ?: 'admin');
            try {
                $pass = \Illuminate\Support\Facades\Crypt::decryptString($router->api_password ?: $router->password);
            } catch (\Throwable $e) {
                $pass = $router->api_password ?: $router->password;
            }

            try {
                $api = new \App\Services\MikrotikApiService($host, $port, $user, $pass, 6);
                $code = strtoupper(trim($receipt));

                // 1. Add Hotspot voucher user (username = receipt, password = receipt)
                $existingUsers = $api->executeCommand('/ip/hotspot/user/print');
                $exists = false;
                if (is_array($existingUsers)) {
                    foreach ($existingUsers as $eu) {
                        if (strtoupper($eu['name'] ?? '') === $code) {
                            $exists = true;
                            break;
                        }
                    }
                }
                if (!$exists) {
                    $userPayload = [
                        'name'         => $code,
                        'password'     => $code,
                        'limit-uptime' => $limitUptime,
                        'comment'      => 'M-Pesa ' . ($stkTx->phone ?: '') . ' exp:' . $expiresAt->toDateTimeString(),
                    ];
                    if ($limitBytes) {
                        $userPayload['limit-bytes-total'] = (string) $limitBytes;
                    }
                    $api->executeCommand('/ip/hotspot/user/add', $userPayload);
                }

                // 2. Directly authorize the client session on MikroTik if valid IP is known (instant internet even if popup closes)
                if ($clientIp && filter_var($clientIp, FILTER_VALIDATE_IP)) {
                    $loginParams = [
                        'user'     => $code,
                        'password' => $code,
                        'ip'       => $clientIp,
                    ];
                    if ($clientMac && preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $clientMac)) {
                        $loginParams['mac-address'] = strtoupper($clientMac);
                    }
                    $api->executeCommand('/ip/hotspot/active/login', $loginParams);
                }

                Log::info("[PayHero] Provisioned Hotspot user '{$code}' ({$limitUptime}) on router {$router->name}" . ($clientIp ? " and logged in IP {$clientIp}" : ''));
            } catch (\Throwable $e) {
                Log::warning("Hotspot user add note on {$router->name}: " . $e->getMessage());
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
