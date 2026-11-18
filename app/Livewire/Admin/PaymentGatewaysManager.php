<?php

namespace App\Livewire\Admin;

use App\Models\PaymentChannel;
use App\Services\PayHeroPaymentService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PaymentGatewaysManager extends Component
{
    // Tenant Scope
    public ?int $tenantId = null;

    // Platform Toggle Switch
    public bool $payhero_enabled = true;

    // Settlement Channel Selection ('buy_goods', 'paybill', 'bank')
    public string $channel_type = 'buy_goods';

    // Form inputs
    public string $till_number = '';
    public string $paybill_number = '';
    public string $account_number = '';
    public string $bank_name = '';
    public string $bank_code = '';
    public string $channel_name = ''; // Friendly Label

    // Searchable Bank Dropdown
    public string $bank_search = '';
    public bool $show_bank_dropdown = false;

    // UI State
    public bool $is_submitting = false;
    public ?int $processingId = null;

    // Static Kenya Banks list identical to POS PaymentChannelForm.vue
    public array $banks = [
        ['name' => 'Equity Bank Ltd.',                      'code' => '247247'],
        ['name' => 'Kenya Commercial Bank (KCB)',           'code' => '522522'],
        ['name' => 'Co-operative Bank of Kenya',            'code' => '400200'],
        ['name' => 'Absa Bank Kenya (Barclays)',            'code' => '303030'],
        ['name' => 'NCBA Bank (Personal / General)',        'code' => '880100'],
        ['name' => 'NCBA Loop (Personal Deposit)',          'code' => '714777'],
        ['name' => 'NCBA Loop BIZ (Merchant / Till)',       'code' => '714888'],
        ['name' => 'Family Bank Ltd.',                      'code' => '222111'],
        ['name' => 'Standard Chartered Bank Kenya',         'code' => '329329'],
        ['name' => 'Diamond Trust Bank (DTB)',              'code' => '516600'],
        ['name' => 'Post Office Savings Bank (Postbank)',    'code' => '200999'],
        ['name' => 'Guaranty Trust Bank (GTBank)',          'code' => '910200'],
        ['name' => 'Housing Finance (HFC)',                 'code' => '985050'],
        ['name' => 'Bank of Africa Kenya (BOA)',            'code' => '100400'],
        ['name' => 'Gulf African Bank',                     'code' => '200999'],
        ['name' => 'Consolidated Bank of Kenya',           'code' => '972700'],
        ['name' => 'UBA Bank Kenya (United Bank Africa)',   'code' => '972900'],
        ['name' => 'Guardian Bank Kenya',                   'code' => '559900'],
        ['name' => 'ABC Bank (African Banking Corp)',       'code' => '111777'],
        ['name' => 'Stanbic Bank (CfC)',                    'code' => '600100'],
        ['name' => 'I & M Bank Ltd.',                       'code' => '542542'],
        ['name' => 'National Bank of Kenya',                'code' => '625625'],
        ['name' => 'Ecobank Kenya',                         'code' => '700201'],
        ['name' => 'Sidian Bank (K-Rep)',                   'code' => '111999'],
        ['name' => 'Kingdom Bank (Jamii Bora)',             'code' => '052052'],
        ['name' => 'Credit Bank Kenya',                     'code' => '074074'],
        ['name' => 'Faulu Microfinance Bank',               'code' => '328585'],
        ['name' => 'Kenya Women Microfinance (KWFT)',       'code' => '101200'],
        ['name' => 'Rafiki Microfinance Bank',              'code' => '802200'],
        ['name' => 'SMEP Microfinance Bank',                'code' => '777001'],
        ['name' => 'Musoni Microfinance',                   'code' => '514000'],
        ['name' => 'VisionFund Kenya',                      'code' => '20055'],
    ];

    public function mount(): void
    {
        $user = auth()->user();

        // Authorize: Admins, Managers, Super Admins
        $hasAccess = $user && (
            in_array(strtolower($user->role ?? ''), ['admin', 'superadmin', 'super_admin', 'manager', 'isp_admin']) ||
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Super Admin', 'Manager'])) ||
            (method_exists($user, 'can') && ($user->can('site-setup') || $user->can('payment-gateways') || $user->can('all-customer')))
        );

        if (!$hasAccess) {
            abort(403, 'Unauthorized access to Payment Gateways.');
        }

        $this->tenantId = $user->tenant_id ?: (session('tenant_id') ?: session('impersonated_tenant_id') ?: 1);

        if (!\Illuminate\Support\Facades\DB::table('tenants')->where('id', $this->tenantId)->exists()) {
            $this->tenantId = \Illuminate\Support\Facades\DB::table('tenants')->value('id') ?: 1;
        }

        $this->payhero_enabled = (bool) (siteUrlSettings('payhero_stk_enabled') ?? true);
    }

    public function getChannelsProperty()
    {
        return PaymentChannel::where('tenant_id', $this->tenantId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getFilteredBanksProperty(): array
    {
        if (empty(trim($this->bank_search))) {
            return $this->banks;
        }

        $q = strtolower(trim($this->bank_search));
        return array_values(array_filter($this->banks, function ($b) use ($q) {
            return str_contains(strtolower($b['name']), $q) || str_contains($b['code'], $q);
        }));
    }

    public function selectChannelType(string $type): void
    {
        $this->channel_type = in_array($type, ['buy_goods', 'paybill', 'bank']) ? $type : 'buy_goods';
        $this->reset(['till_number', 'paybill_number', 'account_number', 'bank_name', 'bank_code', 'bank_search', 'channel_name']);
        $this->show_bank_dropdown = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function selectBank(string $name, string $code): void
    {
        $this->bank_name = $name;
        $this->bank_code = $code;
        $this->paybill_number = $code;
        $this->bank_search = "{$name} ({$code})";
        $this->show_bank_dropdown = false;
    }

    public function togglePayheroEnabled(): void
    {
        $this->payhero_enabled = !$this->payhero_enabled;
        
        try {
            if (function_exists('saveSiteSettings')) {
                saveSiteSettings(['payhero_stk_enabled' => $this->payhero_enabled ? '1' : '0']);
            }
        } catch (\Throwable $e) {}

        session()->flash('success', 'PayHero M-Pesa STK Push has been ' . ($this->payhero_enabled ? 'enabled' : 'disabled') . '.');
    }

    public function confirmAndLinkChannel(): void
    {
        $this->resetErrorBag();

        // 1. Validation according to selected channel type
        if ($this->channel_type === 'buy_goods') {
            $this->validate([
                'till_number' => 'required|numeric|digits_between:4,10',
            ], [
                'till_number.required' => 'Please enter your M-Pesa Buy Goods Till number.',
                'till_number.numeric'  => 'Till number must contain numbers only.',
            ]);
        } elseif ($this->channel_type === 'paybill') {
            $this->validate([
                'paybill_number' => 'required|numeric|digits_between:4,10',
                'account_number' => 'required|string|max:40',
            ], [
                'paybill_number.required' => 'Please enter your Paybill business number.',
                'account_number.required' => 'Please enter your Paybill account reference.',
            ]);
        } elseif ($this->channel_type === 'bank') {
            $this->validate([
                'bank_name'      => 'required|string',
                'bank_code'      => 'required|string',
                'account_number' => 'required|string|max:40',
            ], [
                'bank_name.required'      => 'Please select a bank from the list.',
                'account_number.required' => 'Please enter your bank account number.',
            ]);
        }

        $this->is_submitting = true;

        try {
            $shortCode = $this->channel_type === 'buy_goods'
                ? trim($this->till_number)
                : ($this->channel_type === 'paybill' ? trim($this->paybill_number) : trim($this->bank_code));

            $accountNo = $this->channel_type === 'buy_goods'
                ? trim($this->till_number)
                : trim($this->account_number);

            $defaultLabel = match ($this->channel_type) {
                'buy_goods' => "M-Pesa Till {$shortCode}",
                'paybill'   => "M-Pesa Paybill {$shortCode}",
                'bank'      => "{$this->bank_name} {$this->bank_code}",
                default     => "Channel {$shortCode}",
            };

            $label = !empty(trim($this->channel_name)) ? trim($this->channel_name) : $defaultLabel;

            // 2. Register channel with PayHero under Master Admin credentials (with safe fallback)
            $payheroChannelId = null;
            try {
                $service = new PayHeroPaymentService($this->tenantId);
                $regResult = $service->registerChannel([
                    'channel_type'   => $this->channel_type,
                    'short_code'     => $shortCode,
                    'account_number' => $accountNo,
                    'description'    => $label,
                ]);
                $payheroChannelId = $regResult['channel_id'] ?? $regResult['id'] ?? null;
            } catch (\Throwable $pe) {
                Log::warning('PayHero channel registration notice: ' . $pe->getMessage());
            }

            // 3. Save into local tenant database
            $isFirst = PaymentChannel::where('tenant_id', $this->tenantId)->count() === 0;

            PaymentChannel::create([
                'tenant_id'          => $this->tenantId,
                'user_id'            => auth()->id(),
                'gateway'            => 'payhero',
                'channel_type'       => $this->channel_type,
                'channel_name'       => $label,
                'payhero_channel_id' => $payheroChannelId,
                'short_code'         => $shortCode,
                'account_number'     => $accountNo,
                'bank_name'          => $this->channel_type === 'bank' ? $this->bank_name : null,
                'bank_code'          => $this->channel_type === 'bank' ? $this->bank_code : null,
                'is_default'         => $isFirst,
                'is_active'          => true,
                'status'             => 'active',
            ]);

            // Reset form
            $this->reset(['till_number', 'paybill_number', 'account_number', 'bank_name', 'bank_code', 'bank_search', 'channel_name']);
            $this->show_bank_dropdown = false;

            session()->flash('success', "Payment channel '{$label}' confirmed and linked successfully!");
        } catch (\Throwable $e) {
            Log::error('Error linking payment channel: ' . $e->getMessage());
            session()->flash('error', 'Failed to link channel: ' . $e->getMessage());
        } finally {
            $this->is_submitting = false;
        }
    }

    public function setDefaultChannel(int $id): void
    {
        $this->processingId = $id;

        try {
            PaymentChannel::where('tenant_id', $this->tenantId)->update(['is_default' => false]);
            $channel = PaymentChannel::where('tenant_id', $this->tenantId)->findOrFail($id);
            $channel->update([
                'is_default' => true,
                'is_active'  => true,
                'status'     => 'active',
            ]);

            session()->flash('success', "Default payment collection channel set to '{$channel->channel_name}'.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Error setting default channel: ' . $e->getMessage());
        } finally {
            $this->processingId = null;
        }
    }

    public function toggleChannelActive(int $id): void
    {
        $this->processingId = $id;

        try {
            $channel = PaymentChannel::where('tenant_id', $this->tenantId)->findOrFail($id);
            $newActive = !$channel->is_active;

            $channel->update([
                'is_active' => $newActive,
                'status'    => $newActive ? 'active' : 'inactive',
            ]);

            session()->flash('success', "Channel '{$channel->channel_name}' is now " . ($newActive ? 'Active' : 'Inactive') . ".");
        } catch (\Throwable $e) {
            session()->flash('error', 'Error toggling channel: ' . $e->getMessage());
        } finally {
            $this->processingId = null;
        }
    }

    public function deleteChannel(int $id): void
    {
        $this->processingId = $id;

        try {
            $channel = PaymentChannel::where('tenant_id', $this->tenantId)->findOrFail($id);
            $wasDefault = $channel->is_default;
            $name = $channel->channel_name;
            $channel->delete();

            // If deleted channel was default, set another active channel as default
            if ($wasDefault) {
                $next = PaymentChannel::where('tenant_id', $this->tenantId)->where('is_active', true)->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }

            session()->flash('success', "Payment channel '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Error deleting channel: ' . $e->getMessage());
        } finally {
            $this->processingId = null;
        }
    }

    public function render()
    {
        return view('livewire.admin.payment-gateways-manager', [
            'channels'      => $this->channels,
            'activeCount'   => $this->channels->where('is_active', true)->count(),
            'inactiveCount' => $this->channels->where('is_active', false)->count(),
            'filteredBanks' => $this->filtered_banks,
        ])->layout('layouts.app');
    }
}
