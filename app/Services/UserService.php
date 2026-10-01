<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sucursal;
use App\Models\User;
use App\Support\PoliticaContrasena;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Gestiona cuentas administrativas sin mezclar su ciclo de vida con colaboradores. */
class UserService
{
    /** @param array<string, mixed> $data */
    public function crear(User $actor, array $data): User
    {
        abort_unless($actor->can('Create:User'), 403);

        [$atributos, $roles, $sucursales] = $this->datosValidados($actor, $data);

        return DB::transaction(function () use ($atributos, $roles, $sucursales): User {
            $usuario = User::query()->create($atributos);
            $usuario->syncRoles($roles);
            $usuario->sucursalesSupervisadas()->sync($sucursales);

            return $usuario;
        });
    }

    /** @param array<string, mixed> $data */
    public function actualizar(User $actor, User $usuario, array $data): User
    {
        abort_unless($actor->can('update', $usuario), 403);

        if ($usuario->colaborador) {
            throw ValidationException::withMessages([
                'name' => 'Los datos laborales y el rol de una cuenta de colaborador se administran desde Colaboradores.',
            ]);
        }

        [$atributos, $roles, $sucursales] = $this->datosValidados($actor, $data, $usuario);
        $this->protegerSuperadministrador($actor, $usuario, $roles, (bool) $atributos['activo']);

        return DB::transaction(function () use ($actor, $usuario, $atributos, $roles, $sucursales): User {
            $usuario->update($atributos);
            $usuario->syncRoles($roles);
            $usuario->sucursalesSupervisadas()->sync($sucursales);

            if (! $usuario->activo) {
                $usuario->invalidarSesiones();
            }

            return $usuario;
        });
    }

    public function restablecerContrasena(User $actor, User $usuario, string $password): void
    {
        abort_unless($actor->can('ResetPassword:User'), 403);

        $this->validarPassword($password);
        $usuario->restablecerContrasena($password, $actor->id);
    }

    public function cambiarEstado(User $actor, User $usuario, bool $activo): User
    {
        abort_unless($actor->can('update', $usuario), 403);

        if ($usuario->is($actor)) {
            throw ValidationException::withMessages([
                'activo' => 'No puedes cambiar el estado de tu propia cuenta.',
            ]);
        }

        if ($usuario->colaborador) {
            throw ValidationException::withMessages([
                'activo' => 'El acceso de una cuenta de colaborador se gestiona desde Colaboradores.',
            ]);
        }

        $this->protegerSuperadministrador($actor, $usuario, $usuario->roles->all(), $activo);

        $usuario->forceFill(['activo' => $activo])->save();

        if (! $activo) {
            $usuario->invalidarSesiones();
        }

        return $usuario;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: array{name: string, email: string, password?: string, activo: bool}, 1: array<int, Role>, 2: array<int, int>}
     */
    private function datosValidados(User $actor, array $data, ?User $usuario = null): array
    {
        $name = preg_replace('/\s+/', ' ', trim((string) ($data['name'] ?? '')));
        $email = Str::lower(trim((string) ($data['email'] ?? '')));
        $activo = $this->booleano($data['activo'] ?? $usuario?->activo ?? true);

        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['name' => 'Ingresa un nombre de hasta 255 caracteres.']);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            throw ValidationException::withMessages(['email' => 'Ingresa un correo válido.']);
        }

        $duplicado = User::query()->whereRaw('lower(email) = ?', [$email]);
        if ($usuario) {
            $duplicado->whereKeyNot($usuario->id);
        }

        if ($duplicado->exists()) {
            throw ValidationException::withMessages(['email' => 'Ya existe una cuenta con este correo.']);
        }

        $rolIds = collect($data['roles'] ?? $usuario?->roles()->pluck('id')->all() ?? [])
            ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $rolIds)
            ->get();

        if ($rolIds->isEmpty() || $roles->count() !== $rolIds->count()) {
            throw ValidationException::withMessages(['roles' => 'Selecciona al menos un rol válido.']);
        }

        $esOperador = $roles->contains('name', 'operador');
        if ($esOperador && ! $usuario?->colaborador) {
            throw ValidationException::withMessages([
                'roles' => 'Los operadores se crean y administran desde Colaboradores para mantener su vínculo laboral.',
            ]);
        }

        if ($usuario?->colaborador && ($roles->count() !== 1 || ! $esOperador)) {
            throw ValidationException::withMessages([
                'roles' => 'La cuenta de un colaborador debe conservar únicamente el rol Operador.',
            ]);
        }

        $tocaSuperAdmin = $roles->contains('name', 'super_admin')
            || $usuario?->hasRole('super_admin');
        if ($tocaSuperAdmin && ! $actor->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'roles' => 'Solo un superadministrador puede asignar o modificar ese rol.',
            ]);
        }

        if ($usuario?->is($actor) && array_key_exists('roles', $data)) {
            throw ValidationException::withMessages([
                'roles' => 'No puedes modificar tus propios roles.',
            ]);
        }

        $esSupervisor = $roles->contains('name', 'supervisor');
        $sucursalIds = collect($data['sucursalesSupervisadas'] ?? $usuario?->sucursalesSupervisadas()->pluck('id')->all() ?? [])
            ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if (! $esSupervisor) {
            $sucursalIds = collect();
        }

        $sucursales = Sucursal::query()->whereIn('id', $sucursalIds)->get();
        if ($sucursales->count() !== $sucursalIds->count() || $sucursales->contains('activo', false)) {
            throw ValidationException::withMessages(['sucursalesSupervisadas' => 'Selecciona únicamente locales activos.']);
        }

        if ($esSupervisor && $sucursalIds->isEmpty()) {
            throw ValidationException::withMessages(['sucursalesSupervisadas' => 'Asigna al menos un local al supervisor.']);
        }

        $atributos = ['name' => $name, 'email' => $email, 'activo' => $activo];
        if (! $usuario) {
            $password = (string) ($data['password'] ?? '');
            $this->validarPassword($password, (string) ($data['password_confirmation'] ?? ''));
            $atributos['password'] = $password;
        }

        return [$atributos, $roles->all(), $sucursalIds->all()];
    }

    /** @param array<int, Role> $roles */
    private function protegerSuperadministrador(User $actor, User $usuario, array $roles, bool $activo): void
    {
        if (! $usuario->hasRole('super_admin')) {
            return;
        }

        $conservaRol = collect($roles)->contains(fn (Role $rol): bool => $rol->name === 'super_admin');
        if ($conservaRol && $activo) {
            return;
        }

        $otrosActivos = User::role('super_admin')
            ->where('activo', true)
            ->whereKeyNot($usuario->id)
            ->exists();

        if (! $otrosActivos) {
            throw ValidationException::withMessages([
                'roles' => 'Debe permanecer al menos un superadministrador activo.',
            ]);
        }
    }

    private function validarPassword(string $password, ?string $confirmation = null): void
    {
        $rules = ['required', 'string', PoliticaContrasena::regla()];
        if ($confirmation !== null) {
            $rules[] = 'confirmed';
        }

        Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], ['password' => $rules])->validate();
    }

    private function booleano(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
