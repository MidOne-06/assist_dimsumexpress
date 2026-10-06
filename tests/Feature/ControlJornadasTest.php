<?php

namespace Tests\Feature;

use App\Filament\Pages\ControlJornadas;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ControlJornadasTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_exceptional_marks_without_a_detected_shift_are_visible_without_becoming_a_scheduled_journey(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:ControlJornadas', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador excepcional',
            'documento_identidad' => 'CJ-' . uniqid(),
            'cargo' => 'Operario',
            'activo' => true,
        ]);
        $fecha = now()->startOfMonth()->addDay()->setTime(8, 15);
        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => $fecha,
        ]);

        Livewire::actingAs($supervisor)
            ->test(ControlJornadas::class)
            ->set('mes', $fecha->format('Y-m'))
            ->assertSee('Colaborador excepcional')
            ->assertSee('Sin turno · marcaciones registradas')
            ->assertSee('08:15');
    }

    public function test_regularization_action_has_no_visible_header_trigger(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local sin disparador', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:ControlJornadas', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador sin disparador',
            'documento_identidad' => 'CJ-OCULTO-' . uniqid(),
            'activo' => true,
        ]);

        $pagina = Livewire::actingAs($supervisor)->test(ControlJornadas::class);

        $this->assertStringContainsString('display: none !important', $pagina->html());
    }

    public function test_supervisor_regularizes_an_exceptional_journey_through_the_native_modal(): void
    {
        Carbon::setTestNow('2026-10-06 18:00:00');
        $sucursal = Sucursal::create(['nombre' => 'Local regularizable', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:ControlJornadas', 'web'),
            Permission::findOrCreate('Regularizar:Jornada', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador por regularizar',
            'documento_identidad' => 'CJ-REG-' . uniqid(),
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Turno para regularizar',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'tolerancia_entrada_minutos' => 15,
            'tolerancia_salida_minutos' => 15,
            'activo' => true,
        ]);
        $fecha = now()->subDay()->toDateString();
        $entrada = Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => "$fecha 08:05:00",
        ]);

        Livewire::actingAs($supervisor)
            ->test(ControlJornadas::class)
            ->set('mes', now()->format('Y-m'))
            ->call('abrirRegularizacionJornada', $fecha)
            ->assertActionMounted('regularizarJornada')
            ->assertSet('fechaRegularizacion', $fecha)
            ->assertSet('mountedActions.0.data.fecha', $fecha)
            ->set('mountedActions.0.data.turno_id', $turno->id)
            ->set('mountedActions.0.data.motivo', 'Validación de jornada no programada con lectura QR real.')
            ->callMountedAction()
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asignaciones_turno', [
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'origen' => 'regularizado_manual',
        ]);
        $this->assertDatabaseHas('marcaciones', [
            'id' => $entrada->id,
            'turno_id' => $turno->id,
            'fecha_hora' => "$fecha 08:05:00",
        ]);
    }

    public function test_regularization_modal_cannot_open_without_an_exceptional_day_argument(): void
    {
        Carbon::setTestNow('2026-10-06 18:00:00');
        $sucursal = Sucursal::create(['nombre' => 'Local sin jornada excepcional', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:ControlJornadas', 'web'),
            Permission::findOrCreate('Regularizar:Jornada', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador sin marcaciones excepcionales',
            'documento_identidad' => 'CJ-SIN-' . uniqid(),
            'activo' => true,
        ]);

        Livewire::actingAs($supervisor)
            ->test(ControlJornadas::class)
            ->call('abrirRegularizacionJornada', '')
            ->assertSet('mountedActions', []);
    }
}
