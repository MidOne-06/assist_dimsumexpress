<?php

namespace App\Filament\Widgets;

use App\Models\Marcacion;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class MarcacionesPorHoraChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Marcaciones por hora';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    // Altura nativa de ChartWidget: el Dashboard conserva todo su ancho,
    // pero la gráfica no ocupa más que el espacio operativo necesario.
    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $usuario = auth()->user();

        if (! $usuario instanceof User) {
            return $this->datosVacios();
        }

        $registros = Marcacion::query()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds($usuario))
            ->whereDate('fecha_hora', now()->toDateString())
            ->get(['fecha_hora'])
            ->groupBy(fn (Marcacion $marcacion) => $marcacion->fecha_hora->format('H:00'))
            ->sortKeys();

        if ($registros->isEmpty()) {
            return $this->datosVacios();
        }

        return [
            'datasets' => [[
                'label' => 'Marcaciones',
                'data' => $registros->map(fn (Collection $porHora) => $porHora->count())->values()->all(),
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#d97706',
                'borderWidth' => 1,
                'borderRadius' => 6,
            ]],
            'labels' => $registros->keys()->values()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>} */
    private function datosVacios(): array
    {
        return [
            'datasets' => [[
                'label' => 'Marcaciones',
                'data' => [0],
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#d97706',
                'borderWidth' => 1,
                'borderRadius' => 6,
            ]],
            'labels' => ['Sin registros'],
        ];
    }
}
