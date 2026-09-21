<?php

namespace App\Filament\Resources\PuntoVentas\Schemas;

use App\Models\Sucursal;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PuntoVentaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                Select::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn () => Sucursal::query()
                        ->where('tipo', 'tienda')
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id'))
                    ->searchable()
                    ->optionsLimit(8)
                    ->required()
                    ->helperText('Solo se listan sucursales tipo "Tienda"; una planta no tiene puntos de venta.'),
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
