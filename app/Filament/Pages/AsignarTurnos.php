<?php

namespace App\Filament\Pages;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Turno;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * @property-read Schema $form
 */
class AsignarTurnos extends Page
{
    // Shield -- sin esto, `View:AsignarTurnos` (generado por shield:generate)
    // nunca se llegaba a evaluar de verdad: quedaba como fila huérfana en
    // `permissions`, y cualquier usuario con algún rol podía entrar a esta
    // página sin importar sus permisos reales (hallazgo de la auditoría de
    // "toda acción como permiso", 2026-09-18).
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Personal';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Asignación masiva';

    protected static ?string $title = 'Asignación masiva de turnos';

    protected string $view = 'filament.pages.asignar-turnos';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'dias_semana' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('colaborador_ids')
                    ->label('Colaboradores')
                    ->options(fn () => Colaborador::query()
                        ->where('activo', true)
                        ->with('sucursal')
                        ->orderBy('nombre_completo')
                        ->get()
                        ->mapWithKeys(fn (Colaborador $c) => [$c->id => "{$c->nombre_completo} ({$c->sucursal->nombre})"]))
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->helperText('Selecciona uno o varios colaboradores.'),
                Select::make('turno_id')
                    ->label('Turno a asignar')
                    ->options(fn () => Turno::query()->where('activo', true)->orderBy('hora_inicio')->pluck('nombre', 'id'))
                    ->required(),
                DatePicker::make('fecha_inicio')
                    ->label('Desde')
                    ->required()
                    ->native(false),
                DatePicker::make('fecha_fin')
                    ->label('Hasta')
                    ->required()
                    ->native(false),
                CheckboxList::make('dias_semana')
                    ->label('Días de la semana a incluir')
                    ->options([
                        '1' => 'Lunes',
                        '2' => 'Martes',
                        '3' => 'Miércoles',
                        '4' => 'Jueves',
                        '5' => 'Viernes',
                        '6' => 'Sábado',
                        '7' => 'Domingo',
                    ])
                    ->columns(4)
                    ->required()
                    ->helperText('Solo se crearán asignaciones en las fechas del rango que caigan en estos días.'),
                Textarea::make('observacion')
                    ->label('Observación (opcional)')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function asignar(): void
    {
        // Permiso propio, distinto de "ver la página" (View:AsignarTurnos):
        // esto crea/sobreescribe asignaciones reales para hasta 90 días y
        // múltiples colaboradores a la vez -- un proceso mucho más sensible
        // que asignar un turno individual (Create:AsignacionTurno), así que
        // un rol podría necesitar lo uno sin lo otro.
        abort_unless(auth()->user()->can('AsignarMasivo:AsignarTurnos'), 403);

        $data = $this->form->getState();

        $fechaInicio = \Illuminate\Support\Carbon::parse($data['fecha_inicio'])->startOfDay();
        $fechaFin = \Illuminate\Support\Carbon::parse($data['fecha_fin'])->startOfDay();

        if ($fechaFin->lt($fechaInicio)) {
            Notification::make()
                ->title('Rango de fechas inválido')
                ->body('La fecha "Hasta" debe ser igual o posterior a la fecha "Desde".')
                ->danger()
                ->send();

            return;
        }

        if ($fechaInicio->diffInDays($fechaFin) > 90) {
            Notification::make()
                ->title('Rango demasiado amplio')
                ->body('El rango máximo permitido es de 90 días. Divide la asignación en tramos más pequeños.')
                ->danger()
                ->send();

            return;
        }

        $diasSemana = array_map('intval', $data['dias_semana']);
        $colaboradorIds = $data['colaborador_ids'];

        $creadas = 0;
        $actualizadas = 0;

        DB::transaction(function () use ($fechaInicio, $fechaFin, $diasSemana, $colaboradorIds, $data, &$creadas, &$actualizadas) {
            foreach (CarbonPeriod::create($fechaInicio, $fechaFin) as $fecha) {
                if (! in_array($fecha->isoWeekday(), $diasSemana, true)) {
                    continue;
                }

                foreach ($colaboradorIds as $colaboradorId) {
                    $asignacion = AsignacionTurno::firstOrNew([
                        'colaborador_id' => $colaboradorId,
                        'fecha' => $fecha->toDateString(),
                    ]);

                    $existia = $asignacion->exists;

                    $asignacion->turno_id = $data['turno_id'];
                    $asignacion->observacion = $data['observacion'] ?? null;
                    $asignacion->asignado_por = auth()->id();
                    $asignacion->save();

                    $existia ? $actualizadas++ : $creadas++;
                }
            }
        });

        Notification::make()
            ->title('Asignación completada')
            ->body("{$creadas} asignaciones creadas y {$actualizadas} actualizadas.")
            ->success()
            ->send();

        $this->form->fill([
            ...$data,
            'colaborador_ids' => [],
        ]);
    }
}
