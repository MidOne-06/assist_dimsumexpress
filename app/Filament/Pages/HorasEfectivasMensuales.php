<?php

namespace App\Filament\Pages;

use App\Models\Area;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Services\HorasEfectivasMensualesService;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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

class HorasEfectivasMensuales extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Horas efectivas';
    protected static ?string $title = 'Horas efectivas mensuales';
    protected string $view = 'filament.pages.horas-efectivas-mensuales';

    public string $mes;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:HorasEfectivasMensuales') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mesAnterior')->label('Mes anterior')->icon(Heroicon::OutlinedChevronLeft)->iconButton()->tooltip('Mes anterior')->action(fn () => $this->mesAnterior()),
            Action::make('periodo')->label(fn (): string => ucfirst($this->inicioPeriodo()->locale('es')->translatedFormat('F Y')))->disabled(),
            Action::make('mesSiguiente')->label('Mes siguiente')->icon(Heroicon::OutlinedChevronRight)->iconButton()->tooltip('Mes siguiente')->action(fn () => $this->mesSiguiente()),
            Action::make('hoy')->label('Hoy')->color('gray')->action(fn () => $this->irAHoy()),
        ];
    }

    public function mesAnterior(): void
    {
        $this->mes = $this->inicioPeriodo()->subMonthNoOverflow()->format('Y-m');
        $this->resetTable();
    }

    public function mesSiguiente(): void
    {
        $this->mes = $this->inicioPeriodo()->addMonthNoOverflow()->format('Y-m');
        $this->resetTable();
    }

    public function irAHoy(): void
    {
        $this->mes = now()->format('Y-m');
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, ?string $search, int|string $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => $this->registrosPaginados($filters, $search, (int) $page, $recordsPerPage, $sortColumn, $sortDirection))
            ->columns([
                TextColumn::make('colaborador')
                    ->label('Colaborador')
                    ->getStateUsing(fn (array $record): string => $record['colaborador']->nombre_completo)
                    ->description(fn (array $record): ?string => collect([$record['colaborador']->empresa?->nombre, $record['colaborador']->area?->nombre])->filter()->implode(' · ') ?: null)
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('sucursal')
                    ->label('Local base')
                    ->getStateUsing(fn (array $record): string => $record['colaborador']->sucursal?->nombre ?? '—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jornadas_cerradas')
                    ->label('Jornadas')
                    ->numeric()
                    ->description(fn (array $record): ?string => collect([
                        $record['jornadas_abiertas'] ? $record['jornadas_abiertas'] . ' en curso' : null,
                        $record['jornadas_inconsistentes'] ? $record['jornadas_inconsistentes'] . ' observada' : null,
                    ])->filter()->implode(' · ') ?: null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('efectivos_minutos')
                    ->label('Efectivas')
                    ->getStateUsing(fn (array $record): string => $record['jornadas_cerradas'] ? HorasEfectivasMensualesService::formatoSegundos($record['efectivos_segundos']) : '—')
                    ->alignEnd()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('objetivo_minutos')
                    ->label('Objetivo')
                    ->getStateUsing(fn (array $record): string => $record['jornadas_cerradas'] ? HorasEfectivasMensualesService::formatoSegundos($record['objetivo_segundos']) : '—')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('cumplimiento')
                    ->label('Cumplimiento')
                    ->getStateUsing(fn (array $record): string => static::estadoCumplimiento($record))
                    ->badge()
                    ->color(fn (array $record): string => static::colorCumplimiento($record))
                    ->alignCenter(),
                TextColumn::make('diferencia_minutos')
                    ->label('Diferencia')
                    ->getStateUsing(fn (array $record): string => $record['jornadas_cerradas'] ? HorasEfectivasMensualesService::formatoSegundos($record['diferencia_segundos']) : '—')
                    ->color(fn (array $record): string => $record['diferencia_segundos'] < 0 ? 'danger' : 'success')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('extras_minutos')
                    ->label('Extras')
                    ->getStateUsing(fn (array $record): string => $record['jornadas_cerradas'] ? HorasEfectivasMensualesService::formatoSegundos($record['extras_segundos']) : '—')
                    ->color('warning')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('sucursal_id')->label('Local')->options(fn (): array => $this->sucursalesPermitidas()->orderBy('nombre')->pluck('nombre', 'id')->all())->searchable(),
                SelectFilter::make('empresa_id')->label('Empresa')->options(fn (): array => $this->empresasPermitidas()->pluck('nombre', 'id')->all())->searchable(),
                SelectFilter::make('area_id')->label('Área')->options(fn (): array => $this->areasPermitidas()->pluck('nombre', 'id')->all())->searchable(),
                SelectFilter::make('estado_colaborador')->label('Estado')->options(['activo' => 'Activo', 'inactivo' => 'Inactivo']),
                SelectFilter::make('colaborador_id')->label('Colaborador')->options(fn (): array => $this->colaboradoresPermitidos()->orderBy('nombre_completo')->pluck('nombre_completo', 'id')->all())->searchable(),
            ])
            ->filtersFormColumns(['default' => 1, 'md' => 3])
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->persistFiltersInSession()
            ->defaultSort('colaborador')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin jornadas con marcaciones')
            ->recordActions([
                Action::make('detalle')
                    ->label('Detalle')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->button()
                    ->modalHeading(fn (array $record): string => 'Jornadas de ' . $record['colaborador']->nombre_completo)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->schema(fn (array $record): array => static::detalleSchema($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ]);
    }

    /** @param array<string, mixed>|null $filters */
    private function registrosPaginados(?array $filters, ?string $search, int $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator
    {
        $sucursalId = static::filterId($filters, 'sucursal_id');
        $empresaId = static::filterId($filters, 'empresa_id');
        $areaId = static::filterId($filters, 'area_id');
        $colaboradorId = static::filterId($filters, 'colaborador_id');
        $estadoColaborador = data_get($filters, 'estado_colaborador.value');
        $registros = $this->resumenes($sucursalId, $empresaId, $areaId, $estadoColaborador, $colaboradorId);

        if (filled($search)) {
            $busqueda = mb_strtolower($search);
            $registros = $registros->filter(fn (array $fila): bool => str_contains(mb_strtolower($fila['colaborador']->nombre_completo), $busqueda)
                || str_contains(mb_strtolower($fila['colaborador']->sucursal?->nombre ?? ''), $busqueda)
                || str_contains(mb_strtolower($fila['colaborador']->empresa?->nombre ?? ''), $busqueda)
                || str_contains(mb_strtolower($fila['colaborador']->area?->nombre ?? ''), $busqueda));
        }

        $sortColumn = in_array($sortColumn, ['colaborador', 'sucursal', 'jornadas_cerradas', 'efectivos_minutos', 'objetivo_minutos', 'diferencia_minutos', 'extras_minutos'], true) ? $sortColumn : 'colaborador';
        $registros = $registros->sortBy(fn (array $fila): string|int => match ($sortColumn) {
            'colaborador' => mb_strtolower($fila['colaborador']->nombre_completo),
            'sucursal' => mb_strtolower($fila['colaborador']->sucursal?->nombre ?? ''),
            default => $fila[$sortColumn],
        }, SORT_NATURAL, $sortDirection === 'desc')->values();

        $recordsPerPage = $recordsPerPage === 'all' ? max($registros->count(), 1) : (int) $recordsPerPage;

        return new LengthAwarePaginator($registros->forPage(max($page, 1), $recordsPerPage)->values(), $registros->count(), $recordsPerPage, max($page, 1), ['path' => request()->url(), 'pageName' => 'page']);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function resumenes(?int $sucursalId, ?int $empresaId, ?int $areaId, mixed $estadoColaborador, ?int $colaboradorId): Collection
    {
        return app(HorasEfectivasMensualesService::class)->resumenes(
            $this->inicioPeriodo(),
            $this->colaboradoresPermitidos($sucursalId, $empresaId, $areaId, $estadoColaborador),
            $colaboradorId,
        );
    }

    /** @return array<Section> */
    private static function detalleSchema(array $record): array
    {
        return [
            Section::make()->compact()->columns(['default' => 1, 'md' => 4])->schema([
                TextEntry::make('efectivas')->label('Efectivas')->state($record['jornadas_cerradas'] ? static::formatoSegundos($record['efectivos_segundos']) : '—')->weight('medium'),
                TextEntry::make('objetivo')->label('Objetivo')->state($record['jornadas_cerradas'] ? static::formatoSegundos($record['objetivo_segundos']) : '—'),
                TextEntry::make('diferencia')->label('Diferencia')->state($record['jornadas_cerradas'] ? static::formatoSegundos($record['diferencia_segundos']) : '—')->color($record['diferencia_segundos'] < 0 ? 'danger' : 'success'),
                TextEntry::make('extras')->label('Extras')->state($record['jornadas_cerradas'] ? static::formatoSegundos($record['extras_segundos']) : '—')->color('warning'),
            ]),
            Section::make('Jornadas')->compact()->schema([
                RepeatableEntry::make('jornadas')->state($record['jornadas'])->contained()->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                        TextEntry::make('fecha')->label('Fecha'),
                        TextEntry::make('turno')->label('Turno'),
                        TextEntry::make('local')->label('Local')->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 2]),
                        TextEntry::make('entrada')->label('Entrada'),
                        TextEntry::make('salida')->label('Salida'),
                        TextEntry::make('efectivas')->label('Efectivas'),
                        TextEntry::make('objetivo')->label('Objetivo'),
                        TextEntry::make('diferencia')->label('Diferencia')->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 2])->color(fn (string $state): string => str_starts_with($state, '−') ? 'danger' : 'success'),
                        TextEntry::make('estado')->label('Estado')->badge()->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 2])->color(fn (string $state): string => match ($state) { 'En curso' => 'warning', 'Parcial', 'Observada' => 'danger', default => 'success' }),
                    ]),
                ]),
            ]),
        ];
    }

    /** @param array<string, mixed> $record */
    private static function estadoCumplimiento(array $record): string
    {
        if ($record['jornadas_inconsistentes']) {
            return 'Observada';
        }

        return ! $record['jornadas_cerradas'] ? 'En curso' : ($record['diferencia_segundos'] < 0 ? 'Parcial' : 'Cumplida');
    }

    /** @param array<string, mixed> $record */
    private static function colorCumplimiento(array $record): string
    {
        if ($record['jornadas_inconsistentes']) {
            return 'danger';
        }

        return ! $record['jornadas_cerradas'] ? 'warning' : ($record['diferencia_segundos'] < 0 ? 'danger' : 'success');
    }

    public static function formatoHoras(int $minutos): string
    {
        $horas = intdiv(abs($minutos), 60);
        $resto = abs($minutos) % 60;
        $valor = "{$horas} h" . ($resto ? " {$resto} min" : '');

        return $minutos < 0 ? "−{$valor}" : $valor;
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

    private function inicioPeriodo(): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $this->mes, config('app.timezone')) ?: now()->startOfMonth();
    }

    /** @param array<string, mixed>|null $filters */
    private static function filterId(?array $filters, string $name): ?int
    {
        $value = data_get($filters, "{$name}.value");

        return filled($value) ? (int) $value : null;
    }

    private function sucursalesPermitidas(): Builder
    {
        return AlcanceSupervisor::sucursalesQuery(auth()->user());
    }

    private function empresasPermitidas(): Builder
    {
        return Empresa::query()->whereIn('id', $this->colaboradoresPermitidos()->whereNotNull('empresa_id')->select('empresa_id'))->orderBy('nombre');
    }

    private function areasPermitidas(): Builder
    {
        return Area::query()->whereIn('id', $this->colaboradoresPermitidos()->whereNotNull('area_id')->select('area_id'))->orderBy('nombre');
    }

    private function colaboradoresPermitidos(?int $sucursalId = null, ?int $empresaId = null, ?int $areaId = null, mixed $estado = null): Builder
    {
        return Colaborador::query()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
            ->when($sucursalId, fn (Builder $query) => $query->where('sucursal_id', $sucursalId))
            ->when($empresaId, fn (Builder $query) => $query->where('empresa_id', $empresaId))
            ->when($areaId, fn (Builder $query) => $query->where('area_id', $areaId))
            ->when($estado === 'activo', fn (Builder $query) => $query->where('activo', true))
            ->when($estado === 'inactivo', fn (Builder $query) => $query->where('activo', false));
    }
}
