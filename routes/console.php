<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FG-Delete — nightly purge of archived records past their 30-day window.
Schedule::command('model:purge-trashed')->dailyAt('02:00');
