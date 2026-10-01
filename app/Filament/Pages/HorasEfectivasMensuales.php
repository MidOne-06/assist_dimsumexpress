<?php

namespace App\Filament\Pages;

use App\Models\Area;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\Marcacion;
use App\Models\ResumenJornada;
use App\Support\AlcanceSupervisor;
use App\Support\JornadaMarcacion;
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
                    ->getStateUsing(fn (array $record): string => $record['jornadas_cerradas'] ? static::formatoSegundos($record['efectivos_segundos']) : '—')
                    ->alignEnd()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('objetivo_minutos')
                    ->label('Objetivo')
                    ->getStateUsing(fn (array $record): string => $record['jornadas_cerradas'] ? static::formatoSegundos($record['objetivo_segundos']) : '—')
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
                    ->getStateUsing(fn (array $record): string => $record['jornadas_cerradas'] ? static::formatoSegundos($record['diferencia_segundos']) : '—')
                    ->color(fn (array $record): string => $record['diferencia_segundos'] < 0 ? 'danger' : 'success')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('extras_minutos')
                    ->label('Extras')
                    ->getStateUsing(fn (array $record): string => $record['jornadas_cerradas'] ? static::formatoSegundos($record['extras_segundos']) : '—')
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
        $inicio = $this->inicioPeriodo();
        $fin = $inicio->copy()->endOfMonth();
        $asignaciones = AsignacionTurno::query()
            ->with(['colaborador.sucursal', 'colaborador.empresa', 'colaborador.area', 'turno', 'resumenJornada'])
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn('colaborador_id', $this->colaboradoresPermitidos($sucursalId, $empresaId, $areaId, $estadoColaborador)->select('id'))
            ->when($colaboradorId, fn (Builder $query) => $query->where('colaborador_id', $colaboradorId))
            ->orderBy('fecha')
            ->get();

        if ($asignaciones->isEmpty()) {
            return collect();
        }

        $marcacionesPorColaborador = Marcacion::query()
            ->with(['sucursal', 'puntoVenta'])
            ->whereIn('colaborador_id', $asignaciones->pluck('colaborador_id')->unique())
            ->whereIn('turno_id', $asignaciones->pluck('turno_id')->unique())
            ->whereBetween('fecha_hora', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()->addMinutes(JornadaMarcacion::MAXIMO_JORNADA_MINUTOS)])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get()
            ->groupBy('colaborador_id');
        $resumenes = collect();

        foreach ($asignaciones as $asignacion) {
            $limites = JornadaMarcacion::limites($asignacion);
            $marcaciones = ($marcacionesPorColaborador->get($asignacion->colaborador_id) ?? collect())
                ->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === $asignacion->turno_id && $marcacion->fecha_hora->betweenIncluded($limites['ventana_inicio'], $limites['jornada_fin_maximo']))
                ->values();
            $tieneEntrada = $marcaciones->contains('tipo', Marcacion::TIPO_ENTRADA);

            if (! $tieneEntrada) {
                continue;
            }

            $jornada = $asignacion->resumenJornada
                ? static::jornadaConsolidada($asignacion->resumenJornada)
                : JornadaMarcacion::resumen($asignacion->colaborador, $asignacion, $marcaciones);
            $id = $asignacion->colaborador_id;
            $fila = $resumenes->get($id, static::filaVacia($asignacion->colaborador));
            $detalle = static::detalleJornada($asignacion, $marcaciones, $jornada);

            if ($jornada['estado'] === 'en_curso') {
                $fila['jornadas_abiertas']++;
            } elseif ($jornada['estado'] === 'inconsistente') {
                $fila['jornadas_inconsistentes']++;
            } else {
                $fila['jornadas_cerradas']++;
                $fila['efectivos_segundos'] += $jornada['efectivos_segundos'];
                $fila['objetivo_segundos'] += $jornada['objetivo_segundos'];
                $fila['diferencia_segundos'] += $jornada['diferencia_segundos'];
                $fila['extras_segundos'] += $jornada['extras_segundos'];
                $fila['efectivos_minutos'] += $jornada['efectivos_minutos'];
                $fila['objetivo_minutos'] += $jornada['objetivo_minutos'];
                $fila['diferencia_minutos'] += $jornada['diferencia_minutos'];
                $fila['extras_minutos'] += $jornada['extras_minutos'];
            }

            $fila['jornadas'][] = $detalle;
            $resumenes->put($id, $fila);
        }

        return $resumenes->values();
    }

    /** @return array{estado:string, efectivos_segundos:int, objetivo_segundos:int, extras_segundos:int, diferencia_segundos:int, refrigerio_segundos:int, efectivos_minutos:int, objetivo_minutos:int, extras_minutos:int, diferencia_minutos:int, inconsistencias:array<int, string>} */
    private static function jornadaConsolidada(ResumenJornada $resumen): array
    {
        return [
            'estado' => $resumen->estado,
            'efectivos_segundos' => $resumen->efectivos_segundos,
            'objetivo_segundos' => $resumen->objetivo_segundos,
            'extras_segundos' => $resumen->extras_segundos,
            'diferencia_segundos' => $resumen->diferencia_segundos,
            'refrigerio_segundos' => $resumen->refrigerio_segundos,
            'efectivos_minutos' => intdiv($resumen->efectivos_segundos, 60),
            'objetivo_minutos' => intdiv($resumen->objetivo_segundos, 60),
            'extras_minutos' => intdiv($resumen->extras_segundos, 60),
            'diferencia_minutos' => ($resumen->diferencia_segundos < 0 ? -1 : 1) * intdiv(abs($resumen->diferencia_segundos), 60),
            'inconsistencias' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function filaVacia(Colaborador $colaborador): array
    {
        return ['__key' => 'colaborador-' . $colaborador->id, 'colaborador' => $colaborador, 'jornadas_cerradas' => 0, 'jornadas_abiertas' => 0, 'jornadas_inconsistentes' => 0, 'efectivos_segundos' => 0, 'objetivo_segundos' => 0, 'diferencia_segundos' => 0, 'extras_segundos' => 0, 'efectivos_minutos' => 0, 'objetivo_minutos' => 0, 'diferencia_minutos' => 0, 'extras_minutos' => 0, 'jornadas' => []];
    }

    /** @param Collection<int, Marcacion> $marcaciones
     *  @param array{estado:string, efectivos_segundos:?int, objetivo_segundos:int, extras_segundos:?int, diferencia_segundos:?int, inconsistencias:array<int, string>} $jornada
     *  @return array<string, string>
     */
    private static function detalleJornada(AsignacionTurno $asignacion, Collection $marcaciones, array $jornada): array
    {
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salida = $marcaciones->filter(fn (Marcacion $marcacion): bool => $marcacion->tipo === Marcacion::TIPO_SALIDA)->last();
        $local = $entrada?->sucursal?->nombre ?? $asignacion->colaborador->sucursal?->nombre ?? '—';
        $puntoVenta = $entrada?->puntoVenta?->nombre;

        return [
            'fecha' => $asignacion->fecha->format('d/m/Y'), 'turno' => $asignacion->turno->nombre,
            'entrada' => $entrada?->fecha_hora?->format('H:i:s') ?? '—', 'salida' => $salida?->fecha_hora?->format('H:i:s') ?? '—',
            'local' => $puntoVenta ? "{$local} · {$puntoVenta}" : $local,
            'efectivas' => $jornada['efectivos_segundos'] === null ? '—' : static::formatoSegundos($jornada['efectivos_segundos']),
            'objetivo' => static::formatoSegundos($jornada['objetivo_segundos']),
            'diferencia' => $jornada['diferencia_segundos'] === null ? '—' : static::formatoSegundos($jornada['diferencia_segundos']),
            'estado' => match ($jornada['estado']) { 'en_curso' => 'En curso', 'inconsistente' => 'Observada', 'pendiente' => 'Parcial', default => 'Cumplida' },
        ];
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
