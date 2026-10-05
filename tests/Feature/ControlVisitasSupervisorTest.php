<?php

namespace Tests\Feature;

use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ControlVisitasSupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_the_supervisor_visit_control_with_an_open_visit(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $sucursal = Sucursal::create(['nombre' => 'Local control', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create(['name' => 'Supervisora de prueba']);
        $administrador = User::factory()->create();
        $administrador->assignRole('administrador');
        $administrador->givePermissionTo(
            Permission::findByName('Access:AdminPanel', 'web'),
            Permission::findByName('View:ControlVisitasSupervisor', 'web'),
            Permission::findByName('Regularizar:VisitaSupervisor', 'web'),
        );

        VisitaSupervisor::create([
            'supervisor_id' => $supervisor->id,
            'sucursal_id' => $sucursal->id,
            'fecha' => today(),
            'fecha_hora' => now(),
            'estado' => VisitaSupervisor::EN_CURSO,
            'ingreso_en' => now(),
        ]);

        $this->actingAs($administrador)
            ->get('/admin/control-visitas-supervisor')
            ->assertOk()
            ->assertSee('Control de visitas de supervisión')
            ->assertSee('Supervisora de prueba')
            ->assertSee('En curso');
    }

    public function test_supervisor_cannot_open_the_administrative_visit_control(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        $this->actingAs($supervisor)
            ->get('/admin/control-visitas-supervisor')
            ->assertForbidden();
    }
}
