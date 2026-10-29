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
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class MatrizMarcaciones extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Matriz de asistencia';

    protected static ?string $title = 'Matriz de marcaciones';

    protected string $view = 'filament.pages.matriz-marcaciones';

    #[Url]
    public ?string $mes = null;

    public function mount(): void
    {
        if (! is_string($this->mes) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes)) {
            $this->mes = now()->format('Y-m');
        }
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('mesAnterior')->label('Mes anterior')->icon(Heroicon::OutlinedChevronLeft)->iconButton()->tooltip('Mes anterior')->action(function (): void {
                $this->cambiarMes(-1);
            }),
            Action::make('periodo')->label(fn (): string => ucfirst($this->inicioMes()->locale('es')->translatedFormat('F Y')))->disabled(),
            Action::make('mesSiguiente')->label('Mes siguiente')->icon(Heroicon::OutlinedChevronRight)->iconButton()->tooltip('Mes siguiente')->action(function (): void {
                $this->cambiarMes(1);
            }),
            Action::make('hoy')->label('Mes actual')->color('gray')->action(function (): void {
                $this->irAlMesActual();
            }),
        ];
    }

    public function cambiarMes(int $desplazamiento): void
    {
        $this->redirigirAlMes($this->inicioMes()->addMonthsNoOverflow($desplazamiento)->format('Y-m'));
    }

    public function irAlMesActual(): void
    {
        $this->redirigirAlMes(now()->format('Y-m'));
    }

    public function applyTableFilters(): void
    {
        $this->tableFilters = $this->tableDeferredFilters;
        $this->handleTableFilterUpdates();
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, ?string $search, int|string $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => $this->registrosPaginados($filters, $search, (int) $page, $recordsPerPage, $sortColumn, $sortDirection))
            ->columns($this->columnasTabla())
            ->filters([
                SelectFilter::make('empresa_id')->label('Empresa')->options(fn (): array => $this->empresasPermitidas()->pluck('nombre', 'id')->all())->searchable(),
                SelectFilter::make('area_id')->label('Área')->options(fn (): array => $this->areasPermitidas()->pluck('nombre', 'id')->all())->searchable(),
                SelectFilter::make('sucursal_id')->label('Local base')->options(fn (): array => $this->sucursalesPermitidas()->mapWithKeys(fn (Sucursal $sucursal): array => [$sucursal->id => $sucursal->nombre.($sucursal->activo ? '' : ' · Histórico')])->all())->searchable(),
                SelectFilter::make('punto_venta_id')->label('Punto de venta base')->options(fn (): array => $this->puntosVentaPermitidos()->mapWithKeys(fn (PuntoVenta $puntoVenta): array => [$puntoVenta->id => trim(($puntoVenta->sucursal?->nombre ? $puntoVenta->sucursal->nombre.' · ' : '').$puntoVenta->nombre.($puntoVenta->activo ? '' : ' · Histórico'))])->all())->searchable(),
                SelectFilter::make('cargo')->label('Cargo')->options(fn (): array => $this->cargosPermitidos()->mapWithKeys(fn (string $cargo): array => [$cargo => $cargo])->all())->searchable(),
                SelectFilter::make('colaborador_id')->label('Colaborador')->options(fn (): array => $this->colaboradoresPermitidos()->mapWithKeys(fn (Colaborador $colaborador): array => [$colaborador->id => $colaborador->nombre_completo.($colaborador->activo ? '' : ' · Histórico')])->all())->searchable(),
                SelectFilter::make('estado_colaborador')->label('Estado del colaborador')->options(['activo' => 'Activos', 'inactivo' => 'Históricos']),
                SelectFilter::make('estado_marcacion')->label('Marcación del mes')->options($this->estadosMarcacion()),
            ])
            ->filtersFormColumns(['default' => 1, 'md' => 3, 'xl' => 4])
            ->filtersFormWidth(Width::SevenExtraLarge)
            ->filtersTriggerAction(fn (Action $action): Action => $action->label('Filtros')->icon(Heroicon::OutlinedFunnel))
            ->persistFiltersInSession()
            ->defaultSort('colaborador')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Sin colaboradores para los filtros seleccionados');
    }

    /** @return array<int, TextColumn> */
    private function columnasTabla(): array
    {
        $columnas = [
            TextColumn::make('colaborador')
                ->label('Colaborador')
                ->getStateUsing(fn (array $record): string => $record['colaborador']->nombre_completo)
                ->description(fn (array $record): ?string => collect([$record['colaborador']->cargo, $record['colaborador']->sucursal?->nombre, $record['colaborador']->puntoVenta?->nombre])->filter()->implode(' · ') ?: null)
                ->searchable()
                ->sortable()
                ->weight('medium')
                ->wrap(),
        ];

        foreach ($this->dias as $dia) {
            $fecha = $dia->toDateString();
            $columnas[] = TextColumn::make('dia_'.$dia->format('Y_m_d'))
                ->label($dia->locale('es')->translatedFormat('D').' '.$dia->day)
                ->getStateUsing(fn (array $record): HtmlString => $this->htmlMarcacionesDia($record['marcaciones'][$fecha] ?? collect()))
                ->html()
                ->wrap()
                ->extraHeaderAttributes(['class' => $fecha === now()->toDateString() ? 'attendance-matrix-day-header--today' : ''])
                ->extraCellAttributes(['class' => $fecha === now()->toDateString() ? 'attendance-matrix-day-cell--today' : '']);
        }

        return $columnas;
    }

    /** @param array<string, mixed>|null $filters */
    private function registrosPaginados(?array $filters, ?string $search, int $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator
    {
        $filters = $this->tableFilters ?? $filters;
        $consulta = $this->colaboradoresFiltradosQuery($filters, $search)
            ->with(['empresa:id,nombre', 'area:id,nombre', 'sucursal:id,nombre', 'puntoVenta:id,nombre'])
            ->orderBy('nombre_completo');

        if ($sortColumn === 'colaborador' && $sortDirection === 'desc') {
            $consulta->reorder()->orderByDesc('nombre_completo');
        }

        $total = (clone $consulta)->count();
        $porPagina = $recordsPerPage === 'all' ? max($total, 1) : (int) $recordsPerPage;
        $pagina = max($page, 1);
        /** @var Collection<int, Colaborador> $colaboradores */
        $colaboradores = $consulta->forPage($pagina, $porPagina)->get();
        $marcaciones = app(MatrizMarcacionesService::class)->porColaboradorYDia($colaboradores, $this->inicioMes());
        $registros = $colaboradores->map(fn (Colaborador $colaborador): array => [
            'colaborador' => $colaborador,
            'marcaciones' => $marcaciones[$colaborador->id] ?? [],
        ])->values();

        return new LengthAwarePaginator($registros, $total, $porPagina, $pagina, ['path' => request()->url(), 'pageName' => 'page']);
    }

    /** @param array<string, mixed>|null $filters */
    private function colaboradoresFiltradosQuery(?array $filters, ?string $search): Builder
    {
        $empresaId = $this->filterId($filters, 'empresa_id');
        $areaId = $this->filterId($filters, 'area_id');
        $sucursalId = $this->filterId($filters, 'sucursal_id');
        $puntoVentaId = $this->filterId($filters, 'punto_venta_id');
        $colaboradorId = $this->filterId($filters, 'colaborador_id');
        $cargo = data_get($filters, 'cargo.value');
        $estadoColaborador = data_get($filters, 'estado_colaborador.value');
        $estadoMarcacion = data_get($filters, 'estado_marcacion.value', 'todos');

        $consulta = $this->colaboradoresBaseQuery()
            ->when($empresaId, fn (Builder $colaboradores): Builder => $colaboradores->where('empresa_id', $empresaId))
            ->when($areaId, fn (Builder $colaboradores): Builder => $colaboradores->where('area_id', $areaId))
            ->when($sucursalId, fn (Builder $colaboradores): Builder => $colaboradores->where('sucursal_id', $sucursalId))
            ->when($puntoVentaId, fn (Builder $colaboradores): Builder => $colaboradores->where('punto_venta_id', $puntoVentaId))
            ->when($colaboradorId, fn (Builder $colaboradores): Builder => $colaboradores->whereKey($colaboradorId))
            ->when(filled($cargo), fn (Builder $colaboradores): Builder => $colaboradores->where('cargo', $cargo))
            ->when($estadoColaborador === 'activo', fn (Builder $colaboradores): Builder => $colaboradores->where('activo', true))
            ->when($estadoColaborador === 'inactivo', fn (Builder $colaboradores): Builder => $colaboradores->where('activo', false));

        if (filled($search)) {
            $termino = '%'.trim($search).'%';
            $consulta->where(function (Builder $subconsulta) use ($termino): void {
                $subconsulta->where('nombre_completo', 'like', $termino)->orWhere('documento_identidad', 'like', $termino)->orWhere('codigo_empresa', 'like', $termino);
            });
        }

        return match ($estadoMarcacion) {
            'con_marcaciones' => $consulta->whereHas('marcaciones', fn (Builder $marcaciones): Builder => $this->cambiarRango($marcaciones)),
            'sin_marcaciones' => $consulta->whereDoesntHave('marcaciones', fn (Builder $marcaciones): Builder => $this->cambiarRango($marcaciones)),
            'sin_entrada' => $consulta->whereDoesntHave('marcaciones', fn (Builder $marcaciones): Builder => $this->cambiarRango($marcaciones, Marcacion::TIPO_ENTRADA)),
            Marcacion::TIPO_ENTRADA, Marcacion::TIPO_SALIDA_REFRIGERIO, Marcacion::TIPO_REGRESO_REFRIGERIO, Marcacion::TIPO_SALIDA => $consulta->whereHas('marcaciones', fn (Builder $marcaciones): Builder => $this->cambiarRango($marcaciones, $estadoMarcacion)),
            default => $consulta,
        };
    }

    /** @return Collection<int, Sucursal> */
    private function sucursalesPermitidas(): Collection
    {
        return Sucursal::query()->whereIn('id', AlcanceSupervisor::sucursalIdsHistoricos(auth()->user()))->orderBy('nombre')->get(['id', 'nombre', 'activo']);
    }

    /** @return Collection<int, PuntoVenta> */
    private function puntosVentaPermitidos(): Collection
    {
        return PuntoVenta::query()->with('sucursal:id,nombre')->whereIn('sucursal_id', AlcanceSupervisor::sucursalIdsHistoricos(auth()->user()))->orderBy('nombre')->get(['id', 'sucursal_id', 'nombre', 'activo']);
    }

    /** @return Collection<int, Empresa> */
    private function empresasPermitidas(): Collection
    {
        return Empresa::query()->whereIn('id', $this->colaboradoresBaseQuery()->whereNotNull('empresa_id')->select('empresa_id'))->orderBy('nombre')->get(['id', 'nombre']);
    }

    /** @return Collection<int, Area> */
    private function areasPermitidas(): Collection
    {
        return Area::query()->whereIn('id', $this->colaboradoresBaseQuery()->whereNotNull('area_id')->select('area_id'))->orderBy('nombre')->get(['id', 'nombre']);
    }

    /** @return Collection<int, string> */
    private function cargosPermitidos(): Collection
    {
        return $this->colaboradoresBaseQuery()->whereNotNull('cargo')->where('cargo', '!=', '')->distinct()->orderBy('cargo')->pluck('cargo');
    }

    /** @return Collection<int, Colaborador> */
    private function colaboradoresPermitidos(): Collection
    {
        return $this->colaboradoresBaseQuery()->orderBy('nombre_completo')->get(['id', 'nombre_completo', 'activo']);
    }

    /** @return Collection<int, Carbon> */
    public function getDiasProperty(): Collection
    {
        $inicio = $this->inicioMes();
        $fin = $inicio->copy()->endOfMonth();

        return collect(range(0, $inicio->diffInDays($fin)))->map(fn (int $desplazamiento): Carbon => $inicio->copy()->addDays($desplazamiento));
    }

    /** @return array<string, string> */
    private function estadosMarcacion(): array
    {
        return [
            'con_marcaciones' => 'Con alguna marcación',
            'sin_marcaciones' => 'Sin marcaciones',
            Marcacion::TIPO_ENTRADA => 'Con ingreso',
            'sin_entrada' => 'Sin ingreso',
            Marcacion::TIPO_SALIDA_REFRIGERIO => 'Con salida a refrigerio',
            Marcacion::TIPO_REGRESO_REFRIGERIO => 'Con regreso de refrigerio',
            Marcacion::TIPO_SALIDA => 'Con salida de turno',
        ];
    }

    private function htmlMarcacionesDia(Collection $marcaciones): HtmlString
    {
        if ($marcaciones->isEmpty()) {
            return new HtmlString('<span class="attendance-matrix-empty">Sin marcaciones</span>');
        }

        return new HtmlString($marcaciones->map(function (Marcacion $marcacion): string {
            $color = match (MatrizMarcacionesService::colorTipo($marcacion->tipo)) {
                'success' => 'success', 'warning' => 'warning', 'danger' => 'danger', 'info' => 'info', default => 'gray',
            };

            return '<div class="attendance-matrix-mark attendance-matrix-mark--'.$color.'"><span>'.e(MatrizMarcacionesService::etiquetaTipo($marcacion->tipo)).'</span><time datetime="'.e($marcacion->fecha_hora->toIso8601String()).'">'.e($marcacion->fecha_hora->format('H:i')).'</time></div>';
        })->implode(''));
    }

    private function cambiarRango(Builder $query, ?string $tipo = null): Builder
    {
        return $query->where('fecha_hora', '>=', $this->inicioMes())->where('fecha_hora', '<', $this->inicioMes()->copy()->addMonth())->when($tipo, fn (Builder $marcaciones): Builder => $marcaciones->where('tipo', $tipo));
    }

    private function colaboradoresBaseQuery(): Builder
    {
        return Colaborador::query()->whereIn('sucursal_id', AlcanceSupervisor::sucursalIdsHistoricos(auth()->user()));
    }

    /** @param array<string, mixed>|null $filters */
    private function filterId(?array $filters, string $nombre): ?int
    {
        $valor = data_get($filters, $nombre.'.value');

        return filled($valor) ? (int) $valor : null;
    }

    private function inicioMes(): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $this->mes ?? now()->format('Y-m'), config('app.timezone')) ?: now()->startOfMonth();
    }

    private function redirigirAlMes(string $mes): void
    {
        $this->redirect(static::getUrl(['mes' => $mes]), navigate: true);
    }
}
