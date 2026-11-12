<?php

namespace App\Livewire\Admin;

use App\Models\CustomersInfo;
use App\Models\SmsTemplate;
use App\Services\Sms\IspSmsDispatcher;
use Codepagol\SmsBridge\Facades\SmsBridge;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class SmsEmailHub extends Component
{
    use WithPagination;

    public string $activeTab = 'campaigns'; // 'campaigns', 'gateways', 'templates', 'bridge', 'logs'

    // ── Campaigns / Send SMS Tab ─────────────────────────────────
    public string $recipientGroup = 'all'; // 'all', 'active', 'inactive', 'due', 'custom'
    public string $customPhone = '';
    public string $messageContent = '';
    public string $selectedTemplateKey = '';
    public ?string $campaignStatus = null;
    public string $campaignStatusType = 'success';

    // ── Gateways Tab ─────────────────────────────────────────────
    public ?int $gateway_id = null;
    public string $gateway_type = 'africastalking'; // 'africastalking', 'advanta', 'blessed', 'textsms', 'system'
    public string $sender_id = 'HSMART';
    public string $api_key = '';
    public string $api_secret = '';
    public string $api_username = '';
    public string $endpoint_url = '';
    public string $http_method = 'POST';
    public string $body_template = '{"to": "{to}", "message": "{message}", "sender": "{sender}"}';
    public string $success_keyword = 'success';
    public bool $is_default = true;
    public bool $is_fallback = true;
    public float $low_balance_threshold = 100.0;

    // Test SMS Modal
    public string $test_phone = '';
    public string $test_message = 'HSmart ISP: This is a test SMS message to verify gateway connectivity.';
    public ?string $test_result = null;

    // Email SMTP settings
    public string $mail_mailer = 'smtp';
    public string $mail_host = 'smtp.mailtrap.io';
    public int $mail_port = 587;
    public string $mail_username = '';
    public string $mail_password = '';
    public string $mail_encryption = 'tls';
    public string $mail_from_address = 'support@hsmart.co.ke';
    public string $mail_from_name = 'HSmart ISP';
    public string $test_email_recipient = '';
    public ?string $test_email_result = null;

    // ── Templates Tab ────────────────────────────────────────────
    public array $templatesList = [];
    public ?int $editingTemplateId = null;
    public string $template_title = '';
    public string $template_body = '';

    public function mount()
    {
        if (! hasAccess(['Super Admin', 'Admin', 'Manager', 'Product Manager'], ['sms-setup', 'sms-gateways', 'outreach'])) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->has('tab') && in_array(request('tab'), ['campaigns', 'gateways', 'templates', 'bridge', 'logs'])) {
            $this->activeTab = request('tab');
        }

        $this->loadTenantGateway();
        $this->loadTemplates();
    }

    public function setTab(string $tab)
    {
        $this->activeTab = in_array($tab, ['campaigns', 'gateways', 'templates', 'bridge', 'logs']) ? $tab : 'campaigns';
        $this->resetPage();
    }

    protected function resolveTenantId(): int
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?: (session('tenant_id') ?: session('impersonated_tenant_id'));
        if (!$tenantId || !\Illuminate\Support\Facades\DB::table('tenants')->where('id', $tenantId)->exists()) {
            $tenantId = \Illuminate\Support\Facades\DB::table('tenants')->where('is_active', 1)->value('id')
                     ?: \Illuminate\Support\Facades\DB::table('tenants')->value('id')
                     ?: 1;
        }
        return (int) $tenantId;
    }

    public function loadTenantGateway()
    {
        $tenantId = $this->resolveTenantId();

        $gw = DB::table('isp_sms_gateways')->where('tenant_id', $tenantId)->first();
        if ($gw) {
            $this->gateway_id       = $gw->id;
            $this->gateway_type     = $gw->gateway_type;
            $this->sender_id        = $gw->sender_id;
            $this->api_username     = $gw->api_username;
            $this->endpoint_url     = $gw->endpoint_url;
            $this->http_method      = $gw->http_method;
            $this->body_template    = $gw->body_template;
            $this->success_keyword  = $gw->success_keyword;
            $this->is_default       = (bool) $gw->is_default;
            $this->is_fallback      = (bool) $gw->is_fallback;
            $this->low_balance_threshold = isset($gw->low_balance_threshold) ? (float) $gw->low_balance_threshold : 100.0;
            $this->api_key          = '••••••••••••••••';
            $this->api_secret       = '••••••••••••••••';
        }
    }

    public function loadTemplates()
    {
        $user = auth()->user();
        $tenantId = $user ? $user->tenant_id : null;

        if (\Illuminate\Support\Facades\Schema::hasTable('sms_templates')) {
            $this->templatesList = DB::table('sms_templates')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
                ->get()
                ->toArray();
        }
    }

    // ── Send Campaign SMS ────────────────────────────────────────
    public function sendCampaign()
    {
        $this->validate([
            'messageContent' => 'required|string|min:2|max:600',
        ]);

        $tenantId = $this->resolveTenantId();

        $recipients = [];

        if ($this->recipientGroup === 'custom') {
            $this->validate(['customPhone' => 'required|string']);
            $phones = array_filter(array_map('trim', explode(',', $this->customPhone)));
            foreach ($phones as $p) {
                $recipients[] = $p;
            }
        } else {
            $query = CustomersInfo::query();
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            if ($this->recipientGroup === 'active') {
                $query->where('status', 'active');
            } elseif ($this->recipientGroup === 'inactive') {
                $query->where('status', 'inactive');
            } elseif ($this->recipientGroup === 'due') {
                $query->where('status', 'due');
            }

            $recipients = $query->pluck('mobile')->filter()->unique()->toArray();
        }

        if (empty($recipients)) {
            $this->campaignStatusType = 'error';
            $this->campaignStatus = 'No valid customer recipients found for the selected filter.';
            return;
        }

        $sentCount = 0;
        foreach ($recipients as $phone) {
            $personalized = str_replace(
                ['{name}', '{phone}', '{site_name}'],
                ['Customer', $phone, config('app.name')],
                $this->messageContent
            );

            if (IspSmsDispatcher::sendDirectSms($phone, $personalized, $tenantId)) {
                $sentCount++;
            }
        }

        $this->campaignStatusType = 'success';
        $this->campaignStatus = "Successfully dispatched SMS to {$sentCount} recipient(s).";
        $this->messageContent = '';
        $this->customPhone = '';
    }

    public function applyTemplateToMessage()
    {
        if (empty($this->selectedTemplateKey)) return;

        foreach ($this->templatesList as $t) {
            $tArr = (array) $t;
            if (($tArr['id'] ?? '') == $this->selectedTemplateKey || ($tArr['title'] ?? '') == $this->selectedTemplateKey) {
                $this->messageContent = $tArr['body'] ?? ($tArr['template'] ?? '');
                break;
            }
        }
    }

    // ── Save Gateways ────────────────────────────────────────────
    public function saveGateway()
    {
        $tenantId = $this->resolveTenantId();

        $updateData = [
            'tenant_id'        => $tenantId,
            'gateway_type'     => $this->gateway_type,
            'sender_id'        => $this->sender_id,
            'api_username'     => $this->api_username,
            'endpoint_url'     => $this->endpoint_url,
            'http_method'      => $this->http_method,
            'body_template'    => $this->body_template,
            'success_keyword'  => $this->success_keyword,
            'is_default'            => $this->is_default,
            'is_fallback'           => $this->is_fallback,
            'low_balance_threshold' => max(0, (float) $this->low_balance_threshold),
            'updated_at'            => now(),
        ];

        // Only update secrets if newly provided (not mask)
        if (!empty($this->api_key) && !str_contains($this->api_key, '••••')) {
            $updateData['api_key'] = Crypt::encryptString(trim($this->api_key));
        }
        if (!empty($this->api_secret) && !str_contains($this->api_secret, '••••')) {
            $updateData['api_secret'] = Crypt::encryptString(trim($this->api_secret));
        }

        DB::table('isp_sms_gateways')->updateOrInsert(
            ['tenant_id' => $tenantId],
            $updateData
        );

        \Illuminate\Support\Facades\Cache::forget("sms_balance:{$tenantId}");

        $this->campaignStatusType = 'success';
        $this->campaignStatus = "SMS Gateway settings saved successfully.";
    }

    public function sendTestSms()
    {
        $this->validate([
            'test_phone'   => 'required|string',
            'test_message' => 'required|string|min:5',
        ]);

        $tenantId = $this->resolveTenantId();

        $sent = IspSmsDispatcher::sendDirectSms($this->test_phone, $this->test_message, $tenantId);

        if ($sent) {
            $this->test_result = "Test SMS sent successfully to {$this->test_phone}!";
        } else {
            $this->test_result = "Error sending SMS. Please verify your API Key and Sender ID.";
        }
    }

    public function render()
    {
        $tenantId = $this->resolveTenantId();

        // Delivery Logs
        $logs = DB::table('isp_notification_logs')
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->paginate(15);

        // SMS Wallet balance
        $wallet = DB::table('sms_wallets')->where('tenant_id', $tenantId)->first();
        $smsBalance = $wallet ? (int) $wallet->balance : 20;

        return view('livewire.admin.sms-email-hub', compact('logs', 'smsBalance'))->layout('layouts.app');
    }
}
