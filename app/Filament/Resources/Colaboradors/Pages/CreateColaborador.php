<?php

namespace App\Filament\Resources\Colaboradors\Pages;

use App\Filament\Resources\Colaboradors\ColaboradorResource;
use App\Models\Colaborador;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateColaborador extends CreateRecord
{
    protected static string $resource = ColaboradorResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['nombre_completo'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            unset($data['email'], $data['password']);
            $data['user_id'] = $user->id;

            // La sucursal es tipo "planta": no aplica punto de venta aunque
            // el formulario lo haya enviado por alguna condición de carrera.
            if (empty($data['punto_venta_id'])) {
                $data['punto_venta_id'] = null;
            }

            return Colaborador::create($data);
        });
    }
}
