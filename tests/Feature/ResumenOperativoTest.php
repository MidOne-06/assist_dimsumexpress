<?php

namespace Tests\Feature;

use App\Filament\Widgets\ResumenOperativo;
use App\Filament\Widgets\MarcacionesPorHoraChart;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResumenOperativoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_operational_metrics_limited_to_the_users_locations(): void
    {
        $supervisor = User::factory()->create();
        $sucursal = Sucursal::create(['nombre' => 'Local asignado', 'tipo' => 'tienda', 'activo' => true]);
        $otraSucursal = Sucursal::create(['nombre' => 'Local externo', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador del local',
            'documento_identidad' => 'KPI-001',
            'activo' => true,
        ]);
        Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $otraSucursal->id,
            'nombre_completo' => 'Colaborador externo',
            'documento_identidad' => 'KPI-002',
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Turno KPI',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '16:00:00',
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'activo' => true,
        ]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => now()->toDateString(),
        ]);
        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now(),
        ]);
        IncidenciaMarcacion::create([
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'tipo' => IncidenciaMarcacion::TIPO_MARCACION_OMITIDA,
            'detectada_en' => now(),
        ]);

        $this->actingAs($supervisor);

        Livewire::test(ResumenOperativo::class)
            ->assertSee('Colaboradores activos')
            ->assertSee('Turnos de hoy')
            ->assertSee('Marcaciones hoy')
            ->assertSee('Incidencias pendientes')
            ->assertSee('1');

        Livewire::test(MarcacionesPorHoraChart::class)
            ->assertSee('Marcaciones por hora')
            ->assertSee('max-height: 280px');
    }
}
