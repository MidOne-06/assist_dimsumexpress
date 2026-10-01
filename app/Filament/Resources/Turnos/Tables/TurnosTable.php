<?php

namespace App\Filament\Resources\Turnos\Tables;

use App\Models\Turno;
use App\Services\TurnoService;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TurnosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('hora_inicio')
                    ->label('Inicio')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('hora_fin')
                    ->label('Fin')
                    ->formatStateUsing(fn (?string $state, Turno $record): string => $record->solo_entrada
                        ? '—'
                        : ($record->jornada_abierta
                            ? 'Sin horario'
                            : (! $state ? '—' : \Carbon\Carbon::parse($state)->format('H:i'))))
                    ->sortable(),
                IconColumn::make('cruza_medianoche')
                    ->label('Nocturno')
                    ->boolean(),
                TextColumn::make('tolerancia_entrada_minutos')
                    ->label('Toler. entrada')
                    ->suffix(' min')
                    ->alignCenter(),
                TextColumn::make('tolerancia_salida_minutos')
                    ->label('Toler. salida')
                    ->suffix(' min')
                    ->alignCenter(),
                TextColumn::make('refrigerio_minutos')
                    ->label('Refrigerio')
                    ->formatStateUsing(fn ($state, $record) => $record->incluye_refrigerio ? $state . ' min' : 'No')
                    ->alignCenter(),
                TextColumn::make('horas_efectivas_objetivo_minutos')
                    ->label('Horas efectivas')
                    ->formatStateUsing(fn (int $state, Turno $record) => $record->solo_entrada ? '—' : self::minutos($state))
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('horas_efectivas_jornada_completa_minutos')
                    ->label('Jornada completa')
                    ->formatStateUsing(fn (?int $state, Turno $record) => ! $record->solo_entrada && $state ? self::minutos($state) : '—')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('solo_entrada')
                    ->label('Solo entrada')
                    ->boolean(),
                IconColumn::make('jornada_abierta')
                    ->label('Jornada abierta')
                    ->boolean(),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('asignaciones_count')
                    ->label('Asignaciones')
                    ->counts('asignaciones')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('marcaciones_count')
                    ->label('Marcaciones')
                    ->counts('marcaciones')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('hora_inicio')
            ->filters([
                TernaryFilter::make('activo')
                    ->label('Activo'),
                TernaryFilter::make('solo_entrada')
                    ->label('Solo entrada'),
                TernaryFilter::make('jornada_abierta')
                    ->label('Jornada abierta'),
                TernaryFilter::make('incluye_refrigerio')
                    ->label('Incluye refrigerio'),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (Turno $record, array $data): Turno => app(TurnoService::class)
                        ->actualizar(auth()->user(), $record, $data))
                    ->modal()
                    ->modalHeading('Actualizar turno')
                    ->modalWidth(Width::ExtraLarge),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->toolbarActions([]);
    }

    private static function minutos(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto > 0 ? "{$horas} h {$resto} min" : "{$horas} h";
    }
}
