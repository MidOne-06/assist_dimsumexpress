<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Services\RoleService;
use App\Support\CatalogoPermisos;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleResource extends ShieldRoleResource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Seguridad';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Roles y permisos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->disabled(fn (?Role $record): bool => $record ? RoleService::esRolSistema($record) : false),
                    Hidden::make('guard_name')
                        ->default('web')
                        ->dehydrated(),
                    static::getSelectAllFormComponent()
                        ->helperText(null)
                        ->columnSpanFull(),
                ]),
            static::getShieldFormComponents(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (string $state): string => static::etiquetaRol($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('permissions')
                    ->label('Permiso')
                    ->relationship('permissions', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(['default' => 1, 'md' => 2])
            ->filtersFormWidth(Width::Large)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin roles')
            ->recordActions([
                EditAction::make()
                    ->using(function (Role $record, array $data): Role {
                        /** @var \App\Models\User $actor */
                        $actor = auth()->user();

                        return app(RoleService::class)->actualizar($actor, $record, $data);
                    })
                    ->modal()
                    ->modalHeading('Actualizar rol y permisos')
                    ->modalWidth(Width::FiveExtraLarge),
                DeleteAction::make()
                    ->visible(fn (Role $record): bool => ! RoleService::esRolSistema($record) && ! $record->users()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar rol')
                    ->modalSubmitActionLabel('Eliminar'),
            ])
            ->defaultSort('name');
    }

    public static function getTabFormComponentForCustomPermissions(): Component
    {
        $categorias = CatalogoPermisos::categorias();

        return Tab::make('custom_permissions')
            ->label('Acciones especiales')
            ->badge(collect($categorias)->map(fn (array $permisos): int => count($permisos))->sum())
            ->schema([
                Grid::make()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema(collect($categorias)
                        ->map(fn (array $permisos, string $categoria): Section => Section::make($categoria)
                            ->compact()
                            ->schema([
                                static::getCheckboxListFormComponent(
                                    name: 'custom_'.Str::slug($categoria, '_'),
                                    options: $permisos,
                                    searchable: false,
                                    columns: 1,
                                ),
                            ]))
                        ->values()
                        ->all()),
            ]);
    }

    public static function etiquetaRol(string $rol): string
    {
        return match ($rol) {
            'super_admin' => 'Superadministrador',
            'administrador' => 'Administrador',
            'supervisor' => 'Supervisor',
            'operador' => 'Operador',
            'panel_user' => 'Usuario del panel',
            default => str($rol)->replace('_', ' ')->headline()->toString(),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
        ];
    }
}
