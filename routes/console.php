<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('donations:reconcile')->everyFifteenMinutes();
Schedule::command('donations:nudge-stale-pending')->everyFiveMinutes();
Schedule::command('analytics:rollup')->everyFifteenMinutes();
Schedule::command('analytics:rollup --yesterday')->dailyAt('00:15');
Schedule::command('donors:send-birthday-whatsapp')->dailyAt('09:00');
Schedule::command('donations:email-daily-report')->dailyAt('01:00');
Schedule::command('danamojo:sync')->everyTwoMinutes();
Schedule::command('aisensy:sync-templates')->hourly();
Schedule::command('meta:sync-ad-spend --sync')->everyTwoHours();
Schedule::command('geoip:update --force')->monthlyOn(3, '04:15');
