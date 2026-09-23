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
        $this->assertSame(['salida'], JornadaMarcacion::siguientesTipos($colaborador, $asignacion));
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

    public function test_final_confirmation_uses_the_assignment_that_contains_an_overnight_mark(): void
    {
        Carbon::setTestNow('2026-09-21 22:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('22:00:00', '06:00:00', true, '2026-09-21');
        AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $asignacion->turno_id,
            'fecha' => '2026-09-22',
        ]);
        $colaborador->user->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));

        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        Carbon::setTestNow('2026-09-22 06:00:00');
        $salida = $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA);

        $this->actingAs($colaborador->user)
            ->get(route('marcacion.confirmacion', $salida))
            ->assertOk()
            ->assertSee('Horas efectivas trabajadas')
            ->assertSee('8 h 0 min · Meta 7 h');
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

    public function test_entry_only_shift_is_completed_after_its_entry_mark(): void
    {
        Carbon::setTestNow('2026-09-21 08:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $asignacion->turno->update(['solo_entrada' => true]);

        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);

        $this->assertSame([], JornadaMarcacion::siguientesTipos($colaborador, $asignacion->fresh('turno')));
    }

    public function test_turn_without_refrigerio_only_offers_final_exit(): void
    {
        Carbon::setTestNow('2026-09-21 14:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('13:00:00', '22:00:00');
        $asignacion->turno->update(['incluye_refrigerio' => false, 'refrigerio_minutos' => 0, 'horas_efectivas_objetivo_minutos' => 540]);
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);

        $asignacionSinRefrigerio = $asignacion->fresh(['turno']);
        $this->assertSame([Marcacion::TIPO_SALIDA], JornadaMarcacion::siguientesTipos($colaborador, $asignacionSinRefrigerio));
    }

    public function test_extended_open_jornada_calculates_effective_and_extra_hours(): void
    {
        Carbon::setTestNow('2026-09-21 08:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $asignacion->turno->update(['incluye_refrigerio' => true, 'refrigerio_minutos' => 60, 'horas_efectivas_objetivo_minutos' => 480, 'horas_efectivas_jornada_completa_minutos' => 540]);
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        Carbon::setTestNow('2026-09-21 13:00:00');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA_REFRIGERIO);
        Carbon::setTestNow('2026-09-21 14:00:00');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_REGRESO_REFRIGERIO);

        Carbon::setTestNow('2026-09-21 22:00:00');
        $this->assertSame($asignacion->id, JornadaMarcacion::asignacionVigente($colaborador)?->id);
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA);

        $resumen = JornadaMarcacion::resumen($colaborador, $asignacion->fresh('turno'));
        $this->assertSame(780, $resumen['efectivos_minutos']);
        $this->assertSame(540, $resumen['objetivo_minutos']);
        $this->assertSame(240, $resumen['extras_minutos']);
        $this->assertSame('extendida', $resumen['estado']);
    }

    public function test_qr_return_persists_the_one_hour_refrigerio_audit(): void
    {
        $this->withoutMiddleware();
        Carbon::setTestNow('2026-09-21 12:00:00');
        [$colaborador] = $this->crearJornada('08:00:00', '17:00:00');
        $usuario = $colaborador->user;
        $usuario->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $qrEntrada = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'token-entrada-' . uniqid(),
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);
        $qrSalidaRefrigerio = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'token-salida-refrigerio-' . uniqid(),
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);
        $qrRegresoRefrigerio = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'token-regreso-refrigerio-' . uniqid(),
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);

        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qrEntrada->token, 'tipo' => Marcacion::TIPO_ENTRADA])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-09-21 12:10:00');
        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qrSalidaRefrigerio->token, 'tipo' => Marcacion::TIPO_SALIDA_REFRIGERIO])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-09-21 13:15:00');
        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qrRegresoRefrigerio->token, 'tipo' => Marcacion::TIPO_REGRESO_REFRIGERIO])
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

    public function test_operator_landing_explains_the_real_next_step_of_the_shift(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $operador = $colaborador->user;
        $operador->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));

        $this->actingAs($operador)
            ->get(route('marcacion.show'))
            ->assertOk()
            ->assertSee('Tu siguiente paso')
            ->assertSee('Escanea el QR para continuar')
            ->assertSee('Marcar ingreso de turno')
            ->assertDontSee('name="tipo"', false);

        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        $qr = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'token-vista-' . uniqid(),
            'proposito' => QrToken::PROPOSITO_ASISTENCIA,
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);

        $this->actingAs($operador)
            ->get(route('marcacion.show', ['token' => $qr->token]))
            ->assertOk()
            ->assertSee('Marcar salida de refrigerio')
            ->assertSee('Marcar salida de turno')
            ->assertSee('10:00:00');

        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA_REFRIGERIO);

        $this->actingAs($operador)
            ->get(route('marcacion.show', ['token' => $qr->token]))
            ->assertOk()
            ->assertSee('Marcar ingreso de refrigerio')
            ->assertDontSee('Marcar salida de turno');

        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_REGRESO_REFRIGERIO);

        $this->actingAs($operador)
            ->get(route('marcacion.show', ['token' => $qr->token]))
            ->assertOk()
            ->assertDontSee('Marcar salida de refrigerio')
            ->assertSee('Marcar salida de turno');
    }

    public function test_each_attendance_action_requires_a_new_dynamic_qr_scan(): void
    {
        $this->withoutMiddleware();
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$colaborador] = $this->crearJornada('08:00:00', '17:00:00');
        $operador = $colaborador->user;
        $operador->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));

        $primerQr = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'qr-primero-' . uniqid(),
            'proposito' => QrToken::PROPOSITO_ASISTENCIA,
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);
        $nuevoQr = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'qr-nuevo-' . uniqid(),
            'proposito' => QrToken::PROPOSITO_ASISTENCIA,
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);

        $this->actingAs($operador)
            ->post(route('marcacion.store'), ['token' => $primerQr->token, 'tipo' => Marcacion::TIPO_ENTRADA])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Volver atrás o reutilizar el enlace del primer escaneo no puede
        // habilitar salida de refrigerio ni salida de turno.
        $this->actingAs($operador)
            ->get(route('marcacion.show', ['token' => $primerQr->token]))
            ->assertOk()
            ->assertSee('ya fue usado para una marcación');

        $this->actingAs($operador)
            ->post(route('marcacion.store'), ['token' => $primerQr->token, 'tipo' => Marcacion::TIPO_SALIDA])
            ->assertRedirect()
            ->assertSessionHasErrors('tipo');

        // withoutMiddleware() conserva la bolsa de errores entre requests;
        // se limpia para simular la siguiente navegación normal del usuario.
        $this->flushSession();

        // El recorrido normal abre el QR nuevo antes de confirmar.
        $this->actingAs($operador)
            ->get(route('marcacion.show', ['token' => $nuevoQr->token]))
            ->assertOk()
            ->assertSee('Marcar salida de turno');

        $this->actingAs($operador)
            ->post(route('marcacion.store'), ['token' => $nuevoQr->token, 'tipo' => Marcacion::TIPO_SALIDA])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'tipo' => Marcacion::TIPO_SALIDA,
            'qr_token_id' => $nuevoQr->id,
        ]);
    }

    public function test_confirmation_and_traceability_keep_the_exact_seconds_of_a_mark(): void
    {
        Carbon::setTestNow('2026-09-21 10:30:45');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $operador = $colaborador->user;
        $operador->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $qr = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'token-traza-' . uniqid(),
            'proposito' => QrToken::PROPOSITO_ASISTENCIA,
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);
        $marcacion = Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $asignacion->turno_id,
            'sucursal_id' => $colaborador->sucursal_id,
            'qr_token_id' => $qr->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => '2026-09-21 10:30:45',
            'ip_origen' => '198.51.100.24',
            'user_agent' => 'Agente de prueba',
        ]);

        $this->actingAs($operador)
            ->get(route('marcacion.confirmacion', $marcacion))
            ->assertOk()
            ->assertSee('10:30:45');

        $traza = view('filament.actions.trazabilidad-marcacion', [
            'marcacion' => $marcacion->fresh(['colaborador', 'turno', 'sucursal', 'puntoVenta', 'qrToken']),
        ])->render();

        $this->assertStringContainsString('21/09/2026 10:30:45', $traza);
        $this->assertStringContainsString('198.51.100.24', $traza);
        $this->assertStringContainsString('QR #' . $qr->id, $traza);
        $this->assertStringContainsString('Agente de prueba', $traza);
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
