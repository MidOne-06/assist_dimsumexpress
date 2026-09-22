<?php

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Support\AlcanceSupervisor;
use App\Support\JornadaMarcacion;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HorasEfectivasMensuales extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Horas efectivas';

    protected static ?string $title = 'Horas efectivas mensuales';

    protected string $view = 'filament.pages.horas-efectivas-mensuales';

    public ?int $sucursalId = null;

    public ?int $colaboradorId = null;

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
        $this->sucursalId = $this->sucursalesPermitidas()->value('id');
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

    public function updatedSucursalId(?int $sucursalId): void
    {
        if ($sucursalId && $this->sucursalesPermitidas()->whereKey($sucursalId)->exists()) {
            $this->colaboradorId = null;

            return;
        }

        $this->sucursalId = $this->sucursalesPermitidas()->value('id');
        $this->colaboradorId = null;
    }

    public function updatedColaboradorId(?int $colaboradorId): void
    {
        if (! $colaboradorId || $this->colaboradoresPermitidos()->whereKey($colaboradorId)->exists()) {
            return;
        }

        $this->colaboradorId = null;
    }

    /** @return Collection<int, Sucursal> */
    public function getSucursalesProperty(): Collection
    {
        return $this->sucursalesPermitidas()->get(['id', 'nombre']);
    }

    /** @return Collection<int, Colaborador> */
    public function getColaboradoresProperty(): Collection
    {
        return $this->colaboradoresPermitidos()
            ->orderBy('nombre_completo')
            ->get(['id', 'nombre_completo']);
    }

    /**
     * Solo acumula jornadas que tienen entrada y salida reales. Las jornadas
     * sin cierre no se estiman ni se convierten en horas trabajadas.
     *
     * @return Collection<int, array{colaborador: Colaborador, jornadas_cerradas: int, efectivos_minutos: int, objetivo_minutos: int, diferencia_minutos: int, extras_minutos: int}>
     */
    public function getResumenesProperty(): Collection
    {
        $inicio = Carbon::parse("{$this->mes}-01", config('app.timezone'))->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $asignaciones = AsignacionTurno::query()
            ->with(['colaborador.sucursal', 'turno'])
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn('colaborador_id', $this->colaboradoresPermitidos()->select('id'))
            ->when($this->colaboradorId, fn (Builder $query) => $query->where('colaborador_id', $this->colaboradorId))
            ->orderBy('fecha')
            ->get();

        if ($asignaciones->isEmpty()) {
            return collect();
        }

        $marcacionesPorColaborador = Marcacion::query()
            ->whereIn('colaborador_id', $asignaciones->pluck('colaborador_id')->unique())
            ->whereIn('turno_id', $asignaciones->pluck('turno_id')->unique())
            ->whereBetween('fecha_hora', [
                $inicio->copy()->startOfDay(),
                $fin->copy()->endOfDay()->addMinutes(JornadaMarcacion::MAXIMO_JORNADA_MINUTOS),
            ])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get()
            ->groupBy('colaborador_id');

        $resumenes = collect();

        foreach ($asignaciones as $asignacion) {
            $limites = JornadaMarcacion::limites($asignacion);
            $marcaciones = ($marcacionesPorColaborador->get($asignacion->colaborador_id) ?? collect())
                ->filter(fn (Marcacion $marcacion): bool => $marcacion->turno_id === $asignacion->turno_id
                    && $marcacion->fecha_hora->betweenIncluded($limites['ventana_inicio'], $limites['jornada_fin_maximo']))
                ->values();
            $jornada = JornadaMarcacion::resumen($asignacion->colaborador, $asignacion, $marcaciones);

            if ($jornada['estado'] === 'en_curso') {
                continue;
            }

            $id = $asignacion->colaborador_id;
            $fila = $resumenes->get($id, [
                'colaborador' => $asignacion->colaborador,
                'jornadas_cerradas' => 0,
                'efectivos_minutos' => 0,
                'objetivo_minutos' => 0,
                'diferencia_minutos' => 0,
                'extras_minutos' => 0,
            ]);

            $fila['jornadas_cerradas']++;
            $fila['efectivos_minutos'] += $jornada['efectivos_minutos'];
            $fila['objetivo_minutos'] += $jornada['objetivo_minutos'];
            $fila['diferencia_minutos'] += $jornada['diferencia_minutos'];
            $fila['extras_minutos'] += $jornada['extras_minutos'];
            $resumenes->put($id, $fila);
        }

        return $resumenes->sortBy(fn (array $fila): string => $fila['colaborador']->nombre_completo)->values();
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

    private function colaboradoresPermitidos(): Builder
    {
        return Colaborador::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
            ->when($this->sucursalId, fn (Builder $query) => $query->where('sucursal_id', $this->sucursalId));
    }
}
