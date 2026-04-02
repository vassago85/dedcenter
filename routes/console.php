<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('matches:send-reminders')->dailyAt('18:00');
Schedule::command('matches:auto-close-past-date')->dailyAt('00:05');
Schedule::command('matches:auto-close-registration')->hourly();
