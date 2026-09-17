<?php

namespace App\Http\Controllers;

use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\QrToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarcacionController extends Controller
{
    public function show(Request $request): View
    {
        $colaborador = $request->user()->colaborador;

        if (! $colaborador) {
            return view('marcacion.error', [
                'mensaje' => 'Tu usuario no está vinculado a ningún colaborador. Contacta a tu administrador.',
            ]);
        }

        $token = $request->query('token');
        $qrToken = $token ? QrToken::where('token', $token)->first() : null;

        if (! $qrToken || ! $qrToken->vigente()) {
            return view('marcacion.error', [
                'mensaje' => 'El código QR expiró o no es válido. Vuelve a escanear el código de la pantalla.',
            ]);
        }

        if ($qrToken->sucursal_id !== $colaborador->sucursal_id) {
            return view('marcacion.error', [
                'mensaje' => 'Este código QR pertenece a otra sucursal distinta a la tuya.',
            ]);
        }

        return view('marcacion.show', [
            'colaborador' => $colaborador,
            'token' => $qrToken->token,
            'siguientesTipos' => $this->siguientesTiposPermitidos($colaborador),
            'ultimaMarcacion' => $this->ultimaMarcacionDeHoy($colaborador),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'tipo' => ['required', Rule::in([
                Marcacion::TIPO_ENTRADA,
                Marcacion::TIPO_SALIDA,
                Marcacion::TIPO_SALIDA_REFRIGERIO,
                Marcacion::TIPO_REGRESO_REFRIGERIO,
            ])],
        ]);

        $colaborador = $request->user()->colaborador;
        abort_unless($colaborador, 403, 'Tu usuario no está vinculado a ningún colaborador.');

        $qrToken = QrToken::where('token', $data['token'])->first();

        if (! $qrToken || ! $qrToken->vigente()) {
            return back()->withErrors(['tipo' => 'El código QR expiró. Vuelve a escanearlo desde la pantalla.']);
        }

        if ($qrToken->sucursal_id !== $colaborador->sucursal_id) {
            return back()->withErrors(['tipo' => 'Este código QR pertenece a otra sucursal distinta a la tuya.']);
        }

        $siguientesTipos = $this->siguientesTiposPermitidos($colaborador);

        if (! in_array($data['tipo'], $siguientesTipos, true)) {
            return back()->withErrors(['tipo' => 'Esa marcación ya no corresponde al siguiente paso de tu jornada. Actualiza la página.']);
        }

        $marcacion = Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'tipo' => $data['tipo'],
            'fecha_hora' => now(),
            'turno_id' => $colaborador->turnoDelDia()?->id,
            'qr_token_id' => $qrToken->id,
            'sucursal_id' => $qrToken->sucursal_id,
            'punto_venta_id' => $qrToken->punto_venta_id,
            'ip_origen' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()->route('marcacion.confirmacion', $marcacion);
    }

    public function confirmacion(Request $request, Marcacion $marcacion): View
    {
        abort_unless($marcacion->colaborador->user_id === $request->user()->id, 403);

        return view('marcacion.confirmacion', ['marcacion' => $marcacion]);
    }

    private function ultimaMarcacionDeHoy(Colaborador $colaborador): ?Marcacion
    {
        return $colaborador->marcaciones()
            ->whereDate('fecha_hora', now()->toDateString())
            ->latest('fecha_hora')
            ->first();
    }

    /**
     * Máquina de estados simple del día: determina qué tipo(s) de marcación
     * son válidos a continuación, para no dejar marcar "salida" sin haber
     * marcado "entrada", ni permitir una jornada ya cerrada.
     *
     * @return array<int, string>
     */
    private function siguientesTiposPermitidos(Colaborador $colaborador): array
    {
        $ultima = $this->ultimaMarcacionDeHoy($colaborador);

        return match ($ultima?->tipo) {
            null => [Marcacion::TIPO_ENTRADA],
            Marcacion::TIPO_ENTRADA, Marcacion::TIPO_REGRESO_REFRIGERIO => [
                Marcacion::TIPO_SALIDA_REFRIGERIO,
                Marcacion::TIPO_SALIDA,
            ],
            Marcacion::TIPO_SALIDA_REFRIGERIO => [Marcacion::TIPO_REGRESO_REFRIGERIO],
            Marcacion::TIPO_SALIDA => [],
            default => [],
        };
    }
}
