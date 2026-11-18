<?php

namespace App\Http\Controllers;

use App\Models\TenantSubscriptionPayment;
use App\Services\TenantSubscriptionService;
use Illuminate\Http\JsonResponse;
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
     * Check STK push payment status
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
}
