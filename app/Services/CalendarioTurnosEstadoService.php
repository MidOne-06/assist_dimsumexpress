<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Marcacion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Evalúa la asistencia visible en el calendario de turnos.
 *
 * No crea ni modifica asignaciones o marcaciones: prepara el estado de
 * lectura que consume la grilla administrativa.
 */
final class CalendarioTurnosEstadoService
{
    /**
     * @param EloquentCollection<int, \App\Models\Colaborador> $colaboradores
     * @return array<int, array<string, array<int, Marcacion>>>
     */
    public function entradas(EloquentCollection $colaboradores, string $mes): array
    {
        if ($colaboradores->isEmpty()) {
            return [];
        }

        $inicio = Carbon::parse("{$mes}-01")->toDateString();
        $fin = Carbon::parse("{$mes}-01")->endOfMonth()->toDateString();
        $mapa = [];

        Marcacion::query()
            ->whereIn('colaborador_id', $colaboradores->pluck('id'))
            ->where('tipo', Marcacion::TIPO_ENTRADA)
            ->whereBetween('fecha_hora', ["{$inicio} 00:00:00", "{$fin} 23:59:59"])
            ->orderBy('fecha_hora')
            ->get()
            ->each(function (Marcacion $marcacion) use (&$mapa): void {
                $fecha = $marcacion->fecha_hora->toDateString();
                $turnoId = $marcacion->turno_id ?? 0;
                $mapa[$marcacion->colaborador_id][$fecha][$turnoId] ??= $marcacion;
            });

        return $mapa;
    }

    /**
     * @param array<int, array<string, array<int, Marcacion>>> $entradas
     * @return array{estado: string, label: string, hora: ?string}
     */
    public function estado(AsignacionTurno $asignacion, array $entradas): array
    {
        $entradasDelDia = $entradas[$asignacion->colaborador_id][$asignacion->fecha->toDateString()] ?? [];
        $entrada = $entradasDelDia[$asignacion->turno_id] ?? null;
        $turno = $asignacion->turno;
        $limite = Carbon::parse($asignacion->fecha->toDateString() . ' ' . $turno->hora_inicio)
            ->addMinutes($turno->tolerancia_entrada_minutos);

        if ($entrada) {
            if ($entrada->fecha_hora->lte($limite)) {
                return ['estado' => 'a_tiempo', 'label' => 'A tiempo', 'hora' => $entrada->fecha_hora->format('H:i:s')];
            }

            $minutosTarde = (int) ceil($limite->diffInSeconds($entrada->fecha_hora) / 60);

            return ['estado' => 'tardanza', 'label' => "Tardanza de {$minutosTarde} min", 'hora' => $entrada->fecha_hora->format('H:i:s')];
        }

        if ($entradasDelDia !== []) {
            $entradaOtroTurno = reset($entradasDelDia);

            return ['estado' => 'turno_distinto', 'label' => 'Marcó otro turno', 'hora' => $entradaOtroTurno->fecha_hora->format('H:i:s')];
        }

        $aunNoVence = $asignacion->fecha->isFuture() || ($asignacion->fecha->isToday() && now()->lt($limite));

        return $aunNoVence
            ? ['estado' => 'pendiente', 'label' => 'Pendiente', 'hora' => null]
            : ['estado' => 'falta', 'label' => 'Falta (sin marcar entrada)', 'hora' => null];
    }

    /**
     * @param Collection<int, AsignacionTurno> $asignaciones
     * @param array<int, array<string, array<int, Marcacion>>> $entradas
     */
    public function tooltip(Collection $asignaciones, array $entradas): HtmlString
    {
        return new HtmlString($asignaciones->map(function (AsignacionTurno $asignacion) use ($entradas): string {
            $estado = $this->estado($asignacion, $entradas);
            $nombre = e($asignacion->colaborador->nombre_completo);
            $detalle = $estado['hora'] ? "{$estado['label']} ({$estado['hora']})" : $estado['label'];

            return "{$nombre} — " . e($detalle);
        })->join('<br>'));
    }

    /**
     * @param Collection<int, AsignacionTurno> $asignaciones
     * @param array<int, array<string, array<int, Marcacion>>> $entradas
     */
    public function peorEstado(Collection $asignaciones, array $entradas): string
    {
        $prioridad = ['falta' => 4, 'turno_distinto' => 3, 'tardanza' => 2, 'pendiente' => 1, 'a_tiempo' => 0];

        return $asignaciones
            ->map(fn (AsignacionTurno $asignacion): string => $this->estado($asignacion, $entradas)['estado'])
            ->sortByDesc(fn (string $estado): int => $prioridad[$estado] ?? 0)
            ->first() ?? 'pendiente';
    }
}
