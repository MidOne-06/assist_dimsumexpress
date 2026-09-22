<?php

namespace App\Filament\Resources\IncidenciaMarcacions\Pages;

use App\Filament\Resources\IncidenciaMarcacions\IncidenciaMarcacionResource;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Support\AlcanceSupervisor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\ValidationException;

class ListIncidenciaMarcacions extends ListRecords
{
    protected static string $resource = IncidenciaMarcacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reportarOmission')
                ->label('Reportar marcación omitida')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('Reportar:IncidenciaMarcacion') ?? false)
                ->authorize(fn (): bool => auth()->user()?->can('Reportar:IncidenciaMarcacion') ?? false)
                ->modalHeading('Reportar marcación omitida')
                ->modalSubmitActionLabel('Registrar incidencia')
                ->schema([
                    Select::make('colaborador_id')
                        ->label('Colaborador')
                        ->options(fn (): array => Colaborador::query()
                            ->where('activo', true)
                            ->whereIn('sucursal_id', AlcanceSupervisor::sucursalIds(auth()->user()))
                            ->orderBy('nombre_completo')
                            ->pluck('nombre_completo', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('asignacion_turno_id', null)),
                    Select::make('asignacion_turno_id')
                        ->label('Turno afectado')
                        ->options(fn (Get $get): array => filled($get('colaborador_id')) ? AsignacionTurno::query()
                            ->with('turno')
                            ->where('colaborador_id', $get('colaborador_id'))
                            ->orderByDesc('fecha')
                            ->limit(90)
                            ->get()
                            ->mapWithKeys(fn (AsignacionTurno $asignacion): array => [
                                $asignacion->id => $asignacion->fecha->format('d/m/Y') . ' · ' . $asignacion->turno->nombre,
                            ])
                            ->all() : [])
                        ->disabled(fn (Get $get): bool => blank($get('colaborador_id')))
                        ->searchable()
                        ->required(),
                    Textarea::make('observacion_reporte')
                        ->label('Qué marcación se omitió y por qué')
                        ->placeholder('Ej.: Salida a refrigerio no escaneada por falla del equipo.')
                        ->rows(4)
                        ->minLength(5)
                        ->maxLength(2000)
                        ->required(),
                ])
                ->action(fn (array $data) => $this->reportarOmission($data)),
        ];
    }

    /** @param array<string, mixed> $data */
    private function reportarOmission(array $data): void
    {
        $asignacion = AsignacionTurno::query()
            ->with('colaborador')
            ->findOrFail($data['asignacion_turno_id']);

        if ((int) $data['colaborador_id'] !== $asignacion->colaborador_id
            || ! AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $asignacion->colaborador->sucursal_id)) {
            abort(403);
        }

        $incidencia = IncidenciaMarcacion::firstOrCreate(
            ['asignacion_turno_id' => $asignacion->id],
            [
                'colaborador_id' => $asignacion->colaborador_id,
                'tipo' => IncidenciaMarcacion::TIPO_MARCACION_OMITIDA,
                'detectada_en' => now(),
                'observacion_reporte' => $data['observacion_reporte'],
            ],
        );

        if (! $incidencia->wasRecentlyCreated) {
            throw ValidationException::withMessages([
                'asignacion_turno_id' => 'Ese turno ya tiene una incidencia registrada. Revísala antes de crear otra.',
            ]);
        }

        Notification::make()
            ->title('Incidencia registrada')
            ->success()
            ->send();
    }
}
