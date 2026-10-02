<?php

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Services\ControlJornadaService;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

class ControlJornadas extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Control de jornadas';

    protected static ?string $title = 'Control de jornadas';

    protected string $view = 'filament.pages.control-jornadas';

    public ?int $sucursalId = null;

    public string $mes;

    /** @var Collection<int, AsignacionTurno>|null */
    private ?Collection $asignacionesCache = null;

    /** @var array<int, array<string, array<string, array{aplica: bool, estado: string, hora: ?string, etiqueta: string}>>>|null */
    private ?array $segmentosCache = null;

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
    }

    public function mesAnterior(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->subMonthNoOverflow()->format('Y-m');
        $this->limpiarCache();
    }

    public function mesSiguiente(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->addMonthNoOverflow()->format('Y-m');
        $this->limpiarCache();
    }

    public function irAHoy(): void
    {
        $this->mes = now()->format('Y-m');
        $this->limpiarCache();
        $this->dispatch('control-jornadas-ir-a-hoy');
    }

    public function updatedSucursalId(): void
    {
        $this->limpiarCache();
    }

    /** @return Collection<int, Sucursal> */
    public function getSucursalesProperty(): Collection
    {
        return AlcanceSupervisor::sucursalesQuery(auth()->user())->get();
    }

    /** @return array<int, Carbon> */
    public function getDiasProperty(): array
    {
        $inicio = Carbon::parse("{$this->mes}-01");
        $dias = [];

        for ($dia = $inicio->copy(); $dia->lte($inicio->copy()->endOfMonth()); $dia->addDay()) {
            $dias[] = $dia->copy();
        }

        return $dias;
    }

    /** @return SupportCollection<int, Colaborador> */
    public function getColaboradoresProperty(): SupportCollection
    {
        return $this->asignaciones
            ->map(fn (AsignacionTurno $asignacion): Colaborador => $asignacion->colaborador)
            ->unique('id')
            ->sortBy(fn (Colaborador $colaborador): string => $colaborador->sucursal->nombre.'|'.$colaborador->nombre_completo)
            ->values();
    }

    /** @return array<int, array<string, AsignacionTurno>> */
    public function getAsignacionesPorColaboradorProperty(): array
    {
        $mapa = [];

        foreach ($this->asignaciones as $asignacion) {
            $mapa[$asignacion->colaborador_id][$asignacion->fecha->toDateString()] = $asignacion;
        }

        return $mapa;
    }

    /** @return array<int, array<string, array<string, array{aplica: bool, estado: string, hora: ?string, etiqueta: string}>>> */
    public function getSegmentosPorAsignacionProperty(): array
    {
        if ($this->segmentosCache !== null) {
            return $this->segmentosCache;
        }

        $marcacionesPorJornada = $this->marcacionesDelMes
            ->groupBy(fn (Marcacion $marcacion): string => $marcacion->colaborador_id.':'.$marcacion->turno_id);
        $servicio = app(ControlJornadaService::class);

        $this->segmentosCache = [];
        foreach ($this->asignaciones as $asignacion) {
            $clave = $asignacion->colaborador_id.':'.$asignacion->turno_id;
            $this->segmentosCache[$asignacion->id] = $servicio->segmentos(
                $asignacion,
                $marcacionesPorJornada->get($clave, collect()),
            );
        }

        return $this->segmentosCache;
    }

    /** @return Collection<int, AsignacionTurno> */
    public function getAsignacionesProperty(): Collection
    {
        if ($this->asignacionesCache !== null) {
            return $this->asignacionesCache;
        }

        $this->asignacionesCache = AsignacionTurno::query()
            ->with(['colaborador.sucursal', 'turno'])
            ->whereBetween('fecha', $this->limitesDelMes())
            ->whereHas('colaborador', fn (Builder $query): Builder => $query
                ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
                ->when($this->sucursalId, fn (Builder $subquery): Builder => $subquery->where('sucursal_id', $this->sucursalId)))
            ->orderBy('fecha')
            ->get();

        return $this->asignacionesCache;
    }

    /** @return Collection<int, Marcacion> */
    public function getMarcacionesDelMesProperty(): Collection
    {
        $colaboradorIds = $this->asignaciones->pluck('colaborador_id')->unique()->values();
        if ($colaboradorIds->isEmpty()) {
            return new Collection();
        }

        [$inicio, $fin] = $this->limitesDelMes();

        return Marcacion::query()
            ->whereIn('colaborador_id', $colaboradorIds)
            ->whereBetween('fecha_hora', [
                Carbon::parse($inicio)->subDay()->startOfDay(),
                Carbon::parse($fin)->addDay()->endOfDay(),
            ])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get();
    }

    /** @return array{0: string, 1: string} */
    private function limitesDelMes(): array
    {
        $inicio = Carbon::parse("{$this->mes}-01");

        return [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()];
    }

    private function limpiarCache(): void
    {
        $this->asignacionesCache = null;
        $this->segmentosCache = null;
    }
}
