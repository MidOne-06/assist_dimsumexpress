<?php

namespace App\Filament\Resources\AsignacionTurnos\Schemas;

use App\Models\Colaborador;
use App\Models\Turno;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AsignacionTurnoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('colaborador_id')
                    ->label('Colaborador')
                    ->options(fn () => Colaborador::query()
                        ->where('activo', true)
                        ->orderBy('nombre_completo')
                        ->pluck('nombre_completo', 'id'))
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('turno_id')
                    ->label('Turno')
                    ->options(fn () => Turno::query()
                        ->where('activo', true)
                        ->orderBy('hora_inicio')
                        ->pluck('nombre', 'id'))
                    ->required(),
                DatePicker::make('fecha')
                    ->label('Fecha')
                    ->required()
                    ->native(false)
                    ->unique(
                        table: 'asignaciones_turno',
                        column: 'fecha',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('colaborador_id', $get('colaborador_id')),
                    )
                    ->validationMessages([
                        'unique' => 'Este colaborador ya tiene un turno asignado en esa fecha.',
                    ])
                    ->helperText('Un colaborador solo puede tener un turno asignado por día.'),
                TextInput::make('observacion')
                    ->label('Observación')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
