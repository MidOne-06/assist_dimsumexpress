<?php

namespace App\Console\Commands;

use App\Models\AsignacionTurno;
use App\Services\ConsolidacionJornadaService;
use Illuminate\Console\Command;

class ConsolidarJornadas extends Command
{
    protected $signature = 'asistencia:consolidar-jornadas {--historico : Incluye toda jornada cerrada sin consolidado}';

    protected $description = 'Consolida en forma inmutable las jornadas cerradas con precisión de segundos';

    public function handle(ConsolidacionJornadaService $servicio): int
    {
        $inicio = $this->option('historico') ? null : now()->copy()->subDays(2)->toDateString();
        $consolidadas = 0;

        AsignacionTurno::query()
            ->with(['colaborador', 'turno'])
            ->when($inicio, fn ($query) => $query->whereDate('fecha', '>=', $inicio))
            ->whereDoesntHave('resumenJornada')
            ->orderBy('id')
            ->eachById(function (AsignacionTurno $asignacion) use ($servicio, &$consolidadas): void {
                if ($asignacion->colaborador && $servicio->consolidar($asignacion->colaborador, $asignacion)) {
                    $consolidadas++;
                }
            });

        $this->info("Jornadas consolidadas: {$consolidadas}");

        return self::SUCCESS;
    }
}
