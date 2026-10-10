<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PuntoVenta;
use App\Models\User;
use App\Models\VisitaSupervisor;
use App\Models\VisitaSupervisorMarcacion;
use App\Support\AlcanceSupervisor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Regulariza el cierre de una visita sin alterar sus eventos previos. */
final class RegularizacionVisitaSupervisorService
{
    /** @param array<string, mixed> $data */
    public function regularizar(VisitaSupervisor $visita, array $data, User $usuario, ?string $ipOrigen, ?string $userAgent): void
    {
        DB::transaction(function () use ($visita, $data, $usuario, $ipOrigen, $userAgent): void {
            $visita = VisitaSupervisor::query()->lockForUpdate()->findOrFail($visita->id);
            abort_unless($this->puedeRegularizar($visita, $usuario), 403);
            $salida = Carbon::parse($data['salida_en']);

            if ($visita->ingreso_en && $salida->lt($visita->ingreso_en)) {
                throw ValidationException::withMessages(['salida_en' => 'La salida no puede ser anterior al ingreso.']);
            }
            if ($salida->isFuture()) {
                throw ValidationException::withMessages(['salida_en' => 'La salida no puede estar en el futuro.']);
            }
            if ($visita->fecha && $salida->toDateString() < $visita->fecha->toDateString()) {
                throw ValidationException::withMessages(['salida_en' => 'La salida debe corresponder a la fecha de la visita.']);
            }

            $puntoSalidaId = filled($data['punto_venta_salida_id'] ?? null) ? (int) $data['punto_venta_salida_id'] : null;
            if ($puntoSalidaId !== null && ! PuntoVenta::query()->whereKey($puntoSalidaId)->where('sucursal_id', $visita->sucursal_id)->where('activo', true)->exists()) {
                throw ValidationException::withMessages(['punto_venta_salida_id' => 'Seleccione un punto de venta activo del mismo local de la visita.']);
            }

            $motivo = (string) $data['regularizacion_motivo'];
            $visita->update([
                'estado' => VisitaSupervisor::REGULARIZADA,
                'salida_en' => $salida,
                'punto_venta_salida_id' => $puntoSalidaId,
                'regularizada_por_id' => $usuario->id,
                'regularizada_en' => now(),
                'regularizacion_motivo' => $motivo,
            ]);
            VisitaSupervisorMarcacion::create([
                'visita_supervisor_id' => $visita->id,
                'supervisor_id' => $visita->supervisor_id,
                'sucursal_id' => $visita->sucursal_id,
                'punto_venta_id' => $puntoSalidaId,
                'tipo' => VisitaSupervisorMarcacion::REGULARIZACION,
                'fecha_hora' => $salida,
                'ip_origen' => $ipOrigen,
                'user_agent' => substr((string) $userAgent, 0, 1000),
                'metadata' => ['motivo' => $motivo, 'regularizada_por_id' => $usuario->id],
            ]);
        });
    }

    private function puedeRegularizar(VisitaSupervisor $visita, User $usuario): bool
    {
        return $visita->estado === VisitaSupervisor::EN_CURSO
            && $usuario->can('Regularizar:VisitaSupervisor')
            && $usuario->hasAnyRole(['super_admin', 'administrador'])
            && in_array($visita->sucursal_id, AlcanceSupervisor::sucursalIds($usuario), true);
    }
}
