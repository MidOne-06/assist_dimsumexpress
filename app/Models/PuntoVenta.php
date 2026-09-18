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
}
