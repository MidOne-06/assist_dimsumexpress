<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MisMarcacionesHoyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_shows_only_the_authenticated_collaborators_actual_marks_for_today(): void
    {
        Carbon::setTestNow('2026-10-10 14:30:00');

        [$usuario, $colaborador, $sucursal] = $this->crearColaboradorConAcceso();
        [, $otroColaborador] = $this->crearColaboradorConAcceso();

        $this->crearMarcacion($colaborador, $sucursal, Marcacion::TIPO_ENTRADA, '2026-10-10 08:01:12');
        $this->crearMarcacion($colaborador, $sucursal, Marcacion::TIPO_SALIDA_REFRIGERIO, '2026-10-10 12:03:15');
        $this->crearMarcacion($colaborador, $sucursal, Marcacion::TIPO_REGRESO_REFRIGERIO, '2026-10-10 13:01:27');
        $this->crearMarcacion($colaborador, $sucursal, Marcacion::TIPO_SALIDA, '2026-10-10 17:04:48');
        $this->crearMarcacion($colaborador, $sucursal, Marcacion::TIPO_ENTRADA, '2026-10-09 07:59:59');
        $this->crearMarcacion($otroColaborador, $sucursal, Marcacion::TIPO_ENTRADA, '2026-10-10 06:30:00');

        $this->actingAs($usuario)
            ->get(route('marcaciones-hoy.show'))
            ->assertOk()
            ->assertSee('Mis marcaciones de hoy')
            ->assertSee('Ingreso de turno')
            ->assertSee('Salida a refrigerio')
            ->assertSee('Ingreso de refrigerio')
            ->assertSee('Salida de turno')
            ->assertSee('08:01:12')
            ->assertSee('12:03:15')
            ->assertSee('13:01:27')
            ->assertSee('17:04:48')
            ->assertDontSee('07:59:59')
            ->assertDontSee('06:30:00')
            ->assertDontSee('Sucursal móvil')
            ->assertDontSee('Turno aplicado')
            ->assertSee('prefers-color-scheme', false)
            ->assertSee('overflow-wrap: anywhere;', false);
    }

    public function test_it_uses_an_empty_state_when_the_collaborator_has_not_marked_today(): void
    {
        Carbon::setTestNow('2026-10-10 14:30:00');

        [$usuario] = $this->crearColaboradorConAcceso();

        $this->actingAs($usuario)
            ->get(route('marcaciones-hoy.show'))
            ->assertOk()
            ->assertSee('Aún no registraste marcaciones hoy.')
            ->assertSee('Volver a marcar');
    }

    public function test_it_requires_the_personal_schedule_permission(): void
    {
        Carbon::setTestNow('2026-10-10 14:30:00');

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('marcaciones-hoy.show'))
            ->assertForbidden();
    }

    /** @return array{0: User, 1: Colaborador, 2: Sucursal} */
    private function crearColaboradorConAcceso(): array
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal móvil',
            'tipo' => 'tienda',
            'activo' => true,
        ]);
        $usuario = User::factory()->create();
        $colaborador = Colaborador::create([
            'user_id' => $usuario->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador móvil',
            'documento_identidad' => 'MOVIL-' . $usuario->id,
            'activo' => true,
        ]);
        $usuario->givePermissionTo(Permission::findOrCreate('View:MiHorario', 'web'));

        return [$usuario, $colaborador, $sucursal];
    }

    private function crearMarcacion(Colaborador $colaborador, Sucursal $sucursal, string $tipo, string $fechaHora): void
    {
        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => $tipo,
            'fecha_hora' => $fechaHora,
        ]);
    }
}
