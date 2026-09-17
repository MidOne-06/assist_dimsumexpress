<?php

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\Turno;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CalendarioTurnos extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Personal';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Calendario de turnos';

    protected static ?string $title = 'Calendario de turnos';

    protected string $view = 'filament.pages.calendario-turnos';

    public ?int $sucursalId = null;

    public string $mes;

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
        $this->sucursalId = Sucursal::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->value('id');
    }

    public function mesAnterior(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->subMonthNoOverflow()->format('Y-m');
    }

    public function mesSiguiente(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->addMonthNoOverflow()->format('Y-m');
    }

    public function irAHoy(): void
    {
        $this->mes = now()->format('Y-m');
    }

    public function getSucursalesProperty(): Collection
    {
        return Sucursal::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return array<int, Carbon>
     */
    public function getDiasProperty(): array
    {
        $inicio = Carbon::parse("{$this->mes}-01");
        $fin = $inicio->copy()->endOfMonth();

        $dias = [];

        for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay()) {
            $dias[] = $dia->copy();
        }

        return $dias;
    }

    public function getColaboradoresProperty(): Collection
    {
        if (! $this->sucursalId) {
            return new Collection();
        }

        return Colaborador::query()
            ->where('sucursal_id', $this->sucursalId)
            ->where('activo', true)
            ->orderBy('nombre_completo')
            ->get();
    }

    /**
     * @return array<int, array<string, AsignacionTurno>>
     */
    public function getMapaAsignacionesProperty(): array
    {
        $colaboradores = $this->colaboradores;

        if ($colaboradores->isEmpty()) {
            return [];
        }

        $inicio = Carbon::parse("{$this->mes}-01")->toDateString();
        $fin = Carbon::parse("{$this->mes}-01")->endOfMonth()->toDateString();

        $mapa = [];

        AsignacionTurno::query()
            ->whereIn('colaborador_id', $colaboradores->pluck('id'))
            ->whereBetween('fecha', [$inicio, $fin])
            ->with('turno')
            ->get()
            ->each(function (AsignacionTurno $asignacion) use (&$mapa) {
                $mapa[$asignacion->colaborador_id][$asignacion->fecha->toDateString()] = $asignacion;
            });

        return $mapa;
    }

    public function getTurnosActivosProperty(): Collection
    {
        return Turno::query()->where('activo', true)->orderBy('hora_inicio')->get();
    }

    /**
     * Color determinístico por turno para que el mismo turno siempre pinte
     * igual en toda la grilla, sin necesitar que el usuario elija un color.
     *
     * Se usan valores hexadecimales reales (no clases Tailwind) porque el
     * CSS que Filament v5 distribuye es un build propio (Tailwind v4) que
     * solo contiene las clases que sus propios componentes usan -- clases
     * utilitarias arbitrarias como "bg-emerald-100" no existen en ese
     * bundle y no pintarían nada en el navegador.
     *
     * @return array{bg: string, text: string}
     */
    public static function colorParaTurno(int $turnoId): array
    {
        $paleta = [
            ['bg' => '#22c55e', 'text' => '#ffffff'], // verde
            ['bg' => '#3b82f6', 'text' => '#ffffff'], // azul
            ['bg' => '#f97316', 'text' => '#ffffff'], // naranja
            ['bg' => '#a855f7', 'text' => '#ffffff'], // púrpura
            ['bg' => '#ef4444', 'text' => '#ffffff'], // rojo
            ['bg' => '#06b6d4', 'text' => '#ffffff'], // cian
            ['bg' => '#eab308', 'text' => '#1f2937'], // amarillo (texto oscuro por contraste)
            ['bg' => '#84cc16', 'text' => '#1f2937'], // lima (texto oscuro por contraste)
        ];

        return $paleta[$turnoId % count($paleta)];
    }
}
