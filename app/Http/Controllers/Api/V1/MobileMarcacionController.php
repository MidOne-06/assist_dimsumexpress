<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Marcacion;
use App\Models\User;
use App\Services\MarcacionQrValidationService;
use App\Services\RegistrarMarcacionService;
use App\Support\PresentacionConfirmacionMarcacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileMarcacionController extends Controller
{
    public function __construct(
        private readonly MarcacionQrValidationService $validadorQr,
        private readonly RegistrarMarcacionService $registro,
    ) {
    }

    public function validarQr(Request $request): JsonResponse
    {
        $colaborador = $this->colaborador($request);
        $data = $request->validate(['token' => ['required', 'string']]);

        try {
            $resultado = $this->validadorQr->validar($colaborador, (string) $data['token'], now());
        } catch (ValidationException $e) {
            return response()->json([
                'confirmado' => false,
                'mensaje' => $e->errors()['token'][0] ?? $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'confirmado' => true,
            'mensaje' => 'QR escaneado correctamente',
            'estacion' => [
                'sucursal' => $resultado['qrToken']->sucursal?->nombre,
                'punto_venta' => $resultado['qrToken']->puntoVenta?->nombre,
            ],
            'acciones' => $resultado['acciones'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $colaborador = $this->colaborador($request);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'accion' => ['required', 'in:entrada,salida_refrigerio,regreso_refrigerio,salida'],
        ]);

        $marcacion = $this->registro->registrar(
            $colaborador,
            (string) $data['token'],
            (string) $data['accion'],
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'marcacion' => [
                'id' => $marcacion->id,
                'tipo' => $marcacion->tipo,
                'hora' => $marcacion->fecha_hora->format('H:i:s'),
                'fecha' => $marcacion->fecha_hora->toDateString(),
                'confirmacion' => PresentacionConfirmacionMarcacion::para($marcacion),
            ],
        ], 201);
    }

    public function hoy(Request $request): JsonResponse
    {
        $colaborador = $this->colaborador($request);
        $hoy = now();
        $marcaciones = Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereBetween('fecha_hora', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get()
            ->map(fn (Marcacion $marcacion): array => [
                'tipo' => $marcacion->tipo,
                'etiqueta' => $this->etiqueta($marcacion),
                'hora' => $marcacion->fecha_hora->format('H:i:s'),
            ]);

        return response()->json([
            'fecha' => $hoy->toDateString(),
            'marcaciones' => $marcaciones,
        ]);
    }

    private function colaborador(Request $request): \App\Models\Colaborador
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($usuario->can('Registrar:Marcacion'), 403);

        $colaborador = $usuario->colaborador;
        abort_unless($colaborador?->activo, 403, 'Esta cuenta no está disponible para marcar.');

        return $colaborador;
    }

    private function etiqueta(Marcacion $marcacion): string
    {
        return match ($marcacion->tipo) {
            Marcacion::TIPO_ENTRADA => 'Ingreso de turno',
            Marcacion::TIPO_SALIDA_REFRIGERIO => 'Salida a refrigerio',
            Marcacion::TIPO_REGRESO_REFRIGERIO => 'Ingreso de refrigerio',
            Marcacion::TIPO_SALIDA => 'Salida de turno',
            default => 'Marcación registrada',
        };
    }
}
