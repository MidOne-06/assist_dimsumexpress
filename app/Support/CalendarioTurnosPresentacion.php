<?php

namespace App\Support;

/**
 * Valores de presentación compartidos por las vistas del calendario de turnos.
 *
 * No consulta datos ni cambia el estado de la jornada: mantiene los detalles
 * visuales fuera de la página Livewire para que puedan reutilizarse de forma
 * consistente en el calendario administrativo y el horario del colaborador.
 */
final class CalendarioTurnosPresentacion
{
    /**
     * "Ana Torres Quispe" -> "Ana T." para celdas angostas.
     */
    public static function abreviarNombre(string $nombreCompleto): string
    {
        $partes = preg_split('/\s+/', trim($nombreCompleto));

        if (count($partes) < 2) {
            return $nombreCompleto;
        }

        return "{$partes[0]} " . mb_substr($partes[1], 0, 1) . '.';
    }

    public static function iconoEstado(string $estado): ?string
    {
        return match ($estado) {
            'a_tiempo' => 'heroicon-s-check-circle',
            'tardanza' => 'heroicon-s-exclamation-triangle',
            'falta' => 'heroicon-s-x-circle',
            'turno_distinto' => 'heroicon-s-arrow-path',
            default => null,
        };
    }

    public static function colorEstado(string $estado): string
    {
        return match ($estado) {
            'a_tiempo' => '#16a34a',
            'tardanza' => '#f59e0b',
            'falta' => '#dc2626',
            'turno_distinto' => '#7c3aed',
            default => '#9ca3af',
        };
    }

    /**
     * @return array{bg: string, text: string}
     */
    public static function colorParaTurno(int $turnoId): array
    {
        $paleta = [
            ['bg' => '#22c55e', 'text' => '#ffffff'],
            ['bg' => '#3b82f6', 'text' => '#ffffff'],
            ['bg' => '#f97316', 'text' => '#ffffff'],
            ['bg' => '#a855f7', 'text' => '#ffffff'],
            ['bg' => '#ef4444', 'text' => '#ffffff'],
            ['bg' => '#06b6d4', 'text' => '#ffffff'],
            ['bg' => '#eab308', 'text' => '#1f2937'],
            ['bg' => '#84cc16', 'text' => '#1f2937'],
        ];

        return $paleta[$turnoId % count($paleta)];
    }
}
