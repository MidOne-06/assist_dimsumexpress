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

                $resumen = JornadaMarcacion::resumen($asignacion->colaborador, $asignacion);
                if ($resumen['estado'] === 'inconsistente') {
                    $ultimaInconsistente = JornadaMarcacion::ultimaMarcacion($asignacion->colaborador, $asignacion);
                    $incidencia = IncidenciaMarcacion::firstOrCreate(
                        [
                            'asignacion_turno_id' => $asignacion->id,
                            'tipo' => IncidenciaMarcacion::TIPO_SECUENCIA_INCONSISTENTE,
                        ],
                        [
                            'colaborador_id' => $asignacion->colaborador_id,
                            'detectada_en' => $momento,
                            'sucursal_id' => $ultimaInconsistente?->sucursal_id ?? $asignacion->colaborador->sucursal_id,
                            'punto_venta_id' => $ultimaInconsistente?->punto_venta_id,
                            'observacion_reporte' => implode(', ', $resumen['inconsistencias']),
                        ],
                    );

                    if ($incidencia->wasRecentlyCreated) {
                        $detectadas++;
                    }

                    return;
                }

                // Una jornada abierta puede cerrar fuera del turno programado
                // por operación. La incidencia solo nace al vencer el máximo
                // de jornada, nunca al terminar el horario base.
                if ($momento->lte($limites['jornada_fin_maximo'])) {
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
                    [
                        'asignacion_turno_id' => $asignacion->id,
                        'tipo' => $tipo,
                    ],
                    [
                        'colaborador_id' => $asignacion->colaborador_id,
                        'detectada_en' => $momento,
                        'sucursal_id' => $ultima?->sucursal_id ?? $asignacion->colaborador->sucursal_id,
                        'punto_venta_id' => $ultima?->punto_venta_id,
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
