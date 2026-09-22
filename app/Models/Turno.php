<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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

    /** @param array<string, mixed> $atributos */
    public function actualizarParaFuturo(array $atributos): self
    {
        $atributos = Arr::only($atributos, $this->getFillable());

        if (! $this->asignaciones()->where('fecha', '<=', today())->exists()) {
            $this->update($atributos);

            return $this;
        }

        return DB::transaction(function () use ($atributos): self {
            $nuevoTurno = static::create($atributos);

            $this->asignaciones()
                ->where('fecha', '>', today())
                ->update(['turno_id' => $nuevoTurno->id]);

            // Esta versión queda como evidencia del horario ya aplicado.
            $this->update(['activo' => false]);

            return $nuevoTurno;
        });
    }
}
