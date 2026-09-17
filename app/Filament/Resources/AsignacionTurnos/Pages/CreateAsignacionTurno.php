<?php

namespace App\Filament\Resources\AsignacionTurnos\Pages;

use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAsignacionTurno extends CreateRecord
{
    protected static string $resource = AsignacionTurnoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['asignado_por'] = auth()->id();

        return $data;
    }
}
