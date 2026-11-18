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
    private function getTenantId(): ?int
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        $tenantId = $user->tenant_id ?? null;
        return ($tenantId && $tenantId > 1) ? $tenantId : null;
    }

    private function customerScope()
    {
        $user = auth()->user();
        $query = CustomersInfo::query();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('Reseller') && $user->reseller) {
            $query->where('reseller_id', $user->reseller->id);
        } elseif ($tenantId = $this->getTenantId()) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    // ── Metric calculations ──
    public function getMetricsProperty(): array
    {
        $tenantId = $this->getTenantId();
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // PPPoE / CollectionSummary totals
        $pppoeTodayQuery = CollectionSummary::whereDate('collection_date', $today);
        $pppoeMonthQuery = CollectionSummary::whereBetween('collection_date', [$startOfMonth, $endOfMonth]);

        if ($tenantId) {
            $pppoeTodayQuery->whereHas('customer', fn($q) => $q->where('tenant_id', $tenantId));
            $pppoeMonthQuery->whereHas('customer', fn($q) => $q->where('tenant_id', $tenantId));
        }
        $pppoeToday = (float) $pppoeTodayQuery->sum('collection_amount');
        $pppoeMonth = (float) $pppoeMonthQuery->sum('collection_amount');

        // HotspotSale totals
        $hotspotToday = 0.0;
        $hotspotMonth = 0.0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('hotspot_sales')) {
                $hsTodayQuery = HotspotSale::whereDate('sale_date', $today);
                $hsMonthQuery = HotspotSale::whereBetween('sale_date', [$startOfMonth, $endOfMonth]);
                if ($tenantId) {
                    $hsTodayQuery->where('tenant_id', $tenantId);
                    $hsMonthQuery->where('tenant_id', $tenantId);
                }
                $hotspotToday = (float) $hsTodayQuery->sum('amount');
                $hotspotMonth = (float) $hsMonthQuery->sum('amount');
            }
        } catch (\Throwable $e) {}

        // Pending Unmatched
        $pendingUnmatched = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('unmatched_payments')) {
                $unmatchedQuery = UnmatchedPayment::where('status', 'pending');
                if ($tenantId) {
                    $unmatchedQuery->where('tenant_id', $tenantId);
                }
                $pendingUnmatched = $unmatchedQuery->count();
            }
        } catch (\Throwable $e) {}

        return [
            'today_total' => $pppoeToday + $hotspotToday,
            'month_total' => $pppoeMonth + $hotspotMonth,
            'pppoe_month' => $pppoeMonth,
            'hotspot_month' => $hotspotMonth,
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
        $this->selectedReceiptId = $id;
        $this->selectedReceiptType = $type;

        if ($type === 'hotspot') {
            $this->selectedReceiptData = HotspotSale::find($id);
        } else {
            $this->selectedReceiptData = CollectionSummary::with(['customer', 'customer.customerAddress', 'customer.pppUser', 'customer.billing'])->find($id);
        }
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
            $uniqueId = $this->collectCustomerData->customer_unique_id;

            CollectionSummary::create([
                'customer_collection_unique_id' => $uniqueId,
                'collection_date' => Carbon::now(),
                'collection_amount' => $paid,
                'collected_by' => auth()->user()?->email ?? 'admin',
                'payment_type' => 'Cash',
                'payment_method' => 'Cash',
                'payment_status' => 'paid',
                'invoice_no' => time() . rand(10, 99),
                'bill_month' => Carbon::now()->format('F-Y'),
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
        $collection = CollectionSummary::find($id);
        if (! $collection) {
            flash()->error('Collection record not found.');
            return;
        }

        $collectedAmount = (float) $collection->collection_amount;
        $customerId = $collection->customer_collection_unique_id;

        $billing = BillingInfo::where('customer_bill_unique_id', $customerId)->first();
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

            $collectionsQuery = CollectionSummary::with(['customer', 'customer.pppUser'])
                ->orderBy('collection_date', 'desc');

            if ($dateStart && $dateEnd) {
                $collectionsQuery->whereBetween('collection_date', [$dateStart, $dateEnd]);
            }
            if ($tenantId) {
                $collectionsQuery->whereHas('customer', fn($q) => $q->where('tenant_id', $tenantId));
            }
            if (! empty($this->txSearch)) {
                $term = $this->txSearch;
                $collectionsQuery->where(function ($q) use ($term) {
                    $q->where('transaction_id', 'like', "%{$term}%")
                        ->orWhere('invoice_no', 'like', "%{$term}%")
                        ->orWhere('customer_collection_unique_id', 'like', "%{$term}%")
                        ->orWhereHas('customer', function ($qc) use ($term) {
                            $qc->where('customer_name', 'like', "%{$term}%")
                                ->orWhere('mobile', 'like', "%{$term}%")
                                ->orWhereHas('pppUser', fn($qp) => $qp->where('username', 'like', "%{$term}%"));
                        });
                });
            }
            if ($this->txTypeFilter === 'cash') {
                $collectionsQuery->where(function ($q) {
                    $q->where('payment_method', 'cash')
                        ->orWhere('payment_type', 'cash')
                        ->orWhere('collected_by', 'like', '%@%');
                });
            }

            // If Hotspot filter is active, skip collection summaries or prioritize HotspotSale
            if ($this->txTypeFilter === 'hotspot') {
                $hotspotQuery = HotspotSale::orderBy('created_at', 'desc');
                if ($dateStart && $dateEnd) {
                    $hotspotQuery->whereBetween('sale_date', [$dateStart->toDateString(), $dateEnd->toDateString()]);
                }
                if ($tenantId) {
                    $hotspotQuery->where('tenant_id', $tenantId);
                }
                if (! empty($this->txSearch)) {
                    $term = $this->txSearch;
                    $hotspotQuery->where(function ($q) use ($term) {
                        $q->where('voucher_code', 'like', "%{$term}%")
                            ->orWhere('username', 'like', "%{$term}%")
                            ->orWhere('router_name', 'like', "%{$term}%")
                            ->orWhere('payment_method', 'like', "%{$term}%");
                    });
                }
                $txRecords = $hotspotQuery->paginate(15);
            } else {
                $txRecords = $collectionsQuery->paginate(15);
            }
        }

        // ── Data for Tab 3: Unmatched ──
        $unmatchedPayments = collect();
        $claimCustomerResults = collect();
        if ($this->activeTab === 'unmatched') {
            $uQuery = UnmatchedPayment::with(['customer', 'assignedByUser'])
                ->orderBy('created_at', 'desc');

            if ($tenantId) {
                $uQuery->where('tenant_id', $tenantId);
            }
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

        $selectedClaimPayment = $this->unmatchedSelectedId ? UnmatchedPayment::find($this->unmatchedSelectedId) : null;
        $selectedClaimCustomer = $this->claimSelectedCustomerId ? CustomersInfo::with('billing', 'pppUser')->find($this->claimSelectedCustomerId) : null;

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
