<?php

namespace App\Filament\Resources\Marcacions;

use App\Filament\Resources\Marcacions\Pages\ListMarcacions;
use App\Filament\Resources\Marcacions\Tables\MarcacionsTable;
use App\Models\Marcacion;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarcacionResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Marcacion::class;

    protected static ?string $slug = 'marcaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Marcaciones';

    protected static ?string $modelLabel = 'marcación';

    protected static ?string $pluralModelLabel = 'marcaciones';

    // Solo lectura: las marcaciones se generan desde el flujo real de QR
    // (estación de marcado + celular del colaborador), no deben crearse ni editarse a
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

    /**
     * Solo generar permisos "Ver todo"/"Ver" -- Shield genera por defecto
     * 12 permisos tipo CRUD para cada Resource, pero canCreate/canEdit/
     * canDelete de esta clase siempre devuelven false sin importar el
     * permiso real: dejar esos permisos disponibles en "Roles" confundiría
     * a un administrador haciéndole creer que puede otorgarlos (hallazgo de
     * la implementación de roles y permisos, 2026-09-18).
     *
     * @return array<int, string>
     */
    public static function getPermissionPrefixes(): array
    {
        return ['ViewAny', 'View'];
    }

    public static function table(Table $table): Table
    {
        return MarcacionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()));
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
