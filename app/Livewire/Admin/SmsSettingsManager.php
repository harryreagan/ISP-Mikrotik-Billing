<?php

namespace App\Services\Sms;

// helper function decryptStringSafe already defined in IspSmsDispatcher.php
namespace App\Livewire\Admin;

use App\Services\Sms\IspSmsDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class SmsSettingsManager extends Component
{
    public $activeTab = 'gateways'; // 'gateways', 'wallet', 'email', 'logs'

    // Form inputs for gateway
    public $gateway_id = null;
    public $gateway_type = 'africastalking';
    public $sender_id = 'HSMART';
    public $api_key = '';
    public $api_secret = '';
    public $api_username = '';
    public $endpoint_url = '';
    public $http_method = 'POST';
    public $http_headers = '{"Content-Type": "application/json"}';
    public $body_template = '{"to": "{to}", "message": "{message}", "sender": "{sender}"}';
    public $success_keyword = 'success';
    public $is_default = true;
    public $is_fallback = true;

    // Test SMS Modal
    public $test_phone = '';
    public $test_message = 'HSmart ISP: This is a test SMS message to verify gateway connectivity.';
    public $test_result = null;

    // Email SMTP settings
    public $mail_mailer = 'smtp';
    public $mail_host = 'smtp.mailtrap.io';
    public $mail_port = 587;
    public $mail_username = '';
    public $mail_password = '';
    public $mail_encryption = 'tls';
    public $mail_from_address = 'support@hsmart.co.ke';
    public $mail_from_name = 'HSmart ISP';
    public $test_email_recipient = '';
    public $test_email_result = null;

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

    public function mount()
    {
        $tenantId = $this->resolveTenantId();

        $existing = DB::table('isp_sms_gateways')->where('tenant_id', $tenantId)->first();
        if ($existing) {
            $this->loadGateway($existing->id);
        }
    }

    public function loadGateway($id)
    {
        $gw = DB::table('isp_sms_gateways')->find($id);
        if ($gw) {
            $this->gateway_id       = $gw->id;
            $this->gateway_type     = $gw->gateway_type;
            $this->sender_id        = $gw->sender_id;
            $this->api_username     = $gw->api_username;
            $this->endpoint_url     = $gw->endpoint_url;
            $this->http_method      = $gw->http_method;
            $this->http_headers     = is_string($gw->http_headers) ? $gw->http_headers : json_encode($gw->http_headers);
            $this->body_template    = $gw->body_template;
            $this->success_keyword  = $gw->success_keyword;
            $this->is_default       = (bool) $gw->is_default;
            $this->is_fallback      = (bool) $gw->is_fallback;
            // Never expose secret in plain text
            $this->api_key          = '••••••••••••••••';
            $this->api_secret       = '••••••••••••••••';
        }
    }

    public function saveGateway()
    {
        $tenantId = $this->resolveTenantId();

        $data = [
            'tenant_id'        => $tenantId,
            'gateway_type'     => $this->gateway_type,
            'sender_id'        => $this->sender_id,
            'api_username'     => $this->api_username,
            'endpoint_url'     => $this->endpoint_url,
            'http_method'      => $this->http_method,
            'http_headers'     => is_array($this->http_headers) ? json_encode($this->http_headers) : ($this->http_headers ?: json_encode([])),
            'body_template'    => $this->body_template,
            'success_keyword'  => $this->success_keyword,
            'is_default'       => $this->is_default,
            'is_fallback'      => $this->is_fallback,
            'updated_at'       => now(),
        ];

        // Encrypt secrets if changed
        if (!empty($this->api_key) && $this->api_key !== '••••••••••••••••') {
            $data['api_key'] = encrypt($this->api_key);
        }
        if (!empty($this->api_secret) && $this->api_secret !== '••••••••••••••••') {
            $data['api_secret'] = encrypt($this->api_secret);
        }

        if ($this->is_default) {
            DB::table('isp_sms_gateways')->where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        if ($this->gateway_id) {
            DB::table('isp_sms_gateways')->where('id', $this->gateway_id)->update($data);
            flash()->success('SMS Gateway settings updated successfully!');
        } else {
            $data['created_at'] = now();
            $this->gateway_id = DB::table('isp_sms_gateways')->insertGetId($data);
            flash()->success('SMS Gateway created successfully!');
        }
    }

    public function sendTestSms()
    {
        if (empty($this->test_phone)) {
            $this->test_result = ['status' => 'error', 'message' => 'Please provide a recipient phone number.'];
            return;
        }

        $tenantId = $this->resolveTenantId();

        $success = IspSmsDispatcher::sendDirectSms($this->test_phone, $this->test_message, $tenantId);

        if ($success) {
            $this->test_result = ['status' => 'success', 'message' => "Test SMS sent successfully to {$this->test_phone}!"];
        } else {
            $this->test_result = ['status' => 'error', 'message' => "Failed to send SMS. Verify gateway credentials and balance."];
        }
    }

    public function sendTestEmail()
    {
        if (empty($this->test_email_recipient)) {
            $this->test_email_result = ['status' => 'error', 'message' => 'Please enter a test email recipient.'];
            return;
        }

        try {
            Mail::raw('This is a test email sent from HSmart ISP Billing platform to verify mail delivery.', function ($m) {
                $m->to($this->test_email_recipient)->subject('HSmart ISP Test Email');
            });
            $this->test_email_result = ['status' => 'success', 'message' => "Test email dispatched to {$this->test_email_recipient}!"];
        } catch (\Throwable $e) {
            $this->test_email_result = ['status' => 'error', 'message' => 'Email delivery failed: ' . $e->getMessage()];
        }
    }

    public function render()
    {
        $tenantId = $this->resolveTenantId();

        $gateways = DB::table('isp_sms_gateways')->where('tenant_id', $tenantId)->get();
        $logs = DB::table('isp_notification_logs')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate(15);

        $walletBalance = IspSmsDispatcher::getTenantCreditBalance($tenantId);
        $totalSent = DB::table('isp_notification_logs')->where('tenant_id', $tenantId)->where('status', 'sent')->count();
        $totalFailed = DB::table('isp_notification_logs')->where('tenant_id', $tenantId)->where('status', 'failed')->count();

        // Platform carrier balance
        $carrierInfo = IspSmsDispatcher::checkSystemGatewayBalance();
        $carrierCredits = $carrierInfo['credits'] ?? '332.00';

        return view('livewire.admin.sms-settings-manager', compact('gateways', 'logs', 'walletBalance', 'carrierCredits', 'totalSent', 'totalFailed'))->layout('layouts.app');
    }
}

