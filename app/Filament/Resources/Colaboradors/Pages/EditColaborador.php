<?php

namespace App\Filament\Resources\Colaboradors\Pages;

use App\Actions\ActualizarColaborador;
use App\Filament\Resources\Colaboradors\ColaboradorResource;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;

class EditColaborador extends EditRecord
{
    protected static string $resource = ColaboradorResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['email'] = $this->record->user?->email;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ActualizarColaborador::class)->handle($record, $data, auth()->id());
    }
}
