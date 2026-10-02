<?php

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Marcacion;
use App\Support\JornadaMarcacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Traduce las marcaciones de una jornada a segmentos visuales. La fuente de
 * verdad siempre son las marcaciones: esta clase no crea ni modifica datos.
 */
final class ControlJornadaService
{
    /**
     * @param  Collection<int, Marcacion>  $marcaciones
     * @return array<string, array{aplica: bool, estado: string, hora: ?string, etiqueta: string}>
     */
    public function segmentos(AsignacionTurno $asignacion, Collection $marcaciones, ?Carbon $referencia = null): array
    {
        $referencia ??= now();
        $turno = $asignacion->turno;
        $limites = JornadaMarcacion::limites($asignacion);
        $marcaciones = $marcaciones
            ->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === $asignacion->turno_id
                && $marcacion->fecha_hora->betweenIncluded($limites['ventana_inicio'], $limites['jornada_fin_maximo']))
            ->sortBy(fn (Marcacion $marcacion): array => [$marcacion->fecha_hora->getTimestamp(), $marcacion->id])
            ->values();

        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salidaRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $regresoRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);
        $salida = $marcaciones->filter(fn (Marcacion $marcacion): bool => $marcacion->tipo === Marcacion::TIPO_SALIDA)->last();

        $entradaLimite = $limites['inicio']->copy()->addMinutes((int) $turno->tolerancia_entrada_minutos);
        $finLimite = $limites['fin']->copy()->addMinutes((int) $turno->tolerancia_salida_minutos);
        $jornadaVencida = $referencia->gt($finLimite);

        $segmentos = [
            'entrada' => $this->entrada($entrada, $entradaLimite, $referencia),
            'salida_refrigerio' => $this->salidaRefrigerio(
                $turno->solo_entrada || ! $turno->incluye_refrigerio,
                $salidaRefrigerio,
                $entrada,
                $salida,
                $jornadaVencida,
            ),
            'regreso_refrigerio' => $this->regresoRefrigerio(
                $turno->solo_entrada || ! $turno->incluye_refrigerio,
                $salidaRefrigerio,
                $regresoRefrigerio,
                (int) $turno->refrigerio_minutos,
                $referencia,
            ),
            'salida' => $this->salida(
                $turno->solo_entrada || $turno->jornada_abierta,
                $salida,
                $entrada,
                $limites['fin'],
                $jornadaVencida,
            ),
        ];

        if (JornadaMarcacion::resumen($asignacion->colaborador, $asignacion, $marcaciones)['estado'] === 'inconsistente') {
            foreach ($segmentos as $clave => $segmento) {
                if ($segmento['aplica'] && $segmento['estado'] !== 'completo') {
                    $segmentos[$clave]['estado'] = 'inconsistente';
                    $segmentos[$clave]['etiqueta'] .= ' · Secuencia inconsistente';
                }
            }
        }

        return $segmentos;
    }

    /** @return array{aplica: bool, estado: string, hora: ?string, etiqueta: string} */
    private function entrada(?Marcacion $entrada, Carbon $limite, Carbon $referencia): array
    {
        if (! $entrada) {
            return $this->segmento(true, $referencia->gt($limite) ? 'faltante' : 'pendiente', null, 'Entrada pendiente');
        }

        $tarde = $entrada->fecha_hora->gt($limite);

        return $this->segmento(true, $tarde ? 'tardanza' : 'completo', $entrada->fecha_hora, $tarde ? 'Entrada con tardanza' : 'Entrada registrada');
    }

    /** @return array{aplica: bool, estado: string, hora: ?string, etiqueta: string} */
    private function salidaRefrigerio(bool $noAplica, ?Marcacion $salidaRefrigerio, ?Marcacion $entrada, ?Marcacion $salida, bool $jornadaVencida): array
    {
        if ($noAplica) {
            return $this->noAplica('Sin refrigerio programado');
        }

        if ($salidaRefrigerio) {
            return $this->segmento(true, 'completo', $salidaRefrigerio->fecha_hora, 'Salida a refrigerio registrada');
        }

        $faltante = $entrada !== null && ($salida !== null || $jornadaVencida);

        return $this->segmento(true, $faltante ? 'faltante' : 'pendiente', null, 'Salida a refrigerio pendiente');
    }

    /** @return array{aplica: bool, estado: string, hora: ?string, etiqueta: string} */
    private function regresoRefrigerio(bool $noAplica, ?Marcacion $salidaRefrigerio, ?Marcacion $regresoRefrigerio, int $minutos, Carbon $referencia): array
    {
        if ($noAplica) {
            return $this->noAplica('Sin refrigerio programado');
        }

        if (! $salidaRefrigerio) {
            return $this->segmento(true, 'no_iniciado', null, 'Refrigerio no iniciado');
        }

        $esperado = $salidaRefrigerio->fecha_hora->copy()->addMinutes($minutos);
        if (! $regresoRefrigerio) {
            return $this->segmento(true, $referencia->gt($esperado) ? 'faltante' : 'pendiente', null, 'Retorno de refrigerio pendiente');
        }

        $tarde = $regresoRefrigerio->fecha_hora->gt($esperado);

        return $this->segmento(true, $tarde ? 'tardanza' : 'completo', $regresoRefrigerio->fecha_hora, $tarde ? 'Retorno de refrigerio tardío' : 'Retorno de refrigerio registrado');
    }

    /** @return array{aplica: bool, estado: string, hora: ?string, etiqueta: string} */
    private function salida(bool $noAplica, ?Marcacion $salida, ?Marcacion $entrada, Carbon $fin, bool $jornadaVencida): array
    {
        if ($noAplica) {
            return $this->noAplica('Salida no requerida por este turno');
        }

        if (! $salida) {
            return $this->segmento(true, $entrada && $jornadaVencida ? 'faltante' : 'pendiente', null, 'Salida de turno pendiente');
        }

        $anticipada = $salida->fecha_hora->lt($fin);

        return $this->segmento(true, $anticipada ? 'tardanza' : 'completo', $salida->fecha_hora, $anticipada ? 'Salida anticipada' : 'Salida de turno registrada');
    }

    /** @return array{aplica: bool, estado: string, hora: ?string, etiqueta: string} */
    private function noAplica(string $etiqueta): array
    {
        return ['aplica' => false, 'estado' => 'no_aplica', 'hora' => null, 'etiqueta' => $etiqueta];
    }

    /** @return array{aplica: bool, estado: string, hora: ?string, etiqueta: string} */
    private function segmento(bool $aplica, string $estado, ?Carbon $hora, string $etiqueta): array
    {
        return [
            'aplica' => $aplica,
            'estado' => $estado,
            'hora' => $hora?->format('H:i'),
            'etiqueta' => $etiqueta,
        ];
    }
}
