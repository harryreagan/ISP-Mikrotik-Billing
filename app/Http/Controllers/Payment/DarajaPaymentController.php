<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\MpesaStkTransaction;
use App\Models\UnmatchedPayment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
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
}
