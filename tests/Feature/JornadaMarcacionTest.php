<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\QrToken;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\TurnoOperativo;
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
            ->assertSee('Marcación registrada')
            ->assertSee('Tu asistencia fue registrada correctamente.')
            ->assertDontSee('Horas efectivas trabajadas')
            ->assertDontSee('Meta 7 h')
            ->assertDontSee('Marcación excepcional');
    }

    public function test_assigned_shift_records_a_late_entry_until_its_technical_end(): void
    {
        Carbon::setTestNow('2026-09-21 07:49:00');
        [$colaborador] = $this->crearJornada('08:00:00', '17:00:00');

        $this->assertNull(JornadaMarcacion::asignacionVigente($colaborador));

        Carbon::setTestNow('2026-09-21 08:10:00');
        $this->assertNotNull(JornadaMarcacion::asignacionVigente($colaborador));

        Carbon::setTestNow('2026-09-21 17:11:00');
        $this->assertNotNull(JornadaMarcacion::asignacionVigente($colaborador));

        Carbon::setTestNow('2026-09-22 02:01:00');
        $this->assertNull(JornadaMarcacion::asignacionVigente($colaborador));
    }

    public function test_an_open_shift_can_be_completed_after_its_shift_definition_is_deactivated(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        $asignacion->turno->update(['activo' => false]);

        $vigente = JornadaMarcacion::asignacionVigente($colaborador);

        $this->assertSame($asignacion->id, $vigente?->id);
        $this->assertSame(
            [Marcacion::TIPO_SALIDA_REFRIGERIO, Marcacion::TIPO_SALIDA],
            JornadaMarcacion::siguientesTipos($colaborador, $vigente),
        );

        $sinEntrada = $this->crearJornada('08:00:00', '17:00:00')[0];
        $sinEntrada->asignacionesTurno()->first()->turno->update(['activo' => false]);

        $this->assertNull(JornadaMarcacion::asignacionVigente($sinEntrada));
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

    public function test_refrigerio_can_start_during_an_operational_extension_but_not_past_the_safe_limit(): void
    {
        Carbon::setTestNow('2026-09-21 16:19:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);

        $this->assertSame(
            [Marcacion::TIPO_SALIDA_REFRIGERIO, Marcacion::TIPO_SALIDA],
            JornadaMarcacion::siguientesTipos($colaborador, $asignacion),
        );
        $this->assertTrue(JornadaMarcacion::puedeIniciarRefrigerio($asignacion));

        Carbon::setTestNow('2026-09-22 01:00:01');

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

    public function test_open_shift_without_scheduled_exit_keeps_the_complete_marking_flow(): void
    {
        Carbon::setTestNow('2026-09-21 08:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $asignacion->turno->update([
            'jornada_abierta' => true,
            'incluye_refrigerio' => true,
            'refrigerio_minutos' => 60,
            'horas_efectivas_objetivo_minutos' => 480,
        ]);

        $abierta = $asignacion->fresh('turno');
        $this->assertNull($abierta->turno->hora_fin);
        $this->assertSame([Marcacion::TIPO_ENTRADA], JornadaMarcacion::siguientesTipos($colaborador, $abierta));

        $this->marcar($colaborador, $abierta, Marcacion::TIPO_ENTRADA);
        $this->assertSame([Marcacion::TIPO_SALIDA_REFRIGERIO, Marcacion::TIPO_SALIDA], JornadaMarcacion::siguientesTipos($colaborador, $abierta));

        Carbon::setTestNow('2026-09-21 12:00:00');
        $this->marcar($colaborador, $abierta, Marcacion::TIPO_SALIDA_REFRIGERIO);
        $this->assertSame([Marcacion::TIPO_REGRESO_REFRIGERIO], JornadaMarcacion::siguientesTipos($colaborador, $abierta));

        Carbon::setTestNow('2026-09-21 13:00:00');
        $this->marcar($colaborador, $abierta, Marcacion::TIPO_REGRESO_REFRIGERIO);
        $this->assertSame([Marcacion::TIPO_SALIDA], JornadaMarcacion::siguientesTipos($colaborador, $abierta));

        Carbon::setTestNow('2026-09-21 17:00:00');
        $this->assertSame($abierta->id, JornadaMarcacion::asignacionVigente($colaborador)?->id);
        $this->marcar($colaborador, $abierta, Marcacion::TIPO_SALIDA);

        $resumen = JornadaMarcacion::resumen($colaborador, $abierta);
        $this->assertSame(480, $resumen['efectivos_minutos']);
        $this->assertSame('cumplida', $resumen['estado']);
    }

    public function test_open_shift_allows_a_late_first_entry_until_its_technical_end(): void
    {
        Carbon::setTestNow('2026-09-21 15:25:00');
        [$colaborador, $asignacion] = $this->crearJornada('07:00:00', '17:00:00');
        $asignacion->turno->update([
            'jornada_abierta' => true,
            'incluye_refrigerio' => true,
            'refrigerio_minutos' => 60,
            'horas_efectivas_objetivo_minutos' => 480,
        ]);

        $abierta = $asignacion->fresh('turno');
        $limites = JornadaMarcacion::limites($abierta);

        $this->assertSame('2026-09-22 01:00:00', $limites['ventana_fin']->toDateTimeString());
        $this->assertSame($abierta->id, JornadaMarcacion::asignacionVigente($colaborador)?->id);

        $acciones = JornadaMarcacion::acciones($colaborador, $abierta);
        $this->assertSame([
            ['entrada', 3, true],
            ['salida_refrigerio', 5, false],
            ['regreso_refrigerio', 7, false],
            ['salida', 9, false],
        ], collect($acciones)->map(fn (array $accion): array => [$accion['tipo'], $accion['codigo'], $accion['habilitada']])->all());

        $this->marcar($colaborador, $abierta, Marcacion::TIPO_ENTRADA);
        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => '2026-09-21 15:25:00',
        ]);
    }

    public function test_no_shift_mark_is_saved_as_a_traceable_exception_at_the_base_station(): void
    {
        $this->withoutMiddleware();
        Carbon::setTestNow('2026-09-21 15:25:00');
        $sucursal = Sucursal::create(['nombre' => 'Sucursal excepcional', 'tipo' => 'tienda', 'activo' => true]);
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $colaborador = Colaborador::create([
            'user_id' => $usuario->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador sin turno',
            'documento_identidad' => 'SIN-TURNO-1',
            'activo' => true,
        ]);
        $qr = QrToken::create([
            'sucursal_id' => $sucursal->id,
            'token' => 'qr-sin-turno-' . uniqid(),
            'proposito' => QrToken::PROPOSITO_ASISTENCIA,
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);

        $this->assertNull(JornadaMarcacion::asignacionVigente($colaborador));
        $this->assertSame(
            ['entrada'],
            collect(JornadaMarcacion::accionesSinTurno($colaborador))
                ->where('habilitada', true)
                ->pluck('tipo')
                ->all(),
        );

        $this->actingAs($usuario)
            ->post(route('marcacion.store'), ['token' => $qr->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'turno_id' => null,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'qr_token_id' => $qr->id,
            'sucursal_id' => $sucursal->id,
        ]);
    }

    public function test_station_detects_the_closest_operational_shift_in_an_overlap(): void
    {
        Carbon::setTestNow('2026-09-21 14:30:00');
        $sucursal = Sucursal::create(['nombre' => 'Tienda por rango', 'tipo' => 'tienda', 'activo' => true]);
        $usuario = User::factory()->create();
        $colaborador = Colaborador::create(['user_id' => $usuario->id, 'sucursal_id' => $sucursal->id, 'nombre_completo' => 'Detección por rango', 'documento_identidad' => 'RANGO-1', 'activo' => true]);
        $apertura = Turno::create(['nombre' => 'Apertura por rango', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'tolerancia_entrada_minutos' => 10, 'activo' => true]);
        $cierre = Turno::create(['nombre' => 'Cierre por rango', 'hora_inicio' => '14:00', 'hora_fin' => '22:00', 'tolerancia_entrada_minutos' => 10, 'incluye_refrigerio' => false, 'refrigerio_minutos' => 0, 'activo' => true]);
        TurnoOperativo::create(['turno_id' => $apertura->id, 'sucursal_id' => $sucursal->id, 'prioridad' => 100, 'activo' => true]);
        TurnoOperativo::create(['turno_id' => $cierre->id, 'sucursal_id' => $sucursal->id, 'prioridad' => 100, 'activo' => true]);

        $detectada = JornadaMarcacion::detectarTurnoOperativo($colaborador, $sucursal, null);

        $this->assertFalse($detectada->exists);
        $this->assertSame($cierre->id, $detectada->turno_id);
        $this->assertSame('detectado_automaticamente', $detectada->origen);
    }

    public function test_qr_only_marking_creates_a_detected_assignment_and_infers_the_full_sequence(): void
    {
        $this->withoutMiddleware();
        $sucursal = Sucursal::create(['nombre' => 'Tienda QR inteligente', 'tipo' => 'tienda', 'activo' => true]);
        $puntoVenta = PuntoVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja QR', 'activo' => true]);
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $colaborador = Colaborador::create([
            'user_id' => $usuario->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta->id,
            'nombre_completo' => 'Colaborador sin programación manual',
            'documento_identidad' => 'QR-AUTO-1',
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Apertura QR inteligente',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'incluye_refrigerio' => true,
            'refrigerio_minutos' => 60,
            'activo' => true,
        ]);
        TurnoOperativo::create(['turno_id' => $turno->id, 'sucursal_id' => $sucursal->id, 'punto_venta_id' => $puntoVenta->id, 'prioridad' => 1, 'activo' => true]);

        foreach ([
            ['08:20:00', Marcacion::TIPO_ENTRADA],
            ['12:00:00', Marcacion::TIPO_SALIDA_REFRIGERIO],
            ['13:05:00', Marcacion::TIPO_REGRESO_REFRIGERIO],
            ['16:30:00', Marcacion::TIPO_SALIDA],
        ] as [$hora, $tipo]) {
            Carbon::setTestNow("2026-09-21 {$hora}");
            $qr = QrToken::generarPara($sucursal, $puntoVenta, 60);
            $this->actingAs($usuario)
                ->post(route('marcacion.store'), ['token' => $qr->token])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
            $this->assertDatabaseHas('marcaciones', ['colaborador_id' => $colaborador->id, 'qr_token_id' => $qr->id, 'tipo' => $tipo]);
        }

        $asignacionDetectada = AsignacionTurno::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('turno_id', $turno->id)
            ->where('origen', 'detectado_automaticamente')
            ->sole();
        $this->assertSame('2026-09-21', $asignacionDetectada->fecha->toDateString());
    }

    public function test_unmarked_break_caps_effective_hours_at_the_shift_target(): void
    {
        Carbon::setTestNow('2026-09-21 08:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        Carbon::setTestNow('2026-09-21 17:00:00');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA);

        $resumen = JornadaMarcacion::resumen($colaborador, $asignacion);

        $this->assertSame(8 * 3600, $resumen['efectivos_segundos']);
        $this->assertSame(0, $resumen['extras_segundos']);
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

    public function test_effective_hours_are_compared_with_second_precision(): void
    {
        Carbon::setTestNow('2026-09-21 08:00:05');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $asignacion->turno->update(['incluye_refrigerio' => false, 'refrigerio_minutos' => 0, 'horas_efectivas_objetivo_minutos' => 540]);
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_ENTRADA);
        Carbon::setTestNow('2026-09-21 17:00:04');
        $this->marcar($colaborador, $asignacion, Marcacion::TIPO_SALIDA);

        $resumen = JornadaMarcacion::resumen($colaborador, $asignacion->fresh('turno'));

        $this->assertSame(32399, $resumen['efectivos_segundos']);
        $this->assertSame(-1, $resumen['diferencia_segundos']);
        $this->assertSame('pendiente', $resumen['estado']);
        $this->assertSame(539, $resumen['efectivos_minutos']);
    }

    public function test_manual_assignment_has_priority_over_automatic_shift_matching(): void
    {
        $this->withoutMiddleware();
        $sucursal = Sucursal::create(['nombre' => 'Sucursal dinámica', 'tipo' => 'tienda', 'activo' => true]);
        $apertura = Turno::create(['nombre' => 'Apertura dinámica', 'hora_inicio' => '06:00', 'hora_fin' => '15:00', 'tolerancia_entrada_minutos' => 10, 'activo' => true]);
        $cierre = Turno::create(['nombre' => 'Cierre dinámico', 'hora_inicio' => '15:00', 'hora_fin' => '23:00', 'tolerancia_entrada_minutos' => 10, 'incluye_refrigerio' => false, 'refrigerio_minutos' => 0, 'activo' => true]);
        $qr = QrToken::create([
            'sucursal_id' => $sucursal->id,
            'token' => 'turno-dinamico-' . uniqid(),
            'proposito' => QrToken::PROPOSITO_ASISTENCIA,
            'expira_en' => Carbon::parse('2026-09-30 23:59:59'),
        ]);

        Carbon::setTestNow('2026-09-21 15:00:00');
        $usuarioApertura = User::factory()->create();
        $usuarioApertura->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $colaboradorApertura = Colaborador::create([
            'user_id' => $usuarioApertura->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador cierre a apertura',
            'documento_identidad' => 'DIN-1',
            'activo' => true,
        ]);
        $asignacionCierre = AsignacionTurno::create([
            'colaborador_id' => $colaboradorApertura->id,
            'turno_id' => $cierre->id,
            'fecha' => today(),
        ]);

        $this->actingAs($usuarioApertura)
            ->post(route('marcacion.store'), ['token' => $qr->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('asignaciones_turno', ['id' => $asignacionCierre->id, 'turno_id' => $cierre->id]);
        $this->assertDatabaseMissing('ajustes_turno_automaticos', ['asignacion_turno_id' => $asignacionCierre->id]);
        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaboradorApertura->id,
            'turno_id' => $cierre->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
        ]);

        Carbon::setTestNow('2026-09-22 06:00:00');
        $usuarioCierre = User::factory()->create();
        $usuarioCierre->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $colaboradorCierre = Colaborador::create([
            'user_id' => $usuarioCierre->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador apertura a cierre',
            'documento_identidad' => 'DIN-2',
            'activo' => true,
        ]);
        $asignacionApertura = AsignacionTurno::create([
            'colaborador_id' => $colaboradorCierre->id,
            'turno_id' => $apertura->id,
            'fecha' => today(),
        ]);

        $this->actingAs($usuarioCierre)
            ->post(route('marcacion.store'), ['token' => $qr->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('asignaciones_turno', ['id' => $asignacionApertura->id, 'turno_id' => $apertura->id]);
        $this->assertDatabaseMissing('ajustes_turno_automaticos', ['asignacion_turno_id' => $asignacionApertura->id]);
        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaboradorCierre->id,
            'turno_id' => $apertura->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
        ]);
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

        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qrEntrada->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-09-21 12:10:00');
        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qrSalidaRefrigerio->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-09-21 13:15:00');
        $this->actingAs($usuario)->post(route('marcacion.store'), ['token' => $qrRegresoRefrigerio->token])
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
            ->assertSee('Escanea el QR')
            ->assertDontSee('Turno programado')
            ->assertDontSee('Turno detectado por horario')
            ->assertDontSee('Horas efectivas trabajadas')
            ->assertDontSee('Marcación excepcional')
            ->assertDontSee('Marcar ingreso')
            ->assertDontSee('data-mp-accion=', false)
            ->assertDontSee('data-codigo-marcacion', false);

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
            ->assertSee('QR escaneado correctamente')
            ->assertSee('Continuar')
            ->assertDontSee('Turno programado')
            ->assertDontSee('Turno detectado por horario')
            ->assertDontSee('Horas efectivas trabajadas')
            ->assertDontSee('Marcación excepcional')
            ->assertDontSee($colaborador->sucursal->nombre)
            ->assertDontSee('data-codigo-marcacion', false)
            ->assertDontSee('localStorage.getItem', false)
            ->assertSee('prefers-color-scheme', false)
            ->assertDontSee('>3<', false)
            ->assertDontSee('>5<', false)
            ->assertDontSee('>7<', false)
            ->assertDontSee('>9<', false)
            ->assertDontSee('10:00:00')
            ->assertDontSee('Salida de refrigerio')
            ->assertDontSee('Ingreso de refrigerio')
            ->assertDontSee('Salida de turno');
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
            ->post(route('marcacion.store'), ['token' => $primerQr->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Volver atrás o reutilizar el enlace del primer escaneo no puede
        // habilitar salida de refrigerio ni salida de turno.
        $this->actingAs($operador)
            ->get(route('marcacion.show', ['token' => $primerQr->token]))
            ->assertOk()
            ->assertSee('ya fue usado para una marcación');

        $this->actingAs($operador)
            ->post(route('marcacion.store'), ['token' => $primerQr->token])
            ->assertRedirect()
            ->assertSessionHasErrors('token');

        // withoutMiddleware() conserva la bolsa de errores entre requests;
        // se limpia para simular la siguiente navegación normal del usuario.
        $this->flushSession();

        // El recorrido normal abre el QR nuevo antes de confirmar.
        Carbon::setTestNow('2026-09-21 16:00:00');
        $this->actingAs($operador)
            ->get(route('marcacion.show', ['token' => $nuevoQr->token]))
            ->assertOk()
            ->assertSee('Continuar');

        $this->actingAs($operador)
            ->post(route('marcacion.store'), ['token' => $nuevoQr->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'tipo' => Marcacion::TIPO_SALIDA,
            'qr_token_id' => $nuevoQr->id,
        ]);
    }

    public function test_validating_a_scanned_qr_confirms_the_station_and_next_action_without_creating_a_mark(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$colaborador] = $this->crearJornada('08:00:00', '17:00:00');
        $operador = $colaborador->user;
        $operador->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));

        $qr = QrToken::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'token' => 'qr-validacion-' . uniqid(),
            'proposito' => QrToken::PROPOSITO_ASISTENCIA,
            'expira_en' => Carbon::parse('2026-09-21 17:00:00'),
        ]);

        $this->actingAs($operador)
            ->getJson(route('marcacion.validar-qr', ['token' => $qr->token]))
            ->assertOk()
            ->assertJsonPath('confirmado', true)
            ->assertJsonPath('mensaje', 'QR escaneado correctamente')
            ->assertJsonMissing(['acciones'])
            ->assertJsonMissing(['estacion'])
            ->assertJsonMissing(['sin_turno_asignado']);

        $this->assertDatabaseMissing('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'qr_token_id' => $qr->id,
        ]);
    }

    public function test_same_branch_point_of_sale_is_not_a_coverage_when_the_collaborator_has_no_base_point_of_sale(): void
    {
        $this->withoutMiddleware();
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $colaborador->user->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $puntoVenta = PuntoVenta::create([
            'sucursal_id' => $colaborador->sucursal_id,
            'nombre' => 'Caja sin cobertura',
            'activo' => true,
        ]);
        $qr = QrToken::generarPara($colaborador->sucursal, $puntoVenta, 60);

        $this->actingAs($colaborador->user)
            ->post(route('marcacion.store'), ['token' => $qr->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $colaborador->sucursal_id,
            'punto_venta_id' => $puntoVenta->id,
            'cobertura_operativa_id' => null,
        ]);
        $this->assertDatabaseMissing('coberturas_operativas', [
            'asignacion_turno_id' => $asignacion->id,
            'punto_venta_id' => $puntoVenta->id,
        ]);
    }

    public function test_a_mark_at_another_branch_creates_coverage_without_blocking_the_mark(): void
    {
        $this->withoutMiddleware();
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$colaborador, $asignacion] = $this->crearJornada('08:00:00', '17:00:00');
        $colaborador->user->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $otraSucursal = Sucursal::create(['nombre' => 'Sucursal de cobertura real', 'tipo' => 'tienda', 'activo' => true]);
        $puntoVenta = PuntoVenta::create(['sucursal_id' => $otraSucursal->id, 'nombre' => 'Caja de cobertura', 'activo' => true]);
        $qr = QrToken::generarPara($otraSucursal, $puntoVenta, 60);

        $this->actingAs($colaborador->user)
            ->post(route('marcacion.store'), ['token' => $qr->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cobertura = \App\Models\CoberturaOperativa::query()->sole();
        $this->assertSame($asignacion->id, $cobertura->asignacion_turno_id);
        $this->assertSame($otraSucursal->id, $cobertura->sucursal_id);
        $this->assertSame($puntoVenta->id, $cobertura->punto_venta_id);
        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $otraSucursal->id,
            'punto_venta_id' => $puntoVenta->id,
            'cobertura_operativa_id' => $cobertura->id,
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
