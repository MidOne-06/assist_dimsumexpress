<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\TurnoOperativo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TurnoVersionadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_shift_and_operational_rule_are_read_only(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal histórica', 'tipo' => 'planta', 'activo' => true]);
        $turno = Turno::create(['nombre' => 'Turno histórico bloqueado', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => false]);
        $regla = TurnoOperativo::create(['turno_id' => $turno->id, 'sucursal_id' => $sucursal->id, 'prioridad' => 100, 'activo' => false]);

        try {
            $turno->actualizarParaFuturo(['nombre' => 'Cambio no permitido']);
            $this->fail('Un turno histórico no debe actualizarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('turno', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $regla->update(['prioridad' => 1]);
    }

    public function test_an_active_operational_rule_cannot_reference_an_archived_shift(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal validada', 'tipo' => 'planta', 'activo' => true]);
        $turnoArchivado = Turno::create([
            'nombre' => 'Turno archivado',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'activo' => false,
        ]);

        $this->expectException(ValidationException::class);

        TurnoOperativo::create([
            'turno_id' => $turnoArchivado->id,
            'sucursal_id' => $sucursal->id,
            'prioridad' => 100,
            'activo' => true,
        ]);
    }

    public function test_editing_a_shift_with_history_versions_only_future_assignments(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal de prueba', 'tipo' => 'planta', 'activo' => true]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador de prueba',
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);
        $turno = Turno::create(['nombre' => 'Turno base', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $reglaAnterior = TurnoOperativo::create(['turno_id' => $turno->id, 'sucursal_id' => $sucursal->id, 'prioridad' => 100, 'activo' => true]);
        $historica = AsignacionTurno::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'fecha' => now()->subDay()->toDateString()]);
        $hoy = AsignacionTurno::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'fecha' => now()->toDateString()]);
        $futura = AsignacionTurno::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'fecha' => now()->addDay()->toDateString()]);

        $nuevo = $turno->actualizarParaFuturo([
            'nombre' => 'Turno actualizado',
            'hora_inicio' => '09:00',
            'hora_fin' => '18:00',
            'cruza_medianoche' => false,
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'activo' => true,
        ]);

        $this->assertNotSame($turno->id, $nuevo->id);
        $this->assertFalse($turno->fresh()->activo);
        $this->assertSame($turno->id, $historica->fresh()->turno_id);
        $this->assertSame($turno->id, $hoy->fresh()->turno_id);
        $this->assertSame($nuevo->id, $futura->fresh()->turno_id);
        $this->assertSame('09:00', $nuevo->hora_inicio);
        $this->assertFalse($reglaAnterior->fresh()->activo);
        $this->assertDatabaseHas('turnos_operativos', [
            'turno_id' => $nuevo->id,
            'sucursal_id' => $sucursal->id,
            'prioridad' => 100,
            'activo' => true,
        ]);
    }
}
