<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Models\ResumenJornada;
use App\Services\RegularizacionJornadaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegularizacionJornadaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_regularization_links_exceptional_marks_and_preserves_their_real_traceability(): void
    {
        Carbon::setTestNow('2026-10-06 18:00:00');
        [$supervisor, $colaborador, $sucursal, $turno] = $this->contexto();
        $fecha = '2026-10-05';
        $entrada = $this->marcacion($colaborador, $sucursal, Marcacion::TIPO_ENTRADA, "$fecha 08:07:12");
        $salidaRefrigerio = $this->marcacion($colaborador, $sucursal, Marcacion::TIPO_SALIDA_REFRIGERIO, "$fecha 12:00:03");
        $regreso = $this->marcacion($colaborador, $sucursal, Marcacion::TIPO_REGRESO_REFRIGERIO, "$fecha 13:00:03");
        $salida = $this->marcacion($colaborador, $sucursal, Marcacion::TIPO_SALIDA, "$fecha 17:01:55");

        $asignacion = app(RegularizacionJornadaService::class)->regularizar($supervisor, $colaborador, $fecha, [
            'turno_id' => $turno->id,
            'motivo' => 'El colaborador cubrió el turno y no tenía programación previa.',
        ]);

        $this->assertSame('regularizado_manual', $asignacion->origen);
        $this->assertSame($supervisor->id, $asignacion->asignado_por);
        $this->assertSame('Regularización: El colaborador cubrió el turno y no tenía programación previa.', $asignacion->observacion);
        $this->assertSame($fecha, $asignacion->fresh()->fecha->toDateString());

        foreach ([$entrada, $salidaRefrigerio, $regreso, $salida] as $marcacion) {
            $this->assertDatabaseHas('marcaciones', [
                'id' => $marcacion->id,
                'turno_id' => $turno->id,
                'tipo' => $marcacion->tipo,
                'fecha_hora' => $marcacion->fecha_hora,
            ]);
        }

        $regreso->refresh();
        $this->assertSame('2026-10-05 13:00:03', $regreso->refrigerio_retorno_esperado_en?->format('Y-m-d H:i:s'));
        $this->assertSame(0, $regreso->refrigerio_diferencia_segundos);
        $this->assertDatabaseHas('resumenes_jornada', [
            'asignacion_turno_id' => $asignacion->id,
            'entrada_marcacion_id' => $entrada->id,
            'salida_marcacion_id' => $salida->id,
        ]);
    }

    public function test_regularization_requires_exceptional_marks_and_rolls_back_the_assignment_when_there_are_none(): void
    {
        Carbon::setTestNow('2026-10-06 18:00:00');
        [$supervisor, $colaborador, , $turno] = $this->contexto();

        try {
            app(RegularizacionJornadaService::class)->regularizar($supervisor, $colaborador, '2026-10-05', [
                'turno_id' => $turno->id,
                'motivo' => 'No se encontró ninguna lectura QR para vincular.',
            ]);
            $this->fail('La regularización sin marcaciones debe ser rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fecha', $exception->errors());
        }

        $this->assertSame(0, AsignacionTurno::query()->where('colaborador_id', $colaborador->id)->count());
    }

    /** @return array{0: User, 1: Colaborador, 2: Sucursal, 3: Turno} */
    private function contexto(): array
    {
        $sucursal = Sucursal::create(['nombre' => 'Local regularización', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('Regularizar:Jornada', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador sin turno',
            'documento_identidad' => 'REG-' . uniqid(),
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Apertura regularización',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'tolerancia_entrada_minutos' => 15,
            'tolerancia_salida_minutos' => 15,
            'incluye_refrigerio' => true,
            'refrigerio_minutos' => 60,
            'activo' => true,
        ]);

        return [$supervisor, $colaborador, $sucursal, $turno];
    }

    private function marcacion(Colaborador $colaborador, Sucursal $sucursal, string $tipo, string $fechaHora): Marcacion
    {
        return Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => $tipo,
            'fecha_hora' => $fechaHora,
            'ip_origen' => '198.51.100.23',
            'user_agent' => 'Trazabilidad de prueba',
        ]);
    }
}
