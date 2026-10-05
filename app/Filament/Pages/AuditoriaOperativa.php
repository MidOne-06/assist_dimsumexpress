<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\Colaboradors\ColaboradorResource;
use App\Filament\Resources\IncidenciaMarcacions\IncidenciaMarcacionResource;
use App\Filament\Resources\TurnoOperativos\TurnoOperativoResource;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Models\TurnoOperativo;
use App\Models\VisitaSupervisor;
use App\Services\SchedulerHeartbeat;
use App\Support\AlcanceSupervisor;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Consolida alertas de configuración y operación que impedirían o afectarían
 * la marcación. Es una pantalla diagnóstica: no corrige ni modifica datos.
 */
class AuditoriaOperativa extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Auditoría operativa';

    protected static ?string $title = 'Auditoría operativa';

    protected string $view = 'filament.pages.auditoria-operativa';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:AuditoriaOperativa') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('actualizar')
                ->label('Actualizar')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn (): null => null),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, ?string $search, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->registrosPaginados(
                search: $search,
                page: (int) $page,
                recordsPerPage: $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('nivel')
                    ->label('Nivel')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Atención' ? 'warning' : 'danger'),
                TextColumn::make('hallazgo')
                    ->label('Hallazgo')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('detalle')
                    ->label('Detalle')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('local')
                    ->label('Local')
                    ->placeholder('Sin local asignado')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('revisar')
                    ->label('Revisar')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (array $record): string => $record['url']),
            ])
            ->poll('60s')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Sin hallazgos operativos')
            ->emptyStateDescription('La configuración revisada no presenta bloqueos ni incidencias pendientes.');
    }

    /** @return array{criticos: int, atencion: int, scheduler: string, actualizado: string} */
    public function getResumenProperty(): array
    {
        $hallazgos = $this->hallazgos();
        $heartbeat = app(SchedulerHeartbeat::class);
        $ultimaEjecucion = $heartbeat->latestAt();

        return [
            'criticos' => $hallazgos->where('nivel', 'Crítico')->count(),
            'atencion' => $hallazgos->where('nivel', 'Atención')->count(),
            'scheduler' => $ultimaEjecucion && ! $heartbeat->isStale($ultimaEjecucion) ? 'Activo' : 'Revisar',
            'actualizado' => now()->format('d/m/Y H:i'),
        ];
    }

    private function registrosPaginados(?string $search, int $page, int|string $recordsPerPage): LengthAwarePaginator
    {
        $registros = $this->hallazgos();

        if (filled($search)) {
            $needle = mb_strtolower($search);
            $registros = $registros->filter(fn (array $registro): bool => str_contains(
                mb_strtolower(implode(' ', [$registro['hallazgo'], $registro['detalle'], $registro['local'] ?? ''])),
                $needle,
            ));
        }

        $registros = $registros->values();
        $recordsPerPage = $recordsPerPage === 'all' ? max($registros->count(), 1) : (int) $recordsPerPage;
        $page = max($page, 1);

        return new LengthAwarePaginator(
            $registros->forPage($page, $recordsPerPage)->values(),
            $registros->count(),
            $recordsPerPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    /** @return Collection<int, array{__key: string, nivel: string, hallazgo: string, detalle: string, local: ?string, url: string}> */
    private function hallazgos(): Collection
    {
        $usuario = auth()->user();

        if (! $usuario) {
            return collect();
        }

        $sucursalIds = AlcanceSupervisor::sucursalIds($usuario);
        $hallazgos = collect();

        $mapeos = TurnoOperativo::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', $sucursalIds)
            ->whereHas('turno', fn ($query) => $query->where('activo', true))
            ->get(['id', 'sucursal_id', 'punto_venta_id'])
            ->groupBy('sucursal_id');

        Colaborador::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', $sucursalIds)
            ->with(['sucursal:id,nombre', 'puntoVenta:id,nombre', 'user:id,activo'])
            ->orderBy('nombre_completo')
            ->get()
            ->each(function (Colaborador $colaborador) use ($mapeos, $hallazgos): void {
                $mapeosLocal = $mapeos->get($colaborador->sucursal_id, collect());
                $mapeosEstacion = $colaborador->punto_venta_id
                    ? $mapeosLocal->where('punto_venta_id', $colaborador->punto_venta_id)
                    : collect();
                $mapeosAplicables = $mapeosEstacion->isNotEmpty()
                    ? $mapeosEstacion
                    : $mapeosLocal->whereNull('punto_venta_id');

                if ($mapeosAplicables->isEmpty()) {
                    $local = $colaborador->sucursal?->nombre;
                    $estacion = $colaborador->puntoVenta?->nombre;
                    $hallazgos->push([
                        '__key' => "turno-{$colaborador->id}",
                        'nivel' => 'Crítico',
                        'hallazgo' => 'Sin turno operativo aplicable',
                        'detalle' => trim("{$colaborador->nombre_completo}" . ($estacion ? " · {$estacion}" : '')),
                        'local' => $local,
                        'url' => TurnoOperativoResource::getUrl('index'),
                    ]);
                }

                if (! $colaborador->user?->activo) {
                    $hallazgos->push([
                        '__key' => "acceso-{$colaborador->id}",
                        'nivel' => 'Atención',
                        'hallazgo' => 'Cuenta de acceso inactiva',
                        'detalle' => $colaborador->nombre_completo,
                        'local' => $colaborador->sucursal?->nombre,
                        'url' => ColaboradorResource::getUrl('index', ['search' => $colaborador->nombre_completo]),
                    ]);
                }
            });

        IncidenciaMarcacion::query()
            ->whereNull('resuelta_en')
            ->whereIn('sucursal_id', $sucursalIds)
            ->with(['colaborador:id,nombre_completo', 'sucursal:id,nombre'])
            ->latest('detectada_en')
            ->get()
            ->each(function (IncidenciaMarcacion $incidencia) use ($hallazgos): void {
                $hallazgos->push([
                    '__key' => "incidencia-{$incidencia->id}",
                    'nivel' => 'Atención',
                    'hallazgo' => 'Incidencia de marcación pendiente',
                    'detalle' => trim(implode(' · ', array_filter([
                        $incidencia->colaborador?->nombre_completo,
                        IncidenciaMarcacion::etiquetaTipo($incidencia->tipo),
                    ]))),
                    'local' => $incidencia->sucursal?->nombre,
                    'url' => IncidenciaMarcacionResource::getUrl('index'),
                ]);
            });

        // Una visita abierta de un día anterior nunca se cierra de forma
        // automática: requiere revisión y regularización explícita. Incluimos
        // locales inactivos porque el registro sigue siendo trazable, aunque
        // ya no se permitan operaciones nuevas en ese local.
        if ($usuario->can('Regularizar:VisitaSupervisor')) {
            VisitaSupervisor::query()
                ->where('estado', VisitaSupervisor::EN_CURSO)
                ->whereDate('fecha', '<', today())
                ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIdsHistoricos($usuario))
                ->with(['supervisor:id,name', 'sucursal:id,nombre,activo'])
                ->orderBy('fecha')
                ->get()
                ->each(function (VisitaSupervisor $visita) use ($hallazgos): void {
                    $hallazgos->push([
                        '__key' => "visita-supervisor-pendiente-{$visita->id}",
                        'nivel' => 'Atención',
                        'hallazgo' => 'Visita de supervisión sin salida',
                        'detalle' => trim(implode(' · ', array_filter([
                            $visita->supervisor?->name,
                            $visita->ingreso_en?->format('d/m/Y H:i'),
                        ]))),
                        'local' => ($visita->sucursal?->nombre ?? '—') . ($visita->sucursal?->activo ? '' : ' (inactivo)'),
                        'url' => ControlVisitasSupervisor::getUrl(),
                    ]);
                });
        }

        return $hallazgos;
    }
}
