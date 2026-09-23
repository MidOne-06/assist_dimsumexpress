<?php

namespace App\Filament\Resources\AsignacionTurnos\Pages;

use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Support\AlcanceSupervisor;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListAsignacionTurnos extends ListRecords
{
    protected static string $resource = AsignacionTurnoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('asignarPorRango')
                ->label('Asignar por rango')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('AsignarMasivo:AsignarTurnos') ?? false)
                ->modalHeading('Asignar turnos por rango')
                ->modalSubmitActionLabel('Asignar turnos')
                ->schema([
                    Section::make()
                        ->columns(12)
                        ->schema([
                            Select::make('sucursal_id')
                                ->label('Local')
                                ->options(fn (): array => AlcanceSupervisor::sucursalesQuery(auth()->user())
                                    ->pluck('nombre', 'id')
                                    ->all())
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('colaborador_ids', []))
                                ->columnSpan(['default' => 'full', 'lg' => 5]),
                            Select::make('colaborador_ids')
                                ->label('Colaboradores')
                                ->options(fn (Get $get): array => filled($get('sucursal_id')) ? Colaborador::query()
                                    ->where('sucursal_id', $get('sucursal_id'))
                                    ->where('activo', true)
                                    ->orderBy('nombre_completo')
                                    ->pluck('nombre_completo', 'id')
                                    ->all() : [])
                                ->multiple()
                                ->searchable()
                                ->optionsLimit(8)
                                ->disabled(fn (Get $get): bool => blank($get('sucursal_id')))
                                ->required()
                                ->columnSpan(['default' => 'full', 'lg' => 7]),
                            Select::make('turno_id')
                                ->label('Turno')
                                ->options(fn (): array => Turno::query()
                                    ->where('activo', true)
                                    ->orderBy('hora_inicio')
                                    ->pluck('nombre', 'id')
                                    ->all())
                                ->required()
                                ->columnSpan(['default' => 'full', 'lg' => 5]),
                            DatePicker::make('fecha_inicio')
                                ->label('Desde')
                                ->native(false)
                                ->minDate(today()->addDay())
                                ->default(today()->addDay())
                                ->required()
                                ->columnSpan(['default' => 6, 'lg' => 3]),
                            DatePicker::make('fecha_fin')
                                ->label('Hasta')
                                ->native(false)
                                ->minDate(today()->addDay())
                                ->default(today()->addDay())
                                ->required()
                                ->columnSpan(['default' => 6, 'lg' => 3]),
                            CheckboxList::make('dias_semana')
                                ->label('Días')
                                ->options([
                                    '1' => 'Lunes',
                                    '2' => 'Martes',
                                    '3' => 'Miércoles',
                                    '4' => 'Jueves',
                                    '5' => 'Viernes',
                                    '6' => 'Sábado',
                                    '7' => 'Domingo',
                                ])
                                ->default(['1', '2', '3', '4', '5', '6', '7'])
                                ->columns(['default' => 2, 'md' => 4, 'xl' => 7])
                                ->required()
                                ->columnSpanFull(),
                            Textarea::make('observacion')
                                ->label('Observación')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(fn (array $data) => $this->asignarPorRango($data)),
            CreateAction::make()
                ->label('Asignar a un colaborador')
                ->modal()
                ->modalHeading('Asignar turno individual')
                ->modalWidth(Width::Large)
                ->createAnother(false),
        ];
    }

    /** @param array<string, mixed> $data */
    private function asignarPorRango(array $data): void
    {
        abort_unless(auth()->user()?->can('AsignarMasivo:AsignarTurnos'), 403);

        $fechaInicio = Carbon::parse($data['fecha_inicio'])->startOfDay();
        $fechaFin = Carbon::parse($data['fecha_fin'])->startOfDay();
        $sucursalId = (int) $data['sucursal_id'];

        if ($fechaInicio->lte(today())) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'Solo se pueden programar turnos futuros.',
            ]);
        }

        if ($fechaFin->lt($fechaInicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha "Hasta" debe ser igual o posterior a la fecha "Desde".',
            ]);
        }

        if ($fechaInicio->diffInDays($fechaFin) > 90) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'El rango máximo permitido es de 90 días.',
            ]);
        }

        abort_unless(
            Sucursal::query()
                ->whereKey($sucursalId)
                ->whereIn('id', AlcanceSupervisor::sucursalIds(auth()->user()))
                ->exists(),
            403,
        );

        $colaboradorIds = array_values(array_unique(array_map('intval', $data['colaborador_ids'])));
        $colaboradoresPermitidos = Colaborador::query()
            ->whereIn('id', $colaboradorIds)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->count();

        abort_unless($colaboradoresPermitidos === count($colaboradorIds), 403);

        $diasSemana = array_map('intval', $data['dias_semana']);
        $creadas = 0;
        $actualizadas = 0;

        DB::transaction(function () use ($fechaInicio, $fechaFin, $diasSemana, $colaboradorIds, $data, &$creadas, &$actualizadas): void {
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
    }
}
