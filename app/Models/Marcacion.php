<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colaborador_id', 'tipo', 'fecha_hora', 'turno_id', 'qr_token_id', 'sucursal_id', 'punto_venta_id', 'ip_origen', 'user_agent', 'refrigerio_retorno_esperado_en', 'refrigerio_diferencia_segundos'])]
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
            'refrigerio_retorno_esperado_en' => 'datetime',
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

    /** @return array{etiqueta: string, estado: string, esperado: \Carbon\Carbon}|null */
    public function resumenRetornoRefrigerio(): ?array
    {
        if ($this->tipo !== self::TIPO_REGRESO_REFRIGERIO) {
            return null;
        }

        $esperado = $this->refrigerio_retorno_esperado_en;
        $diferenciaSegundos = $this->refrigerio_diferencia_segundos;

        // Compatibilidad con marcaciones históricas anteriores a este control.
        if (! $esperado || $diferenciaSegundos === null) {
            $salida = static::query()
                ->where('colaborador_id', $this->colaborador_id)
                ->where('tipo', self::TIPO_SALIDA_REFRIGERIO)
                ->where('fecha_hora', '<=', $this->fecha_hora)
                ->when($this->turno_id, fn ($query) => $query->where('turno_id', $this->turno_id))
                ->orderByDesc('fecha_hora')
                ->orderByDesc('id')
                ->first();

            if (! $salida) {
                return null;
            }

            $esperado = $salida->fecha_hora->copy()->addHour();
            $diferenciaSegundos = $this->fecha_hora->getTimestamp() - $esperado->getTimestamp();
        }

        if ($diferenciaSegundos === 0) {
            return ['etiqueta' => 'A tiempo', 'estado' => 'puntual', 'esperado' => $esperado];
        }

        $minutos = (int) ceil(abs($diferenciaSegundos) / 60);

        return [
            'etiqueta' => $minutos . ' min ' . ($diferenciaSegundos > 0 ? 'tarde' : 'antes'),
            'estado' => $diferenciaSegundos > 0 ? 'tarde' : 'temprano',
            'esperado' => $esperado,
        ];
    }
}
