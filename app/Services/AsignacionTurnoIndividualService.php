<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Turno;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AsignacionTurnoIndividualService
{
    /** @param array<string, mixed> $data */
    public function crear(User $usuario, array $data): AsignacionTurno
    {
        abort_unless($usuario->can('Create:AsignacionTurno'), 403);

        [$colaborador, $fecha, $turnoId] = $this->datosValidos($usuario, $data, null, true);

        return AsignacionTurno::query()->create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turnoId,
            'fecha' => $fecha->toDateString(),
            'observacion' => $this->observacion($data),
            'asignado_por' => $usuario->id,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function actualizar(User $usuario, AsignacionTurno $asignacion, array $data): AsignacionTurno
    {
        abort_unless($usuario->can('update', $asignacion), 403);

        [$colaborador, $fecha, $turnoId] = $this->datosValidos($usuario, $data, $asignacion, false);

        $asignacion->update([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turnoId,
            'fecha' => $fecha->toDateString(),
            'observacion' => $this->observacion($data),
            'asignado_por' => $usuario->id,
        ]);

        return $asignacion;
    }

    public function eliminar(User $usuario, AsignacionTurno $asignacion): bool
    {
        abort_unless($usuario->can('delete', $asignacion), 403);

        return (bool) $asignacion->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Colaborador, 1: Carbon, 2: int}
     */
    private function datosValidos(User $usuario, array $data, ?AsignacionTurno $ignorando, bool $permiteHoy): array
    {
        $colaboradorId = (int) ($data['colaborador_id'] ?? 0);
        $turnoId = (int) ($data['turno_id'] ?? 0);

        if (blank($data['fecha'] ?? null)) {
            throw ValidationException::withMessages(['fecha' => 'Selecciona una fecha.']);
        }

        $fecha = Carbon::parse((string) $data['fecha'])->startOfDay();

        if ($fecha->lt(today()) || (! $permiteHoy && $fecha->isToday())) {
            throw ValidationException::withMessages([
                'fecha' => $permiteHoy
                    ? 'No se pueden programar turnos en fechas pasadas.'
                    : 'Solo se pueden modificar asignaciones futuras.',
            ]);
        }

        $colaborador = Colaborador::query()
            ->whereKey($colaboradorId)
            ->where('activo', true)
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds($usuario))
            ->first();

        abort_unless($colaborador, 403);

        if (! Turno::query()->whereKey($turnoId)->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['turno_id' => 'Selecciona un turno activo.']);
        }

        if ($fecha->isToday() && Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('fecha_hora', $fecha)
            ->exists()) {
            throw ValidationException::withMessages([
                'fecha' => 'No se puede cambiar el turno de hoy porque ya existen marcaciones.',
            ]);
        }

        if (AsignacionTurno::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('fecha', $fecha)
            ->when($ignorando, fn ($query) => $query->whereKeyNot($ignorando->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'fecha' => 'Este colaborador ya tiene un turno asignado en esa fecha.',
            ]);
        }

        return [$colaborador, $fecha, $turnoId];
    }

    /** @param array<string, mixed> $data */
    private function observacion(array $data): ?string
    {
        $observacion = filled($data['observacion'] ?? null) ? trim((string) $data['observacion']) : null;

        if ($observacion !== null && mb_strlen($observacion) > 255) {
            throw ValidationException::withMessages(['observacion' => 'La observación no debe superar 255 caracteres.']);
        }

        return $observacion;
    }
}
