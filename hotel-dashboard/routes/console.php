<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic prediction generation
// Run on the 1st day of each month at midnight
Schedule::command('predictions:generate --clear --months=3')
    ->monthlyOn(1, '00:00')
    ->timezone('Asia/Jakarta')
    ->emailOutputOnFailure(env('MANAGER_EMAIL', 'manager@dharmahotel.com'));

Schedule::command('predictions:generate-single --clear --months=3')
    ->monthlyOn(1, '00:05')
    ->timezone('Asia/Jakarta')
    ->emailOutputOnFailure(env('MANAGER_EMAIL', 'manager@dharmahotel.com'));
