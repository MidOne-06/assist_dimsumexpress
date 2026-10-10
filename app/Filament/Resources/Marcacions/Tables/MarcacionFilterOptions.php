<?php

namespace App\Filament\Resources\Marcacions\Tables;

use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Builder;

/** Opciones y consultas compartidas por los filtros del historial de marcaciones. */
final class MarcacionFilterOptions
{
    public const TURNO_SIN_ASIGNAR = '__sin_turno__';

    /** @param array<int, int> $sucursalIds
     *  @return array<int, string>
     */
    public static function colaboradores(array $sucursalIds): array
    {
        return Colaborador::query()
            ->where(function (Builder $query) use ($sucursalIds): void {
                $query->whereIn('sucursal_id', $sucursalIds)
                    ->orWhereIn('id', Marcacion::query()
                        ->whereIn('sucursal_id', $sucursalIds)
                        ->select('colaborador_id'));
            })
            ->orderBy('nombre_completo')
            ->get(['id', 'nombre_completo', 'activo'])
            ->mapWithKeys(fn (Colaborador $colaborador): array => [
                $colaborador->id => $colaborador->nombre_completo . ($colaborador->activo ? '' : ' · Histórico'),
            ])
            ->all();
    }

    /** @param array<int, int> $sucursalIds
     *  @return array<string, string>
     */
    public static function turnos(array $sucursalIds): array
    {
        $opciones = Turno::query()
            ->whereIn('id', Marcacion::query()
                ->whereIn('sucursal_id', $sucursalIds)
                ->whereNotNull('turno_id')
                ->select('turno_id'))
            ->get()
            ->groupBy(fn (Turno $turno): string => self::normalizarNombre($turno->nombre))
            ->map(function ($versiones): string {
                $referencia = $versiones->firstWhere('activo', true) ?? $versiones->sortByDesc('id')->first();

                return $referencia->nombre . ($versiones->count() > 1 ? ' · Histórico incluido' : '');
            })
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        return [self::TURNO_SIN_ASIGNAR => 'Sin turno / excepción'] + $opciones->all();
    }

    /** @param array<int, int> $sucursalIds
     *  @return array<int, string>
     */
    public static function puntosVenta(array $sucursalIds): array
    {
        return PuntoVenta::query()
            ->with('sucursal')
            ->whereIn('id', Marcacion::query()
                ->whereIn('sucursal_id', $sucursalIds)
                ->whereNotNull('punto_venta_id')
                ->select('punto_venta_id'))
            ->get()
            ->sortBy(fn (PuntoVenta $puntoVenta): string => ($puntoVenta->sucursal?->nombre ?? '') . '|' . $puntoVenta->nombre)
            ->mapWithKeys(fn (PuntoVenta $puntoVenta): array => [
                $puntoVenta->id => ($puntoVenta->sucursal?->nombre ?? 'Local no disponible')
                    . ' · ' . $puntoVenta->nombre
                    . ($puntoVenta->activo ? '' : ' · Histórico'),
            ])
            ->all();
    }

    public static function aplicarTurno(Builder $query, ?string $concepto): Builder
    {
        if (blank($concepto)) {
            return $query;
        }

        if ($concepto === self::TURNO_SIN_ASIGNAR) {
            return $query->whereNull('turno_id');
        }

        return $query->whereHas('turno', fn (Builder $turnos): Builder => $turnos
            ->whereRaw('lower(trim(nombre)) = ?', [self::normalizarNombre($concepto)]));
    }

    private static function normalizarNombre(?string $nombre): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $nombre)));
    }
}
