<?php

namespace App\Filament\Resources\Colaboradors\Tables;

use App\Models\Colaborador;
use App\Models\Sucursal;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ColaboradorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('documento_identidad')
                    ->label('Documento')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('sucursal.nombre')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('puntoVenta.nombre')
                    ->label('Punto de venta')
                    ->placeholder('—'),
                TextColumn::make('cargo')
                    ->label('Cargo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('fecha_ingreso')
                    ->label('Ingreso')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre_completo')
            ->filters([
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn () => Sucursal::query()->orderBy('nombre')->pluck('nombre', 'id')),
                TernaryFilter::make('activo')
                    ->label('Activo'),
            ])
            ->recordActions([
                Action::make('cambiarEstado')
                    ->label(fn (Colaborador $record): string => $record->activo ? 'Dar de baja' : 'Reactivar')
                    ->icon(fn (Colaborador $record): Heroicon => $record->activo ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (Colaborador $record): string => $record->activo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Colaborador $record): string => $record->activo ? 'Dar de baja a colaborador' : 'Reactivar colaborador')
                    ->modalDescription(fn (Colaborador $record): string => $record->activo
                        ? "{$record->nombre_completo} no podrá volver a iniciar sesión. Su historial se conservará."
                        : "{$record->nombre_completo} podrá volver a iniciar sesión con su contraseña actual.")
                    ->modalSubmitActionLabel(fn (Colaborador $record): string => $record->activo ? 'Dar de baja' : 'Reactivar')
                    ->authorize(fn (Colaborador $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Colaborador $record): void {
                        if ($record->activo) {
                            $record->desactivarAcceso();
                        } else {
                            $record->reactivarAcceso();
                        }

                        Notification::make()
                            ->title($record->activo ? 'Colaborador reactivado' : 'Colaborador dado de baja')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
