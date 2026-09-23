<?php

namespace App\Filament\Resources\Colaboradors\Tables;

use App\Actions\ActualizarColaborador;
use App\Models\Colaborador;
use App\Models\Area;
use App\Models\Empresa;
use App\Models\Sucursal;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

class ColaboradorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('documento_identidad')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('empresa.nombre')
                    ->label('Empresa')
                    ->badge()
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('area.nombre')
                    ->label('Área')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('codigo_empresa')
                    ->label('Código')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('sucursal.nombre')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('puntoVenta.nombre')
                    ->label('Punto de venta')
                    ->placeholder('—'),
                TextColumn::make('cargo')
                    ->label('Cargo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('fecha_ingreso')
                    ->label('Ingreso')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre_completo')
            ->filters([
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn () => Sucursal::query()->orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->options(fn () => Empresa::query()->orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('area_id')
                    ->label('Área')
                    ->options(fn () => Area::query()->orderBy('nombre')->pluck('nombre', 'id')),
                TernaryFilter::make('activo')
                    ->label('Activo'),
            ])
            ->recordActions([
                Action::make('restablecerContrasena')
                    ->label('Restablecer contraseña')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->visible(fn (Colaborador $record): bool => $record->user !== null)
                    ->authorize(fn (): bool => auth()->user()?->can('ResetPassword:User') ?? false)
                    ->modal()
                    ->modalHeading(fn (Colaborador $record): string => "Restablecer contraseña: {$record->nombre_completo}")
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
                    ->action(function (Colaborador $record, array $data): void {
                        $record->user?->restablecerContrasena($data['password'], auth()->id());

                        Notification::make()
                            ->title('Contraseña actualizada')
                            ->success()
                            ->send();
                    }),
                Action::make('cambiarEstado')
                    ->label(fn (Colaborador $record): string => $record->activo ? 'Dar de baja' : 'Reactivar')
                    ->icon(fn (Colaborador $record): Heroicon => $record->activo ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (Colaborador $record): string => $record->activo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Colaborador $record): string => $record->activo ? 'Dar de baja a colaborador' : 'Reactivar colaborador')
                    ->modalSubmitActionLabel(fn (Colaborador $record): string => $record->activo ? 'Dar de baja' : 'Reactivar')
                    ->authorize(fn (Colaborador $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Colaborador $record): void {
                        if ($record->activo) {
                            $record->desactivarAcceso();
                        } else {
                            $record->reactivarAcceso();
                        }

                        Notification::make()
                            ->title($record->activo ? 'Colaborador reactivado' : 'Colaborador dado de baja')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->fillForm(fn (Colaborador $record): array => [
                        ...$record->attributesToArray(),
                        'email' => $record->user?->email,
                    ])
                    ->using(fn (Colaborador $record, array $data) => app(ActualizarColaborador::class)->handle($record, $data, auth()->id()))
                    ->modal()
                    ->modalHeading('Actualizar colaborador')
                    ->modalWidth(Width::FiveExtraLarge),
            ])
            ->toolbarActions([]);
    }
}
