<?php

namespace App\Filament\Resources\Turnos\Pages;

use App\Filament\Resources\Turnos\TurnoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListTurnos extends ListRecords
{
    protected static string $resource = TurnoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modal()
                ->modalHeading('Crear turno')
                ->modalWidth(Width::TwoExtraLarge)
                ->createAnother(false),
        ];
    }
}
