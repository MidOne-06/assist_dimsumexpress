<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnoVersionadoTest extends TestCase
{
    use RefreshDatabase;

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
    }
}
