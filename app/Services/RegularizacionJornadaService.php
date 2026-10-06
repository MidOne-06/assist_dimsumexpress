<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Turno;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use App\Support\JornadaMarcacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Convierte una jornada excepcional (lecturas QR sin turno) en una jornada
 * evaluable, sin cambiar sus horas, estación, QR ni tipo de marcación.
 */
final class RegularizacionJornadaService
{
    /**
     * @param array{turno_id:mixed,motivo:mixed} $data
     */
    public function regularizar(User $usuario, Colaborador $colaborador, string $fecha, array $data): AsignacionTurno
    {
        abort_unless($usuario->can('Regularizar:Jornada'), 403);
        abort_unless(AlcanceSupervisor::puedeGestionarSucursal($usuario, (int) $colaborador->sucursal_id), 403);

        $fechaJornada = Carbon::parse($fecha, config('app.timezone'))->startOfDay();

        if ($fechaJornada->isFuture()) {
            throw ValidationException::withMessages([
                'fecha' => 'Solo se pueden regularizar jornadas de hoy o anteriores.',
            ]);
        }

        $turno = Turno::query()
            ->whereKey((int) ($data['turno_id'] ?? 0))
            ->where('activo', true)
            ->first();

        if (! $turno) {
            throw ValidationException::withMessages([
                'turno_id' => 'Selecciona un turno activo.',
            ]);
        }

        $motivo = trim((string) ($data['motivo'] ?? ''));
        if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 200) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo debe tener entre 10 y 200 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $colaborador, $fechaJornada, $turno, $motivo): AsignacionTurno {
            $colaborador = Colaborador::query()->lockForUpdate()->findOrFail($colaborador->id);

            abort_unless(AlcanceSupervisor::puedeGestionarSucursal($usuario, (int) $colaborador->sucursal_id), 403);

            if (AsignacionTurno::query()
                ->where('colaborador_id', $colaborador->id)
                ->whereDate('fecha', $fechaJornada)
                ->exists()) {
                throw ValidationException::withMessages([
                    'fecha' => 'Esta jornada ya tiene un turno asignado. No se puede regularizar nuevamente.',
                ]);
            }

            $asignacion = AsignacionTurno::query()->create([
                'colaborador_id' => $colaborador->id,
                'turno_id' => $turno->id,
                'fecha' => $fechaJornada->toDateString(),
                'origen' => 'regularizado_manual',
                'observacion' => 'Regularización: ' . $motivo,
                'asignado_por' => $usuario->id,
            ]);
            $asignacion->load('turno');

            $marcaciones = $this->marcacionesExcepcionales($colaborador, $asignacion, $fechaJornada);
            if ($marcaciones->isEmpty()) {
                throw ValidationException::withMessages([
                    'fecha' => 'No hay marcaciones sin turno para regularizar en esta jornada.',
                ]);
            }

            Marcacion::query()
                ->whereKey($marcaciones->pluck('id'))
                ->update(['turno_id' => $asignacion->turno_id]);

            $this->recalcularRetornosDeRefrigerio($marcaciones, $asignacion);

            app(ConsolidacionJornadaService::class)->consolidar(
                $colaborador,
                $asignacion,
                JornadaMarcacion::marcaciones($colaborador, $asignacion),
            );

            return $asignacion->fresh(['turno', 'asignadoPor']);
        });
    }

    /** @return Collection<int, Marcacion> */
    private function marcacionesExcepcionales(Colaborador $colaborador, AsignacionTurno $asignacion, Carbon $fecha): Collection
    {
        $limites = JornadaMarcacion::limites($asignacion);

        return Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereNull('turno_id')
            // Se conserva cualquier ingreso temprano de la fecha elegida y,
            // para turnos nocturnos, las marcas hasta su final técnico.
            ->whereBetween('fecha_hora', [$fecha->copy()->startOfDay(), $limites['jornada_fin_maximo']])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @param Collection<int, Marcacion> $marcaciones */
    private function recalcularRetornosDeRefrigerio(Collection $marcaciones, AsignacionTurno $asignacion): void
    {
        if (! $asignacion->turno->incluye_refrigerio) {
            return;
        }

        $ultimaSalidaRefrigerio = null;

        foreach ($marcaciones as $marcacion) {
            if ($marcacion->tipo === Marcacion::TIPO_SALIDA_REFRIGERIO) {
                $ultimaSalidaRefrigerio = $marcacion;

                continue;
            }

            if ($marcacion->tipo !== Marcacion::TIPO_REGRESO_REFRIGERIO || ! $ultimaSalidaRefrigerio) {
                continue;
            }

            $control = JornadaMarcacion::controlRetornoRefrigerio(
                $ultimaSalidaRefrigerio,
                $marcacion->fecha_hora,
                JornadaMarcacion::minutosRefrigerio($asignacion),
            );

            $marcacion->update([
                'refrigerio_retorno_esperado_en' => $control['esperado'],
                'refrigerio_diferencia_segundos' => $control['diferencia_segundos'],
            ]);
            $ultimaSalidaRefrigerio = null;
        }
    }
}
