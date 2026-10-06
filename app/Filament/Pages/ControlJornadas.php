<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Services\RegularizacionJornadaService;
use App\Support\AlcanceSupervisor;
use App\Support\JornadaMarcacion;
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

    /** La escala solicitada: de 06:00 a 22:00. */
    public const HORA_INICIO_ESCALA = 6 * 60;

    public const HORA_FIN_ESCALA = 22 * 60;

    public ?int $sucursalId = null;

    public ?int $colaboradorId = null;

    public string $mes;

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
     * Filament necesita conocer la acción en la cabecera para renderizar su
     * contenedor nativo de modales. El control permanece fuera de la
     * interacción: la única apertura válida es la del icono de un día con
     * marcaciones excepcionales.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->regularizarJornadaAction()
                ->extraAttributes([
                    'class' => 'hidden',
                    'aria-hidden' => 'true',
                    'tabindex' => '-1',
                ]),
        ];
    }

    /**
     * Acción interna: solo se monta desde abrirRegularizacionJornada(), que
     * aporta la fecha del día excepcional. El montaje sin esa fecha se
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
                $fecha = $action->getArguments()['fecha'] ?? null;

                // Esta acción no tiene botón propio: solo puede montarse por
                // el icono del día excepcional. Cancelar aquí evita que un
                // montaje directo renderice un modal sin contexto, sin
                // interferir con la carga normal de argumentos de Filament.
                if (! filled($fecha) || ! $this->puedeRegularizarJornada((string) $fecha)) {
                    $action->cancel();
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
     *     refrigerio:?array{inicio:int, fin:int, incidencia:bool}
     * }>
     */
    public function getJornadasProperty(): SupportCollection
    {
        $colaborador = $this->colaborador;

        if (! $colaborador) {
            return collect();
        }

        $inicioMes = Carbon::parse("{$this->mes}-01")->startOfDay();
        $finMes = $inicioMes->copy()->endOfMonth()->endOfDay();

        $asignaciones = AsignacionTurno::query()
            ->with('turno')
            ->where('colaborador_id', $colaborador->id)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get()
            ->keyBy(fn (AsignacionTurno $asignacion): string => $asignacion->fecha->toDateString());

        // Las jornadas nocturnas y abiertas pueden terminar después de
        // medianoche. Se carga una ventana ampliada y se vuelve a limitar con
        // JornadaMarcacion para cada turno, evitando consultas por columna.
        $marcaciones = Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereBetween('fecha_hora', [
                $inicioMes->copy()->subDay(),
                $finMes->copy()->addMinutes(JornadaMarcacion::MAXIMO_JORNADA_MINUTOS),
            ])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get();

        return collect($this->dias)->map(function (Carbon $fecha) use ($asignaciones, $marcaciones, $colaborador): array {
            /** @var ?AsignacionTurno $asignacion */
            $asignacion = $asignaciones->get($fecha->toDateString());

            if (! $asignacion) {
                // Una lectura excepcional sin turno operativo detectado no se
                // transforma artificialmente en una jornada. Se muestra en
                // el calendario para auditoría, conservando que no hay un
                // turno con el cual calcular horas efectivas o refrigerio.
                $eventosExcepcionales = $marcaciones
                    ->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === null
                        && $marcacion->fecha_hora->isSameDay($fecha))
                    ->values();

                return [
                    'fecha' => $fecha,
                    'asignacion' => null,
                    'marcaciones' => $eventosExcepcionales,
                    'jornada' => $this->rangoJornada($eventosExcepcionales),
                    'refrigerio' => null,
                ];
            }

            $limites = JornadaMarcacion::limites($asignacion);
            $eventos = $marcaciones
                ->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === $asignacion->turno_id
                    && $marcacion->fecha_hora->betweenIncluded($limites['ventana_inicio'], $limites['jornada_fin_maximo']))
                ->values();

            return [
                'fecha' => $fecha,
                'asignacion' => $asignacion,
                'marcaciones' => $eventos,
                'jornada' => $this->rangoJornada($eventos),
                'refrigerio' => $this->rangoRefrigerio($asignacion, $eventos),
            ];
        });
    }

    public static function iniciales(string $nombre): string
    {
        return collect(preg_split('/\s+/', trim($nombre)))
            ->filter()
            ->take(2)
            ->map(fn (string $parte): string => mb_strtoupper(mb_substr($parte, 0, 1)))
            ->implode('');
    }

    /** Posición vertical dentro de la escala, limitada a su rango visible. */
    public static function porcentajeHora(Carbon $hora): float
    {
        $minutos = ($hora->hour * 60) + $hora->minute + ($hora->second / 60);
        $rango = self::HORA_FIN_ESCALA - self::HORA_INICIO_ESCALA;

        return max(0, min(100, (($minutos - self::HORA_INICIO_ESCALA) / $rango) * 100));
    }

    /** @return array<int, int> */
    public static function horasEscala(): array
    {
        return range(6, 22, 2);
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

    /** @param SupportCollection<int, Marcacion> $marcaciones */
    private function rangoJornada(SupportCollection $marcaciones): ?array
    {
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);

        if (! $entrada) {
            return null;
        }

        $salida = $marcaciones
            ->filter(fn (Marcacion $marcacion): bool => $marcacion->tipo === Marcacion::TIPO_SALIDA)
            ->last();
        $ultimoEvento = $salida ?? $marcaciones->last();

        return [
            'inicio' => self::porcentajeHora($entrada->fecha_hora),
            'fin' => max(self::porcentajeHora($ultimoEvento->fecha_hora), self::porcentajeHora($entrada->fecha_hora) + 0.75),
            'cerrada' => (bool) $salida,
        ];
    }

    /** @param SupportCollection<int, Marcacion> $marcaciones */
    private function rangoRefrigerio(AsignacionTurno $asignacion, SupportCollection $marcaciones): ?array
    {
        if (! $asignacion->turno->incluye_refrigerio) {
            return null;
        }

        $salida = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $regreso = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);

        if (! $salida && ! $regreso) {
            return null;
        }

        $duracion = max(1, (int) $asignacion->turno->refrigerio_minutos);
        $inicio = $salida?->fecha_hora ?? $regreso->fecha_hora->copy()->subMinutes($duracion);
        $fin = $regreso?->fecha_hora ?? $salida->fecha_hora->copy()->addMinutes($duracion);

        return [
            'inicio' => self::porcentajeHora($inicio),
            'fin' => max(self::porcentajeHora($fin), self::porcentajeHora($inicio) + 0.75),
            'incidencia' => ! $salida || ! $regreso,
        ];
    }
}
