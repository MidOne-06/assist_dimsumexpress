<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'sucursal_id', 'punto_venta_id', 'nombre_completo', 'documento_identidad', 'cargo', 'fecha_ingreso', 'activo'])]
class Colaborador extends Model
{
    protected $table = 'colaboradores';

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    public function asignacionesTurno(): HasMany
    {
        return $this->hasMany(AsignacionTurno::class);
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    public function turnoDelDia(?string $fecha = null): ?Turno
    {
        $asignacion = $this->asignacionesTurno()
            ->where('fecha', $fecha ?? now()->toDateString())
            ->first();

        return $asignacion?->turno;
    }
}
