<?php

namespace App\Filament\Resources\Turnos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class TurnoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TimePicker::make('hora_inicio')
                    ->label('Hora de inicio')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('hora_fin')
                    ->label('Hora de fin')
                    ->seconds(false)
                    ->required(fn (Get $get): bool => ! (bool) $get('solo_entrada'))
                    ->visible(fn (Get $get): bool => ! (bool) $get('solo_entrada'))
                    ->dehydrated(fn (Get $get): bool => ! (bool) $get('solo_entrada')),
                Toggle::make('cruza_medianoche')
                    ->label('Cruza medianoche')
                    ->default(false)
                    ->visible(fn (Get $get): bool => ! (bool) $get('solo_entrada'))
                    ->dehydrated(fn (Get $get): bool => ! (bool) $get('solo_entrada')),
                Toggle::make('solo_entrada')
                    ->label('Solo entrada')
                    ->live()
                    ->default(false)
                    ->afterStateUpdated(function (Set $set, bool $state): void {
                        if (! $state) {
                            // Al volver a un turno regular, la hora de fin se
                            // solicita al usuario; solo restauramos valores
                            // seguros para que no herede los ceros del modo
                            // "Solo entrada".
                            $set('tolerancia_salida_minutos', 10);
                            $set('incluye_refrigerio', true);
                            $set('refrigerio_minutos', 60);
                            $set('horas_efectivas_objetivo_minutos', 480);

                            return;
                        }

                        $set('hora_fin', null);
                        $set('cruza_medianoche', false);
                        $set('tolerancia_salida_minutos', 0);
                        $set('incluye_refrigerio', false);
                        $set('refrigerio_minutos', 0);
                        $set('horas_efectivas_objetivo_minutos', 0);
                        $set('horas_efectivas_jornada_completa_minutos', null);
                    }),
                TextInput::make('tolerancia_entrada_minutos')
                    ->label('Tolerancia de entrada (min)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(120)
                    ->default(10),
                TextInput::make('tolerancia_salida_minutos')
                    ->label('Tolerancia de salida (min)')
                    ->required(fn (Get $get): bool => ! (bool) $get('solo_entrada'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(120)
                    ->default(10)
                    ->visible(fn (Get $get): bool => ! (bool) $get('solo_entrada'))
                    ->dehydrated(fn (Get $get): bool => ! (bool) $get('solo_entrada')),
                Toggle::make('incluye_refrigerio')
                    ->label('Incluye refrigerio')
                    ->live()
                    ->default(true)
                    ->visible(fn (Get $get): bool => ! (bool) $get('solo_entrada'))
                    ->afterStateUpdated(fn (Set $set, bool $state) => $set('refrigerio_minutos', $state ? 60 : 0)),
                TextInput::make('refrigerio_minutos')
                    ->label('Refrigerio')
                    ->suffix('min')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(180)
                    ->default(60)
                    ->required(fn (Get $get): bool => (bool) $get('incluye_refrigerio'))
                    ->visible(fn (Get $get): bool => ! (bool) $get('solo_entrada') && (bool) $get('incluye_refrigerio')),
                \Filament\Forms\Components\Select::make('horas_efectivas_objetivo_minutos')
                    ->label('Horas efectivas requeridas')
                    ->options(collect(range(1, 16))->mapWithKeys(fn (int $hora): array => [$hora * 60 => $hora . ' h'])->all())
                    ->default(480)
                    ->required(fn (Get $get): bool => ! (bool) $get('solo_entrada'))
                    ->visible(fn (Get $get): bool => ! (bool) $get('solo_entrada')),
                \Filament\Forms\Components\Select::make('horas_efectivas_jornada_completa_minutos')
                    ->label('Objetivo si completa jornada')
                    ->options(collect(range(1, 18))->mapWithKeys(fn (int $hora): array => [$hora * 60 => $hora . ' h'])->all())
                    ->placeholder('No aplica')
                    ->visible(fn (Get $get): bool => ! (bool) $get('solo_entrada')),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
