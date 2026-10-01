<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Mantiene los roles de producción y sus permisos como una unidad validada. */
class RoleService
{
    /** @var array<int, string> */
    public const ROLES_DEL_SISTEMA = ['super_admin', 'administrador', 'supervisor', 'operador', 'panel_user'];

    /** @param array<string, mixed> $data */
    public function crear(User $actor, array $data): Role
    {
        abort_unless($actor->can('Create:Role'), 403);

        [$nombre, $permisos] = $this->datosValidados($data);
        if (in_array($nombre, self::ROLES_DEL_SISTEMA, true)) {
            throw ValidationException::withMessages([
                'name' => 'Ese rol está reservado por el sistema.',
            ]);
        }

        return DB::transaction(function () use ($nombre, $permisos): Role {
            $rol = Role::query()->create(['name' => $nombre, 'guard_name' => 'web']);
            $rol->syncPermissions($permisos);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $rol;
        });
    }

    /** @param array<string, mixed> $data */
    public function actualizar(User $actor, Role $rol, array $data): Role
    {
        abort_unless($actor->can('update', $rol), 403);

        [$nombre, $permisos] = $this->datosValidados($data, $rol);
        $esRolSistema = in_array($rol->name, self::ROLES_DEL_SISTEMA, true);

        if ($esRolSistema && $nombre !== $rol->name) {
            throw ValidationException::withMessages([
                'name' => 'No puedes renombrar un rol del sistema.',
            ]);
        }

        if ($rol->name === 'super_admin') {
            if (! $actor->hasRole('super_admin')) {
                abort(403);
            }

            $permisos = Permission::query()->where('guard_name', 'web')->get();
        }

        return DB::transaction(function () use ($rol, $nombre, $permisos): Role {
            $rol->update(['name' => $nombre, 'guard_name' => 'web']);
            $rol->syncPermissions($permisos);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $rol;
        });
    }

    public static function esRolSistema(Role $rol): bool
    {
        return in_array($rol->name, self::ROLES_DEL_SISTEMA, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: string, 1: \Illuminate\Support\Collection<int, Permission>}
     */
    private function datosValidados(array $data, ?Role $rol = null): array
    {
        $nombre = $this->normalizarNombre($data['name'] ?? $rol?->name ?? '');
        if ($nombre === '') {
            throw ValidationException::withMessages(['name' => 'Ingresa el nombre del rol.']);
        }

        if (mb_strlen($nombre) > 255) {
            throw ValidationException::withMessages(['name' => 'El nombre no debe superar 255 caracteres.']);
        }

        $duplicado = Role::query()->where('guard_name', 'web')->whereRaw('lower(name) = ?', [mb_strtolower($nombre)]);
        if ($rol) {
            $duplicado->whereKeyNot($rol->id);
        }
        if ($duplicado->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe un rol con ese nombre.']);
        }

        $nombresPermiso = collect($data)
            ->except(['name', 'guard_name', 'select_all', 'team_id'])
            ->filter(static fn (mixed $value): bool => is_array($value))
            ->flatten()
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values();

        $permisos = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $nombresPermiso)
            ->get();

        if ($permisos->count() !== $nombresPermiso->count()) {
            throw ValidationException::withMessages(['permissions' => 'La selección contiene permisos no válidos.']);
        }

        if ($nombresPermiso->isEmpty() && $rol?->name !== 'super_admin') {
            throw ValidationException::withMessages(['permissions' => 'Selecciona al menos un permiso.']);
        }

        return [$nombre, $permisos];
    }

    private function normalizarNombre(mixed $valor): string
    {
        $nombre = preg_replace('/\s+/', '_', trim((string) $valor));
        $nombre = mb_strtolower($nombre);

        return $nombre;
    }
}
