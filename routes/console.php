<?php

use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\ScheduledTasksController;
use App\Livewire\MikrotikSync;
use App\Models\MainSiteData;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

$timezone = config('app.timezone') ?: 'Africa/Nairobi';

Schedule::call(function () {
    app(ScheduledTasksController::class)->createMonthlyBill();
})->name('create-monthly-bill')->lastDayOfMonth('23:45')->timezone($timezone)->withoutOverlapping();

Schedule::call(function () {
    app(ScheduledTasksController::class)->allCustomersMonthlyBillSMS();
})->name('monthly-bill-sms')->monthlyOn(1, '10:00')->timezone($timezone)->withoutOverlapping();

Schedule::call(function () {
    app(ScheduledTasksController::class)->createAlert();
})->name('create-alert')->dailyAt('08:00')->timezone($timezone)->withoutOverlapping();

Schedule::call(function () {
    app(ScheduledTasksController::class)->userDisable();
})->name('user-disable')->dailyAt('08:30')->timezone($timezone)->withoutOverlapping();

Schedule::call(function () {
    app(MikrotikSync::class)->allSync();
})->name('mikrotik-all-sync')->daily()->timezone($timezone)->withoutOverlapping();

Schedule::call(function () {
    Artisan::call('app:poll-router-logs');
})->name('poll-router-logs')->everyMinute()->withoutOverlapping();

Schedule::command('isp:lifecycle-automations')->everyMinute()->withoutOverlapping();

Schedule::call(function () {
    Artisan::call('app:expire-realtime-hotspot-users');
})->name('expire-realtime-hotspot-users')->everyMinute()->withoutOverlapping();

Schedule::call(function () {
    $days = (int) MainSiteData::getValue('log_retention_days', 30);
    app(MikrotikController::class)->pruneOldLogs($days);
})->name('prune-old-logs')->dailyAt('04:00')->timezone($timezone)->withoutOverlapping();

Schedule::command('currency:update-rates')->dailyAt('03:00')->timezone($timezone)->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->daily();

// ── HSmart PLATFORMS HUB CONNECTOR ──
Schedule::call(function () {
    if (config('hub.enabled') && config('hub.key')) {
        app(\App\Services\HubConnector\HubSyncService::class)->sendHeartbeat();
        app(\App\Services\HubConnector\HubSyncService::class)->sendOnlineSamples();
        app(\App\Services\HubConnector\HubSyncService::class)->pollCommands();
    }
})->name('hub-heartbeat-and-samples')->everyMinute()->withoutOverlapping();

Schedule::call(function () {
    if (config('hub.enabled') && config('hub.key')) {
        app(\App\Services\HubConnector\HubSyncService::class)->syncRouters();
    }
})->name('hub-router-sync')->everyFiveMinutes()->withoutOverlapping();

Schedule::call(function () {
    if (config('hub.enabled') && config('hub.key')) {
        app(\App\Services\HubConnector\HubSyncService::class)->sendDailyStats();
    }
})->name('hub-daily-stats')->dailyAt('23:55')->withoutOverlapping();


// Schedule::call(function () {
//     Log::info('Hello World', [
//         'time' => Carbon::now(),
//         'timezone' => config('app.timezone'),
//     ]);
// })->everyMinute();
