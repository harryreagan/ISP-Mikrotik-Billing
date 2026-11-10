<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\MpesaStkTransaction;
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
     * Handle PayHero Webhook Notification.
     *
     * Rules:
     * - Reverse-verify transaction status with PayHero API before activating.
     * - Idempotency guard.
     * - Route to single PaymentService::fulfill().
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

        // 1. Gate 1: Check initial status
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

        // 2. Gate 2: Reverse-verify the transaction status with PayHero API before activating!
        $verifyResult = $this->payheroService->verifyStatus($reference);
        if (! $verifyResult['verified']) {
            Log::error("PayHero reverse-verification failed for reference: {$reference}");
            return response()->json(['status' => 'error', 'message' => 'Reverse verification failed'], 422);
        }

        $vData = $verifyResult['data'] ?? [];
        $apiStatus = strtoupper(trim((string) ($vData['status'] ?? $vData['response']['status'] ?? '')));
        $apiAmount = (float) ($vData['amount'] ?? $vData['response']['amount'] ?? $amount);
        $apiReceipt = strtoupper(trim((string) ($vData['mpesa_receipt_number'] ?? $vData['response']['mpesa_receipt_number'] ?? $receipt)));

        if (! in_array($apiStatus, ['SUCCESS', 'COMPLETED', 'SUCCESSFUL'], true)) {
            Log::warning("PayHero reverse-verification confirmed transaction is NOT successful: Status={$apiStatus}");
            return response()->json(['status' => 'ignored', 'message' => 'PayHero API did not confirm successful payment'], 200);
        }

        $finalReceipt = $apiReceipt ?: $receipt;
        $finalAmount  = $apiAmount > 0 ? $apiAmount : $amount;

        // 3. Gate 3: Idempotency Guard
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

        // 4. Gate 4: Customer Matching
        $customer = null;
        $customerRef = $stkTx?->reference;

        if ($customerRef) {
            $customer = CustomersInfo::where('customer_unique_id', $customerRef)->first();
            if (! $customer) {
                $customer = CustomersInfo::whereHas('pppUser', fn($q) => $q->where('username', $customerRef))->first();
            }
        }

        if (! $customer && $phone) {
            $normalizedPhone = normalizeKenyanPhone($phone);
            $local0 = $normalizedPhone ? ('0' . substr($normalizedPhone, 3)) : null;

            $customer = CustomersInfo::where('mobile', $normalizedPhone)
                ->orWhere('mobile', '+' . $normalizedPhone)
                ->when($local0, fn($q) => $q->orWhere('mobile', $local0))
                ->first();
        }

        // 5. Unmatched handling
        if (! $customer) {
            Log::warning("PayHero payment unmatched: Receipt: {$finalReceipt}, Phone: {$phone}, Ref: {$reference}. Storing in unmatched_payments.");

            UnmatchedPayment::updateOrCreate(
                ['transaction_code' => $finalReceipt],
                [
                    'source'            => 'payhero',
                    'sender'            => 'MPESA',
                    'amount'            => $finalAmount,
                    'phone'             => $phone,
                    'account_reference' => $customerRef ?: $reference,
                    'raw_message'       => "PayHero payment {$finalReceipt} Ksh {$finalAmount} from {$phone}",
                    'raw_payload'       => $payload,
                    'status'            => 'pending',
                ]
            );

            return response()->json(['status' => 'unmatched', 'message' => 'Logged to unmatched payments'], 200);
        }

        // 6. Fulfill via centralized PaymentService
        try {
            $this->paymentService->fulfill(
                $customer,
                $finalAmount,
                'payhero',
                $finalReceipt,
                [
                    'phone'        => $phone,
                    'reference'    => $reference,
                    'raw_callback' => $payload,
                ]
            );

            return response()->json([
                'status'           => 'success',
                'message'          => 'PayHero payment fulfilled successfully',
                'transaction_code' => $finalReceipt,
            ]);
        } catch (\Exception $e) {
            Log::error("PayHero fulfill failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}

