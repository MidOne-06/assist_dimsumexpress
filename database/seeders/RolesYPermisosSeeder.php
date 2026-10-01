<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\CatalogoPermisos;
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

        foreach (array_values(array_unique([
            ...CatalogoPermisos::nombres(),
            'Access:AdminPanel',
            'Registrar:Marcacion',
            'View:MiHorario',
            'Registrar:VisitaSupervisor',
            'View:CalendarioVisitasSupervisor',
            'View:HorasEfectivasMensuales',
            'View:AparienciaSistema',
            'ViewAny:Marcacion',
            'View:Marcacion',
            'Exportar:Marcacion',
            'ViewAny:IncidenciaMarcacion',
            'View:IncidenciaMarcacion',
            'Resolver:IncidenciaMarcacion',
            'Reportar:IncidenciaMarcacion',
            'View:EstacionesQr',
            'ViewAny:CoberturaOperativa',
            'View:CoberturaOperativa',
            'Revisar:CoberturaOperativa',
            'VerEnlace:PuntoVenta',
            'RegenerarEnlace:PuntoVenta',
            'AsignarMasivo:AsignarTurnos',
            'Exportar:AsignacionTurno',
            'ResetPassword:User',
            'Exportar:Colaborador',
            'Importar:Colaborador',
            'ViewEnlaces:Colaborador',
            'GenerarEnlace:Colaborador',
            'RevocarEnlace:Colaborador',
            'ViewAny:Empresa',
            'View:Empresa',
            'Create:Empresa',
            'Update:Empresa',
            'ViewAny:Area',
            'View:Area',
            'Create:Area',
            'Update:Area',
        ])) as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

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
            'ViewAny:Marcacion',
            'View:Marcacion',
            'Exportar:Marcacion',
            'ViewAny:IncidenciaMarcacion',
            'View:IncidenciaMarcacion',
            'Resolver:IncidenciaMarcacion',
            'Reportar:IncidenciaMarcacion',
            'ViewAny:CoberturaOperativa',
            'View:CoberturaOperativa',
            'Revisar:CoberturaOperativa',
            'Create:AsignacionTurno',
            'Update:AsignacionTurno',
            'Delete:AsignacionTurno',
            'DeleteAny:AsignacionTurno',
            'ViewAny:Turno',
            'View:Turno',
            'View:CalendarioTurnos',
            'View:HorasEfectivasMensuales',
            'View:AsignarTurnos',
            'AsignarMasivo:AsignarTurnos',
            'Exportar:AsignacionTurno',
            'Registrar:Marcacion',
            'View:MiHorario',
            'ViewEnlaces:Colaborador',
            'GenerarEnlace:Colaborador',
            'RevocarEnlace:Colaborador',
        ]));
        // Estas dos lecturas se declaran expresamente porque Marcaciones es
        // un recurso de solo lectura y no genera permisos CRUD completos.
        $supervisor->givePermissionTo(
            Permission::findByName('ViewAny:Marcacion', 'web'),
            Permission::findByName('View:Marcacion', 'web'),
        );
        $supervisor->givePermissionTo(Permission::findByName('Registrar:VisitaSupervisor', 'web'));

        // El operario marca asistencia mediante /marcar. No recibe acceso al
        // panel administrativo ni privilegios de gestión.
        $operador->syncPermissions($permisos->whereIn('name', [
            'Registrar:Marcacion',
            'View:MiHorario',
        ]));

        // Los colaboradores que existían antes de introducir roles mantienen
        // el acceso operativo mínimo. No se les otorga acceso al panel.
        User::query()
            ->whereHas('colaborador')
            ->doesntHave('roles')
            ->chunkById(100, fn ($usuarios) => $usuarios->each(
                fn (User $usuario) => $usuario->assignRole($operador)
            ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
