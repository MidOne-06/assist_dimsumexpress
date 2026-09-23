<?php

namespace App\Filament\Resources\Areas;

use App\Filament\Resources\Areas\Pages\ListAreas;
use App\Models\Area;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AreaResource extends Resource
{
    protected static ?string $model = Area::class;
    protected static ?string $slug = 'areas';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;
    protected static string|\UnitEnum|null $navigationGroup = 'Organización';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Áreas';
    protected static ?string $modelLabel = 'área';
    protected static ?string $pluralModelLabel = 'áreas';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('codigo')->label('Código')->required()->maxLength(30)->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::upper($state) : null)->unique(ignoreRecord: true),
            Toggle::make('activo')->label('Activo')->default(true)->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nombre')->label('Área')->searchable()->sortable(),
            TextColumn::make('codigo')->label('Código')->badge()->searchable()->sortable(),
            IconColumn::make('activo')->label('Activo')->boolean(),
        ])->filters([
            TernaryFilter::make('activo')->label('Activo'),
        ])->recordActions([
            EditAction::make()->modal()->modalHeading('Actualizar área')->modalWidth(Width::Medium),
        ])->toolbarActions([])->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return ['index' => ListAreas::route('/')];
    }
}
