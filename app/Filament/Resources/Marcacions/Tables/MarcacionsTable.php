<?php

namespace App\Filament\Resources\Marcacions\Tables;

use App\Models\Marcacion;
use App\Models\Area;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\AsignacionTurno;
use App\Support\JornadaMarcacion;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarcacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_hora')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('colaborador.nombre_completo')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('empresa.nombre')
                    ->label('Empresa')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('area.nombre')
                    ->label('Área')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'entrada' => 'Entrada',
                        'salida' => 'Salida',
                        'salida_refrigerio' => 'Salida a refrigerio',
                        'regreso_refrigerio' => 'Regreso de refrigerio',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'entrada' => 'success',
                        'salida' => 'danger',
                        'salida_refrigerio' => 'warning',
                        'regreso_refrigerio' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('retorno_refrigerio')
                    ->label('Retorno de refrigerio')
                    ->getStateUsing(fn (Marcacion $record): string => $record->resumenRetornoRefrigerio()['etiqueta'] ?? '—')
                    ->badge()
                    ->color(function (Marcacion $record): string {
                        return match ($record->resumenRetornoRefrigerio()['estado'] ?? null) {
                            'puntual' => 'success',
                            'temprano' => 'warning',
                            'tarde' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->tooltip(function (Marcacion $record): ?string {
                        $resumen = $record->resumenRetornoRefrigerio();

                        return $resumen ? 'Retorno esperado: ' . $resumen['esperado']->format('d/m/Y H:i:s') : null;
                    }),
                TextColumn::make('sucursal.nombre')
                    ->label('Sucursal')
                    ->sortable(),
                TextColumn::make('puntoVenta.nombre')
                    ->label('Punto de venta')
                    ->placeholder('—'),
                TextColumn::make('turno.nombre')
                    ->label('Turno')
                    ->placeholder('—'),
                TextColumn::make('horas_efectivas')
                    ->label('Horas efectivas')
                    ->getStateUsing(function (Marcacion $record): string {
                        if ($record->tipo !== Marcacion::TIPO_SALIDA) {
                            return '—';
                        }
                        $asignacion = AsignacionTurno::query()->with('turno')->where('colaborador_id', $record->colaborador_id)->where('turno_id', $record->turno_id)->whereIn('fecha', [$record->fecha_hora->toDateString(), $record->fecha_hora->copy()->subDay()->toDateString()])->first();
                        $resumen = $asignacion ? JornadaMarcacion::resumen($record->colaborador, $asignacion) : null;
                        return $resumen && $resumen['efectivos_minutos'] !== null ? sprintf('%dh %02dm', intdiv($resumen['efectivos_minutos'], 60), $resumen['efectivos_minutos'] % 60) : '—';
                    }),
                TextColumn::make('ip_origen')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('fecha_hora', 'desc')
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'entrada' => 'Entrada',
                        'salida' => 'Salida',
                        'salida_refrigerio' => 'Salida a refrigerio',
                        'regreso_refrigerio' => 'Regreso de refrigerio',
                    ]),
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn () => Sucursal::query()->orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->options(fn () => Empresa::query()->orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('area_id')
                    ->label('Área')
                    ->options(fn () => Area::query()->orderBy('nombre')->pluck('nombre', 'id')),
                Filter::make('fecha')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('desde')->native(false),
                        \Filament\Forms\Components\DatePicker::make('hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_hora', '>=', $fecha))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_hora', '<=', $fecha));
                    }),
            ])
            ->recordActions([
                Action::make('trazabilidad')
                    ->label('Trazabilidad')
                    ->authorize(fn (): bool => auth()->user()->can('View:Marcacion'))
                    ->modalHeading('Trazabilidad de marcación')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalContent(fn (Marcacion $record) => view('filament.actions.trazabilidad-marcacion', [
                        'marcacion' => $record->loadMissing(['colaborador', 'turno', 'sucursal', 'puntoVenta', 'qrToken']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->toolbarActions([]);
    }
}
