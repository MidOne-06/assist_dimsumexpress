<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RoleService;
use App\Support\CatalogoPermisos;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_custom_role_with_only_existing_permissions(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $actor = $this->superadministradorConPermisos();

        $rol = app(RoleService::class)->crear($actor, [
            'name' => ' Auditor Local ',
            'custom_permissions_tab' => ['ViewAny:Marcacion', 'View:Marcacion'],
        ]);

        $this->assertSame('auditor_local', $rol->name);
        $this->assertTrue($rol->hasPermissionTo('ViewAny:Marcacion'));
        $this->assertTrue($rol->hasPermissionTo('View:Marcacion'));

        $this->expectException(ValidationException::class);

        app(RoleService::class)->crear($actor, [
            'name' => 'Auditor Local',
            'custom_permissions_tab' => ['Permiso:Inexistente'],
        ]);
    }

    public function test_all_special_actions_are_registered_and_grouped_in_the_role_catalog(): void
    {
        $this->seed(RolesYPermisosSeeder::class);

        $this->assertSame(
            CatalogoPermisos::nombres(),
            config('filament-shield.custom_permissions'),
        );
        $this->assertSame(
            [],
            array_values(array_diff(
                CatalogoPermisos::nombres(),
                Permission::query()->where('guard_name', 'web')->pluck('name')->all(),
            )),
        );
        $this->assertCount(7, CatalogoPermisos::categorias());
    }

    public function test_it_preserves_system_roles_and_superadmin_access(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $actor = $this->superadministradorConPermisos();
        $superAdmin = Role::findByName('super_admin', 'web');

        app(RoleService::class)->actualizar($actor, $superAdmin, [
            'name' => 'super_admin',
        ]);

        $this->assertSame(
            Permission::query()->where('guard_name', 'web')->count(),
            $superAdmin->fresh()->permissions()->count(),
        );

        $this->expectException(ValidationException::class);

        app(RoleService::class)->actualizar($actor, Role::findByName('operador', 'web'), [
            'name' => 'operador_nuevo',
            'custom_permissions_tab' => ['Registrar:Marcacion'],
        ]);
    }

    private function superadministradorConPermisos(): User
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $actor->givePermissionTo(
            Permission::findOrCreate('Create:Role', 'web'),
            Permission::findOrCreate('Update:Role', 'web'),
        );

        return $actor;
    }
}
