<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrarVisitaSupervisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileVisitaSupervisorController extends Controller
{
    public function __construct(private readonly RegistrarVisitaSupervisorService $registro)
    {
    }

    public function validarQr(Request $request): JsonResponse
    {
        $usuario = $this->usuario($request);
        $data = $request->validate(['token' => ['required', 'string']]);

        try {
            $resultado = $this->registro->validar($usuario, (string) $data['token']);
        } catch (ValidationException $e) {
            return response()->json([
                'confirmado' => false,
                'mensaje' => $e->errors()['token'][0] ?? $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'confirmado' => true,
            'accion' => $resultado['accion'],
            'local' => $resultado['sucursal']->nombre,
            'punto_venta' => $resultado['puntoVenta']?->nombre,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->usuario($request);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'accion' => ['required', 'in:ingreso,salida'],
        ]);

        $resultado = $this->registro->registrar(
            $usuario,
            (string) $data['token'],
            (string) $data['accion'],
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'visita' => [
                'id' => $resultado['visita']->id,
                'accion' => $resultado['accion'],
                'mensaje' => $resultado['accion'] === 'ingreso' ? 'Ingreso de visita registrado' : 'Salida de visita registrada',
                'hora' => now()->format('H:i:s'),
            ],
        ], 201);
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
