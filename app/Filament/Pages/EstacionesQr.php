<?php

namespace App\Filament\Pages;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Support\AlcanceSupervisor;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
     * estación. Reutiliza los permisos ya existentes de cada recurso para no
     * convertir el acceso al módulo en una vía de revelación de secretos.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:EstacionesQr')
            && (auth()->user()?->can('VerEnlace:Sucursal')
                || auth()->user()?->can('VerEnlace:PuntoVenta'));
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
                TextColumn::make('nombre')->label('Estación')->searchable()->sortable(),
                TextColumn::make('tipo_label')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sucursal' ? 'primary' : 'gray')
                    ->sortable(),
                TextColumn::make('sucursal')->label('Sucursal')->searchable()->sortable(),
                TextColumn::make('ubicacion')->label('Ubicación')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo de estación')
                    ->options([
                        'sucursal' => 'Sucursal',
                        'punto_venta' => 'Punto de venta',
                    ]),
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
                    ->label('Ver QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->authorize(fn (array $record): bool => $record['tipo'] === 'sucursal'
                        ? auth()->user()->can('VerEnlace:Sucursal')
                        : auth()->user()->can('VerEnlace:PuntoVenta'))
                    ->modalHeading(fn (array $record): string => "Estación: {$record['nombre']}")
                    ->modalContent(fn (array $record) => view('filament.actions.estacion-qr', [
                        'estacion' => $record,
                        'qr' => $this->codigoQr($record['url']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('verQrVisita')
                    ->label('QR visita')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->authorize(fn (): bool => auth()->user()->can('View:EstacionesQr'))
                    ->modalHeading(fn (array $record): string => "Visita de supervisor: {$record['nombre']}")
                    ->modalContent(fn (array $record) => view('filament.actions.estacion-qr', [
                        'estacion' => $record,
                        // Este QR provisiona la pantalla física. El QR que
                        // muestra esa pantalla rota cada 20 segundos.
                        'qr' => $this->codigoQr($record['visita_estacion_url']),
                        'url' => $record['visita_estacion_url'],
                        'etiqueta' => 'Enlace de estación de visita',
                        'archivo' => 'visita-supervisor-',
                        // Abrir o copiar una URL de visita desde el panel
                        // equivale a registrar una visita. El panel solo debe
                        // servir para visualizar o descargar el QR físico.
                        'mostrarEnlace' => true,
                        'permitirAbrir' => true,
                        'abrirUrl' => $record['visita_estacion_url'],
                        'etiquetaAbrir' => 'Abrir vista de estación',
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('regenerarEnlace')
                    ->label('Regenerar enlace')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->authorize(fn (array $record): bool => $record['tipo'] === 'sucursal'
                        ? auth()->user()->can('VerEnlace:Sucursal')
                        : auth()->user()->can('VerEnlace:PuntoVenta'))
                    ->requiresConfirmation()
                    ->modalHeading('Regenerar enlace de estación')
                    ->modalDescription('El enlace anterior dejará de funcionar de inmediato. Actualiza la pantalla física con el nuevo QR.')
                    ->modalSubmitActionLabel('Regenerar enlace')
                    ->action(function (array $record): void {
                        if ($record['tipo'] === 'sucursal') {
                            Sucursal::query()->findOrFail($record['sucursal_id'])->regenerarTokenPantalla();
                        } else {
                            PuntoVenta::query()->findOrFail((int) str_replace('punto-venta-', '', $record['__key']))->regenerarTokenPantalla();
                        }

                        Notification::make()->title('Enlace de estación regenerado')->success()->send();
                    }),
            ])
            ->defaultSort('sucursal')
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
        $tipo = data_get($filters, 'tipo.value');
        $sucursalId = data_get($filters, 'sucursal_id.value');

        if (filled($tipo)) {
            $estaciones = $estaciones->where('tipo', $tipo);
        }

        if (filled($sucursalId)) {
            $estaciones = $estaciones->where('sucursal_id', (int) $sucursalId);
        }

        if (filled($search)) {
            $needle = Str::lower($search);

            $estaciones = $estaciones->filter(fn (array $estacion): bool => Str::contains(
                Str::lower(implode(' ', [$estacion['nombre'], $estacion['tipo_label'], $estacion['sucursal'], $estacion['ubicacion']])),
                $needle,
            ));
        }

        $sortColumn = in_array($sortColumn, ['nombre', 'tipo_label', 'sucursal'], true) ? $sortColumn : 'sucursal';
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
     * @return Collection<int, array{__key: string, tipo: string, tipo_label: string, nombre: string, sucursal: string, sucursal_id: int, ubicacion: string, url: string, visita_url: string, visita_estacion_url: string}>
     */
    private function estacionesBase(): Collection
    {
        $estaciones = collect();
        $puedeVerSucursales = auth()->user()->can('VerEnlace:Sucursal');
        $puedeVerPuntosVenta = auth()->user()->can('VerEnlace:PuntoVenta');

        if ($puedeVerSucursales) {
            Sucursal::query()
                ->where('activo', true)
                ->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))
                ->orderBy('nombre')
                ->get()
                ->each(function (Sucursal $sucursal) use ($estaciones): void {
                    $estaciones->push([
                        '__key' => "sucursal-{$sucursal->id}",
                        'tipo' => 'sucursal',
                        'tipo_label' => 'Sucursal',
                        'nombre' => $sucursal->nombre,
                        'sucursal' => $sucursal->nombre,
                        'sucursal_id' => $sucursal->id,
                        'ubicacion' => $sucursal->tipo === 'planta' ? 'Planta' : 'Tienda',
                        'url' => $sucursal->enlaceEstacion(),
                        'visita_url' => $sucursal->enlaceVisitaSupervisor(),
                        'visita_estacion_url' => $sucursal->enlaceEstacionVisita(),
                    ]);
                });
        }

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
                        'tipo' => 'punto_venta',
                        'tipo_label' => 'Punto de venta',
                        'nombre' => $puntoVenta->nombre,
                        'sucursal' => $puntoVenta->sucursal->nombre,
                        'sucursal_id' => $puntoVenta->sucursal_id,
                        'ubicacion' => 'Punto de venta',
                        'url' => $puntoVenta->enlaceEstacion(),
                        'visita_url' => $puntoVenta->enlaceVisitaSupervisor(),
                        'visita_estacion_url' => $puntoVenta->enlaceEstacionVisita(),
                    ]);
                });
        }

        return $estaciones;
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
