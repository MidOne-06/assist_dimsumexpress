<?php

namespace App\Filament\Resources\Colaboradors\Schemas;

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
                    ->description('Con este correo y contraseña el colaborador se loguea desde su celular para marcar asistencia.')
                    ->columns(2)
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
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Déjalo en blanco para no cambiar la contraseña actual.' : null),
                    ]),

                Section::make('Datos del colaborador')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre_completo')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('documento_identidad')
                            ->label('Documento de identidad')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('cargo')
                            ->label('Cargo')
                            ->maxLength(255),
                        Select::make('sucursal_id')
                            ->label('Sucursal')
                            ->options(fn () => Sucursal::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->optionsLimit(8)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('punto_venta_id', null)),
                        Select::make('punto_venta_id')
                            ->label('Punto de venta')
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
                            ->visible(fn (Get $get) => static::sucursalEsTienda($get('sucursal_id')))
                            ->required(fn (Get $get) => static::sucursalEsTienda($get('sucursal_id')))
                            ->dehydrated(fn (Get $get) => static::sucursalEsTienda($get('sucursal_id'))),
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

    protected static function sucursalEsTienda(?int $sucursalId): bool
    {
        if (! $sucursalId) {
            return false;
        }

        return Sucursal::query()->whereKey($sucursalId)->where('tipo', 'tienda')->exists();
    }
}
