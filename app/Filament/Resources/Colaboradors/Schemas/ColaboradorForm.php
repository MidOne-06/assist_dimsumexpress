<?php

namespace App\Filament\Resources\Colaboradors\Schemas;

use App\Models\Area;
use App\Models\Empresa;
use App\Models\PuntoVenta;
use App\Support\AlcanceSupervisor;
use App\Support\PoliticaContrasena;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ColaboradorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cuenta de acceso')
                    ->columnSpanFull()
                    ->compact()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'email', ignorable: fn (?Model $record) => $record?->user)
                            ->columnSpanFull(),
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->minLength(PoliticaContrasena::MINIMO_CARACTERES)
                            ->rules([PoliticaContrasena::regla()])
                            ->confirmed()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state)),
                        TextInput::make('password_confirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' || filled($get('password')))
                            ->dehydrated(false),
                    ]),

                Section::make('Datos del colaborador')
                    ->columnSpanFull()
                    ->compact()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('nombre_completo')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('documento_identidad')
                            ->label('Documento de identidad')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        Select::make('empresa_id')
                            ->label('Empresa')
                            ->options(fn (): array => Empresa::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('area_id')
                            ->label('Área')
                            ->options(fn (): array => Area::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('cargo')
                            ->label('Cargo')
                            ->maxLength(255),
                        Select::make('sucursal_id')
                            ->label('Sucursal')
                            ->options(fn (): array => auth()->user()
                                ? AlcanceSupervisor::sucursalesQuery(auth()->user())->pluck('nombre', 'id')->all()
                                : [])
                            ->searchable()
                            ->optionsLimit(8)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('punto_venta_id', null)),
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
                            ->optionsLimit(8),
                        DatePicker::make('fecha_ingreso')
                            ->label('Fecha de ingreso')
                            ->default(now())
                            ->native(false),
                        Toggle::make('activo')
                            ->label('Activo')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

}
