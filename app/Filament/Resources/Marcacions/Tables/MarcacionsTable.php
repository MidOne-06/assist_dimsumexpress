<?php

namespace App\Filament\Resources\Marcacions\Tables;

use App\Models\Area;
use App\Models\Empresa;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Support\AlcanceSupervisor;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarcacionsTable
{
    public static function configure(Table $table): Table
    {
        $sucursalIds = AlcanceSupervisor::sucursalIds(auth()->user());

        return $table
            ->columns([
                TextColumn::make('fecha_hora')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn (Marcacion $record): string => $record->turno?->nombre ?? 'Sin turno')
                    ->sortable(),
                TextColumn::make('colaborador.nombre_completo')
                    ->label('Colaborador')
                    ->searchable()
                    ->description(function (Marcacion $record): ?string {
                        return collect([
                            $record->empresa?->nombre,
                            $record->area?->nombre,
                        ])->filter()->join(' · ') ?: null;
                    })
                    ->sortable(),
                TextColumn::make('empresa.nombre')
                    ->label('Empresa')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('area.nombre')
                    ->label('Área')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'entrada' => 'Entrada',
                        'salida' => 'Salida',
                        'salida_refrigerio' => 'Salida a refrigerio',
                        'regreso_refrigerio' => 'Regreso de refrigerio',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'entrada' => 'success',
                        'salida' => 'danger',
                        'salida_refrigerio' => 'warning',
                        'regreso_refrigerio' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('retorno_refrigerio')
                    ->label('Retorno de refrigerio')
                    ->getStateUsing(fn (Marcacion $record): string => $record->resumenRetornoRefrigerio()['etiqueta'] ?? '—')
                    ->badge()
                    ->color(function (Marcacion $record): string {
                        return match ($record->resumenRetornoRefrigerio()['estado'] ?? null) {
                            'puntual' => 'success',
                            'temprano' => 'warning',
                            'tarde' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->tooltip(function (Marcacion $record): ?string {
                        $resumen = $record->resumenRetornoRefrigerio();

                        return $resumen ? 'Retorno esperado: ' . $resumen['esperado']->format('d/m/Y H:i:s') : null;
                    }),
                TextColumn::make('sucursal.nombre')
                    ->label('Local')
                    ->description(fn (Marcacion $record): ?string => $record->puntoVenta?->nombre)
                    ->sortable(),
                TextColumn::make('ip_origen')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('fecha_hora', 'desc')
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'entrada' => 'Entrada',
                        'salida' => 'Salida',
                        'salida_refrigerio' => 'Salida a refrigerio',
                        'regreso_refrigerio' => 'Regreso de refrigerio',
                    ]),
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn (): array => Sucursal::query()
                        ->whereIn('id', $sucursalIds)
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('punto_venta_id')
                    ->label('Punto de venta')
                    ->options(fn (): array => MarcacionFilterOptions::puntosVenta($sucursalIds))
                    ->searchable(),
                SelectFilter::make('colaborador_id')
                    ->label('Colaborador')
                    ->options(fn (): array => MarcacionFilterOptions::colaboradores($sucursalIds))
                    ->searchable(),
                SelectFilter::make('turno_concepto')
                    ->label('Turno')
                    ->options(fn (): array => MarcacionFilterOptions::turnos($sucursalIds))
                    ->query(fn (Builder $query, array $data): Builder => MarcacionFilterOptions::aplicarTurno(
                        $query,
                        $data['value'] ?? null,
                    ))
                    ->searchable(),
                SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->options(fn (): array => Empresa::query()
                        ->whereIn('id', Marcacion::query()->whereIn('sucursal_id', $sucursalIds)->whereNotNull('empresa_id')->distinct()->pluck('empresa_id'))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all()),
                SelectFilter::make('area_id')
                    ->label('Área')
                    ->options(fn (): array => Area::query()
                        ->whereIn('id', Marcacion::query()->whereIn('sucursal_id', $sucursalIds)->whereNotNull('area_id')->distinct()->pluck('area_id'))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all()),
                Filter::make('fecha')
                    ->default([
                        'desde' => now()->toDateString(),
                        'hasta' => now()->toDateString(),
                    ])
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('desde')->label('Desde')->native(false),
                        \Filament\Forms\Components\DatePicker::make('hasta')->label('Hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_hora', '>=', $fecha))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_hora', '<=', $fecha));
                    }),
            ])
            ->filtersFormColumns(4)
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->persistFiltersInSession()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin marcaciones')
            ->recordActions([
                Action::make('jornada')
                    ->label('Jornada')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->button()
                    ->authorize(fn (): bool => auth()->user()->can('View:Marcacion'))
                    ->modalHeading('Control de jornada')
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema(fn (Marcacion $record): array => MarcacionInfolistSchemas::jornada(
                        $record->loadMissing(['colaborador', 'turno', 'sucursal', 'puntoVenta', 'coberturaOperativa'])
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('trazabilidad')
                    ->label('Trazabilidad')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->button()
                    ->authorize(fn (): bool => auth()->user()->can('View:Marcacion'))
                    ->modalHeading('Trazabilidad de marcación')
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema(fn (Marcacion $record): array => MarcacionInfolistSchemas::trazabilidad(
                        $record->loadMissing(['colaborador', 'turno', 'sucursal', 'puntoVenta', 'qrToken', 'empresa', 'area', 'coberturaOperativa'])
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->toolbarActions([]);
    }

}
