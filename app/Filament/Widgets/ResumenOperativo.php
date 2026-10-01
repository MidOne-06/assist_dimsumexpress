<?php

namespace App\Filament\Widgets;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenOperativo extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $usuario = auth()->user();

        if (! $usuario instanceof User) {
            return [];
        }

        $sucursalIds = AlcanceSupervisor::sucursalIds($usuario);
        $hoy = now()->toDateString();
        $inicioHoy = now()->startOfDay();
        $finHoy = now()->endOfDay();

        $colaboradoresActivos = Colaborador::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', $sucursalIds)
            ->count();

        $turnosHoy = AsignacionTurno::query()
            ->whereDate('fecha', $hoy)
            ->whereHas('colaborador', fn ($query) => $query->whereIn('sucursal_id', $sucursalIds)->where('activo', true))
            ->count();

        $marcacionesHoy = Marcacion::query()
            ->whereIn('sucursal_id', $sucursalIds)
            ->whereBetween('fecha_hora', [$inicioHoy, $finHoy])
            ->count();

        $incidenciasPendientes = IncidenciaMarcacion::query()
            ->whereNull('resuelta_en')
            ->where(function ($query) use ($sucursalIds): void {
                $query
                    ->whereIn('sucursal_id', $sucursalIds)
                    ->orWhere(function ($legacy) use ($sucursalIds): void {
                        $legacy
                            ->whereNull('sucursal_id')
                            ->whereHas('colaborador', fn ($colaborador) => $colaborador->whereIn('sucursal_id', $sucursalIds));
                    });
            })
            ->count();

        return [
            Stat::make('Colaboradores activos', $colaboradoresActivos)
                ->icon(Heroicon::OutlinedUsers)
                ->color('primary'),
            Stat::make('Turnos de hoy', $turnosHoy)
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('info'),
            Stat::make('Marcaciones hoy', $marcacionesHoy)
                ->icon(Heroicon::OutlinedFingerPrint)
                ->color('success'),
            Stat::make('Incidencias pendientes', $incidenciasPendientes)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($incidenciasPendientes ? 'danger' : 'gray'),
        ];
    }
}
