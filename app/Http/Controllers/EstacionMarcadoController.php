<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\QrToken;
use App\Models\Sucursal;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EstacionMarcadoController extends Controller
{
    /**
     * Segundos de vigencia de cada código QR. Coincide con el rango
     * recomendado por el usuario (15-30s) para el refresco de la pantalla.
     */
    private const VIGENCIA_SEGUNDOS = 20;

    public function show(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): View
    {
        $this->validarEstacion($request, $sucursal, $puntoVenta);

        return view('estacion-marcado.show', [
            'sucursal' => $sucursal,
            'puntoVenta' => $puntoVenta,
            'clave' => $request->query('clave'),
            'vigenciaSegundos' => self::VIGENCIA_SEGUNDOS,
        ]);
    }

    public function token(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta = null): JsonResponse
    {
        $this->validarEstacion($request, $sucursal, $puntoVenta);

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

    /**
     * Sin esta validación, cualquiera que conociera o enumerara un ID
     * numérico de sucursal/punto de venta (1, 2, 3...) podía generar un QR
     * válido y marcar asistencia sin estar físicamente en la tienda. La
     * "clave" es un secreto largo y aleatorio (token_pantalla) que solo debe
     * conocer la pantalla física de esa estación -- se compara con
     * hash_equals para evitar timing attacks.
     */
    private function validarEstacion(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta): void
    {
        // Una estación de asistencia siempre representa un punto de venta.
        // La sucursal es únicamente su contenedor organizacional.
        abort_unless($puntoVenta instanceof PuntoVenta, 404);
        abort_if($puntoVenta->sucursal_id !== $sucursal->id, 404);
        abort_unless($sucursal->activo && $puntoVenta->activo, 404);

        $claveEsperada = $puntoVenta->token_pantalla;
        $claveRecibida = (string) $request->query('clave');

        abort_unless(
            $claveEsperada && $claveRecibida !== '' && hash_equals($claveEsperada, $claveRecibida),
            404
        );
    }
}
