<?php

namespace App\Filament\Resources\Colaboradors\Tables;

use App\Actions\ActualizarColaborador;
use App\Models\Colaborador;
use App\Models\Area;
use App\Models\Empresa;
use App\Services\EnlacesAccesoColaboradorService;
use App\Support\PoliticaContrasena;
use App\Support\AlcanceSupervisor;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ColaboradorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->description(fn (Colaborador $record): ?string => collect([
                        $record->codigo_empresa ? "Código: {$record->codigo_empresa}" : null,
                        $record->user?->email,
                    ])->filter()->join(' · ') ?: null)
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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sucursal.nombre')
                    ->label('Local')
                    ->description(fn (Colaborador $record): ?string => $record->puntoVenta?->nombre)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('puntoVenta.nombre')
                    ->label('Punto de venta')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cargo')
                    ->label('Cargo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fecha_ingreso')
                    ->label('Ingreso')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre_completo')
            ->filters([
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn (): array => auth()->user()
                        ? AlcanceSupervisor::sucursalesQuery(auth()->user())->pluck('nombre', 'id')->all()
                        : [])
                    ->searchable(),
                SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->options(fn () => Empresa::query()->orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable(),
                SelectFilter::make('area_id')
                    ->label('Área')
                    ->options(fn () => Area::query()->orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable(),
                TernaryFilter::make('activo')
                    ->label('Activo'),
            ])
            ->filtersFormColumns(['default' => 1, 'md' => 2, 'xl' => 4])
            ->filtersFormWidth(Width::FourExtraLarge)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin colaboradores')
            ->recordActions([
                Action::make('verHistorialEnlacesAcceso')
                    ->label('Historial de accesos')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->visible(fn (Colaborador $record): bool => $record->user !== null)
                    ->authorize(fn (Colaborador $record): bool => self::puedeGestionarEnlace($record, 'ViewEnlaces:Colaborador'))
                    ->modal()
                    ->modalHeading('Historial de accesos')
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->schema(fn (Colaborador $record): array => self::historialEnlacesSchema($record)),
                Action::make('generarEnlaceAcceso')
                    ->label('Generar acceso')
                    ->icon(Heroicon::OutlinedLink)
                    ->color('info')
                    ->visible(fn (Colaborador $record): bool => $record->activo && $record->user !== null)
                    ->authorize(fn (Colaborador $record): bool => self::puedeGestionarEnlace($record, 'GenerarEnlace:Colaborador'))
                    ->modal()
                    ->modalHeading('Generar enlace de activación')
                    ->modalWidth(Width::Medium)
                    ->modalSubmitActionLabel('Generar acceso')
                    ->schema([
                        Select::make('vigencia_minutos')
                            ->label('Vigencia')
                            ->options(EnlacesAccesoColaboradorService::VIGENCIAS)
                            ->native()
                            ->default(15)
                            ->required(),
                    ])
                    ->action(function (Colaborador $record, array $data): void {
                        $resultado = app(EnlacesAccesoColaboradorService::class)->generar(
                            $record,
                            auth()->user(),
                            (int) $data['vigencia_minutos'],
                        );
                        Notification::make()
                            ->title('Enlace de activación generado')
                            ->actions([
                                Action::make('abrirEnlace')
                                    ->label('Abrir enlace')
                                    ->url($resultado['url'], shouldOpenInNewTab: true),
                            ])
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Action::make('revocarEnlaceAcceso')
                    ->label('Revocar acceso')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Colaborador $record): bool => $record->enlacesAcceso()
                        ->whereNull('usado_en')
                        ->whereNull('revocado_en')
                        ->where('expira_en', '>', now())
                        ->exists())
                    ->authorize(fn (Colaborador $record): bool => self::puedeGestionarEnlace($record, 'RevocarEnlace:Colaborador'))
                    ->requiresConfirmation()
                    ->modalHeading('Revocar enlace de activación')
                    ->modalSubmitActionLabel('Revocar acceso')
                    ->action(function (Colaborador $record): void {
                        app(EnlacesAccesoColaboradorService::class)->revocarActivos($record);

                        Notification::make()
                            ->title('Enlace revocado')
                            ->success()
                            ->send();
                    }),
                Action::make('restablecerContrasena')
                    ->label('Restablecer contraseña')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->visible(fn (Colaborador $record): bool => $record->user !== null)
                    ->authorize(fn (): bool => auth()->user()?->can('ResetPassword:User') ?? false)
                    ->modal()
                    ->modalHeading(fn (Colaborador $record): string => "Restablecer contraseña: {$record->nombre_completo}")
                    ->modalWidth(Width::Large)
                    ->modalSubmitActionLabel('Actualizar contraseña')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
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
                        ]),
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
                    ->using(fn (Colaborador $record, array $data) => app(ActualizarColaborador::class)->handle($record, $data, auth()->user()))
                    ->modal()
                    ->modalHeading('Actualizar colaborador')
                    ->modalWidth(Width::ThreeExtraLarge),
            ])
            ->toolbarActions([]);
    }

    private static function puedeGestionarEnlace(Colaborador $record, string $permiso): bool
    {
        $usuario = auth()->user();

        return $usuario?->can($permiso) === true
            && AlcanceSupervisor::puedeGestionarSucursal($usuario, $record->sucursal_id);
    }

    /** @return array<Section> */
    private static function historialEnlacesSchema(Colaborador $record): array
    {
        $enlaces = $record->enlacesAcceso()
            ->with('generadoPor')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($enlace): array {
                $estado = match (true) {
                    $enlace->usado_en !== null => 'Usado',
                    $enlace->revocado_en !== null => 'Revocado',
                    $enlace->expira_en->isPast() => 'Vencido',
                    default => 'Vigente',
                };

                return [
                    'estado' => $estado,
                    'generado' => $enlace->created_at->format('d/m/Y H:i:s'),
                    'vence' => $enlace->expira_en->format('d/m/Y H:i:s'),
                    'usado' => $enlace->usado_en?->format('d/m/Y H:i:s') ?? '—',
                    'generado_por' => $enlace->generadoPor?->name ?? 'Sistema',
                ];
            })
            ->all();

        if ($enlaces === []) {
            return [
                Section::make()->compact()->schema([
                    TextEntry::make('sin_enlaces')->hiddenLabel()->state('Sin enlaces registrados'),
                ]),
            ];
        }

        return [
            Section::make()->compact()->schema([
                RepeatableEntry::make('enlaces')
                    ->hiddenLabel()
                    ->state($enlaces)
                    ->contained()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])->schema([
                            TextEntry::make('estado')
                                ->label('Estado')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Usado' => 'success',
                                    'Revocado' => 'danger',
                                    'Vencido' => 'warning',
                                    default => 'info',
                                }),
                            TextEntry::make('generado')->label('Generado'),
                            TextEntry::make('vence')->label('Vence'),
                            TextEntry::make('usado')->label('Usado'),
                            TextEntry::make('generado_por')->label('Generado por'),
                        ]),
                    ]),
            ]),
        ];
    }
}
