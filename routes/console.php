<?php

use App\Jobs\MarkMissedVisitSchedules;
use App\Jobs\SendItemFulfillmentReminder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new MarkMissedVisitSchedules)->dailyAt('00:00');
Schedule::job(new SendItemFulfillmentReminder)->dailyAt('07:00');
