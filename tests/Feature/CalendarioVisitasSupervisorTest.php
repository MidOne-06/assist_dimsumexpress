<?php

namespace Tests\Feature;

use App\Filament\Pages\CalendarioVisitasSupervisor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CalendarioVisitasSupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_supervisor_visits_in_the_monthly_calendar_and_can_filter_them(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $administrador = User::factory()->create();
        $administrador->assignRole('administrador');
        $this->actingAs($administrador);
        $this->assertTrue(CalendarioVisitasSupervisor::shouldRegisterNavigation());
        $ana = User::factory()->create(['name' => 'Ana Supervisora']);
        $ana->assignRole('supervisor');
        $beatriz = User::factory()->create(['name' => 'Beatriz Supervisora']);
        $beatriz->assignRole('supervisor');
        $local = Sucursal::create(['nombre' => 'Tienda Centro', 'tipo' => 'tienda', 'activo' => true]);

        VisitaSupervisor::create([
            'supervisor_id' => $ana->id,
            'sucursal_id' => $local->id,
            'fecha' => now()->toDateString(),
            'fecha_hora' => now()->setTime(9, 30),
        ]);

        Livewire::actingAs($administrador)
            ->test(CalendarioVisitasSupervisor::class)
            ->assertSee('Ana Supervisora')
            ->assertSee('Beatriz Supervisora')
            ->assertSee('Tienda Centro')
            ->assertSee('09:30:00')
            ->set('supervisorId', $ana->id)
            ->assertSee('Ana Supervisora')
            ->assertDontSee('Beatriz Supervisora')
            ->call('limpiarFiltros')
            ->assertSet('supervisorId', null)
            ->assertSet('sucursalId', null)
            ->call('irAHoy')
            ->assertSet('mes', now()->format('Y-m'))
            ->assertDispatched('visitas-ir-a-hoy');
    }

    public function test_calendar_keeps_historical_visits_visible_after_a_supervisor_role_is_removed(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $administrador = User::factory()->create();
        $administrador->assignRole('administrador');
        $exSupervisora = User::factory()->create(['name' => 'Supervisora histórica']);
        $exSupervisora->assignRole('supervisor');
        $local = Sucursal::create(['nombre' => 'Tienda histórica', 'tipo' => 'tienda', 'activo' => true]);

        VisitaSupervisor::create([
            'supervisor_id' => $exSupervisora->id,
            'sucursal_id' => $local->id,
            'fecha' => now()->toDateString(),
            'fecha_hora' => now()->setTime(10, 15),
        ]);
        $exSupervisora->removeRole('supervisor');

        Livewire::actingAs($administrador)
            ->test(CalendarioVisitasSupervisor::class)
            ->assertSee('Supervisora histórica')
            ->assertSee('Tienda histórica');
    }

    public function test_limited_user_only_sees_visits_and_filters_from_assigned_locations(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $propia = Sucursal::create(['nombre' => 'Local propio', 'tipo' => 'tienda', 'activo' => true]);
        $ajena = Sucursal::create(['nombre' => 'Local ajeno', 'tipo' => 'tienda', 'activo' => true]);
        $usuario = User::factory()->create();
        $usuario->assignRole('supervisor');
        $usuario->givePermissionTo(Permission::findOrCreate('View:CalendarioVisitasSupervisor', 'web'));
        $usuario->sucursalesSupervisadas()->attach($propia);
        $supervisorPropio = User::factory()->create(['name' => 'Supervisor propio']);
        $supervisorPropio->assignRole('supervisor');
        $supervisorPropio->sucursalesSupervisadas()->attach($propia);
        $supervisorAjeno = User::factory()->create(['name' => 'Supervisor ajeno']);
        $supervisorAjeno->assignRole('supervisor');
        $supervisorAjeno->sucursalesSupervisadas()->attach($ajena);

        VisitaSupervisor::create([
            'supervisor_id' => $supervisorPropio->id,
            'sucursal_id' => $propia->id,
            'fecha' => now()->toDateString(),
            'fecha_hora' => now(),
        ]);
        VisitaSupervisor::create([
            'supervisor_id' => $supervisorAjeno->id,
            'sucursal_id' => $ajena->id,
            'fecha' => now()->toDateString(),
            'fecha_hora' => now(),
        ]);

        Livewire::actingAs($usuario)
            ->test(CalendarioVisitasSupervisor::class)
            ->assertSee('Supervisor propio')
            ->assertSee('Local propio')
            ->assertDontSee('Supervisor ajeno')
            ->assertDontSee('Local ajeno');
    }
}
