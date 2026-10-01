<?php

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Turno;
use App\Services\AsignacionMasivaTurnosService;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
use Illuminate\Validation\ValidationException;

class AsignarTurnos extends Page implements HasTable
{
    // Shield -- sin esto, `View:AsignarTurnos` (generado por shield:generate)
    // nunca se llegaba a evaluar de verdad: quedaba como fila huérfana en
    // `permissions`, y cualquier usuario con algún rol podía entrar a esta
    // página sin importar sus permisos reales (hallazgo de la auditoría de
    // "toda acción como permiso", 2026-09-18).
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Gestión de personal';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Asignación masiva';

    protected static ?string $title = 'Asignación masiva de turnos';

    protected string $view = 'filament.pages.asignar-turnos';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->proximasAsignacionesQuery())
            ->heading('Próximas asignaciones')
            ->columns([
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('colaborador.nombre_completo')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('colaborador.sucursal.nombre')
                    ->label('Local')
                    ->description(fn (AsignacionTurno $record): ?string => $record->colaborador?->puntoVenta?->nombre)
                    ->toggleable(),
                TextColumn::make('turno.nombre')
                    ->label('Turno')
                    ->badge(),
                TextColumn::make('turno.hora_inicio')
                    ->label('Inicio')
                    ->time('H:i'),
                TextColumn::make('turno.hora_fin')
                    ->label('Fin')
                    ->formatStateUsing(fn (?string $state, AsignacionTurno $record): string => $record->turno?->solo_entrada
                        ? '—'
                        : ($record->turno?->jornada_abierta ? 'Sin horario' : ($state ? \Carbon\Carbon::parse($state)->format('H:i') : '—'))),
                TextColumn::make('asignadoPor.name')
                    ->label('Asignado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde')->native(false),
                        DatePicker::make('hasta')->label('Hasta')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $subquery, string $fecha): Builder => $subquery->whereDate('fecha', '>=', $fecha))
                        ->when($data['hasta'] ?? null, fn (Builder $subquery, string $fecha): Builder => $subquery->whereDate('fecha', '<=', $fecha))),
                SelectFilter::make('sucursal_id')
                    ->label('Local')
                    ->options(fn (): array => AlcanceSupervisor::sucursalesQuery(auth()->user())
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $subquery, int $sucursalId): Builder => $subquery->whereHas(
                            'colaborador',
                            fn (Builder $colaboradores): Builder => $colaboradores->where('sucursal_id', $sucursalId),
                        ),
                    )),
                SelectFilter::make('turno_id')
                    ->label('Turno')
                    ->options(fn (): array => Turno::query()
                        ->where('activo', true)
                        ->orderBy('hora_inicio')
                        ->pluck('nombre', 'id')
                        ->all()),
            ])
            ->filtersFormColumns(2)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->defaultSort('fecha')
            ->emptyStateHeading('Sin asignaciones próximas')
            ->recordActions([
                $this->editarAsignacionAction(),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar asignación')
                    ->visible(fn (AsignacionTurno $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ]);
    }

    private function proximasAsignacionesQuery(): Builder
    {
        return AsignacionTurno::query()
            ->with(['colaborador.sucursal', 'colaborador.puntoVenta', 'turno', 'asignadoPor'])
            ->whereDate('fecha', '>=', today())
            ->whereHas('colaborador', fn (Builder $query): Builder => $query->whereIn(
                'sucursal_id',
                AlcanceSupervisor::sucursalIds(auth()->user()),
            ));
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('asignarPorRango')
                ->label('Asignar turnos')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible(fn (): bool => auth()->user()?->can('AsignarMasivo:AsignarTurnos') ?? false)
                ->modalHeading('Asignar turnos por rango')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel('Guardar asignaciones')
                ->closeModalByClickingAway(false)
                ->schema($this->schemaAsignacionMasiva())
                ->action(function (array $data): void {
                    $this->asignar($data);
                }),
        ];
    }

    /** @return array<int, Section> */
    private function schemaAsignacionMasiva(): array
    {
        return [
                Section::make()
                    ->compact()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('sucursal_id')
                            ->label('Local')
                            ->options(fn (): array => AlcanceSupervisor::sucursalesQuery(auth()->user())
                                ->pluck('nombre', 'id')
                                ->all())
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('colaborador_ids', []))
                            ->columnSpan(1),
                        Select::make('turno_id')
                            ->label('Turno')
                            ->options(fn (): array => Turno::query()
                                ->where('activo', true)
                                ->orderBy('hora_inicio')
                                ->pluck('nombre', 'id')
                                ->all())
                            ->required()
                            ->columnSpan(1),
                        DatePicker::make('fecha_inicio')
                            ->label('Desde')
                            ->required()
                            ->native(false)
                            ->minDate(today())
                            ->default(today())
                            ->columnSpan(1),
                        DatePicker::make('fecha_fin')
                            ->label('Hasta')
                            ->required()
                            ->native(false)
                            ->minDate(today())
                            ->default(today())
                            ->columnSpan(1),
                        Select::make('colaborador_ids')
                            ->label('Colaboradores')
                            ->options(fn (Get $get): array => filled($get('sucursal_id')) ? Colaborador::query()
                                ->where('sucursal_id', $get('sucursal_id'))
                                ->where('activo', true)
                                ->orderBy('nombre_completo')
                                ->get()
                                ->mapWithKeys(fn (Colaborador $c) => [$c->id => $c->nombre_completo])
                                ->all() : [])
                            ->multiple()
                            ->searchable()
                            ->optionsLimit(8)
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('sucursal_id')))
                            ->columnSpanFull(),
                        CheckboxList::make('dias_semana')
                            ->label('Días')
                            ->options([
                                '1' => 'Lunes',
                                '2' => 'Martes',
                                '3' => 'Miércoles',
                                '4' => 'Jueves',
                                '5' => 'Viernes',
                                '6' => 'Sábado',
                                '7' => 'Domingo',
                            ])
                            ->default(['1', '2', '3', '4', '5', '6', '7'])
                            ->columns(['default' => 2, 'md' => 4])
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('observacion')
                            ->label('Observación')
                            ->rows(2)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function asignar(array $data): void
    {
        $resultado = app(AsignacionMasivaTurnosService::class)->asignar(auth()->user(), $data);

        Notification::make()
            ->title('Asignación completada')
            ->body("{$resultado['creadas']} asignaciones creadas y {$resultado['actualizadas']} actualizadas.")
            ->success()
            ->send();
    }

    private function editarAsignacionAction(): Action
    {
        return Action::make('editar')
            ->label('Editar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('Actualizar asignación')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar cambios')
            ->visible(fn (AsignacionTurno $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->fillForm(fn (AsignacionTurno $record): array => [
                'turno_id' => $record->turno_id,
                'fecha' => $record->fecha->toDateString(),
                'observacion' => $record->observacion,
            ])
            ->schema([
                Section::make()
                    ->compact()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
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
            ->action(function (AsignacionTurno $record, array $data): void {
                abort_unless(auth()->user()?->can('update', $record), 403);

                if (! Turno::query()->whereKey($data['turno_id'])->where('activo', true)->exists()) {
                    throw ValidationException::withMessages(['turno_id' => 'Selecciona un turno activo.']);
                }

                $fecha = \Illuminate\Support\Carbon::parse($data['fecha'])->startOfDay();

                if ($fecha->lte(today())) {
                    throw ValidationException::withMessages(['fecha' => 'Solo se pueden modificar asignaciones futuras.']);
                }

                if (AsignacionTurno::query()
                    ->where('colaborador_id', $record->colaborador_id)
                    ->whereDate('fecha', $fecha)
                    ->whereKeyNot($record->id)
                    ->exists()) {
                    throw ValidationException::withMessages(['fecha' => 'Este colaborador ya tiene un turno asignado en esa fecha.']);
                }

                $record->update([
                    'turno_id' => $data['turno_id'],
                    'fecha' => $fecha,
                    'observacion' => filled($data['observacion'] ?? null) ? trim((string) $data['observacion']) : null,
                    'asignado_por' => auth()->id(),
                ]);
            });
    }
}
