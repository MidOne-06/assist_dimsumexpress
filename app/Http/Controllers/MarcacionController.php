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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
            return $this->respuestaQrNoConfirmado('No podemos habilitar la marcación de esta cuenta. Consulta con tu supervisor.');
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

        $asignacion = $this->resolverAsignacion($colaborador, $qrToken, now());

        if (! $this->estacionPermitida($colaborador, $qrToken, $asignacion)) {
            return $this->respuestaQrNoConfirmado('Este código no está disponible para tu marcación. Usa el QR mostrado en tu local.');
        }

        if ($this->tiposDisponibles($colaborador, $asignacion, now()) === []) {
            return $this->respuestaQrNoConfirmado('Tu jornada ya no admite más marcaciones por hoy. Consulta con tu supervisor si necesitas regularizarla.');
        }

        return response()->json([
            'confirmado' => true,
            'mensaje' => 'QR escaneado correctamente',
        ]);
    }

    public function show(Request $request): View
    {
        abort_unless($request->user()?->can('Registrar:Marcacion'), 403);

        $colaborador = $request->user()->colaborador;

        if (! $colaborador) {
            return view('marcacion.error', [
                'mensaje' => 'No podemos habilitar la marcación de esta cuenta. Consulta con tu supervisor.',
            ]);
        }

        if (! $colaborador->activo) {
            return view('marcacion.error', [
                'mensaje' => 'Tu cuenta no está disponible para marcar. Consulta con tu supervisor.',
            ]);
        }

        $token = $request->query('token');

        // Sin token: no es un intento de marcación fallido, es el estado
        // normal justo después de iniciar sesión (aún no escaneó nada).
        // Antes se mostraba el mismo mensaje de "no se pudo registrar tu
        // marcación" que un QR realmente vencido, lo cual confundía al
        // colaborador que recién se loguea.
        if (! $token) {
            return view('marcacion.esperando', [
                'apariencia' => app(AparienciaSistemaService::class),
                'colaborador' => $colaborador,
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

        $asignacion = $this->resolverAsignacion($colaborador, $qrToken, now());

        if (! $this->estacionPermitida($colaborador, $qrToken, $asignacion)) {
            return view('marcacion.error', [
                'mensaje' => 'Este código no está disponible para tu marcación. Usa el QR mostrado en tu local.',
            ]);
        }

        $tiposDisponibles = $this->tiposDisponibles($colaborador, $asignacion, now());
        if ($tiposDisponibles === []) {
            return view('marcacion.error', [
                'mensaje' => 'Tu jornada ya no admite más marcaciones por hoy. Consulta con tu supervisor si necesitas regularizarla.',
            ]);
        }

        return view('marcacion.show', [
            'apariencia' => app(AparienciaSistemaService::class),
            'colaborador' => $colaborador,
            'token' => $qrToken->token,
            'acciones' => $this->accionesPresentables($colaborador, $asignacion, now()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('Registrar:Marcacion'), 403);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'accion' => ['required', 'in:entrada,salida_refrigerio,regreso_refrigerio,salida'],
        ]);

        $colaborador = $request->user()->colaborador;
        abort_unless($colaborador, 403, 'Tu usuario no está vinculado a ningún colaborador.');

        $qrToken = QrToken::with(['sucursal', 'puntoVenta'])->where('token', $data['token'])->first();

        if (! $qrToken || ! $qrToken->vigentePara(QrToken::PROPOSITO_ASISTENCIA)) {
            return back()->withErrors(['token' => 'El código QR expiró. Vuelve a escanearlo desde la pantalla.']);
        }

        $marcacion = DB::transaction(function () use ($colaborador, $data, $qrToken, $request): Marcacion {
            // El bloqueo evita doble marcación por doble toque en el celular.
            $colaboradorBloqueado = Colaborador::query()->lockForUpdate()->findOrFail($colaborador->id);

            if (! $colaboradorBloqueado->activo) {
                throw ValidationException::withMessages(['token' => 'Tu cuenta no está disponible para marcar. Consulta con tu supervisor.']);
            }

            $fechaHora = now();
            $asignacion = $this->resolverAsignacion($colaboradorBloqueado, $qrToken, $fechaHora);

            if (! $this->estacionPermitida($colaboradorBloqueado, $qrToken, $asignacion)) {
                throw ValidationException::withMessages(['token' => 'Este código no está disponible para tu marcación. Usa el QR mostrado en tu local.']);
            }

            // Se repite dentro de la transacción, después de bloquear al
            // colaborador, para impedir que dos pestañas reutilicen el mismo
            // QR antes de que una de ellas termine de registrar la acción.
            if ($this->qrYaUsadoPorColaborador($colaboradorBloqueado, $qrToken)) {
                throw ValidationException::withMessages(['token' => 'Este código QR ya fue usado para una marcación. Escanea el nuevo QR de la pantalla para continuar.']);
            }

            $tiposDisponibles = $this->tiposDisponibles($colaboradorBloqueado, $asignacion, $fechaHora);
            $tipo = (string) $data['accion'];
            if (! in_array($tipo, $tiposDisponibles, true)) {
                throw ValidationException::withMessages(['accion' => 'Esta acción ya no está disponible. Escanea un nuevo QR y selecciona una acción válida.']);
            }

            if ($asignacion && ! $asignacion->exists) {
                if ($tipo !== Marcacion::TIPO_ENTRADA) {
                    throw ValidationException::withMessages(['token' => 'Registra primero tu ingreso.']);
                }

                $asignacion = AsignacionTurno::query()->firstOrCreate(
                    ['colaborador_id' => $colaboradorBloqueado->id, 'fecha' => $fechaHora->toDateString()],
                    $asignacion->getAttributes(),
                );
                $asignacion->load('turno');
            }

            // La primera entrada puede detectar que la operación cambió el
            // turno del día. El ajuste se guarda aquí, después de validar el
            // flujo y dentro del mismo bloqueo transaccional; abrir un QR no
            // modifica por sí solo la programación semanal.
            if ($asignacion && $tipo === Marcacion::TIPO_ENTRADA) {
                JornadaMarcacion::confirmarAjusteAutomatico($colaboradorBloqueado, $asignacion, $fechaHora);
            }

            $controlRefrigerio = null;
            $cobertura = $asignacion
                ? $this->registrarCoberturaAutomatica($colaboradorBloqueado, $asignacion, $qrToken, $fechaHora)
                : null;

            if ($asignacion && $tipo === Marcacion::TIPO_REGRESO_REFRIGERIO) {
                $salidaRefrigerio = JornadaMarcacion::ultimaMarcacion($colaboradorBloqueado, $asignacion);

                if ($salidaRefrigerio?->tipo !== Marcacion::TIPO_SALIDA_REFRIGERIO) {
                    throw ValidationException::withMessages(['token' => 'No se pudo continuar con la marcación. Actualiza la página e inténtalo nuevamente.']);
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
                'tipo' => $tipo,
                'fecha_hora' => $fechaHora,
                'refrigerio_retorno_esperado_en' => $controlRefrigerio['esperado'] ?? null,
                'refrigerio_diferencia_segundos' => $controlRefrigerio['diferencia_segundos'] ?? null,
                'turno_id' => $asignacion?->turno_id,
                'qr_token_id' => $qrToken->id,
                'sucursal_id' => $qrToken->sucursal_id,
                'punto_venta_id' => $qrToken->punto_venta_id,
                'cobertura_operativa_id' => $cobertura?->id,
                'ip_origen' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            if ($asignacion && $marcacion->tipo === Marcacion::TIPO_SALIDA) {
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

        return view('marcacion.confirmacion', compact('marcacion'));
    }

    /** @return array<int, string> */
    private function tiposDisponibles(Colaborador $colaborador, ?AsignacionTurno $asignacion, \Carbon\Carbon $momento): array
    {
        if ($asignacion) {
            return JornadaMarcacion::siguientesTipos($colaborador, $asignacion);
        }

        return array_values(array_filter([
            JornadaMarcacion::siguienteTipoSinTurnoAutomatico($colaborador, $momento),
        ]));
    }

    /**
     * Presenta todo el flujo de la jornada, sin convertir la interfaz en una
     * inferencia silenciosa. La validación definitiva permanece en store(),
     * por lo que un botón deshabilitado no puede habilitarse desde el cliente.
     *
     * @return array<int, array{tipo:string,etiqueta:string,icono:string,color:string,habilitada:bool,motivo:?string}>
     */
    private function accionesPresentables(Colaborador $colaborador, ?AsignacionTurno $asignacion, \Carbon\Carbon $momento): array
    {
        $acciones = $asignacion
            ? JornadaMarcacion::acciones($colaborador, $asignacion)
            : JornadaMarcacion::accionesSinTurno($colaborador, $momento);

        return collect($acciones)->map(function (array $accion): array {
            $presentacion = match ($accion['tipo']) {
                Marcacion::TIPO_ENTRADA => ['etiqueta' => 'Ingreso de turno', 'icono' => 'heroicon-o-arrow-right-on-rectangle', 'color' => 'success'],
                Marcacion::TIPO_SALIDA_REFRIGERIO => ['etiqueta' => 'Salida a refrigerio', 'icono' => 'heroicon-o-clock', 'color' => 'warning'],
                Marcacion::TIPO_REGRESO_REFRIGERIO => ['etiqueta' => 'Ingreso de refrigerio', 'icono' => 'heroicon-o-arrow-right-circle', 'color' => 'info'],
                default => ['etiqueta' => 'Salida de turno', 'icono' => 'heroicon-o-arrow-left-on-rectangle', 'color' => 'danger'],
            };

            return $presentacion + [
                'tipo' => $accion['tipo'],
                'habilitada' => $accion['habilitada'],
                'motivo' => $accion['motivo'],
            ];
        })->all();
    }

    /**
     * Una jornada ya iniciada conserva su turno. Antes de la primera entrada,
     * el turno efectivo se resuelve desde la estación QR (local/caja) por su
     * rango horario; la programación manual queda como respaldo cuando la
     * estación no tiene una regla que aplique. El cambio no se escribe hasta
     * que el POST confirma la lectura y queda auditado.
     */
    private function resolverAsignacion(Colaborador $colaborador, QrToken $qrToken, \Carbon\Carbon $momento): ?AsignacionTurno
    {
        $asignacion = JornadaMarcacion::asignacionVigente($colaborador, $momento, false);

        if ($asignacion && JornadaMarcacion::jornadaAbierta($colaborador, $asignacion)) {
            return $asignacion;
        }

        $yaMarcoHoy = $colaborador->marcaciones()
            ->whereBetween('fecha_hora', [$momento->copy()->startOfDay(), $momento->copy()->endOfDay()])
            ->exists();

        if (! $yaMarcoHoy) {
            $detectada = JornadaMarcacion::detectarTurnoOperativo(
                $colaborador,
                $qrToken->sucursal,
                $qrToken->puntoVenta,
                $momento,
            );

            if ($detectada) {
                $programada = $colaborador->asignacionesTurno()
                    ->with('turno')
                    ->whereDate('fecha', $momento->toDateString())
                    ->orderBy('id')
                    ->first();

                if (! $programada || $programada->turno_id === $detectada->turno_id) {
                    return $programada ?? $detectada;
                }

                return JornadaMarcacion::aplicarTurnoDetectado($programada, $detectada);
            }
        }

        if ($asignacion) {
            return $asignacion;
        }

        $manual = $colaborador->asignacionesTurno()
            ->with('turno')
            ->whereDate('fecha', $momento->toDateString())
            ->where(fn ($query) => $query->whereNull('origen')->orWhere('origen', '!=', 'detectado_automaticamente'))
            ->orderBy('id')
            ->first();

        if ($manual?->turno?->activo) {
            return $manual;
        }

        return JornadaMarcacion::detectarTurnoOperativo($colaborador, $qrToken->sucursal, $qrToken->puntoVenta, $momento);
    }

    private function estacionPermitida(Colaborador $colaborador, QrToken $qrToken, ?AsignacionTurno $asignacion = null): bool
    {
        if (! $qrToken->sucursal?->activo || ($qrToken->punto_venta_id && ! $qrToken->puntoVenta?->activo)) {
            return false;
        }

        if ($this->esEstacionBase($colaborador, $qrToken)) {
            return true;
        }

        // Una lectura válida jamás bloquea a un colaborador por no tener una
        // asignación previa. Si no hubo rango compatible, queda como marca
        // excepcional trazable con la estación real; si lo hubo, se vincula
        // al turno detectado y a la cobertura al confirmar el POST.
        return true;
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

}
