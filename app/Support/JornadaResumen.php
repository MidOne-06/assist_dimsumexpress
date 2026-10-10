<?php

namespace App\Support;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use Illuminate\Support\Collection;

/** Calcula cumplimiento, horas efectivas e inconsistencias de una jornada. */
final class JornadaResumen
{
    /**
     * El cálculo se conserva en segundos para que una marcación nunca pierda
     * precisión. Los campos en minutos se mantienen como compatibilidad para
     * vistas existentes; no se usan para decidir cumplimiento ni extras.
     *
     * @return array{estado:string, efectivos_segundos:?int, objetivo_segundos:int, extras_segundos:?int, diferencia_segundos:?int, refrigerio_segundos:?int, efectivos_minutos:?int, objetivo_minutos:int, extras_minutos:?int, diferencia_minutos:?int, inconsistencias:array<int, string>}
     */
    public static function calcular(Colaborador $colaborador, AsignacionTurno $asignacion, ?Collection $marcaciones = null): array
    {
        $marcaciones ??= JornadaMarcacion::marcaciones($colaborador, $asignacion);
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salida = $marcaciones->filter(fn (Marcacion $marcacion) => $marcacion->tipo === Marcacion::TIPO_SALIDA)->last();
        $objetivoConfigurado = JornadaMarcacion::minutosObjetivo($asignacion);
        $objetivoConfiguradoSegundos = $objetivoConfigurado * 60;
        $inconsistencias = self::inconsistencias($marcaciones, $entrada, $salida);

        if ($inconsistencias !== []) {
            return self::resultadoInconsistente($objetivoConfigurado, $inconsistencias);
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

        $brutosSegundos = max(0, $salida->fecha_hora->getTimestamp() - $entrada->fecha_hora->getTimestamp());
        $refrigerioMarcado = $inicioRefrigerio !== null && $finRefrigerio !== null;
        // Si el turno exige refrigerio pero no hubo ninguna marca, la
        // permanencia adicional no se convierte en horas efectivas: se aplica
        // la meta configurada (p. ej. 08:00–17:00 cuenta máximo 8 h).
        $efectivosSegundos = $asignacion->turno->incluye_refrigerio && ! $refrigerioMarcado
            ? min($brutosSegundos, $objetivoConfiguradoSegundos)
            : max(0, $brutosSegundos - $refrigerioSegundos);
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
            'efectivos_minutos' => self::segundosAMinutos($efectivosSegundos),
            'objetivo_minutos' => self::segundosAMinutos($objetivoSegundos),
            'extras_minutos' => self::segundosAMinutos(max(0, $diferenciaSegundos)),
            'diferencia_minutos' => self::segundosAMinutos($diferenciaSegundos),
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


}
