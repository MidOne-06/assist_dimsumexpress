<?php

namespace Tests\Feature;

use App\Filament\Pages\AsignarTurnos;
use App\Filament\Pages\CalendarioTurnos;
use App\Filament\Resources\AsignacionTurnos\Pages\ListAsignacionTurnos;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
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

    public function test_calendar_only_renders_shifts_and_collaborators_for_the_supervisors_location_and_month(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:CalendarioTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($propia);
        $colaboradorPropio = $this->colaborador($propia);
        $colaboradorAjeno = $this->colaborador($ajena);
        $turnoPropio = Turno::create(['nombre' => 'Turno propio', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $turnoAjeno = Turno::create(['nombre' => 'Turno ajeno', 'hora_inicio' => '10:00', 'hora_fin' => '19:00', 'activo' => true]);

        AsignacionTurno::create(['colaborador_id' => $colaboradorPropio->id, 'turno_id' => $turnoPropio->id, 'fecha' => now()->toDateString()]);
        AsignacionTurno::create(['colaborador_id' => $colaboradorAjeno->id, 'turno_id' => $turnoAjeno->id, 'fecha' => now()->toDateString()]);

        Livewire::actingAs($supervisor)
            ->test(CalendarioTurnos::class)
            ->assertSee('Turno propio')
            ->assertDontSee('Turno ajeno')
            ->assertSee($colaboradorPropio->nombre_completo)
            ->assertDontSee($colaboradorAjeno->nombre_completo);
    }

    public function test_calendar_matches_an_entry_to_its_assigned_shift_and_keeps_historical_rows(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:CalendarioTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $turnoProgramado = Turno::create(['nombre' => 'Apertura', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'tolerancia_entrada_minutos' => 10, 'activo' => true]);
        $turnoDistinto = Turno::create(['nombre' => 'Cierre', 'hora_inicio' => '14:00', 'hora_fin' => '22:00', 'activo' => true]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turnoProgramado->id,
            'fecha' => now()->toDateString(),
        ]);

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turnoDistinto->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now()->setTime(14, 0, 1),
        ]);

        $this->actingAs($supervisor);
        $calendario = app(CalendarioTurnos::class);
        $calendario->mount();

        $estado = $calendario->estadoAsignacion($asignacion->fresh('turno'));
        $this->assertSame('turno_distinto', $estado['estado']);
        $this->assertSame('14:00:01', $estado['hora']);

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turnoProgramado->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now()->setTime(8, 10, 1),
        ]);

        $calendario = app(CalendarioTurnos::class);
        $calendario->mount();
        $estado = $calendario->estadoAsignacion($asignacion->fresh('turno'));
        $this->assertSame('tardanza', $estado['estado']);
        $this->assertSame('Tardanza de 1 min', $estado['label']);
        $this->assertSame('08:10:01', $estado['hora']);

        $colaborador->update(['activo' => false]);
        $turnoProgramado->update(['activo' => false]);

        $calendario = app(CalendarioTurnos::class);
        $calendario->mount();
        $this->assertCount(1, $calendario->colaboradores);
        $this->assertCount(1, $calendario->turnosActivos);
    }

    public function test_supervisor_assigns_a_date_range_from_the_assignments_list(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('ViewAny:AsignacionTurno', 'web'),
            Permission::findOrCreate('AsignarMasivo:AsignarTurnos', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $turno = Turno::create(['nombre' => 'Turno por rango', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $desde = now()->addDay()->toDateString();
        $hasta = now()->addDays(3)->toDateString();
        $dias = collect(range(1, 3))
            ->map(fn (int $diasDesdeManana): string => (string) now()->addDays($diasDesdeManana)->isoWeekday())
            ->unique()
            ->values()
            ->all();

        Livewire::actingAs($supervisor)
            ->test(ListAsignacionTurnos::class)
            ->mountAction('asignarPorRango')
            ->set('mountedActions.0.data.sucursal_id', $sucursal->id)
            ->set('mountedActions.0.data.colaborador_ids', [$colaborador->id])
            ->set('mountedActions.0.data.turno_id', $turno->id)
            ->set('mountedActions.0.data.fecha_inicio', $desde)
            ->set('mountedActions.0.data.fecha_fin', $hasta)
            ->set('mountedActions.0.data.dias_semana', $dias)
            ->callMountedAction()
            ->assertHasNoErrors();

        foreach (range(1, 3) as $diasDesdeManana) {
            $this->assertDatabaseHas('asignaciones_turno', [
                'colaborador_id' => $colaborador->id,
                'turno_id' => $turno->id,
                'fecha' => now()->addDays($diasDesdeManana)->toDateString(),
            ]);
        }
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
