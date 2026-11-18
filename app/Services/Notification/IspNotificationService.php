<?php

namespace App\Services\Notification;

use App\Models\Router;
use App\Services\Auth\OtpVerificationService;
use App\Services\Sms\IspSmsDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class IspNotificationService
{
    /**
     * Standard notification events with default Kenyan ISP templates.
     */
    public static function getDefaultEventTemplates(): array
    {
        return [
            // ── NETWORK & ROUTER ALERTS (TO ISP OWNER) ──
            'router_offline_alert' => [
                'title'             => 'Router Offline Alert (Immediate)',
                'description'       => 'Triggered instantly when a MikroTik router becomes unreachable.',
                'recipient_type'    => 'admin',
                'sms_template'      => 'CRITICAL ALERT: Router {router} is OFFLINE since {downtime}. Affected subscribers: {active_users}. Please inspect power supply and network backhaul.',
                'email_subject'     => 'CRITICAL: Router {router} is Offline - {isp_name}',
                'email_template'    => '<h2>Critical Network Alert</h2><p>Router <strong>{router}</strong> went offline at <strong>{downtime}</strong>.</p><p>Location: <strong>{location}</strong><br>Affected subscribers: <strong>{active_users}</strong></p><p>Please inspect router power supply and upstream backhaul immediately.</p>',
                'whatsapp_template' => "🚨 *CRITICAL NETWORK ALERT*\nRouter *{router}* is *OFFLINE* since {downtime}.\n📍 Location: {location}\n👥 Affected subscribers: {active_users}\nPlease verify power and backhaul immediately.",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'router_offline_12h_reminder' => [
                'title'             => 'Router Offline 12-Hour Escalation',
                'description'       => 'Follow-up reminder sent to the ISP owner if the router remains offline after 12 hours.',
                'recipient_type'    => 'admin',
                'sms_template'      => 'URGENT REMINDER (12 Hours): Router {router} has been OFFLINE for over 12 hours since {downtime}. Urgent action required to restore subscriber internet.',
                'email_subject'     => 'URGENT: Router {router} Offline for 12+ Hours - {isp_name}',
                'email_template'    => '<h2>12-Hour Escalation Notice</h2><p>Router <strong>{router}</strong> has been continuously offline for more than 12 hours (since {downtime}).</p><p>Location: <strong>{location}</strong></p><p>Subscribers are experiencing extended downtime. Please take immediate corrective measures.</p>',
                'whatsapp_template' => "⚠️ *URGENT REMINDER (12h Escalation)*\nRouter *{router}* has been *OFFLINE for over 12 hours* (since {downtime}).\n📍 Location: {location}\nSubscriber services remain halted.",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'router_restored_alert' => [
                'title'             => 'Router Back Online (Recovery)',
                'description'       => 'Notification sent when a previously offline router recovers and reconnects.',
                'recipient_type'    => 'admin',
                'sms_template'      => 'RECOVERY: Router {router} is now back ONLINE at {uptime_restored}. Telemetry and subscriber sessions restored. Network is healthy.',
                'email_subject'     => 'RECOVERY: Router {router} Back Online - {isp_name}',
                'email_template'    => '<h2>Network Restored</h2><p>Router <strong>{router}</strong> is now back online at <strong>{uptime_restored}</strong>.</p><p>Subscribers are reconnecting and telemetry is operational.</p>',
                'whatsapp_template' => "✅ *NETWORK RESTORED*\nRouter *{router}* is back *ONLINE* as of {uptime_restored}.\nTelemetry and internet services have been restored.",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],

            // ── CUSTOMER LIFECYCLE NOTIFICATIONS (TO SUBSCRIBERS) ──
            'welcome' => [
                'title'             => 'New Customer Welcome',
                'description'       => 'Sent to new PPPoE or Hotspot customer upon registration.',
                'recipient_type'    => 'customer',
                'sms_template'      => 'Dear {name}, welcome to {isp_name}! Your {service_type} account is active. Username: {username}, Password: {password}. Support: {support_phone}.',
                'email_subject'     => 'Welcome to {isp_name} - High-Speed Internet Service',
                'email_template'    => '<h2>Welcome, {name}!</h2><p>Your subscription is active with package: <strong>{package}</strong>.</p><p>Account Details:<br>Username: <strong>{username}</strong><br>Password: <strong>{password}</strong><br>Expiry: <strong>{expiry}</strong></p>',
                'whatsapp_template' => "👋 *Welcome to {isp_name}, {name}!*\nYour account credentials:\nUsername: *{username}*\nPassword: *{password}*\nPackage: *{package}*\nSupport: {support_phone}",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'payment_received' => [
                'title'             => 'Payment Received & Service Renewed',
                'description'       => 'Sent immediately when a payment is matched and account renewed.',
                'recipient_type'    => 'customer',
                'sms_template'      => 'Confirmed! Received KES {amount} for {package}. Ref: {trx_id}. Account {username} is now active until {expiry}. Thank you for choosing {isp_name}!',
                'email_subject'     => 'Payment Confirmation - Receipt #{trx_id}',
                'email_template'    => '<h2>Payment Received</h2><p>Dear {name},</p><p>We received your payment of <strong>KES {amount}</strong> via M-Pesa ({trx_id}). Your service has been renewed until <strong>{expiry}</strong>.</p>',
                'whatsapp_template' => "💳 *Payment Confirmed!*\nReceived: *KES {amount}*\nPackage: *{package}*\nRef: *{trx_id}*\nExpiry: *{expiry}*\nThank you for choosing *{isp_name}*!",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'package_expiring_3days' => [
                'title'             => 'Package Expiring in 3 Days',
                'description'       => 'Gentle renewal reminder 3 days prior to expiration.',
                'recipient_type'    => 'customer',
                'sms_template'      => 'Notice: Dear {name}, your internet subscription {package} will expire in 3 days on {expiry}. Pay KES {amount} to Paybill {paybill}, Acc: {username} to avoid interruption.',
                'email_subject'     => 'Reminder: Subscription Expiring Soon',
                'email_template'    => '<p>Dear {name}, your package <strong>{package}</strong> expires on <strong>{expiry}</strong>. Please renew early to avoid interruption.</p>',
                'whatsapp_template' => "⏳ *Subscription Reminder:*\nDear {name}, your package *{package}* expires in 3 days on *{expiry}*.\nRenew via Paybill *{paybill}*, Acc: *{username}* (KES {amount}).",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'package_expiring_1day' => [
                'title'             => 'Package Expiring Tomorrow',
                'description'       => 'Urgent reminder 1 day prior to expiration.',
                'recipient_type'    => 'customer',
                'sms_template'      => 'Urgent: Your {isp_name} internet package expires tomorrow, {expiry}. Renew today to prevent service interruption. Amount: KES {amount}.',
                'email_subject'     => 'Urgent: Internet Package Expires Tomorrow',
                'email_template'    => '<p>Dear {name}, your subscription expires tomorrow, <strong>{expiry}</strong>. Amount due: KES {amount}.</p>',
                'whatsapp_template' => "⚠️ *Urgent Renewal Notice:*\nDear {name}, your internet expires *tomorrow, {expiry}*.\nPlease pay KES {amount} to Paybill *{paybill}*, Acc: *{username}* to stay connected.",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'package_expiring_today' => [
                'title'             => 'Package Expiring Today',
                'description'       => 'Final notice on the day of expiry before service pauses.',
                'recipient_type'    => 'customer',
                'sms_template'      => 'Final Notice: Your internet package expires TODAY at {expiry_time}. Pay KES {amount} to Paybill {paybill}, Acc: {username} now for uninterrupted connection.',
                'email_subject'     => 'Final Notice: Internet Package Expires Today',
                'email_template'    => '<p>Dear {name}, your service expires today. Please make payment to avoid disruption.</p>',
                'whatsapp_template' => "🚨 *Final Notice:* Your internet subscription expires *TODAY*.\nPay KES {amount} to Paybill *{paybill}*, Acc: *{username}* now.",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'package_expired' => [
                'title'             => 'Subscription Expired',
                'description'       => 'Sent when account expires and service is suspended.',
                'recipient_type'    => 'customer',
                'sms_template'      => 'Dear {name}, your {isp_name} internet subscription has expired. Connection paused. Renew now via Paybill {paybill}, Acc: {username} for instant reconnection.',
                'email_subject'     => 'Subscription Expired - Immediate Renewal Required',
                'email_template'    => '<p>Dear {name}, your package has expired and service has been paused. Please renew to restore connection.</p>',
                'whatsapp_template' => "🛑 *Subscription Expired:*\nDear {name}, your internet service is currently paused.\nPay via Paybill *{paybill}*, Acc: *{username}* for instant auto-reconnection.",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'package_suspended' => [
                'title'             => 'Account Suspended',
                'description'       => 'Notice sent when account is manually or automatically suspended.',
                'recipient_type'    => 'customer',
                'sms_template'      => 'Notice: Your {isp_name} account {username} is suspended. Outstanding balance: KES {balance}. Please contact {support_phone}.',
                'email_subject'     => 'Account Suspended Notice - {isp_name}',
                'email_template'    => '<p>Your account is temporarily suspended. Balance due: KES {balance}.</p>',
                'whatsapp_template' => "⛔ *Account Suspended:*\nYour {isp_name} account {username} has been suspended.\nBalance: KES {balance}. Contact {support_phone} for assistance.",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
            'package_reactivated' => [
                'title'             => 'Service Reactivated',
                'description'       => 'Sent when an account is unblocked / restored.',
                'recipient_type'    => 'customer',
                'sms_template'      => 'Good news! Your {isp_name} internet service for account {username} has been reactivated. Enjoy your high-speed connection!',
                'email_subject'     => 'Service Reactivated - {isp_name}',
                'email_template'    => '<p>Your connection has been restored successfully. Enjoy high-speed internet!</p>',
                'whatsapp_template' => "🎉 *Service Reactivated!*\nGood news! Your {isp_name} internet service has been restored. Enjoy your connection!",
                'sms_enabled'       => true,
                'email_enabled'     => true,
                'whatsapp_enabled'  => true,
                'in_app_enabled'    => true,
            ],
        ];
    }

    /**
     * Dispatch an event notification across SMS, Email, and WhatsApp.
     */
    public static function notify(
        int $tenantId,
        string $eventKey,
        array $data,
        ?string $phone = null,
        ?string $email = null,
        ?string $whatsapp = null
    ): array {
        // 1. Fetch template from DB or fallback to default
        $template = DB::table('isp_notification_templates')
            ->where('tenant_id', $tenantId)
            ->where('event_key', $eventKey)
            ->first();

        $defaults = self::getDefaultEventTemplates()[$eventKey] ?? null;

        // 2. Fetch tenant custom notification settings
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $settings = !empty($tenant?->settings)
            ? (is_string($tenant->settings) ? json_decode($tenant->settings, true) : (array)$tenant->settings)
            : [];
        $notifConfig = $settings['notifications'] ?? [];
        $eventOverrides = $notifConfig['events'][$eventKey] ?? null;

        // Check channel enablement (settings override > template > defaults)
        $smsEnabled = isset($eventOverrides['sms'])
            ? (bool)$eventOverrides['sms']
            : ($template ? (bool)$template->sms_enabled : ($defaults['sms_enabled'] ?? false));

        $emailEnabled = isset($eventOverrides['email'])
            ? (bool)$eventOverrides['email']
            : ($template ? (bool)$template->email_enabled : ($defaults['email_enabled'] ?? false));

        $whatsappEnabled = isset($eventOverrides['whatsapp'])
            ? (bool)$eventOverrides['whatsapp']
            : ($template && isset($template->whatsapp_enabled) ? (bool)$template->whatsapp_enabled : ($defaults['whatsapp_enabled'] ?? false));

        $smsBody = $template?->sms_template ?: ($defaults['sms_template'] ?? '');
        $emailBody = $template?->email_template ?: ($defaults['email_template'] ?? '');
        $emailSubject = $template?->email_subject ?: ($defaults['email_subject'] ?? 'Notification from ISP');
        $whatsappBody = ($template && !empty($template->whatsapp_template))
            ? $template->whatsapp_template
            : ($defaults['whatsapp_template'] ?? $smsBody);

        // Ensure defaults in data
        if (!isset($data['isp_name'])) {
            $data['isp_name'] = $tenant?->name ?: 'HSmart ISP';
        }
        if (!isset($data['support_phone'])) {
            $data['support_phone'] = $notifConfig['owner_phone'] ?? '0700000000';
        }

        // Replace placeholders
        $replacements = [];
        foreach ($data as $k => $v) {
            $replacements['{' . $k . '}'] = (string) $v;
        }

        $finalSms = str_replace(array_keys($replacements), array_values($replacements), $smsBody);
        $finalEmail = str_replace(array_keys($replacements), array_values($replacements), $emailBody);
        $finalSubject = str_replace(array_keys($replacements), array_values($replacements), $emailSubject);
        $finalWhatsapp = str_replace(array_keys($replacements), array_values($replacements), $whatsappBody);

        $results = ['sms' => false, 'email' => false, 'whatsapp' => false];

        // 3. Dispatch SMS
        if ($smsEnabled && !empty($phone) && !empty($finalSms)) {
            $results['sms'] = IspSmsDispatcher::sendDirectSms($phone, $finalSms, $tenantId);
        }

        // 4. Dispatch Email
        if ($emailEnabled && !empty($email) && !empty($finalEmail)) {
            try {
                Mail::html($finalEmail, function ($msg) use ($email, $finalSubject) {
                    $msg->to($email)->subject($finalSubject);
                });
                $results['email'] = true;

                DB::table('isp_notification_logs')->insert([
                    'tenant_id'         => $tenantId,
                    'event_key'         => $eventKey,
                    'channel'           => 'email',
                    'recipient'         => $email,
                    'subject'           => $finalSubject,
                    'content'           => $finalEmail,
                    'status'            => 'sent',
                    'provider'          => 'SMTP / System Mailer',
                    'sent_at'           => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error("[IspNotificationService] Email delivery failure: " . $e->getMessage());
            }
        }

        // 5. Dispatch WhatsApp
        if ($whatsappEnabled && !empty($whatsapp) && !empty($finalWhatsapp)) {
            $normalizedWa = OtpVerificationService::normalizeKenyanPhone($whatsapp) ?: $whatsapp;
            $waSent = false;
            $waProvider = 'System WhatsApp';
            $waResponse = null;

            // Look up custom WhatsApp webhook/gateway if configured
            $waGateway = DB::table('isp_sms_gateways')
                ->where('tenant_id', $tenantId)
                ->whereIn('gateway_type', ['whatsapp', 'ultramsg', 'chatapi'])
                ->first();

            if ($waGateway && !empty($waGateway->endpoint_url)) {
                try {
                    $res = Http::timeout(10)->post($waGateway->endpoint_url, [
                        'to'      => $normalizedWa,
                        'message' => $finalWhatsapp,
                    ]);
                    $waSent = $res->successful();
                    $waProvider = $waGateway->gateway_type;
                    $waResponse = $res->body();
                } catch (\Throwable $we) {
                    Log::warning("[IspNotificationService] WhatsApp webhook error: " . $we->getMessage());
                }
            } else {
                // If no dedicated WhatsApp API gateway, send via SMS gateway as fallback ensuring the owner gets it
                if (!$results['sms']) {
                    $waSent = IspSmsDispatcher::sendDirectSms($normalizedWa, $finalWhatsapp, $tenantId);
                    $waProvider = 'SMS Fallback (No WhatsApp Gateway configured)';
                } else {
                    $waSent = true;
                    $waProvider = 'WhatsApp (Logged / SMS dispatched)';
                }
            }

            $results['whatsapp'] = $waSent;

            try {
                DB::table('isp_notification_logs')->insert([
                    'tenant_id'         => $tenantId,
                    'event_key'         => $eventKey,
                    'channel'           => 'whatsapp',
                    'recipient'         => $normalizedWa,
                    'content'           => $finalWhatsapp,
                    'status'            => $waSent ? 'sent' : 'failed',
                    'provider'          => $waProvider,
                    'provider_response' => is_string($waResponse) ? $waResponse : json_encode($waResponse),
                    'cost'              => 0.00,
                    'sent_at'           => $waSent ? now() : null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            } catch (\Throwable $e) {}
        }

        return $results;
    }

    /**
     * Dispatch immediate alert to ISP owner when a router goes offline.
     */
    public static function notifyAdminRouterOffline(Router $router, int $activeUsersCount = 0): array
    {
        $tenantId = (int) ($router->tenant_id ?: 1);
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $settings = !empty($tenant?->settings)
            ? (is_string($tenant->settings) ? json_decode($tenant->settings, true) : (array)$tenant->settings)
            : [];
        $notif = $settings['notifications'] ?? [];

        $eventConfig = $notif['events']['router_offline_alert'] ?? ['sms' => true, 'email' => true, 'whatsapp' => true];
        if (empty($eventConfig['sms']) && empty($eventConfig['email']) && empty($eventConfig['whatsapp'])) {
            return ['skipped' => true, 'reason' => 'disabled_in_settings'];
        }

        $adminUser = DB::table('users')->where('tenant_id', $tenantId)->whereIn('role', ['admin', 'superadmin'])->first();
        $phone = $notif['owner_phone'] ?? ($adminUser?->phone ?: $adminUser?->mobile);
        $email = $notif['owner_email'] ?? $adminUser?->email;
        $whatsapp = $notif['owner_whatsapp'] ?? ($phone ?: null);

        $downtimeStr = $router->last_seen_at ? Carbon::parse($router->last_seen_at)->format('H:i, d M') : now()->format('H:i, d M');

        return self::notify(
            $tenantId,
            'router_offline_alert',
            [
                'router'       => $router->name ?: 'Gateway-' . $router->id,
                'location'     => $router->location ?: 'Main Tower',
                'downtime'     => $downtimeStr,
                'active_users' => $activeUsersCount,
                'isp_name'     => $tenant?->name ?: 'HSmart ISP',
            ],
            ($eventConfig['sms'] ?? true) ? $phone : null,
            ($eventConfig['email'] ?? true) ? $email : null,
            ($eventConfig['whatsapp'] ?? true) ? $whatsapp : null
        );
    }

    /**
     * Dispatch 12-hour reminder to ISP owner if router remains offline.
     */
    public static function notifyAdminRouterOffline12hReminder(Router $router, string $downtimeStart): array
    {
        $tenantId = (int) ($router->tenant_id ?: 1);
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $settings = !empty($tenant?->settings)
            ? (is_string($tenant->settings) ? json_decode($tenant->settings, true) : (array)$tenant->settings)
            : [];
        $notif = $settings['notifications'] ?? [];

        $eventConfig = $notif['events']['router_offline_12h_reminder'] ?? ['sms' => true, 'email' => true, 'whatsapp' => true];
        if (empty($eventConfig['sms']) && empty($eventConfig['email']) && empty($eventConfig['whatsapp'])) {
            return ['skipped' => true, 'reason' => 'disabled_in_settings'];
        }

        $adminUser = DB::table('users')->where('tenant_id', $tenantId)->whereIn('role', ['admin', 'superadmin'])->first();
        $phone = $notif['owner_phone'] ?? ($adminUser?->phone ?: $adminUser?->mobile);
        $email = $notif['owner_email'] ?? $adminUser?->email;
        $whatsapp = $notif['owner_whatsapp'] ?? ($phone ?: null);

        return self::notify(
            $tenantId,
            'router_offline_12h_reminder',
            [
                'router'       => $router->name ?: 'Gateway-' . $router->id,
                'location'     => $router->location ?: 'Main Tower',
                'downtime'     => $downtimeStart,
                'isp_name'     => $tenant?->name ?: 'HSmart ISP',
            ],
            ($eventConfig['sms'] ?? true) ? $phone : null,
            ($eventConfig['email'] ?? true) ? $email : null,
            ($eventConfig['whatsapp'] ?? true) ? $whatsapp : null
        );
    }

    /**
     * Dispatch recovery alert to ISP owner when router comes back online.
     */
    public static function notifyAdminRouterRestored(Router $router): array
    {
        $tenantId = (int) ($router->tenant_id ?: 1);
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $settings = !empty($tenant?->settings)
            ? (is_string($tenant->settings) ? json_decode($tenant->settings, true) : (array)$tenant->settings)
            : [];
        $notif = $settings['notifications'] ?? [];

        $eventConfig = $notif['events']['router_restored_alert'] ?? ['sms' => true, 'email' => true, 'whatsapp' => true];
        if (empty($eventConfig['sms']) && empty($eventConfig['email']) && empty($eventConfig['whatsapp'])) {
            return ['skipped' => true, 'reason' => 'disabled_in_settings'];
        }

        $adminUser = DB::table('users')->where('tenant_id', $tenantId)->whereIn('role', ['admin', 'superadmin'])->first();
        $phone = $notif['owner_phone'] ?? ($adminUser?->phone ?: $adminUser?->mobile);
        $email = $notif['owner_email'] ?? $adminUser?->email;
        $whatsapp = $notif['owner_whatsapp'] ?? ($phone ?: null);

        return self::notify(
            $tenantId,
            'router_restored_alert',
            [
                'router'          => $router->name ?: 'Gateway-' . $router->id,
                'uptime_restored' => now()->format('H:i, d M'),
                'isp_name'        => $tenant?->name ?: 'HSmart ISP',
            ],
            ($eventConfig['sms'] ?? true) ? $phone : null,
            ($eventConfig['email'] ?? true) ? $email : null,
            ($eventConfig['whatsapp'] ?? true) ? $whatsapp : null
        );
    }
}
