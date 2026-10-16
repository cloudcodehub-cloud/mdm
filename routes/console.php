<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mdm:dispatch-shift-reminders')->everyMinute();
Schedule::command('mdm:dispatch-active-visit-monitors')->everyMinute();
Schedule::command('mdm:dispatch-care-plan-review-warnings')->dailyAt('06:15');
Schedule::command('mdm:dispatch-compliance-expiration-reminders')->dailyAt('06:30');
Schedule::command('mdm:dispatch-compliance-staffing-sync')->dailyAt('06:35');
