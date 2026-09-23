<?php

namespace App\Filament\Resources\Colaboradors\Schemas;

use App\Models\Area;
use App\Models\Empresa;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

class ColaboradorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cuenta de acceso')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'email', ignorable: fn (?Model $record) => $record?->user),
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->minLength(12)
                            ->rules([Password::min(12)->mixedCase()->numbers()->symbols()])
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state)),
                    ]),

                Section::make('Datos del colaborador')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 12])
                    ->schema([
                        TextInput::make('nombre_completo')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 'full', 'md' => 2, 'xl' => 6]),
                        TextInput::make('documento_identidad')
                            ->label('Documento de identidad')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(['default' => 'full', 'md' => 1, 'xl' => 3]),
                        Select::make('empresa_id')
                            ->label('Empresa')
                            ->options(fn (): array => Empresa::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(['default' => 'full', 'md' => 1, 'xl' => 6]),
                        Select::make('area_id')
                            ->label('Área')
                            ->options(fn (): array => Area::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(['default' => 'full', 'md' => 1, 'xl' => 6]),
                        TextInput::make('cargo')
                            ->label('Cargo')
                            ->maxLength(255)
                            ->columnSpan(['default' => 'full', 'md' => 2, 'xl' => 4]),
                        Select::make('sucursal_id')
                            ->label('Sucursal')
                            ->options(fn () => Sucursal::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->optionsLimit(8)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('punto_venta_id', null))
                            ->columnSpan(['default' => 'full', 'md' => 1, 'xl' => 4]),
                        Select::make('punto_venta_id')
                            ->label('Punto de venta')
                            ->visible(function (Get $get): bool {
                                if (! $get('sucursal_id')) {
                                    return false;
                                }

                                return PuntoVenta::query()
                                    ->where('sucursal_id', $get('sucursal_id'))
                                    ->where('activo', true)
                                    ->exists();
                            })
                            ->options(function (Get $get) {
                                if (! $get('sucursal_id')) {
                                    return [];
                                }

                                return PuntoVenta::query()
                                    ->where('sucursal_id', $get('sucursal_id'))
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id');
                            })
                            ->searchable()
                            ->optionsLimit(8)
                            ->columnSpan(['default' => 'full', 'md' => 1, 'xl' => 4]),
                        DatePicker::make('fecha_ingreso')
                            ->label('Fecha de ingreso')
                            ->default(now())
                            ->native(false)
                            ->columnSpan(['default' => 'full', 'md' => 1, 'xl' => 2]),
                        Toggle::make('activo')
                            ->label('Activo')
                            ->default(true)
                            ->required()
                            ->columnSpan(['default' => 'full', 'md' => 1, 'xl' => 2]),
                    ]),
            ]);
    }

}
