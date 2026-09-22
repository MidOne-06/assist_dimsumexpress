<?php

namespace App\Filament\Resources\IncidenciaMarcacions;

use App\Filament\Resources\IncidenciaMarcacions\Pages\ListIncidenciaMarcacions;
use App\Models\IncidenciaMarcacion;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
        return parent::getEloquentQuery()
            ->whereHas('colaborador', fn (Builder $query) => $query->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user())));
    }

    public static function table(Table $table): Table
    {
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
                TextColumn::make('colaborador.sucursal.nombre')
                    ->label('Local')
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
                    ]),
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(['pendiente' => 'Pendiente', 'resuelta' => 'Resuelta'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'pendiente' => $query->whereNull('resuelta_en'),
                        'resuelta' => $query->whereNotNull('resuelta_en'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('resolver')
                    ->label('Resolver')
                    ->color('success')
                    ->visible(fn (IncidenciaMarcacion $record): bool => $record->estaPendiente() && auth()->user()->can('Resolver:IncidenciaMarcacion'))
                    ->authorize(fn (IncidenciaMarcacion $record): bool => $record->estaPendiente()
                        && auth()->user()->can('Resolver:IncidenciaMarcacion')
                        && AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $record->colaborador->sucursal_id))
                    ->modalHeading('Resolver incidencia')
                    ->modalWidth(Width::Large)
                    ->modalSubmitActionLabel('Guardar resolución')
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

    public static function getPages(): array
    {
        return [
            'index' => ListIncidenciaMarcacions::route('/'),
        ];
    }
}
