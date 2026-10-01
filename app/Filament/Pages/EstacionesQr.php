<?php

namespace App\Filament\Pages;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Support\AlcanceSupervisor;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EstacionesQr extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'Asistencia';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Estaciones QR';

    protected static ?string $title = 'Estaciones de marcado QR';

    protected string $view = 'filament.pages.estaciones-qr';

    /**
     * Esta pantalla revela enlaces que incluyen la clave privada de cada
     * estación. Reutiliza el permiso de puntos de venta para no
     * convertir el acceso al módulo en una vía de revelación de secretos.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:EstacionesQr')
            && auth()->user()?->can('VerEnlace:PuntoVenta');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, ?string $search, int | string $page, int | string $recordsPerPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => $this->registrosPaginados(
                filters: $filters,
                search: $search,
                page: (int) $page,
                recordsPerPage: $recordsPerPage,
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
            ))
            ->columns([
                TextColumn::make('nombre')
                    ->label('Estación')
                    ->description(fn (array $record): string => $record['tipo'])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sucursal')->label('Sucursal')->searchable()->sortable(),
            ])
            ->filters([
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn (): array => Sucursal::query()
                        ->where('activo', true)
                        ->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all()),
            ])
            ->actions([
                Action::make('verQr')
                    ->label('QR asistencia')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->authorize(fn (): bool => auth()->user()->can('VerEnlace:PuntoVenta'))
                    ->tooltip('Abrir y descargar QR de asistencia')
                    ->modalHeading(fn (array $record): string => "QR de asistencia · {$record['nombre']}")
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalContent(fn (array $record) => view('filament.actions.estacion-qr', [
                        'estacion' => $record,
                        'qr' => $this->codigoQr($record['url']),
                        'tituloQr' => 'Marcación de asistencia',
                        'subtituloQr' => "{$record['sucursal']} · {$record['tipo']}",
                        'etiqueta' => 'Enlace de asistencia',
                        'archivo' => 'DIMSUM-QR-ASISTENCIA-',
                        'etiquetaDescargar' => 'Descargar QR de asistencia',
                        'etiquetaAbrir' => 'Abrir estación de asistencia',
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('verQrVisita')
                    ->label('QR visita')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->color('gray')
                    ->authorize(fn (): bool => auth()->user()->can('View:EstacionesQr')
                        && auth()->user()->can('VerEnlace:PuntoVenta'))
                    ->tooltip('Abrir y descargar QR de visita de supervisión')
                    ->modalHeading(fn (array $record): string => "QR de visita · {$record['nombre']}")
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalContent(fn (array $record) => view('filament.actions.estacion-qr', [
                        'estacion' => $record,
                        // Este QR provisiona la pantalla física. El QR que
                        // muestra esa pantalla rota cada 60 segundos.
                        'qr' => $this->codigoQr($record['visita_estacion_url']),
                        'url' => $record['visita_estacion_url'],
                        'tituloQr' => 'Visita de supervisión',
                        'subtituloQr' => "{$record['sucursal']} · {$record['tipo']}",
                        'etiqueta' => 'Enlace de visita de supervisión',
                        'archivo' => 'DIMSUM-QR-VISITA-SUPERVISION-',
                        'etiquetaDescargar' => 'Descargar QR de visita',
                        'mostrarEnlace' => true,
                        'permitirAbrir' => true,
                        'abrirUrl' => $record['visita_estacion_url'],
                        'etiquetaAbrir' => 'Abrir estación de visita',
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                ActionGroup::make([
                    Action::make('regenerarEnlace')
                        ->label('Regenerar accesos')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('warning')
                        ->authorize(fn (): bool => auth()->user()->can('RegenerarEnlace:PuntoVenta'))
                        ->requiresConfirmation()
                        ->modalHeading('Regenerar accesos de estación')
                        ->modalSubmitActionLabel('Regenerar')
                        ->action(function (array $record): void {
                            $this->puntoVentaPermitido($record)->regenerarTokenPantalla();

                            Notification::make()->title('Accesos de estación regenerados')->success()->send();
                        }),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->button()
                    ->color('gray'),
            ])
            ->defaultSort('sucursal')
            ->poll('30s')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Sin estaciones');
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    private function registrosPaginados(?array $filters, ?string $search, int $page, int | string $recordsPerPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator
    {
        $estaciones = $this->estacionesBase();
        // Los SelectFilter de Filament envían `['value' => null]` cuando no
        // tienen selección. No debemos tomar el arreglo completo como filtro,
        // pues Collection::where() lo interpretaría como un valor real.
        $sucursalId = data_get($filters, 'sucursal_id.value');

        if (filled($sucursalId)) {
            $estaciones = $estaciones->where('sucursal_id', (int) $sucursalId);
        }

        if (filled($search)) {
            $needle = Str::lower($search);

            $estaciones = $estaciones->filter(fn (array $estacion): bool => Str::contains(
                Str::lower(implode(' ', [$estacion['nombre'], $estacion['sucursal'], $estacion['tipo']])),
                $needle,
            ));
        }

        $sortColumn = in_array($sortColumn, ['nombre', 'sucursal'], true) ? $sortColumn : 'sucursal';
        $estaciones = $estaciones
            ->sortBy(fn (array $estacion): string => Str::lower($estacion[$sortColumn]), SORT_NATURAL, $sortDirection === 'desc')
            ->values();

        $recordsPerPage = $recordsPerPage === 'all' ? max($estaciones->count(), 1) : (int) $recordsPerPage;
        $page = max($page, 1);

        return new LengthAwarePaginator(
            $estaciones->forPage($page, $recordsPerPage)->values(),
            $estaciones->count(),
            $recordsPerPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    /**
     * @return Collection<int, array{__key: string, nombre: string, sucursal: string, sucursal_id: int, tipo: string, url: string, visita_estacion_url: string}>
     */
    private function estacionesBase(): Collection
    {
        $estaciones = collect();
        $puedeVerPuntosVenta = auth()->user()->can('VerEnlace:PuntoVenta');

        if ($puedeVerPuntosVenta) {
            PuntoVenta::query()
                ->where('activo', true)
                ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
                ->with('sucursal:id,nombre')
                ->orderBy('sucursal_id')
                ->orderBy('nombre')
                ->get()
                ->each(function (PuntoVenta $puntoVenta) use ($estaciones): void {
                    $estaciones->push([
                        '__key' => "punto-venta-{$puntoVenta->id}",
                        'nombre' => $puntoVenta->nombre,
                        'sucursal' => $puntoVenta->sucursal->nombre,
                        'sucursal_id' => $puntoVenta->sucursal_id,
                        'tipo' => self::etiquetaTipo($puntoVenta->tipo),
                        'url' => $puntoVenta->enlaceEstacion(),
                        'visita_estacion_url' => $puntoVenta->enlaceEstacionVisita(),
                    ]);
                });
        }

        return $estaciones;
    }

    /** @param array<string, mixed> $record */
    private function puntoVentaPermitido(array $record): PuntoVenta
    {
        $id = preg_replace('/^punto-venta-/', '', (string) ($record['__key'] ?? ''));
        abort_unless(is_string($id) && ctype_digit($id), 404);

        return PuntoVenta::query()
            ->where('activo', true)
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
            ->findOrFail((int) $id);
    }

    private static function etiquetaTipo(?string $tipo): string
    {
        return match ($tipo) {
            'caja' => 'Caja',
            'produccion' => 'Producción',
            'oficina' => 'Oficina',
            'almacen' => 'Almacén',
            default => 'Punto de marcado',
        };
    }

    private function codigoQr(string $url): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            data: $url,
            size: 280,
            margin: 10,
        ))->build()->getDataUri();
    }
}
