<?php

namespace App\Filament\Resources\PuntoVentas\Pages;

use App\Filament\Resources\PuntoVentas\PuntoVentaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPuntoVentas extends ListRecords
{
    protected static string $resource = PuntoVentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
