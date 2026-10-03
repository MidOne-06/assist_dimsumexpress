<?php

use App\Console\Commands\PurgeExpiredQrTokens;
use App\Console\Commands\DetectarIncidenciasMarcacion;
use App\Console\Commands\ConsolidarJornadas;
use App\Services\SchedulerHeartbeat;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => app(SchedulerHeartbeat::class)->touch())
    ->name('asistencia-scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(PurgeExpiredQrTokens::class, ['--hours' => 24])
    ->hourly()
    ->withoutOverlapping();

Schedule::command(DetectarIncidenciasMarcacion::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(ConsolidarJornadas::class)
    ->hourly()
    ->withoutOverlapping();
