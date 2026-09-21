<?php

namespace App\Filament\Resources\PuntoVentas\Pages;

use App\Filament\Concerns\HasCompactFormWidth;
use App\Filament\Resources\PuntoVentas\PuntoVentaResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePuntoVenta extends CreateRecord
{
    use HasCompactFormWidth;

    protected static string $resource = PuntoVentaResource::class;
}
