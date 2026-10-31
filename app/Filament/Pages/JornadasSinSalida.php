<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Services\JornadasSinSalidaService;
use App\Services\RegularizacionSalidaService;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JornadasSinSalida extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Jornadas sin salida';

    protected static ?string $title = 'Jornadas pendientes de regularizar salida';

    protected string $view = 'filament.pages.jornadas-sin-salida';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->can('View:ControlJornadas')
            || $user?->can('Regularizar:Jornada')
            || false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $conteo = cache()->remember('badge_jornadas_sin_salida_'.$user->id, 60, function () use ($user): int {
            return app(JornadasSinSalidaService::class)->conteo(
                now()->subDays(14),
                now(),
                now(),
                $user,
            );
        });

        return $conteo > 0 ? (string) $conteo : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->jornadasSinSalidaQuery())
            ->columns([
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('colaborador.nombre_completo')
                    ->label('Colaborador')
                    ->description(fn (AsignacionTurno $record): ?string => collect([
                        $record->colaborador->cargo,
                        $record->colaborador->sucursal?->nombre,
                    ])->filter()->implode(' · ') ?: null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sucursal')
                    ->label('Local asignado')
                    ->getStateUsing(fn (AsignacionTurno $record): string => $record->colaborador->sucursal?->nombre ?? '—'),
                TextColumn::make('turno.nombre')
                    ->label('Turno')
                    ->description(fn (AsignacionTurno $record): string => $record->turno ? $record->turno->rangoHorario() : '—')
                    ->sortable(),
                TextColumn::make('entrada')
                    ->label('Ingreso')
                    ->getStateUsing(function (AsignacionTurno $record): string {
                        $entrada = $record->entradaMarcacion();

                        return $entrada ? $entrada->fecha_hora->format('d/m H:i') : '—';
                    }),
                TextColumn::make('ultima_marca')
                    ->label('Última marca')
                    ->getStateUsing(function (AsignacionTurno $record): string {
                        $ultima = $record->ultimaMarcacion();
                        if (! $ultima) {
                            return '—';
                        }

                        $tipo = match ($ultima->tipo) {
                            Marcacion::TIPO_ENTRADA => 'Entrada',
                            Marcacion::TIPO_SALIDA_REFRIGERIO => 'Salida refrigerio',
                            Marcacion::TIPO_REGRESO_REFRIGERIO => 'Retorno refrigerio',
                            Marcacion::TIPO_SALIDA => 'Salida',
                            default => $ultima->tipo,
                        };

                        return $tipo.' '.$ultima->fecha_hora->format('d/m H:i');
                    }),
                TextColumn::make('vencio_en')
                    ->label('Venció el')
                    ->getStateUsing(fn (AsignacionTurno $record): string => $record->vencioEn()->format('d/m H:i')),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->default('Sin salida')
                    ->badge()
                    ->color('danger'),
            ])
            ->filters([
                Filter::make('periodo')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->default(now()->subDays(14)->toDateString())
                            ->native(false),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->default(now()->toDateString())
                            ->native(false),
                    ]),
                SelectFilter::make('sucursal_id')
                    ->label('Local')
                    ->options(fn (): array => Sucursal::query()
                        ->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('colaborador_id')
                    ->label('Colaborador')
                    ->options(fn (): array => Colaborador::query()
                        ->where('activo', true)
                        ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
                        ->orderBy('nombre_completo')
                        ->pluck('nombre_completo', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->actions([
                $this->regularizarAction(),
            ])
            ->emptyStateHeading('No hay jornadas sin salida pendientes')
            ->emptyStateDescription('Todas las jornadas del periodo seleccionado cuentan con su salida registrada o están en curso.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }

    private function regularizarAction(): Action
    {
        return Action::make('regularizar')
            ->label('Regularizar salida')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('warning')
            ->modalHeading('Regularizar salida de turno')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar salida y consolidar')
            ->modalCancelActionLabel('Cancelar')
            ->schema(fn (AsignacionTurno $record): array => [
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Placeholder::make('colaborador')
                        ->label('Colaborador')
                        ->content($record->colaborador->nombre_completo),
                    Placeholder::make('turno')
                        ->label('Turno')
                        ->content($record->turno ? ($record->turno->nombre.' · '.$record->turno->rangoHorario()) : '—'),
                    Placeholder::make('ingreso')
                        ->label('Ingreso registrado')
                        ->content($record->entradaMarcacion()?->fecha_hora?->format('d/m/Y H:i') ?? '—'),
                ]),
                DateTimePicker::make('salida_en')
                    ->label('Fecha y hora de salida')
                    ->seconds(false)
                    ->native(false)
                    ->required()
                    ->default($record->salidaSugerida())
                    ->minDate($record->entradaMarcacion()?->fecha_hora)
                    ->maxDate(now()),
                Select::make('motivo_sugerido')
                    ->label('Sugerencia de motivo')
                    ->placeholder('Selecciona un motivo común o escribe abajo...')
                    ->options([
                        'Colaborador omitió escanear salida al término de su turno' => 'Omitió escanear al término de turno',
                        'Falla técnica o desconexión en punto de venta' => 'Falla técnica o desconexión',
                        'Retiro autorizado previamente por supervisor' => 'Retiro autorizado por supervisor',
                        'Emergencia personal / salud justificada' => 'Emergencia personal / salud',
                    ])
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => filled($state) ? $set('motivo', $state) : null),
                Textarea::make('motivo')
                    ->label('Motivo de regularización (obligatorio)')
                    ->required()
                    ->minLength(5)
                    ->maxLength(500)
                    ->rows(3)
                    ->placeholder('Explica el motivo por el cual se regulariza la salida...'),
            ])
            ->action(function (AsignacionTurno $record, array $data): void {
                $usuario = auth()->user();
                abort_unless($usuario !== null, 403);

                app(RegularizacionSalidaService::class)->regularizar(
                    $record,
                    $data,
                    $usuario,
                    request()->ip(),
                    request()->userAgent(),
                );

                cache()->forget('badge_jornadas_sin_salida_'.$usuario->id);

                Notification::make()
                    ->title('Salida regularizada exitosamente')
                    ->body('La marcación fue registrada y la jornada se ha consolidado.')
                    ->success()
                    ->send();
            });
    }

    private function jornadasSinSalidaQuery(): Builder
    {
        $data = $this->tableFilters ?? [];
        $desde = filled($data['periodo']['desde'] ?? null) ? Carbon::parse($data['periodo']['desde']) : now()->subDays(14);
        $hasta = filled($data['periodo']['hasta'] ?? null) ? Carbon::parse($data['periodo']['hasta']) : now();
        $sucursalId = filled($data['sucursal_id']['value'] ?? null) ? (int) $data['sucursal_id']['value'] : null;
        $colaboradorId = filled($data['colaborador_id']['value'] ?? null) ? (int) $data['colaborador_id']['value'] : null;

        $ids = app(JornadasSinSalidaService::class)->listarIds(
            $desde,
            $hasta,
            now(),
            auth()->user(),
            $sucursalId,
            $colaboradorId,
        );

        return AsignacionTurno::query()
            ->whereIn('id', $ids)
            ->with(['turno', 'colaborador.sucursal', 'colaborador.puntoVenta'])
            ->orderByDesc('fecha');
    }
}
