<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileSessionController extends Controller
{
    private const TOKEN_DAYS = 30;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'dispositivo' => ['required', 'string', 'max:80'],
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        /**
         * El origen de datos histórico conserva algunos correos con mayúsculas.
         * La app móvil debe tratarlos igual que el acceso web: sin distinguir
         * entre mayúsculas y minúsculas.
         */
        $usuario = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $usuario || ! Hash::check((string) $data['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => 'Ese correo y contraseña no coinciden con nuestros registros.',
            ]);
        }

        $usuario->load('colaborador');

        if (! $usuario->estaActivoParaAcceso()) {
            throw ValidationException::withMessages([
                'email' => 'Esta cuenta no está disponible. Consulta con tu supervisor.',
            ]);
        }

        $accesos = $this->accesos($usuario);
        if (! $accesos['marcacion'] && ! $accesos['visitas']) {
            return response()->json([
                'message' => 'Esta cuenta no tiene una operación móvil habilitada.',
            ], 403);
        }

        $nombreDispositivo = 'mobile:'.trim((string) $data['dispositivo']);
        // Renovar sólo este equipo evita acumular tokens al actualizar la app,
        // sin cerrar las sesiones de los demás dispositivos autorizados.
        $usuario->tokens()->where('name', $nombreDispositivo)->delete();
        $nuevoToken = $usuario->createToken(
            $nombreDispositivo,
            ['mobile:access'],
            now()->addDays(self::TOKEN_DAYS),
        );

        return response()->json([
            'token' => $nuevoToken->plainTextToken,
            'vence_en' => $nuevoToken->accessToken->expires_at?->toIso8601String(),
            'usuario' => $this->perfil($usuario, $accesos),
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $usuario->loadMissing('colaborador');

        return response()->json(['usuario' => $this->perfil($usuario, $this->accesos($usuario))]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([], 204);
    }

    /** @return array{marcacion: bool, visitas: bool} */
    private function accesos(User $usuario): array
    {
        return [
            'marcacion' => (bool) $usuario->colaborador?->activo && $usuario->can('Registrar:Marcacion'),
            'visitas' => $usuario->hasRole('supervisor') && $usuario->can('Registrar:VisitaSupervisor'),
        ];
    }

    /** @param array{marcacion: bool, visitas: bool} $accesos */
    private function perfil(User $usuario, array $accesos): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => trim((string) ($usuario->colaborador?->nombre_completo ?: $usuario->name)),
            'accesos' => $accesos,
        ];
    }
}
