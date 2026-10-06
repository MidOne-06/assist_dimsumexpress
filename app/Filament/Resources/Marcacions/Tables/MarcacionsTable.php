<?php

namespace App\Filament\Resources\Marcacions\Tables;

use App\Models\Area;
use App\Models\AsignacionTurno;
use App\Models\CoberturaOperativa;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Support\AlcanceSupervisor;
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
    private const TURNO_SIN_ASIGNAR = '__sin_turno__';

    public static function configure(Table $table): Table
    {
        $sucursalIds = AlcanceSupervisor::sucursalIds(auth()->user());

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
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('area.nombre')
                    ->label('Área')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->label('Local')
                    ->description(fn (Marcacion $record): ?string => $record->puntoVenta?->nombre)
                    ->sortable(),
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
                    ->options(fn (): array => Sucursal::query()
                        ->whereIn('id', $sucursalIds)
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('punto_venta_id')
                    ->label('Punto de venta')
                    ->options(fn (): array => self::opcionesPuntoVenta($sucursalIds))
                    ->searchable(),
                SelectFilter::make('colaborador_id')
                    ->label('Colaborador')
                    ->options(fn (): array => self::opcionesColaborador($sucursalIds))
                    ->searchable(),
                SelectFilter::make('turno_concepto')
                    ->label('Turno')
                    ->options(fn (): array => self::opcionesTurno($sucursalIds))
                    ->query(fn (Builder $query, array $data): Builder => self::aplicarFiltroTurnoConcepto(
                        $query,
                        $data['value'] ?? null,
                    ))
                    ->searchable(),
                SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->options(fn (): array => Empresa::query()
                        ->whereIn('id', Marcacion::query()->whereIn('sucursal_id', $sucursalIds)->whereNotNull('empresa_id')->distinct()->pluck('empresa_id'))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all()),
                SelectFilter::make('area_id')
                    ->label('Área')
                    ->options(fn (): array => Area::query()
                        ->whereIn('id', Marcacion::query()->whereIn('sucursal_id', $sucursalIds)->whereNotNull('area_id')->distinct()->pluck('area_id'))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all()),
                Filter::make('fecha')
                    ->default([
                        'desde' => now()->toDateString(),
                        'hasta' => now()->toDateString(),
                    ])
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('desde')->label('Desde')->native(false),
                        \Filament\Forms\Components\DatePicker::make('hasta')->label('Hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_hora', '>=', $fecha))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_hora', '<=', $fecha));
                    }),
            ])
            ->filtersFormColumns(4)
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->persistFiltersInSession()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin marcaciones')
            ->recordActions([
                Action::make('jornada')
                    ->label('Jornada')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->button()
                    ->authorize(fn (): bool => auth()->user()->can('View:Marcacion'))
                    ->modalHeading('Control de jornada')
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema(fn (Marcacion $record): array => self::jornadaSchema(
                        $record->loadMissing(['colaborador', 'turno', 'sucursal', 'puntoVenta', 'coberturaOperativa'])
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('trazabilidad')
                    ->label('Trazabilidad')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->button()
                    ->authorize(fn (): bool => auth()->user()->can('View:Marcacion'))
                    ->modalHeading('Trazabilidad de marcación')
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema(fn (Marcacion $record): array => self::trazabilidadSchema(
                        $record->loadMissing(['colaborador', 'turno', 'sucursal', 'puntoVenta', 'qrToken', 'empresa', 'area', 'coberturaOperativa'])
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->toolbarActions([]);
    }

    /** @param array<int, int> $sucursalIds
     *  @return array<int, string>
     */
    public static function opcionesColaborador(array $sucursalIds): array
    {
        return Colaborador::query()
            ->where(function (Builder $query) use ($sucursalIds): void {
                // El colaborador puede estar cubriendo una estación distinta
                // a su sede base. Debe poder encontrarse por el filtro cuando
                // ya tiene una marcación en cualquiera de los locales visibles.
                $query->whereIn('sucursal_id', $sucursalIds)
                    ->orWhereIn('id', Marcacion::query()
                        ->whereIn('sucursal_id', $sucursalIds)
                        ->select('colaborador_id'));
            })
            ->orderBy('nombre_completo')
            ->get(['id', 'nombre_completo', 'activo'])
            ->mapWithKeys(fn (Colaborador $colaborador): array => [
                $colaborador->id => $colaborador->nombre_completo . ($colaborador->activo ? '' : ' · Histórico'),
            ])
            ->all();
    }

    /**
     * Consolida las versiones históricas de un mismo turno en una sola
     * opción. El valor mantiene el nombre normalizado, no un ID concreto,
     * para que "Apertura" consulte su histórico completo.
     *
     * @param array<int, int> $sucursalIds
     * @return array<string, string>
     */
    public static function opcionesTurno(array $sucursalIds): array
    {
        $opciones = Turno::query()
            ->whereIn('id', Marcacion::query()
                ->whereIn('sucursal_id', $sucursalIds)
                ->whereNotNull('turno_id')
                ->select('turno_id'))
            ->get()
            ->groupBy(fn (Turno $turno): string => self::normalizarNombre($turno->nombre))
            ->map(function ($versiones, string $concepto): string {
                $referencia = $versiones->firstWhere('activo', true) ?? $versiones->sortByDesc('id')->first();

                return $referencia->nombre . ($versiones->count() > 1 ? ' · Histórico incluido' : '');
            })
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        return [self::TURNO_SIN_ASIGNAR => 'Sin turno / excepción'] + $opciones->all();
    }

    /**
     * @param array<int, int> $sucursalIds
     * @return array<int, string>
     */
    public static function opcionesPuntoVenta(array $sucursalIds): array
    {
        return PuntoVenta::query()
            ->with('sucursal')
            ->whereIn('id', Marcacion::query()
                ->whereIn('sucursal_id', $sucursalIds)
                ->whereNotNull('punto_venta_id')
                ->select('punto_venta_id'))
            ->get()
            ->sortBy(fn (PuntoVenta $puntoVenta): string => ($puntoVenta->sucursal?->nombre ?? '') . '|' . $puntoVenta->nombre)
            ->mapWithKeys(fn (PuntoVenta $puntoVenta): array => [
                $puntoVenta->id => ($puntoVenta->sucursal?->nombre ?? 'Local no disponible')
                    . ' · ' . $puntoVenta->nombre
                    . ($puntoVenta->activo ? '' : ' · Histórico'),
            ])
            ->all();
    }

    public static function aplicarFiltroTurnoConcepto(Builder $query, ?string $concepto): Builder
    {
        if (blank($concepto)) {
            return $query;
        }

        if ($concepto === self::TURNO_SIN_ASIGNAR) {
            return $query->whereNull('turno_id');
        }

        return $query->whereHas('turno', fn (Builder $turnos): Builder => $turnos
            ->whereRaw('lower(trim(nombre)) = ?', [self::normalizarNombre($concepto)]));
    }

    private static function normalizarNombre(?string $nombre): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $nombre)));
    }

    /** @return array<Section> */
    private static function jornadaSchema(Marcacion $marcacion): array
    {
        $asignacion = self::asignacionDeMarcacion($marcacion);

        if ($asignacion === null || $marcacion->colaborador === null) {
            return [
                Section::make()
                    ->compact()
                    ->schema([
                        TextEntry::make('estado')->label('Estado')->state('Sin asignación de turno vinculada')->badge()->color('warning'),
                    ]),
            ];
        }

        $marcaciones = JornadaMarcacion::marcaciones($marcacion->colaborador, $asignacion);
        $resumen = JornadaMarcacion::resumen($marcacion->colaborador, $asignacion, $marcaciones);
        $limites = JornadaMarcacion::limites($asignacion);
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salidaRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $regresoRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);
        $salida = $marcaciones->filter(fn (Marcacion $evento): bool => $evento->tipo === Marcacion::TIPO_SALIDA)->last();
        $ultima = $marcaciones->last();
        $incidencias = IncidenciaMarcacion::query()
            ->where('asignacion_turno_id', $asignacion->id)
            ->orderBy('detectada_en')
            ->get();

        $estado = self::estadoJornada($resumen['estado'], $ultima?->tipo);
        $cobertura = $marcacion->coberturaOperativa;

        return [
            Section::make()
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('estado')->label('Estado')->state($estado['etiqueta'])->badge()->color($estado['color']),
                    TextEntry::make('turno')->label('Turno')->state($asignacion->turno?->nombre ?? '—'),
                    TextEntry::make('programado')->label('Programado')->state($limites['inicio']->format('d/m/Y H:i:s').' · '.$limites['fin']->format('H:i:s')),
                ]),
            Section::make('Marcaciones')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('entrada')->label('Entrada')->state($entrada?->fecha_hora)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('salida')->label('Salida')->state($salida?->fecha_hora)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('salida_refrigerio')->label('Salida a refrigerio')->state($salidaRefrigerio?->fecha_hora)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('regreso_refrigerio')->label('Regreso de refrigerio')->state($regresoRefrigerio?->fecha_hora)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                ]),
            Section::make('Horas')
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('efectivas')->label('Efectivas')->state(self::formatearSegundos($resumen['efectivos_segundos'])),
                    TextEntry::make('objetivo')->label('Objetivo')->state(self::formatearSegundos($resumen['objetivo_segundos'])),
                    TextEntry::make('diferencia')->label('Diferencia')->state(self::formatearDiferenciaSegundos($resumen['diferencia_segundos']))->badge()->color(($resumen['diferencia_segundos'] ?? 0) < 0 ? 'danger' : (($resumen['diferencia_segundos'] ?? 0) > 0 ? 'warning' : 'success')),
                ]),
            Section::make('Cumplimiento')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('entrada_estado')->label('Entrada')->state(self::estadoEntrada($entrada, $limites['inicio'], (int) $asignacion->turno->tolerancia_entrada_minutos)['etiqueta'])->badge()->color(self::estadoEntrada($entrada, $limites['inicio'], (int) $asignacion->turno->tolerancia_entrada_minutos)['color']),
                    TextEntry::make('salida_estado')->label('Salida')->state(self::estadoSalida($salida, $limites['fin'], (int) $asignacion->turno->tolerancia_salida_minutos)['etiqueta'])->badge()->color(self::estadoSalida($salida, $limites['fin'], (int) $asignacion->turno->tolerancia_salida_minutos)['color']),
                ]),
            Section::make('Estación')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('local')->label('Local marcado')->state(collect([$marcacion->sucursal?->nombre, $marcacion->puntoVenta?->nombre])->filter()->join(' · ') ?: '—'),
                    TextEntry::make('cobertura')->label('Cobertura')->state($cobertura ? self::etiquetaCobertura($cobertura->estado) : 'Local habitual')->badge()->color($cobertura ? match ($cobertura->estado) {
                        CoberturaOperativa::ESTADO_REVISADA => 'success',
                        CoberturaOperativa::ESTADO_OBSERVADA => 'danger',
                        default => 'warning',
                    } : 'gray'),
                ]),
            Section::make('Incidencias')
                ->compact()
                ->visible($incidencias->isNotEmpty())
                ->schema([
                    TextEntry::make('incidencias')->label('Registro')->state($incidencias->map(fn (IncidenciaMarcacion $incidencia): string => IncidenciaMarcacion::etiquetaTipo($incidencia->tipo).' · '.($incidencia->estaPendiente() ? 'Pendiente' : 'Resuelta'))->implode("\n"))->wrap(),
                ]),
        ];
    }

    private static function asignacionDeMarcacion(Marcacion $marcacion): ?AsignacionTurno
    {
        if ($marcacion->turno_id === null) {
            return null;
        }

        return AsignacionTurno::query()
            ->with('turno')
            ->where('colaborador_id', $marcacion->colaborador_id)
            ->where('turno_id', $marcacion->turno_id)
            ->whereIn('fecha', [$marcacion->fecha_hora->toDateString(), $marcacion->fecha_hora->copy()->subDay()->toDateString()])
            ->get()
            ->first(fn (AsignacionTurno $asignacion): bool => $marcacion->fecha_hora->betweenIncluded(
                JornadaMarcacion::limites($asignacion)['ventana_inicio'],
                JornadaMarcacion::limites($asignacion)['jornada_fin_maximo'],
            ));
    }

    /** @return array{etiqueta: string, color: string} */
    private static function estadoJornada(string $estado, ?string $ultimoTipo): array
    {
        if ($ultimoTipo === Marcacion::TIPO_SALIDA_REFRIGERIO) {
            return ['etiqueta' => 'En refrigerio', 'color' => 'warning'];
        }

        return match ($estado) {
            'cumplida' => ['etiqueta' => 'Cumplida', 'color' => 'success'],
            'extendida' => ['etiqueta' => 'Extendida', 'color' => 'warning'],
            'pendiente' => ['etiqueta' => 'Pendiente', 'color' => 'danger'],
            'inconsistente' => ['etiqueta' => 'Observada', 'color' => 'danger'],
            default => ['etiqueta' => 'En curso', 'color' => 'info'],
        };
    }

    private static function formatearMinutos(?int $minutos): string
    {
        return $minutos === null ? '—' : sprintf('%dh %02dm', intdiv($minutos, 60), $minutos % 60);
    }

    private static function formatearDiferencia(?int $minutos): string
    {
        if ($minutos === null) {
            return 'En curso';
        }

        return ($minutos > 0 ? '+' : ($minutos < 0 ? '−' : '')).self::formatearMinutos(abs($minutos));
    }

    private static function formatearSegundos(?int $segundos): string
    {
        if ($segundos === null) {
            return '—';
        }

        $absoluto = abs($segundos);
        $horas = intdiv($absoluto, 3600);
        $minutos = intdiv($absoluto % 3600, 60);
        $restantes = $absoluto % 60;

        return "{$horas} h {$minutos} min" . ($restantes ? " {$restantes} s" : '');
    }

    private static function formatearDiferenciaSegundos(?int $segundos): string
    {
        if ($segundos === null) {
            return 'En curso';
        }

        return ($segundos > 0 ? '+' : ($segundos < 0 ? '−' : '')).self::formatearSegundos(abs($segundos));
    }

    /** @return array{etiqueta: string, color: string} */
    private static function estadoEntrada(?Marcacion $entrada, \Carbon\Carbon $inicio, int $tolerancia): array
    {
        if ($entrada === null) {
            return ['etiqueta' => 'Pendiente', 'color' => 'gray'];
        }

        $limite = $inicio->copy()->addMinutes($tolerancia);
        if ($entrada->fecha_hora->lte($limite)) {
            return ['etiqueta' => 'A tiempo', 'color' => 'success'];
        }

        return [
            'etiqueta' => self::formatearDuracionSegundos($entrada->fecha_hora->getTimestamp() - $limite->getTimestamp()).' tarde',
            'color' => 'danger',
        ];
    }

    /** @return array{etiqueta: string, color: string} */
    private static function estadoSalida(?Marcacion $salida, \Carbon\Carbon $fin, int $tolerancia): array
    {
        if ($salida === null) {
            return ['etiqueta' => 'Pendiente', 'color' => 'gray'];
        }

        $limite = $fin->copy()->subMinutes($tolerancia);
        if ($salida->fecha_hora->gte($limite)) {
            return ['etiqueta' => 'Conforme', 'color' => 'success'];
        }

        return [
            'etiqueta' => self::formatearDuracionSegundos($limite->getTimestamp() - $salida->fecha_hora->getTimestamp()).' antes',
            'color' => 'danger',
        ];
    }

    private static function formatearDuracionSegundos(int $segundos): string
    {
        $segundos = max(0, $segundos);
        $horas = intdiv($segundos, 3600);
        $minutos = intdiv($segundos % 3600, 60);
        $restantes = $segundos % 60;

        return collect([
            $horas > 0 ? $horas.' h' : null,
            $minutos > 0 ? $minutos.' min' : null,
            $restantes > 0 ? $restantes.' s' : null,
        ])->filter()->join(' ') ?: '0 s';
    }

    private static function etiquetaCobertura(string $estado): string
    {
        return match ($estado) {
            CoberturaOperativa::ESTADO_REVISADA => 'Cobertura conforme',
            CoberturaOperativa::ESTADO_OBSERVADA => 'Cobertura observada',
            default => 'Cobertura pendiente',
        };
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
                        ->state($marcacion->qrToken ? 'Validado' : '—')
                        ->badge()
                        ->color($marcacion->qrToken ? 'success' : 'gray'),
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
                    TextEntry::make('cobertura')
                        ->label('Cobertura')
                        ->state($marcacion->coberturaOperativa ? self::etiquetaCobertura($marcacion->coberturaOperativa->estado) : 'Local habitual')
                        ->badge()
                        ->color($marcacion->coberturaOperativa ? match ($marcacion->coberturaOperativa->estado) {
                            CoberturaOperativa::ESTADO_REVISADA => 'success',
                            CoberturaOperativa::ESTADO_OBSERVADA => 'danger',
                            default => 'warning',
                        } : 'gray'),
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
