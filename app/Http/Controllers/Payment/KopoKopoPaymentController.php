<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\MpesaStkTransaction;
use App\Models\UnmatchedPayment;
use App\Services\KopoKopoPaymentService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KopoKopoPaymentController extends Controller
{
    protected PaymentService $paymentService;
    protected KopoKopoPaymentService $kopoService;

    public function __construct(PaymentService $paymentService, KopoKopoPaymentService $kopoService)
    {
        $this->paymentService = $paymentService;
        $this->kopoService = $kopoService;
    }

    /**
     * Handle KopoKopo Webhook Notification.
     *
     * Rules:
     * - Optional HMAC SHA-256 signature verification.
     * - Reverse-verify payment status with KopoKopo API before activating.
     * - Idempotency guard.
     * - Route to single PaymentService::fulfill().
     */
    public function handleWebhook(Request $request)
    {
        \App\Services\Security\SafePayloadLogger::info('KopoKopo Webhook Received', $request->all());

        // 1. Signature Verification
        $webhookSecret = $this->kopoService->getWebhookSecret();
        if (! empty($webhookSecret)) {
            $signature = $request->header('X-KopoKopo-Signature');
            $rawBody   = $request->getContent();
            $expected  = hash_hmac('sha256', $rawBody, $webhookSecret);

            if (! hash_equals($expected, (string) $signature)) {
                Log::warning('KopoKopo webhook signature mismatch.');
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();
        $data = $payload['data'] ?? [];
        $paymentId = $data['id'] ?? $payload['id'] ?? null;
        $attributes = $data['attributes'] ?? $payload['event']['data'] ?? [];

        $status = strtolower(trim((string) ($attributes['status'] ?? '')));
        $amount = (float) ($attributes['amount']['value'] ?? $attributes['amount'] ?? 0);
        $receipt = strtoupper(trim((string) ($attributes['event']['resource']['reference'] ?? $attributes['reference'] ?? $paymentId)));
        $phone = (string) ($attributes['subscriber']['phone_number'] ?? $attributes['phone_number'] ?? '');
        $metadata = $attributes['metadata'] ?? [];
        $customerRef = $metadata['customer_reference'] ?? null;

        $stkTx = null;
        if ($paymentId) {
            $stkTx = MpesaStkTransaction::where('checkout_request_id', $paymentId)->first();
        }

        // 2. Gate: Payload status check
        if (! in_array($status, ['success', 'successful', 'completed'], true)) {
            Log::warning("KopoKopo webhook non-success status: '{$status}' for payment {$paymentId}");
            if ($stkTx) {
                $stkTx->update(['status' => 'failed', 'raw_callback' => $payload]);
            }
            return response()->json(['status' => 'ignored', 'message' => 'Non-success status'], 200);
        }

        // 3. Reverse-verify with KopoKopo API before activating
        if ($paymentId) {
            $verifyResult = $this->kopoService->verifyPayment($paymentId);
            if (! $verifyResult['verified']) {
                Log::warning("KopoKopo reverse-verification did not confirm success for {$paymentId}");
                return response()->json(['status' => 'ignored', 'message' => 'Reverse verification failed'], 200);
            }
        }

        // 4. Idempotency Check
        if (CollectionSummary::where('transaction_id', $receipt)->exists()) {
            Log::info("KopoKopo duplicate transaction ignored: {$receipt}");
            if ($stkTx) {
                $stkTx->update(['status' => 'completed', 'mpesa_receipt_number' => $receipt]);
            }
            return response()->json(['status' => 'ignored', 'message' => 'Already processed'], 200);
        }

        if ($stkTx) {
            $stkTx->update([
                'status'               => 'completed',
                'mpesa_receipt_number' => $receipt,
                'raw_callback'         => $payload,
            ]);
            $customerRef = $customerRef ?: $stkTx->reference;
        }

        // 5. Customer Matching
        $customer = null;
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

        // 6. Unmatched Logging
        if (! $customer) {
            Log::warning("KopoKopo payment unmatched: Receipt: {$receipt}, Phone: {$phone}, Ref: {$customerRef}. Storing in unmatched_payments.");

            UnmatchedPayment::updateOrCreate(
                ['transaction_code' => $receipt],
                [
                    'source'            => 'kopokopo',
                    'sender'            => 'MPESA',
                    'amount'            => $amount,
                    'phone'             => $phone,
                    'account_reference' => $customerRef,
                    'raw_message'       => "KopoKopo payment {$receipt} Ksh {$amount} from {$phone}",
                    'raw_payload'       => $payload,
                    'status'            => 'pending',
                ]
            );

            return response()->json(['status' => 'unmatched', 'message' => 'Logged to unmatched payments'], 200);
        }

        // 7. Fulfill via centralized PaymentService
        try {
            $this->paymentService->fulfill(
                $customer,
                $amount,
                'kopokopo',
                $receipt,
                [
                    'phone'        => $phone,
                    'payment_id'   => $paymentId,
                    'raw_callback' => $payload,
                ]
            );

            return response()->json([
                'status'           => 'success',
                'message'          => 'KopoKopo payment fulfilled successfully',
                'transaction_code' => $receipt,
            ]);
        } catch (\Exception $e) {
            Log::error("KopoKopo fulfill failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}

