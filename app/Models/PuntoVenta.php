<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sucursal_id', 'nombre', 'activo'])]
class PuntoVenta extends Model
{
    protected $table = 'puntos_venta';

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
