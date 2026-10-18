<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra una sesión nativa cuyo usuario ya fue desactivado y garantiza que
 * las rutas móviles sólo acepten tokens emitidos para esta aplicación.
 */
class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->estaActivoParaAcceso() || ! $user->tokenCan('mobile:access')) {
            return response()->json([
                'message' => 'Esta sesión móvil ya no está disponible. Inicia sesión nuevamente.',
            ], 401);
        }

        return $next($request);
    }
}
