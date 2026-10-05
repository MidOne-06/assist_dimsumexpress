<?php

namespace App\Filament\Resources\TurnoOperativos;

use App\Filament\Resources\TurnoOperativos\Pages\ListTurnoOperativos;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\TurnoOperativo;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TurnoOperativoResource extends Resource
{
    protected static ?string $model = TurnoOperativo::class;
    protected static ?string $slug = 'turnos-operativos';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static string|\UnitEnum|null $navigationGroup = 'Organización';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Turnos por estación';
    protected static ?string $modelLabel = 'turno operativo';
    protected static ?string $pluralModelLabel = 'turnos operativos';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default'=>1,'md'=>2])->components([
            Select::make('sucursal_id')->label('Local')->options(Sucursal::query()->where('activo',true)->orderBy('nombre')->pluck('nombre','id')->all())->searchable()->live()->required(),
            Select::make('punto_venta_id')->label('Caja / estación')->options(fn (Get $get): array => filled($get('sucursal_id')) ? PuntoVenta::query()->where('sucursal_id',$get('sucursal_id'))->where('activo',true)->orderBy('nombre')->pluck('nombre','id')->all() : [])->searchable()->placeholder('Hereda los turnos del local'),
            Select::make('turno_id')->label('Turno')->options(Turno::query()->where('activo',true)->orderBy('hora_inicio')->pluck('nombre','id')->all())->searchable()->required(),
            TextInput::make('prioridad')->numeric()->minValue(1)->maxValue(999)->default(100)->helperText('Menor número gana solo si dos rangos empatan.'),
            Toggle::make('activo')->label('Detección automática activa')->default(true)->helperText('Solo puede existir una regla activa por turno en cada local o caja.')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sucursal.nombre')->label('Local')->searchable()->sortable(),
            TextColumn::make('puntoVenta.nombre')->label('Caja / estación')->placeholder('Todo el local'),
            TextColumn::make('turno.nombre')->label('Turno')->description(fn (TurnoOperativo $r): string => $r->turno->rangoHorario()),
            TextColumn::make('prioridad')->alignCenter(),
            IconColumn::make('activo')->label('Vigente')->boolean(),
        ])->defaultSort('sucursal_id')->filters([
            TernaryFilter::make('activo')
                ->label('Vigencia')
                ->placeholder('Todos')
                ->trueLabel('Vigentes')
                ->falseLabel('Históricos')
                ->default(true),
        ])->recordActions([
            \Filament\Actions\EditAction::make()
                ->visible(fn (TurnoOperativo $record): bool => $record->activo)
                ->modal(),
        ]);
    }
    public static function getPages(): array { return ['index'=>ListTurnoOperativos::route('/')]; }
}
