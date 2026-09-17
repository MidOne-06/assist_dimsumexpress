<?php

namespace App\Filament\Resources\Marcacions\Tables;

use App\Models\Sucursal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarcacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_hora')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('colaborador.nombre_completo')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('sucursal.nombre')
                    ->label('Sucursal')
                    ->sortable(),
                TextColumn::make('puntoVenta.nombre')
                    ->label('Punto de venta')
                    ->placeholder('—'),
                TextColumn::make('turno.nombre')
                    ->label('Turno')
                    ->placeholder('—'),
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
                    ->options(fn () => Sucursal::query()->orderBy('nombre')->pluck('nombre', 'id')),
                Filter::make('fecha')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('desde')->native(false),
                        \Filament\Forms\Components\DatePicker::make('hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_hora', '>=', $fecha))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_hora', '<=', $fecha));
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
