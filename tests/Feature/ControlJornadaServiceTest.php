<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Services\ControlJornadaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlJornadaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_paints_all_segments_for_a_completed_shift_with_late_lunch_return(): void
    {
        [$colaborador, $turno, $asignacion, $sucursal] = $this->jornada(true);

        foreach ([
            [Marcacion::TIPO_ENTRADA, '2026-10-02 08:05:00'],
            [Marcacion::TIPO_SALIDA_REFRIGERIO, '2026-10-02 12:00:00'],
            [Marcacion::TIPO_REGRESO_REFRIGERIO, '2026-10-02 13:10:00'],
            [Marcacion::TIPO_SALIDA, '2026-10-02 17:00:00'],
        ] as [$tipo, $fecha]) {
            Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'sucursal_id' => $sucursal->id, 'tipo' => $tipo, 'fecha_hora' => $fecha]);
        }

        $segmentos = app(ControlJornadaService::class)->segmentos($asignacion->fresh(['turno', 'colaborador']), Marcacion::query()->get(), Carbon::parse('2026-10-02 18:00:00'));

        $this->assertSame('completo', $segmentos['entrada']['estado']);
        $this->assertSame('completo', $segmentos['salida_refrigerio']['estado']);
        $this->assertSame('tardanza', $segmentos['regreso_refrigerio']['estado']);
        $this->assertSame('completo', $segmentos['salida']['estado']);
    }

    public function test_it_does_not_require_lunch_or_exit_for_a_single_entry_shift(): void
    {
        [$colaborador, $turno, $asignacion, $sucursal] = $this->jornada(false, true);
        Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'sucursal_id' => $sucursal->id, 'tipo' => Marcacion::TIPO_ENTRADA, 'fecha_hora' => '2026-10-02 08:00:00']);

        $segmentos = app(ControlJornadaService::class)->segmentos($asignacion->fresh(['turno', 'colaborador']), Marcacion::query()->get(), Carbon::parse('2026-10-02 18:00:00'));

        $this->assertSame('completo', $segmentos['entrada']['estado']);
        $this->assertFalse($segmentos['salida_refrigerio']['aplica']);
        $this->assertFalse($segmentos['regreso_refrigerio']['aplica']);
        $this->assertFalse($segmentos['salida']['aplica']);
    }

    /** @return array{0: Colaborador, 1: Turno, 2: AsignacionTurno, 3: Sucursal} */
    private function jornada(bool $incluyeRefrigerio, bool $soloEntrada = false): array
    {
        $sucursal = Sucursal::create(['nombre' => 'Local de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $colaborador = Colaborador::create(['user_id' => User::factory()->create()->id, 'sucursal_id' => $sucursal->id, 'nombre_completo' => 'Colaborador de prueba', 'documento_identidad' => uniqid('DOC-', true), 'activo' => true]);
        $turno = Turno::create([
            'nombre' => 'Turno de prueba',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'solo_entrada' => $soloEntrada,
            'incluye_refrigerio' => $incluyeRefrigerio,
            'refrigerio_minutos' => $incluyeRefrigerio ? 60 : 0,
            'horas_efectivas_objetivo_minutos' => $soloEntrada ? 0 : (480 + ($incluyeRefrigerio ? 0 : 60)),
            'activo' => true,
        ]);
        $asignacion = AsignacionTurno::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'fecha' => '2026-10-02']);

        return [$colaborador, $turno, $asignacion, $sucursal];
    }
}
