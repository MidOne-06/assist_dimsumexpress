<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use App\Support\AlcanceSupervisor;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitaSupervisorController extends Controller
{
    /** Pantalla física del QR; abrirla no crea una visita. */
    public function estacion(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): View
    {
        $this->validarEstacion($request, $sucursal, $puntoVenta);

        $urlVisita = route('visita-supervisor.show', [
            'sucursal' => $sucursal->id,
            'puntoVenta' => $puntoVenta?->id,
            'clave' => $request->query('clave'),
        ]);
        $qr = (new Builder(writer: new SvgWriter(), data: $urlVisita, size: 340, margin: 12))->build()->getDataUri();

        return view('estacion-visita.show', compact('sucursal', 'puntoVenta', 'qr'));
    }

    public function show(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): View
    {
        $this->validarEstacion($request, $sucursal, $puntoVenta);

        $usuario = $request->user();
        abort_unless(
            $usuario instanceof User
                && $usuario->hasRole('supervisor')
                && $usuario->can('Registrar:VisitaSupervisor')
                && AlcanceSupervisor::puedeGestionarSucursal($usuario, $sucursal->id),
            403,
        );

        $visita = VisitaSupervisor::firstOrCreate(
            [
                'supervisor_id' => $usuario->id,
                'sucursal_id' => $sucursal->id,
                'fecha' => today()->toDateString(),
            ],
            [
                'punto_venta_id' => $puntoVenta?->id,
                'fecha_hora' => now(),
                'ip_origen' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ],
        );

        return view('visitas-supervisor.confirmada', [
            'sucursal' => $sucursal,
            'puntoVenta' => $puntoVenta,
            'visita' => $visita,
            'nueva' => $visita->wasRecentlyCreated,
        ]);
    }

    private function validarEstacion(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta): void
    {
        abort_if($puntoVenta && $puntoVenta->sucursal_id !== $sucursal->id, 404);
        abort_unless($sucursal->activo && (! $puntoVenta || $puntoVenta->activo), 404);

        $claveEsperada = $puntoVenta?->token_pantalla ?? $sucursal->token_pantalla;
        abort_unless($claveEsperada && hash_equals($claveEsperada, (string) $request->query('clave')), 404);
    }
}
