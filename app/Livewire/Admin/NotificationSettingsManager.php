<?php

namespace App\Livewire\Admin;

use App\Services\Notification\IspNotificationService;
use App\Services\Sms\IspSmsDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class NotificationSettingsManager extends Component
{
    // Alert Recipient Settings (ISP Owner)
    public string $owner_phone = '';
    public string $owner_email = '';
    public string $owner_whatsapp = '';

    // Events Matrix: [ event_key => [ 'sms' => bool, 'email' => bool, 'whatsapp' => bool ] ]
    public array $events = [];

    // Test alert modal/trigger
    public string $test_event = 'router_offline_alert';
    public string $test_channel = 'sms';
    public ?string $test_result = null;
    public string $test_status_type = 'success';

    // Active tab in settings
    public string $activeTab = 'network'; // 'network', 'customers', 'templates', 'logs'

    protected function resolveTenantId(): int
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?: (session('tenant_id') ?: session('impersonated_tenant_id'));
        if (!$tenantId || !DB::table('tenants')->where('id', $tenantId)->exists()) {
            $tenantId = DB::table('tenants')->where('is_active', 1)->value('id')
                     ?: DB::table('tenants')->value('id')
                     ?: 1;
        }
        return (int) $tenantId;
    }

    public function mount()
    {
        $tenantId = $this->resolveTenantId();
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $adminUser = DB::table('users')->where('tenant_id', $tenantId)->whereIn('role', ['admin', 'superadmin'])->first();

        $settings = !empty($tenant?->settings)
            ? (is_string($tenant->settings) ? json_decode($tenant->settings, true) : (array)$tenant->settings)
            : [];
        $notif = $settings['notifications'] ?? [];

        // Load contact info
        $this->owner_phone = $notif['owner_phone'] ?? ($adminUser?->phone ?: ($adminUser?->mobile ?: ''));
        $this->owner_email = $notif['owner_email'] ?? ($adminUser?->email ?: '');
        $this->owner_whatsapp = $notif['owner_whatsapp'] ?? ($this->owner_phone ?: '');

        // Initialize event toggles
        $defaultTemplates = IspNotificationService::getDefaultEventTemplates();
        $savedEvents = $notif['events'] ?? [];

        foreach ($defaultTemplates as $key => $meta) {
            $this->events[$key] = [
                'sms'      => isset($savedEvents[$key]['sms']) ? (bool)$savedEvents[$key]['sms'] : ($meta['sms_enabled'] ?? false),
                'email'    => isset($savedEvents[$key]['email']) ? (bool)$savedEvents[$key]['email'] : ($meta['email_enabled'] ?? false),
                'whatsapp' => isset($savedEvents[$key]['whatsapp']) ? (bool)$savedEvents[$key]['whatsapp'] : ($meta['whatsapp_enabled'] ?? false),
            ];
        }
    }

    public function setTab(string $tab)
    {
        $this->activeTab = in_array($tab, ['network', 'customers', 'templates', 'logs']) ? $tab : 'network';
    }

    public function toggleChannelAll(string $channel, bool $state)
    {
        if (!in_array($channel, ['sms', 'email', 'whatsapp'])) {
            return;
        }

        foreach ($this->events as $key => $val) {
            $this->events[$key][$channel] = $state;
        }
    }

    public function saveSettings()
    {
        $tenantId = $this->resolveTenantId();
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        $settings = !empty($tenant?->settings)
            ? (is_string($tenant->settings) ? json_decode($tenant->settings, true) : (array)$tenant->settings)
            : [];

        $settings['notifications'] = [
            'owner_phone'    => trim($this->owner_phone),
            'owner_email'    => trim($this->owner_email),
            'owner_whatsapp' => trim($this->owner_whatsapp),
            'events'         => $this->events,
            'updated_at'     => now()->toDateTimeString(),
        ];

        DB::table('tenants')->where('id', $tenantId)->update([
            'settings'   => json_encode($settings),
            'updated_at' => now(),
        ]);

        session()->flash('success', 'Notification preferences saved successfully. Alert rules are now active.');
    }

    public function sendTestAlert()
    {
        $tenantId = $this->resolveTenantId();
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $ispName = $tenant?->name ?: 'HSmart ISP';

        $phone = trim($this->owner_phone);
        $email = trim($this->owner_email);
        $whatsapp = trim($this->owner_whatsapp ?: $this->owner_phone);

        $testData = [
            'router'          => 'Main Gateway (TEST)',
            'location'        => 'HQ Tower',
            'downtime'        => now()->format('H:i, d M'),
            'uptime_restored' => now()->format('H:i, d M'),
            'active_users'    => 24,
            'isp_name'        => $ispName,
            'name'            => 'John Doe (Test)',
            'username'        => 'test_user',
            'password'        => 'pass1234',
            'package'         => '10 Mbps Fiber (30 Days)',
            'amount'          => '1,500',
            'expiry'          => now()->addDays(30)->format('d M Y'),
            'expiry_time'     => '18:00',
            'trx_id'          => 'TEST' . rand(1000, 9999),
            'paybill'         => '4082218',
            'support_phone'   => $phone ?: '0700000000',
        ];

        $targetPhone = ($this->test_channel === 'sms') ? $phone : null;
        $targetEmail = ($this->test_channel === 'email') ? $email : null;
        $targetWa = ($this->test_channel === 'whatsapp') ? $whatsapp : null;

        if ($this->test_channel === 'sms' && empty($phone)) {
            $this->test_result = 'Owner Phone Number is missing. Please set your mobile phone above.';
            $this->test_status_type = 'danger';
            return;
        }

        if ($this->test_channel === 'email' && empty($email)) {
            $this->test_result = 'Owner Email Address is missing. Please set your email above.';
            $this->test_status_type = 'danger';
            return;
        }

        if ($this->test_channel === 'whatsapp' && empty($whatsapp)) {
            $this->test_result = 'Owner WhatsApp Number is missing. Please set your WhatsApp number above.';
            $this->test_status_type = 'danger';
            return;
        }

        $res = IspNotificationService::notify(
            $tenantId,
            $this->test_event,
            $testData,
            $targetPhone,
            $targetEmail,
            $targetWa
        );

        $channelSent = $res[$this->test_channel] ?? false;

        if ($channelSent) {
            $dest = ($this->test_channel === 'sms') ? $phone : (($this->test_channel === 'email') ? $email : $whatsapp);
            $this->test_result = "Test notification dispatched successfully via " . strtoupper($this->test_channel) . " to {$dest}.";
            $this->test_status_type = 'success';
        } else {
            $this->test_result = "Failed to send " . strtoupper($this->test_channel) . ". Please verify gateway connectivity or SMS wallet credits.";
            $this->test_status_type = 'warning';
        }
    }

    public function render()
    {
        $tenantId = $this->resolveTenantId();
        $defaultTemplates = IspNotificationService::getDefaultEventTemplates();

        // Categorize events into groups
        $networkEvents = [];
        $customerEvents = [];

        foreach ($defaultTemplates as $key => $meta) {
            if (($meta['recipient_type'] ?? '') === 'admin' || str_contains($key, 'router')) {
                $networkEvents[$key] = $meta;
            } else {
                $customerEvents[$key] = $meta;
            }
        }

        // Recent Notification Logs
        $recentLogs = DB::table('isp_notification_logs')
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('livewire.admin.notification-settings-manager', [
            'networkEvents'    => $networkEvents,
            'customerEvents'   => $customerEvents,
            'defaultTemplates' => $defaultTemplates,
            'recentLogs'       => $recentLogs,
        ])->layout('layouts.app');
    }
}
