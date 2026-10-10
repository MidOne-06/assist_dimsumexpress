<?php

namespace App\Services;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Illuminate\Http\Request;

/** Valida la clave privada de una estación física de QR. */
final class EstacionQrAccessService
{
    public function validarRequest(Request $request, Sucursal $sucursal, ?PuntoVenta $puntoVenta): void
    {
        $this->validarClave($sucursal, $puntoVenta, (string) $request->query('clave'));
    }

    public function validarClave(Sucursal $sucursal, ?PuntoVenta $puntoVenta, string $clave): void
    {
        abort_unless($puntoVenta instanceof PuntoVenta, 404);
        abort_if($puntoVenta->sucursal_id !== $sucursal->id, 404);
        abort_unless($sucursal->activo && $puntoVenta->activo, 404);

        $esperada = $puntoVenta->token_pantalla;
        abort_unless($esperada && $clave !== '' && hash_equals($esperada, $clave), 404);
    }
}
