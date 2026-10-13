<?php

namespace App\Filament\Resources\AsignacionTurnos\Pages;

use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
use App\Services\AsignacionTurnoIndividualService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAsignacionTurnos extends ListRecords
{
    protected static string $resource = AsignacionTurnoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Asignar a un colaborador')
                ->modal()
                ->modalHeading('Asignar turno individual')
                ->modalWidth(Width::Large)
                ->createAnother(false)
                ->using(fn (array $data) => app(AsignacionTurnoIndividualService::class)->crear(auth()->user(), $data)),
        ];
    }
}
