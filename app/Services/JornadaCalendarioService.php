<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Support\JornadaMarcacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Construye el modelo de lectura del calendario individual de jornadas.
 *
 * Este servicio no regulariza ni altera marcaciones: únicamente cruza las
 * asignaciones y lecturas existentes para que Filament las pueda dibujar.
 */
final class JornadaCalendarioService
{
    public const HORA_INICIO_ESCALA = 6 * 60;

    public const HORA_FIN_ESCALA = 22 * 60;

    /**
     * @param array<int, Carbon> $dias
     * @return Collection<int, array{
     *     fecha:Carbon,
     *     asignacion:?AsignacionTurno,
     *     marcaciones:Collection<int, Marcacion>,
     *     jornada:?array<string, mixed>,
     *     refrigerio:?array{inicio:float, fin:float, incidencia:bool}
     * }>
     */
    public function construir(Colaborador $colaborador, string $mes, array $dias): Collection
    {
        $inicioMes = Carbon::parse("{$mes}-01")->startOfDay();
        $finMes = $inicioMes->copy()->endOfMonth()->endOfDay();

        $asignaciones = AsignacionTurno::query()
            ->with('turno')
            ->where('colaborador_id', $colaborador->id)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get()
            ->keyBy(fn (AsignacionTurno $asignacion): string => $asignacion->fecha->toDateString());

        // Una jornada nocturna o abierta puede terminar tras medianoche. La
        // ventana se limita luego por cada turno mediante JornadaMarcacion.
        $marcaciones = Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereBetween('fecha_hora', [
                $inicioMes->copy()->subDay(),
                $finMes->copy()->addMinutes(JornadaMarcacion::MAXIMO_JORNADA_MINUTOS),
            ])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get();

        return collect($dias)->map(function (Carbon $fecha) use ($asignaciones, $marcaciones): array {
            /** @var ?AsignacionTurno $asignacion */
            $asignacion = $asignaciones->get($fecha->toDateString());

            if (! $asignacion) {
                $eventosExcepcionales = $marcaciones
                    ->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === null
                        && $marcacion->fecha_hora->isSameDay($fecha))
                    ->values();

                return [
                    'fecha' => $fecha,
                    'asignacion' => null,
                    'marcaciones' => $eventosExcepcionales,
                    'jornada' => $this->rangoJornada($eventosExcepcionales),
                    'refrigerio' => null,
                ];
            }

            $limites = JornadaMarcacion::limites($asignacion);
            $eventos = $marcaciones
                ->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === $asignacion->turno_id
                    && $marcacion->fecha_hora->betweenIncluded($limites['ventana_inicio'], $limites['jornada_fin_maximo']))
                ->values();

            return [
                'fecha' => $fecha,
                'asignacion' => $asignacion,
                'marcaciones' => $eventos,
                'jornada' => $this->rangoJornada($eventos),
                'refrigerio' => $this->rangoRefrigerio($asignacion, $eventos),
            ];
        });
    }

    public static function iniciales(string $nombre): string
    {
        return collect(preg_split('/\s+/', trim($nombre)))
            ->filter()
            ->take(2)
            ->map(fn (string $parte): string => mb_strtoupper(mb_substr($parte, 0, 1)))
            ->implode('');
    }

    /** Posición vertical dentro de la escala, limitada a su rango visible. */
    public static function porcentajeHora(Carbon $hora): float
    {
        $minutos = ($hora->hour * 60) + $hora->minute + ($hora->second / 60);
        $rango = self::HORA_FIN_ESCALA - self::HORA_INICIO_ESCALA;

        return max(0, min(100, (($minutos - self::HORA_INICIO_ESCALA) / $rango) * 100));
    }

    /** @return array<int, int> */
    public static function horasEscala(): array
    {
        return range(6, 22, 2);
    }

    /** @param Collection<int, Marcacion> $marcaciones */
    private function rangoJornada(Collection $marcaciones): ?array
    {
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);

        if (! $entrada) {
            return null;
        }

        $salida = $marcaciones
            ->filter(fn (Marcacion $marcacion): bool => $marcacion->tipo === Marcacion::TIPO_SALIDA)
            ->last();
        $ultimoEvento = $salida ?? $marcaciones->last();

        return [
            'inicio' => self::porcentajeHora($entrada->fecha_hora),
            'fin' => max(self::porcentajeHora($ultimoEvento->fecha_hora), self::porcentajeHora($entrada->fecha_hora) + 0.75),
            'cerrada' => (bool) $salida,
        ];
    }

    /** @param Collection<int, Marcacion> $marcaciones */
    private function rangoRefrigerio(AsignacionTurno $asignacion, Collection $marcaciones): ?array
    {
        if (! $asignacion->turno->incluye_refrigerio) {
            return null;
        }

        $salida = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $regreso = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);

        if (! $salida && ! $regreso) {
            return null;
        }

        $duracion = max(1, (int) $asignacion->turno->refrigerio_minutos);
        $inicio = $salida?->fecha_hora ?? $regreso->fecha_hora->copy()->subMinutes($duracion);
        $fin = $regreso?->fecha_hora ?? $salida->fecha_hora->copy()->addMinutes($duracion);

        return [
            'inicio' => self::porcentajeHora($inicio),
            'fin' => max(self::porcentajeHora($fin), self::porcentajeHora($inicio) + 0.75),
            'incidencia' => ! $salida || ! $regreso,
        ];
    }
}
