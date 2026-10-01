<?php

namespace Tests\Feature;

use App\Filament\Resources\IncidenciaMarcacions\IncidenciaMarcacionResource;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Policies\IncidenciaMarcacionPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class IncidenciasMarcacionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_detects_a_missing_final_exit_after_the_shift_window_closes(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:05');
        [$colaborador, $asignacion] = $this->crearJornada();
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);

        Carbon::setTestNow('2026-09-22 03:00:01');
        $this->artisan('asistencia:detectar-incidencias')->assertExitCode(0);

        $this->assertDatabaseHas('incidencias_marcacion', [
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'tipo' => IncidenciaMarcacion::TIPO_SALIDA_TURNO_PENDIENTE,
        ]);
        $this->assertSame(1, Marcacion::query()->where('colaborador_id', $colaborador->id)->count());
    }

    public function test_detects_a_missing_return_without_inventing_a_return_or_exit(): void
    {
        Carbon::setTestNow('2026-09-21 12:00:08');
        [$colaborador, $asignacion] = $this->crearJornada();
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        Carbon::setTestNow('2026-09-21 12:30:11');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA_REFRIGERIO);

        Carbon::setTestNow('2026-09-22 03:00:01');
        $this->artisan('asistencia:detectar-incidencias')->assertExitCode(0);

        $this->assertDatabaseHas('incidencias_marcacion', [
            'asignacion_turno_id' => $asignacion->id,
            'tipo' => IncidenciaMarcacion::TIPO_RETORNO_REFRIGERIO_PENDIENTE,
        ]);
        $this->assertSame(2, Marcacion::query()->where('colaborador_id', $colaborador->id)->count());
        $this->assertDatabaseMissing('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'tipo' => Marcacion::TIPO_REGRESO_REFRIGERIO,
        ]);
    }

    public function test_does_not_flag_a_shift_with_final_exit_or_a_shift_that_is_still_active(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$colaborador, $asignacion] = $this->crearJornada();
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);

        $this->artisan('asistencia:detectar-incidencias')->assertExitCode(0);
        $this->assertDatabaseCount('incidencias_marcacion', 0);

        Carbon::setTestNow('2026-09-21 16:45:22');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA);
        Carbon::setTestNow('2026-09-21 17:10:01');
        $this->artisan('asistencia:detectar-incidencias')->assertExitCode(0);

        $this->assertDatabaseCount('incidencias_marcacion', 0);
    }

    public function test_a_reported_omission_is_traceable_without_creating_a_false_mark(): void
    {
        Carbon::setTestNow('2026-09-21 17:30:12');
        [$colaborador, $asignacion] = $this->crearJornada();

        $incidencia = IncidenciaMarcacion::create([
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'tipo' => IncidenciaMarcacion::TIPO_MARCACION_OMITIDA,
            'detectada_en' => now(),
            'observacion_reporte' => 'Salida a refrigerio no escaneada por falla del equipo.',
        ]);

        $this->assertSame('Marcación omitida reportada', IncidenciaMarcacion::etiquetaTipo($incidencia->tipo));
        $this->assertSame(0, Marcacion::query()->where('colaborador_id', $colaborador->id)->count());
        $this->assertDatabaseHas('incidencias_marcacion', [
            'id' => $incidencia->id,
            'observacion_reporte' => 'Salida a refrigerio no escaneada por falla del equipo.',
        ]);
    }

    public function test_scope_and_policy_use_the_actual_local_of_a_coverage_incident(): void
    {
        [$colaborador, $asignacion] = $this->crearJornada();
        $localCobertura = Sucursal::create([
            'nombre' => 'Local de cobertura',
            'tipo' => 'tienda',
            'activo' => true,
        ]);
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:IncidenciaMarcacion', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($localCobertura);
        $incidencia = IncidenciaMarcacion::create([
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $localCobertura->id,
            'tipo' => IncidenciaMarcacion::TIPO_SALIDA_TURNO_PENDIENTE,
            'detectada_en' => now(),
        ]);

        $this->actingAs($supervisor);

        $this->assertSame([$incidencia->id], IncidenciaMarcacionResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(app(IncidenciaMarcacionPolicy::class)->view($supervisor, $incidencia));
        $schema = (new \ReflectionMethod(IncidenciaMarcacionResource::class, 'detalleSchema'))
            ->invoke(null, $incidencia->load(['colaborador', 'asignacionTurno.turno', 'sucursal', 'puntoVenta', 'resueltaPor']));

        $this->assertCount(5, $schema);
        $this->assertInstanceOf(\Filament\Schemas\Components\Section::class, $schema[0]);
    }

    public function test_incidents_list_renders_the_operational_filters_and_empty_state(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(Permission::findOrCreate('ViewAny:IncidenciaMarcacion', 'web'));

        Livewire::actingAs($usuario)
            ->test(\App\Filament\Resources\IncidenciaMarcacions\Pages\ListIncidenciaMarcacions::class)
            ->assertSee('Sin incidencias');
    }

    /** @return array{Colaborador, AsignacionTurno} */
    private function crearJornada(): array
    {
        $sucursal = Sucursal::create(['nombre' => 'Local de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $user = User::factory()->create();
        $colaborador = Colaborador::create([
            'user_id' => $user->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador de prueba',
            'documento_identidad' => 'INC-' . uniqid(),
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Turno de prueba',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'activo' => true,
        ]);

        return [$colaborador, AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => now()->toDateString(),
        ])];
    }

    private function marcar(Colaborador $colaborador, AsignacionTurno $asignacion, string $tipo): Marcacion
    {
        return Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $asignacion->turno_id,
            'sucursal_id' => $colaborador->sucursal_id,
            'tipo' => $tipo,
            'fecha_hora' => now(),
        ]);
    }
}
