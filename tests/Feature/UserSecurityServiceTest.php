<?php

namespace Tests\Feature;

use App\Models\Sucursal;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_scoped_supervisor_and_revokes_access_when_deactivated(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $actor->givePermissionTo(
            Permission::findOrCreate('Create:User', 'web'),
            Permission::findOrCreate('Update:User', 'web'),
        );
        $sucursal = Sucursal::create(['nombre' => 'Local supervisor', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = Role::findByName('supervisor', 'web');

        $usuario = app(UserService::class)->crear($actor, [
            'name' => '  Carmen   Supervisora ',
            'email' => ' CARMEN.SUPERVISORA@DIMSUM.TEST ',
            'password' => 'ClaveSegura2026!A',
            'password_confirmation' => 'ClaveSegura2026!A',
            'roles' => [$supervisor->id],
            'sucursalesSupervisadas' => [$sucursal->id],
            'activo' => true,
        ]);

        $this->assertSame('Carmen Supervisora', $usuario->name);
        $this->assertSame('carmen.supervisora@dimsum.test', $usuario->email);
        $this->assertTrue(Hash::check('ClaveSegura2026!A', $usuario->password));
        $this->assertTrue($usuario->hasRole('supervisor'));
        $this->assertTrue($usuario->sucursalesSupervisadas->contains($sucursal));

        DB::table('sessions')->insert([
            'id' => 'sesion-supervisor',
            'user_id' => $usuario->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        app(UserService::class)->cambiarEstado($actor, $usuario, false);

        $this->assertFalse($usuario->fresh()->estaActivoParaAcceso());
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-supervisor']);
        $this->withSession(['_token' => 'token-inactivo'])->post(route('login'), [
            '_token' => 'token-inactivo',
            'email' => 'carmen.supervisora@dimsum.test',
            'password' => 'ClaveSegura2026!A',
        ])->assertSessionHasErrors('email');
    }

    public function test_it_prevents_a_non_superadmin_from_elevating_an_account(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::findOrCreate('Create:User', 'web'));
        $superAdmin = Role::findByName('super_admin', 'web');

        $this->expectException(ValidationException::class);

        app(UserService::class)->crear($actor, [
            'name' => 'Cuenta privilegiada',
            'email' => 'privilegiada@dimsum.test',
            'password' => 'ClaveSegura2026!A',
            'password_confirmation' => 'ClaveSegura2026!A',
            'roles' => [$superAdmin->id],
            'activo' => true,
        ]);
    }

    public function test_it_keeps_the_lifecycle_of_linked_collaborators_out_of_user_management(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $actor->givePermissionTo(Permission::findOrCreate('Update:User', 'web'));
        $sucursal = Sucursal::create(['nombre' => 'Local colaborador', 'tipo' => 'tienda', 'activo' => true]);
        $usuario = User::factory()->create();
        $usuario->assignRole('operador');
        $usuario->colaborador()->create([
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Operador vinculado',
            'documento_identidad' => 'OPERADOR-001',
            'activo' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(UserService::class)->cambiarEstado($actor, $usuario, false);
    }
}
