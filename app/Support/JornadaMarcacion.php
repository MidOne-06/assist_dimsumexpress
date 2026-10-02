<?php

namespace App\Support;

use App\Models\AjusteTurnoAutomatico;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Define la jornada por turno asignado, incluso cuando cruza medianoche. */
final class JornadaMarcacion
{
    public const DURACION_REFRIGERIO_MINUTOS = 60;
    public const MAXIMO_JORNADA_MINUTOS = 18 * 60;

    /** @var array<string, array{codigo: int, etiqueta: string}> */
    private const ACCIONES = [
        Marcacion::TIPO_ENTRADA => ['codigo' => 3, 'etiqueta' => 'Entrada'],
        Marcacion::TIPO_SALIDA_REFRIGERIO => ['codigo' => 5, 'etiqueta' => 'Salida a refrigerio'],
        Marcacion::TIPO_REGRESO_REFRIGERIO => ['codigo' => 7, 'etiqueta' => 'Ingreso de refrigerio'],
        Marcacion::TIPO_SALIDA => ['codigo' => 9, 'etiqueta' => 'Salida de turno'],
    ];
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
    public static function asignacionVigente(Colaborador $colaborador, ?Carbon $momento = null): ?AsignacionTurno
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
        if ($asignacionHoy && ! static::tieneMarcacionesEnFecha($colaborador, $momento)) {
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

                return $momento->betweenIncluded($limites['ventana_inicio'], $limites['ventana_fin'])
                    || ($momento->lte($limites['jornada_fin_maximo']) && $jornadaAbierta);
            })
            ->sortByDesc(fn (AsignacionTurno $asignacion) => static::limites($asignacion)['inicio']->getTimestamp())
            ->first();
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
     * El cálculo se conserva en segundos para que una marcación nunca pierda
     * precisión. Los campos en minutos se mantienen como compatibilidad para
     * vistas existentes; no se usan para decidir cumplimiento ni extras.
     *
     * @return array{estado:string, efectivos_segundos:?int, objetivo_segundos:int, extras_segundos:?int, diferencia_segundos:?int, refrigerio_segundos:?int, efectivos_minutos:?int, objetivo_minutos:int, extras_minutos:?int, diferencia_minutos:?int, inconsistencias:array<int, string>}
     */
    public static function resumen(Colaborador $colaborador, AsignacionTurno $asignacion, ?Collection $marcaciones = null): array
    {
        $marcaciones ??= static::marcaciones($colaborador, $asignacion);
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salida = $marcaciones->filter(fn (Marcacion $marcacion) => $marcacion->tipo === Marcacion::TIPO_SALIDA)->last();
        $objetivoConfigurado = static::minutosObjetivo($asignacion);
        $objetivoConfiguradoSegundos = $objetivoConfigurado * 60;
        $inconsistencias = static::inconsistencias($marcaciones, $entrada, $salida);

        if ($inconsistencias !== []) {
            return static::resultadoInconsistente($objetivoConfigurado, $inconsistencias);
        }

        if (! $entrada || ! $salida) {
            return [
                'estado' => 'en_curso',
                'efectivos_segundos' => null,
                'objetivo_segundos' => $objetivoConfiguradoSegundos,
                'extras_segundos' => null,
                'diferencia_segundos' => null,
                'refrigerio_segundos' => null,
                'efectivos_minutos' => null,
                'objetivo_minutos' => $objetivoConfigurado,
                'extras_minutos' => null,
                'diferencia_minutos' => null,
                'inconsistencias' => [],
            ];
        }

        $refrigerioSegundos = 0;
        $inicioRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $finRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);
        if ($inicioRefrigerio && $finRefrigerio) {
            $refrigerioSegundos = max(0, $finRefrigerio->fecha_hora->getTimestamp() - $inicioRefrigerio->fecha_hora->getTimestamp());
        }

        $efectivosSegundos = max(0, $salida->fecha_hora->getTimestamp() - $entrada->fecha_hora->getTimestamp() - $refrigerioSegundos);
        // Un turno puede definir una meta de jornada completa superior a su
        // meta ordinaria. Solo se aplica cuando la permanencia real la
        // alcanza: así un turno mañana extendido se reconoce sin reasignar
        // al colaborador y sin imponer una regla global a los demás turnos.
        $objetivoJornadaCompleta = $asignacion->turno->horas_efectivas_jornada_completa_minutos;
        $objetivoSegundos = $objetivoJornadaCompleta !== null && $efectivosSegundos >= ((int) $objetivoJornadaCompleta * 60)
            ? (int) $objetivoJornadaCompleta * 60
            : $objetivoConfiguradoSegundos;
        $diferenciaSegundos = $efectivosSegundos - $objetivoSegundos;

        return [
            'estado' => $diferenciaSegundos < 0 ? 'pendiente' : ($diferenciaSegundos > 0 ? 'extendida' : 'cumplida'),
            'efectivos_segundos' => $efectivosSegundos,
            'objetivo_segundos' => $objetivoSegundos,
            'extras_segundos' => max(0, $diferenciaSegundos),
            'diferencia_segundos' => $diferenciaSegundos,
            'refrigerio_segundos' => $refrigerioSegundos,
            'efectivos_minutos' => static::segundosAMinutos($efectivosSegundos),
            'objetivo_minutos' => static::segundosAMinutos($objetivoSegundos),
            'extras_minutos' => static::segundosAMinutos(max(0, $diferenciaSegundos)),
            'diferencia_minutos' => static::segundosAMinutos($diferenciaSegundos),
            'inconsistencias' => [],
        ];
    }

    /** @return array<int, string> */
    private static function inconsistencias(Collection $marcaciones, ?Marcacion $entrada, ?Marcacion $salida): array
    {
        $inconsistencias = [];
        $conteos = $marcaciones->countBy('tipo');

        foreach ([Marcacion::TIPO_ENTRADA, Marcacion::TIPO_SALIDA, Marcacion::TIPO_SALIDA_REFRIGERIO, Marcacion::TIPO_REGRESO_REFRIGERIO] as $tipo) {
            if (($conteos[$tipo] ?? 0) > 1) {
                $inconsistencias[] = "{$tipo}_duplicada";
            }
        }

        $salidaRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $regresoRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);
        if (($salidaRefrigerio === null) !== ($regresoRefrigerio === null) && $salida !== null) {
            $inconsistencias[] = 'refrigerio_incompleto';
        }
        if ($salidaRefrigerio && $regresoRefrigerio && $regresoRefrigerio->fecha_hora->lte($salidaRefrigerio->fecha_hora)) {
            $inconsistencias[] = 'orden_refrigerio_invalido';
        }
        if ($entrada && $salida && $salida->fecha_hora->lte($entrada->fecha_hora)) {
            $inconsistencias[] = 'orden_jornada_invalido';
        }
        if ($salida && $marcaciones->contains(fn (Marcacion $marcacion): bool => $marcacion->fecha_hora->gt($salida->fecha_hora))) {
            $inconsistencias[] = 'evento_posterior_a_salida';
        }

        return array_values(array_unique($inconsistencias));
    }

    /** @return array{estado:string, efectivos_segundos:null, objetivo_segundos:int, extras_segundos:null, diferencia_segundos:null, refrigerio_segundos:null, efectivos_minutos:null, objetivo_minutos:int, extras_minutos:null, diferencia_minutos:null, inconsistencias:array<int, string>} */
    private static function resultadoInconsistente(int $objetivoMinutos, array $inconsistencias): array
    {
        return [
            'estado' => 'inconsistente',
            'efectivos_segundos' => null,
            'objetivo_segundos' => $objetivoMinutos * 60,
            'extras_segundos' => null,
            'diferencia_segundos' => null,
            'refrigerio_segundos' => null,
            'efectivos_minutos' => null,
            'objetivo_minutos' => $objetivoMinutos,
            'extras_minutos' => null,
            'diferencia_minutos' => null,
            'inconsistencias' => $inconsistencias,
        ];
    }

    private static function segundosAMinutos(int $segundos): int
    {
        return ($segundos < 0 ? -1 : 1) * intdiv(abs($segundos), 60);
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

    /**
     * Estado completo del panel de acciones de marcación.
     *
     * Se exponen siempre las cuatro acciones para que el colaborador vea el
     * flujo completo tipo terminal. La autorización real continúa siendo
     * validada en el controlador, dentro de la transacción de guardado.
     *
     * @return array<int, array{tipo: string, codigo: int, etiqueta: string, habilitada: bool, motivo: ?string}>
     */
    public static function acciones(Colaborador $colaborador, AsignacionTurno $asignacion): array
    {
        $siguientes = static::siguientesTipos($colaborador, $asignacion);
        $ultima = static::ultimaMarcacion($colaborador, $asignacion)?->tipo;

        return collect(static::ACCIONES)
            ->map(fn (array $accion, string $tipo): array => [
                'tipo' => $tipo,
                'codigo' => $accion['codigo'],
                'etiqueta' => $accion['etiqueta'],
                'habilitada' => in_array($tipo, $siguientes, true),
                'motivo' => in_array($tipo, $siguientes, true)
                    ? null
                    : static::motivoAccionNoDisponible($tipo, $ultima, $asignacion),
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
                ? 'Este turno registra solo entrada.'
                : (! $asignacion->turno->incluye_refrigerio
                    ? 'Este turno no incluye refrigerio.'
                    : match ($ultima) {
                        null => 'Registra primero tu entrada.',
                        Marcacion::TIPO_SALIDA_REFRIGERIO => 'Confirma primero tu ingreso de refrigerio.',
                        Marcacion::TIPO_REGRESO_REFRIGERIO => 'El refrigerio ya fue registrado.',
                        Marcacion::TIPO_SALIDA => 'Tu jornada ya fue cerrada.',
                        default => 'Esta acción no está disponible.',
                    }),
            Marcacion::TIPO_REGRESO_REFRIGERIO => match ($ultima) {
                null => 'Registra primero tu entrada.',
                Marcacion::TIPO_ENTRADA => 'Inicia primero tu refrigerio.',
                Marcacion::TIPO_REGRESO_REFRIGERIO => 'Ya registraste tu ingreso de refrigerio.',
                Marcacion::TIPO_SALIDA => 'Tu jornada ya fue cerrada.',
                default => 'Esta acción no está disponible.',
            },
            Marcacion::TIPO_SALIDA => match ($ultima) {
                null => 'Registra primero tu entrada.',
                Marcacion::TIPO_SALIDA_REFRIGERIO => 'Registra primero tu ingreso de refrigerio.',
                Marcacion::TIPO_SALIDA => 'Tu jornada ya fue cerrada.',
                default => 'Esta acción no está disponible.',
            },
            default => 'Esta acción no está disponible.',
        };
    }
}
