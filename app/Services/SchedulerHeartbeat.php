<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SchedulerHeartbeat
{
    private const PATH = 'health/scheduler-heartbeat.json';

    /** Registra una señal pequeña en el volumen compartido de la aplicación. */
    public function touch(): void
    {
        Storage::disk('local')->put(self::PATH, json_encode([
            'updated_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    /** Devuelve null hasta que el scheduler haya emitido su primera señal. */
    public function latestAt(): ?Carbon
    {
        if (! Storage::disk('local')->exists(self::PATH)) {
            return null;
        }

        try {
            $payload = json_decode(Storage::disk('local')->get(self::PATH), true, flags: JSON_THROW_ON_ERROR);
            $timestamp = $payload['updated_at'] ?? null;

            return is_string($timestamp) ? Carbon::parse($timestamp) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isStale(?Carbon $lastHeartbeat = null): bool
    {
        $lastHeartbeat ??= $this->latestAt();

        return $lastHeartbeat !== null && $lastHeartbeat->lt(now()->subMinutes(3));
    }
}
