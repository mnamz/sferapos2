<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run')->dailyAt('00:00');
Schedule::command('sales:check-daily')->dailyAt('23:30')->timezone('Asia/Kuala_Lumpur');
// Pull + apply HQ stock-transfer commands (no-op unless STOCK_HQ_TRANSFERS is on).
Schedule::command('stock:transfers-poll')->everyMinute()->withoutOverlapping();
// Hourly: re-sync on-hand stock to HQ (restocks/adjustments of non-serial items
// only reach HQ this way), so a same-day restock can be transferred out within the
// hour. A sale still in the HQ queue during a run can be counted twice; the next
// run corrects it. Off the hour to avoid the :00 pushes. No-op unless STOCK_HQ_ENABLED.
Schedule::command('stock:snapshot')->hourlyAt(7)->timezone('Asia/Kuala_Lumpur')->withoutOverlapping();
Schedule::command('myinvois:push-queue')->hourly()->timezone('Asia/Kuala_Lumpur');
Schedule::command('tangent:push-sales')->hourly()->timezone('Asia/Kuala_Lumpur');
// Daily belt-and-suspenders: force a full re-send of the past-7-days window
// (the vendor requires a daily upload covering the last 7 days).
Schedule::command('tangent:push-sales --force')->dailyAt('23:45')->timezone('Asia/Kuala_Lumpur');
