<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Colaborador;
use App\Models\Marcacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class MatrizMarcacionesService
{
    /**
     * Agrupa las marcaciones reales por colaborador y día. El resultado
     * conserva todas las marcas, incluyendo casos excepcionales repetidos.
     *
     * @param  Collection<int, Colaborador>  $colaboradores
     * @return array<int, array<string, Collection<int, Marcacion>>>
     */
    public function porColaboradorYDia(Collection $colaboradores, Carbon $inicio): array
    {
        if ($colaboradores->isEmpty()) {
            return [];
        }

        $finExclusivo = $inicio->copy()->addMonth();

        return Marcacion::query()
            ->whereIn('colaborador_id', $colaboradores->modelKeys())
            ->where('fecha_hora', '>=', $inicio)
            ->where('fecha_hora', '<', $finExclusivo)
            ->orderBy('fecha_hora')
            ->get(['id', 'colaborador_id', 'tipo', 'fecha_hora'])
            ->groupBy(fn (Marcacion $marcacion): int => $marcacion->colaborador_id)
            ->map(fn (Collection $marcaciones): Collection => $marcaciones->groupBy(
                fn (Marcacion $marcacion): string => $marcacion->fecha_hora->toDateString(),
            ))
            ->all();
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            Marcacion::TIPO_ENTRADA => 'Ingreso',
            Marcacion::TIPO_SALIDA_REFRIGERIO => 'Salida a refrigerio',
            Marcacion::TIPO_REGRESO_REFRIGERIO => 'Regreso de refrigerio',
            Marcacion::TIPO_SALIDA => 'Salida',
            default => $tipo,
        };
    }

    public static function colorTipo(string $tipo): string
    {
        return match ($tipo) {
            Marcacion::TIPO_ENTRADA => 'success',
            Marcacion::TIPO_SALIDA_REFRIGERIO => 'warning',
            Marcacion::TIPO_REGRESO_REFRIGERIO => 'info',
            Marcacion::TIPO_SALIDA => 'danger',
            default => 'gray',
        };
    }
}
