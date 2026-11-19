<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// BSB only reports today's messages: check every few minutes so none are missed.
// Needs `php artisan schedule:run` every minute (cron, or Windows Task Scheduler).
Schedule::command('bsb:poll')
    ->cron('*/' . max(1, min(59, (int) config('bsb.poll_minutes'))) . ' * * * *')
    ->withoutOverlapping(30);
