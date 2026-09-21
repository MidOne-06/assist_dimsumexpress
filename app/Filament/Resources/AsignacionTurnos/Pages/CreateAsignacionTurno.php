<?php

namespace App\Filament\Resources\AsignacionTurnos\Pages;

use App\Filament\Concerns\HasCompactFormWidth;
use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
use App\Models\Colaborador;
use App\Support\AlcanceSupervisor;
use Filament\Resources\Pages\CreateRecord;

class CreateAsignacionTurno extends CreateRecord
{
    use HasCompactFormWidth;

    protected static string $resource = AsignacionTurnoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $colaborador = Colaborador::query()->findOrFail($data['colaborador_id']);
        abort_unless(
            AlcanceSupervisor::puedeGestionarSucursal(auth()->user(), $colaborador->sucursal_id),
            403,
        );

        $data['asignado_por'] = auth()->id();

        return $data;
    }
}
