<?php

namespace App\Filament\Resources\CoberturaOperativas;

use App\Filament\Resources\CoberturaOperativas\Pages\ListCoberturaOperativas;
use App\Models\CoberturaOperativa;
use App\Models\Sucursal;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
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
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()));
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
                TextColumn::make('asignacionTurno.fecha')
                    ->label('Fecha de turno')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('asignacionTurno.turno.nombre')
                    ->label('Turno')
                    ->placeholder('—'),
                TextColumn::make('sucursal.nombre')
                    ->label('Sucursal')
                    ->sortable(),
                TextColumn::make('puntoVenta.nombre')
                    ->label('Punto de venta')
                    ->placeholder('—'),
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
                TextColumn::make('observacion_revision')
                    ->label('Observación')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('detectada_en', 'desc')
            ->filters([
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn (): array => Sucursal::query()
                        ->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))
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
            ])
            ->recordActions([
                Action::make('revisar')
                    ->label('Revisar')
                    ->color('success')
                    ->visible(fn (CoberturaOperativa $record): bool => $record->estado === CoberturaOperativa::ESTADO_PENDIENTE)
                    ->authorize(fn (CoberturaOperativa $record): bool => auth()->user()->can('Revisar:CoberturaOperativa')
                        && AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $record->sucursal_id))
                    ->modalHeading('Revisar cobertura')
                    ->modalWidth(Width::Large)
                    ->modalSubmitActionLabel('Guardar')
                    ->schema([
                        Select::make('estado')
                            ->label('Resultado')
                            ->options([
                                CoberturaOperativa::ESTADO_REVISADA => 'Conforme',
                                CoberturaOperativa::ESTADO_OBSERVADA => 'Observada',
                            ])
                            ->required(),
                        Textarea::make('observacion_revision')
                            ->label('Observación')
                            ->rows(3)
                            ->maxLength(2000),
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

    public static function getPages(): array
    {
        return [
            'index' => ListCoberturaOperativas::route('/'),
        ];
    }
}
