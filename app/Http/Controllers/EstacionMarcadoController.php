<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\QrToken;
use App\Models\Sucursal;
use App\Services\EstacionQrAccessService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EstacionMarcadoController extends Controller
{
    /**
     * Segundos de vigencia de cada código QR. Coincide con el rango
     * suficiente para abrir la cámara y cargar la confirmación móvil.
     */
    private const VIGENCIA_SEGUNDOS = 60;

    public function __construct(private readonly EstacionQrAccessService $estacionAccess)
    {
    }

    public function show(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): View
    {
        $this->estacionAccess->validarRequest($request, $sucursal, $puntoVenta);

        return view('estacion-marcado.show', [
            'sucursal' => $sucursal,
            'puntoVenta' => $puntoVenta,
            'clave' => $request->query('clave'),
            'vigenciaSegundos' => self::VIGENCIA_SEGUNDOS,
        ]);
    }

    public function token(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): JsonResponse
    {
        $this->estacionAccess->validarRequest($request, $sucursal, $puntoVenta);

        $qrToken = QrToken::generarPara($sucursal, $puntoVenta, self::VIGENCIA_SEGUNDOS);

        $marcarUrl = route('marcacion.show', ['token' => $qrToken->token]);

        // SVG en vez de PNG: no depende de la extensión GD de PHP (no está
        // habilitada en esta imagen) y se ve igual de nítido en la pantalla.
        $resultado = (new Builder(
            writer: new SvgWriter(),
            data: $marcarUrl,
            size: 340,
            margin: 12,
        ))->build();

        return response()->json([
            'qr' => $resultado->getDataUri(),
            'segundos_restantes' => self::VIGENCIA_SEGUNDOS,
        ]);
    }

}
