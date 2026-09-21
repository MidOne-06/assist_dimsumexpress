<?php

namespace App\Filament\Resources\AsignacionTurnos\Pages;

use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
use App\Models\Colaborador;
use App\Support\AlcanceSupervisor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAsignacionTurno extends EditRecord
{
    protected static string $resource = AsignacionTurnoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
