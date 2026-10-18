<?php

namespace App\Http\Controllers;

use App\Models\Marcacion;
use App\Models\QrToken;
use App\Services\AparienciaSistemaService;
use App\Services\MarcacionFlowService;
use App\Services\RegistrarMarcacionService;
use App\Support\PresentacionConfirmacionMarcacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarcacionController extends Controller
{
    public function __construct(
        private readonly MarcacionFlowService $flujo,
        private readonly RegistrarMarcacionService $registro,
    )
    {
    }

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

        if ($this->flujo->qrYaUsadoPorColaborador($colaborador, $qrToken)) {
            return $this->respuestaQrNoConfirmado('Este código QR ya fue utilizado. Espera el nuevo código de la pantalla y vuelve a escanear.');
        }

        $asignacion = $this->flujo->resolverAsignacion($colaborador, $qrToken, now());

        if (! $this->flujo->estacionPermitida($colaborador, $qrToken, $asignacion)) {
            return $this->respuestaQrNoConfirmado('Este código no está disponible para tu marcación. Usa el QR mostrado en tu local.');
        }

        if ($this->flujo->tiposDisponibles($colaborador, $asignacion, now()) === []) {
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
        if ($this->flujo->qrYaUsadoPorColaborador($colaborador, $qrToken)) {
            return view('marcacion.error', [
                'mensaje' => 'Este código QR ya fue usado para una marcación. Escanea el nuevo QR de la pantalla para continuar.',
            ]);
        }

        $asignacion = $this->flujo->resolverAsignacion($colaborador, $qrToken, now());

        if (! $this->flujo->estacionPermitida($colaborador, $qrToken, $asignacion)) {
            return view('marcacion.error', [
                'mensaje' => 'Este código no está disponible para tu marcación. Usa el QR mostrado en tu local.',
            ]);
        }

        $tiposDisponibles = $this->flujo->tiposDisponibles($colaborador, $asignacion, now());
        if ($tiposDisponibles === []) {
            return view('marcacion.error', [
                'mensaje' => 'Tu jornada ya no admite más marcaciones por hoy. Consulta con tu supervisor si necesitas regularizarla.',
            ]);
        }

        return view('marcacion.show', [
            'apariencia' => app(AparienciaSistemaService::class),
            'colaborador' => $colaborador,
            'token' => $qrToken->token,
            'acciones' => $this->flujo->accionesPresentables($colaborador, $asignacion, now()),
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

        $marcacion = $this->registro->registrar(
            $colaborador,
            (string) $data['token'],
            (string) $data['accion'],
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('marcacion.confirmacion', $marcacion);
    }

    public function confirmacion(Request $request, Marcacion $marcacion): View
    {
        abort_unless($request->user()?->can('Registrar:Marcacion'), 403);
        $marcacion->loadMissing('colaborador');
        abort_unless($marcacion->colaborador?->user_id === $request->user()->id, 403);

        return view('marcacion.confirmacion', [
            'marcacion' => $marcacion,
            // El tipo se persiste dentro de la transacción de store(). Por
            // ello la pantalla confirma el evento real, sin inferirlo por la
            // hora ni exponer el turno interno del local al colaborador.
            'confirmacion' => PresentacionConfirmacionMarcacion::para($marcacion),
        ]);
    }

    private function respuestaQrNoConfirmado(string $mensaje): JsonResponse
    {
        return response()->json([
            'confirmado' => false,
            'mensaje' => $mensaje,
        ], 422);
    }

}
