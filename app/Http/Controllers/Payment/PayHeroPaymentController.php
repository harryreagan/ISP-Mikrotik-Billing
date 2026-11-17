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

    /**
     * Initiate Portal STK Push via PayHero.
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

        // Resolve Tenant strictly for isolated captive portal & payments
        $tenantId = 1;
        if ($request->filled('tenant_id')) {
            $tenantId = (int) $request->tenant_id;
        } elseif ($request->filled('router_id')) {
            $router = RouterList::find($request->router_id) ?: \App\Models\Router::find($request->router_id);
            if ($router && $router->tenant_id) {
                $tenantId = (int) $router->tenant_id;
            }
        } elseif ($request->filled('plan_id')) {
            $plan = PackageList::find($request->plan_id);
            if ($plan && $plan->tenant_id) {
                $tenantId = (int) $plan->tenant_id;
            }
        } elseif (auth()->check() && auth()->user()->tenant_id) {
            $tenantId = (int) auth()->user()->tenant_id;
        }

        try {
            $service = PayHeroPaymentService::forTenant($tenantId);
            $result = $service->initiatePayment($phone, $amount, $customerRef);

            if (!empty($result['success'])) {
                // Attach extra metadata if transaction was created
                $checkoutId = $result['checkout_request_id'] ?? $result['reference'] ?? null;
                if ($checkoutId) {
                    MpesaStkTransaction::where('checkout_request_id', $checkoutId)->update([
                        'raw_callback' => [
                            'package_id' => $request->package_id ?? null,
                            'router_id'  => $request->router_id ?? null,
                            'mac'        => $request->mac ?? null,
                            'ip'         => $request->ip ?? null,
                            'tenant_id'  => $tenantId,
                        ],
                    ]);
                }

                return response()->json([
                    'success'             => true,
                    'checkout_request_id' => $checkoutId,
                    'message'             => $result['message'] ?? 'STK push sent to your phone. Please enter your M-Pesa PIN.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initiate STK push.',
            ], 422);

        } catch (\Throwable $e) {
            Log::error('[PayHeroPaymentController] initiatePortalPayment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment service error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status for polling from Captive Portal.
     */
    public function checkPortalStatus(Request $request)
    {
        $checkoutId = $request->query('checkout_id') ?: $request->query('reference');
        $phone = $request->query('phone');

        if ($checkoutId) {
            $stkTx = MpesaStkTransaction::where('checkout_request_id', $checkoutId)->first();

            // 1. If already completed in local DB
            if ($stkTx && $stkTx->status === 'completed') {
                return response()->json([
                    'status'       => 'completed',
                    'voucher_code' => $stkTx->mpesa_receipt_number ?: $stkTx->checkout_request_id,
                    'password'     => substr(preg_replace('/[^0-9]/', '', $stkTx->phone), -4) ?: '1234',
                ]);
            }

            // 2. If pending, query PayHero API reverse verification directly
            if ($stkTx && $stkTx->status === 'pending') {
                try {
                    $verifyResult = $this->payheroService->verifyStatus($checkoutId);
                    if (!empty($verifyResult['is_paid'])) {
                        $vData = $verifyResult['data'] ?? [];
                        $apiReceipt = strtoupper(trim((string) ($vData['mpesa_receipt_number'] ?? $vData['response']['mpesa_receipt_number'] ?? $checkoutId)));
                        $apiAmount = (float) ($vData['amount'] ?? $vData['response']['amount'] ?? $stkTx->amount);

                        $stkTx->update([
                            'status'               => 'completed',
                            'mpesa_receipt_number' => $apiReceipt,
                        ]);

                        // Fulfill subscriber or record collection
                        $this->fulfillSuccessfulTransaction($stkTx, $apiReceipt, $apiAmount);

                        return response()->json([
                            'status'       => 'completed',
                            'voucher_code' => $apiReceipt,
                            'password'     => substr(preg_replace('/[^0-9]/', '', $stkTx->phone), -4) ?: '1234',
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[PayHero checkPortalStatus] Verification poll exception: ' . $e->getMessage());
                }
            }
        }

        // 3. Fallback: check CollectionSummary by phone within last 5 minutes
        if ($phone) {
            $cleanPhone = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($phone) : $phone;
            $recent = CollectionSummary::where(function ($q) use ($phone, $cleanPhone) {
                    $q->where('customer_collection_unique_id', 'like', "%{$phone}%")
                      ->orWhere('customer_collection_unique_id', 'like', "%{$cleanPhone}%");
                })
                ->where('created_at', '>=', now()->subMinutes(5))
                ->latest()
                ->first();

            if ($recent) {
                return response()->json([
                    'status'       => 'completed',
                    'voucher_code' => $recent->transaction_id,
                    'password'     => substr(preg_replace('/[^0-9]/', '', $phone), -4) ?: '1234',
                ]);
            }
        }

        return response()->json(['status' => 'pending']);
    }

    /**
     * Helper to fulfill payment after successful STK confirmation.
     */
    protected function fulfillSuccessfulTransaction(MpesaStkTransaction $stkTx, string $receipt, float $amount): void
    {
        // Check idempotency
        if (CollectionSummary::where('transaction_id', $receipt)->exists()) {
            return;
        }

        $customer = null;
        if ($stkTx->reference) {
            $customer = CustomersInfo::where('customer_unique_id', $stkTx->reference)->first();
            if (!$customer) {
                $customer = CustomersInfo::whereHas('pppUser', fn($q) => $q->where('username', $stkTx->reference))->first();
            }
        }

        if (!$customer && $stkTx->phone) {
            $normalizedPhone = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($stkTx->phone) : $stkTx->phone;
            $customer = CustomersInfo::where('mobile', $normalizedPhone)
                ->orWhere('mobile', '+' . $normalizedPhone)
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
            // Hotspot / guest voucher collection record
            $maxInvoice = CollectionSummary::max('invoice_no');
            $invoiceNo = $maxInvoice ? ($maxInvoice + 1) : 100001;

            CollectionSummary::create([
                'customer_collection_unique_id' => $stkTx->phone,
                'collection_date'               => now(),
                'collection_amount'             => $amount,
                'collected_by'                  => 'Online Payment (PAYHERO)',
                'payment_type'                  => 'online',
                'payment_method'                => 'payhero',
                'transaction_id'                => $receipt,
                'payment_status'                => 'paid',
                'invoice_no'                    => $invoiceNo,
                'bill_month'                    => now()->format('F Y'),
            ]);
        }
    }

    /**
     * Handle PayHero Webhook Notification.
     */
    public function handleWebhook(Request $request)
    {
        \App\Services\Security\SafePayloadLogger::info('PayHero Webhook Received', $request->all());

        $payload = $request->all();
        $responseObj = $payload['response'] ?? $payload;

        $status    = strtoupper(trim((string) ($responseObj['status'] ?? $payload['status'] ?? '')));
        $reference = trim((string) ($responseObj['reference'] ?? $payload['reference'] ?? $payload['external_reference'] ?? ''));
        $receipt   = strtoupper(trim((string) ($responseObj['mpesa_receipt_number'] ?? $payload['mpesa_receipt_number'] ?? $reference)));
        $amount    = (float) ($responseObj['amount'] ?? $payload['amount'] ?? 0);
        $phone     = (string) ($responseObj['phone_number'] ?? $payload['phone_number'] ?? $responseObj['phone'] ?? '');

        $stkTx = MpesaStkTransaction::where('checkout_request_id', $reference)
            ->orWhere('mpesa_receipt_number', $receipt)
            ->first();

        // 1. Check initial status
        if ($status !== 'SUCCESS' && $status !== 'COMPLETED' && $status !== 'SUCCESSFUL') {
            Log::warning("PayHero webhook non-success status: '{$status}' for reference {$reference}");
            if ($stkTx) {
                $stkTx->update([
                    'status'       => 'failed',
                    'raw_callback' => $payload,
                ]);
            }
            return response()->json(['status' => 'ignored', 'message' => 'Transaction was not successful'], 200);
        }

        // 2. Reverse-verify with PayHero API before activating
        $verifyResult = $this->payheroService->verifyStatus($reference);
        if (!$verifyResult['verified']) {
            Log::error("PayHero reverse-verification failed for reference: {$reference}");
            return response()->json(['status' => 'error', 'message' => 'Reverse verification failed'], 422);
        }

        $vData = $verifyResult['data'] ?? [];
        $apiStatus = strtoupper(trim((string) ($vData['status'] ?? $vData['response']['status'] ?? '')));
        $apiAmount = (float) ($vData['amount'] ?? $vData['response']['amount'] ?? $amount);
        $apiReceipt = strtoupper(trim((string) ($vData['mpesa_receipt_number'] ?? $vData['response']['mpesa_receipt_number'] ?? $receipt)));

        if (!in_array($apiStatus, ['SUCCESS', 'COMPLETED', 'SUCCESSFUL'], true)) {
            Log::warning("PayHero reverse-verification confirmed transaction is NOT successful: Status={$apiStatus}");
            return response()->json(['status' => 'ignored', 'message' => 'PayHero API did not confirm successful payment'], 200);
        }

        $finalReceipt = $apiReceipt ?: $receipt;
        $finalAmount  = $apiAmount > 0 ? $apiAmount : $amount;

        // 3. Idempotency Guard
        if (CollectionSummary::where('transaction_id', $finalReceipt)->exists()) {
            Log::info("PayHero duplicate transaction ignored: {$finalReceipt}");
            if ($stkTx) {
                $stkTx->update(['status' => 'completed', 'mpesa_receipt_number' => $finalReceipt]);
            }
            return response()->json(['status' => 'ignored', 'message' => 'Transaction already processed'], 200);
        }

        if ($stkTx) {
            $stkTx->update([
                'status'               => 'completed',
                'mpesa_receipt_number' => $finalReceipt,
                'raw_callback'         => $payload,
            ]);
        }

        // 4. Fulfill transaction
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
