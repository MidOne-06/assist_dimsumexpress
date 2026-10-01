<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asignacion_turno_id',
    'colaborador_id',
    'empresa_id',
    'area_id',
    'sucursal_id',
    'punto_venta_id',
    'entrada_marcacion_id',
    'salida_marcacion_id',
    'salida_refrigerio_marcacion_id',
    'regreso_refrigerio_marcacion_id',
    'fecha_jornada',
    'estado',
    'efectivos_segundos',
    'objetivo_segundos',
    'diferencia_segundos',
    'extras_segundos',
    'refrigerio_segundos',
    'consolidado_en',
    'calculo_version',
    'huella_marcaciones',
    'detalle',
])]
class ResumenJornada extends Model
{
    protected $table = 'resumenes_jornada';

    protected function casts(): array
    {
        return [
            'fecha_jornada' => 'date',
            'efectivos_segundos' => 'integer',
            'objetivo_segundos' => 'integer',
            'diferencia_segundos' => 'integer',
            'extras_segundos' => 'integer',
            'refrigerio_segundos' => 'integer',
            'consolidado_en' => 'datetime',
            'calculo_version' => 'integer',
            'detalle' => 'array',
        ];
    }

    public function asignacionTurno(): BelongsTo
    {
        return $this->belongsTo(AsignacionTurno::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }
}
