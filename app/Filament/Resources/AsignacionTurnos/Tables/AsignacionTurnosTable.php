<?php

namespace App\Filament\Resources\AsignacionTurnos\Tables;

use App\Models\AsignacionTurno;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Services\AsignacionTurnoIndividualService;
use App\Support\AlcanceSupervisor;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class AsignacionTurnosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('colaborador.nombre_completo')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('colaborador.sucursal.nombre')
                    ->label('Local')
                    ->description(fn ($record): ?string => $record->colaborador?->puntoVenta?->nombre)
                    ->toggleable(),
                TextColumn::make('turno.nombre')
                    ->label('Turno')
                    ->badge(),
                TextColumn::make('turno.hora_inicio')
                    ->label('Inicio')
                    ->time('H:i'),
                TextColumn::make('turno.hora_fin')
                    ->label('Fin')
                    ->formatStateUsing(fn (?string $state, $record): string => $record->turno?->solo_entrada
                        ? '—'
                        : ($record->turno?->jornada_abierta ? 'Sin horario' : ($state ? \Carbon\Carbon::parse($state)->format('H:i') : '—'))),
                TextColumn::make('origen')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        AsignacionTurno::ORIGEN_PROGRAMACION_MASIVA => 'Programación masiva',
                        AsignacionTurno::ORIGEN_DETECTADO_AUTOMATICAMENTE => 'Detección automática',
                        AsignacionTurno::ORIGEN_REGULARIZADO_MANUAL => 'Regularización',
                        default => 'Asignación individual',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        AsignacionTurno::ORIGEN_PROGRAMACION_MASIVA => 'primary',
                        AsignacionTurno::ORIGEN_DETECTADO_AUTOMATICAMENTE => 'info',
                        AsignacionTurno::ORIGEN_REGULARIZADO_MANUAL => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('situacion')
                    ->label('Estado')
                    ->state(fn (AsignacionTurno $record): string => $record->fecha->isFuture()
                        ? 'Programada'
                        : ($record->resumenJornada ? 'Cerrada' : ($record->fecha->isToday() ? 'En curso' : 'Histórica')))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Programada' => 'info',
                        'Cerrada' => 'success',
                        'En curso' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('observacion')
                    ->label('Observación')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('asignadoPor.name')
                    ->label('Asignado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde')->native(false),
                        DatePicker::make('hasta')->label('Hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $date) => $q->whereDate('fecha', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $date) => $q->whereDate('fecha', '<=', $date));
                    }),
                SelectFilter::make('turno_id')
                    ->label('Turno')
                    ->options(fn () => Turno::query()->orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('origen')
                    ->label('Origen')
                    ->options([
                        AsignacionTurno::ORIGEN_MANUAL => 'Asignación individual',
                        AsignacionTurno::ORIGEN_PROGRAMACION_MASIVA => 'Programación masiva',
                        AsignacionTurno::ORIGEN_DETECTADO_AUTOMATICAMENTE => 'Detección automática',
                        AsignacionTurno::ORIGEN_REGULARIZADO_MANUAL => 'Regularización',
                    ]),
                SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options([
                        'futuras' => 'Próximas',
                        'hoy' => 'Hoy',
                        'historicas' => 'Históricas',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'futuras' => $query->whereDate('fecha', '>', today()),
                        'hoy' => $query->whereDate('fecha', today()),
                        'historicas' => $query->whereDate('fecha', '<', today()),
                        default => $query,
                    }),
                SelectFilter::make('sucursal')
                    ->label('Sucursal')
                    ->options(fn () => Sucursal::query()
                        ->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $sucursalId) => $q->whereHas('colaborador', fn (Builder $q2) => $q2->where('sucursal_id', $sucursalId))
                        );
                    }),
            ])
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::FourExtraLarge)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin asignaciones')
            ->recordActions([
                EditAction::make()
                    ->modal()
                    ->modalHeading('Actualizar asignación de turno')
                    ->modalWidth(Width::Large)
                    ->using(fn ($record, array $data) => app(AsignacionTurnoIndividualService::class)
                        ->actualizar(auth()->user(), $record, $data)),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar asignación')
                    ->using(fn ($record) => app(AsignacionTurnoIndividualService::class)
                        ->eliminar(auth()->user(), $record)),
            ])
            ->toolbarActions([]);
    }
}
