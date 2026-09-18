<?php

namespace App\Filament\Resources\PuntoVentas\Tables;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PuntoVentasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sucursal.nombre')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nombre')
            ->filters([
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn () => Sucursal::query()->where('tipo', 'tienda')->orderBy('nombre')->pluck('nombre', 'id')),
                TernaryFilter::make('activo')
                    ->label('Activo'),
            ])
            ->recordActions([
                Action::make('enlace')
                    ->label('Ver enlace')
                    ->icon(Heroicon::OutlinedLink)
                    ->color('gray')
                    // Ver SucursalsTable.php: mismo permiso propio, distinto
                    // de ViewAny/View:PuntoVenta, con ->authorize() (revisado
                    // siempre) además de ->visible() (solo oculta el botón).
                    ->visible(fn () => auth()->user()->can('VerEnlace:PuntoVenta'))
                    ->authorize(fn () => auth()->user()->can('VerEnlace:PuntoVenta'))
                    ->modalHeading('Enlace de la estación')
                    ->modalContent(fn (PuntoVenta $record) => view('filament.actions.enlace-estacion', ['url' => $record->enlaceEstacion()]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
