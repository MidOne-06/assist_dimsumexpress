<?php

namespace App\Services;

use App\Models\PuntoVenta;
use App\Models\QrToken;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use App\Models\VisitaSupervisorMarcacion;
use App\Support\AlcanceSupervisor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reglas transaccionales de visitas compartibles con la app móvil. */
final class RegistrarVisitaSupervisorService
{
    /**
     * @return array{qrToken: QrToken, sucursal: Sucursal, puntoVenta: ?PuntoVenta, accion: string, visita: ?VisitaSupervisor}
     */
    public function validar(User $usuario, string $token): array
    {
        $this->validarSupervisor($usuario);

        $qrToken = QrToken::query()
            ->with(['sucursal', 'puntoVenta'])
            ->where('token', $token)
            ->first();

        if (! $qrToken?->vigentePara(QrToken::PROPOSITO_VISITA_SUPERVISOR)) {
            throw ValidationException::withMessages([
                'token' => 'El código QR cambió o venció. Escanea el código actualizado de la estación.',
            ]);
        }

        $sucursal = $qrToken->sucursal;
        $puntoVenta = $qrToken->puntoVenta;
        if (! $sucursal?->activo || ($qrToken->punto_venta_id && ! $puntoVenta?->activo)) {
            throw ValidationException::withMessages(['token' => 'La estación de visita no está disponible.']);
        }

        if (! AlcanceSupervisor::puedeGestionarSucursal($usuario, $sucursal->id)) {
            throw new AuthorizationException('No tienes acceso a este local para registrar una visita.');
        }

        $resultado = $this->accionDisponible($usuario, $qrToken, $sucursal);
        if ($resultado['accion'] === 'reutilizado') {
            throw ValidationException::withMessages([
                'token' => 'Este código QR ya fue confirmado. Escanea el código actualizado de la estación.',
            ]);
        }

        if ($resultado['accion'] === 'bloqueado') {
            $local = $resultado['visita']?->sucursal?->nombre ?? 'otro local';
            throw ValidationException::withMessages([
                'token' => "Primero registra tu salida de visita en {$local}.",
            ]);
        }

        return [
            'qrToken' => $qrToken,
            'sucursal' => $sucursal,
            'puntoVenta' => $puntoVenta,
            'accion' => $resultado['accion'],
            'visita' => $resultado['visita'],
        ];
    }

    /** @return array{accion: 'ingreso'|'salida', visita: VisitaSupervisor} */
    public function registrar(
        User $usuario,
        string $token,
        string $accion,
        ?string $ipOrigen,
        ?string $userAgent,
    ): array {
        return DB::transaction(function () use ($usuario, $token, $accion, $ipOrigen, $userAgent): array {
            User::query()->lockForUpdate()->findOrFail($usuario->id);
            $validacion = $this->validar($usuario, $token);

            if ($validacion['accion'] !== $accion) {
                throw ValidationException::withMessages([
                    'accion' => 'La acción elegida ya no está disponible. Escanea un nuevo QR y vuelve a confirmarla.',
                ]);
            }

            $ahora = now();
            $qrToken = $validacion['qrToken'];
            $sucursal = $validacion['sucursal'];
            $puntoVenta = $validacion['puntoVenta'];
            $agente = substr((string) $userAgent, 0, 1000);
            $atributosEvento = [
                'supervisor_id' => $usuario->id,
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta?->id,
                'qr_token_id' => $qrToken->id,
                'fecha_hora' => $ahora,
                'ip_origen' => $ipOrigen,
                'user_agent' => $agente,
            ];

            if ($accion === 'ingreso') {
                $visita = VisitaSupervisor::create([
                    'supervisor_id' => $usuario->id,
                    'sucursal_id' => $sucursal->id,
                    'punto_venta_id' => $puntoVenta?->id,
                    'qr_token_id' => $qrToken->id,
                    'fecha' => $ahora->toDateString(),
                    'fecha_hora' => $ahora,
                    'ip_origen' => $ipOrigen,
                    'user_agent' => $agente,
                    'estado' => VisitaSupervisor::EN_CURSO,
                    'ingreso_en' => $ahora,
                    'punto_venta_ingreso_id' => $puntoVenta?->id,
                    'ingreso_qr_token_id' => $qrToken->id,
                    'ingreso_ip_origen' => $ipOrigen,
                    'ingreso_user_agent' => $agente,
                ]);

                VisitaSupervisorMarcacion::create($atributosEvento + [
                    'visita_supervisor_id' => $visita->id,
                    'tipo' => VisitaSupervisorMarcacion::INGRESO,
                ]);

                return compact('accion', 'visita');
            }

            /** @var VisitaSupervisor $visita */
            $visita = $validacion['visita'];
            $visita->update([
                'estado' => VisitaSupervisor::FINALIZADA,
                'salida_en' => $ahora,
                'punto_venta_salida_id' => $puntoVenta?->id,
                'salida_qr_token_id' => $qrToken->id,
                'salida_ip_origen' => $ipOrigen,
                'salida_user_agent' => $agente,
            ]);

            VisitaSupervisorMarcacion::create($atributosEvento + [
                'visita_supervisor_id' => $visita->id,
                'tipo' => VisitaSupervisorMarcacion::SALIDA,
            ]);

            return compact('accion', 'visita');
        });
    }

    private function validarSupervisor(User $usuario): void
    {
        if (! $usuario->hasRole('supervisor') || ! $usuario->can('Registrar:VisitaSupervisor')) {
            throw new AuthorizationException('Esta cuenta no puede registrar visitas de supervisión.');
        }
    }

    /** @return array{accion: 'ingreso'|'salida'|'bloqueado'|'reutilizado', visita: ?VisitaSupervisor} */
    private function accionDisponible(User $usuario, QrToken $qrToken, Sucursal $sucursal): array
    {
        if (VisitaSupervisorMarcacion::query()
            ->where('supervisor_id', $usuario->id)
            ->where('qr_token_id', $qrToken->id)
            ->exists()) {
            return ['accion' => 'reutilizado', 'visita' => null];
        }

        $visitaAbierta = VisitaSupervisor::query()
            ->where('supervisor_id', $usuario->id)
            ->where('estado', VisitaSupervisor::EN_CURSO)
            ->orderBy('ingreso_en')
            ->first();

        if (! $visitaAbierta) {
            return ['accion' => 'ingreso', 'visita' => null];
        }

        if ((int) $visitaAbierta->sucursal_id === (int) $sucursal->id) {
            return ['accion' => 'salida', 'visita' => $visitaAbierta];
        }

        return ['accion' => 'bloqueado', 'visita' => $visitaAbierta->loadMissing('sucursal:id,nombre')];
    }
}
