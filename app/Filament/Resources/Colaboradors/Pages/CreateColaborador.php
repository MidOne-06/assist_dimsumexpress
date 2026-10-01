<?php

namespace App\Filament\Resources\Colaboradors\Pages;

use App\Actions\CrearColaborador;
use App\Filament\Resources\Colaboradors\ColaboradorResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateColaborador extends CreateRecord
{
    protected static string $resource = ColaboradorResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CrearColaborador::class)->handle($data, auth()->user());
    }
}
