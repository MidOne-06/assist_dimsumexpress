<?php

namespace App\Filament\Resources\Turnos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TurnoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TimePicker::make('hora_inicio')
                    ->label('Hora de inicio')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('hora_fin')
                    ->label('Hora de fin')
                    ->seconds(false)
                    ->required(),
                Toggle::make('cruza_medianoche')
                    ->label('Cruza medianoche')
                    ->helperText('Actívalo para turnos nocturnos donde la hora de fin es al día siguiente (ej. 22:00 - 06:00).')
                    ->default(false)
                    ->required(),
                TextInput::make('tolerancia_entrada_minutos')
                    ->label('Tolerancia de entrada (min)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(120)
                    ->default(10),
                TextInput::make('tolerancia_salida_minutos')
                    ->label('Tolerancia de salida (min)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(120)
                    ->default(10),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->required(),
            ]);
    }
}
