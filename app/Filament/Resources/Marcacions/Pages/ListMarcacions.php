<?php

namespace App\Filament\Resources\Marcacions\Pages;

use App\Filament\Resources\Marcacions\MarcacionResource;
use Filament\Resources\Pages\ListRecords;

class ListMarcacions extends ListRecords
{
    protected static string $resource = MarcacionResource::class;

    // Sin CreateAction: las marcaciones solo se generan desde el flujo real
    // de QR (estación de marcado + celular del colaborador), nunca a mano desde el panel.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
