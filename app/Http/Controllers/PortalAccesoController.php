<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AparienciaSistemaService;
use App\Support\PortalAccesos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalAccesoController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        /** @var User $usuario */
        $usuario = $request->user();

        abort_unless($usuario->estaActivoParaAcceso(), 403);

        $accesos = PortalAccesos::disponibles($usuario);

        if (count($accesos) === 1) {
            return redirect()->to($accesos[0]['ruta']);
        }

        return view('acceso.portal', [
            'apariencia' => app(AparienciaSistemaService::class),
            'usuario' => $usuario,
            'accesos' => $accesos,
        ]);
    }
}
