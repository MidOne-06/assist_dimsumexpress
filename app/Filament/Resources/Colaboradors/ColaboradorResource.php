<?php

namespace App\Filament\Resources\Colaboradors;

use App\Filament\Resources\Colaboradors\Pages\CreateColaborador;
use App\Filament\Resources\Colaboradors\Pages\EditColaborador;
use App\Filament\Resources\Colaboradors\Pages\ListColaboradors;
use App\Filament\Resources\Colaboradors\Schemas\ColaboradorForm;
use App\Filament\Resources\Colaboradors\Tables\ColaboradorsTable;
use App\Models\Colaborador;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Support\AlcanceSupervisor;

class ColaboradorResource extends Resource
{
    protected static ?string $model = Colaborador::class;

    protected static ?string $slug = 'colaboradores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Gestión de personal';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'colaborador';

    protected static ?string $pluralModelLabel = 'colaboradores';

    protected static ?string $recordTitleAttribute = 'nombre_completo';

    public static function form(Schema $schema): Schema
    {
        return ColaboradorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ColaboradorsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()));
    }

    public static function canDelete($record): bool
    {
        return false;
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
            'index' => ListColaboradors::route('/'),
            'create' => CreateColaborador::route('/create'),
            'edit' => EditColaborador::route('/{record}/edit'),
        ];
    }
}
