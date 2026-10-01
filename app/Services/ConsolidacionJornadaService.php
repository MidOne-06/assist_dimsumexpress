<?php

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\ResumenJornada;
use App\Support\JornadaMarcacion;
use Illuminate\Support\Collection;

class ConsolidacionJornadaService
{
    /**
     * Crea una fotografía inmutable al cerrar la jornada. Un resumen ya
     * existente no se recalcula ni se sobrescribe: la trazabilidad debe
     * conservar exactamente las marcaciones que cerraron ese día.
     *
     * @param Collection<int, Marcacion>|null $marcaciones
     */
    public function consolidar(Colaborador $colaborador, AsignacionTurno $asignacion, ?Collection $marcaciones = null): ?ResumenJornada
    {
        $marcaciones ??= JornadaMarcacion::marcaciones($colaborador, $asignacion);
        $resumen = JornadaMarcacion::resumen($colaborador, $asignacion, $marcaciones);

        if (! in_array($resumen['estado'], ['cumplida', 'extendida', 'pendiente'], true)) {
            return null;
        }

        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salida = $marcaciones->filter(fn (Marcacion $marcacion): bool => $marcacion->tipo === Marcacion::TIPO_SALIDA)->last();
        if (! $entrada || ! $salida) {
            return null;
        }

        $salidaRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $regresoRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);
        $huella = hash('sha256', $marcaciones->map(fn (Marcacion $marcacion): string => implode('|', [$marcacion->id, $marcacion->tipo, $marcacion->fecha_hora->format('Y-m-d H:i:s')]))->implode(';'));

        return ResumenJornada::firstOrCreate(
            ['asignacion_turno_id' => $asignacion->id],
            [
                'colaborador_id' => $colaborador->id,
                'empresa_id' => $entrada->empresa_id ?? $colaborador->empresa_id,
                'area_id' => $entrada->area_id ?? $colaborador->area_id,
                'sucursal_id' => $entrada->sucursal_id,
                'punto_venta_id' => $entrada->punto_venta_id,
                'entrada_marcacion_id' => $entrada->id,
                'salida_marcacion_id' => $salida->id,
                'salida_refrigerio_marcacion_id' => $salidaRefrigerio?->id,
                'regreso_refrigerio_marcacion_id' => $regresoRefrigerio?->id,
                'fecha_jornada' => $asignacion->fecha->toDateString(),
                'estado' => $resumen['estado'],
                'efectivos_segundos' => $resumen['efectivos_segundos'],
                'objetivo_segundos' => $resumen['objetivo_segundos'],
                'diferencia_segundos' => $resumen['diferencia_segundos'],
                'extras_segundos' => $resumen['extras_segundos'],
                'refrigerio_segundos' => $resumen['refrigerio_segundos'],
                'consolidado_en' => now(),
                'calculo_version' => 1,
                'huella_marcaciones' => $huella,
                'detalle' => [
                    'turno' => $asignacion->turno?->nombre,
                    'entrada' => $entrada->fecha_hora->format('Y-m-d H:i:s'),
                    'salida' => $salida->fecha_hora->format('Y-m-d H:i:s'),
                    'salida_refrigerio' => $salidaRefrigerio?->fecha_hora?->format('Y-m-d H:i:s'),
                    'regreso_refrigerio' => $regresoRefrigerio?->fecha_hora?->format('Y-m-d H:i:s'),
                    'estacion' => [
                        'sucursal_id' => $entrada->sucursal_id,
                        'punto_venta_id' => $entrada->punto_venta_id,
                    ],
                ],
            ],
        );
    }
}
