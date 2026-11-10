<?php

namespace App\Services\Notification;

use App\Services\Sms\IspSmsDispatcher;
use Illuminate\Support\Facades\DB;
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
            'welcome' => [
                'title'          => 'Welcome to High-Speed Internet',
                'sms_template'   => 'Dear {name}, welcome to {isp_name}! Your account is active. Username: {username}, Password: {password}. Support: {support_phone}.',
                'email_subject'  => 'Welcome to {isp_name} - High-Speed Internet Service',
                'email_template' => '<h2>Welcome, {name}!</h2><p>Your subscription is active with package: <strong>{package}</strong>.</p><p>Account Details:<br>Username: <strong>{username}</strong><br>Expiry: <strong>{expiry}</strong></p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'payment_received' => [
                'title'          => 'Payment Received & Service Renewed',
                'sms_template'   => 'Confirmed! Received KES {amount} for {package}. Ref: {trx_id}. Account {username} is now active until {expiry}. Thank you for choosing {isp_name}!',
                'email_subject'  => 'Payment Confirmation - Receipt #{trx_id}',
                'email_template' => '<h2>Payment Received</h2><p>Dear {name},</p><p>We received your payment of <strong>KES {amount}</strong> via M-Pesa ({trx_id}). Your service has been renewed until <strong>{expiry}</strong>.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'invoice_created' => [
                'title'          => 'New Invoice Generated',
                'sms_template'   => 'Dear {name}, invoice #{invoice_no} for KES {amount} is ready. Due date: {due_date}. Pay via Paybill: {paybill}, Acc: {account_no}.',
                'email_subject'  => 'New Invoice #{invoice_no} from {isp_name}',
                'email_template' => '<h2>Invoice #{invoice_no}</h2><p>Dear {name}, your monthly bill of <strong>KES {amount}</strong> is due on <strong>{due_date}</strong>.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'package_expiring_3days' => [
                'title'          => 'Package Expiring in 3 Days',
                'sms_template'   => 'Notice: Dear {name}, your internet subscription {package} will expire in 3 days on {expiry}. Pay KES {amount} to Paybill {paybill}, Acc: {username} to avoid interruption.',
                'email_subject'  => 'Reminder: Subscription Expiring Soon',
                'email_template' => '<p>Dear {name}, your package <strong>{package}</strong> expires on <strong>{expiry}</strong>. Please renew early.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'package_expiring_1day' => [
                'title'          => 'Package Expiring Tomorrow',
                'sms_template'   => 'Urgent: Your {isp_name} internet package expires tomorrow, {expiry}. Renew today to prevent service interruption. Amount: KES {amount}.',
                'email_subject'  => 'Urgent: Internet Package Expires Tomorrow',
                'email_template' => '<p>Dear {name}, your subscription expires tomorrow, <strong>{expiry}</strong>.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'package_expiring_today' => [
                'title'          => 'Package Expiring Today',
                'sms_template'   => 'Final Notice: Your internet package expires TODAY at {expiry_time}. Pay KES {amount} to Paybill {paybill}, Acc: {username} now for uninterrupted connection.',
                'email_subject'  => 'Final Notice: Internet Package Expires Today',
                'email_template' => '<p>Dear {name}, your service expires today.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'package_expired' => [
                'title'          => 'Subscription Expired',
                'sms_template'   => 'Dear {name}, your {isp_name} internet subscription has expired. Connection paused. Renew now via Paybill {paybill}, Acc: {username} for instant reconnection.',
                'email_subject'  => 'Subscription Expired - Immediate Renewal Required',
                'email_template' => '<p>Dear {name}, your package has expired and service has been paused. Please renew to restore connection.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'package_suspended' => [
                'title'          => 'Account Suspended',
                'sms_template'   => 'Notice: Your {isp_name} account {username} is suspended. Outstanding balance: KES {balance}. Please contact {support_phone}.',
                'email_subject'  => 'Account Suspended Notice',
                'email_template' => '<p>Your account is temporarily suspended.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'package_reactivated' => [
                'title'          => 'Service Reactivated',
                'sms_template'   => 'Good news! Your {isp_name} internet service for account {username} has been reactivated. Enjoy your high-speed connection!',
                'email_subject'  => 'Service Reactivated - {isp_name}',
                'email_template' => '<p>Your connection has been restored successfully.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'router_offline_alert' => [
                'title'          => 'Router Offline Alert (Admin)',
                'sms_template'   => 'ALERT: Router {router} is OFFLINE since {downtime}. Affected subscribers: {active_users}. Please check power and backhaul immediately.',
                'email_subject'  => 'CRITICAL: Router {router} is Offline',
                'email_template' => '<p>Alert: Router <strong>{router}</strong> went offline at {downtime}.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'password_reset' => [
                'title'          => 'Password Reset OTP',
                'sms_template'   => 'Your password reset code is {code}. Valid for 5 minutes. If you did not request this, contact support immediately.',
                'email_subject'  => 'Password Reset Request',
                'email_template' => '<p>Use verification code <strong>{code}</strong> to reset your password.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
            'otp_verification' => [
                'title'          => 'Sign-In OTP Verification',
                'sms_template'   => 'Your HSmart sign-in OTP is {code}. Valid for 5 minutes. Do not share this code.',
                'email_subject'  => 'Your Sign-In OTP Code',
                'email_template' => '<p>Your one-time login code is <strong>{code}</strong>.</p>',
                'sms_enabled'    => true,
                'email_enabled'  => true,
                'in_app_enabled' => true,
            ],
        ];
    }

    /**
     * Dispatch an event notification using the ISP's customized or default templates.
     */
    public static function notify(int $tenantId, string $eventKey, array $data, ?string $phone = null, ?string $email = null): array
    {
        // 1. Fetch template for this tenant, or fall back to default
        $template = DB::table('isp_notification_templates')
            ->where('tenant_id', $tenantId)
            ->where('event_key', $eventKey)
            ->first();

        $defaults = self::getDefaultEventTemplates()[$eventKey] ?? null;

        $smsEnabled = $template ? (bool)$template->sms_enabled : ($defaults['sms_enabled'] ?? false);
        $emailEnabled = $template ? (bool)$template->email_enabled : ($defaults['email_enabled'] ?? false);
        $smsBody = $template?->sms_template ?: ($defaults['sms_template'] ?? '');
        $emailBody = $template?->email_template ?: ($defaults['email_template'] ?? '');
        $emailSubject = $template?->email_subject ?: ($defaults['email_subject'] ?? 'Notification from ISP');

        // Replace placeholders
        $replacements = [];
        foreach ($data as $k => $v) {
            $replacements['{' . $k . '}'] = (string) $v;
        }

        $finalSms = str_replace(array_keys($replacements), array_values($replacements), $smsBody);
        $finalEmail = str_replace(array_keys($replacements), array_values($replacements), $emailBody);
        $finalSubject = str_replace(array_keys($replacements), array_values($replacements), $emailSubject);

        $results = ['sms' => false, 'email' => false];

        // 2. Dispatch SMS
        if ($smsEnabled && !empty($phone) && !empty($finalSms)) {
            $results['sms'] = IspSmsDispatcher::sendDirectSms($phone, $finalSms, $tenantId);
        }

        // 3. Dispatch Email
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

        return $results;
    }
}

