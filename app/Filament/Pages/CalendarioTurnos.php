<?php

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Services\AsignacionTurnoIndividualService;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Services\CalendarioTurnosSpreadsheetService;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\HtmlString;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Unique;

class CalendarioTurnos extends Page
{
    // Ver bitácora de AsignarTurnos.php -- mismo hallazgo: sin este trait,
    // `View:CalendarioTurnos` nunca se llegaba a evaluar.
    use HasPageShield;

    // El ancho completo (antes fijado acá) ahora se define a nivel de panel
    // (AdminPanelProvider::maxContentWidth) para que aplique a todos los
    // módulos por igual -- ver esa clase para el detalle.

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Gestión de personal';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Calendario de turnos';

    protected static ?string $title = 'Calendario de turnos';

    protected string $view = 'filament.pages.calendario-turnos';

    public ?int $sucursalId = null;

    public ?int $asignacionEditandoId = null;

    public string $mes;

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
        $this->sucursalId = null;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarCalendario')
                ->label('Exportar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('Exportar:AsignacionTurno') ?? false)
                ->action(fn () => app(CalendarioTurnosSpreadsheetService::class)->exportar(
                    $this->asignacionesDelMesQuery(),
                )),
            Action::make('asignarTurno')
                ->label('Asignar turno')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible(fn (): bool => auth()->user()?->can('Create:AsignacionTurno') ?? false)
                ->modalHeading('Asignar turno')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel('Guardar asignación')
                ->schema([
                    Section::make()
                        ->compact()
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema([
                            Select::make('colaborador_id')
                                ->label('Colaborador')
                                ->options(fn (): array => $this->colaboradores
                                    ->where('activo', true)
                                    ->pluck('nombre_completo', 'id')
                                    ->all())
                                ->searchable()
                                ->optionsLimit(8)
                                ->required()
                                ->columnSpanFull(),
                            Select::make('turno_id')
                                ->label('Turno')
                                ->options(fn (): array => Turno::query()
                                    ->where('activo', true)
                                    ->orderBy('hora_inicio')
                                    ->pluck('nombre', 'id')
                                    ->all())
                                ->required(),
                            DatePicker::make('fecha')
                                ->label('Fecha')
                                ->native(false)
                                ->minDate(today())
                                ->default(today())
                                ->required()
                                ->unique(
                                    table: 'asignaciones_turno',
                                    column: 'fecha',
                                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                        ->where('colaborador_id', $get('colaborador_id')),
                                )
                                ->validationMessages([
                                    'unique' => 'Este colaborador ya tiene un turno asignado en esa fecha.',
                                ]),
                            Textarea::make('observacion')
                                ->label('Observación')
                                ->rows(2)
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $colaborador = $this->colaboradoresPermitidosQuery()
                        ->where('activo', true)
                        ->findOrFail($data['colaborador_id']);

                    abort_unless(
                        AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $colaborador->sucursal_id),
                        403,
                    );

                    app(AsignacionTurnoIndividualService::class)->crear(auth()->user(), $data);
                }),
            Action::make('editarAsignacion')
                ->extraAttributes(['class' => 'hidden'])
                ->modalHeading('Actualizar asignación')
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel('Guardar cambios')
                ->fillForm(fn (): array => $this->datosAsignacionEditable())
                ->schema([
                    Section::make()
                        ->compact()
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema([
                            Select::make('colaborador_id')
                                ->label('Colaborador')
                                ->options(fn (): array => $this->opcionesColaboradores())
                                ->searchable()
                                ->optionsLimit(8)
                                ->required()
                                ->columnSpanFull(),
                            Select::make('turno_id')
                                ->label('Turno')
                                ->options(fn (): array => Turno::query()
                                    ->where('activo', true)
                                    ->orderBy('hora_inicio')
                                    ->pluck('nombre', 'id')
                                    ->all())
                                ->required(),
                            DatePicker::make('fecha')
                                ->label('Fecha')
                                ->native(false)
                                ->minDate(today()->addDay())
                                ->required(),
                            Textarea::make('observacion')
                                ->label('Observación')
                                ->rows(2)
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $asignacion = $this->asignacionEditable();

                    if (! $asignacion) {
                        Notification::make()
                            ->title('La asignación ya no está disponible')
                            ->danger()
                            ->send();

                        return;
                    }
                    $colaborador = $this->colaboradoresPermitidosQuery()
                        ->where('activo', true)
                        ->findOrFail($data['colaborador_id']);

                    if (AsignacionTurno::query()
                        ->where('colaborador_id', $colaborador->id)
                        ->whereDate('fecha', $data['fecha'])
                        ->whereKeyNot($asignacion->id)
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'fecha' => 'Este colaborador ya tiene un turno asignado en esa fecha.',
                        ]);
                    }

                    app(AsignacionTurnoIndividualService::class)->actualizar(auth()->user(), $asignacion, $data);
                }),
        ];
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
        $this->dispatch('calendario-turnos-ir-a-hoy');
    }

    /**
     * Abre la acción nativa de Filament con un argumento ya validado.
     *
     * Los distintivos de la grilla son controles personalizados. El ID queda
     * en el estado Livewire antes de montar la acción, sin depender del ciclo
     * de argumentos del modal.
     */
    public function abrirEdicionAsignacion(int $asignacionId): void
    {
        $asignacion = AsignacionTurno::query()
            ->with('colaborador')
            ->find($asignacionId);

        if (! $asignacion || ! $this->puedeEditarAsignacion($asignacion)) {
            Notification::make()
                ->title('No se puede editar esta asignación')
                ->danger()
                ->send();

            return;
        }

        $this->asignacionEditandoId = $asignacion->id;
        $this->mountAction('editarAsignacion');
    }

    public function updatedSucursalId(?int $sucursalId): void
    {
        if ($sucursalId === null) {
            return;
        }

        if ($sucursalId && $this->sucursalesPermitidas()->whereKey($sucursalId)->exists()) {
            return;
        }

        $this->sucursalId = $this->sucursalesPermitidas()->value('id');
    }

    public function getSucursalesProperty(): Collection
    {
        return $this->sucursalesPermitidas()->get();
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
        $inicio = Carbon::parse("{$this->mes}-01")->toDateString();
        $fin = Carbon::parse("{$this->mes}-01")->endOfMonth()->toDateString();

        return $this->colaboradoresPermitidosQuery()
            // Un colaborador desactivado no se muestra como disponible en
            // meses futuros, pero sus asignaciones ya realizadas siguen
            // siendo consultables al revisar un periodo histórico.
            ->where(function (Builder $query) use ($inicio, $fin): void {
                $query
                    ->where('activo', true)
                    ->orWhereHas('asignacionesTurno', fn (Builder $asignaciones) => $asignaciones
                        ->whereBetween('fecha', [$inicio, $fin]));
            })
            ->orderBy('nombre_completo')
            ->get();
    }

    public function getTurnosActivosProperty(): Collection
    {
        $colaboradores = $this->colaboradores;

        if ($colaboradores->isEmpty()) {
            return new Collection();
        }

        // La grilla muestra solo turnos que tienen asignaciones dentro del
        // alcance y el mes consultado. No se filtran por "activo": un turno
        // desactivado debe permanecer visible al revisar su historial.
        return Turno::query()
            ->whereIn('id', AsignacionTurno::query()
                ->whereIn('colaborador_id', $colaboradores->pluck('id'))
                ->whereBetween('fecha', $this->limitesDelMes())
                ->select('turno_id'))
            ->orderBy('hora_inicio')
            ->get();
    }

    private function sucursalesPermitidas(): \Illuminate\Database\Eloquent\Builder
    {
        return AlcanceSupervisor::sucursalesQuery(auth()->user());
    }

    private function colaboradoresPermitidosQuery(): Builder
    {
        return Colaborador::query()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
            ->when($this->sucursalId, fn (Builder $query): Builder => $query->where('sucursal_id', $this->sucursalId));
    }

    /** @return array{0: string, 1: string} */
    private function limitesDelMes(): array
    {
        $inicio = Carbon::parse("{$this->mes}-01");

        return [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()];
    }

    private function asignacionesDelMesQuery(): Builder
    {
        return AsignacionTurno::query()
            ->whereBetween('fecha', $this->limitesDelMes())
            ->whereHas('colaborador', fn (Builder $query): Builder => $query
                ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
                ->when($this->sucursalId, fn (Builder $subquery): Builder => $subquery->where('sucursal_id', $this->sucursalId)));
    }

    /** @return array<int, string> */
    private function opcionesColaboradores(): array
    {
        return $this->colaboradoresPermitidosQuery()
            ->where('activo', true)
            ->orderBy('nombre_completo')
            ->pluck('nombre_completo', 'id')
            ->all();
    }

    private function asignacionEditable(): ?AsignacionTurno
    {
        $asignacion = AsignacionTurno::query()
            ->with('colaborador')
            ->find($this->asignacionEditandoId);

        if (! $asignacion || ! $this->puedeEditarAsignacion($asignacion)) {
            return null;
        }

        return $asignacion;
    }

    /** @return array<string, mixed> */
    private function datosAsignacionEditable(): array
    {
        $asignacion = $this->asignacionEditable();

        if (! $asignacion) {
            return [];
        }

        return [
            'colaborador_id' => $asignacion->colaborador_id,
            'turno_id' => $asignacion->turno_id,
            'fecha' => $asignacion->fecha->toDateString(),
            'observacion' => $asignacion->observacion,
        ];
    }

    /** @return Collection<int, AsignacionTurno> */
    public function getAsignacionesCalendarioProperty(): Collection
    {
        return $this->asignacionesDelMesQuery()
            ->with(['colaborador.sucursal', 'turno'])
            ->get();
    }

    /** @return SupportCollection<int, array{clave: string, sucursal: Sucursal, turno: Turno}> */
    public function getFilasCalendarioProperty(): SupportCollection
    {
        return $this->asignacionesCalendario
            ->groupBy(fn (AsignacionTurno $asignacion): string => $this->claveFila($asignacion))
            ->map(function ($asignaciones, string $clave): array {
                /** @var AsignacionTurno $asignacion */
                $asignacion = $asignaciones->first();

                return [
                    'clave' => $clave,
                    'sucursal' => $asignacion->colaborador->sucursal,
                    'turno' => $asignacion->turno,
                ];
            })
            ->sortBy(fn (array $fila): string => $fila['sucursal']->nombre . '|' . $fila['turno']->hora_inicio)
            ->values();
    }

    /** @return array<string, array<string, Collection<int, AsignacionTurno>>> */
    public function getMapaPorFilaProperty(): array
    {
        $mapa = [];

        foreach ($this->asignacionesCalendario as $asignacion) {
            $clave = $this->claveFila($asignacion);
            $fecha = $asignacion->fecha->toDateString();
            $mapa[$clave][$fecha] ??= new Collection();
            $mapa[$clave][$fecha]->push($asignacion);
        }

        return $mapa;
    }

    private function claveFila(AsignacionTurno $asignacion): string
    {
        return $asignacion->colaborador->sucursal_id . ':' . $asignacion->turno_id;
    }

    /**
     * Marcaciones reales de tipo "entrada" del mes, indexadas por
     * colaborador+fecha+turno, para no hacer una consulta por celda al
     * calcular si cada asignación se cumplió con el turno programado.
     *
     * @return array<int, array<string, array<int, Marcacion>>>
     */
    public function getEntradasProperty(): array
    {
        $colaboradores = $this->colaboradores;

        if ($colaboradores->isEmpty()) {
            return [];
        }

        $inicio = Carbon::parse("{$this->mes}-01")->toDateString();
        $fin = Carbon::parse("{$this->mes}-01")->endOfMonth()->toDateString();

        $mapa = [];

        Marcacion::query()
            ->whereIn('colaborador_id', $colaboradores->pluck('id'))
            ->where('tipo', Marcacion::TIPO_ENTRADA)
            ->whereBetween('fecha_hora', ["{$inicio} 00:00:00", "{$fin} 23:59:59"])
            ->orderBy('fecha_hora')
            ->get()
            ->each(function (Marcacion $marcacion) use (&$mapa) {
                // Si por algún motivo hay más de una entrada para el mismo
                // turno, se conserva la primera. Las entradas de otro turno
                // se mantienen separadas para no validar erróneamente una
                // asignación que no les corresponde.
                $fecha = $marcacion->fecha_hora->toDateString();
                $turnoId = $marcacion->turno_id ?? 0;
                $mapa[$marcacion->colaborador_id][$fecha][$turnoId] ??= $marcacion;
            });

        return $mapa;
    }

    /**
     * Cruza lo planificado (AsignacionTurno) con la entrada registrada para
     * ese mismo turno, usando su tolerancia de entrada. Una entrada de otro
     * turno se informa como tal y nunca se interpreta como asistencia a
     * tiempo, tardanza o falta de la programación actual.
     *
     * @return array{estado: string, label: string, hora: ?string}
     */
    public function estadoAsignacion(AsignacionTurno $asignacion): array
    {
        $entradasDelDia = $this->entradas[$asignacion->colaborador_id][$asignacion->fecha->toDateString()] ?? [];
        $entrada = $entradasDelDia[$asignacion->turno_id] ?? null;
        $turno = $asignacion->turno;

        $limite = Carbon::parse($asignacion->fecha->toDateString() . ' ' . $turno->hora_inicio)
            ->addMinutes($turno->tolerancia_entrada_minutos);

        if ($entrada) {
            if ($entrada->fecha_hora->lte($limite)) {
                return ['estado' => 'a_tiempo', 'label' => 'A tiempo', 'hora' => $entrada->fecha_hora->format('H:i:s')];
            }

            $minutosTarde = (int) ceil($limite->diffInSeconds($entrada->fecha_hora) / 60);

            return ['estado' => 'tardanza', 'label' => "Tardanza de {$minutosTarde} min", 'hora' => $entrada->fecha_hora->format('H:i:s')];
        }

        if ($entradasDelDia !== []) {
            $entradaOtroTurno = reset($entradasDelDia);

            return [
                'estado' => 'turno_distinto',
                'label' => 'Marcó otro turno',
                'hora' => $entradaOtroTurno->fecha_hora->format('H:i:s'),
            ];
        }

        // Sin marcación todavía: si el límite de tolerancia de hoy aún no
        // pasó (o la fecha es futura), no es una falta, solo está pendiente.
        $aunNoVence = $asignacion->fecha->isFuture()
            || ($asignacion->fecha->isToday() && now()->lt($limite));

        if ($aunNoVence) {
            return ['estado' => 'pendiente', 'label' => 'Pendiente', 'hora' => null];
        }

        return ['estado' => 'falta', 'label' => 'Falta (sin marcar entrada)', 'hora' => null];
    }

    public function puedeEditarAsignacion(AsignacionTurno $asignacion): bool
    {
        return auth()->user()?->can('update', $asignacion) ?? false;
    }

    public function tooltipNombres(\Illuminate\Support\Collection $asignaciones): HtmlString
    {
        return new HtmlString(
            $asignaciones
                ->map(function (AsignacionTurno $asignacion) {
                    $estado = $this->estadoAsignacion($asignacion);
                    $nombre = e($asignacion->colaborador->nombre_completo);
                    $detalle = $estado['hora']
                        ? "{$estado['label']} ({$estado['hora']})"
                        : $estado['label'];

                    return "{$nombre} — " . e($detalle);
                })
                ->join('<br>')
        );
    }

    /**
     * El peor estado entre varios colaboradores del mismo turno/día, para
     * decidir si el badge resumen (">2 colaboradores") debe avisar de algo.
     *
     * @param  Collection<int, AsignacionTurno>  $asignaciones
     */
    public function peorEstado(\Illuminate\Support\Collection $asignaciones): string
    {
        $prioridad = ['falta' => 4, 'turno_distinto' => 3, 'tardanza' => 2, 'pendiente' => 1, 'a_tiempo' => 0];

        return $asignaciones
            ->map(fn (AsignacionTurno $a) => $this->estadoAsignacion($a)['estado'])
            ->sortByDesc(fn (string $estado) => $prioridad[$estado] ?? 0)
            ->first() ?? 'pendiente';
    }

}
