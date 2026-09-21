<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('Access:AdminPanel', 'web');

        /** @var Collection<int, Permission> $permisos */
        $permisos = Permission::query()
            ->where('guard_name', 'web')
            ->get();

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $administrador = Role::findOrCreate('administrador', 'web');
        $supervisor = Role::findOrCreate('supervisor', 'web');
        $operador = Role::findOrCreate('operador', 'web');

        $superAdmin->syncPermissions($permisos);

        // El administrador opera todos los módulos de asistencia, pero no
        // puede crear usuarios de panel ni elevar permisos o roles.
        $administrador->syncPermissions($permisos->reject(
            fn (Permission $permiso): bool => str_ends_with($permiso->name, ':Role')
                || str_ends_with($permiso->name, ':User')
        ));

        // Supervisor: consulta de personal, marcaciones y calendario. Las
        // altas, bajas, enlaces QR y asignación masiva quedan en administración.
        $supervisor->syncPermissions($permisos->whereIn('name', [
            'Access:AdminPanel',
            'ViewAny:Colaborador',
            'View:Colaborador',
            'ViewAny:Marcacion',
            'View:Marcacion',
            'ViewAny:AsignacionTurno',
            'View:AsignacionTurno',
            'ViewAny:Turno',
            'View:Turno',
            'ViewAny:Sucursal',
            'View:Sucursal',
            'View:CalendarioTurnos',
        ]));

        // El operario marca asistencia mediante /marcar. No recibe acceso al
        // panel administrativo ni privilegios de gestión.
        $operador->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
