<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled tasks
Schedule::command('audit:verify-chain')->dailyAt('02:00');
Schedule::command('pha:fetch-bsp-fx')->weekdays()->at('09:00');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('backup:run')->dailyAt('01:00');
Schedule::command('telescope:prune --hours=72')->daily();
