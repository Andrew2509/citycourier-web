<?php

use App\Modules\Payment\Jobs\ReconciliationJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('finance:run-auto-payouts')
    ->hourly()
    ->withoutOverlapping();

Schedule::job(new ReconciliationJob())
    ->dailyAt('02:00')
    ->withoutOverlapping();
