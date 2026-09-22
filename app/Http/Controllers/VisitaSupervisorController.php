<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use App\Support\AlcanceSupervisor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitaSupervisorController extends Controller
{
    public function show(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): View
    {
        abort_if($puntoVenta && $puntoVenta->sucursal_id !== $sucursal->id, 404);
        abort_unless($sucursal->activo && (! $puntoVenta || $puntoVenta->activo), 404);

        $claveEsperada = $puntoVenta?->token_pantalla ?? $sucursal->token_pantalla;
        abort_unless(
            $claveEsperada && hash_equals($claveEsperada, (string) $request->query('clave')),
            404,
        );

        $usuario = $request->user();
        abort_unless(
            $usuario instanceof User
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
}
