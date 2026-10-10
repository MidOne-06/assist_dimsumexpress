<?php

namespace App\Support;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use Carbon\Carbon;

/** Resuelve la secuencia y presentación de acciones permitidas en una jornada. */
final class JornadaAcciones
{
    /** @var array<string, array{codigo: int, etiqueta: string}> */
    private const ACCIONES = [
        Marcacion::TIPO_ENTRADA => ['codigo' => 3, 'etiqueta' => 'Entrada'],
        Marcacion::TIPO_SALIDA_REFRIGERIO => ['codigo' => 5, 'etiqueta' => 'Salida a refrigerio'],
        Marcacion::TIPO_REGRESO_REFRIGERIO => ['codigo' => 7, 'etiqueta' => 'Ingreso de refrigerio'],
        Marcacion::TIPO_SALIDA => ['codigo' => 9, 'etiqueta' => 'Salida de turno'],
    ];

    /** @return array<int, string> */
    public static function siguientesTipos(Colaborador $colaborador, AsignacionTurno $asignacion): array
    {
        return match (JornadaMarcacion::ultimaMarcacion($colaborador, $asignacion)?->tipo) {
            null => [Marcacion::TIPO_ENTRADA],
            Marcacion::TIPO_ENTRADA => $asignacion->turno->solo_entrada ? [] : array_values(array_filter([
                JornadaMarcacion::puedeIniciarRefrigerio($asignacion) ? Marcacion::TIPO_SALIDA_REFRIGERIO : null,
                Marcacion::TIPO_SALIDA,
            ])),
            Marcacion::TIPO_REGRESO_REFRIGERIO => [Marcacion::TIPO_SALIDA],
            Marcacion::TIPO_SALIDA_REFRIGERIO => [Marcacion::TIPO_REGRESO_REFRIGERIO],
            Marcacion::TIPO_SALIDA => [],
            default => [],
        };
    }

    /** Determina la siguiente acción sin pedir al colaborador que la elija. */
    public static function siguienteTipoAutomatico(Colaborador $colaborador, AsignacionTurno $asignacion, ?Carbon $momento = null): ?string
    {
        $momento ??= now();
        $ultima = JornadaMarcacion::ultimaMarcacion($colaborador, $asignacion)?->tipo;

        if ($ultima === null) {
            return Marcacion::TIPO_ENTRADA;
        }

        if ($ultima === Marcacion::TIPO_SALIDA_REFRIGERIO) {
            return Marcacion::TIPO_REGRESO_REFRIGERIO;
        }

        if ($ultima === Marcacion::TIPO_REGRESO_REFRIGERIO) {
            return Marcacion::TIPO_SALIDA;
        }

        if ($ultima !== Marcacion::TIPO_ENTRADA || $asignacion->turno->solo_entrada) {
            return null;
        }

        if (JornadaMarcacion::minutosRefrigerio($asignacion) < 1 || ! JornadaMarcacion::puedeIniciarRefrigerio($asignacion, $momento)) {
            return Marcacion::TIPO_SALIDA;
        }

        if ($asignacion->turno->jornada_abierta || ! $asignacion->turno->hora_fin) {
            return Marcacion::TIPO_SALIDA_REFRIGERIO;
        }

        $limites = JornadaMarcacion::limites($asignacion);
        $puntoMedio = $limites['inicio']->copy()->addSeconds(
            intdiv($limites['fin']->getTimestamp() - $limites['inicio']->getTimestamp(), 2),
        );

        return $momento->lt($puntoMedio)
            ? Marcacion::TIPO_SALIDA_REFRIGERIO
            : Marcacion::TIPO_SALIDA;
    }

    /** La jornada excepcional sin turno conserva ingreso y salida trazables. */
    public static function siguienteTipoSinTurnoAutomatico(Colaborador $colaborador, ?Carbon $momento = null): ?string
    {
        $momento ??= now();
        $ultima = $colaborador->marcaciones()
            ->whereNull('turno_id')
            ->whereBetween('fecha_hora', [$momento->copy()->startOfDay(), $momento->copy()->endOfDay()])
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->value('tipo');

        return match ($ultima) {
            null => Marcacion::TIPO_ENTRADA,
            Marcacion::TIPO_ENTRADA => Marcacion::TIPO_SALIDA,
            default => null,
        };
    }

    /** @return array<int, array{tipo: string, codigo: int, etiqueta: string, habilitada: bool, motivo: ?string}> */
    public static function acciones(Colaborador $colaborador, AsignacionTurno $asignacion): array
    {
        $siguientes = self::siguientesTipos($colaborador, $asignacion);
        $ultima = JornadaMarcacion::ultimaMarcacion($colaborador, $asignacion)?->tipo;

        return collect(self::ACCIONES)
            ->map(fn (array $accion, string $tipo): array => [
                'tipo' => $tipo,
                'codigo' => $accion['codigo'],
                'etiqueta' => $accion['etiqueta'],
                'habilitada' => in_array($tipo, $siguientes, true),
                'motivo' => in_array($tipo, $siguientes, true)
                    ? null
                    : self::motivoAccionNoDisponible($tipo, $ultima, $asignacion),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{tipo: string, codigo: int, etiqueta: string, habilitada: bool, motivo: ?string}> */
    public static function accionesSinTurno(Colaborador $colaborador, ?Carbon $momento = null): array
    {
        $momento ??= now();
        $ultima = $colaborador->marcaciones()
            ->whereNull('turno_id')
            ->whereBetween('fecha_hora', [$momento->copy()->startOfDay(), $momento->copy()->endOfDay()])
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->first();

        $siguientes = match ($ultima?->tipo) {
            null => [Marcacion::TIPO_ENTRADA],
            Marcacion::TIPO_ENTRADA => [Marcacion::TIPO_SALIDA_REFRIGERIO, Marcacion::TIPO_SALIDA],
            Marcacion::TIPO_SALIDA_REFRIGERIO => [Marcacion::TIPO_REGRESO_REFRIGERIO],
            Marcacion::TIPO_REGRESO_REFRIGERIO => [Marcacion::TIPO_SALIDA],
            default => [],
        };

        return collect(self::ACCIONES)
            ->map(fn (array $accion, string $tipo): array => [
                'tipo' => $tipo,
                'codigo' => $accion['codigo'],
                'etiqueta' => $accion['etiqueta'],
                'habilitada' => in_array($tipo, $siguientes, true),
                'motivo' => in_array($tipo, $siguientes, true)
                    ? null
                    : self::motivoAccionSinTurnoNoDisponible($tipo, $ultima?->tipo),
            ])
            ->values()
            ->all();
    }

    private static function motivoAccionNoDisponible(string $tipo, ?string $ultima, AsignacionTurno $asignacion): string
    {
        return match ($tipo) {
            Marcacion::TIPO_ENTRADA => $ultima === null
                ? 'La entrada no está habilitada en este momento.'
                : 'Ya registraste tu entrada.',
            Marcacion::TIPO_SALIDA_REFRIGERIO => $asignacion->turno->solo_entrada
                ? 'Esta acción no está disponible por ahora.'
                : (! $asignacion->turno->incluye_refrigerio
                    ? 'Esta acción no está disponible por ahora.'
                    : match ($ultima) {
                        null => 'Registra primero tu entrada.',
                        Marcacion::TIPO_SALIDA_REFRIGERIO => 'Confirma primero tu ingreso de refrigerio.',
                        Marcacion::TIPO_REGRESO_REFRIGERIO => 'El refrigerio ya fue registrado.',
                        Marcacion::TIPO_SALIDA => 'Tu jornada ya finalizó.',
                        default => 'Esta acción no está disponible por ahora.',
                    }),
            Marcacion::TIPO_REGRESO_REFRIGERIO => match ($ultima) {
                null => 'Registra primero tu entrada.',
                Marcacion::TIPO_ENTRADA => 'Inicia primero tu refrigerio.',
                Marcacion::TIPO_REGRESO_REFRIGERIO => 'Ya registraste tu ingreso de refrigerio.',
                Marcacion::TIPO_SALIDA => 'Tu jornada ya finalizó.',
                default => 'Esta acción no está disponible por ahora.',
            },
            Marcacion::TIPO_SALIDA => match ($ultima) {
                null => 'Registra primero tu entrada.',
                Marcacion::TIPO_SALIDA_REFRIGERIO => 'Registra primero tu ingreso de refrigerio.',
                Marcacion::TIPO_SALIDA => 'Tu jornada ya finalizó.',
                default => 'Esta acción no está disponible por ahora.',
            },
            default => 'Esta acción no está disponible por ahora.',
        };
    }

    private static function motivoAccionSinTurnoNoDisponible(string $tipo, ?string $ultima): string
    {
        return match ($tipo) {
            Marcacion::TIPO_ENTRADA => $ultima === null
                ? 'La entrada no está habilitada en este momento.'
                : 'Ya registraste tu entrada.',
            Marcacion::TIPO_SALIDA_REFRIGERIO => match ($ultima) {
                null => 'Registra primero tu entrada.',
                Marcacion::TIPO_SALIDA_REFRIGERIO => 'Confirma primero tu ingreso de refrigerio.',
                Marcacion::TIPO_REGRESO_REFRIGERIO => 'El refrigerio ya fue registrado.',
                Marcacion::TIPO_SALIDA => 'Tu jornada ya finalizó.',
                default => 'Esta acción no está disponible por ahora.',
            },
            Marcacion::TIPO_REGRESO_REFRIGERIO => match ($ultima) {
                null => 'Registra primero tu entrada.',
                Marcacion::TIPO_ENTRADA => 'Inicia primero tu refrigerio.',
                Marcacion::TIPO_REGRESO_REFRIGERIO => 'Ya registraste tu ingreso de refrigerio.',
                Marcacion::TIPO_SALIDA => 'Tu jornada ya finalizó.',
                default => 'Esta acción no está disponible por ahora.',
            },
            Marcacion::TIPO_SALIDA => match ($ultima) {
                null => 'Registra primero tu entrada.',
                Marcacion::TIPO_SALIDA_REFRIGERIO => 'Registra primero tu ingreso de refrigerio.',
                Marcacion::TIPO_SALIDA => 'Tu jornada ya finalizó.',
                default => 'Esta acción no está disponible por ahora.',
            },
            default => 'Esta acción no está disponible por ahora.',
        };
    }
}
