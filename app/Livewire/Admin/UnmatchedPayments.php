<?php

namespace App\Livewire\Admin;

use App\Models\CustomersInfo;
use App\Models\UnmatchedPayment;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class UnmatchedPayments extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'pending';
    public string $sourceFilter = 'all';

    // Manual Assign Modal State
    public ?int $selectedPaymentId = null;
    public string $customerSearch = '';
    public ?int $selectedCustomerId = null;
    public string $adminNotes = '';
    public string $statusMessage = '';
    public string $errorMessage = '';

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingSourceFilter(): void { $this->resetPage(); }

    public function openAssignModal(int $paymentId): void
    {
        $this->resetErrorBag();
        $this->statusMessage = '';
        $this->errorMessage = '';
        $this->selectedPaymentId = $paymentId;
        $this->customerSearch = '';
        $this->selectedCustomerId = null;
        $this->adminNotes = '';

        $payment = UnmatchedPayment::find($paymentId);
        if ($payment) {
            // Prepopulate search with phone or account reference if present
            $this->customerSearch = $payment->account_reference ?: ($payment->phone ?: '');
        }
    }

    public function closeAssignModal(): void
    {
        $this->selectedPaymentId = null;
        $this->selectedCustomerId = null;
        $this->customerSearch = '';
        $this->adminNotes = '';
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
    }

    public function assignPayment(): void
    {
        if (! hasAccess(['Super Admin'], ['payment-collection'])) {
            abort(403, 'Unauthorized action.');
        }

        if (! $this->selectedPaymentId || ! $this->selectedCustomerId) {
            $this->errorMessage = 'Please select a customer to assign this payment to.';
            return;
        }

        $payment = UnmatchedPayment::find($this->selectedPaymentId);
        $customer = CustomersInfo::find($this->selectedCustomerId);

        if (! $payment || ! $customer) {
            $this->errorMessage = 'Selected payment or customer no longer exists.';
            return;
        }

        if ($payment->status === 'matched' || $payment->status === 'manual_matched') {
            $this->errorMessage = 'This payment has already been matched and processed.';
            return;
        }

        try {
            // Centralized fulfill()
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

            // Update UnmatchedPayment record
            $payment->update([
                'status'             => 'manual_matched',
                'customer_id'        => $customer->id,
                'customer_unique_id' => $customer->customer_unique_id,
                'assigned_by'        => auth()->id(),
                'assigned_at'        => now(),
                'admin_notes'        => $this->adminNotes ?: 'Manually assigned by admin.',
            ]);

            $this->statusMessage = "Transaction [{$payment->transaction_code}] successfully assigned to {$customer->customer_name} ({$customer->customer_unique_id})!";
            $this->closeAssignModal();

        } catch (\Exception $e) {
            Log::error('Manual payment assignment failed: ' . $e->getMessage());
            $this->errorMessage = 'Failed to fulfill payment: ' . $e->getMessage();
        }
    }

    public function ignorePayment(int $paymentId): void
    {
        if (! hasAccess(['Super Admin'], ['payment-collection'])) {
            abort(403, 'Unauthorized action.');
        }

        $payment = UnmatchedPayment::find($paymentId);
        if ($payment) {
            $payment->update([
                'status'      => 'ignored',
                'admin_notes' => 'Marked as ignored by admin on ' . now()->toDateTimeString(),
            ]);
            $this->statusMessage = "Payment [{$payment->transaction_code}] marked as ignored.";
        }
    }

    public function render()
    {
        if (! hasAccess(['Super Admin'], ['payment-collection'])) {
            abort(403, 'Unauthorized action.');
        }

        $query = UnmatchedPayment::with(['customer', 'assignedByUser'])
            ->orderBy('created_at', 'desc');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->sourceFilter !== 'all') {
            $query->where('source', $this->sourceFilter);
        }

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('transaction_code', 'like', '%' . $this->search . '%')
                    ->orWhere('phone', 'like', '%' . $this->search . '%')
                    ->orWhere('sender_name', 'like', '%' . $this->search . '%')
                    ->orWhere('account_reference', 'like', '%' . $this->search . '%')
                    ->orWhere('customer_unique_id', 'like', '%' . $this->search . '%');
            });
        }

        $payments = $query->paginate(15);

        // Search customers for assign modal
        $searchedCustomers = collect();
        if ($this->selectedPaymentId && strlen(trim($this->customerSearch)) >= 2) {
            $searchedCustomers = CustomersInfo::with('billing', 'pppUser')
                ->where(function ($q) {
                    $q->where('customer_name', 'like', '%' . $this->customerSearch . '%')
                        ->orWhere('customer_unique_id', 'like', '%' . $this->customerSearch . '%')
                        ->orWhere('mobile', 'like', '%' . $this->customerSearch . '%')
                        ->orWhereHas('pppUser', fn($qp) => $qp->where('username', 'like', '%' . $this->customerSearch . '%'));
                })
                ->take(8)
                ->get();
        }

        $selectedPayment = $this->selectedPaymentId ? UnmatchedPayment::find($this->selectedPaymentId) : null;
        $selectedCustomer = $this->selectedCustomerId ? CustomersInfo::with('billing', 'pppUser')->find($this->selectedCustomerId) : null;

        $pendingCount = UnmatchedPayment::where('status', 'pending')->count();

        return view('livewire.admin.unmatched-payments', compact(
            'payments',
            'searchedCustomers',
            'selectedPayment',
            'selectedCustomer',
            'pendingCount'
        ))->layout('layouts.app');
    }
}

