<?php

namespace App\Filament\Resources\PuntoVentas\Pages;

use App\Filament\Resources\PuntoVentas\PuntoVentaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListPuntoVentas extends ListRecords
{
    protected static string $resource = PuntoVentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modal()
                ->modalHeading('Crear punto de marcado')
                ->modalWidth(Width::Large)
                ->createAnother(false),
        ];
    }
}
