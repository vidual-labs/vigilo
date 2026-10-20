<?php

use Illuminate\Support\Facades\Schedule;

/*
| The scheduler container runs `php artisan schedule:work`. Without it,
| nothing recurring happens — no daily run, no digest, no retention.
*/

Schedule::command('vigilo:run --trigger=schedule')
    ->dailyAt((string) config('vigilo.schedule.daily_run_at', '06:00'))
    ->timezone((string) config('vigilo.timezone', 'Europe/Berlin'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('vigilo:prune')->dailyAt('03:30')->timezone((string) config('vigilo.timezone', 'Europe/Berlin'));
Schedule::command('queue:prune-batches --hours=72')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();

// Heartbeat for the scheduler container's Docker healthcheck.
Schedule::call(fn () => touch(storage_path('framework/scheduler-heartbeat')))
    ->everyMinute()
    ->name('vigilo:heartbeat');
