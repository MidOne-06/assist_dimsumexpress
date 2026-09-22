<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ListRoles;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;

class RoleResource extends ShieldRoleResource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Seguridad';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Roles y permisos';

    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->recordActions([
                EditAction::make()
                    ->modal()
                    ->modalHeading('Actualizar rol y permisos')
                    ->modalWidth(Width::TwoExtraLarge),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
        ];
    }
}
