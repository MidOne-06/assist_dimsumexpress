<?php

namespace App\Filament\Resources\AsignacionTurnos\Pages;

use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
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
}
