<?php

namespace App\Filament\Resources\PuntoVentas\Tables;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Services\PuntoVentaService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
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
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'caja' => 'Caja',
                        'produccion' => 'Producción',
                        'oficina' => 'Oficina',
                        'almacen' => 'Almacén',
                        default => 'Otro',
                    }),
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
                    ->options(fn () => Sucursal::query()->orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(['caja' => 'Caja', 'produccion' => 'Producción', 'oficina' => 'Oficina', 'almacen' => 'Almacén', 'otro' => 'Otro']),
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
                EditAction::make()
                    ->modal()
                    ->modalHeading('Actualizar punto de marcado')
                    ->modalWidth(Width::Large)
                    ->using(fn (PuntoVenta $record, array $data) => app(PuntoVentaService::class)
                        ->actualizar(auth()->user(), $record, $data)),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->toolbarActions([]);
    }
}
