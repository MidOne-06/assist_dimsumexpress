<?php

use App\Console\Commands\PurgeExpiredQrTokens;
use App\Console\Commands\DetectarIncidenciasMarcacion;
use Illuminate\Support\Facades\Schedule;

Schedule::command(PurgeExpiredQrTokens::class, ['--hours' => 24])
    ->hourly()
    ->withoutOverlapping();

Schedule::command(DetectarIncidenciasMarcacion::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();
