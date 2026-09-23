<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asignacion_turno_id',
    'colaborador_id',
    'sucursal_id',
    'punto_venta_id',
    'origen',
    'estado',
    'detectada_en',
    'revisada_en',
    'revisada_por_id',
    'observacion_revision',
])]
class CoberturaOperativa extends Model
{
    protected $table = 'coberturas_operativas';

    public const ORIGEN_AUTOMATICA = 'automatica';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_REVISADA = 'revisada';

    public const ESTADO_OBSERVADA = 'observada';

    protected function casts(): array
    {
        return [
            'detectada_en' => 'datetime',
            'revisada_en' => 'datetime',
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

    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por_id');
    }
}
