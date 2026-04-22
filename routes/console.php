<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$scheduleTimezone = (string) config('automation.schedule.timezone', 'UTC');

Schedule::command('billing:generate-monthly-invoices --queue')
    ->monthlyOn(1, config('automation.schedule.generate_monthly_invoices_at', '00:05'))
    ->timezone($scheduleTimezone)
    ->name('billing.generate-monthly-invoices')
    ->withoutOverlapping(60);

Schedule::command('billing:check-overdue-invoices --queue')
    ->dailyAt(config('automation.schedule.check_overdue_at', '00:20'))
    ->timezone($scheduleTimezone)
    ->name('billing.check-overdue-invoices')
    ->withoutOverlapping(60);

Schedule::command('billing:create-overdue-isolations --queue')
    ->dailyAt(config('automation.schedule.create_overdue_isolations_at', '00:30'))
    ->timezone($scheduleTimezone)
    ->name('billing.create-overdue-isolations')
    ->withoutOverlapping(60);

Schedule::command('mikrotik:sync-vids --queue')
    ->cron(config('automation.schedule.sync_mikrotik_vid_cron', '*/15 * * * *'))
    ->timezone($scheduleTimezone)
    ->name('network.sync-mikrotik-vid')
    ->withoutOverlapping(14);

Schedule::command('monitor:sync-pppoe --queue')
    ->cron(config('automation.schedule.sync_pppoe_cron', '* * * * *'))
    ->timezone($scheduleTimezone)
    ->name('monitoring.sync-pppoe')
    ->withoutOverlapping(1);

Schedule::command('genieacs:sync-onts --queue')
    ->cron(config('automation.schedule.sync_genieacs_cron', '*/5 * * * *'))
    ->timezone($scheduleTimezone)
    ->name('monitoring.sync-genieacs-onts')
    ->withoutOverlapping(10);

Schedule::command('notifications:process-telegram --queue')
    ->cron(config('automation.schedule.process_telegram_cron', '* * * * *'))
    ->timezone($scheduleTimezone)
    ->name('notifications.process-telegram')
    ->withoutOverlapping(5);
