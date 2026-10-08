<?php

namespace App\Support;

use App\Models\User;

/**
 * Centraliza las operaciones disponibles para una sesión.
 *
 * Un rol describe la responsabilidad de una persona; el permiso y la ficha
 * vinculada deciden si puede abrir realmente cada operación. De esta manera,
 * un supervisor que además administra nunca termina en una ruta equivocada.
 */
final class PortalAccesos
{
    /**
     * @return array<int, array{clave: string, titulo: string, descripcion: string, ruta: string, icono: string, color: string}>
     */
    public static function disponibles(User $usuario): array
    {
        $usuario->loadMissing('colaborador');

        $accesos = [];

        if ($usuario->colaborador?->activo && $usuario->can('Registrar:Marcacion')) {
            $accesos[] = [
                'clave' => 'asistencia',
                'titulo' => 'Marcar asistencia',
                'descripcion' => 'Escanea el QR de tu estación.',
                'ruta' => route('marcacion.show'),
                'icono' => 'heroicon-o-qr-code',
                'color' => 'success',
            ];
        }

        if ($usuario->hasRole('supervisor') && $usuario->can('Registrar:VisitaSupervisor')) {
            $accesos[] = [
                'clave' => 'visitas',
                'titulo' => 'Registrar visita',
                'descripcion' => 'Escanea el QR del local visitado.',
                'ruta' => route('visita-supervisor.esperando'),
                'icono' => 'heroicon-o-map-pin',
                'color' => 'info',
            ];
        }

        // Un enlace temporal de colaborador solo habilita la operación para
        // la que fue emitido; nunca se ofrece administración desde él.
        $accesoOperativoTemporal = request()->hasSession()
            && request()->session()->get('acceso_operativo_via_enlace', false) === true;

        if (! $accesoOperativoTemporal && $usuario->can('Access:AdminPanel')) {
            $accesos[] = [
                'clave' => 'administracion',
                'titulo' => 'Panel administrativo',
                'descripcion' => 'Gestiona la operación y sus reportes.',
                'ruta' => route('filament.admin.pages.dashboard'),
                'icono' => 'heroicon-o-squares-2x2',
                'color' => 'warning',
            ];
        }

        return $accesos;
    }
}
