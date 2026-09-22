<?php

namespace App\Filament\Resources\Colaboradors\Pages;

use App\Filament\Resources\Colaboradors\ColaboradorResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($record, $data) {
            $userUpdates = [
                'name' => $data['nombre_completo'],
                'email' => $data['email'],
            ];

            if (filled($data['password'] ?? null)) {
                $userUpdates['password'] = $data['password'];
            }

            // OJO: debe ser $record->user (instancia), no $record->user() (query
            // builder de la relación) -- ->update() sobre el query builder omite
            // el cast "hashed" de Eloquent y guarda la contraseña en texto plano.
            $record->user->update($userUpdates);

            unset($data['email'], $data['password']);

            if (empty($data['punto_venta_id'])) {
                $data['punto_venta_id'] = null;
            }

            $record->update($data);

            return $record;
        });
    }
}
