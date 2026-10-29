<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Area;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Services\MatrizMarcacionesService;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class MatrizMarcaciones extends Page
{
    use HasPageShield;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Matriz de asistencia';

    protected static ?string $title = 'Matriz de marcaciones';

    protected string $view = 'filament.pages.matriz-marcaciones';

    public string $mes;

    public ?int $empresaId = null;

    public ?int $areaId = null;

    public ?int $sucursalId = null;

    public ?int $puntoVentaId = null;

    public ?int $colaboradorId = null;

    public ?string $cargo = null;

    public string $estadoColaborador = 'todos';

    public string $estadoMarcacion = 'todos';

    public string $busqueda = '';

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('mesAnterior')
                ->label('Mes anterior')
                ->icon(Heroicon::OutlinedChevronLeft)
                ->iconButton()
                ->tooltip('Mes anterior')
                ->action(function (): void {
                    $this->cambiarMes(-1);
                }),
            Action::make('periodo')
                ->label(fn (): string => ucfirst($this->inicioMes()->locale('es')->translatedFormat('F Y')))
                ->disabled(),
            Action::make('mesSiguiente')
                ->label('Mes siguiente')
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconButton()
                ->tooltip('Mes siguiente')
                ->action(function (): void {
                    $this->cambiarMes(1);
                }),
            Action::make('hoy')
                ->label('Mes actual')
                ->color('gray')
                ->action(function (): void {
                    $this->irAlMesActual();
                }),
        ];
    }

    public function cambiarMes(int $desplazamiento): void
    {
        $this->mes = $this->inicioMes()->addMonthsNoOverflow($desplazamiento)->format('Y-m');
        $this->resetPagina();
        $this->dispatch('matriz-marcaciones-ir-a-hoy');
    }

    public function irAlMesActual(): void
    {
        $this->mes = now()->format('Y-m');
        $this->resetPagina();
        $this->dispatch('matriz-marcaciones-ir-a-hoy');
    }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'empresaId',
            'areaId',
            'sucursalId',
            'puntoVentaId',
            'colaboradorId',
            'cargo',
            'busqueda',
        ]);
        $this->estadoColaborador = 'todos';
        $this->estadoMarcacion = 'todos';
        $this->resetPagina();
    }

    public function aplicarFiltros(): void
    {
        $this->resetPagina();
    }

    public function updatedSucursalId(): void
    {
        $this->puntoVentaId = null;
        $this->resetPagina();
    }

    public function updatedMes(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes)) {
            $this->mes = now()->format('Y-m');
        }

        $this->resetPagina();
        $this->dispatch('matriz-marcaciones-ir-a-hoy');
    }

    public function updatedEmpresaId(): void
    {
        $this->resetPagina();
    }

    public function updatedAreaId(): void
    {
        $this->resetPagina();
    }

    public function updatedPuntoVentaId(): void
    {
        $this->resetPagina();
    }

    public function updatedColaboradorId(): void
    {
        $this->resetPagina();
    }

    public function updatedCargo(): void
    {
        $this->resetPagina();
    }

    public function updatedEstadoColaborador(): void
    {
        $this->resetPagina();
    }

    public function updatedEstadoMarcacion(): void
    {
        $this->resetPagina();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPagina();
    }

    /** @return Collection<int, Carbon> */
    public function getDiasProperty(): Collection
    {
        $inicio = $this->inicioMes();
        $fin = $inicio->copy()->endOfMonth();

        return collect(range(0, $inicio->diffInDays($fin)))
            ->map(fn (int $desplazamiento): Carbon => $inicio->copy()->addDays($desplazamiento));
    }

    /** @return Collection<int, Sucursal> */
    public function getSucursalesProperty(): Collection
    {
        return Sucursal::query()
            ->whereIn('id', AlcanceSupervisor::sucursalIdsHistoricos(auth()->user()))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'activo']);
    }

    /** @return Collection<int, PuntoVenta> */
    public function getPuntosVentaProperty(): Collection
    {
        return PuntoVenta::query()
            ->with('sucursal:id,nombre')
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIdsHistoricos(auth()->user()))
            ->when($this->sucursalId, fn (Builder $query): Builder => $query->where('sucursal_id', $this->sucursalId))
            ->orderBy('nombre')
            ->get(['id', 'sucursal_id', 'nombre', 'activo']);
    }

    /** @return Collection<int, Empresa> */
    public function getEmpresasProperty(): Collection
    {
        return Empresa::query()
            ->whereIn('id', $this->colaboradoresBaseQuery()->whereNotNull('empresa_id')->select('empresa_id'))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /** @return Collection<int, Area> */
    public function getAreasProperty(): Collection
    {
        return Area::query()
            ->whereIn('id', $this->colaboradoresBaseQuery()->whereNotNull('area_id')->select('area_id'))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /** @return Collection<int, string> */
    public function getCargosProperty(): Collection
    {
        return $this->colaboradoresBaseQuery()
            ->whereNotNull('cargo')
            ->where('cargo', '!=', '')
            ->distinct()
            ->orderBy('cargo')
            ->pluck('cargo');
    }

    /** @return Collection<int, Colaborador> */
    public function getOpcionesColaboradoresProperty(): Collection
    {
        return $this->colaboradoresFiltradosQuery()
            ->orderBy('nombre_completo')
            ->get(['id', 'nombre_completo', 'activo']);
    }

    public function getFilasPaginadasProperty(): LengthAwarePaginator
    {
        return $this->colaboradoresFiltradosQuery()
            ->with(['empresa:id,nombre', 'area:id,nombre', 'sucursal:id,nombre', 'puntoVenta:id,nombre'])
            ->orderBy('nombre_completo')
            ->paginate(perPage: 10, pageName: 'matrizPage');
    }

    /**
     * @return array<int, array<string, Collection<int, Marcacion>>>
     */
    public function getMarcacionesPorColaboradorYDiaProperty(): array
    {
        /** @var Collection<int, Colaborador> $colaboradores */
        $colaboradores = $this->filasPaginadas->getCollection();

        return app(MatrizMarcacionesService::class)->porColaboradorYDia($colaboradores, $this->inicioMes());
    }

    /** @return array<string, string> */
    public function estadosMarcacion(): array
    {
        return [
            'todos' => 'Todas las jornadas',
            'con_marcaciones' => 'Con alguna marcación',
            'sin_marcaciones' => 'Sin marcaciones',
            'entrada' => 'Con ingreso',
            'sin_entrada' => 'Sin ingreso',
            'salida_refrigerio' => 'Con salida a refrigerio',
            'regreso_refrigerio' => 'Con regreso de refrigerio',
            'salida' => 'Con salida de turno',
        ];
    }

    private function cambiarRango(Builder $query, ?string $tipo = null): Builder
    {
        return $query
            ->where('fecha_hora', '>=', $this->inicioMes())
            ->where('fecha_hora', '<', $this->inicioMes()->copy()->addMonth())
            ->when($tipo, fn (Builder $marcaciones): Builder => $marcaciones->where('tipo', $tipo));
    }

    private function colaboradoresBaseQuery(): Builder
    {
        return Colaborador::query()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIdsHistoricos(auth()->user()));
    }

    private function colaboradoresFiltradosQuery(): Builder
    {
        $query = $this->colaboradoresBaseQuery()
            ->when($this->empresaId, fn (Builder $colaboradores): Builder => $colaboradores->where('empresa_id', $this->empresaId))
            ->when($this->areaId, fn (Builder $colaboradores): Builder => $colaboradores->where('area_id', $this->areaId))
            ->when($this->sucursalId, fn (Builder $colaboradores): Builder => $colaboradores->where('sucursal_id', $this->sucursalId))
            ->when($this->puntoVentaId, fn (Builder $colaboradores): Builder => $colaboradores->where('punto_venta_id', $this->puntoVentaId))
            ->when($this->colaboradorId, fn (Builder $colaboradores): Builder => $colaboradores->whereKey($this->colaboradorId))
            ->when($this->cargo, fn (Builder $colaboradores): Builder => $colaboradores->where('cargo', $this->cargo))
            ->when($this->estadoColaborador === 'activo', fn (Builder $colaboradores): Builder => $colaboradores->where('activo', true))
            ->when($this->estadoColaborador === 'inactivo', fn (Builder $colaboradores): Builder => $colaboradores->where('activo', false))
            ->when(filled($this->busqueda), function (Builder $colaboradores): Builder {
                $termino = '%'.trim($this->busqueda).'%';

                return $colaboradores->where(function (Builder $subconsulta) use ($termino): void {
                    $subconsulta
                        ->where('nombre_completo', 'like', $termino)
                        ->orWhere('documento_identidad', 'like', $termino)
                        ->orWhere('codigo_empresa', 'like', $termino);
                });
            });

        return match ($this->estadoMarcacion) {
            'con_marcaciones' => $query->whereHas('marcaciones', fn (Builder $marcaciones): Builder => $this->cambiarRango($marcaciones)),
            'sin_marcaciones' => $query->whereDoesntHave('marcaciones', fn (Builder $marcaciones): Builder => $this->cambiarRango($marcaciones)),
            'sin_entrada' => $query->whereDoesntHave('marcaciones', fn (Builder $marcaciones): Builder => $this->cambiarRango($marcaciones, Marcacion::TIPO_ENTRADA)),
            Marcacion::TIPO_ENTRADA,
            Marcacion::TIPO_SALIDA_REFRIGERIO,
            Marcacion::TIPO_REGRESO_REFRIGERIO,
            Marcacion::TIPO_SALIDA => $query->whereHas('marcaciones', fn (Builder $marcaciones): Builder => $this->cambiarRango($marcaciones, $this->estadoMarcacion)),
            default => $query,
        };
    }

    private function inicioMes(): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $this->mes, config('app.timezone')) ?: now()->startOfMonth();
    }

    private function resetPagina(): void
    {
        $this->resetPage('matrizPage');
    }
}
