<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Services\ConsolidacionJornadaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidacionJornadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_journey_is_consolidated_once_with_exact_seconds_and_actual_station(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Estación real', 'tipo' => 'tienda', 'activo' => true]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador consolidado',
            'documento_identidad' => 'CON-001',
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Exacto',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'incluye_refrigerio' => false,
            'refrigerio_minutos' => 0,
            'horas_efectivas_objetivo_minutos' => 540,
            'activo' => true,
        ]);
        $asignacion = AsignacionTurno::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'fecha' => '2026-09-21']);
        $entrada = Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'sucursal_id' => $sucursal->id, 'tipo' => Marcacion::TIPO_ENTRADA, 'fecha_hora' => '2026-09-21 08:00:05']);
        $salida = Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'sucursal_id' => $sucursal->id, 'tipo' => Marcacion::TIPO_SALIDA, 'fecha_hora' => '2026-09-21 17:00:04']);

        $servicio = app(ConsolidacionJornadaService::class);
        $primero = $servicio->consolidar($colaborador, $asignacion);
        $segundo = $servicio->consolidar($colaborador, $asignacion);

        $this->assertSame($primero?->id, $segundo?->id);
        $this->assertDatabaseHas('resumenes_jornada', [
            'asignacion_turno_id' => $asignacion->id,
            'entrada_marcacion_id' => $entrada->id,
            'salida_marcacion_id' => $salida->id,
            'sucursal_id' => $sucursal->id,
            'efectivos_segundos' => 32399,
            'objetivo_segundos' => 32400,
            'diferencia_segundos' => -1,
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseCount('resumenes_jornada', 1);
    }
}
