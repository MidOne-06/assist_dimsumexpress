<?php

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

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

    public string $mes;

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
        if (! $this->sucursalId) {
            return new Collection();
        }

        return Colaborador::query()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
            ->where('sucursal_id', $this->sucursalId)
            ->where('activo', true)
            ->orderBy('nombre_completo')
            ->get();
    }

    public function getTurnosActivosProperty(): Collection
    {
        $colaboradores = $this->colaboradores;

        if ($colaboradores->isEmpty()) {
            return new Collection();
        }

        $inicio = Carbon::parse("{$this->mes}-01")->toDateString();
        $fin = Carbon::parse("{$this->mes}-01")->endOfMonth()->toDateString();

        // Un supervisor no necesita ver filas de turnos que solo existen en
        // otros locales. La grilla muestra exclusivamente turnos asignados a
        // colaboradores dentro de su alcance y en el mes que está consultando.
        return Turno::query()
            ->where('activo', true)
            ->whereIn('id', AsignacionTurno::query()
                ->whereIn('colaborador_id', $colaboradores->pluck('id'))
                ->whereBetween('fecha', [$inicio, $fin])
                ->select('turno_id'))
            ->orderBy('hora_inicio')
            ->get();
    }

    private function sucursalesPermitidas(): \Illuminate\Database\Eloquent\Builder
    {
        return AlcanceSupervisor::sucursalesQuery(auth()->user());
    }

    /**
     * Filas = turnos (identidad fija) en vez de colaboradores, porque un
     * colaborador puede rotar de turno día a día -- anclar las filas por
     * colaborador hacía que la tabla se viera "inestable" de un mes a otro.
     * Cada celda agrupa los colaboradores que trabajan ese turno ese día.
     *
     * @return array<int, array<string, Collection<int, AsignacionTurno>>>
     */
    public function getMapaPorTurnoProperty(): array
    {
        $colaboradores = $this->colaboradores;

        if ($colaboradores->isEmpty()) {
            return [];
        }

        $inicio = Carbon::parse("{$this->mes}-01")->toDateString();
        $fin = Carbon::parse("{$this->mes}-01")->endOfMonth()->toDateString();

        $asignaciones = AsignacionTurno::query()
            ->whereIn('colaborador_id', $colaboradores->pluck('id'))
            ->whereBetween('fecha', [$inicio, $fin])
            ->with(['colaborador', 'turno'])
            ->get();

        $mapa = [];

        foreach ($asignaciones as $asignacion) {
            $fecha = $asignacion->fecha->toDateString();
            $mapa[$asignacion->turno_id][$fecha] ??= new Collection();
            $mapa[$asignacion->turno_id][$fecha]->push($asignacion);
        }

        return $mapa;
    }

    /**
     * Marcaciones reales de tipo "entrada" del mes, indexadas por
     * colaborador+fecha, para no hacer una consulta por celda al calcular si
     * cada asignación se cumplió a tiempo.
     *
     * @return array<int, array<string, Marcacion>>
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
                // Si por algún motivo hay más de una "entrada" el mismo día,
                // se queda con la primera (orderBy fecha_hora asc arriba).
                $mapa[$marcacion->colaborador_id][$marcacion->fecha_hora->toDateString()] ??= $marcacion;
            });

        return $mapa;
    }

    /**
     * Cruza lo planificado (AsignacionTurno) con lo realmente marcado
     * (Marcacion tipo entrada) usando la tolerancia de entrada configurada
     * en el turno, para saber si el turno se cumplió, llegó tarde, o faltó.
     *
     * @return array{estado: string, label: string, hora: ?string}
     */
    public function estadoAsignacion(AsignacionTurno $asignacion): array
    {
        $entrada = $this->entradas[$asignacion->colaborador_id][$asignacion->fecha->toDateString()] ?? null;
        $turno = $asignacion->turno;

        $limite = Carbon::parse($asignacion->fecha->toDateString() . ' ' . $turno->hora_inicio)
            ->addMinutes($turno->tolerancia_entrada_minutos);

        if ($entrada) {
            if ($entrada->fecha_hora->lte($limite)) {
                return ['estado' => 'a_tiempo', 'label' => 'A tiempo', 'hora' => $entrada->fecha_hora->format('H:i')];
            }

            $minutosTarde = $limite->diffInMinutes($entrada->fecha_hora);

            return ['estado' => 'tardanza', 'label' => "Tardanza de {$minutosTarde} min", 'hora' => $entrada->fecha_hora->format('H:i')];
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

    /**
     * "Ana Torres Quispe" -> "Ana T." -- para que quepa en una celda angosta;
     * el nombre completo siempre está disponible en el tooltip.
     */
    public static function abreviarNombre(string $nombreCompleto): string
    {
        $partes = preg_split('/\s+/', trim($nombreCompleto));

        if (count($partes) < 2) {
            return $nombreCompleto;
        }

        return "{$partes[0]} " . mb_substr($partes[1], 0, 1) . '.';
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
     * Ícono y color del estado de asistencia, para pintar un indicador
     * pequeño sobre el nombre del colaborador en la celda.
     */
    public static function iconoEstado(string $estado): ?string
    {
        return match ($estado) {
            'a_tiempo' => 'heroicon-s-check-circle',
            'tardanza' => 'heroicon-s-exclamation-triangle',
            'falta' => 'heroicon-s-x-circle',
            default => null, // pendiente: aún no corresponde marcar, sin ícono
        };
    }

    public static function colorEstado(string $estado): string
    {
        return match ($estado) {
            'a_tiempo' => '#16a34a',
            'tardanza' => '#f59e0b',
            'falta' => '#dc2626',
            default => '#9ca3af',
        };
    }

    /**
     * El peor estado entre varios colaboradores del mismo turno/día, para
     * decidir si el badge resumen (">2 colaboradores") debe avisar de algo.
     *
     * @param  Collection<int, AsignacionTurno>  $asignaciones
     */
    public function peorEstado(\Illuminate\Support\Collection $asignaciones): string
    {
        $prioridad = ['falta' => 3, 'tardanza' => 2, 'pendiente' => 1, 'a_tiempo' => 0];

        return $asignaciones
            ->map(fn (AsignacionTurno $a) => $this->estadoAsignacion($a)['estado'])
            ->sortByDesc(fn (string $estado) => $prioridad[$estado] ?? 0)
            ->first() ?? 'pendiente';
    }

    /**
     * Color determinístico por turno para que el mismo turno siempre pinte
     * igual en toda la grilla, sin necesitar que el usuario elija un color.
     *
     * Se usan valores hexadecimales reales (no clases Tailwind) porque el
     * CSS que Filament v5 distribuye es un build propio (Tailwind v4) que
     * solo contiene las clases que sus propios componentes usan -- clases
     * utilitarias arbitrarias como "bg-emerald-100" no existen en ese
     * bundle y no pintarían nada en el navegador.
     *
     * @return array{bg: string, text: string}
     */
    public static function colorParaTurno(int $turnoId): array
    {
        $paleta = [
            ['bg' => '#22c55e', 'text' => '#ffffff'], // verde
            ['bg' => '#3b82f6', 'text' => '#ffffff'], // azul
            ['bg' => '#f97316', 'text' => '#ffffff'], // naranja
            ['bg' => '#a855f7', 'text' => '#ffffff'], // púrpura
            ['bg' => '#ef4444', 'text' => '#ffffff'], // rojo
            ['bg' => '#06b6d4', 'text' => '#ffffff'], // cian
            ['bg' => '#eab308', 'text' => '#1f2937'], // amarillo (texto oscuro por contraste)
            ['bg' => '#84cc16', 'text' => '#1f2937'], // lima (texto oscuro por contraste)
        ];

        return $paleta[$turnoId % count($paleta)];
    }
}
