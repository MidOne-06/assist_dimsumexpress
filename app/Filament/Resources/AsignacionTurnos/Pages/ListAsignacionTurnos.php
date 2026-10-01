<?php

namespace App\Filament\Resources\AsignacionTurnos\Pages;

use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
use App\Models\Colaborador;
use App\Models\Turno;
use App\Services\AsignacionMasivaTurnosService;
use App\Services\AsignacionTurnoIndividualService;
use App\Support\AlcanceSupervisor;
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
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel('Asignar turnos')
                ->schema([
                    Section::make()
                        ->compact()
                        ->columns(['default' => 1, 'md' => 2])
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
                                ->columnSpan(1),
                            Select::make('turno_id')
                                ->label('Turno')
                                ->options(fn (): array => Turno::query()
                                    ->where('activo', true)
                                    ->orderBy('hora_inicio')
                                    ->pluck('nombre', 'id')
                                    ->all())
                                ->required()
                                ->columnSpan(1),
                            DatePicker::make('fecha_inicio')
                                ->label('Desde')
                                ->native(false)
                                ->minDate(today())
                                ->default(today())
                                ->required()
                                ->columnSpan(1),
                            DatePicker::make('fecha_fin')
                                ->label('Hasta')
                                ->native(false)
                                ->minDate(today())
                                ->default(today())
                                ->required()
                                ->columnSpan(1),
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
                                ->columnSpanFull(),
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
                                ->columns(['default' => 2, 'md' => 4])
                                ->required()
                                ->columnSpanFull(),
                            Textarea::make('observacion')
                                ->label('Observación')
                                ->rows(2)
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(fn (array $data) => $this->asignarPorRango($data)),
            CreateAction::make()
                ->label('Asignar a un colaborador')
                ->modal()
                ->modalHeading('Asignar turno individual')
                ->modalWidth(Width::Large)
                ->createAnother(false)
                ->using(fn (array $data) => app(AsignacionTurnoIndividualService::class)->crear(auth()->user(), $data)),
        ];
    }

    /** @param array<string, mixed> $data */
    private function asignarPorRango(array $data): void
    {
        $resultado = app(AsignacionMasivaTurnosService::class)->asignar(auth()->user(), $data);

        Notification::make()
            ->title('Asignación completada')
            ->body("{$resultado['creadas']} asignaciones creadas y {$resultado['actualizadas']} actualizadas.")
            ->success()
            ->send();
    }
}
