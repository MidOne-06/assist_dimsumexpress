<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\ResumenJornada;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Services\JornadasSinSalidaService;
use App\Services\RegularizacionSalidaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegularizacionSalidaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_regularizes_missing_exit_and_consolidates_jornada(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');

        [$colaborador, $turno, $asignacion, $supervisor] = $this->setupJornada('2026-10-30');

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'sucursal_id' => $colaborador->sucursal_id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => '2026-10-30 14:05:00',
        ]);

        $sinSalidaAntes = app(JornadasSinSalidaService::class)->listar(
            Carbon::parse('2026-10-29'),
            Carbon::parse('2026-10-31'),
        );
        $this->assertCount(1, $sinSalidaAntes);

        $salida = app(RegularizacionSalidaService::class)->regularizar(
            $asignacion,
            [
                'salida_en' => '2026-10-30 22:10:00',
                'motivo' => 'Colaborador olvidó marcar al término de su turno.',
            ],
            $supervisor,
        );

        $this->assertSame(Marcacion::TIPO_SALIDA, $salida->tipo);
        $this->assertSame($colaborador->id, $salida->colaborador_id);
        $this->assertSame('2026-10-30 22:10:00', $salida->fecha_hora->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('regularizaciones_salida', [
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'marcacion_id' => $salida->id,
            'regularizada_por_id' => $supervisor->id,
            'motivo' => 'Colaborador olvidó marcar al término de su turno.',
        ]);

        $resumen = ResumenJornada::where('asignacion_turno_id', $asignacion->id)->first();
        $this->assertNotNull($resumen);
        $this->assertSame($salida->id, $resumen->salida_marcacion_id);
        $this->assertGreaterThan(0, $resumen->efectivos_segundos);
        $this->assertNotNull($resumen->detalle['regularizacion_salida'] ?? null);

        $sinSalidaDespues = app(JornadasSinSalidaService::class)->listar(
            Carbon::parse('2026-10-29'),
            Carbon::parse('2026-10-31'),
        );
        $this->assertCount(0, $sinSalidaDespues);
    }

    public function test_rejects_regularization_if_exit_is_in_the_future(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');

        [$colaborador, $turno, $asignacion, $supervisor] = $this->setupJornada('2026-10-31');

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'sucursal_id' => $colaborador->sucursal_id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => '2026-10-31 11:00:00',
        ]);

        $this->expectException(ValidationException::class);

        app(RegularizacionSalidaService::class)->regularizar(
            $asignacion,
            [
                'salida_en' => '2026-10-31 14:00:00',
                'motivo' => 'Intento de regularizar en el futuro.',
            ],
            $supervisor,
        );
    }

    public function test_rejects_regularization_if_exit_is_before_entry(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');

        [$colaborador, $turno, $asignacion, $supervisor] = $this->setupJornada('2026-10-30');

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'sucursal_id' => $colaborador->sucursal_id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => '2026-10-30 14:05:00',
        ]);

        $this->expectException(ValidationException::class);

        app(RegularizacionSalidaService::class)->regularizar(
            $asignacion,
            [
                'salida_en' => '2026-10-30 13:00:00',
                'motivo' => 'Salida antes de ingreso.',
            ],
            $supervisor,
        );
    }

    /** @return array{0: Colaborador, 1: Turno, 2: AsignacionTurno, 3: User} */
    private function setupJornada(string $fecha): array
    {
        Permission::findOrCreate('Regularizar:Jornada', 'web');
        $role = \Spatie\Permission\Models\Role::findOrCreate('administrador', 'web');
        $role->givePermissionTo('Regularizar:Jornada');

        $sucursal = Sucursal::create(['nombre' => 'Sucursal Prueba', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->assignRole($role);

        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador Test',
            'documento_identidad' => 'DOC-'.uniqid(),
            'activo' => true,
        ]);

        $turno = Turno::create([
            'nombre' => 'Cierre',
            'hora_inicio' => '14:00:00',
            'hora_fin' => '22:00:00',
            'cruza_medianoche' => false,
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'incluye_refrigerio' => false,
            'refrigerio_minutos' => 0,
            'horas_efectivas_objetivo_minutos' => 480,
            'activo' => true,
        ]);

        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => $fecha,
        ]);

        return [$colaborador, $turno, $asignacion, $supervisor];
    }
}
