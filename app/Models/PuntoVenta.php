<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

// token_pantalla NO está en Fillable a propósito: es el secreto de la
// estación física, se genera solo (ver booted()) y nunca debe poder
// llegar por un formulario ni por mass-assignment.
#[Fillable(['sucursal_id', 'nombre', 'activo'])]
class PuntoVenta extends Model
{
    protected $table = 'puntos_venta';

    protected static function booted(): void
    {
        static::creating(function (PuntoVenta $puntoVenta) {
            $puntoVenta->token_pantalla ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class);
    }

    /**
     * Enlace estable de la estación física para este punto de venta (incluye
     * la clave real). No hay otra forma de recuperarlo desde el panel -- sin
     * esto, configurar una pantalla física obligaba a leer la base de datos
     * a mano (hallazgo de la auditoría, 2026-09-18).
     */
    public function enlaceEstacion(): string
    {
        return route('estacion-marcado.show', [
            'sucursal' => $this->sucursal_id,
            'puntoVenta' => $this->id,
            'clave' => $this->token_pantalla,
        ]);
    }
}
