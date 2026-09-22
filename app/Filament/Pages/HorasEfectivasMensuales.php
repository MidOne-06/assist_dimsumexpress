<?php

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Support\AlcanceSupervisor;
use App\Support\JornadaMarcacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
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
            Action::make('periodo')->label(fn (): string => ucfirst(Carbon::parse("{$this->mes}-01")->locale('es')->translatedFormat('F Y')))->disabled(),
            Action::make('mesSiguiente')->label('Mes siguiente')->icon(Heroicon::OutlinedChevronRight)->iconButton()->tooltip('Mes siguiente')->action(fn () => $this->mesSiguiente()),
            Action::make('hoy')->label('Hoy')->color('gray')->action(fn () => $this->irAHoy()),
        ];
    }

    public function mesAnterior(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->subMonthNoOverflow()->format('Y-m');
        $this->resetTable();
    }

    public function mesSiguiente(): void
    {
        $this->mes = Carbon::parse("{$this->mes}-01")->addMonthNoOverflow()->format('Y-m');
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
                TextColumn::make('colaborador')->label('Colaborador')->getStateUsing(fn (array $record): string => $record['colaborador']->nombre_completo)->searchable()->sortable()->weight('medium'),
                TextColumn::make('sucursal')->label('Local')->getStateUsing(fn (array $record): string => $record['colaborador']->sucursal->nombre)->searchable()->sortable(),
                TextColumn::make('jornadas_cerradas')->label('Jornadas')->numeric()->alignEnd()->sortable(),
                TextColumn::make('efectivos_minutos')->label('Efectivas')->getStateUsing(fn (array $record): string => static::formatoHoras($record['efectivos_minutos']))->alignEnd()->sortable()->weight('medium'),
                TextColumn::make('objetivo_minutos')->label('Objetivo')->getStateUsing(fn (array $record): string => static::formatoHoras($record['objetivo_minutos']))->alignEnd()->sortable(),
                TextColumn::make('diferencia_minutos')->label('Diferencia')->getStateUsing(fn (array $record): string => static::formatoHoras($record['diferencia_minutos']))->color(fn (array $record): string => $record['diferencia_minutos'] < 0 ? 'danger' : 'success')->alignEnd()->sortable(),
                TextColumn::make('extras_minutos')->label('Extras')->getStateUsing(fn (array $record): string => static::formatoHoras($record['extras_minutos']))->color('warning')->alignEnd()->sortable(),
            ])
            ->filters([
                SelectFilter::make('sucursal_id')->label('Local')->options(fn (): array => $this->sucursalesPermitidas()->pluck('nombre', 'id')->all())->searchable(),
                SelectFilter::make('colaborador_id')->label('Colaborador')->options(fn (): array => $this->colaboradoresPermitidos()->orderBy('nombre_completo')->pluck('nombre_completo', 'id')->all())->searchable(),
            ])
            ->filtersFormColumns(2)
            ->defaultSort('colaborador')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Sin jornadas cerradas');
    }

    /** @param array<string, mixed>|null $filters */
    private function registrosPaginados(?array $filters, ?string $search, int $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator
    {
        $sucursalId = filled(data_get($filters, 'sucursal_id.value')) ? (int) data_get($filters, 'sucursal_id.value') : null;
        $colaboradorId = filled(data_get($filters, 'colaborador_id.value')) ? (int) data_get($filters, 'colaborador_id.value') : null;
        $registros = $this->resumenes($sucursalId, $colaboradorId);

        if (filled($search)) {
            $busqueda = mb_strtolower($search);
            $registros = $registros->filter(fn (array $fila): bool => str_contains(mb_strtolower($fila['colaborador']->nombre_completo), $busqueda) || str_contains(mb_strtolower($fila['colaborador']->sucursal->nombre), $busqueda));
        }

        $sortColumn = in_array($sortColumn, ['colaborador', 'sucursal', 'jornadas_cerradas', 'efectivos_minutos', 'objetivo_minutos', 'diferencia_minutos', 'extras_minutos'], true) ? $sortColumn : 'colaborador';
        $registros = $registros->sortBy(fn (array $fila): string|int => match ($sortColumn) {
            'colaborador' => mb_strtolower($fila['colaborador']->nombre_completo),
            'sucursal' => mb_strtolower($fila['colaborador']->sucursal->nombre),
            default => $fila[$sortColumn],
        }, SORT_NATURAL, $sortDirection === 'desc')->values();

        $recordsPerPage = $recordsPerPage === 'all' ? max($registros->count(), 1) : (int) $recordsPerPage;

        return new LengthAwarePaginator($registros->forPage(max($page, 1), $recordsPerPage)->values(), $registros->count(), $recordsPerPage, max($page, 1), ['path' => request()->url(), 'pageName' => 'page']);
    }

    /** @return Collection<int, array{colaborador: Colaborador, jornadas_cerradas: int, efectivos_minutos: int, objetivo_minutos: int, diferencia_minutos: int, extras_minutos: int}> */
    private function resumenes(?int $sucursalId, ?int $colaboradorId): Collection
    {
        $inicio = Carbon::parse("{$this->mes}-01", config('app.timezone'))->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $asignaciones = AsignacionTurno::query()->with(['colaborador.sucursal', 'turno'])->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->whereIn('colaborador_id', $this->colaboradoresPermitidos($sucursalId)->select('id'))->when($colaboradorId, fn (Builder $query) => $query->where('colaborador_id', $colaboradorId))->orderBy('fecha')->get();

        if ($asignaciones->isEmpty()) {
            return collect();
        }

        $marcacionesPorColaborador = Marcacion::query()->whereIn('colaborador_id', $asignaciones->pluck('colaborador_id')->unique())->whereIn('turno_id', $asignaciones->pluck('turno_id')->unique())->whereBetween('fecha_hora', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()->addMinutes(JornadaMarcacion::MAXIMO_JORNADA_MINUTOS)])->orderBy('fecha_hora')->orderBy('id')->get()->groupBy('colaborador_id');
        $resumenes = collect();

        foreach ($asignaciones as $asignacion) {
            $limites = JornadaMarcacion::limites($asignacion);
            $marcaciones = ($marcacionesPorColaborador->get($asignacion->colaborador_id) ?? collect())->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === $asignacion->turno_id && $marcacion->fecha_hora->betweenIncluded($limites['ventana_inicio'], $limites['jornada_fin_maximo']))->values();
            $jornada = JornadaMarcacion::resumen($asignacion->colaborador, $asignacion, $marcaciones);
            if ($jornada['estado'] === 'en_curso') {
                continue;
            }

            $id = $asignacion->colaborador_id;
            $fila = $resumenes->get($id, ['colaborador' => $asignacion->colaborador, 'jornadas_cerradas' => 0, 'efectivos_minutos' => 0, 'objetivo_minutos' => 0, 'diferencia_minutos' => 0, 'extras_minutos' => 0]);
            $fila['jornadas_cerradas']++;
            $fila['efectivos_minutos'] += $jornada['efectivos_minutos'];
            $fila['objetivo_minutos'] += $jornada['objetivo_minutos'];
            $fila['diferencia_minutos'] += $jornada['diferencia_minutos'];
            $fila['extras_minutos'] += $jornada['extras_minutos'];
            $resumenes->put($id, $fila);
        }

        return $resumenes->values();
    }

    public static function formatoHoras(int $minutos): string
    {
        $horas = intdiv(abs($minutos), 60);
        $resto = abs($minutos) % 60;
        $valor = "{$horas} h" . ($resto ? " {$resto} min" : '');

        return $minutos < 0 ? "−{$valor}" : $valor;
    }

    private function sucursalesPermitidas(): Builder
    {
        return AlcanceSupervisor::sucursalesQuery(auth()->user());
    }

    private function colaboradoresPermitidos(?int $sucursalId = null): Builder
    {
        return Colaborador::query()->where('activo', true)->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))->when($sucursalId, fn (Builder $query) => $query->where('sucursal_id', $sucursalId));
    }
}
