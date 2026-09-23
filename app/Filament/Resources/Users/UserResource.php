<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Models\Sucursal;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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

    /** @param array<string, mixed> $data */
    public static function validarCuentaOperador(array $data, ?User $usuario = null): void
    {
        $rolOperadorId = Role::query()->where('name', 'operador')->value('id');
        $roles = collect($data['roles'] ?? [])->map(static fn ($id): string => (string) $id);

        if ($rolOperadorId && $roles->contains((string) $rolOperadorId) && ! $usuario?->colaborador) {
            throw ValidationException::withMessages([
                'roles' => 'Los operadores se crean y administran desde Colaboradores para mantener su vínculo laboral.',
            ]);
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
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
                            ->minLength(12)
                            ->rules([Password::min(12)->mixedCase()->numbers()->symbols()])
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->columnSpanFull(),
                        Select::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(8)
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
                            ->optionsLimit(8)
                            ->visible(fn (Get $get): bool => collect($get('roles') ?? [])
                                ->map(fn ($id): string => (string) $id)
                                ->contains((string) Role::query()->where('name', 'supervisor')->value('id')))
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->before(fn (User $record, array $data): mixed => static::validarCuentaOperador($data, $record))
                    ->modal()
                    ->modalHeading('Actualizar usuario')
                    ->modalWidth(Width::TwoExtraLarge),
                Action::make('restablecerContrasena')
                    ->label('Restablecer contraseña')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->authorize(fn (): bool => auth()->user()->can('ResetPassword:User'))
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
                            ->minLength(12)
                            ->rules([Password::min(12)->mixedCase()->numbers()->symbols()])
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->restablecerContrasena($data['password'], auth()->id());
                    }),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => auth()->user()->can('delete', $record)),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
