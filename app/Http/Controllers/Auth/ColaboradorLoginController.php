<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AparienciaSistemaService;
use App\Support\PortalAccesos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ColaboradorLoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login', [
            'apariencia' => app(AparienciaSistemaService::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('recordar'))) {
            throw ValidationException::withMessages([
                'email' => 'Ese correo y contraseña no coinciden con nuestros registros.',
            ]);
        }

        $usuario = $request->user();

        if (! $usuario?->estaActivoParaAcceso()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta no está disponible. Consulta con tu supervisor.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget('acceso_operativo_via_enlace');

        // No se usa redirect()->intended(): si una supervisora abrió /admin
        // por error, esa URL no debe imponerse sobre su operación real.
        // Las cuentas con más de una capacidad eligen explícitamente.
        $accesos = PortalAccesos::disponibles($usuario);

        return count($accesos) === 1
            ? redirect()->to($accesos[0]['ruta'])
            : redirect()->route('acceso.portal');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
