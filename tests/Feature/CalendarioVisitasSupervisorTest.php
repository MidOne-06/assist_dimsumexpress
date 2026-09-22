<?php

namespace Tests\Feature;

use App\Filament\Pages\CalendarioVisitasSupervisor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VisitaSupervisor;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarioVisitasSupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_supervisor_visits_in_the_monthly_calendar_and_can_filter_them(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $administrador = User::factory()->create();
        $administrador->assignRole('administrador');
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
            ->set('supervisorId', $ana->id)
            ->assertSee('Ana Supervisora')
            ->assertDontSee('Beatriz Supervisora');
    }
}
