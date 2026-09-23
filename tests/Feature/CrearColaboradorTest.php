<?php

namespace Tests\Feature;

use App\Actions\CrearColaborador;
use App\Filament\Resources\Colaboradors\Pages\ListColaboradors;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrearColaboradorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_collaborator_also_creates_an_operator_account(): void
    {
        $this->seed(RolesYPermisosSeeder::class);

        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal de prueba',
            'tipo' => 'planta',
            'activo' => true,
        ]);

        $colaborador = app(CrearColaborador::class)->handle([
            'nombre_completo' => 'María Operadora',
            'email' => 'maria.operadora@example.test',
            'password' => 'ClaveSegura2026!',
            'documento_identidad' => 'DNI-12345678',
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => null,
            'cargo' => 'Cajera',
            'fecha_ingreso' => now()->toDateString(),
            'activo' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $colaborador->user_id,
            'email' => 'maria.operadora@example.test',
        ]);
        $this->assertTrue($colaborador->user->hasRole('operador'));
        $this->assertTrue($colaborador->user->can('Registrar:Marcacion'));
        $this->assertTrue($colaborador->user->can('View:MiHorario'));
    }

    public function test_collaborator_modal_renders_with_the_responsive_native_layout(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $usuario = User::factory()->create();
        $usuario->assignRole('super_admin');

        Livewire::actingAs($usuario)
            ->test(ListColaboradors::class)
            ->mountAction('create')
            ->assertHasNoErrors();
    }
}
