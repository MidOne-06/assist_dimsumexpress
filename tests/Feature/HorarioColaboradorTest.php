<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HorarioColaboradorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_current_month_opens_the_week_that_contains_today_with_system_theme_support(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');

        $sucursal = Sucursal::create(['nombre' => 'Sucursal móvil', 'tipo' => 'tienda', 'activo' => true]);
        $usuario = User::factory()->create();
        $colaborador = Colaborador::create([
            'user_id' => $usuario->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador móvil',
            'documento_identidad' => 'MOVIL-001',
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Apertura',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'activo' => true,
        ]);
        AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => '2026-10-20',
        ]);
        $usuario->givePermissionTo(Permission::findOrCreate('View:MiHorario', 'web'));

        $this->actingAs($usuario)
            ->get(route('horario.show'))
            ->assertOk()
            ->assertSee('Semana 3 de 5')
            ->assertSee('<span class="number">20</span>', false)
            ->assertDontSee('<span class="number">1</span>', false)
            ->assertSee('marcacion-dimsum-vertical.png', false)
            ->assertSee('prefers-color-scheme', false)
            ->assertDontSee('localStorage.getItem', false);
    }
}
