<?php

declare(strict_types=1);

namespace App\Services;

use App\Filament\Pages\ControlVisitasSupervisor;
use App\Filament\Resources\Colaboradors\ColaboradorResource;
use App\Filament\Resources\IncidenciaMarcacions\IncidenciaMarcacionResource;
use App\Filament\Resources\Marcacions\MarcacionResource;
use App\Filament\Resources\TurnoOperativos\TurnoOperativoResource;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\TurnoOperativo;
use App\Models\User;
use App\Models\VisitaSupervisor;
use App\Support\AlcanceSupervisor;
use Illuminate\Support\Collection;

final class AuditoriaOperativaService
{
    /** @return Collection<int, array{__key: string, nivel: string, hallazgo: string, detalle: string, local: ?string, url: string}> */
    public function hallazgos(User $usuario): Collection
    {
        $sucursalIds = AlcanceSupervisor::sucursalIds($usuario);
        $hallazgos = collect();

        $mapeos = TurnoOperativo::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', $sucursalIds)
            ->whereHas('turno', fn ($query) => $query->where('activo', true))
            ->get(['id', 'sucursal_id', 'punto_venta_id'])
            ->groupBy('sucursal_id');

        TurnoOperativo::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', $sucursalIds)
            ->whereDoesntHave('turno', fn ($query) => $query->where('activo', true))
            ->with(['sucursal:id,nombre', 'puntoVenta:id,nombre', 'turno:id,nombre'])
            ->orderBy('sucursal_id')
            ->get()
            ->each(function (TurnoOperativo $regla) use ($hallazgos): void {
                $hallazgos->push([
                    '__key' => "turno-inactivo-{$regla->id}",
                    'nivel' => 'Crítico',
                    'hallazgo' => 'Regla activa con turno archivado',
                    'detalle' => trim(implode(' · ', array_filter([
                        $regla->turno?->nombre,
                        $regla->puntoVenta?->nombre,
                    ]))),
                    'local' => $regla->sucursal?->nombre,
                    'url' => TurnoOperativoResource::getUrl('index'),
                ]);
            });

        PuntoVenta::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', $sucursalIds)
            ->with('sucursal:id,nombre')
            ->orderBy('sucursal_id')
            ->get()
            ->each(function (PuntoVenta $puntoVenta) use ($mapeos, $hallazgos): void {
                $mapeosLocal = $mapeos->get($puntoVenta->sucursal_id, collect());
                $tieneRegla = $mapeosLocal
                    ->where('punto_venta_id', $puntoVenta->id)
                    ->isNotEmpty()
                    || $mapeosLocal->whereNull('punto_venta_id')->isNotEmpty();

                if ($tieneRegla) {
                    return;
                }

                $hallazgos->push([
                    '__key' => "estacion-sin-turno-{$puntoVenta->id}",
                    'nivel' => 'Crítico',
                    'hallazgo' => 'Estación sin turno operativo',
                    'detalle' => $puntoVenta->nombre,
                    'local' => $puntoVenta->sucursal?->nombre,
                    'url' => TurnoOperativoResource::getUrl('index'),
                ]);
            });

        Colaborador::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', $sucursalIds)
            ->with(['sucursal:id,nombre', 'puntoVenta:id,nombre', 'user:id,activo'])
            ->orderBy('nombre_completo')
            ->get()
            ->each(function (Colaborador $colaborador) use ($mapeos, $hallazgos): void {
                $mapeosLocal = $mapeos->get($colaborador->sucursal_id, collect());
                $mapeosEstacion = $colaborador->punto_venta_id
                    ? $mapeosLocal->where('punto_venta_id', $colaborador->punto_venta_id)
                    : collect();
                $mapeosAplicables = $mapeosEstacion->isNotEmpty()
                    ? $mapeosEstacion
                    : $mapeosLocal->whereNull('punto_venta_id');

                if ($mapeosAplicables->isEmpty()) {
                    $local = $colaborador->sucursal?->nombre;
                    $estacion = $colaborador->puntoVenta?->nombre;
                    $hallazgos->push([
                        '__key' => "turno-{$colaborador->id}",
                        'nivel' => 'Crítico',
                        'hallazgo' => 'Sin turno operativo aplicable',
                        'detalle' => trim("{$colaborador->nombre_completo}" . ($estacion ? " · {$estacion}" : '')),
                        'local' => $local,
                        'url' => TurnoOperativoResource::getUrl('index'),
                    ]);
                }

                if (! $colaborador->user?->activo) {
                    $hallazgos->push([
                        '__key' => "acceso-{$colaborador->id}",
                        'nivel' => 'Atención',
                        'hallazgo' => 'Cuenta de acceso inactiva',
                        'detalle' => $colaborador->nombre_completo,
                        'local' => $colaborador->sucursal?->nombre,
                        'url' => ColaboradorResource::getUrl('index', ['search' => $colaborador->nombre_completo]),
                    ]);
                }

                if ($colaborador->user?->activo && ! $colaborador->user->can('Registrar:Marcacion')) {
                    $hallazgos->push([
                        '__key' => "permiso-marcacion-{$colaborador->id}",
                        'nivel' => 'Crítico',
                        'hallazgo' => 'Cuenta sin permiso para marcar',
                        'detalle' => $colaborador->nombre_completo,
                        'local' => $colaborador->sucursal?->nombre,
                        'url' => ColaboradorResource::getUrl('index', ['search' => $colaborador->nombre_completo]),
                    ]);
                }
            });

        Marcacion::query()
            ->whereNull('turno_id')
            ->whereIn('sucursal_id', $sucursalIds)
            ->where('fecha_hora', '<=', now())
            ->with(['colaborador:id,nombre_completo', 'sucursal:id,nombre'])
            ->orderBy('fecha_hora')
            ->get()
            ->groupBy(fn (Marcacion $marcacion): string => $marcacion->colaborador_id . ':' . $marcacion->fecha_hora->toDateString())
            ->each(function (Collection $marcaciones, string $clave) use ($hallazgos): void {
                $primera = $marcaciones->first();

                if (! $primera) {
                    return;
                }

                $hallazgos->push([
                    '__key' => "marcacion-sin-turno-{$clave}",
                    'nivel' => 'Atención',
                    'hallazgo' => 'Marcaciones pendientes de regularizar',
                    'detalle' => trim(implode(' · ', array_filter([
                        $primera->colaborador?->nombre_completo,
                        $primera->fecha_hora->format('d/m/Y'),
                        $marcaciones->count() . ' lectura(s)',
                    ]))),
                    'local' => $primera->sucursal?->nombre,
                    'url' => MarcacionResource::getUrl('index'),
                ]);
            });

        IncidenciaMarcacion::query()
            ->whereNull('resuelta_en')
            ->whereIn('sucursal_id', $sucursalIds)
            ->with(['colaborador:id,nombre_completo', 'sucursal:id,nombre'])
            ->latest('detectada_en')
            ->get()
            ->each(function (IncidenciaMarcacion $incidencia) use ($hallazgos): void {
                $hallazgos->push([
                    '__key' => "incidencia-{$incidencia->id}",
                    'nivel' => 'Atención',
                    'hallazgo' => 'Incidencia de marcación pendiente',
                    'detalle' => trim(implode(' · ', array_filter([
                        $incidencia->colaborador?->nombre_completo,
                        IncidenciaMarcacion::etiquetaTipo($incidencia->tipo),
                    ]))),
                    'local' => $incidencia->sucursal?->nombre,
                    'url' => IncidenciaMarcacionResource::getUrl('index'),
                ]);
            });

        if ($usuario->can('Regularizar:VisitaSupervisor')) {
            VisitaSupervisor::query()
                ->where('estado', VisitaSupervisor::EN_CURSO)
                ->whereDate('fecha', '<', today())
                ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIdsHistoricos($usuario))
                ->with(['supervisor:id,name', 'sucursal:id,nombre,activo'])
                ->orderBy('fecha')
                ->get()
                ->each(function (VisitaSupervisor $visita) use ($hallazgos): void {
                    $hallazgos->push([
                        '__key' => "visita-supervisor-pendiente-{$visita->id}",
                        'nivel' => 'Atención',
                        'hallazgo' => 'Visita de supervisión sin salida',
                        'detalle' => trim(implode(' · ', array_filter([
                            $visita->supervisor?->name,
                            $visita->ingreso_en?->format('d/m/Y H:i'),
                        ]))),
                        'local' => ($visita->sucursal?->nombre ?? '—') . ($visita->sucursal?->activo ? '' : ' (inactivo)'),
                        'url' => ControlVisitasSupervisor::getUrl(),
                    ]);
                });
        }

        return $hallazgos;
    }
}
