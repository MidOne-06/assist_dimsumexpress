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
    public const MAXIMO_JORNADA_MINUTOS = 18 * 60;
    /** @return array{inicio: Carbon, fin: Carbon, ventana_inicio: Carbon, ventana_fin: Carbon, jornada_fin_maximo: Carbon} */
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
            'jornada_fin_maximo' => $inicio->copy()->addMinutes(static::MAXIMO_JORNADA_MINUTOS),
        ];
    }

    /** Busca hoy y ayer para permitir que un turno nocturno continúe tras medianoche. */
    public static function asignacionVigente(Colaborador $colaborador, ?Carbon $momento = null): ?AsignacionTurno
    {
        $momento ??= now();

        $fechaActual = $momento->toDateString();
        $fechaAnterior = $momento->copy()->subDay()->toDateString();

        return $colaborador->asignacionesTurno()
            ->with('turno')
            ->where(function ($query) use ($fechaActual, $fechaAnterior): void {
                $query->whereDate('fecha', $fechaActual)
                    ->orWhereDate('fecha', $fechaAnterior);
            })
            ->get()
            ->filter(function (AsignacionTurno $asignacion) use ($momento, $colaborador): bool {
                if (! $asignacion->turno?->activo) {
                    return false;
                }

                $limites = static::limites($asignacion);

                return $momento->betweenIncluded($limites['ventana_inicio'], $limites['ventana_fin'])
                    || ($momento->lte($limites['jornada_fin_maximo']) && static::jornadaAbierta($colaborador, $asignacion));
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
            ->whereBetween('fecha_hora', [$limites['ventana_inicio'], $limites['jornada_fin_maximo']])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get();
    }

    public static function ultimaMarcacion(Colaborador $colaborador, AsignacionTurno $asignacion): ?Marcacion
    {
        return static::marcaciones($colaborador, $asignacion)->last();
    }

    public static function jornadaAbierta(Colaborador $colaborador, AsignacionTurno $asignacion): bool
    {
        $marcaciones = static::marcaciones($colaborador, $asignacion);

        return $marcaciones->contains('tipo', Marcacion::TIPO_ENTRADA)
            && $marcaciones->last()?->tipo !== Marcacion::TIPO_SALIDA;
    }

    public static function minutosRefrigerio(AsignacionTurno $asignacion): int
    {
        return $asignacion->turno->incluye_refrigerio
            ? (int) $asignacion->turno->refrigerio_minutos
            : 0;
    }

    public static function minutosObjetivo(AsignacionTurno $asignacion): int
    {
        return (int) $asignacion->turno->horas_efectivas_objetivo_minutos;
    }

    /** La salida pendiente determina la hora comprometida para el retorno. */
    public static function retornoRefrigerioEsperado(Colaborador $colaborador, AsignacionTurno $asignacion): ?Carbon
    {
        $ultimaMarcacion = static::ultimaMarcacion($colaborador, $asignacion);

        if ($ultimaMarcacion?->tipo !== Marcacion::TIPO_SALIDA_REFRIGERIO) {
            return null;
        }

        return $ultimaMarcacion->fecha_hora->copy()->addMinutes(static::minutosRefrigerio($asignacion));
    }

    /** @return array{esperado: Carbon, diferencia_segundos: int} */
    public static function controlRetornoRefrigerio(Marcacion $salidaRefrigerio, Carbon $retorno, int $minutosRefrigerio = self::DURACION_REFRIGERIO_MINUTOS): array
    {
        $esperado = $salidaRefrigerio->fecha_hora->copy()->addMinutes($minutosRefrigerio);

        return [
            'esperado' => $esperado,
            'diferencia_segundos' => $retorno->getTimestamp() - $esperado->getTimestamp(),
        ];
    }

    public static function puedeIniciarRefrigerio(AsignacionTurno $asignacion, ?Carbon $momento = null): bool
    {
        $momento ??= now();
        $limites = static::limites($asignacion);

        if (static::minutosRefrigerio($asignacion) < 1) {
            return false;
        }

        return $momento->copy()
            ->addMinutes(static::minutosRefrigerio($asignacion))
            ->lte($limites['ventana_fin']);
    }

    /** @return array{estado:string, efectivos_minutos:?int, objetivo_minutos:int, extras_minutos:?int, diferencia_minutos:?int} */
    public static function resumen(Colaborador $colaborador, AsignacionTurno $asignacion, ?Collection $marcaciones = null): array
    {
        $marcaciones ??= static::marcaciones($colaborador, $asignacion);
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salida = $marcaciones->filter(fn (Marcacion $marcacion) => $marcacion->tipo === Marcacion::TIPO_SALIDA)->last();
        $objetivoConfigurado = static::minutosObjetivo($asignacion);

        if (! $entrada || ! $salida) {
            return ['estado' => 'en_curso', 'efectivos_minutos' => null, 'objetivo_minutos' => $objetivoConfigurado, 'extras_minutos' => null, 'diferencia_minutos' => null];
        }

        $refrigerio = 0;
        $inicioRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $finRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);
        if ($inicioRefrigerio && $finRefrigerio) {
            $refrigerio = $inicioRefrigerio->fecha_hora->diffInMinutes($finRefrigerio->fecha_hora);
        }

        $efectivos = (int) max(0, $entrada->fecha_hora->diffInMinutes($salida->fecha_hora) - $refrigerio);
        // Un turno puede definir una meta de jornada completa superior a su
        // meta ordinaria. Solo se aplica cuando la permanencia real la
        // alcanza: así un turno mañana extendido se reconoce sin reasignar
        // al colaborador y sin imponer una regla global a los demás turnos.
        $objetivoJornadaCompleta = $asignacion->turno->horas_efectivas_jornada_completa_minutos;
        $objetivo = $objetivoJornadaCompleta !== null && $efectivos >= $objetivoJornadaCompleta
            ? (int) $objetivoJornadaCompleta
            : $objetivoConfigurado;
        $diferencia = $efectivos - $objetivo;

        return [
            'estado' => $diferencia < 0 ? 'pendiente' : ($diferencia > 0 ? 'extendida' : 'cumplida'),
            'efectivos_minutos' => $efectivos,
            'objetivo_minutos' => $objetivo,
            'extras_minutos' => max(0, $diferencia),
            'diferencia_minutos' => $diferencia,
        ];
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
