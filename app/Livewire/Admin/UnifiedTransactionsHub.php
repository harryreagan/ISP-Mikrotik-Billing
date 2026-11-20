<?php

namespace App\Livewire\Admin;

use App\Http\Controllers\CustomersController;
use App\Http\Controllers\SMSController;
use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\UnmatchedPayment;
use App\Models\User;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class UnifiedTransactionsHub extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Active Navigation Tab: 'transactions', 'invoices', 'unmatched', 'collect'
    public string $activeTab = 'transactions';

    // ── Tab 1: All Transactions / Statements Ledger ──
    public string $txSearch = '';
    public string $txTypeFilter = 'all'; // 'all', 'pppoe', 'hotspot', 'cash'
    public string $txDateFilter = 'this_month'; // 'today', 'yesterday', 'this_week', 'this_month', 'all'
    public ?int $selectedReceiptId = null;
    public string $selectedReceiptType = 'pppoe'; // 'pppoe', 'hotspot'
    public $selectedReceiptData = null;

    // ── Tab 2: Invoices & Statements ──
    public string $invoiceCustomerSearch = '';
    public array $invoiceCustomers = [];
    public int $invoiceHighlightedIndex = 0;
    public ?CustomersInfo $invoiceCustomerData = null;
    public $invoiceCollectionSummaries = [];

    // ── Tab 3: Unmatched Payments & Instant Claim ──
    public string $unmatchedSearch = '';
    public string $unmatchedStatusFilter = 'pending';
    public string $unmatchedSourceFilter = 'all';
    public ?int $unmatchedSelectedId = null;
    public string $claimCustomerSearch = '';
    public ?int $claimSelectedCustomerId = null;
    public string $claimAdminNotes = '';
    public string $statusMessage = '';
    public string $errorMessage = '';

    // ── Tab 4: Record / Adjust Collection (Manual Cash / Offline) ──
    public string $collectCustomerSearch = '';
    public array $collectCustomers = [];
    public int $collectHighlightedIndex = 0;
    public ?CustomersInfo $collectCustomerData = null;
    public $collectPaidAmount = '';
    public $collectTotalAmount = 0;
    public $collectDueAmount = 0;
    public $collectAdvancePaid = 0;
    public string $collectExpireDate = '';
    public $collectRecentSummaries = [];

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $isAdmin = in_array(strtolower($user->role ?? ''), ['admin', 'superadmin', 'super_admin'])
            || (is_object($user) && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Super Admin']))
            || (is_object($user) && method_exists($user, 'hasRole') && $user->hasRole('Reseller'));

        if (! $isAdmin && ! hasAccess(['Super Admin'], ['payment-collection', 'payment-collection-edit', 'payment-collection-invoice', 'amount-collection'])) {
            abort(403, 'Unauthorized action.');
        }

        // Determine initial active tab based on query param or route
        $requestedTab = request()->query('tab');
        $routeName = request()->route()?->getName();

        if ($requestedTab && in_array($requestedTab, ['transactions', 'invoices', 'unmatched', 'collect'])) {
            $this->activeTab = $requestedTab;
        } elseif ($routeName === 'collection-edit') {
            $this->activeTab = 'collect';
        } elseif ($routeName === 'payment-invoice') {
            $this->activeTab = 'invoices';
        } elseif ($routeName === 'admin.unmatched-payments') {
            $this->activeTab = 'unmatched';
        } elseif ($routeName === 'payment-collection') {
            $this->activeTab = request()->has('customer') ? 'collect' : 'transactions';
        } else {
            $this->activeTab = 'transactions';
        }

        if (request()->has('customer')) {
            $this->selectCollectCustomer(request()->query('customer'));
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['transactions', 'invoices', 'unmatched', 'collect'])) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    // ── Scope helper for tenant & reseller isolation ──
    private function getTenantId(): int
    {
        $user = auth()->user();
        return (int) ($user?->tenant_id ?: (session('tenant_id') ?: (session('impersonated_tenant_id') ?: 1)));
    }

    private function customerScope()
    {
        $user = auth()->user();
        $tenantId = $this->getTenantId();
        $query = CustomersInfo::withoutGlobalScopes()->where('tenant_id', $tenantId);

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('Reseller') && $user->reseller) {
            $query->where('reseller_id', $user->reseller->id);
        }

        return $query;
    }

    // ── Metric calculations (Single source of truth: CollectionSummary.service_type per tenant) ──
    public function getMetricsProperty(): array
    {
        $tenantId = $this->getTenantId();
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $base = CollectionSummary::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $pppoeToday = (float) (clone $base)
            ->where('service_type', 'pppoe')
            ->whereDate('collection_date', $today)
            ->sum('collection_amount');

        $hotspotToday = (float) (clone $base)
            ->where('service_type', 'hotspot')
            ->whereDate('collection_date', $today)
            ->sum('collection_amount');

        $pppoeMonth = (float) (clone $base)
            ->where('service_type', 'pppoe')
            ->whereBetween('collection_date', [$startOfMonth, $endOfMonth])
            ->sum('collection_amount');

        $hotspotMonth = (float) (clone $base)
            ->where('service_type', 'hotspot')
            ->whereBetween('collection_date', [$startOfMonth, $endOfMonth])
            ->sum('collection_amount');

        // Pending Unmatched (strictly tenant scoped)
        $pendingUnmatched = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('unmatched_payments')) {
                $pendingUnmatched = (int) UnmatchedPayment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->count();
            }
        } catch (\Throwable $e) {}

        return [
            'today_total'       => $pppoeToday + $hotspotToday,
            'month_total'       => $pppoeMonth + $hotspotMonth,
            'pppoe_month'       => $pppoeMonth,
            'hotspot_month'     => $hotspotMonth,
            'pending_unmatched' => $pendingUnmatched,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // TAB 1: ALL TRANSACTIONS / MASTER STATEMENT LEDGER
    // ══════════════════════════════════════════════════════════════════════
    public function updatingTxSearch(): void { $this->resetPage(); }
    public function updatingTxTypeFilter(): void { $this->resetPage(); }
    public function updatingTxDateFilter(): void { $this->resetPage(); }

    public function openReceiptModal(int $id, string $type): void
    {
        $tenantId = $this->getTenantId();
        $this->selectedReceiptId = $id;
        $this->selectedReceiptType = $type;

        $this->selectedReceiptData = CollectionSummary::withoutGlobalScopes()
            ->with(['customer', 'customer.customerAddress', 'customer.pppUser', 'customer.billing', 'package', 'router'])
            ->where('tenant_id', $tenantId)
            ->find($id);
    }

    public function closeReceiptModal(): void
    {
        $this->selectedReceiptId = null;
        $this->selectedReceiptData = null;
    }

    // ══════════════════════════════════════════════════════════════════════
    // TAB 2: INVOICES & STATEMENTS
    // ══════════════════════════════════════════════════════════════════════
    public function updatedInvoiceCustomerSearch(): void
    {
        if (strlen(trim($this->invoiceCustomerSearch)) >= 2) {
            $this->invoiceCustomers = $this->customerScope()
                ->search($this->invoiceCustomerSearch)
                ->leftJoin('p_p_p_secrets', 'p_p_p_secrets.id', '=', 'customers_infos.ppp_user_id')
                ->with('customerAddress')
                ->select('customers_infos.id', 'customers_infos.customer_unique_id', 'customers_infos.customer_name', 'customers_infos.email', 'customers_infos.mobile', 'customers_infos.status', 'p_p_p_secrets.username as username')
                ->take(10)
                ->get()
                ->toArray();
        } else {
            $this->invoiceCustomers = [];
        }
        $this->invoiceHighlightedIndex = 0;
    }

    public function selectInvoiceCustomer($encryptedId): void
    {
        try {
            $customerId = decrypt($encryptedId);
        } catch (\Throwable $e) {
            $customerId = $encryptedId;
        }

        $this->invoiceCustomerSearch = '';
        $this->invoiceCustomers = [];
        $this->invoiceCustomerData = $this->customerScope()
            ->where('customer_unique_id', $customerId)
            ->with(['customerAddress', 'billing', 'official', 'pppUser'])
            ->first();

        if ($this->invoiceCustomerData) {
            $this->invoiceCollectionSummaries = CollectionSummary::where('customer_collection_unique_id', $customerId)
                ->orderBy('collection_date', 'desc')
                ->take(10)
                ->get();
        }
    }

    public function printInvoice(): void
    {
        $this->dispatch('triggerPrint');
    }

    // ══════════════════════════════════════════════════════════════════════
    // TAB 3: UNMATCHED PAYMENTS & INSTANT CLAIM
    // ══════════════════════════════════════════════════════════════════════
    public function updatingUnmatchedSearch(): void { $this->resetPage(); }
    public function updatingUnmatchedStatusFilter(): void { $this->resetPage(); }
    public function updatingUnmatchedSourceFilter(): void { $this->resetPage(); }

    public function openAssignModal(int $paymentId): void
    {
        $this->resetErrorBag();
        $this->statusMessage = '';
        $this->errorMessage = '';
        $this->unmatchedSelectedId = $paymentId;
        $this->claimCustomerSearch = '';
        $this->claimSelectedCustomerId = null;
        $this->claimAdminNotes = '';

        $payment = UnmatchedPayment::find($paymentId);
        if ($payment) {
            $this->claimCustomerSearch = $payment->account_reference ?: ($payment->phone ?: '');
        }
    }

    public function closeAssignModal(): void
    {
        $this->unmatchedSelectedId = null;
        $this->claimSelectedCustomerId = null;
        $this->claimCustomerSearch = '';
        $this->claimAdminNotes = '';
    }

    public function selectClaimCustomer(int $customerId): void
    {
        $this->claimSelectedCustomerId = $customerId;
    }

    public function assignPayment(): void
    {
        if (! $this->unmatchedSelectedId || ! $this->claimSelectedCustomerId) {
            $this->errorMessage = 'Please select a customer to assign this payment to.';
            return;
        }

        $payment = UnmatchedPayment::find($this->unmatchedSelectedId);
        $customer = CustomersInfo::find($this->claimSelectedCustomerId);

        if (! $payment || ! $customer) {
            $this->errorMessage = 'Selected payment or customer no longer exists.';
            return;
        }

        if ($payment->status === 'matched' || $payment->status === 'manual_matched') {
            $this->errorMessage = 'This payment has already been matched and processed.';
            return;
        }

        try {
            $paymentService = app(PaymentService::class);
            $paymentService->fulfill(
                $customer,
                (float) $payment->amount,
                $payment->source,
                $payment->transaction_code,
                [
                    'phone'             => $payment->phone,
                    'sender_name'       => $payment->sender_name,
                    'account_reference' => $payment->account_reference,
                    'raw_message'       => $payment->raw_message,
                    'assigned_manually' => true,
                    'assigned_by'       => auth()->id(),
                ]
            );

            $payment->update([
                'status'             => 'manual_matched',
                'customer_id'        => $customer->id,
                'customer_unique_id' => $customer->customer_unique_id,
                'assigned_by'        => auth()->id(),
                'assigned_at'        => now(),
                'admin_notes'        => $this->claimAdminNotes ?: 'Manually assigned by admin.',
            ]);

            $this->statusMessage = "Transaction [{$payment->transaction_code}] successfully assigned to {$customer->customer_name} ({$customer->customer_unique_id})!";
            $this->closeAssignModal();
        } catch (\Throwable $e) {
            Log::error('Manual payment assignment error: ' . $e->getMessage());
            $this->errorMessage = 'Failed to fulfill payment: ' . $e->getMessage();
        }
    }

    public function ignorePayment(int $paymentId): void
    {
        $payment = UnmatchedPayment::find($paymentId);
        if ($payment) {
            $payment->update([
                'status'      => 'ignored',
                'admin_notes' => 'Marked as ignored by admin on ' . now()->toDateTimeString(),
            ]);
            $this->statusMessage = "Payment [{$payment->transaction_code}] marked as ignored.";
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // TAB 4: RECORD & ADJUST COLLECTION (MANUAL CASH / OFFLINE)
    // ══════════════════════════════════════════════════════════════════════
    public function updatedCollectCustomerSearch(): void
    {
        if (strlen(trim($this->collectCustomerSearch)) >= 2) {
            $this->collectCustomers = $this->customerScope()
                ->search($this->collectCustomerSearch)
                ->leftJoin('p_p_p_secrets', 'p_p_p_secrets.id', '=', 'customers_infos.ppp_user_id')
                ->with('customerAddress')
                ->select('customers_infos.id', 'customers_infos.customer_unique_id', 'customers_infos.customer_name', 'customers_infos.email', 'customers_infos.mobile', 'customers_infos.status', 'p_p_p_secrets.username as username')
                ->take(10)
                ->get()
                ->toArray();
        } else {
            $this->collectCustomers = [];
        }
        $this->collectHighlightedIndex = 0;
    }

    public function selectCollectCustomer($encryptedId): void
    {
        try {
            $customerId = decrypt($encryptedId);
        } catch (\Throwable $e) {
            $customerId = $encryptedId;
        }

        $this->collectCustomerSearch = '';
        $this->collectCustomers = [];
        $this->collectCustomerData = $this->customerScope()
            ->where('customer_unique_id', $customerId)
            ->with(['customerAddress', 'billing', 'official', 'pppUser'])
            ->first();

        if ($this->collectCustomerData) {
            $this->collectPaidAmount = '';
            $this->collectTotalAmount = (float) ($this->collectCustomerData->billing->due_amount ?? 0);
            $this->collectDueAmount = $this->collectTotalAmount;
            $this->collectAdvancePaid = 0;
            $this->collectExpireDate = '';

            $this->collectRecentSummaries = CollectionSummary::where('customer_collection_unique_id', $customerId)
                ->orderBy('collection_date', 'desc')
                ->take(10)
                ->get();
        }
    }

    public function calculatePayment(): void
    {
        if (! $this->collectCustomerData || ! $this->collectCustomerData->billing) {
            return;
        }

        $billing = $this->collectCustomerData->billing;
        $monthlyRent = (float) ($billing->monthly_rent > 0 ? $billing->monthly_rent : 1);
        $paid = (float) $this->collectPaidAmount;

        $this->collectDueAmount = (float) $billing->due_amount - $paid;

        if ($paid > 0) {
            $this->collectAdvancePaid = $paid - (float) $this->collectTotalAmount;

            if ($this->collectDueAmount > 0) {
                $extraMonths = floor($this->collectDueAmount / $monthlyRent);
                $autoDisableMonth = (int) ($billing->auto_disable_month ?? 1);

                if ($extraMonths < $autoDisableMonth) {
                    $this->collectExpireDate = Carbon::parse($billing->auto_disable_date ?: now())
                        ->month(now()->month)->year(now()->year)
                        ->subMonths((int) $extraMonths)
                        ->format('Y-m-d');
                } else {
                    $this->collectExpireDate = Carbon::parse($billing->auto_disable_date ?: now())
                        ->month(now()->month)->year(now()->year)
                        ->subMonths($autoDisableMonth)
                        ->addMonths(1)
                        ->format('Y-m-d');
                }
            } elseif ($this->collectAdvancePaid > 0) {
                $extraMonths = 1 + floor($this->collectAdvancePaid / $monthlyRent);
                $this->collectExpireDate = Carbon::parse($billing->auto_disable_date ?: now())
                    ->month(now()->month)->year(now()->year)
                    ->addMonths((int) $extraMonths)
                    ->format('Y-m-d');
            } else {
                $this->collectExpireDate = Carbon::parse($billing->auto_disable_date ?: now())
                    ->month(now()->month)->year(now()->year)
                    ->addMonths(1)
                    ->format('Y-m-d');
            }
        } else {
            $this->collectExpireDate = '';
        }
    }

    public function saveCollection(): void
    {
        if (! $this->collectCustomerData || ! $this->collectCustomerData->billing) {
            flash()->error('No customer selected.');
            return;
        }

        $paid = (float) $this->collectPaidAmount;
        if ($paid <= 0) {
            flash()->error('Please enter a valid collection amount.');
            return;
        }

        DB::beginTransaction();
        try {
            $tenantId = $this->getTenantId();
            $uniqueId = $this->collectCustomerData->customer_unique_id;
            $pkg = $this->collectCustomerData->package_id
                ? \App\Models\PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($this->collectCustomerData->package_id)
                : null;
            $serviceType = strtolower((string) ($pkg?->type ?: ($this->collectCustomerData->service_type ?: ($this->collectCustomerData->pppUser?->service ?: 'pppoe'))));
            if (!in_array($serviceType, ['hotspot', 'pppoe'], true)) {
                $serviceType = 'pppoe';
            }

            CollectionSummary::withoutGlobalScopes()->create([
                'tenant_id'                     => $tenantId,
                'customer_collection_unique_id' => $uniqueId,
                'subscriber_name'               => $this->collectCustomerData->customer_name ?: ($this->collectCustomerData->pppUser?->username ?: $this->collectCustomerData->mobile),
                'phone'                         => $this->collectCustomerData->mobile,
                'mac_address'                   => $this->collectCustomerData->pppUser?->caller_id,
                'router_id'                     => $this->collectCustomerData->router_id,
                'collection_date'               => Carbon::now(),
                'collection_amount'             => $paid,
                'collected_by'                  => auth()->user()?->email ?? 'admin',
                'payment_type'                  => $serviceType === 'hotspot' ? 'hotspot' : 'Cash',
                'service_type'                  => $serviceType,
                'package_id'                    => $this->collectCustomerData->package_id,
                'payment_method'                => 'Cash',
                'payment_status'                => 'paid',
                'invoice_no'                    => time() . rand(10, 99),
                'bill_month'                    => Carbon::now()->format('F Y'),
            ]);

            $billing = $this->collectCustomerData->billing;
            $billing->update([
                'paid_amount' => (float) $billing->paid_amount + $paid,
                'paid_date' => Carbon::now(),
                'auto_disable_date' => $this->collectExpireDate ?: $billing->auto_disable_date,
                'due_amount' => $this->collectDueAmount,
            ]);

            // Re-activate customer if currently inactive
            if ($this->collectCustomerData->status !== 'active') {
                $this->collectCustomerData->update(['status' => 'active']);
                try {
                    $customerEnable = new CustomersController;
                    $customerEnable->customerEnable(encrypt($uniqueId));
                } catch (\Throwable $e) {}
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            flash()->error('Payment submission failed: ' . $th->getMessage());
            return;
        }

        try {
            event(new \App\Events\PackagePurchased($this->collectCustomerData, $paid));

            $smsData = [
                'recipient' => $this->collectCustomerData->mobile,
                'customer_name' => $this->collectCustomerData->customer_name,
                'collection_amount' => $paid,
                'ip_or_user_name' => $this->collectCustomerData->pppUser->username ?? '',
                'due_amount' => $this->collectDueAmount,
                'company_name' => siteUrlSettings('site_name'),
            ];
            app(SMSController::class)->paymentCollectionSMS($smsData);
        } catch (\Throwable $e) {}

        flash()->success('Payment collected and recorded successfully.');
        $this->selectCollectCustomer($this->collectCustomerData->customer_unique_id);
    }

    public function deleteCollectionRecord(int $id): void
    {
        $tenantId = $this->getTenantId();
        $collection = CollectionSummary::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($id);
        if (! $collection) {
            flash()->error('Collection record not found.');
            return;
        }

        $collectedAmount = (float) $collection->collection_amount;
        $customerId = $collection->customer_collection_unique_id;

        $billing = BillingInfo::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('customer_bill_unique_id', $customerId)->first();
        if ($billing) {
            $due = (float) $billing->due_amount + $collectedAmount;
            $paid = (float) $billing->paid_amount - $collectedAmount;
            $billing->update([
                'paid_amount' => max(0, $paid),
                'due_amount' => $due,
            ]);
        }

        $collection->delete();
        flash()->success('Collection record reversed successfully.');

        if ($this->collectCustomerData && $this->collectCustomerData->customer_unique_id === $customerId) {
            $this->selectCollectCustomer($customerId);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // RENDER
    // ══════════════════════════════════════════════════════════════════════
    public function reconcilePayments(): void
    {
        try {
            $user = auth()->user();
            $tenantId = $user?->tenant_id ?: null;
            \Illuminate\Support\Facades\Artisan::call('isp:reconcile-payments', [
                '--tenant' => $tenantId,
            ]);
            $this->statusMessage = 'Reconciliation complete! M-Pesa gateway records matched and queued router activations processed.';
            $this->errorMessage = '';
            Log::info('[UnifiedTransactionsHub] Manual reconcile triggered by user ' . ($user?->id ?? 'unknown'));
        } catch (\Throwable $e) {
            $this->errorMessage = 'Reconciliation error: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $tenantId = $this->getTenantId();

        // ── Data for Tab 1: Ledger ──
        $txRecords = collect();
        if ($this->activeTab === 'transactions') {
            // Determine date bounds
            $dateStart = null;
            $dateEnd = null;
            if ($this->txDateFilter === 'today') {
                $dateStart = Carbon::today()->startOfDay();
                $dateEnd = Carbon::today()->endOfDay();
            } elseif ($this->txDateFilter === 'yesterday') {
                $dateStart = Carbon::yesterday()->startOfDay();
                $dateEnd = Carbon::yesterday()->endOfDay();
            } elseif ($this->txDateFilter === 'this_week') {
                $dateStart = Carbon::now()->startOfWeek()->startOfDay();
                $dateEnd = Carbon::now()->endOfWeek()->endOfDay();
            } elseif ($this->txDateFilter === 'this_month') {
                $dateStart = Carbon::now()->startOfMonth()->startOfDay();
                $dateEnd = Carbon::now()->endOfMonth()->endOfDay();
            }

            $collectionsQuery = CollectionSummary::withoutGlobalScopes()
                ->with(['customer', 'customer.pppUser', 'package', 'router'])
                ->where('tenant_id', $tenantId)
                ->orderBy('collection_date', 'desc');

            if ($dateStart && $dateEnd) {
                $collectionsQuery->whereBetween('collection_date', [$dateStart, $dateEnd]);
            }
            if (! empty($this->txSearch)) {
                $term = trim($this->txSearch);
                $collectionsQuery->where(function ($q) use ($term) {
                    $q->where('transaction_id', 'like', "%{$term}%")
                        ->orWhere('invoice_no', 'like', "%{$term}%")
                        ->orWhere('customer_collection_unique_id', 'like', "%{$term}%")
                        ->orWhere('subscriber_name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('mac_address', 'like', "%{$term}%")
                        ->orWhereHas('customer', function ($qc) use ($term) {
                            $qc->where('customer_name', 'like', "%{$term}%")
                                ->orWhere('mobile', 'like', "%{$term}%")
                                ->orWhereHas('pppUser', fn($qp) => $qp->where('username', 'like', "%{$term}%"));
                        });
                });
            }
            if ($this->txTypeFilter === 'hotspot') {
                $collectionsQuery->where('service_type', 'hotspot');
            } elseif ($this->txTypeFilter === 'pppoe') {
                $collectionsQuery->where('service_type', 'pppoe');
            } elseif ($this->txTypeFilter === 'cash') {
                $collectionsQuery->where(function ($q) {
                    $q->where('payment_method', 'cash')
                        ->orWhere('payment_type', 'cash')
                        ->orWhere('collected_by', 'like', '%@%');
                });
            }

            $txRecords = $collectionsQuery->paginate(15);
        }

        // ── Data for Tab 3: Unmatched ──
        $unmatchedPayments = collect();
        $claimCustomerResults = collect();
        if ($this->activeTab === 'unmatched') {
            $uQuery = UnmatchedPayment::withoutGlobalScopes()
                ->with(['customer', 'assignedByUser'])
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc');
            if ($this->unmatchedStatusFilter !== 'all') {
                $uQuery->where('status', $this->unmatchedStatusFilter);
            }
            if ($this->unmatchedSourceFilter !== 'all') {
                $uQuery->where('source', $this->unmatchedSourceFilter);
            }
            if (! empty($this->unmatchedSearch)) {
                $term = $this->unmatchedSearch;
                $uQuery->where(function ($q) use ($term) {
                    $q->where('transaction_code', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('sender_name', 'like', "%{$term}%")
                        ->orWhere('account_reference', 'like', "%{$term}%")
                        ->orWhere('customer_unique_id', 'like', "%{$term}%");
                });
            }
            $unmatchedPayments = $uQuery->paginate(15);

            if ($this->unmatchedSelectedId && strlen(trim($this->claimCustomerSearch)) >= 2) {
                $claimCustomerResults = $this->customerScope()
                    ->with('billing', 'pppUser')
                    ->where(function ($q) {
                        $q->where('customer_name', 'like', '%' . $this->claimCustomerSearch . '%')
                            ->orWhere('customer_unique_id', 'like', '%' . $this->claimCustomerSearch . '%')
                            ->orWhere('mobile', 'like', '%' . $this->claimCustomerSearch . '%')
                            ->orWhereHas('pppUser', fn($qp) => $qp->where('username', 'like', '%' . $this->claimCustomerSearch . '%'));
                    })
                    ->take(8)
                    ->get();
            }
        }

        $selectedClaimPayment = $this->unmatchedSelectedId
            ? UnmatchedPayment::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($this->unmatchedSelectedId)
            : null;
        $selectedClaimCustomer = $this->claimSelectedCustomerId
            ? CustomersInfo::withoutGlobalScopes()->where('tenant_id', $tenantId)->with('billing', 'pppUser')->find($this->claimSelectedCustomerId)
            : null;

        return view('livewire.admin.unified-transactions-hub', [
            'metrics' => $this->metrics,
            'txRecords' => $txRecords,
            'unmatchedPayments' => $unmatchedPayments,
            'claimCustomerResults' => $claimCustomerResults,
            'selectedClaimPayment' => $selectedClaimPayment,
            'selectedClaimCustomer' => $selectedClaimCustomer,
        ])->layout('layouts.app');
    }
}
