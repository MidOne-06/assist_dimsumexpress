<?php

namespace App\Console\Commands;

use App\Models\AsignacionTurno;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Support\JornadaMarcacion;
use Illuminate\Console\Command;

class DetectarIncidenciasMarcacion extends Command
{
    protected $signature = 'asistencia:detectar-incidencias';

    protected $description = 'Detecta jornadas vencidas con retorno de refrigerio o salida de turno pendientes';

    public function handle(): int
    {
        $detectadas = 0;
        $momento = now();

        AsignacionTurno::query()
            ->with(['turno', 'colaborador'])
            ->whereDate('fecha', '<=', $momento->toDateString())
            ->orderBy('fecha')
            ->each(function (AsignacionTurno $asignacion) use ($momento, &$detectadas): void {
                if (! $asignacion->turno?->activo || ! $asignacion->colaborador?->activo) {
                    return;
                }

                $limites = JornadaMarcacion::limites($asignacion);

                // La incidencia solo existe una vez cerrada la tolerancia del
                // turno; durante la ventana vigente el colaborador aún puede
                // escanear y completar la acción pendiente normalmente.
                if ($momento->lte($limites['ventana_fin'])) {
                    return;
                }

                $ultima = JornadaMarcacion::ultimaMarcacion($asignacion->colaborador, $asignacion);
                $tipo = match ($ultima?->tipo) {
                    Marcacion::TIPO_SALIDA_REFRIGERIO => IncidenciaMarcacion::TIPO_RETORNO_REFRIGERIO_PENDIENTE,
                    Marcacion::TIPO_ENTRADA, Marcacion::TIPO_REGRESO_REFRIGERIO => $asignacion->turno->solo_entrada
                        ? null
                        : IncidenciaMarcacion::TIPO_SALIDA_TURNO_PENDIENTE,
                    default => null,
                };

                if (! $tipo) {
                    return;
                }

                $incidencia = IncidenciaMarcacion::firstOrCreate(
                    ['asignacion_turno_id' => $asignacion->id],
                    [
                        'colaborador_id' => $asignacion->colaborador_id,
                        'tipo' => $tipo,
                        'detectada_en' => $momento,
                    ],
                );

                if ($incidencia->wasRecentlyCreated) {
                    $detectadas++;
                }
            });

        $this->info("Incidencias detectadas: {$detectadas}");

        return self::SUCCESS;
    }
}
