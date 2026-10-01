<?php

namespace App\Http\Controllers;

use App\Models\EnlaceAccesoColaborador;
use App\Services\AparienciaSistemaService;
use App\Services\EnlacesAccesoColaboradorService;
use App\Support\PoliticaContrasena;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EnlaceAccesoColaboradorController extends Controller
{
    public function show(string $token, EnlacesAccesoColaboradorService $enlaces): View|Response
    {
        $this->prepararFilament();

        $enlace = $enlaces->buscar($token);

        if (! $this->puedeUsarse($enlace)) {
            return $this->expirado();
        }

        return view('acceso-enlace.confirmar', [
            'apariencia' => app(AparienciaSistemaService::class),
            'token' => $token,
            'enlace' => $enlace,
        ]);
    }

    public function consumir(Request $request, string $token, EnlacesAccesoColaboradorService $enlaces): RedirectResponse|Response
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', PoliticaContrasena::regla()],
            'recordar' => ['nullable', 'boolean'],
        ]);

        $enlace = DB::transaction(function () use ($token, $enlaces, $request): ?EnlaceAccesoColaborador {
            $enlace = EnlaceAccesoColaborador::query()
                ->with(['colaborador', 'user'])
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $this->puedeUsarse($enlace)) {
                return null;
            }

            $enlace->update([
                'usado_en' => now(),
                'usado_desde_ip' => $request->ip(),
                'user_agent_uso' => substr((string) $request->userAgent(), 0, 1000),
            ]);

            return $enlace;
        });

        if (! $enlace) {
            return $this->expirado();
        }

        // El enlace sustituye una contraseña temporal o perdida, nunca la
        // expone. Al cambiarla se invalidan todas las sesiones y enlaces
        // pendientes anteriores de la cuenta.
        $enlace->user->restablecerContrasena($data['password']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($enlace->user->fresh(), $request->boolean('recordar'));
        $request->session()->regenerate();
        $request->session()->put('acceso_operativo_via_enlace', true);

        return redirect()->route('marcacion.show');
    }

    private function puedeUsarse(?EnlaceAccesoColaborador $enlace): bool
    {
        return $enlace?->estaVigente() === true
            && $enlace->colaborador?->activo === true
            && $enlace->user?->estaActivoParaAcceso() === true
            && $enlace->user?->can('Registrar:Marcacion') === true;
    }

    private function expirado(): Response
    {
        $this->prepararFilament();

        return response()->view('acceso-enlace.expirado', [
            'apariencia' => app(AparienciaSistemaService::class),
        ], 410);
    }

    private function prepararFilament(): void
    {
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();
    }
}
