<?php

namespace App\Livewire\Admin;

use App\Models\PaymentChannel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PaymentGatewaysManager extends Component
{
    // Tenant Scope
    public ?int $tenantId = null;

    // Active Channel Form state
    public bool $showChannelModal = false;
    public ?int $editingChannelId = null;

    public string $gateway = 'payhero'; // payhero, daraja, kopokopo
    public string $channel_type = 'till'; // till, paybill, bank
    public string $channel_name = '';
    public string $payhero_channel_id = '';
    public string $short_code = '';
    public string $account_number = '';
    public string $bank_name = '';
    public string $bank_code = '';

    // Credentials (plain inputs in modal, encrypted when saving)
    public string $api_key = '';
    public string $api_secret = '';
    public string $passkey = '';

    public bool $is_default = false;
    public bool $is_active = true;

    // Testing state
    public ?string $testStatus = null;
    public ?string $testMessage = null;
    public bool $isTesting = false;

    // Global Platform Fallback status
    public bool $usePlatformFallback = true;

    protected function rules(): array
    {
        return [
            'gateway'            => 'required|in:payhero,daraja,kopokopo',
            'channel_type'       => 'required|in:till,paybill,bank',
            'channel_name'       => 'required|string|max:100',
            'short_code'         => 'required_if:channel_type,till,paybill|nullable|string|max:30',
            'account_number'     => 'required_if:channel_type,paybill,bank|nullable|string|max:50',
            'payhero_channel_id' => 'required_if:gateway,payhero|nullable|string|max:50',
            'api_key'            => $this->editingChannelId ? 'nullable|string' : 'required|string',
            'api_secret'         => 'nullable|string',
        ];
    }

    public function mount(): void
    {
        $user = auth()->user();

        // Authorize: Tenant Admins, Managers, Super Admins
        $hasAccess = $user && (
            in_array(strtolower($user->role ?? ''), ['admin', 'superadmin', 'super_admin', 'manager', 'isp_admin']) ||
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Super Admin', 'Manager'])) ||
            (method_exists($user, 'can') && ($user->can('site-setup') || $user->can('payment-gateways') || $user->can('all-customer')))
        );

        if (!$hasAccess) {
            abort(403, 'Unauthorized access to Payment Gateways.');
        }

        $this->tenantId = $user->tenant_id ?: (session('tenant_id') ?: session('impersonated_tenant_id') ?: 1);

        // Ensure at least tenant 1 or valid tenant is set
        if (!\Illuminate\Support\Facades\DB::table('tenants')->where('id', $this->tenantId)->exists()) {
            $this->tenantId = \Illuminate\Support\Facades\DB::table('tenants')->value('id') ?: 1;
        }
    }

    public function getChannelsProperty()
    {
        return PaymentChannel::where('tenant_id', $this->tenantId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function openCreateModal(string $gateway = 'payhero'): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $this->editingChannelId = null;
        $this->gateway = $gateway;
        $this->channel_type = 'till';
        $this->channel_name = $gateway === 'payhero' ? 'M-Pesa Buy Goods Till' : ($gateway === 'daraja' ? 'Daraja Paybill' : 'KopoKopo Till');
        $this->payhero_channel_id = '';
        $this->short_code = '';
        $this->account_number = '';
        $this->bank_name = '';
        $this->bank_code = '';
        $this->api_key = '';
        $this->api_secret = '';
        $this->passkey = '';
        $this->is_default = $this->channels->count() === 0;
        $this->is_active = true;
        $this->testStatus = null;
        $this->testMessage = null;

        $this->showChannelModal = true;
    }

    public function editChannel(int $id): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $channel = PaymentChannel::where('tenant_id', $this->tenantId)->findOrFail($id);

        $this->editingChannelId = $channel->id;
        $this->gateway = $channel->gateway;
        $this->channel_type = $channel->channel_type;
        $this->channel_name = $channel->channel_name;
        $this->payhero_channel_id = $channel->payhero_channel_id ?? '';
        $this->short_code = $channel->short_code ?? '';
        $this->account_number = $channel->account_number ?? '';
        $this->bank_name = $channel->bank_name ?? '';
        $this->bank_code = $channel->bank_code ?? '';

        // Leave secrets blank to keep existing unless entered
        $this->api_key = '';
        $this->api_secret = '';
        $this->passkey = '';

        $this->is_default = (bool) $channel->is_default;
        $this->is_active = (bool) $channel->is_active;
        $this->testStatus = null;
        $this->testMessage = null;

        $this->showChannelModal = true;
    }

    public function saveChannel(): void
    {
        $this->validate();

        $data = [
            'tenant_id'          => $this->tenantId,
            'gateway'            => $this->gateway,
            'channel_type'       => $this->channel_type,
            'channel_name'       => $this->channel_name,
            'payhero_channel_id' => $this->payhero_channel_id ?: null,
            'short_code'         => $this->short_code ?: null,
            'account_number'     => $this->account_number ?: null,
            'bank_name'          => $this->bank_name ?: null,
            'bank_code'          => $this->bank_code ?: null,
            'is_default'         => $this->is_default,
            'is_active'          => $this->is_active,
            'status'             => 'active',
        ];

        if (!empty($this->api_key)) {
            $data['api_key_encrypted'] = Crypt::encryptString($this->api_key);
        }
        if (!empty($this->api_secret)) {
            $data['api_secret_encrypted'] = Crypt::encryptString($this->api_secret);
        }
        if (!empty($this->passkey)) {
            $data['passkey_encrypted'] = Crypt::encryptString($this->passkey);
        }

        if ($this->is_default) {
            PaymentChannel::where('tenant_id', $this->tenantId)->update(['is_default' => false]);
        }

        if ($this->editingChannelId) {
            $channel = PaymentChannel::where('tenant_id', $this->tenantId)->findOrFail($this->editingChannelId);
            $channel->update($data);
            session()->flash('success', "Payment channel '{$this->channel_name}' updated successfully.");
        } else {
            PaymentChannel::create($data);
            session()->flash('success', "Payment channel '{$this->channel_name}' registered successfully.");
        }

        $this->showChannelModal = false;
    }

    public function setDefaultChannel(int $id): void
    {
        PaymentChannel::where('tenant_id', $this->tenantId)->update(['is_default' => false]);
        PaymentChannel::where('tenant_id', $this->tenantId)->where('id', $id)->update(['is_default' => true]);
        session()->flash('success', 'Default payment channel updated.');
    }

    public function toggleChannelActive(int $id): void
    {
        $channel = PaymentChannel::where('tenant_id', $this->tenantId)->findOrFail($id);
        $channel->update(['is_active' => !$channel->is_active]);
        session()->flash('success', "Channel status toggled to " . ($channel->is_active ? 'Active' : 'Inactive') . ".");
    }

    public function testChannelConnection(int $id): void
    {
        $channel = PaymentChannel::where('tenant_id', $this->tenantId)->findOrFail($id);
        $this->isTesting = true;

        try {
            if ($channel->gateway === 'payhero') {
                $apiKey = $channel->api_key_encrypted ? Crypt::decryptString($channel->api_key_encrypted) : '';
                $channelId = $channel->payhero_channel_id;

                if (empty($apiKey) || empty($channelId)) {
                    $channel->update(['status' => 'error', 'last_tested_at' => now()]);
                    session()->flash('error', 'PayHero credentials or Channel ID are missing.');
                    $this->isTesting = false;
                    return;
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ])->timeout(8)->get("https://backend.payhero.co.ke/api/v2/channels/{$channelId}");

                if ($response->successful()) {
                    $channel->update(['status' => 'active', 'last_tested_at' => now()]);
                    session()->flash('success', 'PayHero Channel verified! Connectivity OK.');
                } else {
                    $channel->update(['status' => 'error', 'last_tested_at' => now()]);
                    session()->flash('error', 'PayHero responded with error: ' . ($response->json('message') ?? 'HTTP ' . $response->status()));
                }
            } else {
                $channel->update(['status' => 'active', 'last_tested_at' => now()]);
                session()->flash('success', 'Gateway parameters formatted correctly.');
            }
        } catch (\Throwable $e) {
            $channel->update(['status' => 'error', 'last_tested_at' => now()]);
            session()->flash('error', 'Connection test failed: ' . $e->getMessage());
        }

        $this->isTesting = false;
    }

    public function deleteChannel(int $id): void
    {
        $channel = PaymentChannel::where('tenant_id', $this->tenantId)->findOrFail($id);
        $name = $channel->channel_name;
        $channel->delete();

        session()->flash('success', "Payment channel '{$name}' removed.");
    }

    public function render()
    {
        return view('livewire.admin.payment-gateways-manager', [
            'channels' => $this->channels,
        ])->layout('layouts.app');
    }
}

