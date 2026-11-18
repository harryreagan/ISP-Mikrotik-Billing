<?php

namespace App\Console\Commands;

use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\MpesaStkTransaction;
use App\Models\Router;
use App\Models\UnmatchedPayment;
use App\Services\PaymentService;
use App\Services\RouterPendingActivationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'isp:reconcile-payments {--tenant= : Filter by specific tenant ID} {--days=30 : Number of past days to inspect} {--dry-run : Inspect without committing changes}';

    protected $description = 'Find gateway payments with no matching local record and attach them to the right tenant/router with an audit report.';

    public function handle(PaymentService $paymentService, RouterPendingActivationService $activationService): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $days = (int) ($this->option('days') ?: 30);
        $dryRun = (bool) $this->option('dry-run');

        $since = Carbon::now()->subDays($days);
        $this->info("Starting payment reconciliation for the past {$days} days" . ($tenantId ? " (Tenant #{$tenantId})" : " (All Tenants)") . ($dryRun ? " [DRY RUN]" : "") . "...");

        $reconciledUnmatched = 0;
        $reconciledStk = 0;
        $retriedHotspot = 0;

        // ── 1. RECONCILE UNMATCHED PAYMENTS ──
        $unmatchedQuery = UnmatchedPayment::where('status', 'pending')
            ->where('created_at', '>=', $since);
        $unmatchedPayments = $unmatchedQuery->get();

        foreach ($unmatchedPayments as $unmatched) {
            $code = strtoupper(trim($unmatched->transaction_code));
            $phone = $unmatched->phone;
            $amount = (float) $unmatched->amount;
            $ref = $unmatched->account_reference;

            // Check if matching customer exists in CustomersInfo
            $customer = null;
            if ($ref) {
                $customer = CustomersInfo::withoutGlobalScopes()
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->where('customer_unique_id', $ref)
                    ->first();
                if (!$customer) {
                    $customer = CustomersInfo::withoutGlobalScopes()
                        ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                        ->whereHas('pppUser', fn($q) => $q->withoutGlobalScopes()->where('username', $ref))
                        ->first();
                }
            }

            if (!$customer && $phone) {
                $norm = function_exists('normalizeKenyanPhone') ? normalizeKenyanPhone($phone) : $phone;
                $customer = CustomersInfo::withoutGlobalScopes()
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->where(function ($q) use ($norm) {
                        $q->where('mobile', $norm)
                          ->orWhere('mobile', '+' . $norm);
                    })
                    ->first();
            }

            if ($customer) {
                $this->line("-> Matched unmatched payment {$code} to subscriber {$customer->customer_unique_id} ({$customer->name})");
                if (!$dryRun) {
                    try {
                        $paymentService->fulfill($customer, $amount, $unmatched->source ?: 'mpesa', $code, [
                            'reconciled' => true,
                            'phone'      => $phone,
                        ]);
                        $unmatched->update([
                            'status'             => 'manual_matched',
                            'customer_id'        => $customer->id,
                            'customer_unique_id' => $customer->customer_unique_id,
                            'assigned_at'        => now(),
                            'admin_notes'        => 'Auto-reconciled by isp:reconcile-payments',
                        ]);
                        $reconciledUnmatched++;
                    } catch (\Throwable $e) {
                        $this->error("Failed to fulfill {$code}: " . $e->getMessage());
                    }
                }
            } else {
                // Check if this was a captive portal / hotspot transaction
                $stk = MpesaStkTransaction::withoutGlobalScopes()
                    ->where('mpesa_receipt_number', $code)
                    ->orWhere('checkout_request_id', $code)
                    ->first();

                if ($stk) {
                    $targetTenant = (int) ($stk->tenant_id ?: ($tenantId ?: 1));
                    $targetRouter = $stk->router_id ?: null;
                    $this->line("-> Matched unmatched payment {$code} to Hotspot STK transaction #{$stk->id}");

                    if (!$dryRun) {
                        // Create HotspotSale & CollectionSummary
                        if (!HotspotSale::withoutGlobalScopes()->where('voucher_code', $code)->exists()) {
                            HotspotSale::withoutGlobalScopes()->create([
                                'tenant_id'        => $targetTenant,
                                'router_id'        => $targetRouter,
                                'router_name'      => 'Hotspot',
                                'voucher_code'     => $code,
                                'profile'          => 'Hotspot Plan',
                                'username'         => $phone ?: $code,
                                'phone'            => $phone,
                                'amount'           => $amount,
                                'payment_method'   => 'mpesa_reconciled',
                                'status'           => 'pending_router',
                                'duration_minutes' => 60,
                                'started_at'       => now(),
                                'expires_at'       => now()->addHour(),
                                'note'             => 'Auto-reconciled Hotspot payment',
                                'sale_date'        => now()->toDateString(),
                            ]);
                        }

                        if (!CollectionSummary::withoutGlobalScopes()->where('transaction_id', $code)->exists()) {
                            $maxInv = CollectionSummary::withoutGlobalScopes()->max('invoice_no') ?: 100000;
                            CollectionSummary::withoutGlobalScopes()->create([
                                'tenant_id'                     => $targetTenant,
                                'customer_collection_unique_id' => $phone ?: $code,
                                'collection_date'               => now(),
                                'collection_amount'             => $amount,
                                'collected_by'                  => 'Reconciled M-Pesa',
                                'payment_type'                  => 'hotspot',
                                'payment_method'                => 'mpesa',
                                'transaction_id'                => $code,
                                'payment_status'                => 'paid',
                                'invoice_no'                    => $maxInv + 1,
                                'bill_month'                    => now()->format('F Y'),
                            ]);
                        }

                        $unmatched->update([
                            'status'      => 'manual_matched',
                            'assigned_at' => now(),
                            'admin_notes' => 'Auto-reconciled Hotspot payment by isp:reconcile-payments',
                        ]);
                        $reconciledUnmatched++;
                    }
                }
            }
        }

        // ── 2. RECONCILE COMPLETED STK TRANSACTIONS MISSING FINANCIAL RECORDS ──
        $stkTransactions = MpesaStkTransaction::withoutGlobalScopes()
            ->where('status', 'completed')
            ->whereNotNull('mpesa_receipt_number')
            ->where('created_at', '>=', $since)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->get();

        foreach ($stkTransactions as $stk) {
            $receipt = strtoupper(trim($stk->mpesa_receipt_number));
            $hasCollection = CollectionSummary::withoutGlobalScopes()->where('transaction_id', $receipt)->exists();
            $hasSale = HotspotSale::withoutGlobalScopes()->where('voucher_code', $receipt)->exists();

            if (!$hasCollection || !$hasSale) {
                $targetTenant = (int) ($stk->tenant_id ?: 1);
                $this->line("-> Found completed STK {$receipt} (KES {$stk->amount}) missing ledger record. Restoring...");

                if (!$dryRun) {
                    if (!$hasSale) {
                        HotspotSale::withoutGlobalScopes()->create([
                            'tenant_id'        => $targetTenant,
                            'router_id'        => $stk->router_id,
                            'router_name'      => 'Hotspot',
                            'voucher_code'     => $receipt,
                            'profile'          => 'Hotspot Plan',
                            'username'         => $stk->phone ?: $receipt,
                            'phone'            => $stk->phone,
                            'amount'           => (float) $stk->amount,
                            'payment_method'   => 'mpesa_stk',
                            'status'           => 'pending_router',
                            'duration_minutes' => 60,
                            'started_at'       => now(),
                            'expires_at'       => now()->addHour(),
                            'note'             => 'Restored from completed STK transaction',
                            'sale_date'        => Carbon::parse($stk->created_at)->toDateString(),
                        ]);
                    }

                    if (!$hasCollection) {
                        $maxInv = CollectionSummary::withoutGlobalScopes()->max('invoice_no') ?: 100000;
                        CollectionSummary::withoutGlobalScopes()->create([
                            'tenant_id'                     => $targetTenant,
                            'customer_collection_unique_id' => $stk->phone ?: $receipt,
                            'collection_date'               => $stk->created_at ?: now(),
                            'collection_amount'             => (float) $stk->amount,
                            'collected_by'                  => 'M-Pesa STK Restored',
                            'payment_type'                  => 'hotspot',
                            'payment_method'                => 'mpesa',
                            'transaction_id'                => $receipt,
                            'payment_status'                => 'paid',
                            'invoice_no'                    => $maxInv + 1,
                            'bill_month'                    => Carbon::parse($stk->created_at)->format('F Y'),
                        ]);
                    }
                    $reconciledStk++;
                }
            }
        }

        // ── 3. RETRY ROUTER PENDING ACTIVATIONS ──
        if (!$dryRun) {
            $retriedHotspot = $activationService->processAllPending();
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Reconciled Unmatched Payments', $reconciledUnmatched],
                ['Restored Missing STK Transactions', $reconciledStk],
                ['Pushed Pending Activations to Routers', $retriedHotspot],
                ['Remaining Unmatched Payments', UnmatchedPayment::where('status', 'pending')->count()],
            ]
        );

        Log::info("[ReconcilePayments] Completed. Unmatched: {$reconciledUnmatched}, Restored STK: {$reconciledStk}, Router Activations: {$retriedHotspot}");

        return Command::SUCCESS;
    }
}
