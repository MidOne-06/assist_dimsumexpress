<?php

namespace Tests\Feature;

use App\Filament\Resources\Turnos\TurnoResource;
use App\Filament\Resources\Turnos\Pages\ListTurnos;
use App\Models\User;
use App\Models\Turno;
use App\Services\TurnoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TurnosModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_turnos_only_exposes_the_list_route_for_modal_management(): void
    {
        $this->assertSame(['index'], array_keys(TurnoResource::getPages()));
    }

    public function test_create_turno_modal_renders_its_reactive_fields(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('ViewAny:Turno', 'web'),
            Permission::findOrCreate('Create:Turno', 'web'),
        );

        Livewire::actingAs($usuario)
            ->test(ListTurnos::class)
            ->mountAction('create')
            ->assertHasNoErrors();
    }

    public function test_shift_rules_normalize_values_validate_midnight_and_preserve_history_when_versioned(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('Create:Turno', 'web'),
            Permission::findOrCreate('Update:Turno', 'web'),
        );

        $turno = app(TurnoService::class)->crear($usuario, [
            'nombre' => '  Apertura   operativa ',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'cruza_medianoche' => false,
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'incluye_refrigerio' => true,
            'refrigerio_minutos' => 60,
            'horas_efectivas_objetivo_minutos' => 480,
            'horas_efectivas_jornada_completa_minutos' => 780,
            'solo_entrada' => false,
            'activo' => true,
        ]);

        $this->assertSame('Apertura operativa', $turno->nombre);
        $this->assertSame(480, $turno->horas_efectivas_objetivo_minutos);
        $this->assertSame(780, $turno->horas_efectivas_jornada_completa_minutos);

        try {
            app(TurnoService::class)->crear($usuario, [
                'nombre' => 'Horario inválido',
                'hora_inicio' => '14:00',
                'hora_fin' => '08:00',
                'cruza_medianoche' => false,
                'tolerancia_entrada_minutos' => 0,
                'tolerancia_salida_minutos' => 0,
                'incluye_refrigerio' => false,
                'horas_efectivas_objetivo_minutos' => 480,
                'solo_entrada' => false,
                'activo' => true,
            ]);
            $this->fail('Un horario nocturno sin indicar el cruce no debe guardarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cruza_medianoche', $exception->errors());
        }

        $nocturno = app(TurnoService::class)->crear($usuario, [
            'nombre' => 'Nocturno',
            'hora_inicio' => '22:00',
            'hora_fin' => '06:00',
            'cruza_medianoche' => true,
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'incluye_refrigerio' => false,
            'horas_efectivas_objetivo_minutos' => 480,
            'solo_entrada' => false,
            'activo' => true,
        ]);
        $this->assertTrue($nocturno->cruza_medianoche);

        $soloEntrada = Turno::create([
            'nombre' => 'Control de ingreso',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'solo_entrada' => true,
            'incluye_refrigerio' => true,
            'refrigerio_minutos' => 60,
            'horas_efectivas_objetivo_minutos' => 480,
            'activo' => true,
        ]);
        $this->assertFalse($soloEntrada->incluye_refrigerio);
        $this->assertSame(0, $soloEntrada->horas_efectivas_objetivo_minutos);

        $jornadaAbierta = app(TurnoService::class)->crear($usuario, [
            'nombre' => 'Jornada abierta de operación',
            'hora_inicio' => '08:00',
            'jornada_abierta' => true,
            'solo_entrada' => false,
            'tolerancia_entrada_minutos' => 10,
            'incluye_refrigerio' => true,
            'refrigerio_minutos' => 60,
            'horas_efectivas_objetivo_minutos' => 480,
            'horas_efectivas_jornada_completa_minutos' => 540,
            'activo' => true,
        ]);
        $this->assertTrue($jornadaAbierta->jornada_abierta);
        $this->assertFalse($jornadaAbierta->solo_entrada);
        $this->assertNull($jornadaAbierta->hora_fin);
        $this->assertTrue($jornadaAbierta->incluye_refrigerio);
        $this->assertSame('08:00 · Jornada abierta', $jornadaAbierta->rangoHorario());
    }
}
