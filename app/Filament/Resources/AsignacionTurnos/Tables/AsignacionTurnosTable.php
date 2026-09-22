<?php

namespace App\Filament\Resources\AsignacionTurnos\Tables;

use App\Models\Sucursal;
use App\Models\Turno;
use App\Support\AlcanceSupervisor;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AsignacionTurnosTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                    ->label('Sucursal')
                    ->toggleable(),
                TextColumn::make('turno.nombre')
                    ->label('Turno')
                    ->badge(),
                TextColumn::make('turno.hora_inicio')
                    ->label('Inicio')
                    ->time('H:i'),
                TextColumn::make('turno.hora_fin')
                    ->label('Fin')
                    ->time('H:i'),
                TextColumn::make('observacion')
                    ->label('Observación')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('asignadoPor.name')
                    ->label('Asignado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                Filter::make('fecha')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('desde')->native(false),
                        \Filament\Forms\Components\DatePicker::make('hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $date) => $q->whereDate('fecha', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $date) => $q->whereDate('fecha', '<=', $date));
                    }),
                SelectFilter::make('turno_id')
                    ->label('Turno')
                    ->options(fn () => Turno::query()->orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('sucursal')
                    ->label('Sucursal')
                    ->options(fn () => Sucursal::query()
                        ->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $sucursalId) => $q->whereHas('colaborador', fn (Builder $q2) => $q2->where('sucursal_id', $sucursalId))
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
