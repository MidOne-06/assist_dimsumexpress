<?php

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\CoberturaOperativa;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\QrToken;
use App\Support\JornadaMarcacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve el flujo operativo de una marcación QR. Mantiene al controlador
 * limitado a HTTP y a la transacción, mientras esta clase centraliza las
 * reglas reutilizables de turno, estación y cobertura.
 */
final class MarcacionFlowService
{
    /** @return array<int, string> */
    public function tiposDisponibles(Colaborador $colaborador, ?AsignacionTurno $asignacion, Carbon $momento): array
    {
        if ($asignacion) {
            return JornadaMarcacion::siguientesTipos($colaborador, $asignacion);
        }

        return array_values(array_filter([
            JornadaMarcacion::siguienteTipoSinTurnoAutomatico($colaborador, $momento),
        ]));
    }

    /**
     * @return array<int, array{tipo:string,etiqueta:string,icono:string,color:string,habilitada:bool,motivo:?string}>
     */
    public function accionesPresentables(Colaborador $colaborador, ?AsignacionTurno $asignacion, Carbon $momento): array
    {
        $acciones = $asignacion
            ? JornadaMarcacion::acciones($colaborador, $asignacion)
            : JornadaMarcacion::accionesSinTurno($colaborador, $momento);

        return collect($acciones)->map(function (array $accion): array {
            $presentacion = match ($accion['tipo']) {
                Marcacion::TIPO_ENTRADA => ['etiqueta' => 'Ingreso de turno', 'icono' => 'heroicon-o-arrow-right-on-rectangle', 'color' => 'success'],
                Marcacion::TIPO_SALIDA_REFRIGERIO => ['etiqueta' => 'Salida a refrigerio', 'icono' => 'heroicon-o-clock', 'color' => 'warning'],
                Marcacion::TIPO_REGRESO_REFRIGERIO => ['etiqueta' => 'Ingreso de refrigerio', 'icono' => 'heroicon-o-arrow-right-circle', 'color' => 'info'],
                default => ['etiqueta' => 'Salida de turno', 'icono' => 'heroicon-o-arrow-left-on-rectangle', 'color' => 'danger'],
            };

            return $presentacion + [
                'tipo' => $accion['tipo'],
                'habilitada' => $accion['habilitada'],
                'motivo' => $accion['motivo'],
            ];
        })->all();
    }

    /**
     * Una jornada abierta conserva su turno. Antes del primer ingreso se
     * intenta el turno del rango operativo de la estación y, si no aplica,
     * se mantiene la programación manual como respaldo.
     */
    public function resolverAsignacion(Colaborador $colaborador, QrToken $qrToken, Carbon $momento): ?AsignacionTurno
    {
        $asignacion = JornadaMarcacion::asignacionVigente($colaborador, $momento, false);

        if ($asignacion && JornadaMarcacion::jornadaAbierta($colaborador, $asignacion)) {
            return $asignacion;
        }

        $yaMarcoHoy = $colaborador->marcaciones()
            ->whereBetween('fecha_hora', [$momento->copy()->startOfDay(), $momento->copy()->endOfDay()])
            ->exists();

        if (! $yaMarcoHoy) {
            $detectada = JornadaMarcacion::detectarTurnoOperativo(
                $colaborador,
                $qrToken->sucursal,
                $qrToken->puntoVenta,
                $momento,
            );

            if ($detectada) {
                $programada = $colaborador->asignacionesTurno()
                    ->with('turno')
                    ->whereDate('fecha', $momento->toDateString())
                    ->orderBy('id')
                    ->first();

                if (! $programada || $programada->turno_id === $detectada->turno_id) {
                    return $programada ?? $detectada;
                }

                return JornadaMarcacion::aplicarTurnoDetectado($programada, $detectada);
            }
        }

        if ($asignacion) {
            return $asignacion;
        }

        $manual = $colaborador->asignacionesTurno()
            ->with('turno')
            ->whereDate('fecha', $momento->toDateString())
            ->where(fn ($query) => $query->whereNull('origen')->orWhere('origen', '!=', 'detectado_automaticamente'))
            ->orderBy('id')
            ->first();

        if ($manual?->turno?->activo) {
            return $manual;
        }

        return JornadaMarcacion::detectarTurnoOperativo($colaborador, $qrToken->sucursal, $qrToken->puntoVenta, $momento);
    }

    /** Una estación activa siempre admite una marca trazable, incluso sin turno previo. */
    public function estacionPermitida(Colaborador $colaborador, QrToken $qrToken, ?AsignacionTurno $asignacion = null): bool
    {
        return (bool) ($qrToken->sucursal?->activo)
            && (! $qrToken->punto_venta_id || (bool) $qrToken->puntoVenta?->activo);
    }

    public function registrarCoberturaAutomatica(Colaborador $colaborador, AsignacionTurno $asignacion, QrToken $qrToken, Carbon $detectadaEn): ?CoberturaOperativa
    {
        if ($this->esEstacionBase($colaborador, $qrToken) || ! Schema::hasTable('coberturas_operativas')) {
            return null;
        }

        return CoberturaOperativa::firstOrCreate(
            [
                'asignacion_turno_id' => $asignacion->id,
                'sucursal_id' => $qrToken->sucursal_id,
                'punto_venta_id' => $qrToken->punto_venta_id,
            ],
            [
                'colaborador_id' => $colaborador->id,
                'origen' => CoberturaOperativa::ORIGEN_AUTOMATICA,
                'estado' => CoberturaOperativa::ESTADO_PENDIENTE,
                'detectada_en' => $detectadaEn,
            ],
        );
    }

    public function qrYaUsadoPorColaborador(Colaborador $colaborador, QrToken $qrToken): bool
    {
        return Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('qr_token_id', $qrToken->id)
            ->exists();
    }

    private function esEstacionBase(Colaborador $colaborador, QrToken $qrToken): bool
    {
        if ((int) $qrToken->sucursal_id !== (int) $colaborador->sucursal_id) {
            return false;
        }

        return ! $qrToken->punto_venta_id
            || ! $colaborador->punto_venta_id
            || (int) $qrToken->punto_venta_id === (int) $colaborador->punto_venta_id;
    }
}
