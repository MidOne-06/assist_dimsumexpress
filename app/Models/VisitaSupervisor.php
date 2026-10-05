<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'supervisor_id', 'sucursal_id', 'punto_venta_id', 'qr_token_id', 'fecha', 'fecha_hora', 'ip_origen', 'user_agent',
    'estado', 'ingreso_en', 'salida_en', 'punto_venta_ingreso_id', 'punto_venta_salida_id',
    'ingreso_qr_token_id', 'salida_qr_token_id', 'ingreso_ip_origen', 'salida_ip_origen',
    'ingreso_user_agent', 'salida_user_agent', 'regularizada_por_id', 'regularizada_en', 'regularizacion_motivo',
])]
class VisitaSupervisor extends Model
{
    public const EN_CURSO = 'en_curso';
    public const FINALIZADA = 'finalizada';
    public const REGULARIZADA = 'regularizada';
    public const HISTORICA = 'historica';

    protected $table = 'visitas_supervisor';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_hora' => 'datetime',
            'ingreso_en' => 'datetime',
            'salida_en' => 'datetime',
            'regularizada_en' => 'datetime',
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

    public function qrToken(): BelongsTo
    {
        return $this->belongsTo(QrToken::class);
    }

    public function puntoVentaIngreso(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class, 'punto_venta_ingreso_id');
    }

    public function puntoVentaSalida(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class, 'punto_venta_salida_id');
    }

    public function ingresoQrToken(): BelongsTo
    {
        return $this->belongsTo(QrToken::class, 'ingreso_qr_token_id');
    }

    public function salidaQrToken(): BelongsTo
    {
        return $this->belongsTo(QrToken::class, 'salida_qr_token_id');
    }

    public function regularizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regularizada_por_id');
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(VisitaSupervisorMarcacion::class);
    }

    public function duracionEnSegundos(): ?int
    {
        if (! $this->ingreso_en || ! $this->salida_en) {
            return null;
        }

        return max(0, $this->ingreso_en->diffInSeconds($this->salida_en, false));
    }
}
