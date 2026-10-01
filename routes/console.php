<?php

use App\Console\Commands\PurgeExpiredQrTokens;
use App\Console\Commands\DetectarIncidenciasMarcacion;
use App\Console\Commands\ConsolidarJornadas;
use Illuminate\Support\Facades\Schedule;

Schedule::command(PurgeExpiredQrTokens::class, ['--hours' => 24])
    ->hourly()
    ->withoutOverlapping();

Schedule::command(DetectarIncidenciasMarcacion::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(ConsolidarJornadas::class)
    ->hourly()
    ->withoutOverlapping();
