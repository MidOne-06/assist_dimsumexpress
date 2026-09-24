<?php

namespace App\Filament\Resources\Marcacions\Tables;

use App\Models\Marcacion;
use App\Models\Area;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\AsignacionTurno;
use App\Support\JornadaMarcacion;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
                    ->description(fn (Marcacion $record): string => $record->turno?->nombre ?? 'Sin turno')
                    ->sortable(),
                TextColumn::make('colaborador.nombre_completo')
                    ->label('Colaborador')
                    ->searchable()
                    ->description(function (Marcacion $record): ?string {
                        return collect([
                            $record->empresa?->nombre,
                            $record->area?->nombre,
                        ])->filter()->join(' · ') ?: null;
                    })
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
                    ->label('Estación')
                    ->description(fn (Marcacion $record): ?string => $record->puntoVenta?->nombre)
                    ->sortable(),
                TextColumn::make('horas_efectivas')
                    ->label('Horas efectivas')
                    ->getStateUsing(function (Marcacion $record): string {
                        if ($record->tipo !== Marcacion::TIPO_SALIDA) {
                            return '—';
                        }
                        $asignacion = AsignacionTurno::query()->with('turno')->where('colaborador_id', $record->colaborador_id)->where('turno_id', $record->turno_id)->whereIn('fecha', [$record->fecha_hora->toDateString(), $record->fecha_hora->copy()->subDay()->toDateString()])->first();
                        $resumen = $asignacion ? JornadaMarcacion::resumen($record->colaborador, $asignacion) : null;
                        return $resumen && $resumen['efectivos_minutos'] !== null ? sprintf('%dh %02dm', intdiv($resumen['efectivos_minutos'], 60), $resumen['efectivos_minutos'] % 60) : '—';
                    })
                    ->alignEnd(),
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
            ->filtersFormColumns(3)
            ->filtersFormWidth(Width::FourExtraLarge)
            ->recordActions([
                Action::make('trazabilidad')
                    ->label('Trazabilidad')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->button()
                    ->authorize(fn (): bool => auth()->user()->can('View:Marcacion'))
                    ->modalHeading('Trazabilidad de marcación')
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema(fn (Marcacion $record): array => self::trazabilidadSchema(
                        $record->loadMissing(['colaborador', 'turno', 'sucursal', 'puntoVenta', 'qrToken', 'empresa', 'area'])
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->toolbarActions([]);
    }

    /**
     * @return array<Section>
     */
    private static function trazabilidadSchema(Marcacion $marcacion): array
    {
        $retorno = $marcacion->resumenRetornoRefrigerio();

        return [
            Section::make()
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('tipo')
                        ->label('Marcación')
                        ->state(self::etiquetaTipo($marcacion->tipo))
                        ->badge()
                        ->color(self::colorTipo($marcacion->tipo)),
                    TextEntry::make('fecha_hora')
                        ->label('Fecha y hora')
                        ->state($marcacion->fecha_hora)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('referencia')
                        ->label('Referencia')
                        ->state('#' . $marcacion->id),
                ]),
            Section::make('Colaborador')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('colaborador')
                        ->label('Nombre')
                        ->state($marcacion->colaborador?->nombre_completo ?? '—'),
                    TextEntry::make('documento')
                        ->label('Documento')
                        ->state($marcacion->colaborador?->documento_identidad ?? '—'),
                    TextEntry::make('empresa')
                        ->label('Empresa')
                        ->state($marcacion->empresa?->nombre ?? '—'),
                    TextEntry::make('area')
                        ->label('Área')
                        ->state($marcacion->area?->nombre ?? '—'),
                ]),
            Section::make('Estación y turno')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('estacion')
                        ->label('Estación')
                        ->state(collect([$marcacion->sucursal?->nombre, $marcacion->puntoVenta?->nombre])->filter()->join(' · ') ?: '—'),
                    TextEntry::make('turno')
                        ->label('Turno')
                        ->state($marcacion->turno?->nombre ?? '—'),
                    TextEntry::make('qr')
                        ->label('QR dinámico')
                        ->state($marcacion->qrToken ? 'QR #' . $marcacion->qrToken->id : '—'),
                    TextEntry::make('expira_en')
                        ->label('Venció')
                        ->state($marcacion->qrToken?->expira_en)
                        ->dateTime('d/m/Y H:i:s')
                        ->placeholder('—'),
                ]),
            Section::make('Refrigerio')
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->visible($retorno !== null)
                ->schema([
                    TextEntry::make('retorno_estado')
                        ->label('Retorno')
                        ->state($retorno['etiqueta'] ?? '—')
                        ->badge()
                        ->color(match ($retorno['estado'] ?? null) {
                            'puntual' => 'success',
                            'temprano' => 'warning',
                            'tarde' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('retorno_esperado')
                        ->label('Retorno esperado')
                        ->state($retorno['esperado'] ?? null)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('diferencia_refrigerio')
                        ->label('Diferencia')
                        ->state($marcacion->refrigerio_diferencia_segundos === null ? '—' : $marcacion->refrigerio_diferencia_segundos . ' s'),
                ]),
            Section::make('Origen')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('ip_origen')
                        ->label('IP')
                        ->state($marcacion->ip_origen ?? '—')
                        ->copyable(),
                    TextEntry::make('created_at')
                        ->label('Registrado')
                        ->state($marcacion->created_at)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('user_agent')
                        ->label('Dispositivo')
                        ->state($marcacion->user_agent ?? '—')
                        ->wrap()
                        ->copyable()
                        ->columnSpanFull(),
                ]),
        ];
    }

    private static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            'entrada' => 'Entrada',
            'salida' => 'Salida',
            'salida_refrigerio' => 'Salida a refrigerio',
            'regreso_refrigerio' => 'Regreso de refrigerio',
            default => $tipo,
        };
    }

    private static function colorTipo(string $tipo): string
    {
        return match ($tipo) {
            'entrada' => 'success',
            'salida' => 'danger',
            'salida_refrigerio' => 'warning',
            'regreso_refrigerio' => 'info',
            default => 'gray',
        };
    }
}
