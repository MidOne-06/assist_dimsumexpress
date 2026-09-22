<?php

namespace App\Filament\Resources\Sucursals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
