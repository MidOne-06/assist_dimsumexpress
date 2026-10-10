<?php

namespace App\Support;

use App\Models\Marcacion;

/**
 * Traduce una marcación ya persistida a la información mínima que verá el
 * colaborador al confirmar. No decide el flujo: esa decisión sigue viviendo
 * en JornadaMarcacion y queda validada antes de crear la marcación.
 */
final class PresentacionConfirmacionMarcacion
{
    /** @return array{saludo:string,evento:string,color:string,icono:string,nombre:string} */
    public static function para(Marcacion $marcacion): array
    {
        $presentacion = match ($marcacion->tipo) {
            Marcacion::TIPO_ENTRADA => [
                'saludo' => '¡Bienvenido!',
                'evento' => 'Ingreso de turno registrado',
                'color' => 'success',
                'icono' => 'heroicon-s-arrow-right-circle',
            ],
            Marcacion::TIPO_SALIDA_REFRIGERIO => [
                'saludo' => '¡Buen provecho!',
                'evento' => 'Salida a refrigerio registrada',
                'color' => 'warning',
                'icono' => 'heroicon-s-clock',
            ],
            Marcacion::TIPO_REGRESO_REFRIGERIO => [
                'saludo' => '¡Bienvenido de vuelta!',
                'evento' => 'Ingreso de refrigerio registrado',
                'color' => 'info',
                'icono' => 'heroicon-s-arrow-right-circle',
            ],
            Marcacion::TIPO_SALIDA => [
                'saludo' => '¡Nos vemos!',
                'evento' => 'Salida de turno registrada',
                'color' => 'danger',
                'icono' => 'heroicon-s-arrow-left-circle',
            ],
            default => [
                'saludo' => 'Marcación registrada',
                'evento' => 'Registro confirmado',
                'color' => 'success',
                'icono' => 'heroicon-s-check-circle',
            ],
        };

        return $presentacion + [
            'nombre' => trim((string) $marcacion->colaborador?->nombre_completo) ?: 'Colaborador',
        ];
    }
}
