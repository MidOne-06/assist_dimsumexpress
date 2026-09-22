<?php

namespace App\Actions;

use App\Models\Colaborador;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final class CrearColaborador
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Colaborador
    {
        return DB::transaction(function () use ($data): Colaborador {
            $user = User::create([
                'name' => $data['nombre_completo'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            // Un colaborador siempre requiere el acceso operativo mínimo para
            // consultar su horario y registrar asistencia. Las cuentas de
            // administración y supervisión se gestionan desde Usuarios y roles.
            $user->assignRole(Role::findOrCreate('operador', 'web'));

            unset($data['email'], $data['password']);
            $data['user_id'] = $user->id;

            if (empty($data['punto_venta_id'])) {
                $data['punto_venta_id'] = null;
            }

            return Colaborador::create($data);
        });
    }
}
