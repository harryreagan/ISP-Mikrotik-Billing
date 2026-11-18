<?php

namespace App\Observers;

use App\Models\CollectionSummary;
use App\Models\HotspotSale;
use App\Models\MpesaStkTransaction;
use App\Models\PaymentSummary;
use App\Services\HubConnector\HubSyncService;
use Illuminate\Database\Eloquent\Model;

class PaymentHubObserver
{
    public function created(Model $model): void
    {
        $this->push($model);
    }

    public function updated(Model $model): void
    {
        if ($model instanceof MpesaStkTransaction) {
            if ($model->wasChanged('status') && $model->status === 'completed') {
                $this->push($model);
            }
        } elseif ($model->wasChanged('status') || $model->wasChanged('amount') || $model->wasChanged('collection_amount')) {
            $this->push($model);
        }
    }

    protected function push(Model $model): void
    {
        try {
            if (!config('hub.enabled') || !config('hub.key')) {
                return;
            }

            $payload = null;

            if ($model instanceof MpesaStkTransaction) {
                if ($model->status !== 'completed') {
                    return;
                }
                $payload = [
                    'id' => $model->id,
                    'amount' => (float) $model->amount,
                    'currency' => 'KES',
                    'channel' => strtolower($model->gateway ?? 'mpesa_stk'),
                    'status' => 'completed',
                    'account_number' => $model->reference ?: $model->customer_unique_id,
                    'phone_number' => $model->phone,
                    'package_type' => 'hotspot',
                    'reference' => $model->mpesa_receipt_number ?: $model->checkout_request_id,
                    'paid_at' => $model->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            } elseif ($model instanceof CollectionSummary) {
                $payload = [
                    'id' => 'col_' . $model->id,
                    'amount' => (float) $model->collection_amount,
                    'currency' => 'KES',
                    'channel' => strtolower($model->payment_method ?? 'cash'),
                    'status' => 'completed',
                    'account_number' => $model->customer_collection_unique_id,
                    'package_type' => 'pppoe',
                    'reference' => $model->transaction_id ?: ('INV-' . $model->invoice_no),
                    'paid_at' => $model->collection_date?->toIso8601String() ?? now()->toIso8601String(),
                ];
            } elseif ($model instanceof HotspotSale) {
                $payload = [
                    'id' => 'hs_' . $model->id,
                    'amount' => (float) $model->amount,
                    'currency' => 'KES',
                    'channel' => strtolower($model->payment_method ?? 'cash'),
                    'status' => 'completed',
                    'account_number' => $model->username ?: $model->voucher_code,
                    'package_type' => 'hotspot',
                    'reference' => $model->voucher_code ?: ('HS-' . $model->id),
                    'paid_at' => $model->sale_date?->toIso8601String() ?? now()->toIso8601String(),
                ];
            } elseif ($model instanceof PaymentSummary) {
                if (empty($model->amount)) {
                    return;
                }
                $payload = [
                    'id' => $model->id,
                    'amount' => (float) $model->amount,
                    'currency' => 'KES',
                    'channel' => strtolower($model->gateway ?? 'mpesa_stk'),
                    'status' => ($model->status ?? 'success') === 'success' ? 'completed' : 'pending',
                    'account_number' => $model->account_number ?? $model->username ?? null,
                    'phone_number' => $model->phone ?? null,
                    'package_name' => $model->package_name ?? null,
                    'package_type' => $model->connection_type ?? 'hotspot',
                    'reference' => $model->trx_id ?? $model->transaction_id ?? null,
                    'paid_at' => $model->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            }

            if ($payload) {
                app(HubSyncService::class)->pushPayment($payload);
            }
        } catch (\Throwable $e) {
            // Fail silently; outbox handles retry
        }
    }
}
