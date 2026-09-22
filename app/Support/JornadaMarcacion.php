<?php

namespace App\Support;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Define la jornada por turno asignado, incluso cuando cruza medianoche. */
final class JornadaMarcacion
{
    public const DURACION_REFRIGERIO_MINUTOS = 60;

    /** @return array{inicio: Carbon, fin: Carbon, ventana_inicio: Carbon, ventana_fin: Carbon} */
    public static function limites(AsignacionTurno $asignacion): array
    {
        $turno = $asignacion->turno;
        $inicio = Carbon::parse($asignacion->fecha->toDateString() . ' ' . $turno->hora_inicio, config('app.timezone'));
        $fin = Carbon::parse($asignacion->fecha->toDateString() . ' ' . $turno->hora_fin, config('app.timezone'));

        if ($turno->cruza_medianoche || $fin->lte($inicio)) {
            $fin->addDay();
        }

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'ventana_inicio' => $inicio->copy()->subMinutes($turno->tolerancia_entrada_minutos),
            'ventana_fin' => $fin->copy()->addMinutes($turno->tolerancia_salida_minutos),
        ];
    }

    /** Busca hoy y ayer para permitir que un turno nocturno continúe tras medianoche. */
    public static function asignacionVigente(Colaborador $colaborador, ?Carbon $momento = null): ?AsignacionTurno
    {
        $momento ??= now();

        return $colaborador->asignacionesTurno()
            ->with('turno')
            ->whereIn('fecha', [$momento->toDateString(), $momento->copy()->subDay()->toDateString()])
            ->get()
            ->filter(function (AsignacionTurno $asignacion) use ($momento): bool {
                if (! $asignacion->turno?->activo) {
                    return false;
                }

                $limites = static::limites($asignacion);

                return $momento->betweenIncluded($limites['ventana_inicio'], $limites['ventana_fin']);
            })
            ->sortByDesc(fn (AsignacionTurno $asignacion) => static::limites($asignacion)['inicio']->getTimestamp())
            ->first();
    }

    /** @return Collection<int, Marcacion> */
    public static function marcaciones(Colaborador $colaborador, AsignacionTurno $asignacion): Collection
    {
        $limites = static::limites($asignacion);

        return $colaborador->marcaciones()
            ->where('turno_id', $asignacion->turno_id)
            ->whereBetween('fecha_hora', [$limites['ventana_inicio'], $limites['ventana_fin']])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get();
    }

    public static function ultimaMarcacion(Colaborador $colaborador, AsignacionTurno $asignacion): ?Marcacion
    {
        return static::marcaciones($colaborador, $asignacion)->last();
    }

    /** La salida pendiente determina la hora comprometida para el retorno. */
    public static function retornoRefrigerioEsperado(Colaborador $colaborador, AsignacionTurno $asignacion): ?Carbon
    {
        $ultimaMarcacion = static::ultimaMarcacion($colaborador, $asignacion);

        if ($ultimaMarcacion?->tipo !== Marcacion::TIPO_SALIDA_REFRIGERIO) {
            return null;
        }

        return $ultimaMarcacion->fecha_hora->copy()->addMinutes(static::DURACION_REFRIGERIO_MINUTOS);
    }

    /** @return array{esperado: Carbon, diferencia_segundos: int} */
    public static function controlRetornoRefrigerio(Marcacion $salidaRefrigerio, Carbon $retorno): array
    {
        $esperado = $salidaRefrigerio->fecha_hora->copy()->addMinutes(static::DURACION_REFRIGERIO_MINUTOS);

        return [
            'esperado' => $esperado,
            'diferencia_segundos' => $retorno->getTimestamp() - $esperado->getTimestamp(),
        ];
    }

    public static function puedeIniciarRefrigerio(AsignacionTurno $asignacion, ?Carbon $momento = null): bool
    {
        $momento ??= now();
        $limites = static::limites($asignacion);

        return $momento->copy()
            ->addMinutes(static::DURACION_REFRIGERIO_MINUTOS)
            ->lte($limites['ventana_fin']);
    }

    /** @return array<int, string> */
    public static function siguientesTipos(Colaborador $colaborador, AsignacionTurno $asignacion): array
    {
        return match (static::ultimaMarcacion($colaborador, $asignacion)?->tipo) {
            null => [Marcacion::TIPO_ENTRADA],
            Marcacion::TIPO_ENTRADA => $asignacion->turno->solo_entrada ? [] : array_values(array_filter([
                static::puedeIniciarRefrigerio($asignacion) ? Marcacion::TIPO_SALIDA_REFRIGERIO : null,
                Marcacion::TIPO_SALIDA,
            ])),
            // El refrigerio es único por jornada. Tras registrar el retorno,
            // la única marcación posible es el cierre del turno.
            Marcacion::TIPO_REGRESO_REFRIGERIO => [Marcacion::TIPO_SALIDA],
            Marcacion::TIPO_SALIDA_REFRIGERIO => [Marcacion::TIPO_REGRESO_REFRIGERIO],
            Marcacion::TIPO_SALIDA => [],
            default => [],
        };
    }
}
