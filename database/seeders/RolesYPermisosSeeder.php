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

        // Supervisor: gestiona turnos únicamente de los locales enlazados a
        // su usuario. El alcance se aplica en las páginas, recursos y políticas.
        $supervisor->syncPermissions($permisos->whereIn('name', [
            'Access:AdminPanel',
            'ViewAny:Colaborador',
            'View:Colaborador',
            'ViewAny:AsignacionTurno',
            'View:AsignacionTurno',
            'Create:AsignacionTurno',
            'Update:AsignacionTurno',
            'Delete:AsignacionTurno',
            'DeleteAny:AsignacionTurno',
            'ViewAny:Turno',
            'View:Turno',
            'View:CalendarioTurnos',
            'View:AsignarTurnos',
            'AsignarMasivo:AsignarTurnos',
        ]));

        // El operario marca asistencia mediante /marcar. No recibe acceso al
        // panel administrativo ni privilegios de gestión.
        $operador->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
