<?php

namespace App\Filament\Resources\Sucursals\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SucursalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Select::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'tienda' => 'Tienda',
                        'planta' => 'Planta',
                    ])
                    ->required()
                    ->default('tienda')
                    ->helperText('Una "planta" es una sucursal de producción: no tiene puntos de venta.'),
                Textarea::make('direccion')
                    ->label('Dirección')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('activo')
                    ->label('Activa')
                    ->default(true)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
