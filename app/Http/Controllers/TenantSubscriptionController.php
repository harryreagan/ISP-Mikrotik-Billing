<?php

namespace App\Http\Controllers;

use App\Models\TenantSubscriptionPayment;
use App\Services\TenantSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TenantSubscriptionController extends Controller
{
    /**
     * Show ISP subscription dashboard & plans
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $subscription = TenantSubscriptionService::getTenantSubscription($tenantId);
        $plans = TenantSubscriptionService::getPlans();
        
        $payments = TenantSubscriptionPayment::forTenant($tenantId)
            ->latest('id')
            ->take(15)
            ->get();

        $selectedPlan = $request->query('plan', $subscription['plan_slug'] ?: 'starter');

        return view('subscription.index', [
            'subscription' => $subscription,
            'plans' => $plans,
            'payments' => $payments,
            'selectedPlan' => $selectedPlan,
            'user' => $user,
        ]);
    }

    /**
     * Initiate M-Pesa STK Push for subscription
     */
    public function initiateMpesa(Request $request): JsonResponse
    {
        $request->validate([
            'plan_slug' => 'required|string',
            'phone' => 'required|string|min:9',
        ]);

        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $result = TenantSubscriptionService::initiateMpesaStk($tenantId, $request->plan_slug, $request->phone);

        return response()->json($result, ($result['status'] === 'success') ? 200 : 422);
    }

    /**
     * Initiate Paystack Checkout for subscription
     */
    public function initiatePaystack(Request $request): JsonResponse
    {
        $request->validate([
            'plan_slug' => 'required|string',
            'email' => 'nullable|email',
        ]);

        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);
        $email = $request->input('email') ?: ($user->email ?? 'admin@hsmart.co.ke');

        $result = TenantSubscriptionService::initiatePaystack($tenantId, $request->plan_slug, $email);

        return response()->json($result, ($result['status'] === 'success') ? 200 : 422);
    }

    /**
     * Verify Paystack payment redirect
     */
    public function verifyPaystack(Request $request, string $reference): RedirectResponse
    {
        $result = TenantSubscriptionService::verifyPaystack($reference);

        if ($result['status'] === 'completed') {
            return redirect()->route('subscription.index')->with('success', 'Subscription activated successfully! Thank you for renewing your HSmart ISP license.');
        }

        return redirect()->route('subscription.index')->with('error', $result['message'] ?? 'Payment verification was unsuccessful. Please check with your bank.');
    }

    /**
     * Check payment status (used for AJAX polling on M-Pesa & Paystack)
     */
    public function checkStatus(Request $request, string $reference): JsonResponse
    {
        $result = TenantSubscriptionService::checkPaymentStatus($reference);
        return response()->json($result);
    }

    /**
     * PayHero Webhook Callback for subscription payments
     */
    public function webhookCallback(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info("Subscription Webhook Callback received:", ['payload' => $payload]);

        $externalRef = $payload['external_reference'] ?? $payload['reference'] ?? null;
        $receipt = $payload['MpesaReceiptNumber'] ?? $payload['receipt'] ?? $payload['mpesa_receipt'] ?? null;
        $status = strtoupper($payload['status'] ?? $payload['Status'] ?? '');
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;

        if (!$externalRef) {
            return response()->json(['status' => 'ignored', 'message' => 'Missing reference'], 400);
        }

        if (in_array($status, ['SUCCESS', 'COMPLETED', 'CONFIRMED', 'PAID'])) {
            $fulfilled = TenantSubscriptionService::fulfillPayment(
                $externalRef,
                $receipt ?: ($externalRef ?: 'MPESA-' . strtoupper(\Illuminate\Support\Str::random(8))),
                $amount,
                $payload
            );

            return response()->json(['status' => 'success', 'fulfilled' => $fulfilled]);
        }

        // Handle failure
        if (in_array($status, ['FAILED', 'CANCELLED', 'REJECTED'])) {
            TenantSubscriptionPayment::where('reference', $externalRef)
                ->orWhere('external_reference', $externalRef)
                ->update(['status' => 'failed']);
        }

        return response()->json(['status' => 'acknowledged']);
    }

    /**
     * Paystack Webhook Callback
     */
    public function paystackWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? '';

        if ($event === 'charge.success') {
            $data = $payload['data'] ?? [];
            $reference = $data['reference'] ?? null;
            if ($reference) {
                TenantSubscriptionService::verifyPaystack($reference);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
