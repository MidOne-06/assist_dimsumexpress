<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PortalAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_operation_accounts_are_sent_directly_to_their_operation(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local portal', 'tipo' => 'tienda', 'activo' => true]);
        $colaborador = User::factory()->create(['activo' => true]);
        $colaborador->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        Colaborador::create([
            'user_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador portal',
            'documento_identidad' => 'PORTAL-001',
            'activo' => true,
        ]);

        $administrador = User::factory()->create(['activo' => true]);
        $administrador->givePermissionTo(Permission::findOrCreate('Access:AdminPanel', 'web'));

        $this->actingAs($colaborador)
            ->get(route('acceso.portal'))
            ->assertRedirect(route('marcacion.show'));

        $this->actingAs($administrador)
            ->get(route('acceso.portal'))
            ->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_supervisor_and_administrator_sees_an_explicit_operation_selector(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $supervisor = User::factory()->create([
            'email' => 'supervisor.portal@example.test',
            'password' => Hash::make('ClavePrueba2026!'),
            'activo' => true,
        ]);
        $supervisor->assignRole('supervisor');

        $this->withSession([
            '_token' => 'csrf-portal',
            'url.intended' => route('filament.admin.pages.dashboard'),
        ])->post(route('login'), [
            '_token' => 'csrf-portal',
            'email' => $supervisor->email,
            'password' => 'ClavePrueba2026!',
        ])->assertRedirect(route('acceso.portal'));

        $this->actingAs($supervisor)
            ->get(route('acceso.portal'))
            ->assertOk()
            ->assertSee('¿Qué deseas hacer?')
            ->assertSee('Registrar visita')
            ->assertSee('Panel administrativo')
            ->assertDontSee('Marcar asistencia');
    }

    public function test_a_multi_role_collaborator_can_choose_all_of_its_authorized_operations(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $sucursal = Sucursal::create(['nombre' => 'Local mixto', 'tipo' => 'tienda', 'activo' => true]);
        $usuario = User::factory()->create(['activo' => true]);
        $usuario->assignRole('supervisor');
        Colaborador::create([
            'user_id' => $usuario->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Supervisor colaborador',
            'documento_identidad' => 'PORTAL-002',
            'activo' => true,
        ]);

        $this->actingAs($usuario)
            ->get(route('acceso.portal'))
            ->assertOk()
            ->assertSee('Marcar asistencia')
            ->assertSee('Registrar visita')
            ->assertSee('Panel administrativo');
    }

    public function test_the_main_link_requires_authentication_and_the_legacy_login_pwa_entry_stays_valid(): void
    {
        $this->get(route('acceso.portal'))
            ->assertRedirect(route('login'));

        $this->get(route('pwa.manifest'))
            ->assertJsonPath('start_url', route('login'))
            ->assertJsonPath('id', route('login'));
    }
}
