<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\RegularizacionSalida;
use App\Models\ResumenJornada;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use App\Support\JornadaMarcacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la salida omitida de una jornada vencida y consolida el resumen de asistencia.
 */
final class RegularizacionSalidaService
{
    public function puedeRegularizar(?User $usuario, AsignacionTurno $asignacion): bool
    {
        if (! $usuario
            || ! $usuario->can('Regularizar:Jornada')
            || ! AlcanceSupervisor::puedeGestionarSucursal($usuario, (int) $asignacion->colaborador->sucursal_id)) {
            return false;
        }

        $turno = $asignacion->turno;
        if (! $turno || $turno->solo_entrada || $turno->jornada_abierta || ! $turno->hora_fin) {
            return false;
        }

        $marcaciones = JornadaMarcacion::marcaciones($asignacion->colaborador, $asignacion);
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salida = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA);

        return $entrada !== null && $salida === null;
    }

    /**
     * @param  array{salida_en: mixed, motivo: mixed}  $data
     *
     * @throws ValidationException
     */
    public function regularizar(
        AsignacionTurno $asignacion,
        array $data,
        User $usuario,
        ?string $ipOrigen = null,
        ?string $userAgent = null,
    ): Marcacion {
        abort_unless($this->puedeRegularizar($usuario, $asignacion), 403, 'No tienes permisos para regularizar esta jornada.');

        $asignacion = AsignacionTurno::query()->with(['turno', 'colaborador'])->findOrFail($asignacion->id);
        $colaborador = $asignacion->colaborador;

        $marcaciones = JornadaMarcacion::marcaciones($colaborador, $asignacion);
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salidaExistente = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA);

        if (! $entrada) {
            throw ValidationException::withMessages([
                'salida_en' => 'La jornada no cuenta con marcación de ingreso para regularizar su salida.',
            ]);
        }

        if ($salidaExistente) {
            throw ValidationException::withMessages([
                'salida_en' => 'Esta jornada ya cuenta con una marcación de salida registrada.',
            ]);
        }

        $salidaEn = Carbon::parse((string) $data['salida_en'], config('app.timezone'));

        if ($salidaEn->isFuture()) {
            throw ValidationException::withMessages([
                'salida_en' => 'La fecha y hora de salida no puede estar en el futuro.',
            ]);
        }

        if ($salidaEn->lte($entrada->fecha_hora)) {
            throw ValidationException::withMessages([
                'salida_en' => 'La hora de salida debe ser posterior al ingreso registrado ('.$entrada->fecha_hora->format('d/m/Y H:i').').',
            ]);
        }

        $motivo = trim((string) ($data['motivo'] ?? ''));
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo de regularización debe tener entre 5 y 500 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($asignacion, $colaborador, $entrada, $salidaEn, $motivo, $usuario, $ipOrigen, $userAgent): Marcacion {
            $colaboradorBloqueado = Colaborador::query()->lockForUpdate()->findOrFail($colaborador->id);

            // 1. Crear marcación de salida
            $salida = Marcacion::create([
                'colaborador_id' => $colaboradorBloqueado->id,
                'empresa_id' => $colaboradorBloqueado->empresa_id,
                'area_id' => $colaboradorBloqueado->area_id,
                'tipo' => Marcacion::TIPO_SALIDA,
                'fecha_hora' => $salidaEn,
                'turno_id' => $asignacion->turno_id,
                'qr_token_id' => $entrada->qr_token_id,
                'sucursal_id' => $entrada->sucursal_id,
                'punto_venta_id' => $entrada->punto_venta_id,
                'cobertura_operativa_id' => $entrada->cobertura_operativa_id,
                'ip_origen' => $ipOrigen,
                'user_agent' => substr((string) $userAgent, 0, 1000),
            ]);

            // 2. Registrar auditoría de regularización
            RegularizacionSalida::create([
                'asignacion_turno_id' => $asignacion->id,
                'colaborador_id' => $colaboradorBloqueado->id,
                'marcacion_id' => $salida->id,
                'regularizada_por_id' => $usuario->id,
                'salida_en' => $salidaEn,
                'motivo' => $motivo,
                'ip_origen' => $ipOrigen,
                'user_agent' => substr((string) $userAgent, 0, 1000),
            ]);

            // 3. Consolidar o actualizar ResumenJornada
            $todasMarcaciones = JornadaMarcacion::marcaciones($colaboradorBloqueado, $asignacion);
            $calculoResumen = JornadaMarcacion::resumen($colaboradorBloqueado, $asignacion, $todasMarcaciones);
            $huella = hash('sha256', $todasMarcaciones->map(fn (Marcacion $m): string => implode('|', [$m->id, $m->tipo, $m->fecha_hora->format('Y-m-d H:i:s')]))->implode(';'));

            $salidaRef = $todasMarcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
            $regresoRef = $todasMarcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);

            $detalle = [
                'turno' => $asignacion->turno?->nombre,
                'entrada' => $entrada->fecha_hora->format('Y-m-d H:i:s'),
                'salida' => $salidaEn->format('Y-m-d H:i:s'),
                'salida_refrigerio' => $salidaRef?->fecha_hora?->format('Y-m-d H:i:s'),
                'regreso_refrigerio' => $regresoRef?->fecha_hora?->format('Y-m-d H:i:s'),
                'estacion' => [
                    'sucursal_id' => $entrada->sucursal_id,
                    'punto_venta_id' => $entrada->punto_venta_id,
                ],
                'regularizacion_salida' => [
                    'regularizada_por_id' => $usuario->id,
                    'regularizada_por_nombre' => $usuario->name,
                    'regularizada_en' => now()->toIso8601String(),
                    'motivo' => $motivo,
                ],
            ];

            ResumenJornada::updateOrCreate(
                ['asignacion_turno_id' => $asignacion->id],
                [
                    'colaborador_id' => $colaboradorBloqueado->id,
                    'empresa_id' => $entrada->empresa_id ?? $colaboradorBloqueado->empresa_id,
                    'area_id' => $entrada->area_id ?? $colaboradorBloqueado->area_id,
                    'sucursal_id' => $entrada->sucursal_id,
                    'punto_venta_id' => $entrada->punto_venta_id,
                    'entrada_marcacion_id' => $entrada->id,
                    'salida_marcacion_id' => $salida->id,
                    'salida_refrigerio_marcacion_id' => $salidaRef?->id,
                    'regreso_refrigerio_marcacion_id' => $regresoRef?->id,
                    'fecha_jornada' => $asignacion->fecha->toDateString(),
                    'estado' => $calculoResumen['estado'],
                    'efectivos_segundos' => $calculoResumen['efectivos_segundos'],
                    'objetivo_segundos' => $calculoResumen['objetivo_segundos'],
                    'diferencia_segundos' => $calculoResumen['diferencia_segundos'],
                    'extras_segundos' => $calculoResumen['extras_segundos'],
                    'refrigerio_segundos' => $calculoResumen['refrigerio_segundos'],
                    'consolidado_en' => now(),
                    'calculo_version' => 1,
                    'huella_marcaciones' => $huella,
                    'detalle' => $detalle,
                ],
            );

            return $salida;
        });
    }
}
