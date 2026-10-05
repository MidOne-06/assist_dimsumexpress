<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'visita_supervisor_id', 'supervisor_id', 'sucursal_id', 'punto_venta_id', 'qr_token_id',
    'tipo', 'fecha_hora', 'ip_origen', 'user_agent', 'metadata',
])]
class VisitaSupervisorMarcacion extends Model
{
    public const INGRESO = 'ingreso';
    public const SALIDA = 'salida';
    public const REGULARIZACION = 'regularizacion';

    protected $table = 'visita_supervisor_marcaciones';

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function visitaSupervisor(): BelongsTo
    {
        return $this->belongsTo(VisitaSupervisor::class);
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

    public function qrToken(): BelongsTo
    {
        return $this->belongsTo(QrToken::class);
    }
}
