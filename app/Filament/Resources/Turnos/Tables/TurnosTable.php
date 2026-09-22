<?php

namespace App\Filament\Resources\Turnos\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TurnosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('hora_inicio')
                    ->label('Inicio')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('hora_fin')
                    ->label('Fin')
                    ->time('H:i')
                    ->sortable(),
                IconColumn::make('cruza_medianoche')
                    ->label('Nocturno')
                    ->boolean(),
                TextColumn::make('tolerancia_entrada_minutos')
                    ->label('Toler. entrada')
                    ->suffix(' min')
                    ->alignCenter(),
                TextColumn::make('tolerancia_salida_minutos')
                    ->label('Toler. salida')
                    ->suffix(' min')
                    ->alignCenter(),
                TextColumn::make('refrigerio')
                    ->label('Refrigerio')
                    ->state('1 h')
                    ->alignCenter(),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('hora_inicio')
            ->filters([
                TernaryFilter::make('activo')
                    ->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
