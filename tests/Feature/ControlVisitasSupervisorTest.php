<?php

namespace Tests\Feature;

use App\Models\Sucursal;
use App\Models\PuntoVenta;
use App\Models\User;
use App\Models\VisitaSupervisor;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
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
        $administrador = User::factory()->create(['activo' => true]);
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

        $this->actingAs($administrador, 'web')
            ->get('/admin/control-visitas-supervisor')
            ->assertOk()
            ->assertSee('Control de visitas de supervisión')
            ->assertSee('Supervisora de prueba')
            ->assertSee('En curso');
    }

    public function test_supervisor_cannot_open_the_administrative_visit_control(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $supervisor = User::factory()->create(['activo' => true]);
        $supervisor->assignRole('supervisor');

        $this->actingAs($supervisor, 'web')
            ->get('/admin/control-visitas-supervisor')
            ->assertForbidden();
    }

    public function test_control_keeps_historical_visits_from_an_inactive_location_visible(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $sucursal = Sucursal::create(['nombre' => 'Local histórico', 'tipo' => 'tienda', 'activo' => false]);
        $supervisor = User::factory()->create(['name' => 'Supervisora histórica']);
        $administrador = User::factory()->create(['activo' => true]);
        $administrador->assignRole('administrador');

        VisitaSupervisor::create([
            'supervisor_id' => $supervisor->id,
            'sucursal_id' => $sucursal->id,
            'fecha' => today()->subDay(),
            'fecha_hora' => now()->subDay(),
            'estado' => VisitaSupervisor::HISTORICA,
            'ingreso_en' => now()->subDay(),
        ]);

        $this->actingAs($administrador, 'web')
            ->get('/admin/control-visitas-supervisor')
            ->assertOk()
            ->assertSee('Local histórico')
            ->assertSee('Local inactivo');
    }

    public function test_regularization_rejects_a_station_from_another_location_on_the_server(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $sucursal = Sucursal::create(['nombre' => 'Local de la visita', 'tipo' => 'tienda', 'activo' => true]);
        $otraSucursal = Sucursal::create(['nombre' => 'Otro local', 'tipo' => 'tienda', 'activo' => true]);
        $puntoAjeno = PuntoVenta::create(['sucursal_id' => $otraSucursal->id, 'nombre' => 'Caja ajena', 'activo' => true]);
        $supervisor = User::factory()->create();
        $administrador = User::factory()->create(['activo' => true]);
        $administrador->assignRole('administrador');
        $visita = VisitaSupervisor::create([
            'supervisor_id' => $supervisor->id,
            'sucursal_id' => $sucursal->id,
            'fecha' => today(),
            'fecha_hora' => now()->subHour(),
            'estado' => VisitaSupervisor::EN_CURSO,
            'ingreso_en' => now()->subHour(),
        ]);

        $this->actingAs($administrador, 'web');
        $regularizar = new ReflectionMethod(app(\App\Filament\Pages\ControlVisitasSupervisor::class), 'regularizar');

        try {
            $regularizar->invoke(app(\App\Filament\Pages\ControlVisitasSupervisor::class), $visita, [
                'salida_en' => now()->subMinute()->toDateTimeString(),
                'punto_venta_salida_id' => $puntoAjeno->id,
                'regularizacion_motivo' => 'Validación de punto de venta de otro local.',
            ]);
            $this->fail('La regularización no debe aceptar una estación ajena.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('punto_venta_salida_id', $exception->errors());
        }

        $this->assertDatabaseHas('visitas_supervisor', [
            'id' => $visita->id,
            'estado' => VisitaSupervisor::EN_CURSO,
        ]);
    }
}
