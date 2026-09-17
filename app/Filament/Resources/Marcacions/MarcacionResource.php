<?php

namespace App\Filament\Resources\Marcacions;

use App\Filament\Resources\Marcacions\Pages\ListMarcacions;
use App\Filament\Resources\Marcacions\Tables\MarcacionsTable;
use App\Models\Marcacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MarcacionResource extends Resource
{
    protected static ?string $model = Marcacion::class;

    protected static ?string $slug = 'marcaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|\UnitEnum|null $navigationGroup = 'Personal';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Marcaciones';

    protected static ?string $modelLabel = 'marcación';

    protected static ?string $pluralModelLabel = 'marcaciones';

    // Solo lectura: las marcaciones se generan desde el flujo real de QR
    // (kiosko + celular del colaborador), no deben crearse ni editarse a
    // mano desde el panel para no falsear el registro de asistencia.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return MarcacionsTable::configure($table);
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
            'index' => ListMarcacions::route('/'),
        ];
    }
}
