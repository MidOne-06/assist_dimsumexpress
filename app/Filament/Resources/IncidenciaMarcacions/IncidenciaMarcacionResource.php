<?php

namespace App\Filament\Resources\IncidenciaMarcacions;

use App\Filament\Resources\IncidenciaMarcacions\Pages\ListIncidenciaMarcacions;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IncidenciaMarcacionResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = IncidenciaMarcacion::class;

    protected static ?string $slug = 'incidencias-marcacion';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Incidencias de marcación';

    protected static ?string $modelLabel = 'incidencia de marcación';

    protected static ?string $pluralModelLabel = 'incidencias de marcación';

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
        $sucursalIds = AlcanceSupervisor::sucursalIds(auth()->user());

        return parent::getEloquentQuery()
            ->where(function (Builder $query) use ($sucursalIds): void {
                $query
                    ->whereIn('sucursal_id', $sucursalIds)
                    ->orWhere(function (Builder $legacy) use ($sucursalIds): void {
                        $legacy
                            ->whereNull('sucursal_id')
                            ->whereHas('colaborador', fn (Builder $colaborador) => $colaborador->whereIn('sucursal_id', $sucursalIds));
                    });
            })
            ->with(['colaborador', 'asignacionTurno.turno', 'sucursal', 'puntoVenta', 'resueltaPor']);
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
                TextColumn::make('tipo')
                    ->label('Incidencia')
                    ->formatStateUsing(fn (string $state): string => IncidenciaMarcacion::etiquetaTipo($state))
                    ->badge()
                    ->color(fn (string $state): string => $state === IncidenciaMarcacion::TIPO_RETORNO_REFRIGERIO_PENDIENTE ? 'warning' : 'danger'),
                TextColumn::make('asignacionTurno.fecha')
                    ->label('Fecha de turno')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('asignacionTurno.turno.nombre')
                    ->label('Turno')
                    ->placeholder('—'),
                TextColumn::make('sucursal.nombre')
                    ->label('Local')
                    ->description(fn (IncidenciaMarcacion $record): ?string => $record->puntoVenta?->nombre)
                    ->sortable(),
                IconColumn::make('resuelta_en')
                    ->label('Resuelta')
                    ->boolean()
                    ->getStateUsing(fn (IncidenciaMarcacion $record): bool => ! $record->estaPendiente()),
                TextColumn::make('resuelta_en')
                    ->label('Resuelta el')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('Pendiente')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('resueltaPor.name')
                    ->label('Resuelta por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('detectada_en', 'desc')
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Incidencia')
                    ->options([
                        IncidenciaMarcacion::TIPO_RETORNO_REFRIGERIO_PENDIENTE => 'Retorno de refrigerio pendiente',
                        IncidenciaMarcacion::TIPO_SALIDA_TURNO_PENDIENTE => 'Salida de turno pendiente',
                        IncidenciaMarcacion::TIPO_MARCACION_OMITIDA => 'Marcación omitida reportada',
                        IncidenciaMarcacion::TIPO_SECUENCIA_INCONSISTENTE => 'Secuencia inconsistente',
                    ]),
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(['pendiente' => 'Pendiente', 'resuelta' => 'Resuelta'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'pendiente' => $query->whereNull('resuelta_en'),
                        'resuelta' => $query->whereNotNull('resuelta_en'),
                        default => $query,
                    }),
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
                        ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
                        ->where('activo', true)
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all()),
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
            ->emptyStateHeading('Sin incidencias')
            ->recordActions([
                Action::make('detalle')
                    ->label('Detalle')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->button()
                    ->authorize(fn (IncidenciaMarcacion $record): bool => auth()->user()->can('View:IncidenciaMarcacion')
                        && self::puedeGestionarIncidencia($record))
                    ->modalHeading('Detalle de incidencia')
                    ->modalWidth(Width::Large)
                    ->schema(fn (IncidenciaMarcacion $record): array => self::detalleSchema(
                        $record->loadMissing(['colaborador', 'asignacionTurno.turno', 'sucursal', 'puntoVenta', 'resueltaPor'])
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('resolver')
                    ->label('Resolver')
                    ->color('success')
                    ->visible(fn (IncidenciaMarcacion $record): bool => $record->estaPendiente() && auth()->user()->can('Resolver:IncidenciaMarcacion'))
                    ->authorize(fn (IncidenciaMarcacion $record): bool => $record->estaPendiente()
                        && auth()->user()->can('Resolver:IncidenciaMarcacion')
                        && self::puedeGestionarIncidencia($record))
                    ->modalHeading('Resolver incidencia')
                    ->modalWidth(Width::Medium)
                    ->modalSubmitActionLabel('Guardar resolución')
                    ->modalCancelActionLabel('Cancelar')
                    ->schema([
                        Textarea::make('observacion_resolucion')
                            ->label('Motivo y medida adoptada')
                            ->required()
                            ->minLength(5)
                            ->maxLength(2000)
                            ->rows(4),
                    ])
                    ->action(fn (IncidenciaMarcacion $record, array $data) => $record->update([
                        'resuelta_en' => now(),
                        'resuelta_por_id' => auth()->id(),
                        'observacion_resolucion' => $data['observacion_resolucion'],
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
            ->where(function (Builder $query) use ($sucursalIds): void {
                $query->whereIn('sucursal_id', $sucursalIds)
                    ->orWhereIn('id', IncidenciaMarcacion::query()
                        ->whereIn('sucursal_id', $sucursalIds)
                        ->select('colaborador_id'));
            })
            ->orderBy('nombre_completo')
            ->pluck('nombre_completo', 'id')
            ->all();
    }

    private static function puedeGestionarIncidencia(IncidenciaMarcacion $record): bool
    {
        $sucursalId = $record->sucursal_id ?? $record->colaborador?->sucursal_id;

        return $sucursalId !== null
            && AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $sucursalId);
    }

    /** @return array<Section> */
    private static function detalleSchema(IncidenciaMarcacion $record): array
    {
        return [
            Section::make()
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('estado')
                        ->label('Estado')
                        ->state($record->estaPendiente() ? 'Pendiente' : 'Resuelta')
                        ->badge()
                        ->color($record->estaPendiente() ? 'warning' : 'success'),
                    TextEntry::make('incidencia')
                        ->label('Incidencia')
                        ->state(IncidenciaMarcacion::etiquetaTipo($record->tipo))
                        ->badge()
                        ->color($record->tipo === IncidenciaMarcacion::TIPO_RETORNO_REFRIGERIO_PENDIENTE ? 'warning' : 'danger'),
                    TextEntry::make('detectada_en')
                        ->label('Detectada')
                        ->state($record->detectada_en)
                        ->dateTime('d/m/Y H:i:s'),
                ]),
            Section::make('Jornada')
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
            Section::make('Estación')
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
            Section::make('Reporte')
                ->compact()
                ->visible(filled($record->observacion_reporte))
                ->schema([
                    TextEntry::make('observacion_reporte')
                        ->label('Observación')
                        ->state($record->observacion_reporte)
                        ->wrap(),
                ]),
            Section::make('Resolución')
                ->compact()
                ->visible(! $record->estaPendiente())
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('resuelta_en')
                        ->label('Resuelta')
                        ->state($record->resuelta_en)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('resuelta_por')
                        ->label('Resuelta por')
                        ->state($record->resueltaPor?->name ?? '—'),
                    TextEntry::make('observacion_resolucion')
                        ->label('Motivo y medida adoptada')
                        ->state($record->observacion_resolucion ?? '—')
                        ->wrap()
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncidenciaMarcacions::route('/'),
        ];
    }
}
