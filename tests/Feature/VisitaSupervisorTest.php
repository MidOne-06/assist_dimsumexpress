<?php

namespace Tests\Feature;

use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VisitaSupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_scan_registers_one_daily_visit_only_for_an_assigned_location(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $propia = Sucursal::create(['nombre' => 'Local propio', 'tipo' => 'tienda', 'activo' => true]);
        $ajena = Sucursal::create(['nombre' => 'Local ajeno', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $supervisor->givePermissionTo(Permission::findOrCreate('Registrar:VisitaSupervisor', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($propia);

        $this->actingAs($supervisor)
            ->get($propia->enlaceVisitaSupervisor())
            ->assertOk()
            ->assertSee('Visita registrada');

        $this->actingAs($supervisor)
            ->get($propia->enlaceVisitaSupervisor())
            ->assertOk()
            ->assertSee('Visita ya registrada hoy');

        $this->assertSame(1, VisitaSupervisor::query()->count());

        $this->actingAs($supervisor)
            ->get($ajena->enlaceVisitaSupervisor())
            ->assertForbidden();
    }
}
