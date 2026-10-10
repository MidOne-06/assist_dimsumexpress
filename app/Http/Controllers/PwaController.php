<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Services\AparienciaSistemaService;
use App\Services\EstacionQrAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    /** Metadatos instalables para la aplicación operativa móvil. */
    public function manifest(AparienciaSistemaService $apariencia): JsonResponse
    {
        $logo = $apariencia->logoAppMovilUrl();

        return response()->json([
            'id' => route('login'),
            'name' => $apariencia->nombre(),
            'short_name' => 'Asistencias',
            'description' => 'Marcación y visitas de supervisión.',
            'start_url' => route('login'),
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0f172a',
            'theme_color' => $apariencia->colorPrimario(),
            'icons' => [[
                'src' => $logo,
                'sizes' => 'any',
                'type' => $apariencia->logoAppMovilMimeType(),
                'purpose' => 'any maskable',
            ]],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Manifest aislado para una pantalla física. Cada instalación conserva
     * únicamente el acceso a su propia estación, sin necesitar autenticarse.
     * La clave se valida antes de entregar el manifest porque start_url la
     * contiene para que el acceso instalado pueda seguir renovando su QR.
     */
    public function stationManifest(
        Request $request,
        string $tipo,
        Sucursal $sucursal,
        PuntoVenta $puntoVenta,
        AparienciaSistemaService $apariencia,
        EstacionQrAccessService $estacionAccess,
    ): JsonResponse {
        abort_unless(in_array($tipo, ['asistencia', 'visita'], true), 404);
        abort_if($puntoVenta->sucursal_id !== $sucursal->id, 404);
        abort_unless($sucursal->activo && $puntoVenta->activo, 404);

        $clave = (string) $request->query('clave');
        $estacionAccess->validarClave($sucursal, $puntoVenta, $clave);

        $esAsistencia = $tipo === 'asistencia';
        $rutaEstacion = route($esAsistencia ? 'estacion-marcado.show' : 'estacion-visita.show', [
            'sucursal' => $sucursal->id,
            'puntoVenta' => $puntoVenta->id,
        ]);
        $inicio = $rutaEstacion . '?clave=' . rawurlencode($clave);
        // Windows muestra `name` debajo del ícono de la PWA. Debe empezar
        // por el propósito de la estación para que se identifique aun cuando
        // el nombre se reduzca visualmente a dos o tres líneas.
        $nombreTipo = $esAsistencia ? 'QR Asistencia' : 'QR Visitas';
        $contexto = "{$sucursal->nombre} · {$puntoVenta->nombre}";

        return response()->json([
            // El id no lleva la clave: identifica la misma estación aun cuando
            // se regenere su acceso por motivos de seguridad.
            'id' => "/pwa-estacion/{$tipo}/{$sucursal->id}/{$puntoVenta->id}",
            'name' => "{$nombreTipo} · {$puntoVenta->nombre}",
            'short_name' => $nombreTipo,
            'description' => "Estación {$nombreTipo} · {$contexto}",
            'start_url' => $inicio,
            'scope' => $rutaEstacion,
            'display' => 'standalone',
            'background_color' => '#111827',
            'theme_color' => $apariencia->colorPrimario(),
            'icons' => [[
                'src' => $apariencia->logoAppMovilUrl(),
                'sizes' => 'any',
                'type' => $apariencia->logoAppMovilMimeType(),
                'purpose' => 'any maskable',
            ]],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            // El manifest contiene una URL privada de estación. No debe quedar
            // guardado en caches compartidos ni navegadores intermedios.
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * La PWA solo conserva recursos estáticos. Las marcaciones, QR y sesiones
     * siempre se consultan en red para mantener la validación dinámica.
     */
    public function serviceWorker(): Response
    {
        return response()
            ->view('pwa.service-worker')
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Service-Worker-Allowed', '/');
    }
}
