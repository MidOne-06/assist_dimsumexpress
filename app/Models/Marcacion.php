<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colaborador_id', 'tipo', 'fecha_hora', 'turno_id', 'qr_token_id', 'sucursal_id', 'punto_venta_id', 'ip_origen', 'user_agent'])]
class Marcacion extends Model
{
    protected $table = 'marcaciones';

    public const TIPO_ENTRADA = 'entrada';
    public const TIPO_SALIDA = 'salida';
    public const TIPO_SALIDA_REFRIGERIO = 'salida_refrigerio';
    public const TIPO_REGRESO_REFRIGERIO = 'regreso_refrigerio';

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function qrToken(): BelongsTo
    {
        return $this->belongsTo(QrToken::class);
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
