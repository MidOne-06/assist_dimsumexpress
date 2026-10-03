<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SchedulerHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Punto de control para un monitor externo. No revela credenciales ni
     * información operativa: solo confirma app, base y scheduler.
     */
    public function __invoke(SchedulerHeartbeat $scheduler): JsonResponse
    {
        try {
            DB::select('select 1');
            $baseDatos = 'ok';
        } catch (\Throwable) {
            $baseDatos = 'error';
        }

        $heartbeat = $scheduler->latestAt();
        $estadoScheduler = $scheduler->isStale($heartbeat)
            ? 'stale'
            : ($heartbeat ? 'ok' : 'initializing');
        $saludable = $baseDatos === 'ok' && $estadoScheduler !== 'stale';

        return response()
            ->json([
                'status' => $saludable ? 'ok' : 'degraded',
                'checks' => [
                    'database' => $baseDatos,
                    'scheduler' => $estadoScheduler,
                ],
            ], $saludable ? 200 : 503)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
