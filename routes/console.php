<?php

use App\Console\Commands\PurgeExpiredQrTokens;
use Illuminate\Support\Facades\Schedule;

Schedule::command(PurgeExpiredQrTokens::class, ['--hours' => 24])
    ->hourly()
    ->withoutOverlapping();
