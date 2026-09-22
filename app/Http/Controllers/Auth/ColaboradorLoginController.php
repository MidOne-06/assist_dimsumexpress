<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ColaboradorLoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
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

        $request->session()->regenerate();

        $usuario = $request->user();

        // Supervisión es un flujo operativo distinto de asistencia. Un
        // supervisor no necesita (ni debe tener) ficha de colaborador para
        // registrar su visita mediante el QR del local asignado.
        if ($usuario?->hasRole('supervisor') && ! $usuario->colaborador) {
            return redirect()->intended(route('visita-supervisor.esperando'));
        }

        // Las cuentas administrativas puras tampoco deben terminar en el
        // flujo de marcación, que solo corresponde a colaboradores.
        if ($usuario?->can('Access:AdminPanel') && ! $usuario->colaborador) {
            return redirect()->intended(route('filament.admin.pages.dashboard'));
        }

        return redirect()->intended(route('marcacion.show'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
