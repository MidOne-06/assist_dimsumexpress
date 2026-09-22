<?php

namespace App\Http\Controllers;

use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\QrToken;
use App\Support\JornadaMarcacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarcacionController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless($request->user()?->can('Registrar:Marcacion'), 403);

        $colaborador = $request->user()->colaborador;

        if (! $colaborador) {
            return view('marcacion.error', [
                'mensaje' => 'Tu usuario no está vinculado a ningún colaborador. Contacta a tu administrador.',
            ]);
        }

        if (! $colaborador->activo) {
            return view('marcacion.error', [
                'mensaje' => 'Tu cuenta de colaborador está inactiva. Contacta a tu administrador.',
            ]);
        }

        $token = $request->query('token');

        // Sin token: no es un intento de marcación fallido, es el estado
        // normal justo después de iniciar sesión (aún no escaneó nada).
        // Antes se mostraba el mismo mensaje de "no se pudo registrar tu
        // marcación" que un QR realmente vencido, lo cual confundía al
        // colaborador que recién se loguea.
        if (! $token) {
            $asignacion = JornadaMarcacion::asignacionVigente($colaborador);

            return view('marcacion.esperando', [
                'colaborador' => $colaborador,
                'asignacion' => $asignacion,
                'siguientesTipos' => $asignacion
                    ? JornadaMarcacion::siguientesTipos($colaborador, $asignacion)
                    : [],
                'ultimaMarcacion' => $asignacion
                    ? JornadaMarcacion::ultimaMarcacion($colaborador, $asignacion)
                    : null,
                'retornoEsperado' => $asignacion
                    ? JornadaMarcacion::retornoRefrigerioEsperado($colaborador, $asignacion)
                    : null,
            ]);
        }

        $qrToken = QrToken::with(['sucursal', 'puntoVenta'])->where('token', $token)->first();

        if (! $qrToken || ! $qrToken->vigentePara(QrToken::PROPOSITO_ASISTENCIA)) {
            return view('marcacion.error', [
                'mensaje' => 'El código QR expiró o no es válido. Vuelve a escanear el código de la pantalla.',
            ]);
        }

        if (! $this->estacionPermitida($colaborador, $qrToken)) {
            return view('marcacion.error', [
                'mensaje' => 'Este código QR no corresponde a tu centro de trabajo.',
            ]);
        }

        // Un QR dinámico solo habilita una acción por colaborador. Así no
        // puede reutilizarse al volver atrás en el navegador para confirmar
        // una salida sin escanear nuevamente la estación.
        if ($this->qrYaUsadoPorColaborador($colaborador, $qrToken)) {
            return view('marcacion.error', [
                'mensaje' => 'Este código QR ya fue usado para una marcación. Escanea el nuevo QR de la pantalla para continuar.',
            ]);
        }

        $asignacion = JornadaMarcacion::asignacionVigente($colaborador);

        if (! $asignacion) {
            return view('marcacion.error', [
                'mensaje' => 'No tienes un turno activo para marcar en este momento. Revisa tu horario o contacta a tu supervisor.',
            ]);
        }

        return view('marcacion.show', [
            'colaborador' => $colaborador,
            'token' => $qrToken->token,
            'asignacion' => $asignacion,
            'siguientesTipos' => JornadaMarcacion::siguientesTipos($colaborador, $asignacion),
            'ultimaMarcacion' => JornadaMarcacion::ultimaMarcacion($colaborador, $asignacion),
            'retornoEsperado' => JornadaMarcacion::retornoRefrigerioEsperado($colaborador, $asignacion),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('Registrar:Marcacion'), 403);

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

        $qrToken = QrToken::with(['sucursal', 'puntoVenta'])->where('token', $data['token'])->first();

        if (! $qrToken || ! $qrToken->vigentePara(QrToken::PROPOSITO_ASISTENCIA)) {
            return back()->withErrors(['tipo' => 'El código QR expiró. Vuelve a escanearlo desde la pantalla.']);
        }

        if (! $this->estacionPermitida($colaborador, $qrToken)) {
            return back()->withErrors(['tipo' => 'Este código QR no corresponde a tu centro de trabajo.']);
        }

        $marcacion = DB::transaction(function () use ($colaborador, $data, $qrToken, $request): Marcacion {
            // El bloqueo evita doble marcación por doble toque en el celular.
            $colaboradorBloqueado = Colaborador::query()->lockForUpdate()->findOrFail($colaborador->id);

            if (! $colaboradorBloqueado->activo) {
                throw ValidationException::withMessages(['tipo' => 'Tu cuenta de colaborador está inactiva. Contacta a tu administrador.']);
            }

            $asignacion = JornadaMarcacion::asignacionVigente($colaboradorBloqueado);

            if (! $asignacion) {
                throw ValidationException::withMessages(['tipo' => 'No tienes un turno activo para marcar en este momento.']);
            }

            // Se repite dentro de la transacción, después de bloquear al
            // colaborador, para impedir que dos pestañas reutilicen el mismo
            // QR antes de que una de ellas termine de registrar la acción.
            if ($this->qrYaUsadoPorColaborador($colaboradorBloqueado, $qrToken)) {
                throw ValidationException::withMessages(['tipo' => 'Este código QR ya fue usado para una marcación. Escanea el nuevo QR de la pantalla para continuar.']);
            }

            if (! in_array($data['tipo'], JornadaMarcacion::siguientesTipos($colaboradorBloqueado, $asignacion), true)) {
                throw ValidationException::withMessages(['tipo' => 'Esa marcación ya no corresponde al siguiente paso de tu jornada. Actualiza la página.']);
            }

            $fechaHora = now();
            $controlRefrigerio = null;

            if ($data['tipo'] === Marcacion::TIPO_REGRESO_REFRIGERIO) {
                $salidaRefrigerio = JornadaMarcacion::ultimaMarcacion($colaboradorBloqueado, $asignacion);

                if ($salidaRefrigerio?->tipo !== Marcacion::TIPO_SALIDA_REFRIGERIO) {
                    throw ValidationException::withMessages(['tipo' => 'No se encontró la salida a refrigerio de esta jornada. Actualiza la página.']);
                }

                $controlRefrigerio = JornadaMarcacion::controlRetornoRefrigerio($salidaRefrigerio, $fechaHora);
            }

            return Marcacion::create([
                'colaborador_id' => $colaboradorBloqueado->id,
                'tipo' => $data['tipo'],
                'fecha_hora' => $fechaHora,
                'refrigerio_retorno_esperado_en' => $controlRefrigerio['esperado'] ?? null,
                'refrigerio_diferencia_segundos' => $controlRefrigerio['diferencia_segundos'] ?? null,
                'turno_id' => $asignacion->turno_id,
                'qr_token_id' => $qrToken->id,
                'sucursal_id' => $qrToken->sucursal_id,
                'punto_venta_id' => $qrToken->punto_venta_id,
                'ip_origen' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        });

        return redirect()->route('marcacion.confirmacion', $marcacion);
    }

    public function confirmacion(Request $request, Marcacion $marcacion): View
    {
        abort_unless($request->user()?->can('Registrar:Marcacion'), 403);
        abort_unless($marcacion->colaborador->user_id === $request->user()->id, 403);

        return view('marcacion.confirmacion', [
            'marcacion' => $marcacion,
            'retornoEsperado' => $marcacion->tipo === Marcacion::TIPO_SALIDA_REFRIGERIO
                ? $marcacion->fecha_hora->copy()->addMinutes(JornadaMarcacion::DURACION_REFRIGERIO_MINUTOS)
                : null,
        ]);
    }

    private function estacionPermitida(Colaborador $colaborador, QrToken $qrToken): bool
    {
        if (! $qrToken->sucursal?->activo || ($qrToken->punto_venta_id && ! $qrToken->puntoVenta?->activo)) {
            return false;
        }

        if ($qrToken->sucursal_id !== $colaborador->sucursal_id) {
            return false;
        }

        return ! $qrToken->punto_venta_id || $qrToken->punto_venta_id === $colaborador->punto_venta_id;
    }

    private function qrYaUsadoPorColaborador(Colaborador $colaborador, QrToken $qrToken): bool
    {
        return Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('qr_token_id', $qrToken->id)
            ->exists();
    }
}
