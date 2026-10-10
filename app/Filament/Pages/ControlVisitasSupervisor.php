<?php

namespace App\Filament\Pages;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\VisitaSupervisor;
use App\Models\VisitaSupervisorMarcacion;
use App\Services\VisitaSupervisorSpreadsheetService;
use App\Services\RegularizacionVisitaSupervisorService;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ControlVisitasSupervisor extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Control de visitas';
    protected static ?string $title = 'Control de visitas de supervisión';
    protected string $view = 'filament.pages.control-visitas-supervisor';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:ControlVisitasSupervisor') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')
                ->label('Exportar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('Exportar:VisitaSupervisor') ?? false)
                ->authorize(fn (): bool => auth()->user()?->can('Exportar:VisitaSupervisor') ?? false)
                ->action(function () {
                    $query = $this->getFilteredTableQuery();

                    abort_if($query === null, 500, 'No se pudo preparar la exportación.');

                    return app(VisitaSupervisorSpreadsheetService::class)->exportar($query->clone());
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->visitasQuery())
            ->columns([
                TextColumn::make('supervisor.name')->label('Supervisor')->searchable()->sortable()->weight('medium'),
                TextColumn::make('sucursal.nombre')->label('Local')->description(fn (VisitaSupervisor $record): ?string => collect([
                    $record->puntoVentaIngreso?->nombre,
                    $record->sucursal?->activo ? null : 'Local inactivo',
                ])->filter()->join(' · ') ?: null)->searchable()->sortable(),
                TextColumn::make('ingreso_en')->label('Ingreso')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('salida_en')->label('Salida')->dateTime('d/m/Y H:i')->placeholder('Pendiente')->sortable(),
                TextColumn::make('duracion')->label('Duración')->getStateUsing(fn (VisitaSupervisor $record): string => static::formatoDuracion($record->duracionEnSegundos())),
                TextColumn::make('estado')->label('Estado')->getStateUsing(fn (VisitaSupervisor $record): string => static::etiquetaEstado($record))->badge()->color(fn (VisitaSupervisor $record): string => static::colorEstado($record)),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options([
                    VisitaSupervisor::EN_CURSO => 'En curso',
                    VisitaSupervisor::FINALIZADA => 'Finalizada',
                    VisitaSupervisor::REGULARIZADA => 'Regularizada',
                    VisitaSupervisor::HISTORICA => 'Histórica',
                ]),
                SelectFilter::make('sucursal_id')->label('Local')->options(fn (): array => Sucursal::query()
                    ->whereIn('id', AlcanceSupervisor::sucursalIdsHistoricos(auth()->user()))
                    ->orderBy('nombre')
                    ->get(['id', 'nombre', 'activo'])
                    ->mapWithKeys(fn (Sucursal $sucursal): array => [$sucursal->id => $sucursal->nombre . ($sucursal->activo ? '' : ' (inactivo)')])
                    ->all())->searchable(),
                Filter::make('fecha')
                    ->schema([DatePicker::make('desde')->label('Desde')->native(false), DatePicker::make('hasta')->label('Hasta')->native(false)])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $subquery, string $fecha): Builder => $subquery->whereDate('fecha', '>=', $fecha))
                        ->when($data['hasta'] ?? null, fn (Builder $subquery, string $fecha): Builder => $subquery->whereDate('fecha', '<=', $fecha))),
            ])
            ->filtersFormColumns(['default' => 1, 'md' => 3])
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->persistFiltersInSession()
            ->defaultSort('ingreso_en', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin visitas registradas')
            ->recordActions([$this->detalleAction(), $this->regularizarAction()]);
    }

    private function detalleAction(): Action
    {
        return Action::make('detalle')
            ->label('Detalle')
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->color('gray')
            ->modalHeading('Detalle de visita')
            ->modalWidth(Width::SevenExtraLarge)
            ->schema(fn (VisitaSupervisor $record): array => static::detalleSchema($record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    private function regularizarAction(): Action
    {
        return Action::make('regularizar')
            ->label('Regularizar salida')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('warning')
            ->authorize(fn (VisitaSupervisor $record): bool => $this->puedeRegularizar($record))
            ->modalHeading('Regularizar salida de visita')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar regularización')
            ->modalCancelActionLabel('Cancelar')
            ->schema(fn (VisitaSupervisor $record): array => [
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    DateTimePicker::make('salida_en')->label('Salida')->required()->seconds(false)->native(false)->default(now())->minDate($record->ingreso_en)->maxDate(now()),
                    Select::make('punto_venta_salida_id')
                        ->label('Punto de salida')
                        ->options(fn (): array => PuntoVenta::query()->where('sucursal_id', $record->sucursal_id)->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->native(),
                    Textarea::make('regularizacion_motivo')->label('Motivo')->required()->minLength(10)->maxLength(1000)->rows(3)->columnSpanFull(),
                ]),
            ])
            ->action(fn (VisitaSupervisor $record, array $data): null => $this->regularizar($record, $data));
    }

    private function visitasQuery(): Builder
    {
        return VisitaSupervisor::query()
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIdsHistoricos(auth()->user()))
            ->with([
                'supervisor:id,name',
                'sucursal:id,nombre,activo',
                'puntoVentaIngreso:id,nombre',
                'puntoVentaSalida:id,nombre',
                'regularizadaPor:id,name',
                'marcaciones.puntoVenta:id,nombre',
            ]);
    }

    private function puedeRegularizar(VisitaSupervisor $visita): bool
    {
        $usuario = auth()->user();

        return $visita->estado === VisitaSupervisor::EN_CURSO
            && $usuario?->can('Regularizar:VisitaSupervisor')
            && $usuario->hasAnyRole(['super_admin', 'administrador'])
            && in_array($visita->sucursal_id, AlcanceSupervisor::sucursalIds($usuario), true);
    }

    /** @param array<string, mixed> $data */
    private function regularizar(VisitaSupervisor $visita, array $data): null
    {
        $usuario = auth()->user();
        abort_unless($usuario !== null, 403);
        app(RegularizacionVisitaSupervisorService::class)->regularizar($visita, $data, $usuario, request()->ip(), request()->userAgent());

        Notification::make()->title('Salida regularizada')->success()->send();

        return null;
    }

    private static function etiquetaEstado(VisitaSupervisor $visita): string
    {
        if ($visita->estado === VisitaSupervisor::EN_CURSO && $visita->fecha?->isBefore(today())) {
            return 'Pendiente';
        }

        return match ($visita->estado) {
            VisitaSupervisor::EN_CURSO => 'En curso',
            VisitaSupervisor::FINALIZADA => 'Finalizada',
            VisitaSupervisor::REGULARIZADA => 'Regularizada',
            default => 'Histórica',
        };
    }

    private static function colorEstado(VisitaSupervisor $visita): string
    {
        return match (static::etiquetaEstado($visita)) {
            'En curso' => 'warning', 'Pendiente' => 'danger', 'Finalizada' => 'success', 'Regularizada' => 'info', default => 'gray',
        };
    }

    private static function formatoDuracion(?int $segundos): string
    {
        return $segundos === null ? '—' : sprintf('%d h %02d min', intdiv($segundos, 3600), intdiv($segundos % 3600, 60));
    }

    /** @return array<Section> */
    private static function detalleSchema(VisitaSupervisor $visita): array
    {
        $visita->loadMissing([
            'supervisor:id,name', 'sucursal:id,nombre,activo', 'puntoVentaIngreso:id,nombre',
            'puntoVentaSalida:id,nombre', 'regularizadaPor:id,name', 'marcaciones.puntoVenta:id,nombre',
        ]);

        return [
            Section::make()
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('estado')->label('Estado')->state(static::etiquetaEstado($visita))->badge()->color(static::colorEstado($visita)),
                    TextEntry::make('fecha')->label('Fecha')->state($visita->fecha)->date('d/m/Y'),
                    TextEntry::make('duracion')->label('Duración')->state(static::formatoDuracion($visita->duracionEnSegundos())),
                ]),
            Section::make('Visita')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('supervisor')->label('Supervisor')->state($visita->supervisor?->name ?? '—'),
                    TextEntry::make('local')->label('Local')->state($visita->sucursal?->nombre ?? '—')->helperText($visita->sucursal?->activo ? null : 'Local actualmente inactivo'),
                    TextEntry::make('ingreso')->label('Ingreso')->state($visita->ingreso_en)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('estacion_ingreso')->label('Estación de ingreso')->state($visita->puntoVentaIngreso?->nombre ?? '—'),
                    TextEntry::make('salida')->label('Salida')->state($visita->salida_en)->dateTime('d/m/Y H:i:s')->placeholder('Pendiente'),
                    TextEntry::make('estacion_salida')->label('Estación de salida')->state($visita->puntoVentaSalida?->nombre ?? '—'),
                ]),
            Section::make('Eventos inmutables')
                ->compact()
                ->schema([
                    RepeatableEntry::make('marcaciones')
                        ->state($visita->marcaciones)
                        ->contained()
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 4])->schema([
                                TextEntry::make('tipo')->label('Evento')->formatStateUsing(fn (string $state): string => static::etiquetaEvento($state))->badge()->color(fn (string $state): string => static::colorEvento($state)),
                                TextEntry::make('fecha_hora')->label('Fecha y hora')->dateTime('d/m/Y H:i:s'),
                                TextEntry::make('puntoVenta.nombre')->label('Estación')->placeholder('—'),
                                TextEntry::make('referencia')->label('Registro')->state(fn (VisitaSupervisorMarcacion $record): string => '#' . $record->id),
                            ]),
                        ]),
                ]),
            Section::make('Regularización')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->visible($visita->regularizada_en !== null)
                ->schema([
                    TextEntry::make('regularizada_por')->label('Regularizada por')->state($visita->regularizadaPor?->name ?? '—'),
                    TextEntry::make('regularizada_en')->label('Registrada el')->state($visita->regularizada_en)->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('regularizacion_motivo')->label('Motivo')->state($visita->regularizacion_motivo ?? '—')->columnSpanFull()->wrap(),
                ]),
            Section::make('Datos técnicos')
                ->compact()
                ->collapsible()
                ->collapsed()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('ip_ingreso')->label('IP de ingreso')->state($visita->ingreso_ip_origen ?? $visita->ip_origen ?? '—')->copyable(),
                    TextEntry::make('ip_salida')->label('IP de salida')->state($visita->salida_ip_origen ?? '—')->copyable(),
                    TextEntry::make('dispositivo_ingreso')->label('Dispositivo de ingreso')->state($visita->ingreso_user_agent ?? $visita->user_agent ?? '—')->copyable()->wrap()->columnSpanFull(),
                    TextEntry::make('dispositivo_salida')->label('Dispositivo de salida')->state($visita->salida_user_agent ?? '—')->copyable()->wrap()->columnSpanFull(),
                ]),
        ];
    }

    private static function etiquetaEvento(string $tipo): string
    {
        return match ($tipo) {
            VisitaSupervisorMarcacion::INGRESO => 'Ingreso',
            VisitaSupervisorMarcacion::SALIDA => 'Salida',
            VisitaSupervisorMarcacion::REGULARIZACION => 'Salida regularizada',
            default => $tipo,
        };
    }

    private static function colorEvento(string $tipo): string
    {
        return match ($tipo) {
            VisitaSupervisorMarcacion::INGRESO => 'success',
            VisitaSupervisorMarcacion::SALIDA => 'danger',
            VisitaSupervisorMarcacion::REGULARIZACION => 'info',
            default => 'gray',
        };
    }
}
