<?php

namespace App\Filament\Widgets;

use App\Models\CoberturaOperativa;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenMarcaciones extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $usuario = auth()->user();

        if (! $usuario instanceof User) {
            return [];
        }

        $sucursalIds = AlcanceSupervisor::sucursalIds($usuario);
        $inicio = now()->copy()->startOfDay();
        $fin = now()->copy()->endOfDay();
        $marcaciones = Marcacion::query()
            ->whereIn('sucursal_id', $sucursalIds)
            ->whereBetween('fecha_hora', [$inicio, $fin])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get(['id', 'colaborador_id', 'tipo', 'fecha_hora']);
        $ultimas = $marcaciones->groupBy('colaborador_id')->map->last();
        $enRefrigerio = $ultimas->where('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO)->count();
        $enCurso = $ultimas
            ->filter(fn (Marcacion $marcacion): bool => in_array($marcacion->tipo, [Marcacion::TIPO_ENTRADA, Marcacion::TIPO_REGRESO_REFRIGERIO], true))
            ->count();
        $incidencias = IncidenciaMarcacion::query()
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
        $coberturas = CoberturaOperativa::query()
            ->whereIn('sucursal_id', $sucursalIds)
            ->where('estado', CoberturaOperativa::ESTADO_PENDIENTE)
            ->count();

        return [
            Stat::make('Marcaciones de hoy', $marcaciones->count())
                ->icon(Heroicon::OutlinedFingerPrint)
                ->color('primary'),
            Stat::make('Jornadas en curso', $enCurso)
                ->icon(Heroicon::OutlinedClock)
                ->color('info'),
            Stat::make('En refrigerio', $enRefrigerio)
                ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                ->color($enRefrigerio ? 'warning' : 'gray'),
            Stat::make('Alertas abiertas', $incidencias + $coberturas)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color(($incidencias + $coberturas) ? 'danger' : 'gray'),
        ];
    }
}
