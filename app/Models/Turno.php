<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'hora_inicio', 'hora_fin', 'cruza_medianoche', 'tolerancia_entrada_minutos', 'tolerancia_salida_minutos', 'activo'])]
class Turno extends Model
{
    protected function casts(): array
    {
        return [
            'cruza_medianoche' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionTurno::class);
    }
}
