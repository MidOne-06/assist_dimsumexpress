<?php

namespace App\Filament\Resources\IncidenciaMarcacions\Pages;

use App\Filament\Resources\IncidenciaMarcacions\IncidenciaMarcacionResource;
use Filament\Resources\Pages\ListRecords;

class ListIncidenciaMarcacions extends ListRecords
{
    protected static string $resource = IncidenciaMarcacionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
