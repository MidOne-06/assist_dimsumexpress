<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'codigo', 'ruc', 'activo'])]
class Empresa extends Model
{
    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class);
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
