<?php

namespace Tests\Feature;

use App\Filament\Pages\AsignarTurnos;
use App\Filament\Pages\CalendarioTurnos;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupervisionTurnosTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_assigns_future_shifts_only_to_collaborators_of_a_selected_location(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:AsignarTurnos', 'web'),
            Permission::findOrCreate('AsignarMasivo:AsignarTurnos', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($propia);
        $colaboradorPropio = $this->colaborador($propia);
        $this->colaborador($ajena);
        $turno = Turno::create(['nombre' => 'Turno prueba', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $fecha = now()->addDay()->toDateString();

        Livewire::actingAs($supervisor)
            ->test(AsignarTurnos::class)
            ->set('data.sucursal_id', $propia->id)
            ->set('data.colaborador_ids', [$colaboradorPropio->id])
            ->set('data.turno_id', $turno->id)
            ->set('data.fecha_inicio', $fecha)
            ->set('data.fecha_fin', $fecha)
            ->set('data.dias_semana', [(string) now()->addDay()->isoWeekday()])
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asignaciones_turno', [
            'colaborador_id' => $colaboradorPropio->id,
            'turno_id' => $turno->id,
            'fecha' => $fecha,
        ]);
    }

    public function test_calendar_resets_an_out_of_scope_location_for_a_supervisor(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:CalendarioTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($propia);

        Livewire::actingAs($supervisor)
            ->test(CalendarioTurnos::class)
            ->set('sucursalId', $ajena->id)
            ->assertSet('sucursalId', $propia->id);
    }

    private function sucursal(string $nombre): Sucursal
    {
        return Sucursal::create(['nombre' => $nombre, 'tipo' => 'tienda', 'activo' => true]);
    }

    private function colaborador(Sucursal $sucursal): Colaborador
    {
        return Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador ' . uniqid(),
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);
    }
}
