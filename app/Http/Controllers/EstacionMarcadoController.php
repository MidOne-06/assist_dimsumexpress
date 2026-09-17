<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\QrToken;
use App\Models\Sucursal;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class EstacionMarcadoController extends Controller
{
    /**
     * Segundos de vigencia de cada código QR. Coincide con el rango
     * recomendado por el usuario (15-30s) para el refresco de la pantalla.
     */
    private const VIGENCIA_SEGUNDOS = 20;

    public function show(Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): View
    {
        $this->validarPuntoVenta($sucursal, $puntoVenta);

        return view('estacion-marcado.show', [
            'sucursal' => $sucursal,
            'puntoVenta' => $puntoVenta,
            'vigenciaSegundos' => self::VIGENCIA_SEGUNDOS,
        ]);
    }

    public function token(Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): JsonResponse
    {
        $this->validarPuntoVenta($sucursal, $puntoVenta);

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

    private function validarPuntoVenta(Sucursal $sucursal, ?PuntoVenta $puntoVenta): void
    {
        abort_if($puntoVenta && $puntoVenta->sucursal_id !== $sucursal->id, 404);
    }
}
