<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run')->dailyAt('00:00');
Schedule::command('sales:check-daily')->dailyAt('23:30')->timezone('Asia/Kuala_Lumpur');
Schedule::command('myinvois:push-queue')->hourly()->timezone('Asia/Kuala_Lumpur');