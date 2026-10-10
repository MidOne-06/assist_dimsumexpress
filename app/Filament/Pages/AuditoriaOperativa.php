<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\AuditoriaOperativaService;
use App\Services\SchedulerHeartbeat;
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

        return app(AuditoriaOperativaService::class)->hallazgos($usuario);
    }
}
