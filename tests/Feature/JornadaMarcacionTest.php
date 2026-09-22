<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\QrToken;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Support\JornadaMarcacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

    public function test_refrigerio_is_one_hour_and_early_or_late_returns_are_classified(): void
    {
        Carbon::setTestNow('2026-09-21 12:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $salida = $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA_REFRIGERIO);

        $temprano = JornadaMarcacion::controlRetornoRefrigerio($salida, Carbon::parse('2026-09-21 12:55:00'));
        $this->assertSame('2026-09-21 13:00:00', $temprano['esperado']->toDateTimeString());
        $this->assertSame(-300, $temprano['diferencia_segundos']);

        $retorno = Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $asignacion->turno_id,
            'sucursal_id' => $colaborador->sucursal_id,
            'tipo' => Marcacion::TIPO_REGRESO_REFRIGERIO,
            'fecha_hora' => '2026-09-21 13:07:00',
            'refrigerio_retorno_esperado_en' => $temprano['esperado'],
            'refrigerio_diferencia_segundos' => 420,
        ]);

        $this->assertSame('7 min tarde', $retorno->resumenRetornoRefrigerio()['etiqueta']);
        $this->assertSame('tarde', $retorno->resumenRetornoRefrigerio()['estado']);
    }

    public function test_refrigerio_is_not_offered_when_there_is_not_enough_time_to_return(): void
    {
        Carbon::setTestNow('2026-09-21 16:11:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);

        $this->assertSame([Marcacion::TIPO_SALIDA], JornadaMarcacion::siguientesTipos($colaborador, $asignacion));
        $this->assertFalse(JornadaMarcacion::puedeIniciarRefrigerio($asignacion));
    }

    public function test_qr_return_persists_the_one_hour_refrigerio_audit(): void
    {
        $this->withoutMiddleware();
        Carbon::setTestNow('2026-09-21 12:00:00');
        [$colaborador] = $this->crearJornada('08:00:00', '17:00:00');
        $usuario = $colaborador->user;
        $usuario->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $qr = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'token-de-prueba-' . uniqid(),
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);

        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qr->token, 'tipo' => Marcacion::TIPO_ENTRADA])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-09-21 12:10:00');
        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qr->token, 'tipo' => Marcacion::TIPO_SALIDA_REFRIGERIO])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-09-21 13:15:00');
        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qr->token, 'tipo' => Marcacion::TIPO_REGRESO_REFRIGERIO])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $retorno = Marcacion::where('colaborador_id', $colaborador->id)
            ->where('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO)
            ->sole();

        $this->assertSame('2026-09-21 13:10:00', $retorno->refrigerio_retorno_esperado_en->toDateTimeString());
        $this->assertSame(300, $retorno->refrigerio_diferencia_segundos);
        $this->assertSame('5 min tarde', $retorno->resumenRetornoRefrigerio()['etiqueta']);
    }

    public function test_authenticated_users_are_redirected_without_the_login_loop(): void
    {
        [$colaborador] = $this->crearJornada('08:00:00', '17:00:00');
        $operador = $colaborador->user;
        $operador->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));

        $this->actingAs($operador)
            ->get(route('login'))
            ->assertRedirect(route('marcacion.show'));

        $administrador = User::factory()->create();
        $administrador->givePermissionTo(Permission::findOrCreate('Access:AdminPanel', 'web'));

        $this->actingAs($administrador)
            ->get(route('login'))
            ->assertRedirect(route('filament.admin.pages.dashboard'));
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

    private function marcar(Colaborador $colaborador, AsignacionTurno $asignacion, string $tipo): Marcacion
    {
        return Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $asignacion->turno_id, 'sucursal_id' => $colaborador->sucursal_id, 'tipo' => $tipo, 'fecha_hora' => now()]);
    }
}
