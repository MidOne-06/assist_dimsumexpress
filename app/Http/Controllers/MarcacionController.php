<?php

namespace App\Http\Controllers;

use App\Models\AsignacionTurno;
use App\Models\CoberturaOperativa;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\QrToken;
use App\Services\AparienciaSistemaService;
use App\Services\ConsolidacionJornadaService;
use App\Support\JornadaMarcacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarcacionController extends Controller
{
    /**
     * Valida el QR recién leído sin crear una marcación.
     *
     * La cámara usa este endpoint antes de llevar al colaborador a la
     * confirmación. La misma lógica se vuelve a comprobar al guardar para
     * que una lectura válida no pueda reutilizarse ni alterarse desde el
     * navegador.
     */
    public function validarQr(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('Registrar:Marcacion'), 403);

        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $colaborador = $request->user()->colaborador;

        if (! $colaborador || ! $colaborador->activo) {
            return $this->respuestaQrNoConfirmado('Tu cuenta de colaborador no está habilitada para marcar.');
        }

        $qrToken = QrToken::with(['sucursal', 'puntoVenta'])
            ->where('token', $data['token'])
            ->first();

        if (! $qrToken || ! $qrToken->vigentePara(QrToken::PROPOSITO_ASISTENCIA)) {
            return $this->respuestaQrNoConfirmado('El código QR cambió o venció. Escanea el código actual de la pantalla.');
        }

        if ($this->qrYaUsadoPorColaborador($colaborador, $qrToken)) {
            return $this->respuestaQrNoConfirmado('Este código QR ya fue utilizado. Espera el nuevo código de la pantalla y vuelve a escanear.');
        }

        $asignacion = JornadaMarcacion::asignacionVigente($colaborador);

        if (! $asignacion) {
            return $this->respuestaQrNoConfirmado('No tienes una jornada habilitada para marcar en este momento.');
        }

        if (! $this->estacionPermitida($colaborador, $qrToken, $asignacion)) {
            return $this->respuestaQrNoConfirmado('La estación no está habilitada para tu jornada actual.');
        }

        $acciones = JornadaMarcacion::acciones($colaborador, $asignacion);
        $siguientesTipos = collect($acciones)
            ->filter(fn (array $accion): bool => $accion['habilitada'])
            ->pluck('tipo')
            ->all();

        if ($siguientesTipos === []) {
            return $this->respuestaQrNoConfirmado('Tu jornada ya no tiene acciones pendientes.');
        }

        return response()->json([
            'confirmado' => true,
            'mensaje' => 'QR escaneado correctamente',
            'estacion' => [
                'sucursal' => $qrToken->sucursal->nombre,
                'punto_venta' => $qrToken->puntoVenta?->nombre,
            ],
            'acciones' => $acciones,
        ]);
    }

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
                'apariencia' => app(AparienciaSistemaService::class),
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
                'resumenJornada' => $asignacion ? JornadaMarcacion::resumen($colaborador, $asignacion) : null,
            ]);
        }

        $qrToken = QrToken::with(['sucursal', 'puntoVenta'])->where('token', $token)->first();

        if (! $qrToken || ! $qrToken->vigentePara(QrToken::PROPOSITO_ASISTENCIA)) {
            return view('marcacion.error', [
                'mensaje' => 'El código QR expiró o no es válido. Vuelve a escanear el código de la pantalla.',
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

        if (! $this->estacionPermitida($colaborador, $qrToken, $asignacion)) {
            return view('marcacion.error', [
                'mensaje' => 'No tienes un turno activo para marcar en este momento.',
            ]);
        }

        return view('marcacion.show', [
            'apariencia' => app(AparienciaSistemaService::class),
            'colaborador' => $colaborador,
            'token' => $qrToken->token,
            'asignacion' => $asignacion,
            'siguientesTipos' => JornadaMarcacion::siguientesTipos($colaborador, $asignacion),
            'acciones' => JornadaMarcacion::acciones($colaborador, $asignacion),
            'ultimaMarcacion' => JornadaMarcacion::ultimaMarcacion($colaborador, $asignacion),
            'retornoEsperado' => JornadaMarcacion::retornoRefrigerioEsperado($colaborador, $asignacion),
            'resumenJornada' => JornadaMarcacion::resumen($colaborador, $asignacion),
            'estacion' => $qrToken,
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

            if (! $this->estacionPermitida($colaboradorBloqueado, $qrToken, $asignacion)) {
                throw ValidationException::withMessages(['tipo' => 'La estación no está habilitada para tu jornada actual.']);
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

            // La primera entrada puede detectar que la operación cambió el
            // turno del día. El ajuste se guarda aquí, después de validar el
            // flujo y dentro del mismo bloqueo transaccional; abrir un QR no
            // modifica por sí solo la programación semanal.
            if ($data['tipo'] === Marcacion::TIPO_ENTRADA) {
                JornadaMarcacion::confirmarAjusteAutomatico($colaboradorBloqueado, $asignacion, $fechaHora);
            }

            $controlRefrigerio = null;
            $cobertura = $this->registrarCoberturaAutomatica($colaboradorBloqueado, $asignacion, $qrToken, $fechaHora);

            if ($data['tipo'] === Marcacion::TIPO_REGRESO_REFRIGERIO) {
                $salidaRefrigerio = JornadaMarcacion::ultimaMarcacion($colaboradorBloqueado, $asignacion);

                if ($salidaRefrigerio?->tipo !== Marcacion::TIPO_SALIDA_REFRIGERIO) {
                    throw ValidationException::withMessages(['tipo' => 'No se encontró la salida a refrigerio de esta jornada. Actualiza la página.']);
                }

                $controlRefrigerio = JornadaMarcacion::controlRetornoRefrigerio(
                    $salidaRefrigerio,
                    $fechaHora,
                    JornadaMarcacion::minutosRefrigerio($asignacion),
                );
            }

            $marcacion = Marcacion::create([
                'colaborador_id' => $colaboradorBloqueado->id,
                'empresa_id' => $colaboradorBloqueado->empresa_id,
                'area_id' => $colaboradorBloqueado->area_id,
                'tipo' => $data['tipo'],
                'fecha_hora' => $fechaHora,
                'refrigerio_retorno_esperado_en' => $controlRefrigerio['esperado'] ?? null,
                'refrigerio_diferencia_segundos' => $controlRefrigerio['diferencia_segundos'] ?? null,
                'turno_id' => $asignacion->turno_id,
                'qr_token_id' => $qrToken->id,
                'sucursal_id' => $qrToken->sucursal_id,
                'punto_venta_id' => $qrToken->punto_venta_id,
                'cobertura_operativa_id' => $cobertura?->id,
                'ip_origen' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            if ($marcacion->tipo === Marcacion::TIPO_SALIDA) {
                app(ConsolidacionJornadaService::class)->consolidar($colaboradorBloqueado, $asignacion);
            }

            return $marcacion;
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
                ? $marcacion->fecha_hora->copy()->addMinutes($marcacion->turno?->incluye_refrigerio ? $marcacion->turno->refrigerio_minutos : 0)
                : null,
            'resumenJornada' => $marcacion->tipo === Marcacion::TIPO_SALIDA
                ? JornadaMarcacion::resumen($marcacion->colaborador, $this->asignacionDeMarcacion($marcacion))
                : null,
        ]);
    }

    private function asignacionDeMarcacion(Marcacion $marcacion): AsignacionTurno
    {
        $fechaMarcacion = $marcacion->fecha_hora->toDateString();
        $fechaAnterior = $marcacion->fecha_hora->copy()->subDay()->toDateString();

        $asignacion = $marcacion->colaborador->asignacionesTurno()
            ->with('turno')
            ->where('turno_id', $marcacion->turno_id)
            ->where(function ($query) use ($fechaMarcacion, $fechaAnterior): void {
                $query->whereDate('fecha', $fechaMarcacion)
                    ->orWhereDate('fecha', $fechaAnterior);
            })
            ->get()
            ->first(fn (AsignacionTurno $candidata): bool => $marcacion->fecha_hora->betweenIncluded(
                JornadaMarcacion::limites($candidata)['ventana_inicio'],
                JornadaMarcacion::limites($candidata)['jornada_fin_maximo'],
            ));

        if (! $asignacion) {
            throw (new ModelNotFoundException())->setModel(AsignacionTurno::class);
        }

        return $asignacion;
    }

    private function estacionPermitida(Colaborador $colaborador, QrToken $qrToken, ?AsignacionTurno $asignacion = null): bool
    {
        if (! $qrToken->sucursal?->activo || ($qrToken->punto_venta_id && ! $qrToken->puntoVenta?->activo)) {
            return false;
        }

        if ($this->esEstacionBase($colaborador, $qrToken)) {
            return true;
        }

        // Un turno vigente permite cubrir temporalmente una estación activa
        // sin alterar la sede base del colaborador. La cobertura se persiste
        // recién al confirmar la marcación, no al previsualizar el QR.
        return $asignacion !== null;
    }

    private function esEstacionBase(Colaborador $colaborador, QrToken $qrToken): bool
    {
        if ((int) $qrToken->sucursal_id !== (int) $colaborador->sucursal_id) {
            return false;
        }

        // La sede base puede no estar amarrada a una caja específica. En ese
        // caso cualquier punto de venta activo de la misma sucursal es parte
        // de su sede habitual, no una cobertura. Si sí existe caja base,
        // marcar en otra caja queda trazado como cobertura operativa.
        return ! $qrToken->punto_venta_id
            || ! $colaborador->punto_venta_id
            || (int) $qrToken->punto_venta_id === (int) $colaborador->punto_venta_id;
    }

    private function registrarCoberturaAutomatica(Colaborador $colaborador, AsignacionTurno $asignacion, QrToken $qrToken, \Carbon\Carbon $detectadaEn): ?CoberturaOperativa
    {
        if ($this->esEstacionBase($colaborador, $qrToken)) {
            return null;
        }

        // La marcación no debe quedar indisponible si una instancia web se
        // inicia mientras aún se aplica la migración de coberturas. La sede y
        // el punto de venta reales igualmente quedan guardados en Marcacion.
        if (! Schema::hasTable('coberturas_operativas')) {
            return null;
        }

        return CoberturaOperativa::firstOrCreate(
            [
                'asignacion_turno_id' => $asignacion->id,
                'sucursal_id' => $qrToken->sucursal_id,
                'punto_venta_id' => $qrToken->punto_venta_id,
            ],
            [
                'colaborador_id' => $colaborador->id,
                'origen' => CoberturaOperativa::ORIGEN_AUTOMATICA,
                'estado' => CoberturaOperativa::ESTADO_PENDIENTE,
                'detectada_en' => $detectadaEn,
            ],
        );
    }

    private function qrYaUsadoPorColaborador(Colaborador $colaborador, QrToken $qrToken): bool
    {
        return Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('qr_token_id', $qrToken->id)
            ->exists();
    }

    private function respuestaQrNoConfirmado(string $mensaje): JsonResponse
    {
        return response()->json([
            'confirmado' => false,
            'mensaje' => $mensaje,
        ], 422);
    }

    private function etiquetaMarcacion(string $tipo): string
    {
        return match ($tipo) {
            Marcacion::TIPO_ENTRADA => 'Marcar ingreso de turno',
            Marcacion::TIPO_SALIDA_REFRIGERIO => 'Marcar salida de refrigerio',
            Marcacion::TIPO_REGRESO_REFRIGERIO => 'Marcar ingreso de refrigerio',
            Marcacion::TIPO_SALIDA => 'Marcar salida de turno',
        };
    }
}
