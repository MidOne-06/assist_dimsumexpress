<?php

namespace App\Filament\Resources\CoberturaOperativas;

use App\Filament\Resources\CoberturaOperativas\Pages\ListCoberturaOperativas;
use App\Models\Colaborador;
use App\Models\CoberturaOperativa;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoberturaOperativaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = CoberturaOperativa::class;

    protected static ?string $slug = 'coberturas-operativas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Coberturas operativas';

    protected static ?string $modelLabel = 'cobertura operativa';

    protected static ?string $pluralModelLabel = 'coberturas operativas';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /** @return array<int, string> */
    public static function getPermissionPrefixes(): array
    {
        return ['ViewAny', 'View'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
            ->with(['colaborador', 'asignacionTurno.turno', 'sucursal', 'puntoVenta', 'revisadaPor']);
    }

    public static function table(Table $table): Table
    {
        $sucursalIds = AlcanceSupervisor::sucursalIds(auth()->user());

        return $table
            ->columns([
                TextColumn::make('detectada_en')
                    ->label('Detectada')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('colaborador.nombre_completo')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('asignacionTurno.fecha')
                    ->label('Fecha de turno')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('asignacionTurno.turno.nombre')
                    ->label('Turno')
                    ->placeholder('—'),
                TextColumn::make('sucursal.nombre')
                    ->label('Local')
                    ->description(fn (CoberturaOperativa $record): ?string => $record->puntoVenta?->nombre)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        CoberturaOperativa::ESTADO_PENDIENTE => 'Pendiente',
                        CoberturaOperativa::ESTADO_REVISADA => 'Revisada',
                        CoberturaOperativa::ESTADO_OBSERVADA => 'Observada',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CoberturaOperativa::ESTADO_REVISADA => 'success',
                        CoberturaOperativa::ESTADO_OBSERVADA => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('revisadaPor.name')
                    ->label('Revisada por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('revisada_en')
                    ->label('Revisada el')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('Pendiente')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('observacion_revision')
                    ->label('Observación')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('detectada_en', 'desc')
            ->filters([
                SelectFilter::make('colaborador_id')
                    ->label('Colaborador')
                    ->options(fn (): array => self::opcionesColaborador($sucursalIds))
                    ->searchable(),
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn (): array => Sucursal::query()
                        ->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all()),
                SelectFilter::make('punto_venta_id')
                    ->label('Punto de venta')
                    ->options(fn (): array => PuntoVenta::query()
                        ->whereIn('id', CoberturaOperativa::query()
                            ->whereIn('sucursal_id', $sucursalIds)
                            ->whereNotNull('punto_venta_id')
                            ->distinct()
                            ->pluck('punto_venta_id'))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all()),
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        CoberturaOperativa::ESTADO_PENDIENTE => 'Pendiente',
                        CoberturaOperativa::ESTADO_REVISADA => 'Revisada',
                        CoberturaOperativa::ESTADO_OBSERVADA => 'Observada',
                    ]),
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde')->native(false),
                        DatePicker::make('hasta')->label('Hasta')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, string $fecha): Builder => $q->whereDate('detectada_en', '>=', $fecha))
                        ->when($data['hasta'] ?? null, fn (Builder $q, string $fecha): Builder => $q->whereDate('detectada_en', '<=', $fecha))),
            ])
            ->filtersFormColumns(4)
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->persistFiltersInSession()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin coberturas')
            ->recordActions([
                Action::make('detalle')
                    ->label('Detalle')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->button()
                    ->authorize(fn (CoberturaOperativa $record): bool => auth()->user()->can('View:CoberturaOperativa')
                        && AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $record->sucursal_id))
                    ->modalHeading('Detalle de cobertura')
                    ->modalWidth(Width::Large)
                    ->schema(fn (CoberturaOperativa $record): array => self::detalleSchema(
                        $record->loadMissing(['colaborador', 'asignacionTurno.turno', 'sucursal', 'puntoVenta', 'revisadaPor'])
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('revisar')
                    ->label('Revisar')
                    ->color('success')
                    ->visible(fn (CoberturaOperativa $record): bool => $record->estado === CoberturaOperativa::ESTADO_PENDIENTE
                        && auth()->user()->can('Revisar:CoberturaOperativa')
                        && AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $record->sucursal_id))
                    ->authorize(fn (CoberturaOperativa $record): bool => auth()->user()->can('Revisar:CoberturaOperativa')
                        && AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $record->sucursal_id))
                    ->modalHeading('Revisar cobertura')
                    ->modalWidth(Width::Large)
                    ->modalSubmitActionLabel('Guardar')
                    ->modalCancelActionLabel('Cancelar')
                    ->schema([
                        Select::make('estado')
                            ->label('Resultado')
                            ->options([
                                CoberturaOperativa::ESTADO_REVISADA => 'Conforme',
                                CoberturaOperativa::ESTADO_OBSERVADA => 'Observada',
                            ])
                            ->native(false)
                            ->live()
                            ->required(),
                        Textarea::make('observacion_revision')
                            ->label('Observación')
                            ->rows(3)
                            ->maxLength(2000)
                            ->required(fn (Get $get): bool => $get('estado') === CoberturaOperativa::ESTADO_OBSERVADA)
                            ->columnSpanFull(),
                    ])
                    ->action(fn (CoberturaOperativa $record, array $data) => $record->update([
                        'estado' => $data['estado'],
                        'observacion_revision' => $data['observacion_revision'] ?? null,
                        'revisada_en' => now(),
                        'revisada_por_id' => auth()->id(),
                    ])),
            ])
            ->toolbarActions([]);
    }

    /** @param array<int, int> $sucursalIds
     *  @return array<int, string>
     */
    private static function opcionesColaborador(array $sucursalIds): array
    {
        return Colaborador::query()
            ->whereIn('id', CoberturaOperativa::query()
                ->whereIn('sucursal_id', $sucursalIds)
                ->select('colaborador_id'))
            ->orderBy('nombre_completo')
            ->pluck('nombre_completo', 'id')
            ->all();
    }

    /** @return array<Section> */
    private static function detalleSchema(CoberturaOperativa $record): array
    {
        return [
            Section::make()
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('estado')
                        ->label('Estado')
                        ->state(match ($record->estado) {
                            CoberturaOperativa::ESTADO_REVISADA => 'Revisada',
                            CoberturaOperativa::ESTADO_OBSERVADA => 'Observada',
                            default => 'Pendiente',
                        })
                        ->badge()
                        ->color(match ($record->estado) {
                            CoberturaOperativa::ESTADO_REVISADA => 'success',
                            CoberturaOperativa::ESTADO_OBSERVADA => 'danger',
                            default => 'warning',
                        }),
                    TextEntry::make('detectada_en')
                        ->label('Detectada')
                        ->state($record->detectada_en)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('origen')
                        ->label('Origen')
                        ->state($record->origen === CoberturaOperativa::ORIGEN_AUTOMATICA ? 'Automática' : $record->origen),
                ]),
            Section::make('Colaborador y turno')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('colaborador')
                        ->label('Colaborador')
                        ->state($record->colaborador?->nombre_completo ?? '—'),
                    TextEntry::make('fecha_turno')
                        ->label('Fecha de turno')
                        ->state($record->asignacionTurno?->fecha)
                        ->date('d/m/Y')
                        ->placeholder('—'),
                    TextEntry::make('turno')
                        ->label('Turno')
                        ->state($record->asignacionTurno?->turno?->nombre ?? '—'),
                ]),
            Section::make('Estación marcada')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('local')
                        ->label('Local')
                        ->state($record->sucursal?->nombre ?? '—'),
                    TextEntry::make('punto_venta')
                        ->label('Punto de venta')
                        ->state($record->puntoVenta?->nombre ?? '—'),
                ]),
            Section::make('Revisión')
                ->compact()
                ->visible($record->estado !== CoberturaOperativa::ESTADO_PENDIENTE)
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('revisada_en')
                        ->label('Revisada')
                        ->state($record->revisada_en)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('revisada_por')
                        ->label('Revisada por')
                        ->state($record->revisadaPor?->name ?? '—'),
                    TextEntry::make('observacion_revision')
                        ->label('Observación')
                        ->state($record->observacion_revision ?? '—')
                        ->wrap()
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoberturaOperativas::route('/'),
        ];
    }
}
