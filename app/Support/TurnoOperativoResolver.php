<?php

namespace App\Support;

use App\Models\AjusteTurnoAutomatico;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\TurnoOperativo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/** Resuelve el turno operativo efectivo a partir de una estación QR. */
final class TurnoOperativoResolver
{
    /**
     * Busca el turno de tienda configurado en la estación. No persiste nada:
     * la asignación se crea únicamente al confirmar la primera entrada.
     */
    public static function detectar(Colaborador $colaborador, Sucursal $sucursal, ?PuntoVenta $puntoVenta, ?Carbon $momento = null): ?AsignacionTurno
    {
        // Durante una actualización de esquema la marcación conserva el
        // comportamiento excepcional previo; nunca debe responder 500.
        if (! Schema::hasTable('turnos_operativos')) {
            return null;
        }

        $momento ??= now();
        $configuraciones = TurnoOperativo::query()
            ->with('turno')
            ->where('sucursal_id', $sucursal->id)
            ->where('activo', true)
            ->whereHas('turno', fn ($query) => $query->where('activo', true))
            ->get()
            // Las estaciones pueden tener reglas históricas repetidas. Para
            // decidir una marcación se considera una sola regla por turno y
            // ámbito; la de menor prioridad numérica y luego menor id es la
            // vigente de forma determinista hasta que administración depure
            // el catálogo visualmente.
            ->sortBy(fn (TurnoOperativo $regla): array => [$regla->prioridad, $regla->id])
            ->unique(fn (TurnoOperativo $regla): string => ($regla->punto_venta_id ?? 'local').':'.$regla->turno_id)
            ->values();

        $especificas = $puntoVenta ? $configuraciones->where('punto_venta_id', $puntoVenta->id) : collect();
        $candidatas = $especificas->isNotEmpty() ? $especificas : $configuraciones->whereNull('punto_venta_id');

        $ganadora = $candidatas
            ->filter(function (TurnoOperativo $configuracion) use ($momento): bool {
                $inicio = Carbon::parse($momento->toDateString().' '.$configuracion->turno->hora_inicio, config('app.timezone'))
                    ->subMinutes($configuracion->turno->tolerancia_entrada_minutos);
                $fin = self::finOperativo($configuracion->turno, $momento);

                return $momento->betweenIncluded($inicio, $fin);
            })
            ->sortBy(function (TurnoOperativo $configuracion) use ($momento): array {
                $inicio = Carbon::parse($momento->toDateString().' '.$configuracion->turno->hora_inicio, config('app.timezone'));

                return [abs($momento->getTimestamp() - $inicio->getTimestamp()), $configuracion->prioridad, $configuracion->id];
            })
            ->first();

        if (! $ganadora) {
            return null;
        }

        $asignacion = new AsignacionTurno([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $ganadora->turno_id,
            'turno_operativo_id' => $ganadora->id,
            'fecha' => $momento->toDateString(),
            'origen' => 'detectado_automaticamente',
            'detectado_en' => $momento,
            'observacion' => 'Turno detectado por rango de estación',
        ]);
        $asignacion->setRelation('turno', $ganadora->turno);
        $asignacion->setRelation('turnoOperativo', $ganadora);

        return $asignacion;
    }

    private static function finOperativo(Turno $turno, Carbon $momento): Carbon
    {
        if ($turno->jornada_abierta || ! $turno->hora_fin) {
            return Carbon::parse($momento->toDateString().' '.$turno->hora_inicio, config('app.timezone'))
                ->addMinutes(JornadaMarcacion::MAXIMO_JORNADA_MINUTOS);
        }
        $fin = Carbon::parse($momento->toDateString().' '.$turno->hora_fin, config('app.timezone'));
        if ($turno->cruza_medianoche || $fin->lte(Carbon::parse($momento->toDateString().' '.$turno->hora_inicio, config('app.timezone')))) {
            $fin->addDay();
        }
        return $fin;
    }

    /** Persiste el turno detectado solo al confirmar la primera entrada. */
    public static function confirmarAjuste(Colaborador $colaborador, AsignacionTurno $asignacion, Carbon $detectadoEn): void
    {
        $turnoProgramadoId = (int) $asignacion->getOriginal('turno_id');
        $turnoEfectivoId = (int) $asignacion->turno_id;

        if ($turnoProgramadoId === $turnoEfectivoId) {
            return;
        }

        AjusteTurnoAutomatico::firstOrCreate(
            ['asignacion_turno_id' => $asignacion->id],
            [
                'colaborador_id' => $colaborador->id,
                'turno_programado_id' => $turnoProgramadoId,
                'turno_efectivo_id' => $turnoEfectivoId,
                'detectado_en' => $detectadoEn,
            ],
        );

        $asignacion->save();
    }

    /**
     * Conserva la asignación diaria existente, pero aplica el turno que fue
     * resuelto por la estación antes de registrar la primera marcación. Así
     * el histórico mantiene un único registro por día y el cambio conserva
     * trazabilidad en AjusteTurnoAutomatico al confirmarse la entrada.
     */
    public static function aplicarTurno(AsignacionTurno $asignacion, AsignacionTurno $detectada): AsignacionTurno
    {
        $asignacion->turno_id = $detectada->turno_id;
        $asignacion->turno_operativo_id = $detectada->turno_operativo_id;
        $asignacion->origen = $detectada->origen;
        $asignacion->detectado_en = $detectada->detectado_en;
        $asignacion->observacion = $detectada->observacion;
        $asignacion->setRelation('turno', $detectada->turno);
        $asignacion->setRelation('turnoOperativo', $detectada->turnoOperativo);

        return $asignacion;
    }


}
