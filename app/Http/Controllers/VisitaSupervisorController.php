<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\QrToken;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use App\Support\AlcanceSupervisor;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VisitaSupervisorController extends Controller
{
    private const VIGENCIA_SEGUNDOS = 60;

    /** Punto de entrada móvil para un supervisor, antes de escanear el QR. */
    public function esperando(Request $request): View
    {
        $this->validarSupervisor($request->user());

        return view('visitas-supervisor.esperando');
    }

    /** Pantalla física del QR; abrirla no crea una visita. */
    public function estacion(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): View
    {
        $this->validarEstacion($request, $sucursal, $puntoVenta);

        return view('estacion-visita.show', [
            'sucursal' => $sucursal,
            'puntoVenta' => $puntoVenta,
            'clave' => $request->query('clave'),
            'vigenciaSegundos' => self::VIGENCIA_SEGUNDOS,
        ]);
    }

    /** Emite un QR de visita que vence en segundos, como el de asistencia. */
    public function token(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): JsonResponse
    {
        $this->validarEstacion($request, $sucursal, $puntoVenta);

        $qrToken = QrToken::generarPara(
            $sucursal,
            $puntoVenta,
            self::VIGENCIA_SEGUNDOS,
            QrToken::PROPOSITO_VISITA_SUPERVISOR,
        );
        $urlVisita = route('visita-supervisor.show', ['token' => $qrToken->token]);
        $qr = (new Builder(writer: new SvgWriter(), data: $urlVisita, size: 340, margin: 12))->build()->getDataUri();

        return response()->json([
            'qr' => $qr,
            'segundos_restantes' => self::VIGENCIA_SEGUNDOS,
        ]);
    }

    public function show(Request $request): View|Response
    {
        [$qrToken, $sucursal, $puntoVenta] = $this->resolverQrAutorizado($request);

        // Un GET nunca modifica datos: así una previsualización de enlace,
        // la cámara nativa o una carga anticipada del navegador no puede
        // convertirse accidentalmente en una visita registrada.
        return view('visitas-supervisor.confirmar', compact('qrToken', 'sucursal', 'puntoVenta'));
    }

    /** Confirma explícitamente la visita después de leer y validar el QR. */
    public function store(Request $request): View|Response
    {
        $request->validate(['token' => ['required', 'string']]);
        [$qrToken, $sucursal, $puntoVenta] = $this->resolverQrAutorizado($request, (string) $request->input('token'));
        $usuario = $request->user();

        [$visita, $nueva] = DB::transaction(function () use ($usuario, $sucursal, $puntoVenta, $qrToken, $request): array {
            // Serializa las confirmaciones del mismo supervisor. Conserva la
            // regla vigente (una visita por local/día) sin depender de que el
            // índice único dispare una excepción bajo doble toque.
            User::query()->lockForUpdate()->findOrFail($usuario->id);

            $fecha = today()->toDateString();
            $visita = VisitaSupervisor::query()
                ->where('supervisor_id', $usuario->id)
                ->where('sucursal_id', $sucursal->id)
                ->whereDate('fecha', $fecha)
                ->first();

            if ($visita) {
                return [$visita, false];
            }

            return [VisitaSupervisor::create([
                'supervisor_id' => $usuario->id,
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta?->id,
                'qr_token_id' => $qrToken->id,
                'fecha' => $fecha,
                'fecha_hora' => now(),
                'ip_origen' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]), true];
        });

        return view('visitas-supervisor.confirmada', [
            'sucursal' => $sucursal,
            'puntoVenta' => $puntoVenta,
            'visita' => $visita,
            'nueva' => $nueva,
        ]);
    }

    /** @return array{QrToken, Sucursal, ?PuntoVenta} */
    private function resolverQrAutorizado(Request $request, ?string $token = null): array
    {
        $qrToken = QrToken::query()
            ->with(['sucursal', 'puntoVenta'])
            ->where('token', $token ?? (string) $request->query('token'))
            ->first();

        if (! $qrToken?->vigentePara(QrToken::PROPOSITO_VISITA_SUPERVISOR)) {
            abort(response()->view('visitas-supervisor.expirada', status: 410));
        }

        $sucursal = $qrToken->sucursal;
        $puntoVenta = $qrToken->puntoVenta;
        abort_unless($sucursal?->activo && (! $qrToken->punto_venta_id || $puntoVenta?->activo), 404);

        $usuario = $request->user();
        $this->validarSupervisor($usuario);
        abort_unless(AlcanceSupervisor::puedeGestionarSucursal($usuario, $sucursal->id), 403);

        return [$qrToken, $sucursal, $puntoVenta];
    }

    private function validarEstacion(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta): void
    {
        // Igual que asistencia, la visita se registra contra una estación de
        // punto de venta; una sucursal no puede emitir QR por sí sola.
        abort_unless($puntoVenta instanceof PuntoVenta, 404);
        abort_if($puntoVenta->sucursal_id !== $sucursal->id, 404);
        abort_unless($sucursal->activo && $puntoVenta->activo, 404);

        $claveEsperada = $puntoVenta->token_pantalla;
        abort_unless($claveEsperada && hash_equals($claveEsperada, (string) $request->query('clave')), 404);
    }

    private function validarSupervisor(mixed $usuario): void
    {
        abort_unless(
            $usuario instanceof User
                && $usuario->hasRole('supervisor')
                && $usuario->can('Registrar:VisitaSupervisor'),
            403,
        );
    }
}
