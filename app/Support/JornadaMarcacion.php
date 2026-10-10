<?php

namespace App\Support;

use App\Models\AjusteTurnoAutomatico;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Turno;
use App\Models\TurnoOperativo;
use App\Models\Sucursal;
use App\Models\PuntoVenta;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        // Las jornadas sin salida programada conservan un límite técnico para
        // no quedar vigentes indefinidamente. Solo entrada termina su flujo
        // después del ingreso; jornada abierta permite todas las marcaciones.
        if ($turno->solo_entrada || $turno->jornada_abierta || ! $turno->hora_fin) {
            $fin = $inicio->copy()->addMinutes(static::MAXIMO_JORNADA_MINUTOS);
        } else {
            $fin = Carbon::parse($asignacion->fecha->toDateString() . ' ' . $turno->hora_fin, config('app.timezone'));

            if ($turno->cruza_medianoche || $fin->lte($inicio)) {
                $fin->addDay();
            }
        }

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'ventana_inicio' => $inicio->copy()->subMinutes($turno->tolerancia_entrada_minutos),
            // Una jornada abierta no tiene hora de salida programada. Su
            // entrada debe poder confirmarse durante toda la jornada técnica;
            // la tardanza se calcula igualmente desde la hora de inicio y su
            // tolerancia, no se oculta por ampliar la ventana operativa.
            'ventana_fin' => $turno->solo_entrada
                ? $inicio->copy()->addMinutes($turno->tolerancia_entrada_minutos)
                : (($turno->jornada_abierta || ! $turno->hora_fin)
                    ? $fin->copy()
                    : $fin->copy()->addMinutes($turno->tolerancia_salida_minutos)),
            'jornada_fin_maximo' => $inicio->copy()->addMinutes(static::MAXIMO_JORNADA_MINUTOS),
        ];
    }

    /** Busca hoy y ayer para permitir que un turno nocturno continúe tras medianoche. */
    public static function asignacionVigente(Colaborador $colaborador, ?Carbon $momento = null, bool $permitirAjusteAutomatico = true): ?AsignacionTurno
    {
        $momento ??= now();

        $fechaActual = $momento->toDateString();
        $fechaAnterior = $momento->copy()->subDay()->toDateString();

        $asignaciones = $colaborador->asignacionesTurno()
            ->with('turno')
            ->where(function ($query) use ($fechaActual, $fechaAnterior): void {
                $query->whereDate('fecha', $fechaActual)
                    ->orWhereDate('fecha', $fechaAnterior);
            })
            ->get();

        // Una jornada ya iniciada siempre conserva su turno efectivo. No se
        // vuelve a inferir otro turno durante refrigerio o salida final.
        $jornadaAbierta = $asignaciones
            ->filter(function (AsignacionTurno $asignacion) use ($momento, $colaborador): bool {
                return static::jornadaAbierta($colaborador, $asignacion)
                    && $momento->lte(static::limites($asignacion)['jornada_fin_maximo']);
            })
            ->sortByDesc(fn (AsignacionTurno $asignacion) => static::limites($asignacion)['inicio']->getTimestamp())
            ->first();

        if ($jornadaAbierta) {
            return $jornadaAbierta;
        }

        $asignacionHoy = $asignaciones->first(
            fn (AsignacionTurno $asignacion): bool => $asignacion->fecha->isSameDay($momento),
        );

        // La primera marcación puede revelar un cambio operativo de turno.
        // Si coincide de forma inequívoca con la ventana de entrada de otro
        // turno activo, se presenta ese turno efectivo sin tocar todavía la
        // programación. El ajuste se persiste únicamente al confirmar la
        // entrada dentro de la transacción del controlador.
        if ($permitirAjusteAutomatico && $asignacionHoy && ! static::tieneMarcacionesEnFecha($colaborador, $momento)) {
            $turnoAlternativo = static::turnoAlternativoParaEntrada($asignacionHoy, $momento);

            if ($turnoAlternativo) {
                return static::asignacionConTurnoEfectivo($asignacionHoy, $turnoAlternativo);
            }
        }

        return $asignaciones
            ->filter(function (AsignacionTurno $asignacion) use ($momento, $colaborador): bool {
                $jornadaAbierta = static::jornadaAbierta($colaborador, $asignacion);

                // Desactivar o versionar un turno no puede dejar sin salida
                // ni refrigerio a quien ya inició esa jornada. El turno
                // inactivo sigue bloqueando nuevos ingresos, pero permite
                // completar exclusivamente una secuencia ya abierta.
                if (! $asignacion->turno?->activo && ! $jornadaAbierta) {
                    return false;
                }

                $limites = static::limites($asignacion);

                // El reloj de asistencia registra la hora real mientras la
                // jornada asignada siga en curso técnicamente. La tardanza y
                // las demás incidencias se calculan después; no se bloquea al
                // colaborador por llegar fuera de la tolerancia configurada.
                return $momento->betweenIncluded($limites['ventana_inicio'], $limites['jornada_fin_maximo']);
            })
            ->sortByDesc(fn (AsignacionTurno $asignacion) => static::limites($asignacion)['inicio']->getTimestamp())
            ->first();
    }

    /**
     * Busca el turno de tienda configurado en la estación. No persiste nada:
     * la asignación se crea únicamente al confirmar la primera entrada.
     */
    public static function detectarTurnoOperativo(Colaborador $colaborador, Sucursal $sucursal, ?PuntoVenta $puntoVenta, ?Carbon $momento = null): ?AsignacionTurno
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
                $fin = static::finOperativo($configuracion->turno, $momento);

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
                ->addMinutes(self::MAXIMO_JORNADA_MINUTOS);
        }
        $fin = Carbon::parse($momento->toDateString().' '.$turno->hora_fin, config('app.timezone'));
        if ($turno->cruza_medianoche || $fin->lte(Carbon::parse($momento->toDateString().' '.$turno->hora_inicio, config('app.timezone')))) {
            $fin->addDay();
        }
        return $fin;
    }

    /** Persiste el turno detectado solo al confirmar la primera entrada. */
    public static function confirmarAjusteAutomatico(Colaborador $colaborador, AsignacionTurno $asignacion, Carbon $detectadoEn): void
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
    public static function aplicarTurnoDetectado(AsignacionTurno $asignacion, AsignacionTurno $detectada): AsignacionTurno
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

    private static function tieneMarcacionesEnFecha(Colaborador $colaborador, Carbon $momento): bool
    {
        return $colaborador->marcaciones()
            ->whereBetween('fecha_hora', [$momento->copy()->startOfDay(), $momento->copy()->endOfDay()])
            ->exists();
    }

    private static function turnoAlternativoParaEntrada(AsignacionTurno $asignacion, Carbon $momento): ?Turno
    {
        $fecha = $asignacion->fecha->toDateString();
        $turnoProgramadoId = (int) $asignacion->getOriginal('turno_id');

        $coincidencias = Turno::query()
            ->where('activo', true)
            ->where('id', '!=', $turnoProgramadoId)
            ->where('hora_inicio', '!=', $asignacion->turno->hora_inicio)
            ->orderBy('hora_inicio')
            ->get()
            ->filter(function (Turno $turno) use ($fecha, $momento): bool {
                $inicio = Carbon::parse("{$fecha} {$turno->hora_inicio}", config('app.timezone'));

                return $momento->betweenIncluded(
                    $inicio->copy()->subMinutes($turno->tolerancia_entrada_minutos),
                    $inicio->copy()->addMinutes($turno->tolerancia_entrada_minutos),
                );
            })
            ->values();

        return $coincidencias->count() === 1 ? $coincidencias->first() : null;
    }

    private static function asignacionConTurnoEfectivo(AsignacionTurno $asignacion, Turno $turno): AsignacionTurno
    {
        $asignacion->turno_id = $turno->id;
        $asignacion->setRelation('turno', $turno);

        return $asignacion;
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

        // Una jornada abierta puede extenderse por necesidad operativa sin
        // reasignar el turno. El refrigerio conserva su duración configurada
        // y se permite mientras su retorno no supere el límite seguro de la
        // jornada, no solo el fin planificado más su tolerancia.
        return $momento->copy()
            ->addMinutes(static::minutosRefrigerio($asignacion))
            ->lte($limites['jornada_fin_maximo']);
    }

    /**
     * @return array{estado:string, efectivos_segundos:?int, objetivo_segundos:int, extras_segundos:?int, diferencia_segundos:?int, refrigerio_segundos:?int, efectivos_minutos:?int, objetivo_minutos:int, extras_minutos:?int, diferencia_minutos:?int, inconsistencias:array<int, string>}
     */
    public static function resumen(Colaborador $colaborador, AsignacionTurno $asignacion, ?Collection $marcaciones = null): array
    {
        return JornadaResumen::calcular($colaborador, $asignacion, $marcaciones);
    }
    /** @return array<int, string> */
    public static function siguientesTipos(Colaborador $colaborador, AsignacionTurno $asignacion): array
    {
        return JornadaAcciones::siguientesTipos($colaborador, $asignacion);
    }

    public static function siguienteTipoAutomatico(Colaborador $colaborador, AsignacionTurno $asignacion, ?Carbon $momento = null): ?string
    {
        return JornadaAcciones::siguienteTipoAutomatico($colaborador, $asignacion, $momento);
    }

    public static function siguienteTipoSinTurnoAutomatico(Colaborador $colaborador, ?Carbon $momento = null): ?string
    {
        return JornadaAcciones::siguienteTipoSinTurnoAutomatico($colaborador, $momento);
    }

    /** @return array<int, array{tipo: string, codigo: int, etiqueta: string, habilitada: bool, motivo: ?string}> */
    public static function acciones(Colaborador $colaborador, AsignacionTurno $asignacion): array
    {
        return JornadaAcciones::acciones($colaborador, $asignacion);
    }

    /** @return array<int, array{tipo: string, codigo: int, etiqueta: string, habilitada: bool, motivo: ?string}> */
    public static function accionesSinTurno(Colaborador $colaborador, ?Carbon $momento = null): array
    {
        return JornadaAcciones::accionesSinTurno($colaborador, $momento);
    }
}
