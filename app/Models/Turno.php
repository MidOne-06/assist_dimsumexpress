<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['nombre', 'hora_inicio', 'hora_fin', 'cruza_medianoche', 'tolerancia_entrada_minutos', 'tolerancia_salida_minutos', 'solo_entrada', 'jornada_abierta', 'incluye_refrigerio', 'refrigerio_minutos', 'horas_efectivas_objetivo_minutos', 'horas_efectivas_jornada_completa_minutos', 'activo'])]
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
            'jornada_abierta' => 'boolean',
            'incluye_refrigerio' => 'boolean',
            'refrigerio_minutos' => 'integer',
            'horas_efectivas_objetivo_minutos' => 'integer',
            'horas_efectivas_jornada_completa_minutos' => 'integer',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $turno): void {
            $inicio = static::hora($turno->hora_inicio, 'hora_inicio');

            // Un turno de solo entrada no tiene cierre de jornada. Se deja la
            // salida en null para no inventar un horario que no existe.
            if ($turno->solo_entrada) {
                $turno->hora_fin = null;
                $turno->cruza_medianoche = false;
                $turno->tolerancia_salida_minutos = 0;
                $turno->jornada_abierta = false;
                $turno->incluye_refrigerio = false;
                $turno->refrigerio_minutos = 0;
                $turno->horas_efectivas_objetivo_minutos = 0;
                $turno->horas_efectivas_jornada_completa_minutos = null;

                return;
            }

            // La jornada abierta conserva el flujo completo de marcaciones,
            // pero no fija una hora de salida. El límite técnico se aplica en
            // JornadaMarcacion para evitar jornadas indefinidas.
            if ($turno->jornada_abierta) {
                $turno->hora_fin = null;
                $turno->cruza_medianoche = false;
                $turno->tolerancia_salida_minutos = 0;

                return;
            }

            $fin = static::hora($turno->hora_fin, 'hora_fin');

            if (! $turno->cruza_medianoche && $fin->lte($inicio)) {
                throw ValidationException::withMessages([
                    'cruza_medianoche' => 'Activa Cruza medianoche si la salida corresponde al día siguiente.',
                ]);
            }

            if ($turno->cruza_medianoche && $fin->gte($inicio)) {
                throw ValidationException::withMessages([
                    'hora_fin' => 'En un turno nocturno, la hora de fin debe ser anterior a la hora de inicio.',
                ]);
            }

            if ($turno->cruza_medianoche) {
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

            $objetivoJornadaCompleta = $turno->horas_efectivas_jornada_completa_minutos;
            if ($objetivoJornadaCompleta !== null && (int) $objetivoJornadaCompleta <= $objetivo) {
                throw ValidationException::withMessages([
                    'horas_efectivas_jornada_completa_minutos' => 'La meta de jornada completa debe ser mayor a las horas efectivas requeridas.',
                ]);
            }
        });
    }

    private static function hora(?string $hora, string $campo): Carbon
    {
        $valor = trim((string) $hora);

        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $valor)) {
            throw ValidationException::withMessages([$campo => 'Ingresa una hora válida.']);
        }

        return Carbon::parse('2000-01-01 ' . $valor);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionTurno::class);
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    /** @param array<string, mixed> $atributos */
    public function actualizarParaFuturo(array $atributos): self
    {
        $atributos = array_replace(
            Arr::only($this->getAttributes(), $this->getFillable()),
            Arr::only($atributos, $this->getFillable()),
        );

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
