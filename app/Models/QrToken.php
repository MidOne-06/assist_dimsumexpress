<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['sucursal_id', 'punto_venta_id', 'token', 'proposito', 'expira_en'])]
class QrToken extends Model
{
    public const PROPOSITO_ASISTENCIA = 'asistencia';

    public const PROPOSITO_VISITA_SUPERVISOR = 'visita_supervisor';

    protected $table = 'qr_tokens';

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    public function visitasSupervisor(): HasMany
    {
        return $this->hasMany(VisitaSupervisor::class);
    }

    public function vigente(): bool
    {
        return $this->expira_en->isFuture();
    }

    public function vigentePara(string $proposito): bool
    {
        return $this->proposito === $proposito && $this->vigente();
    }

    public static function generarPara(
        Sucursal $sucursal,
        ?PuntoVenta $puntoVenta = null,
        int $vigenciaSegundos = 20,
        string $proposito = self::PROPOSITO_ASISTENCIA,
    ): self
    {
        return self::create([
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta?->id,
            'token' => Str::random(48),
            'proposito' => $proposito,
            'expira_en' => now()->addSeconds($vigenciaSegundos),
        ]);
    }
}
