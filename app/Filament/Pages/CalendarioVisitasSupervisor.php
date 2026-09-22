<?php

namespace App\Filament\Pages;

use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CalendarioVisitasSupervisor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Visitas de supervisión';

    protected static ?string $title = 'Visitas de supervisión';

    protected string $view = 'filament.pages.calendario-visitas-supervisor';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:CalendarioVisitasSupervisor') ?? false;
    }

    public ?int $supervisorId = null;

    public ?int $sucursalId = null;

    public string $mes;

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
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

    /** @return Collection<int, User> */
    public function getSupervisoresProperty(): Collection
    {
        return User::query()
            ->role('supervisor')
            ->when($this->supervisorId, fn ($query) => $query->whereKey($this->supervisorId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return Collection<int, Sucursal> */
    public function getSucursalesProperty(): Collection
    {
        return Sucursal::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /** @return array<int, Carbon> */
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

    /**
     * Cada supervisora puede visitar más de un local por día, por eso cada
     * celda contiene una lista y no un único registro.
     *
     * @return array<int, array<string, Collection<int, VisitaSupervisor>>>
     */
    public function getMapaVisitasProperty(): array
    {
        $inicio = Carbon::parse("{$this->mes}-01")->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $mapa = [];

        VisitaSupervisor::query()
            ->with(['sucursal:id,nombre', 'puntoVenta:id,nombre'])
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->when($this->supervisorId, fn ($query) => $query->where('supervisor_id', $this->supervisorId))
            ->when($this->sucursalId, fn ($query) => $query->where('sucursal_id', $this->sucursalId))
            ->orderBy('fecha_hora')
            ->get()
            ->each(function (VisitaSupervisor $visita) use (&$mapa): void {
                $fecha = $visita->fecha->toDateString();
                $mapa[$visita->supervisor_id][$fecha] ??= new Collection();
                $mapa[$visita->supervisor_id][$fecha]->push($visita);
            });

        return $mapa;
    }
}
