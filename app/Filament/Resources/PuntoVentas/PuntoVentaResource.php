<?php

namespace App\Filament\Resources\PuntoVentas;

use App\Filament\Resources\PuntoVentas\Pages\ListPuntoVentas;
use App\Filament\Resources\PuntoVentas\Schemas\PuntoVentaForm;
use App\Filament\Resources\PuntoVentas\Tables\PuntoVentasTable;
use App\Models\PuntoVenta;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PuntoVentaResource extends Resource
{
    protected static ?string $model = PuntoVenta::class;

    protected static ?string $slug = 'puntos-de-venta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Organización';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'punto de marcado';

    protected static ?string $pluralModelLabel = 'puntos de marcado';

    protected static ?string $navigationLabel = 'Puntos de marcado';

    public static function form(Schema $schema): Schema
    {
        return PuntoVentaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PuntoVentasTable::configure($table);
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
            'index' => ListPuntoVentas::route('/'),
        ];
    }
}
