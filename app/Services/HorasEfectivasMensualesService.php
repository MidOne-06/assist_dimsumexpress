<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\ResumenJornada;
use App\Support\JornadaMarcacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agrega las jornadas de un período para la tabla de horas efectivas.
 *
 * Solo lee resúmenes, asignaciones y marcaciones; los filtros, la interfaz y
 * las acciones administrativas permanecen en la página Filament.
 */
final class HorasEfectivasMensualesService
{
    /** @return Collection<int, array<string, mixed>> */
    public function resumenes(Carbon $inicio, Builder $colaboradoresPermitidos, ?int $colaboradorId): Collection
    {
        $fin = $inicio->copy()->endOfMonth();
        $asignaciones = AsignacionTurno::query()
            ->with(['colaborador.sucursal', 'colaborador.empresa', 'colaborador.area', 'turno', 'resumenJornada'])
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn('colaborador_id', $colaboradoresPermitidos->select('id'))
            ->when($colaboradorId, fn (Builder $query) => $query->where('colaborador_id', $colaboradorId))
            ->orderBy('fecha')
            ->get();

        if ($asignaciones->isEmpty()) {
            return collect();
        }

        $marcacionesPorColaborador = Marcacion::query()
            ->with(['sucursal', 'puntoVenta'])
            ->whereIn('colaborador_id', $asignaciones->pluck('colaborador_id')->unique())
            ->whereIn('turno_id', $asignaciones->pluck('turno_id')->unique())
            ->whereBetween('fecha_hora', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()->addMinutes(JornadaMarcacion::MAXIMO_JORNADA_MINUTOS)])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get()
            ->groupBy('colaborador_id');
        $resumenes = collect();

        foreach ($asignaciones as $asignacion) {
            $limites = JornadaMarcacion::limites($asignacion);
            $marcaciones = ($marcacionesPorColaborador->get($asignacion->colaborador_id) ?? collect())
                ->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === $asignacion->turno_id && $marcacion->fecha_hora->betweenIncluded($limites['ventana_inicio'], $limites['jornada_fin_maximo']))
                ->values();
            $tieneEntrada = $marcaciones->contains('tipo', Marcacion::TIPO_ENTRADA);

            if (! $tieneEntrada) {
                continue;
            }

            $jornada = $asignacion->resumenJornada
                ? $this->jornadaConsolidada($asignacion->resumenJornada)
                : JornadaMarcacion::resumen($asignacion->colaborador, $asignacion, $marcaciones);
            $id = $asignacion->colaborador_id;
            $fila = $resumenes->get($id, $this->filaVacia($asignacion->colaborador));
            $detalle = $this->detalleJornada($asignacion, $marcaciones, $jornada);

            if ($jornada['estado'] === 'en_curso') {
                $fila['jornadas_abiertas']++;
            } elseif ($jornada['estado'] === 'inconsistente') {
                $fila['jornadas_inconsistentes']++;
            } else {
                $fila['jornadas_cerradas']++;
                $fila['efectivos_segundos'] += $jornada['efectivos_segundos'];
                $fila['objetivo_segundos'] += $jornada['objetivo_segundos'];
                $fila['diferencia_segundos'] += $jornada['diferencia_segundos'];
                $fila['extras_segundos'] += $jornada['extras_segundos'];
                $fila['efectivos_minutos'] += $jornada['efectivos_minutos'];
                $fila['objetivo_minutos'] += $jornada['objetivo_minutos'];
                $fila['diferencia_minutos'] += $jornada['diferencia_minutos'];
                $fila['extras_minutos'] += $jornada['extras_minutos'];
            }

            $fila['jornadas'][] = $detalle;
            $resumenes->put($id, $fila);
        }

        return $resumenes->values();
    }

    /** @return array{estado:string, efectivos_segundos:int, objetivo_segundos:int, extras_segundos:int, diferencia_segundos:int, refrigerio_segundos:int, efectivos_minutos:int, objetivo_minutos:int, extras_minutos:int, diferencia_minutos:int, inconsistencias:array<int, string>} */
    private function jornadaConsolidada(ResumenJornada $resumen): array
    {
        return [
            'estado' => $resumen->estado,
            'efectivos_segundos' => $resumen->efectivos_segundos,
            'objetivo_segundos' => $resumen->objetivo_segundos,
            'extras_segundos' => $resumen->extras_segundos,
            'diferencia_segundos' => $resumen->diferencia_segundos,
            'refrigerio_segundos' => $resumen->refrigerio_segundos,
            'efectivos_minutos' => intdiv($resumen->efectivos_segundos, 60),
            'objetivo_minutos' => intdiv($resumen->objetivo_segundos, 60),
            'extras_minutos' => intdiv($resumen->extras_segundos, 60),
            'diferencia_minutos' => ($resumen->diferencia_segundos < 0 ? -1 : 1) * intdiv(abs($resumen->diferencia_segundos), 60),
            'inconsistencias' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function filaVacia(Colaborador $colaborador): array
    {
        return ['__key' => 'colaborador-' . $colaborador->id, 'colaborador' => $colaborador, 'jornadas_cerradas' => 0, 'jornadas_abiertas' => 0, 'jornadas_inconsistentes' => 0, 'efectivos_segundos' => 0, 'objetivo_segundos' => 0, 'diferencia_segundos' => 0, 'extras_segundos' => 0, 'efectivos_minutos' => 0, 'objetivo_minutos' => 0, 'diferencia_minutos' => 0, 'extras_minutos' => 0, 'jornadas' => []];
    }

    /**
     * @param Collection<int, Marcacion> $marcaciones
     * @param array{estado:string, efectivos_segundos:?int, objetivo_segundos:int, extras_segundos:?int, diferencia_segundos:?int, inconsistencias:array<int, string>} $jornada
     * @return array<string, string>
     */
    private function detalleJornada(AsignacionTurno $asignacion, Collection $marcaciones, array $jornada): array
    {
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salida = $marcaciones->filter(fn (Marcacion $marcacion): bool => $marcacion->tipo === Marcacion::TIPO_SALIDA)->last();
        $local = $entrada?->sucursal?->nombre ?? $asignacion->colaborador->sucursal?->nombre ?? '—';
        $puntoVenta = $entrada?->puntoVenta?->nombre;

        return [
            'fecha' => $asignacion->fecha->format('d/m/Y'), 'turno' => $asignacion->turno->nombre,
            'entrada' => $entrada?->fecha_hora?->format('H:i:s') ?? '—', 'salida' => $salida?->fecha_hora?->format('H:i:s') ?? '—',
            'local' => $puntoVenta ? "{$local} · {$puntoVenta}" : $local,
            'efectivas' => $jornada['efectivos_segundos'] === null ? '—' : self::formatoSegundos($jornada['efectivos_segundos']),
            'objetivo' => self::formatoSegundos($jornada['objetivo_segundos']),
            'diferencia' => $jornada['diferencia_segundos'] === null ? '—' : self::formatoSegundos($jornada['diferencia_segundos']),
            'estado' => match ($jornada['estado']) { 'en_curso' => 'En curso', 'inconsistente' => 'Observada', 'pendiente' => 'Parcial', default => 'Cumplida' },
        ];
    }

    public static function formatoSegundos(int $segundos): string
    {
        $absoluto = abs($segundos);
        $horas = intdiv($absoluto, 3600);
        $minutos = intdiv($absoluto % 3600, 60);
        $restantes = $absoluto % 60;
        $valor = "{$horas} h {$minutos} min" . ($restantes ? " {$restantes} s" : '');

        return $segundos < 0 ? "−{$valor}" : $valor;
    }
}
