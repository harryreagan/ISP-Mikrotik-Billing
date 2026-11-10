<?php

namespace App\Console\Commands;

use App\Http\Controllers\ScheduledTasksController;
use App\Jobs\PollRouterTelemetryJob;
use App\Models\CustomersInfo;
use App\Services\Notification\IspNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessIspLifecycleAutomations extends Command
{
    protected $signature = 'isp:lifecycle-automations';
    protected $description = 'Master scheduled daemon for ISP Billing: router polling, subscriber expiry, voucher cleanups and notification reminders';

    public function handle(): int
    {
        $this->info("Starting ISP Lifecycle Automations at " . now()->toIso8601String());

        // 1. Poll Router Telemetry & Health Alerts
        try {
            PollRouterTelemetryJob::dispatchSync();
            $this->line("  [1/4] Router telemetry polled and alert states refreshed.");
        } catch (\Throwable $e) {
            Log::warning("[Lifecycle] Router polling error: " . $e->getMessage());
        }

        // 2. Hotspot Realtime Voucher Expiry & Kicking
        try {
            Artisan::call('app:expire-realtime-hotspot-users');
            $this->line("  [2/4] Hotspot realtime vouchers expired and sessions kicked.");
        } catch (\Throwable $e) {
            Log::warning("[Lifecycle] Hotspot voucher expiry error: " . $e->getMessage());
        }

        // 3. PPPoE & Static Customer Expiry & MikroTik Disable
        try {
            ScheduledTasksController::autoDisableUser();
            $this->line("  [3/4] PPPoE & static subscriber expiry checked and enforced.");
        } catch (\Throwable $e) {
            Log::warning("[Lifecycle] PPPoE auto-disable error: " . $e->getMessage());
        }

        // 4. Send Expiry Reminders (3 days, 1 day, today)
        try {
            $this->sendExpiryReminders();
            $this->line("  [4/4] Subscriber renewal reminders evaluated and dispatched.");
        } catch (\Throwable $e) {
            Log::warning("[Lifecycle] Expiry reminder error: " . $e->getMessage());
        }

        $this->info("Lifecycle automation run finished successfully.");
        return 0;
    }

    protected function sendExpiryReminders(): void
    {
        // 3 days reminder
        $in3Days = Carbon::now()->addDays(3)->toDateString();
        $customers3d = CustomersInfo::whereHas('billing', fn($q) => $q->where('auto_disable_date', $in3Days))
            ->where('status', 'active')
            ->get();

        foreach ($customers3d as $c) {
            $this->dispatchReminder($c, 'package_expiring_3days', $in3Days);
        }

        // 1 day reminder
        $tomorrow = Carbon::now()->addDay()->toDateString();
        $customers1d = CustomersInfo::whereHas('billing', fn($q) => $q->where('auto_disable_date', $tomorrow))
            ->where('status', 'active')
            ->get();

        foreach ($customers1d as $c) {
            $this->dispatchReminder($c, 'package_expiring_1day', $tomorrow);
        }

        // Today reminder
        $today = Carbon::now()->toDateString();
        $customersToday = CustomersInfo::whereHas('billing', fn($q) => $q->where('auto_disable_date', $today))
            ->where('status', 'active')
            ->get();

        foreach ($customersToday as $c) {
            $this->dispatchReminder($c, 'package_expiring_today', $today);
        }
    }

    protected function dispatchReminder(CustomersInfo $c, string $eventKey, string $expiryDate): void
    {
        $pkg = $c->package;
        $phone = $c->customer_phone ?: ($c->official->mobile_primary ?? null);
        $amount = (float) ($c->billing->monthly_rent ?? 0);
        $tenantId = $c->tenant_id ?: 1;

        IspNotificationService::notify(
            $tenantId,
            $eventKey,
            [
                'name'        => $c->customer_name,
                'package'     => $pkg?->package ?: 'Internet Package',
                'amount'      => $amount,
                'expiry'      => $expiryDate,
                'expiry_time' => '23:59',
                'username'    => $c->pppUser?->username ?: $c->customer_unique_id,
                'paybill'     => '400200',
                'isp_name'    => 'HSmart ISP',
            ],
            $phone,
            $c->customer_email
        );
    }
}

