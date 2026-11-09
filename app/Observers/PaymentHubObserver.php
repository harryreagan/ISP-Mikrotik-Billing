<?php

namespace App\Observers;

use App\Models\PaymentSummary;
use App\Services\HubConnector\HubSyncService;

class PaymentHubObserver
{
    public function created(PaymentSummary $payment): void
    {
        $this->push($payment);
    }

    public function updated(PaymentSummary $payment): void
    {
        if ($payment->wasChanged('status') || $payment->wasChanged('amount')) {
            $this->push($payment);
        }
    }

    protected function push(PaymentSummary $payment): void
    {
        try {
            if (!config('hub.enabled') || !config('hub.key')) {
                return;
            }

            app(HubSyncService::class)->pushPayment([
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => 'KES',
                'channel' => strtolower($payment->gateway ?? 'mpesa_stk'),
                'status' => $payment->status === 'success' ? 'completed' : 'pending',
                'account_number' => $payment->account_number ?? $payment->username ?? null,
                'phone_number' => $payment->phone ?? null,
                'package_name' => $payment->package_name ?? null,
                'package_type' => $payment->connection_type ?? 'hotspot',
                'reference' => $payment->trx_id ?? $payment->transaction_id ?? null,
                'paid_at' => $payment->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            // Fail silently; outbox handles retry
        }
    }
}

