<?php

namespace Tests\Feature;

use App\Console\Commands\DetectarIncidenciasMarcacion;
use App\Filament\Resources\Marcacions\MarcacionResource;
use App\Filament\Resources\Marcacions\Tables\MarcacionsTable;
use App\Filament\Widgets\ResumenMarcaciones;
use App\Models\AsignacionTurno;
use App\Models\CoberturaOperativa;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Services\MarcacionSpreadsheetService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarcacionesRobustezTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_persists_the_operational_coverage_link_with_the_real_marking(): void
    {
        [$colaborador, $asignacion, $sucursal] = $this->crearJornada();
        $puntoVenta = PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja 1',
            'activo' => true,
        ]);
        $cobertura = CoberturaOperativa::create([
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta->id,
            'detectada_en' => now(),
        ]);

        $marcacion = Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $asignacion->turno_id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta->id,
            'cobertura_operativa_id' => $cobertura->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now(),
        ]);

        $this->assertSame($cobertura->id, $marcacion->fresh()->cobertura_operativa_id);
    }

    public function test_an_assignment_can_keep_distinct_incidents_without_losing_traceability(): void
    {
        [$colaborador, $asignacion, $sucursal] = $this->crearJornada();

        IncidenciaMarcacion::create([
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => IncidenciaMarcacion::TIPO_MARCACION_OMITIDA,
            'detectada_en' => now(),
        ]);
        IncidenciaMarcacion::create([
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => IncidenciaMarcacion::TIPO_SALIDA_TURNO_PENDIENTE,
            'detectada_en' => now(),
        ]);

        $this->assertDatabaseCount('incidencias_marcacion', 2);
    }

    public function test_detected_incident_uses_the_actual_station_not_the_collaborators_home_branch(): void
    {
        Carbon::setTestNow('2026-09-26 08:00:00');
        [$colaborador, $asignacion, $sucursalBase] = $this->crearJornada();
        $sucursalCobertura = Sucursal::create(['nombre' => 'Local de cobertura', 'tipo' => 'tienda', 'activo' => true]);

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $asignacion->turno_id,
            'sucursal_id' => $sucursalCobertura->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now(),
        ]);

        Carbon::setTestNow('2026-09-27 03:01:00');
        $this->artisan(DetectarIncidenciasMarcacion::class)->assertExitCode(0);

        $this->assertDatabaseHas('incidencias_marcacion', [
            'asignacion_turno_id' => $asignacion->id,
            'sucursal_id' => $sucursalCobertura->id,
            'tipo' => IncidenciaMarcacion::TIPO_SALIDA_TURNO_PENDIENTE,
        ]);
        $this->assertNotSame($sucursalBase->id, $sucursalCobertura->id);
    }

    public function test_dashboard_summary_and_records_are_restricted_to_the_supervisors_locations(): void
    {
        $supervisor = User::factory()->create();
        $propia = Sucursal::create(['nombre' => 'Local propio', 'tipo' => 'tienda', 'activo' => true]);
        $ajena = Sucursal::create(['nombre' => 'Local ajeno', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor->sucursalesSupervisadas()->attach($propia);
        [$colaborador, $asignacion] = $this->crearJornada($propia);
        $externo = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $ajena->id,
            'nombre_completo' => 'Colaborador externo',
            'documento_identidad' => 'EXT-'.uniqid(),
            'activo' => true,
        ]);

        Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $asignacion->turno_id, 'sucursal_id' => $propia->id, 'tipo' => Marcacion::TIPO_ENTRADA, 'fecha_hora' => now()]);
        Marcacion::create(['colaborador_id' => $externo->id, 'sucursal_id' => $ajena->id, 'tipo' => Marcacion::TIPO_ENTRADA, 'fecha_hora' => now()]);

        $this->actingAs($supervisor);

        $this->assertSame(1, MarcacionResource::getEloquentQuery()->count());
        Livewire::test(ResumenMarcaciones::class)
            ->assertSee('Marcaciones de hoy')
            ->assertSee('Jornadas en curso')
            ->assertSee('1');
    }

    public function test_collaborator_filter_includes_people_covering_a_visible_location(): void
    {
        $localVisible = Sucursal::create(['nombre' => 'Local visible', 'tipo' => 'tienda', 'activo' => true]);
        $localBaseExterno = Sucursal::create(['nombre' => 'Local base externo', 'tipo' => 'tienda', 'activo' => true]);
        $externo = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $localBaseExterno->id,
            'nombre_completo' => 'Cobertura visible',
            'documento_identidad' => 'COB-'.uniqid(),
            'activo' => true,
        ]);

        Marcacion::create([
            'colaborador_id' => $externo->id,
            'sucursal_id' => $localVisible->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now(),
        ]);

        $opciones = MarcacionsTable::opcionesColaborador([$localVisible->id]);

        $this->assertSame('Cobertura visible', $opciones[$externo->id]);
    }

    public function test_historical_break_return_uses_its_shift_duration_instead_of_a_fixed_hour(): void
    {
        [$colaborador, $asignacion, $sucursal] = $this->crearJornada();
        $asignacion->turno->update(['incluye_refrigerio' => true, 'refrigerio_minutos' => 45]);

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $asignacion->turno_id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_SALIDA_REFRIGERIO,
            'fecha_hora' => '2026-09-26 12:00:00',
        ]);
        $retorno = Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $asignacion->turno_id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_REGRESO_REFRIGERIO,
            'fecha_hora' => '2026-09-26 12:50:00',
        ]);

        $resumen = $retorno->resumenRetornoRefrigerio();

        $this->assertSame('5 min tarde', $resumen['etiqueta']);
        $this->assertSame('2026-09-26 12:45:00', $resumen['esperado']->toDateTimeString());
    }

    public function test_export_uses_an_xlsx_response(): void
    {
        [$colaborador, $asignacion, $sucursal] = $this->crearJornada();
        Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $asignacion->turno_id, 'sucursal_id' => $sucursal->id, 'tipo' => Marcacion::TIPO_ENTRADA, 'fecha_hora' => now()]);

        $response = app(MarcacionSpreadsheetService::class)->exportar(Marcacion::query());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $response->headers->get('Content-Type'));
    }

    public function test_jornada_duration_formats_seconds_without_decimal_minutes(): void
    {
        $metodo = new \ReflectionMethod(MarcacionsTable::class, 'formatearDuracionSegundos');

        $this->assertSame('3 h 49 min 49 s', $metodo->invoke(null, 13_789));
    }

    /** @return array{Colaborador, AsignacionTurno, Sucursal} */
    private function crearJornada(?Sucursal $sucursal = null): array
    {
        $sucursal ??= Sucursal::create(['nombre' => 'Local '.uniqid(), 'tipo' => 'tienda', 'activo' => true]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador '.uniqid(),
            'documento_identidad' => 'DOC-'.uniqid(),
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Apertura '.uniqid(),
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'activo' => true,
        ]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => now()->toDateString(),
        ]);

        return [$colaborador, $asignacion, $sucursal];
    }
}
