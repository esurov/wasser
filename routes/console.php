<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('photos:backup')
    ->dailyAt('03:00')
    ->onOneServer()
    ->runInBackground();

Schedule::command('vienna:refresh')
    ->dailyAt('02:30')
    ->onOneServer()
    ->runInBackground();
