<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Support\JornadaMarcacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JornadaMarcacionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_refrigerio_respects_the_required_sequence_inside_a_shift(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');

        $this->assertSame(['entrada'], JornadaMarcacion::siguientesTipos($colaborador, $asignacion));
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        $this->assertSame(['salida_refrigerio', 'salida'], JornadaMarcacion::siguientesTipos($colaborador, $asignacion));
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA_REFRIGERIO);
        $this->assertSame(['regreso_refrigerio'], JornadaMarcacion::siguientesTipos($colaborador, $asignacion));
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_REGRESO_REFRIGERIO);
        $this->assertSame(['salida_refrigerio', 'salida'], JornadaMarcacion::siguientesTipos($colaborador, $asignacion));
    }

    public function test_night_shift_keeps_its_refrigerio_flow_after_midnight(): void
    {
        [$colaborador, $asignacion] = $this->crearJornada('22:00:00', '06:00:00', true, '2026-09-21');
        Carbon::setTestNow('2026-09-22 01:00:00');

        $this->assertSame($asignacion->id, JornadaMarcacion::asignacionVigente($colaborador)?->id);
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA_REFRIGERIO);
        $this->assertSame(['regreso_refrigerio'], JornadaMarcacion::siguientesTipos($colaborador, $asignacion));
    }

    public function test_shift_can_only_be_marked_within_its_configured_tolerances(): void
    {
        Carbon::setTestNow('2026-09-21 07:49:00');
        [$colaborador] = $this->crearJornada('08:00:00', '17:00:00');

        $this->assertNull(JornadaMarcacion::asignacionVigente($colaborador));

        Carbon::setTestNow('2026-09-21 08:10:00');
        $this->assertNotNull(JornadaMarcacion::asignacionVigente($colaborador));

        Carbon::setTestNow('2026-09-21 17:11:00');
        $this->assertNull(JornadaMarcacion::asignacionVigente($colaborador));
    }

    /** @return array{Colaborador, AsignacionTurno} */
    private function crearJornada(string $inicio, string $fin, bool $nocturno = false, ?string $fecha = null): array
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $user = User::factory()->create();
        $colaborador = Colaborador::create(['user_id' => $user->id, 'sucursal_id' => $sucursal->id, 'nombre_completo' => 'Colaborador de prueba', 'documento_identidad' => 'DOC-' . uniqid(), 'activo' => true]);
        $turno = Turno::create(['nombre' => 'Turno de prueba', 'hora_inicio' => $inicio, 'hora_fin' => $fin, 'cruza_medianoche' => $nocturno, 'tolerancia_entrada_minutos' => 10, 'tolerancia_salida_minutos' => 10, 'activo' => true]);

        return [$colaborador, AsignacionTurno::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'fecha' => $fecha ?? now()->toDateString()])];
    }

    private function marcar(Colaborador $colaborador, AsignacionTurno $asignacion, string $tipo): void
    {
        Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $asignacion->turno_id, 'sucursal_id' => $colaborador->sucursal_id, 'tipo' => $tipo, 'fecha_hora' => now()]);
    }
}
