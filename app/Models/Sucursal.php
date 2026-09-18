<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

// token_pantalla NO está en Fillable a propósito: es el secreto de la
// estación física, se genera solo (ver booted()) y nunca debe poder
// llegar por un formulario ni por mass-assignment.
#[Fillable(['nombre', 'tipo', 'direccion', 'activo'])]
class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected static function booted(): void
    {
        static::creating(function (Sucursal $sucursal) {
            $sucursal->token_pantalla ??= Str::random(40);
        });
    }

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
