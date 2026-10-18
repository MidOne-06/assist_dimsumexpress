<?php

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\QrToken;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Comprueba un QR de asistencia sin crear una marcación. Es compartido por
 * la web y la app nativa para evitar que ambas interpretaciones del turno
 * evolucionen de manera diferente.
 */
final class MarcacionQrValidationService
{
    public function __construct(private readonly MarcacionFlowService $flujo)
    {
    }

    /**
     * @return array{qrToken: QrToken, asignacion: ?AsignacionTurno, acciones: array<int, array{tipo:string,etiqueta:string,icono:string,color:string,habilitada:bool,motivo:?string}>}
     *
     * @throws ValidationException
     */
    public function validar(Colaborador $colaborador, string $token, Carbon $momento): array
    {
        if (! $colaborador->activo) {
            throw ValidationException::withMessages([
                'token' => 'Tu cuenta no está disponible para marcar. Consulta con tu supervisor.',
            ]);
        }

        $qrToken = QrToken::query()
            ->with(['sucursal', 'puntoVenta'])
            ->where('token', $token)
            ->first();

        if (! $qrToken || ! $qrToken->vigentePara(QrToken::PROPOSITO_ASISTENCIA)) {
            throw ValidationException::withMessages([
                'token' => 'El código QR cambió o venció. Escanea el código actual de la pantalla.',
            ]);
        }

        if ($this->flujo->qrYaUsadoPorColaborador($colaborador, $qrToken)) {
            throw ValidationException::withMessages([
                'token' => 'Este código QR ya fue utilizado. Espera el nuevo código de la pantalla y vuelve a escanear.',
            ]);
        }

        $asignacion = $this->flujo->resolverAsignacion($colaborador, $qrToken, $momento);

        if (! $this->flujo->estacionPermitida($colaborador, $qrToken, $asignacion)) {
            throw ValidationException::withMessages([
                'token' => 'Este código no está disponible para tu marcación. Usa el QR mostrado en tu local.',
            ]);
        }

        $acciones = $this->flujo->accionesPresentables($colaborador, $asignacion, $momento);
        if (collect($acciones)->where('habilitada', true)->isEmpty()) {
            throw ValidationException::withMessages([
                'token' => 'Tu jornada ya no admite más marcaciones por hoy. Consulta con tu supervisor si necesitas regularizarla.',
            ]);
        }

        return compact('qrToken', 'asignacion', 'acciones');
    }
}
