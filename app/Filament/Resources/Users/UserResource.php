<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\UserService;
use App\Support\PoliticaContrasena;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Seguridad';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    /** Compatibilidad y defensa adicional para acciones externas del recurso. */
    public static function validarCuentaOperador(array $data, ?User $usuario = null): void
    {
        $operadorId = Role::query()->where('name', 'operador')->value('id');

        if ($operadorId !== null && collect($data['roles'] ?? [])
            ->map(static fn (mixed $id): string => (string) $id)
            ->contains((string) $operadorId) && ! $usuario?->colaborador) {
            throw ValidationException::withMessages([
                'roles' => 'Los operadores se crean y administran desde Colaboradores para mantener su vínculo laboral.',
            ]);
        }
    }

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
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(PoliticaContrasena::MINIMO_CARACTERES)
                        ->rules([PoliticaContrasena::regla()])
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->visible(fn (string $operation): bool => $operation === 'create'),
                    TextInput::make('password_confirmation')
                        ->label('Confirmar contraseña')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->visible(fn (string $operation): bool => $operation === 'create'),
                    Select::make('roles')
                        ->label('Roles')
                        ->relationship('roles', 'name')
                        ->getOptionLabelFromRecordUsing(fn (Role $role): string => static::etiquetaRol($role->name))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->live()
                        // La acción de creación delega la sincronización al UserService.
                        // Los selects múltiples de relación no se deshidratan por defecto.
                        ->dehydrated()
                        ->optionsLimit(8)
                        ->required()
                        ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                        ->columnSpanFull(),
                    Select::make('sucursalesSupervisadas')
                        ->label('Locales supervisados')
                        ->relationship('sucursalesSupervisadas', 'nombre')
                        ->multiple()
                        ->options(fn (): array => Sucursal::query()
                            ->where('activo', true)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id')
                            ->all())
                        ->preload()
                        ->searchable()
                        // Debe llegar a UserService junto con el rol Supervisor.
                        ->dehydrated()
                        ->optionsLimit(8)
                        ->required(fn (Get $get): bool => static::esSupervisor($get('roles')))
                        ->visible(fn (Get $get): bool => static::esSupervisor($get('roles')))
                        ->columnSpanFull(),
                    Toggle::make('activo')
                        ->label('Acceso activo')
                        ->default(true)
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (User $record): ?string => $record->colaborador?->nombre_completo ? 'Cuenta de colaborador' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->formatStateUsing(fn (string $state): string => static::etiquetaRol($state))
                    ->badge(),
                TextColumn::make('estado_acceso')
                    ->label('Acceso')
                    ->state(fn (User $record): string => $record->estaActivoParaAcceso() ? 'Activo' : 'Inactivo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Activo' ? 'success' : 'danger'),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('activo')
                    ->label('Acceso activo'),
            ])
            ->filtersFormColumns(['default' => 1, 'md' => 2])
            ->filtersFormWidth(Width::Large)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin usuarios')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (User $record): bool => ! $record->colaborador)
                    ->using(function (User $record, array $data): User {
                        /** @var User $actor */
                        $actor = auth()->user();

                        return app(UserService::class)->actualizar($actor, $record, $data);
                    })
                    ->modal()
                    ->modalHeading('Actualizar usuario')
                    ->modalWidth(Width::ExtraLarge),
                Action::make('restablecerContrasena')
                    ->label('Restablecer contraseña')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->authorize(fn (): bool => auth()->user()?->can('ResetPassword:User') ?? false)
                    ->modal()
                    ->modalHeading(fn (User $record): string => "Restablecer contraseña: {$record->email}")
                    ->modalWidth(Width::Medium)
                    ->modalSubmitActionLabel('Actualizar contraseña')
                    ->schema([
                        TextInput::make('password')
                            ->label('Nueva contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(PoliticaContrasena::MINIMO_CARACTERES)
                            ->rules([PoliticaContrasena::regla()])
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        /** @var User $actor */
                        $actor = auth()->user();

                        app(UserService::class)->restablecerContrasena($actor, $record, $data['password']);
                    }),
                Action::make('cambiarEstado')
                    ->label(fn (User $record): string => $record->activo ? 'Desactivar acceso' : 'Reactivar acceso')
                    ->icon(fn (User $record): Heroicon => $record->activo ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (User $record): string => $record->activo ? 'danger' : 'success')
                    ->visible(fn (User $record): bool => ! $record->colaborador && ! $record->is(auth()->user()))
                    ->authorize(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->activo ? 'Desactivar acceso' : 'Reactivar acceso')
                    ->modalSubmitActionLabel(fn (User $record): string => $record->activo ? 'Desactivar' : 'Reactivar')
                    ->action(function (User $record): void {
                        /** @var User $actor */
                        $actor = auth()->user();

                        app(UserService::class)->cambiarEstado($actor, $record, ! $record->activo);
                    }),
            ])
            ->defaultSort('name');
    }

    /** @param array<int|string, mixed>|null $roles */
    public static function esSupervisor(?array $roles): bool
    {
        $supervisorId = Role::query()->where('name', 'supervisor')->value('id');

        return $supervisorId !== null && collect($roles ?? [])
            ->map(static fn (mixed $id): string => (string) $id)
            ->contains((string) $supervisorId);
    }

    public static function etiquetaRol(?string $rol): string
    {
        return match ($rol) {
            'super_admin' => 'Superadministrador',
            'administrador' => 'Administrador',
            'supervisor' => 'Supervisor',
            'operador' => 'Operador',
            default => (string) $rol,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
