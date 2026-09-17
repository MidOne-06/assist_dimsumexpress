<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'tipo', 'direccion', 'activo'])]
class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function esPlanta(): bool
    {
        return $this->tipo === 'planta';
    }

    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class);
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(QrToken::class);
    }
}
