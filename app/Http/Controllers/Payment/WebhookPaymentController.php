<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookPaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Handle incoming bKash Merchant IPN webhook.
     */
    public function handleBkashIpn(Request $request)
    {
        // ── Gate 1: bKash payment must be enabled in settings ──────────────────
        if (! siteUrlSettings('payment_bkash_enabled')) {
            Log::warning('bKash IPN received but bKash payment gateway is disabled.');
            return response()->json(['status' => 'error', 'message' => 'Gateway not enabled'], 403);
        }

        // Log the raw incoming payload for auditing (before any processing)
        Log::info('bKash Merchant IPN Webhook received: ' . json_encode($request->all()));

        // ── Gate 2: HMAC-SHA256 shared-secret signature verification ───────────
        // bKash (or your proxy) must send X-Bkash-Signature: HMAC-SHA256(secret, raw_body)
        // Configure payment_bkash_ipn_secret in your site settings / services config.
        $webhookSecret = siteUrlSettings('payment_bkash_ipn_secret')
            ?: config('services.bkash.ipn_secret');

        if (! empty($webhookSecret)) {
            $signature = $request->header('X-Bkash-Signature');
            $rawBody   = $request->getContent();
            $expected  = hash_hmac('sha256', $rawBody, $webhookSecret);

            if (! hash_equals($expected, (string) $signature)) {
                Log::warning('bKash IPN rejected: invalid HMAC signature.');
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
            }
        } else {
            Log::warning('bKash IPN: payment_bkash_ipn_secret is not configured — signature check skipped (set it ASAP).');
        }

        // ── Parse fields ───────────────────────────────────────────────────────
        $trxId          = $request->input('trxID') ?? $request->input('transactionId') ?? $request->input('trxId');
        $amount         = $request->input('amount');
        $payerReference = $request->input('payerReference') ?? $request->input('reference') ?? $request->input('payerRef');
        $status         = strtolower($request->input('transactionStatus') ?? $request->input('status') ?? '');

        if (! $trxId || ! $amount || ! $payerReference) {
            Log::warning('bKash IPN missing required fields: trxID, amount, or payerReference.');
            return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
        }

        // ── Gate 3: IPN-reported status must be a success value ────────────────
        if (! in_array($status, ['completed', 'successful', 'success'], true)) {
            Log::info("bKash IPN transaction ignored. Status is '{$status}' (expected Completed).");
            return response()->json(['status' => 'ignored', 'message' => 'Non-success transaction status'], 200);
        }

        // ── Gate 4: trxId idempotency — reject already-processed transactions ──
        if (CollectionSummary::where('transaction_id', $trxId)->exists()) {
            Log::info("bKash IPN duplicate trxId rejected: {$trxId}");
            return response()->json(['status' => 'ignored', 'message' => 'Transaction already processed'], 200);
        }

        // ── Gate 5: Server-side Query Payment verification via bKash API ───────
        // Do NOT trust the IPN payload alone; confirm with bKash's own Query API.
        try {
            $verified = $this->verifyBkashTransaction($trxId, (float) $amount);
        } catch (\Exception $e) {
            Log::error('bKash IPN Query Payment API call failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gateway verification failed'], 502);
        }

        if (! $verified) {
            Log::warning("bKash IPN rejected: Query Payment API did not confirm trxId={$trxId} amount={$amount}");
            return response()->json(['status' => 'error', 'message' => 'Payment verification failed'], 422);
        }

        // ── Gate 6: Look up the customer ───────────────────────────────────────
        // Use a constant-time response to avoid customer-existence oracle
        $customer = CustomersInfo::where('customer_unique_id', $payerReference)->first();

        if (! $customer) {
            Log::warning("bKash IPN customer not found for payerReference: {$payerReference}");
            // Return 200 to avoid leaking customer existence to an attacker
            return response()->json(['status' => 'ignored', 'message' => 'Reference not matched'], 200);
        }

        // ── Process payment ────────────────────────────────────────────────────
        try {
            $this->paymentService->processSuccessPayment($customer, (float) $amount, 'bkash', $trxId);
            return response()->json(['status' => 'success', 'message' => 'Payment processed successfully']);
        } catch (\Exception $e) {
            Log::error('bKash IPN processing failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify a bKash transaction server-side by calling the Query Payment API.
     * Returns true only if the gateway confirms the payment as completed and
     * the gateway-returned amount matches the IPN-reported amount.
     */
    private function verifyBkashTransaction(string $trxId, float $ipnAmount): bool
    {
        $config  = $this->getBkashConfig();
        $apiType = siteUrlSettings('payment_bkash_api_type') ?: 'tokenized';

        // Obtain a short-lived token from bKash
        $tokenUrl = ($apiType === 'tokenized')
            ? $config['base_url'] . '/tokenized/checkout/token/grant'
            : $config['base_url'] . '/checkout/token/grant';

        $tokenResp = Http::withBasicAuth($config['username'], $config['password'])
            ->post($tokenUrl, [
                'app_key'    => $config['app_key'],
                'app_secret' => $config['app_secret'],
            ]);

        $tokenBody = $tokenResp->json();
        if (empty($tokenBody['id_token'])) {
            throw new \Exception('bKash token grant failed: ' . json_encode($tokenBody));
        }
        $idToken = $tokenBody['id_token'];

        // Query the payment by trxId
        $queryUrl = ($apiType === 'tokenized')
            ? $config['base_url'] . '/tokenized/checkout/payment/query/' . urlencode($trxId)
            : $config['base_url'] . '/checkout/payment/query/' . urlencode($trxId);

        $queryResp = Http::withToken($idToken)
            ->withHeaders(['X-App-Key' => $config['app_key']])
            ->get($queryUrl);

        $res = $queryResp->json();
        Log::info('bKash Query Payment response for trxId=' . $trxId . ': ' . json_encode($res));

        // Validate: statusCode 0000 = success, and amounts must match within 0.01 BDT
        $gatewayStatus = strtolower($res['transactionStatus'] ?? $res['statusMessage'] ?? '');
        $gatewayAmount = (float) ($res['amount'] ?? 0);
        $statusCode    = $res['statusCode'] ?? null;

        $statusOk = ($statusCode === '0000')
            || in_array($gatewayStatus, ['completed', 'successful', 'success'], true);
        $amountOk = abs($gatewayAmount - $ipnAmount) < 0.01;

        return $statusOk && $amountOk;
    }

    /**
     * Return the bKash API configuration from site settings / config.
     */
    private function getBkashConfig(): array
    {
        return [
            'base_url'   => siteUrlSettings('payment_bkash_base_url')   ?: config('services.bkash.base_url'),
            'username'   => siteUrlSettings('payment_bkash_username')    ?: config('services.bkash.username'),
            'password'   => siteUrlSettings('payment_bkash_password')    ?: config('services.bkash.password'),
            'app_key'    => siteUrlSettings('payment_bkash_app_key')     ?: config('services.bkash.app_key'),
            'app_secret' => siteUrlSettings('payment_bkash_app_secret')  ?: config('services.bkash.app_secret'),
        ];
    }

    /**
     * Handle M-Pesa SMS Forwarder webhooks.
     *
     * Kenya M-Pesa Forwarder rules:
     * - Auth with Bearer token in header ONLY (reject ?api_token= or query token).
     * - Sender must be "MPESA" only.
     * - Reject any SMS containing OTP/PIN/password/secret code.
     * - Extract transaction code, amount, phone, name, account reference using MpesaSmsParser.
     * - Unique check on transaction code.
     * - Store raw SMS.
     * - Match customer by account ref / PPP username / customer ID / phone.
     * - If matched: PaymentService::fulfill().
     * - If unmatched: store in unmatched_payments for manual assignment.
     */
    public function handleSmsReceiver(Request $request)
    {
        // 1. Gate 1: Reject query string token authentication explicitly
        if ($request->has('api_token') || $request->has('token')) {
            Log::warning('SMS Receiver rejected: Query string token authentication attempted.');
            return response()->json([
                'status' => 'error',
                'message' => 'Query string token authentication is disabled. Provide Bearer token in Authorization header.'
            ], 401);
        }

        // 2. Gate 2: Header Bearer token authentication ONLY
        $bearerToken = $request->bearerToken();
        if (empty($bearerToken)) {
            Log::warning('SMS Receiver rejected: Missing Authorization Bearer header.');
            return response()->json([
                'status' => 'error',
                'message' => 'Authorization header with Bearer token is required.'
            ], 401);
        }

        $expectedToken = siteUrlSettings('sms_receiver_token') ?: config('services.sms_forwarder.token', env('SMS_RECEIVER_TOKEN'));
        $authorized = false;

        if (! empty($expectedToken) && hash_equals($expectedToken, $bearerToken)) {
            $authorized = true;
        } else {
            // Alternatively check Sanctum personal access token
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
            if ($pat && $pat->tokenable) {
                $authorized = true;
            }
        }

        if (! $authorized) {
            Log::warning('SMS Receiver rejected: Invalid Bearer token provided.');
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Bearer token.'
            ], 401);
        }

        // Log the received SMS payload safely with secrets redacted
        \App\Services\Security\SafePayloadLogger::info('M-Pesa SMS Forwarder Webhook received payload', $request->all());

        // 3. Gate 3: Sender verification — accept "MPESA" only
        $rawSender = $request->input('sender') ?? $request->input('from') ?? $request->input('sender_name') ?? '';
        $cleanSender = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', (string) $rawSender));

        if ($cleanSender !== 'MPESA') {
            Log::warning("SMS Receiver rejected: Non-MPESA sender '{$rawSender}'");
            return response()->json([
                'status' => 'error',
                'message' => 'Sender must be MPESA'
            ], 403);
        }

        $message = $request->input('message') ?? $request->input('text') ?? $request->input('body') ?? $request->input('msg') ?? '';
        if (empty(trim((string) $message))) {
            return response()->json(['status' => 'ignored', 'message' => 'Empty message content'], 200);
        }

        // 4. Gate 4: Reject sensitive security keywords (OTP / PIN / password / verification code)
        if (preg_match('/\b(otp|pin|password|secret\s*code|verification\s*code|one\s*time\s*pin)\b/i', $message)) {
            Log::warning('SMS Receiver rejected: Message contains sensitive OTP/PIN/Password keyword.');
            return response()->json([
                'status' => 'ignored',
                'message' => 'Security keyword detected in SMS content'
            ], 422);
        }

        // 5. Gate 5: Parse M-Pesa SMS using MpesaSmsParser
        $parsed = \App\Services\MpesaSmsParser::parse($message);
        if (! $parsed) {
            Log::warning('SMS Receiver: Failed to parse M-Pesa parameters from SMS: ' . $message);
            return response()->json(['status' => 'error', 'message' => 'Failed to parse M-Pesa SMS format'], 422);
        }

        $trxId            = $parsed['transaction_code'];
        $amount           = $parsed['amount'];
        $senderPhone      = $parsed['phone'];
        $senderName       = $parsed['sender_name'];
        $accountReference = $parsed['account_reference'];

        // 6. Gate 6: Idempotency check — reject already-processed transactions
        if (CollectionSummary::where('transaction_id', $trxId)->exists()) {
            Log::info("SMS Receiver duplicate transaction ignored: {$trxId}");
            return response()->json(['status' => 'ignored', 'message' => 'Transaction already processed'], 200);
        }

        // 7. Customer Matching Strategy
        $customer = null;

        // A. Match by Account Reference -> Customer Unique ID
        if ($accountReference) {
            $customer = CustomersInfo::where('customer_unique_id', $accountReference)->first();
        }

        // B. Match by Account Reference -> PPP Secret Username
        if (! $customer && $accountReference) {
            $customer = CustomersInfo::whereHas('pppUser', function ($query) use ($accountReference) {
                $query->where('username', $accountReference);
            })->first();
        }

        // C. Match by Sender's Mobile Number (254 format)
        if (! $customer && $senderPhone) {
            $normalizedMobile = normalizeKenyanPhone($senderPhone);
            $local0 = $normalizedMobile ? ('0' . substr($normalizedMobile, 3)) : null;

            $customer = CustomersInfo::where('mobile', $normalizedMobile)
                ->orWhere('mobile', '+' . $normalizedMobile)
                ->when($local0, fn($q) => $q->orWhere('mobile', $local0))
                ->orWhere('alternative_mobile', $normalizedMobile)
                ->first();
        }

        // 8. If Customer Not Matched -> Store in unmatched_payments for manual assignment
        if (! $customer) {
            Log::warning("No matching customer found for M-Pesa SMS. TrxID: {$trxId}, Ref: {$accountReference}, Phone: {$senderPhone}. Logging to unmatched_payments.");

            \App\Models\UnmatchedPayment::updateOrCreate(
                ['transaction_code' => $trxId],
                [
                    'source'            => 'mpesa_sms',
                    'sender'            => $rawSender ?: 'MPESA',
                    'amount'            => $amount,
                    'phone'             => $senderPhone,
                    'sender_name'       => $senderName,
                    'account_reference' => $accountReference,
                    'raw_message'       => $message,
                    'raw_payload'       => $request->all(),
                    'status'            => 'pending',
                ]
            );

            return response()->json([
                'status'           => 'unmatched',
                'message'          => 'Payment logged to unmatched payments for manual assignment',
                'transaction_code' => $trxId,
                'amount'           => $amount,
            ], 200);
        }

        // 9. If Customer Matched -> Fulfill payment and mark record matched
        try {
            // First record raw unmatched record as matched for tracking
            \App\Models\UnmatchedPayment::updateOrCreate(
                ['transaction_code' => $trxId],
                [
                    'source'             => 'mpesa_sms',
                    'sender'             => $rawSender ?: 'MPESA',
                    'amount'             => $amount,
                    'phone'              => $senderPhone,
                    'sender_name'        => $senderName,
                    'account_reference'  => $accountReference,
                    'raw_message'        => $message,
                    'raw_payload'        => $request->all(),
                    'status'             => 'matched',
                    'customer_id'        => $customer->id,
                    'customer_unique_id' => $customer->customer_unique_id,
                    'assigned_at'        => now(),
                ]
            );

            $this->paymentService->fulfill(
                $customer,
                (float) $amount,
                'mpesa_sms',
                $trxId,
                [
                    'sender_phone'       => $senderPhone,
                    'sender_name'        => $senderName,
                    'account_reference'  => $accountReference,
                    'raw_sms'            => $message,
                ]
            );

            return response()->json([
                'status'           => 'success',
                'message'          => 'Payment fulfilled and customer renewed successfully',
                'customer'         => $customer->customer_unique_id,
                'amount'           => $amount,
                'gateway'          => 'mpesa_sms',
                'transaction_code' => $trxId,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to fulfill M-Pesa SMS payment: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Payment fulfillment error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper method to parse MFS notification SMS texts using regex patterns.
     */
    private function parseSms($sender, $message)
    {
        $senderLower = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sender));
        $amount = null;
        $trxId = null;
        $ref = null;
        $senderMobile = null;
        $gateway = 'mfs';

        if (str_contains($senderLower, 'bkash')) {
            $gateway = 'bkash';
            // Parse Amount
            if (preg_match('/(?:Received BDT|Cash In BDT|Payment received BDT)\s*(\d+(?:\.\d+)?)/i', $message, $matches)) {
                $amount = (float)$matches[1];
            }
            // Parse Sender Mobile
            if (preg_match('/from\s*(\d{11})/i', $message, $matches)) {
                $senderMobile = $matches[1];
            }
            // Parse TrxID
            if (preg_match('/TrxID\s*([A-Z0-9]+)/i', $message, $matches)) {
                $trxId = $matches[1];
            }
            // Parse Ref
            if (preg_match('/Ref\s*([a-zA-Z0-9_-]+)/i', $message, $matches)) {
                $ref = $matches[1];
            }
        } elseif (str_contains($senderLower, 'nagad')) {
            $gateway = 'nagad';
            // Parse Amount
            if (preg_match('/(?:Received Amount:\s*Tk\s*|Received BDT\s*)(\d+(?:\.\d+)?)/i', $message, $matches)) {
                $amount = (float)$matches[1];
            }
            // Parse Sender Mobile
            if (preg_match('/From:\s*(\d{11})/i', $message, $matches)) {
                $senderMobile = $matches[1];
            }
            // Parse TxnID
            if (preg_match('/TxnID:\s*([A-Z0-9]+)/i', $message, $matches)) {
                $trxId = $matches[1];
            }
            // Parse Ref
            if (preg_match('/Ref:\s*([a-zA-Z0-9_-]+)/i', $message, $matches)) {
                $ref = $matches[1];
            }
        } elseif (str_contains($senderLower, 'rocket') || str_contains($senderLower, 'dbbl') || $senderLower === '16216') {
            $gateway = 'rocket';
            // Parse Amount
            if (preg_match('/Tk\.?\s*(\d+(?:\.\d+)?)/i', $message, $matches)) {
                $amount = (float)$matches[1];
            }
            // Parse Sender Mobile
            if (preg_match('/from\s*(\d{11,12})/i', $message, $matches)) {
                $senderMobile = $matches[1];
                if (strlen($senderMobile) === 12) {
                    $senderMobile = substr($senderMobile, 0, 11);
                }
            }
            // Parse TxnID
            if (preg_match('/(?:TxnID|TrxID):\s*([A-Z0-9]+)/i', $message, $matches)) {
                $trxId = $matches[1];
            }
            // Parse Ref
            if (preg_match('/Ref:\s*([a-zA-Z0-9_-]+)/i', $message, $matches)) {
                $ref = $matches[1];
            }
        } else {
            // General Fallback
            if (preg_match('/(?:BDT|Tk|TK)\.?\s*(\d+(?:\.\d+)?)/i', $message, $matches)) {
                $amount = (float)$matches[1];
            } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:BDT|Tk|TK)/i', $message, $matches)) {
                $amount = (float)$matches[1];
            }

            if (preg_match('/(?:TrxID|TxnID|TxID)\s*:?\s*([A-Z0-9]+)/i', $message, $matches)) {
                $trxId = $matches[1];
            }

            if (preg_match('/from\s*(\d{11})/i', $message, $matches)) {
                $senderMobile = $matches[1];
            }

            if (preg_match('/Ref\s*:?\s*([a-zA-Z0-9_-]+)/i', $message, $matches)) {
                $ref = $matches[1];
            }
        }

        return [
            'amount' => $amount,
            'trxId' => $trxId,
            'ref' => $ref,
            'senderMobile' => $senderMobile,
            'gateway' => $gateway
        ];
    }
}
