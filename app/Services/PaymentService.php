<?php

namespace App\Services;

use App\Events\PackagePurchased;
use App\Http\Controllers\ScheduledTasksController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\SMSController;
use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\PaymentSummary;
use App\Models\PPPSecrets;
use Carbon\Carbon;
use App\Jobs\ProvisionMikrotikUserJob;
use App\Models\UnmatchedPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Backward-compatible wrapper for fulfill().
     */
    public function processSuccessPayment(CustomersInfo $customer, float $amount, string $gateway, string $trxId, array $metadata = []): bool
    {
        return $this->fulfill($customer, $amount, $gateway, $trxId, $metadata);
    }

    /**
     * Centralized payment fulfillment for all Kenyan gateways (SMS, Daraja, PayHero, KopoKopo).
     *
     * 1. Dedupes transaction code (idempotent).
     * 2. Records collection summary & payment records.
     * 3. Calculates prorated or monthly renewal and auto_disable_date.
     * 4. Updates billing info and customer status to active.
     * 5. Dispatches queued job to enable PPPoE/Hotspot secret on MikroTik.
     * 6. Marks unmatched payment record as matched.
     * 7. Sends SMS payment receipt.
     */
    public function fulfill(CustomersInfo $customer, float $amount, string $gateway, string $trxId, array $metadata = []): bool
    {
        // ── 1. Idempotency Check ────────────────────────────────────────────────
        if (CollectionSummary::where('transaction_id', $trxId)->exists()) {
            Log::info("PaymentService: Duplicate transaction [{$trxId}] detected for customer [{$customer->customer_unique_id}]. Idempotent pass.");
            return true;
        }

        DB::beginTransaction();
        try {
            $billing = $customer->billing;
            if (! $billing) {
                throw new \Exception("Billing information not found for customer {$customer->customer_unique_id}");
            }

            // Fetch reseller of the voucher if it was voucher recharge, or customer's reseller
            $reseller = null;
            if ($gateway === 'voucher') {
                $voucher = \App\Models\Voucher::where('code', $trxId)->first();
                if ($voucher) {
                    $reseller = $voucher->reseller;
                }
            }
            if (! $reseller && $customer->reseller_id) {
                $reseller = $customer->reseller;
            }

            // If bill not yet generated for current month, generate it before calculating due and applying payment
            $billGenerated = ScheduledTasksController::generateBillForActivation($customer->customer_unique_id);
            if ($billGenerated) {
                $billing->refresh();
            }

            $useProrated = $reseller ? (bool)$reseller->getSetting('use_prorated_validity', true) : true;
            $allowPartialActivation = $reseller ? (bool)$reseller->getSetting('allow_partial_activation', false) : false;

            $isProratedApplied = false;
            $rent = (float) $billing->monthly_rent ?: 1.00;
            $currentDue = (float) $billing->due_amount;

            if ($useProrated && $amount < $rent && $amount > 0) {
                // Prorated daily validity calculation
                $proratedDays = (int) round(($amount / $rent) * 30);
                if ($proratedDays < 1) {
                    $proratedDays = 1;
                }

                $baseDate = today();
                if ($billing->auto_disable_date) {
                    $currentExpire = Carbon::parse($billing->auto_disable_date)->startOfDay();
                    if ($currentExpire->gt(today())) {
                        $baseDate = $currentExpire;
                    }
                }

                $expireDate = $baseDate->addDays($proratedDays)->format('Y-m-d');
                $newDue = 0.00;
                $advancePaid = 0.00;
                $isProratedApplied = true;
            } else {
                // Standard monthly billing calculations
                $newDue = max(0.00, $currentDue - $amount);
                $advancePaid = max(0.00, $amount - $currentDue);
                $expireDate = $billing->auto_disable_date;

                if ($newDue > 0) {
                    $extra_month = floor($newDue / $rent);
                    if ($extra_month < $billing->auto_disable_month) {
                        $expireDate = Carbon::parse($billing->auto_disable_date)
                            ->month(now()->month)->year(now()->year)
                            ->subMonths($extra_month)
                            ->format('Y-m-d');
                    } else {
                        $expireDate = Carbon::parse($billing->auto_disable_date)
                            ->month(now()->month)->year(now()->year)
                            ->subMonths($billing->auto_disable_month)
                            ->addMonths(1)
                            ->format('Y-m-d');
                    }
                } elseif ($advancePaid > 0) {
                    $extra_month = 1 + floor($advancePaid / $rent);
                    $expireDate = Carbon::parse($billing->auto_disable_date)
                        ->month(now()->month)->year(now()->year)
                        ->addMonths($extra_month)
                        ->format('Y-m-d');
                } else {
                    $expireDate = Carbon::parse($billing->auto_disable_date)
                        ->month(now()->month)->year(now()->year)
                        ->addMonths(1)
                        ->format('Y-m-d');
                }
            }

            // Generate next sequential invoice number
            $maxInvoice = CollectionSummary::withoutGlobalScopes()->max('invoice_no');
            $invoiceNo = $maxInvoice ? ($maxInvoice + 1) : 100001;

            $pkg = $customer->package_id ? \App\Models\PackageList::withoutGlobalScopes()->find($customer->package_id) : null;
            $svcType = strtolower((string) ($pkg?->type ?: ($customer->service_type ?: ($customer->pppUser?->service ?: 'pppoe'))));
            if (!in_array($svcType, ['hotspot', 'pppoe'], true)) {
                $svcType = 'pppoe';
            }

            // 2. Create Collection Record
            CollectionSummary::withoutGlobalScopes()->create([
                'tenant_id' => $customer->tenant_id ?: 1,
                'customer_collection_unique_id' => $customer->customer_unique_id,
                'subscriber_name' => $customer->customer_name ?: ($customer->pppUser?->username ?: $customer->mobile),
                'phone' => $customer->mobile,
                'mac_address' => $customer->pppUser?->caller_id,
                'router_id' => $customer->router_id,
                'collection_date' => Carbon::now(),
                'collection_amount' => $amount,
                'collected_by' => 'Online Payment ('.strtoupper($gateway).')',
                'payment_type' => $svcType === 'hotspot' ? 'hotspot' : 'online',
                'service_type' => $svcType,
                'package_id' => $customer->package_id,
                'payment_method' => $gateway,
                'transaction_id' => $trxId,
                'payment_status' => 'paid',
                'invoice_no' => $invoiceNo,
                'bill_month' => Carbon::now()->format('F Y'),
            ]);

            // 3. Update BillingInfo
            $billing->update([
                'paid_amount' => $billing->paid_amount + $amount,
                'paid_date' => Carbon::now(),
                'auto_disable_date' => $expireDate,
                'due_amount' => $newDue,
            ]);

            // 4. Create Monthly Payment Summary if not exists for the current month
            $summaryExists = PaymentSummary::where('customer_payment_unique_id', $customer->customer_unique_id)
                ->where('summary_date', Carbon::now()->firstOfMonth()->format('Y-m-d'))
                ->exists();

            if (! $summaryExists) {
                PaymentSummary::create([
                    'customer_payment_unique_id' => $customer->customer_unique_id,
                    'summary_date' => Carbon::now()->firstOfMonth()->format('Y-m-d'),
                    'monthly_rent' => $billing->monthly_rent,
                    'additional_charge' => $billing->additional_charge,
                    'vat' => $billing->vat,
                    'previous_due' => $billing->previous_due,
                    'advance' => $billing->advance,
                    'discount' => $billing->discount,
                ]);
            }

            // 5. Update customer status based on due and partial activation setting
            if ($newDue == 0 || $allowPartialActivation || $isProratedApplied) {
                $customer->status = 'active';
                $customer->disable_count = 0;
            } else {
                $customer->status = 'inactive';
            }
            $customer->save();

            // 6. Extend auto_disable_date if today is beyond it
            if (! $isProratedApplied && $billing->auto_disable_date) {
                $autoDisableDate = Carbon::parse($billing->auto_disable_date)->startOfDay();
                $autoDisableMonth = $billing->auto_disable_month;
                $disableDate = $autoDisableDate->copy()->addMonths($autoDisableMonth);

                if ($disableDate->lte(today())) {
                    while ($disableDate->lte(today())) {
                        $disableDate->addMonth();
                    }

                    $billing->auto_disable_date = $disableDate->copy()->subMonths($autoDisableMonth)->toDateString();
                    $billing->save();
                }
            }

            // 7. Enable customer on Mikrotik Router via Queued Job (Async)
            if ($customer->status === 'active' && $customer->ppp_user_id) {
                // Update local status immediately
                PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'active']);
                
                // Dispatch queued worker job to provision router without blocking HTTP response
                ProvisionMikrotikUserJob::dispatch($customer->id);

                // 7b. Immediately sync into FreeRADIUS (radcheck & radreply)
                try {
                    $pppSecret = PPPSecrets::find($customer->ppp_user_id);
                    $package = \App\Models\PackageList::find($customer->package_id);
                    $speed = $package?->mikrotik_rate_limit ?: $package?->speed;
                    $username = $pppSecret?->name ?: $customer->customer_unique_id;
                    $password = $pppSecret?->password ?: '123456';
                    $expiry = $billing->auto_disable_date;

                    \App\Services\RadiusService::upsertUser(
                        $username,
                        $password,
                        $speed,
                        $expiry,
                        1
                    );
                } catch (\Throwable $re) {
                    \Illuminate\Support\Facades\Log::warning('[PaymentService] RADIUS user activation warning: ' . $re->getMessage());
                }
            }

            // 8. Reconcile matching unmatched payment record if present
            UnmatchedPayment::where('transaction_code', $trxId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'matched',
                    'customer_id' => $customer->id,
                    'customer_unique_id' => $customer->customer_unique_id,
                    'assigned_at' => now(),
                ]);

            DB::commit();

            // Fire reseller commission event
            event(new PackagePurchased($customer, $amount));

            // 8. Send SMS confirmation
            try {
                $smsData = [
                    'recipient' => $customer->mobile,
                    'customer_name' => $customer->customer_name,
                    'collection_amount' => $amount,
                    'ip_or_user_name' => $customer->pppUser->username ?? '',
                    'due_amount' => $newDue,
                    'company_name' => siteUrlSettings('site_name') ?: config('app.name'),
                ];
                $smsResponse = app(SMSController::class)->paymentCollectionSMS($smsData);
                if ($smsResponse && ! $smsResponse->isSuccessful()) {
                    Log::warning("SMS Confirmation not delivered for {$customer->customer_unique_id}: ".$smsResponse->getMessage());
                }
            } catch (\Exception $smsEx) {
                Log::error("SMS Confirmation failed for {$customer->customer_unique_id}: ".$smsEx->getMessage());
            }

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PaymentService processing failed: '.$e->getMessage());
            throw $e;
        }
    }
}
