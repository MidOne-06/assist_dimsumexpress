<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['nombre', 'hora_inicio', 'hora_fin', 'cruza_medianoche', 'tolerancia_entrada_minutos', 'tolerancia_salida_minutos', 'solo_entrada', 'incluye_refrigerio', 'refrigerio_minutos', 'horas_efectivas_objetivo_minutos', 'activo'])]
class Turno extends Model
{
    protected $attributes = [
        'incluye_refrigerio' => true,
        'refrigerio_minutos' => 60,
    ];

    protected function casts(): array
    {
        return [
            'cruza_medianoche' => 'boolean',
            'solo_entrada' => 'boolean',
            'incluye_refrigerio' => 'boolean',
            'refrigerio_minutos' => 'integer',
            'horas_efectivas_objetivo_minutos' => 'integer',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $turno): void {
            if ($turno->solo_entrada) {
                return;
            }

            $inicio = Carbon::parse('2000-01-01 ' . $turno->hora_inicio);
            $fin = Carbon::parse('2000-01-01 ' . $turno->hora_fin);
            if ($turno->cruza_medianoche || $fin->lte($inicio)) {
                $fin->addDay();
            }

            $duracionProgramada = $inicio->diffInMinutes($fin);
            $refrigerio = $turno->incluye_refrigerio ? (int) $turno->refrigerio_minutos : 0;
            $objetivo = (int) $turno->horas_efectivas_objetivo_minutos;
            if ($objetivo < 1) {
                $objetivo = max(1, $duracionProgramada - $refrigerio);
                $turno->horas_efectivas_objetivo_minutos = $objetivo;
            }

            if ($refrigerio < 0 || ($objetivo + $refrigerio) > $duracionProgramada) {
                throw ValidationException::withMessages([
                    'horas_efectivas_objetivo_minutos' => 'El horario debe cubrir las horas efectivas objetivo más el refrigerio configurado.',
                ]);
            }
        });
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
