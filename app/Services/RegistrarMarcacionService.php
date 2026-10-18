<?php

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Support\JornadaMarcacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Persiste una marcación con la misma transacción para web y app nativa. */
final class RegistrarMarcacionService
{
    public function __construct(
        private readonly MarcacionFlowService $flujo,
        private readonly MarcacionQrValidationService $validadorQr,
        private readonly ConsolidacionJornadaService $consolidacion,
    ) {
    }

    /** @throws ValidationException */
    public function registrar(
        Colaborador $colaborador,
        string $token,
        string $tipo,
        ?string $ipOrigen,
        ?string $userAgent,
    ): Marcacion {
        return DB::transaction(function () use ($colaborador, $token, $tipo, $ipOrigen, $userAgent): Marcacion {
            // Evita que dos toques o dos dispositivos reutilicen el mismo QR.
            $colaboradorBloqueado = Colaborador::query()->lockForUpdate()->findOrFail($colaborador->id);
            $fechaHora = now();
            $validacion = $this->validadorQr->validar($colaboradorBloqueado, $token, $fechaHora);
            $qrToken = $validacion['qrToken'];
            $asignacion = $validacion['asignacion'];

            $tiposDisponibles = $this->flujo->tiposDisponibles($colaboradorBloqueado, $asignacion, $fechaHora);
            if (! in_array($tipo, $tiposDisponibles, true)) {
                throw ValidationException::withMessages([
                    'accion' => 'Esta acción ya no está disponible. Escanea un nuevo QR y selecciona una acción válida.',
                ]);
            }

            if ($asignacion && ! $asignacion->exists) {
                if ($tipo !== Marcacion::TIPO_ENTRADA) {
                    throw ValidationException::withMessages(['token' => 'Registra primero tu ingreso.']);
                }

                $asignacion = AsignacionTurno::query()->firstOrCreate(
                    ['colaborador_id' => $colaboradorBloqueado->id, 'fecha' => $fechaHora->toDateString()],
                    $asignacion->getAttributes(),
                );
                $asignacion->load('turno');
            }

            if ($asignacion && $tipo === Marcacion::TIPO_ENTRADA) {
                JornadaMarcacion::confirmarAjusteAutomatico($colaboradorBloqueado, $asignacion, $fechaHora);
            }

            $controlRefrigerio = null;
            $cobertura = $asignacion
                ? $this->flujo->registrarCoberturaAutomatica($colaboradorBloqueado, $asignacion, $qrToken, $fechaHora)
                : null;

            if ($asignacion && $tipo === Marcacion::TIPO_REGRESO_REFRIGERIO) {
                $salidaRefrigerio = JornadaMarcacion::ultimaMarcacion($colaboradorBloqueado, $asignacion);

                if ($salidaRefrigerio?->tipo !== Marcacion::TIPO_SALIDA_REFRIGERIO) {
                    throw ValidationException::withMessages([
                        'token' => 'No se pudo continuar con la marcación. Actualiza la página e inténtalo nuevamente.',
                    ]);
                }

                $controlRefrigerio = JornadaMarcacion::controlRetornoRefrigerio(
                    $salidaRefrigerio,
                    $fechaHora,
                    JornadaMarcacion::minutosRefrigerio($asignacion),
                );
            }

            $marcacion = Marcacion::create([
                'colaborador_id' => $colaboradorBloqueado->id,
                'empresa_id' => $colaboradorBloqueado->empresa_id,
                'area_id' => $colaboradorBloqueado->area_id,
                'tipo' => $tipo,
                'fecha_hora' => $fechaHora,
                'refrigerio_retorno_esperado_en' => $controlRefrigerio['esperado'] ?? null,
                'refrigerio_diferencia_segundos' => $controlRefrigerio['diferencia_segundos'] ?? null,
                'turno_id' => $asignacion?->turno_id,
                'qr_token_id' => $qrToken->id,
                'sucursal_id' => $qrToken->sucursal_id,
                'punto_venta_id' => $qrToken->punto_venta_id,
                'cobertura_operativa_id' => $cobertura?->id,
                'ip_origen' => $ipOrigen,
                'user_agent' => substr((string) $userAgent, 0, 1000),
            ]);

            if ($asignacion && $marcacion->tipo === Marcacion::TIPO_SALIDA) {
                $this->consolidacion->consolidar($colaboradorBloqueado, $asignacion);
            }

            return $marcacion->load('colaborador');
        });
    }
}
