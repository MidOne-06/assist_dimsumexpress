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
     * @return array<int, array<string, Turno>>
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
                $mapa[$asignacion->colaborador_id][$asignacion->fecha->toDateString()] = $asignacion->turno;
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
     * @return array{bg: string, text: string}
     */
    public static function colorParaTurno(int $turnoId): array
    {
        $paleta = [
            ['bg' => 'bg-blue-100 dark:bg-blue-500/20', 'text' => 'text-blue-800 dark:text-blue-300'],
            ['bg' => 'bg-emerald-100 dark:bg-emerald-500/20', 'text' => 'text-emerald-800 dark:text-emerald-300'],
            ['bg' => 'bg-amber-100 dark:bg-amber-500/20', 'text' => 'text-amber-800 dark:text-amber-300'],
            ['bg' => 'bg-purple-100 dark:bg-purple-500/20', 'text' => 'text-purple-800 dark:text-purple-300'],
            ['bg' => 'bg-rose-100 dark:bg-rose-500/20', 'text' => 'text-rose-800 dark:text-rose-300'],
            ['bg' => 'bg-cyan-100 dark:bg-cyan-500/20', 'text' => 'text-cyan-800 dark:text-cyan-300'],
            ['bg' => 'bg-orange-100 dark:bg-orange-500/20', 'text' => 'text-orange-800 dark:text-orange-300'],
            ['bg' => 'bg-lime-100 dark:bg-lime-500/20', 'text' => 'text-lime-800 dark:text-lime-300'],
        ];

        return $paleta[$turnoId % count($paleta)];
    }
}
