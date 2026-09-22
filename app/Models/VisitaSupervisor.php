<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supervisor_id', 'sucursal_id', 'punto_venta_id', 'fecha', 'fecha_hora', 'ip_origen', 'user_agent'])]
class VisitaSupervisor extends Model
{
    protected $table = 'visitas_supervisor';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_hora' => 'datetime',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
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
