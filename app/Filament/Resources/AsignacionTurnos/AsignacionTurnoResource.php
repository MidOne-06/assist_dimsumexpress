<?php

namespace App\Filament\Resources\AsignacionTurnos;

use App\Filament\Resources\AsignacionTurnos\Pages\CreateAsignacionTurno;
use App\Filament\Resources\AsignacionTurnos\Pages\EditAsignacionTurno;
use App\Filament\Resources\AsignacionTurnos\Pages\ListAsignacionTurnos;
use App\Filament\Resources\AsignacionTurnos\Schemas\AsignacionTurnoForm;
use App\Filament\Resources\AsignacionTurnos\Tables\AsignacionTurnosTable;
use App\Models\AsignacionTurno;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AsignacionTurnoResource extends Resource
{
    protected static ?string $model = AsignacionTurno::class;

    protected static ?string $slug = 'asignaciones-turno';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Gestión de personal';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Asignaciones (detalle)';

    protected static ?string $modelLabel = 'asignación de turno';

    protected static ?string $pluralModelLabel = 'asignaciones de turno';

    public static function form(Schema $schema): Schema
    {
        return AsignacionTurnoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AsignacionTurnosTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('colaborador', fn (Builder $query): Builder => $query->whereIn(
                'sucursal_id',
                AlcanceSupervisor::sucursalIds(auth()->user()),
            ));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAsignacionTurnos::route('/'),
            'create' => CreateAsignacionTurno::route('/create'),
            'edit' => EditAsignacionTurno::route('/{record}/edit'),
        ];
    }
}
