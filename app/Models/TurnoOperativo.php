<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turno_id', 'sucursal_id', 'punto_venta_id', 'prioridad', 'activo'])]
class TurnoOperativo extends Model
{
    protected function casts(): array
    {
        return ['activo' => 'boolean', 'prioridad' => 'integer'];
    }

    public function turno(): BelongsTo { return $this->belongsTo(Turno::class); }
    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class); }
    public function puntoVenta(): BelongsTo { return $this->belongsTo(PuntoVenta::class); }
}
