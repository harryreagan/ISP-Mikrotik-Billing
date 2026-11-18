<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\MpesaStkTransaction;
use App\Models\PackageList;
use App\Models\Router;
use App\Models\UnmatchedPayment;
use App\Services\MikrotikApiService;
use App\Services\MikrotikDuration;
use App\Services\PaymentService;
use App\Services\RouterPendingActivationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class DarajaPaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Handle Safaricom Daraja STK Push Callback.
     *
     * Rule: ACTIVATE ONLY WHEN ResultCode == 0.
     * Cancelled/failed/timed-out payments must never activate.
     * Idempotency on CheckoutRequestID and MpesaReceiptNumber.
     */
    public function handleStkCallback(Request $request)
    {
        \App\Services\Security\SafePayloadLogger::info('Daraja STK Push Callback received', $request->all());

        $callback = $request->input('Body.stkCallback');
        if (! $callback) {
            Log::warning('Daraja STK Callback: Missing stkCallback payload');
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $merchantRequestId = $callback['MerchantRequestID'] ?? null;
        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
        $resultCode        = (int) ($callback['ResultCode'] ?? -1);
        $resultDesc        = $callback['ResultDesc'] ?? '';

        $stkTx = MpesaStkTransaction::where('checkout_request_id', $checkoutRequestId)->first();

        // ── STRICT GATE: FAILED / CANCELLED / TIMEOUT PAYMENTS MUST NEVER ACTIVATE ──
        if ($resultCode !== 0) {
            $status = 'failed';
            if ($resultCode === 1032) {
                $status = 'cancelled'; // User cancelled the STK prompt
            } elseif ($resultCode === 1037) {
                $status = 'timeout'; // User timeout / no response
            }

            Log::warning("Daraja STK Push rejected/unsuccessful: CheckoutRequestID={$checkoutRequestId}, ResultCode={$resultCode}, Reason={$resultDesc}");

            if ($stkTx) {
                $stkTx->update([
                    'status'       => $status,
                    'result_code'  => $resultCode,
                    'result_desc'  => $resultDesc,
                    'raw_callback' => $request->all(),
                ]);
            }

            // Always return HTTP 200 with ResultCode 0 to acknowledge Safaricom callback
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // ── SUCCESS PAYMENT (ResultCode == 0) ──────────────────────────────────
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];
        $mpesaReceiptNumber = null;
        $amount             = 0.0;
        $phoneNumber        = null;

        foreach ($metadata as $item) {
            $name  = $item['Name'] ?? '';
            $value = $item['Value'] ?? null;

            if ($name === 'MpesaReceiptNumber') {
                $mpesaReceiptNumber = strtoupper(trim((string) $value));
            } elseif ($name === 'Amount') {
                $amount = (float) $value;
            } elseif ($name === 'PhoneNumber') {
                $phoneNumber = (string) $value;
            }
        }

        if (! $mpesaReceiptNumber || $amount <= 0) {
            Log::error("Daraja STK Callback: ResultCode 0 but missing receipt or amount. Receipt: {$mpesaReceiptNumber}, Amount: {$amount}");
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // ── IDEMPOTENCY GUARD ─────────────────────────────────────────────────
        if (CollectionSummary::where('transaction_id', $mpesaReceiptNumber)->exists()) {
            Log::info("Daraja STK Callback: Receipt [{$mpesaReceiptNumber}] already processed. Idempotent skip.");
            if ($stkTx) {
                $stkTx->update([
                    'status'               => 'completed',
                    'mpesa_receipt_number' => $mpesaReceiptNumber,
                    'result_code'          => 0,
                    'result_desc'          => $resultDesc,
                    'raw_callback'         => $request->all(),
                ]);
            }
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // Update STK transaction status
        if ($stkTx) {
            $stkTx->update([
                'status'               => 'completed',
                'mpesa_receipt_number' => $mpesaReceiptNumber,
                'result_code'          => 0,
                'result_desc'          => $resultDesc,
                'raw_callback'         => $request->all(),
            ]);
        }

        // ── MATCH CUSTOMER ────────────────────────────────────────────────────
        $customer = null;
        $reference = $stkTx?->reference;

        if ($reference) {
            $customer = CustomersInfo::where('customer_unique_id', $reference)->first();
            if (! $customer) {
                $customer = CustomersInfo::whereHas('pppUser', fn($q) => $q->where('username', $reference))->first();
            }
        }

        if (! $customer && $phoneNumber) {
            $normalizedPhone = normalizeKenyanPhone($phoneNumber);
            $local0 = $normalizedPhone ? ('0' . substr($normalizedPhone, 3)) : null;

            $customer = CustomersInfo::where('mobile', $normalizedPhone)
                ->orWhere('mobile', '+' . $normalizedPhone)
                ->when($local0, fn($q) => $q->orWhere('mobile', $local0))
                ->first();
        }

        if (! $customer) {
            $meta = is_array($stkTx?->raw_callback) ? $stkTx->raw_callback : [];
            $isHotspot = ($stkTx && ($stkTx->router_id || !empty($meta['router_id']) || !empty($meta['package_id']) || str_starts_with((string)$reference, 'HS-')));

            if ($isHotspot || $stkTx) {
                // Fulfill as Hotspot voucher sale
                $this->fulfillHotspotTransaction($stkTx, $mpesaReceiptNumber, $amount, $phoneNumber);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            Log::warning("Daraja STK Payment received but customer could not be resolved. Receipt: {$mpesaReceiptNumber}, Phone: {$phoneNumber}, Ref: {$reference}. Storing in unmatched_payments.");

            UnmatchedPayment::updateOrCreate(
                ['transaction_code' => $mpesaReceiptNumber],
                [
                    'source'            => 'daraja',
                    'sender'            => 'MPESA',
                    'amount'            => $amount,
                    'phone'             => $phoneNumber,
                    'account_reference' => $reference,
                    'raw_message'       => "Daraja STK Push {$mpesaReceiptNumber} Ksh {$amount} from {$phoneNumber}",
                    'raw_payload'       => $request->all(),
                    'status'            => 'pending',
                ]
            );

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // ── FULFILL VIA PAYMENTSERVICE ────────────────────────────────────────
        try {
            $this->paymentService->fulfill(
                $customer,
                $amount,
                'daraja',
                $mpesaReceiptNumber,
                [
                    'checkout_request_id' => $checkoutRequestId,
                    'phone'               => $phoneNumber,
                    'raw_callback'        => $request->all(),
                ]
            );
        } catch (\Exception $e) {
            Log::error("Daraja STK fulfill failed for {$customer->customer_unique_id}: " . $e->getMessage());
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Handle Daraja C2B Validation URL.
     */
    public function handleC2bValidation(Request $request)
    {
        \App\Services\Security\SafePayloadLogger::info('Daraja C2B Validation request', $request->all());
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Handle Daraja C2B Confirmation URL.
     */
    public function handleC2bConfirmation(Request $request)
    {
        \App\Services\Security\SafePayloadLogger::info('Daraja C2B Confirmation request', $request->all());

        $transId      = strtoupper(trim((string) $request->input('TransID')));
        $amount       = (float) $request->input('TransAmount');
        $billRef      = trim((string) $request->input('BillRefNumber'));
        $msisdn       = (string) $request->input('MSISDN');
        $firstName    = trim((string) $request->input('FirstName'));
        $lastName     = trim((string) $request->input('LastName'));
        $senderName   = trim("{$firstName} {$lastName}");

        if (! $transId || $amount <= 0) {
            Log::warning('Daraja C2B Confirmation: Invalid transId or amount.');
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // Idempotency Check
        if (CollectionSummary::where('transaction_id', $transId)->exists()) {
            Log::info("Daraja C2B duplicate transId ignored: {$transId}");
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // Customer Matching
        $customer = null;
        if ($billRef) {
            $customer = CustomersInfo::where('customer_unique_id', $billRef)->first();
            if (! $customer) {
                $customer = CustomersInfo::whereHas('pppUser', fn($q) => $q->where('username', $billRef))->first();
            }
        }

        if (! $customer && $msisdn) {
            $normalizedPhone = normalizeKenyanPhone($msisdn);
            $local0 = $normalizedPhone ? ('0' . substr($normalizedPhone, 3)) : null;

            $customer = CustomersInfo::where('mobile', $normalizedPhone)
                ->orWhere('mobile', '+' . $normalizedPhone)
                ->when($local0, fn($q) => $q->orWhere('mobile', $local0))
                ->first();
        }

        if (! $customer) {
            Log::warning("Daraja C2B unmatched: TransID: {$transId}, BillRef: {$billRef}, MSISDN: {$msisdn}. Storing in unmatched_payments.");

            UnmatchedPayment::updateOrCreate(
                ['transaction_code' => $transId],
                [
                    'source'            => 'daraja_c2b',
                    'sender'            => 'MPESA',
                    'amount'            => $amount,
                    'phone'             => $msisdn,
                    'sender_name'       => $senderName,
                    'account_reference' => $billRef,
                    'raw_message'       => "Daraja C2B {$transId} Ksh {$amount} from {$msisdn} ({$senderName}) Ref: {$billRef}",
                    'raw_payload'       => $request->all(),
                    'status'            => 'pending',
                ]
            );

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        try {
            $this->paymentService->fulfill(
                $customer,
                $amount,
                'daraja_c2b',
                $transId,
                [
                    'phone'             => $msisdn,
                    'sender_name'       => $senderName,
                    'account_reference' => $billRef,
                    'raw_callback'      => $request->all(),
                ]
            );
        } catch (\Exception $e) {
            Log::error("Daraja C2B fulfillment failed for {$customer->customer_unique_id}: " . $e->getMessage());
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Initiate STK Push from Captive Portal / Checkout.
     */
    public function initiateStkPush(Request $request)
    {
        $request->validate([
            'phone'  => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $phone = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($request->phone) : $request->phone;
        $amount = (float) $request->amount;
        $ref = $request->account_reference ?: ('HS-' . substr($phone, -4));

        try {
            if (class_exists(\App\Services\DarajaService::class)) {
                $response = \App\Services\DarajaService::initiateStkPush(
                    $phone,
                    $amount,
                    $ref,
                    'Hotspot Package Purchase'
                );

                return response()->json([
                    'success'             => true,
                    'checkout_request_id' => $response['CheckoutRequestID'] ?? ('MOCK-' . time()),
                    'message'             => 'STK Push sent to phone.',
                ]);
            }

            return response()->json([
                'success'             => true,
                'checkout_request_id' => 'STK-' . time(),
                'message'             => 'STK Push initiated.',
            ]);
        } catch (\Throwable $e) {
            Log::error('[DarajaPaymentController] STK push error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate STK push: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status for polling from Captive Portal.
     */
    public function checkPaymentStatus(Request $request)
    {
        $checkoutId = $request->query('checkout_id');
        $phone = $request->query('phone');

        if ($checkoutId) {
            $tx = MpesaStkTransaction::where('checkout_request_id', $checkoutId)->first();
            if ($tx && $tx->status === 'completed') {
                return response()->json([
                    'status'       => 'completed',
                    'voucher_code' => $tx->mpesa_receipt_number,
                    'password'     => substr($tx->phone, -4),
                ]);
            }
        }

        if ($phone) {
            $recent = CollectionSummary::where('customer_collection_unique_id', 'like', "%{$phone}%")
                ->orWhere('payment_type', 'online')
                ->latest()
                ->first();

            if ($recent && $recent->created_at && $recent->created_at->diffInMinutes() < 5) {
                return response()->json([
                    'status'       => 'completed',
                    'voucher_code' => $recent->transaction_id,
                    'password'     => substr($phone, -4),
                ]);
            }
        }

        return response()->json(['status' => 'pending']);
    }

    /**
     * Fulfill a confirmed M-Pesa STK payment as a Hotspot purchase.
     * Decouples financial record from router reachability.
     */
    protected function fulfillHotspotTransaction(?MpesaStkTransaction $stkTx, string $receipt, float $amount, ?string $phone): void
    {
        $receipt = strtoupper(trim($receipt));
        $meta = is_array($stkTx?->raw_callback) ? $stkTx->raw_callback : [];
        $tenantId = (int) ($stkTx?->tenant_id ?: ($meta['tenant_id'] ?? 1));
        $routerId = !empty($meta['router_id']) ? (int) $meta['router_id'] : ($stkTx?->router_id ?: null);
        $packageId = $meta['package_id'] ?? null;
        $clientIp = $meta['ip'] ?? null;
        $clientMac = $meta['mac'] ?? null;

        if ($stkTx && (!$stkTx->tenant_id || !$stkTx->router_id)) {
            MpesaStkTransaction::withoutGlobalScopes()->where('id', $stkTx->id)->update([
                'tenant_id' => $tenantId,
                'router_id' => $routerId,
            ]);
        }

        // 1. Resolve package details
        $durationInfo = MikrotikDuration::parse('1h');
        $limitBytes = null;
        $profileName = 'Hotspot Plan';
        if ($packageId) {
            $pkg = PackageList::withoutGlobalScopes()->find($packageId);
            if ($pkg) {
                $profileName = $pkg->package_name ?: ($pkg->name ?: 'Hotspot Plan');
                $durationVal = !empty($pkg->duration) ? $pkg->duration : (!empty($pkg->validity) ? $pkg->validity : '1h');
                $durationUnit = !empty($pkg->duration_unit) ? $pkg->duration_unit : null;
                $durationInfo = MikrotikDuration::parse($durationVal, $durationUnit);

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
        $durationSeconds = $durationInfo['seconds'] > 0 ? $durationInfo['seconds'] : 3600;
        $durationMinutes = (int) round($durationSeconds / 60);
        $limitUptime = $durationInfo['mikrotik'];

        // 2. Resolve Router
        $routerObj = null;
        $routerName = 'Hotspot';
        if ($routerId) {
            $routerObj = Router::withoutGlobalScopes()->find($routerId);
            if ($routerObj) {
                $routerName = $routerObj->name;
            }
        }

        // 3. Stacking calculation
        $existingActive = HotspotSale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($phone, $clientMac) {
                if ($phone) $q->where('phone', $phone);
                if ($clientMac) $q->orWhere('mac_address', strtoupper($clientMac));
            })
            ->where('expires_at', '>', now())
            ->whereIn('status', ['completed', 'active', 'pending_router'])
            ->latest('expires_at')
            ->first();

        if ($existingActive && $existingActive->expires_at) {
            $startedAt = $existingActive->started_at ?: now();
            $expiresAt = Carbon::parse($existingActive->expires_at)->addSeconds($durationSeconds);
            $totalMinutes = (int) $existingActive->duration_minutes + $durationMinutes;
        } else {
            $startedAt = now();
            $expiresAt = now()->addSeconds($durationSeconds);
            $totalMinutes = $durationMinutes;
        }

        // 4. Record HotspotSale FIRST
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
                    'username'         => $phone ?: $receipt,
                    'phone'            => $phone,
                    'mac_address'      => $clientMac ? strtoupper($clientMac) : null,
                    'ip_address'       => $clientIp,
                    'amount'           => $amount,
                    'payment_method'   => 'mpesa_daraja',
                    'status'           => 'pending_router',
                    'duration_minutes' => $totalMinutes,
                    'started_at'       => $startedAt,
                    'expires_at'       => $expiresAt,
                    'note'             => 'M-Pesa Daraja STK (' . $receipt . ')',
                    'sale_date'        => now()->timezone('Africa/Nairobi')->toDateString(),
                ]
            );
        } catch (\Throwable $hse) {
            Log::warning('[Daraja] HotspotSale save note: ' . $hse->getMessage());
        }

        // 5. Record CollectionSummary FIRST
        try {
            if (!CollectionSummary::withoutGlobalScopes()->where('transaction_id', $receipt)->exists()) {
                $maxInvoice = CollectionSummary::withoutGlobalScopes()->max('invoice_no');
                $invoiceNo = $maxInvoice ? ($maxInvoice + 1) : 100001;

                CollectionSummary::withoutGlobalScopes()->create([
                    'tenant_id'                     => $tenantId,
                    'customer_collection_unique_id' => $phone,
                    'collection_date'               => now(),
                    'collection_amount'             => $amount,
                    'collected_by'                  => 'Hotspot M-Pesa (DARAJA)',
                    'payment_type'                  => 'hotspot',
                    'payment_method'                => 'daraja',
                    'transaction_id'                => $receipt,
                    'payment_status'                => 'paid',
                    'invoice_no'                    => $invoiceNo,
                    'bill_month'                    => now()->format('F Y'),
                ]);
            }
        } catch (\Throwable $ce) {
            Log::warning('[Daraja] CollectionSummary save note: ' . $ce->getMessage());
        }

        // 6. Router Provisioning (Instant or Queue)
        try {
            $this->provisionHotspotVoucherOnRouter(
                $receipt,
                $phone,
                $tenantId,
                $routerId,
                $limitUptime,
                $limitBytes,
                $clientIp,
                $clientMac,
                $expiresAt,
                $hotspotSale?->id
            );
        } catch (\Throwable $he) {
            Log::warning('[Daraja] Hotspot router provisioning note: ' . $he->getMessage());
        }
    }

    /**
     * Provision a paid Hotspot user on the router with fallback queueing.
     */
    protected function provisionHotspotVoucherOnRouter(
        string $receipt,
        ?string $phone,
        int $tenantId,
        ?int $routerId,
        string $limitUptime,
        ?int $limitBytes,
        ?string $clientIp,
        ?string $clientMac,
        Carbon $expiresAt,
        ?int $saleId
    ): void {
        $routersQuery = Router::withoutGlobalScopes()->where(function ($q) {
            $q->where('status', 'online')
              ->orWhere('action', 'connected')
              ->orWhereNotNull('vpn_ip')
              ->orWhereNotNull('last_known_ip');
        });
        if ($routerId) {
            $routersQuery->where('id', $routerId);
        } else {
            $routersQuery->where('tenant_id', $tenantId);
        }
        $routers = $routersQuery->get();

        if ($routers->isEmpty()) {
            RouterPendingActivationService::enqueue(
                $tenantId,
                $routerId,
                $receipt,
                $phone ?: $receipt,
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
            try {
                $pass = Crypt::decryptString($router->api_password ?: $router->password);
            } catch (\Throwable $e) {
                $pass = $router->api_password ?: $router->password;
            }

            try {
                $api = new MikrotikApiService($host, $port, $user, $pass, 5);
                $code = strtoupper(trim($receipt));

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

                $comment = 'M-Pesa ' . ($phone ?: '') . ' exp:' . $expiresAt->toDateTimeString();
                $userPayload = [
                    'name'         => $code,
                    'password'     => $code,
                    'limit-uptime' => $limitUptime,
                    'comment'      => $comment,
                ];
                if ($limitBytes && $limitBytes > 0) {
                    $userPayload['limit-bytes-total'] = (string) $limitBytes;
                }

                if ($matchedUser && !empty($matchedUser['.id'])) {
                    $userPayload['.id'] = $matchedUser['.id'];
                    $api->executeCommand('/ip/hotspot/user/set', $userPayload);
                } else {
                    $api->executeCommand('/ip/hotspot/user/add', $userPayload);
                }

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

                $provisionedAny = true;
                Log::info("[Daraja] Provisioned Hotspot user '{$code}' ({$limitUptime}) on router {$router->name}");
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
            RouterPendingActivationService::enqueue(
                $tenantId,
                $routerId,
                $receipt,
                $phone ?: $receipt,
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
}
