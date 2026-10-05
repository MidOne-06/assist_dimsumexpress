<?php

namespace App\Filament\Pages;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\VisitaSupervisor;
use App\Models\VisitaSupervisorMarcacion;
use App\Support\AlcanceSupervisor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
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

    public function table(Table $table): Table
    {
        return $table
            ->query($this->visitasQuery())
            ->columns([
                TextColumn::make('supervisor.name')->label('Supervisor')->searchable()->sortable()->weight('medium'),
                TextColumn::make('sucursal.nombre')->label('Local')->description(fn (VisitaSupervisor $record): ?string => $record->puntoVentaIngreso?->nombre)->searchable()->sortable(),
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
                SelectFilter::make('sucursal_id')->label('Local')->options(fn (): array => Sucursal::query()->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))->orderBy('nombre')->pluck('nombre', 'id')->all())->searchable(),
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
            ->recordActions([$this->regularizarAction()]);
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
                    DateTimePicker::make('salida_en')->label('Salida')->required()->seconds(false)->native(false)->default(now())->minDate($record->ingreso_en),
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
            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
            ->with(['supervisor:id,name', 'sucursal:id,nombre', 'puntoVentaIngreso:id,nombre']);
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
        DB::transaction(function () use ($visita, $data): void {
            $visita = VisitaSupervisor::query()->lockForUpdate()->findOrFail($visita->id);
            abort_unless($this->puedeRegularizar($visita), 403);
            $salida = Carbon::parse($data['salida_en']);

            if ($visita->ingreso_en && $salida->lt($visita->ingreso_en)) {
                throw ValidationException::withMessages(['salida_en' => 'La salida no puede ser anterior al ingreso.']);
            }

            $visita->update([
                'estado' => VisitaSupervisor::REGULARIZADA,
                'salida_en' => $salida,
                'punto_venta_salida_id' => $data['punto_venta_salida_id'] ?: null,
                'regularizada_por_id' => auth()->id(),
                'regularizada_en' => now(),
                'regularizacion_motivo' => $data['regularizacion_motivo'],
            ]);
            VisitaSupervisorMarcacion::create([
                'visita_supervisor_id' => $visita->id,
                'supervisor_id' => $visita->supervisor_id,
                'sucursal_id' => $visita->sucursal_id,
                'punto_venta_id' => $data['punto_venta_salida_id'] ?: null,
                'tipo' => VisitaSupervisorMarcacion::REGULARIZACION,
                'fecha_hora' => $salida,
                'ip_origen' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 1000),
                'metadata' => ['motivo' => $data['regularizacion_motivo'], 'regularizada_por_id' => auth()->id()],
            ]);
        });

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
}
