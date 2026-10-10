<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Services\JornadaCalendarioService;
use App\Services\RegularizacionJornadaService;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Calendario operativo individual: cruza lo planificado con las marcaciones
 * reales sin modificar ningún dato de asistencia.
 */
class ControlJornadas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Control de jornadas';

    protected static ?string $title = 'Calendario de turnos';

    protected string $view = 'filament.pages.control-jornadas';

    public ?int $sucursalId = null;

    public ?int $colaboradorId = null;

    public string $mes;

    /** Fecha excepcional elegida antes de abrir el modal nativo. */
    public ?string $fechaRegularizacion = null;

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
        $this->sucursalId = request()->integer('sucursal') ?: null;
        $this->colaboradorId = request()->integer('colaborador') ?: null;

        $this->normalizarSeleccion();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:ControlJornadas') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Filament necesita registrar la acción para renderizar su contenedor
     * nativo de modales. El disparador técnico no se muestra: una
     * regularización siempre requiere el día excepcional elegido desde el
     * calendario.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->regularizarJornadaAction()
                // No usar hidden(): Filament dejaría de resolver la acción
                // cuando el icono de un día intente montarla.
                ->extraAttributes([
                    'style' => 'display: none !important',
                    'aria-hidden' => 'true',
                    'tabindex' => '-1',
                ]),
        ];
    }

    /**
     * Acción interna: solo se monta desde abrirRegularizacionJornada(), que
     * persiste la fecha del día excepcional en el estado del componente. El
     * montaje sin esa fecha se
     * cancela de forma defensiva antes de renderizar el formulario.
     */
    protected function regularizarJornadaAction(): Action
    {
        return Action::make('regularizarJornada')
            ->modalHeading('Regularizar jornada')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Regularizar jornada')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $fecha = $this->fechaRegularizacion;

                // Esta acción no tiene botón propio: solo puede montarse por
                // el icono del día excepcional. Cancelar aquí evita que un
                // montaje directo renderice un modal sin contexto.
                if (! filled($fecha) || ! $this->puedeRegularizarJornada((string) $fecha)) {
                    $action->cancel();

                    return;
                }

                $schema?->fill([
                    'fecha' => $fecha,
                    'turno_id' => null,
                    'motivo' => null,
                ]);
            })
            ->schema([
                Hidden::make('fecha')->required(),
                Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        Placeholder::make('colaborador')
                            ->label('Colaborador')
                            ->content(fn (): string => $this->colaborador?->nombre_completo ?? '—'),
                        Placeholder::make('fecha_resumen')
                            ->label('Fecha')
                            ->content(fn (Get $get): string => Carbon::parse((string) $get('fecha'))->format('d/m/Y')),
                    ]),
                Select::make('turno_id')
                    ->label('Turno aplicado')
                    ->options(fn (): array => Turno::query()
                        ->where('activo', true)
                        ->orderBy('hora_inicio')
                        ->get()
                        ->mapWithKeys(fn (Turno $turno): array => [$turno->id => $turno->nombre . ' · ' . $turno->rangoHorario()])
                        ->all())
                    ->native()
                    ->required(),
                Textarea::make('motivo')
                    ->label('Motivo de regularización')
                    ->rows(3)
                    ->minLength(10)
                    ->maxLength(200)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->regularizarJornada($data);
            });
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
        $this->dispatch('control-jornadas-ir-a-hoy');
    }

    /** Abre el modal solo después de fijar una jornada excepcional válida. */
    public function abrirRegularizacionJornada(string $fecha): void
    {
        if (! $this->puedeRegularizarJornada($fecha)) {
            Notification::make()
                ->title('La jornada ya no está disponible para regularizar')
                ->danger()
                ->send();

            return;
        }

        $this->fechaRegularizacion = $fecha;
        $this->mountAction('regularizarJornada');
    }

    /** @param array{turno_id:mixed,motivo:mixed} $data */
    public function regularizarJornada(array $data): void
    {
        $colaborador = $this->colaborador;
        $fecha = filled($data['fecha'] ?? null) ? (string) $data['fecha'] : null;

        if (! $colaborador || ! $fecha || ! $this->puedeRegularizarJornada($fecha)) {
            Notification::make()
                ->title('La jornada ya no está disponible para regularizar')
                ->danger()
                ->send();

            return;
        }

        $asignacion = app(RegularizacionJornadaService::class)->regularizar(
            auth()->user(),
            $colaborador,
            $fecha,
            $data,
        );

        Notification::make()
            ->title('Jornada regularizada')
            ->body($asignacion->turno->nombre . ' aplicado a las marcaciones registradas.')
            ->success()
            ->send();

        $this->fechaRegularizacion = null;

    }

    public function puedeRegularizarJornada(string $fecha): bool
    {
        $colaborador = $this->colaborador;
        $usuario = auth()->user();

        if (! $colaborador
            || ! $usuario?->can('Regularizar:Jornada')
            || ! AlcanceSupervisor::puedeGestionarSucursal($usuario, (int) $colaborador->sucursal_id)) {
            return false;
        }

        $dia = Carbon::parse($fecha, config('app.timezone'))->startOfDay();
        if ($dia->isFuture()) {
            return false;
        }

        return ! AsignacionTurno::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('fecha', $dia)
            ->exists()
            && Marcacion::query()
                ->where('colaborador_id', $colaborador->id)
                ->whereNull('turno_id')
                ->whereBetween('fecha_hora', [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()])
                ->exists();
    }

    public function updatedSucursalId(): void
    {
        $this->colaboradorId = null;
        $this->normalizarSeleccion();
    }

    public function updatedColaboradorId(): void
    {
        $this->normalizarSeleccion();
    }

    /** @return Collection<int, Sucursal> */
    public function getSucursalesProperty(): Collection
    {
        return AlcanceSupervisor::sucursalesQuery(auth()->user())
            ->orderBy('nombre')
            ->get();
    }

    /** @return Collection<int, Colaborador> */
    public function getColaboradoresProperty(): Collection
    {
        return $this->colaboradoresPermitidosQuery()
            ->where('activo', true)
            ->with(['area', 'sucursal'])
            ->orderBy('nombre_completo')
            ->get();
    }

    public function getColaboradorProperty(): ?Colaborador
    {
        if (! $this->colaboradorId) {
            return null;
        }

        return $this->colaboradoresPermitidosQuery()
            ->where('activo', true)
            ->with(['area', 'sucursal'])
            ->find($this->colaboradorId);
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
     * @return SupportCollection<int, array{
     *     fecha:Carbon,
     *     asignacion:?AsignacionTurno,
     *     marcaciones:SupportCollection<int, Marcacion>,
     *     jornada:?array<string, mixed>,
     *     refrigerio:?array{inicio:float, fin:float, incidencia:bool}
     * }>
     */
    public function getJornadasProperty(): SupportCollection
    {
        $colaborador = $this->colaborador;

        if (! $colaborador) {
            return collect();
        }

        return app(JornadaCalendarioService::class)->construir($colaborador, $this->mes, $this->dias);
    }

    private function normalizarSeleccion(): void
    {
        if ($this->sucursalId && ! $this->sucursales->contains('id', $this->sucursalId)) {
            $this->sucursalId = null;
        }

        if ($this->colaboradorId && $this->colaboradoresPermitidosQuery()
            ->where('activo', true)
            ->whereKey($this->colaboradorId)
            ->exists()) {
            return;
        }

        $this->colaboradorId = $this->colaboradoresPermitidosQuery()
            ->where('activo', true)
            ->orderBy('nombre_completo')
            ->value('id');
    }

    private function colaboradoresPermitidosQuery(): Builder
    {
        return Colaborador::query()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
            ->when($this->sucursalId, fn (Builder $query): Builder => $query->where('sucursal_id', $this->sucursalId));
    }

}
