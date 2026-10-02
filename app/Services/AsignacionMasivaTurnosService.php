<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\TurnoOperativo;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsignacionMasivaTurnosService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{creadas: int, actualizadas: int}
     */
    public function asignar(User $usuario, array $data): array
    {
        abort_unless($usuario->can('AsignarMasivo:AsignarTurnos'), 403);

        $fechaInicio = Carbon::parse((string) ($data['fecha_inicio'] ?? ''))->startOfDay();
        $fechaFin = Carbon::parse((string) ($data['fecha_fin'] ?? ''))->startOfDay();
        $sucursalId = (int) ($data['sucursal_id'] ?? 0);
        $turnoId = (int) ($data['turno_id'] ?? 0);
        $colaboradorIds = array_values(array_unique(array_map('intval', (array) ($data['colaborador_ids'] ?? []))));
        $diasSemana = array_values(array_unique(array_map('intval', (array) ($data['dias_semana'] ?? []))));

        if ($fechaInicio->lt(today())) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'No se pueden programar turnos en fechas pasadas.',
            ]);
        }

        if ($fechaFin->lt($fechaInicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha “Hasta” debe ser igual o posterior a la fecha “Desde”.',
            ]);
        }

        if ($fechaInicio->diffInDays($fechaFin) > 90) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'El rango máximo permitido es de 90 días.',
            ]);
        }

        if ($colaboradorIds === []) {
            throw ValidationException::withMessages([
                'colaborador_ids' => 'Selecciona al menos un colaborador.',
            ]);
        }

        if ($diasSemana === [] || array_diff($diasSemana, range(1, 7)) !== []) {
            throw ValidationException::withMessages([
                'dias_semana' => 'Selecciona al menos un día válido.',
            ]);
        }

        abort_unless(
            Sucursal::query()
                ->whereKey($sucursalId)
                ->whereIn('id', AlcanceSupervisor::sucursalIds($usuario))
                ->exists(),
            403,
        );

        if (! Turno::query()->whereKey($turnoId)->where('activo', true)->exists()) {
            throw ValidationException::withMessages([
                'turno_id' => 'Selecciona un turno activo.',
            ]);
        }

        $colaboradores = Colaborador::query()
            ->whereIn('id', $colaboradorIds)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->get(['id', 'sucursal_id', 'punto_venta_id']);

        abort_unless($colaboradores->count() === count($colaboradorIds), 403);

        if ($colaboradores->contains(fn (Colaborador $colaborador): bool => ! TurnoOperativo::turnoHabilitadoEnEstacion(
            $colaborador->sucursal_id,
            $colaborador->punto_venta_id,
            $turnoId,
        ))) {
            throw ValidationException::withMessages([
                'turno_id' => 'El turno seleccionado no está habilitado para la estación base de uno o más colaboradores.',
            ]);
        }

        $fechas = collect(CarbonPeriod::create($fechaInicio, $fechaFin))
            ->filter(fn (Carbon $fecha): bool => in_array($fecha->isoWeekday(), $diasSemana, true))
            ->map(fn (Carbon $fecha): string => $fecha->toDateString())
            ->values();

        if ($fechas->isEmpty()) {
            throw ValidationException::withMessages([
                'dias_semana' => 'El rango no contiene fechas para los días seleccionados.',
            ]);
        }

        if ($fechas->contains(today()->toDateString()) && Marcacion::query()
            ->whereIn('colaborador_id', $colaboradorIds)
            ->whereDate('fecha_hora', today())
            ->exists()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'No se puede cambiar el turno de hoy porque ya existen marcaciones.',
            ]);
        }

        $ahora = now();
        $filas = [];

        foreach ($fechas as $fecha) {
            foreach ($colaboradorIds as $colaboradorId) {
                $filas[] = [
                    'colaborador_id' => $colaboradorId,
                    'turno_id' => $turnoId,
                    'fecha' => $fecha,
                    'observacion' => filled($data['observacion'] ?? null) ? trim((string) $data['observacion']) : null,
                    'asignado_por' => $usuario->id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        $creadas = 0;
        $actualizadas = 0;

        DB::transaction(function () use ($filas, &$creadas, &$actualizadas): void {
            foreach ($filas as $fila) {
                $asignacion = AsignacionTurno::query()
                    ->where('colaborador_id', $fila['colaborador_id'])
                    ->whereDate('fecha', $fila['fecha'])
                    ->first();

                if ($asignacion) {
                    $asignacion->update([
                        'turno_id' => $fila['turno_id'],
                        'observacion' => $fila['observacion'],
                        'asignado_por' => $fila['asignado_por'],
                    ]);
                    $actualizadas++;

                    continue;
                }

                AsignacionTurno::query()->create([
                    'colaborador_id' => $fila['colaborador_id'],
                    'turno_id' => $fila['turno_id'],
                    'fecha' => $fila['fecha'],
                    'observacion' => $fila['observacion'],
                    'asignado_por' => $fila['asignado_por'],
                ]);
                $creadas++;
            }
        });

        return [
            'creadas' => $creadas,
            'actualizadas' => $actualizadas,
        ];
    }
}
