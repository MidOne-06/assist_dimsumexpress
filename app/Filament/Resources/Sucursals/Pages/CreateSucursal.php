<?php

namespace App\Filament\Resources\Sucursals\Pages;

use App\Filament\Concerns\HasCompactFormWidth;
use App\Filament\Resources\Sucursals\SucursalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSucursal extends CreateRecord
{
    use HasCompactFormWidth;

    protected static string $resource = SucursalResource::class;
}
