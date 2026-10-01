<?php

namespace App\Filament\Resources\Sucursals\Tables;

use Filament\Actions\EditAction;
use App\Models\Sucursal;
use App\Services\SucursalService;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\Width;

class SucursalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'planta' ? 'Planta' : 'Tienda')
                    ->color(fn (string $state) => $state === 'planta' ? 'warning' : 'success'),
                TextColumn::make('direccion')
                    ->label('Dirección')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('puntos_venta_count')
                    ->label('Puntos de venta')
                    ->counts('puntosVenta')
                    ->alignCenter(),
                TextColumn::make('colaboradores_count')
                    ->label('Colaboradores')
                    ->counts('colaboradores')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('marcaciones_count')
                    ->label('Marcaciones')
                    ->counts('marcaciones')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('activo')
                    ->label('Activa')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nombre')
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'tienda' => 'Tienda',
                        'planta' => 'Planta',
                    ]),
                TernaryFilter::make('activo')
                    ->label('Activa'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modal()
                    ->modalHeading('Actualizar sucursal')
                    ->modalWidth(Width::Large)
                    ->using(fn (Sucursal $record, array $data) => app(SucursalService::class)
                        ->actualizar(auth()->user(), $record, $data)),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->toolbarActions([]);
    }
}
