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

        $this->assertSame(1, VisitaSupervisor::query()
            ->where('supervisor_id', $supervisor->id)
            ->where('sucursal_id', $propia->id)
            ->whereDate('fecha', today())
            ->count());

        $this->actingAs($supervisor)
            ->get($ajena->enlaceVisitaSupervisor())
            ->assertForbidden();
    }

    public function test_administrator_roles_cannot_register_a_supervisor_visit_from_the_link(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $sucursal = Sucursal::create(['nombre' => 'Local de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $administrador = User::factory()->create();
        $administrador->assignRole('super_admin');

        $this->actingAs($administrador)
            ->get($sucursal->enlaceVisitaSupervisor())
            ->assertForbidden();

        $this->assertDatabaseMissing('visitas_supervisor', [
            'supervisor_id' => $administrador->id,
            'sucursal_id' => $sucursal->id,
            'fecha' => today()->toDateString(),
        ]);
    }

    public function test_station_preview_shows_the_qr_without_recording_a_visit(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local QR', 'tipo' => 'tienda', 'activo' => true]);

        $this->get($sucursal->enlaceEstacionVisita())
            ->assertOk()
            ->assertSee('Código QR de visita de supervisión')
            ->assertSee('La supervisora debe iniciar sesión');

        $this->assertDatabaseMissing('visitas_supervisor', ['sucursal_id' => $sucursal->id]);
    }
}
