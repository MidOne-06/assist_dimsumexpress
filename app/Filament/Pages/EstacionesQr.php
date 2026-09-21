<?php

namespace App\Filament\Pages;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class EstacionesQr extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Estaciones QR';

    protected static ?string $title = 'Estaciones de marcado QR';

    protected string $view = 'filament.pages.estaciones-qr';

    /**
     * Esta pantalla revela enlaces que incluyen la clave privada de cada
     * estación. Reutiliza los permisos ya existentes de cada recurso para no
     * convertir el acceso al módulo en una vía de revelación de secretos.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('VerEnlace:Sucursal')
            || auth()->user()?->can('VerEnlace:PuntoVenta');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return array<int, array{tipo: string, nombre: string, ubicacion: string, url: string, qr: string}>
     */
    public function getEstacionesProperty(): array
    {
        $estaciones = [];
        $puedeVerSucursales = auth()->user()->can('VerEnlace:Sucursal');
        $puedeVerPuntosVenta = auth()->user()->can('VerEnlace:PuntoVenta');

        $sucursales = Sucursal::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        if ($puedeVerSucursales) {
            foreach ($sucursales as $sucursal) {
                $estaciones[] = $this->estacion(
                    tipo: 'Sucursal',
                    nombre: $sucursal->nombre,
                    ubicacion: $sucursal->tipo === 'planta' ? 'Planta' : 'Tienda',
                    url: $sucursal->enlaceEstacion(),
                );
            }
        }

        if ($puedeVerPuntosVenta) {
            PuntoVenta::query()
                ->where('activo', true)
                ->with('sucursal:id,nombre')
                ->orderBy('sucursal_id')
                ->orderBy('nombre')
                ->get()
                ->each(function (PuntoVenta $puntoVenta) use (&$estaciones): void {
                    $estaciones[] = $this->estacion(
                        tipo: 'Punto de venta',
                        nombre: $puntoVenta->nombre,
                        ubicacion: $puntoVenta->sucursal->nombre,
                        url: $puntoVenta->enlaceEstacion(),
                    );
                });
        }

        return $estaciones;
    }

    /**
     * @return array{tipo: string, nombre: string, ubicacion: string, url: string, qr: string}
     */
    private function estacion(string $tipo, string $nombre, string $ubicacion, string $url): array
    {
        $qr = (new Builder(
            writer: new SvgWriter(),
            data: $url,
            size: 240,
            margin: 8,
        ))->build();

        return [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'ubicacion' => $ubicacion,
            'url' => $url,
            'qr' => $qr->getDataUri(),
        ];
    }
}
