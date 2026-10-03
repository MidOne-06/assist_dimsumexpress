<?php

namespace Tests\Feature;

use App\Filament\Pages\AsignarTurnos;
use App\Filament\Pages\CalendarioTurnos;
use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
use App\Filament\Resources\AsignacionTurnos\Pages\ListAsignacionTurnos;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Services\CalendarioTurnosSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class SupervisionTurnosTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_assigns_future_shifts_only_to_collaborators_of_a_selected_location(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:AsignarTurnos', 'web'),
            Permission::findOrCreate('AsignarMasivo:AsignarTurnos', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($propia);
        $colaboradorPropio = $this->colaborador($propia);
        $this->colaborador($ajena);
        $turno = Turno::create(['nombre' => 'Turno prueba', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $fecha = now()->addDay()->toDateString();

        Livewire::actingAs($supervisor)
            ->test(AsignarTurnos::class)
            ->mountAction('asignarPorRango')
            ->set('mountedActions.0.data.sucursal_id', $propia->id)
            ->set('mountedActions.0.data.colaborador_ids', [$colaboradorPropio->id])
            ->set('mountedActions.0.data.turno_id', $turno->id)
            ->set('mountedActions.0.data.fecha_inicio', $fecha)
            ->set('mountedActions.0.data.fecha_fin', $fecha)
            ->set('mountedActions.0.data.dias_semana', [(string) now()->addDay()->isoWeekday()])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertTrue(AsignacionTurno::query()
            ->where('colaborador_id', $colaboradorPropio->id)
            ->where('turno_id', $turno->id)
            ->whereDate('fecha', $fecha)
            ->exists());
    }

    public function test_calendar_resets_an_out_of_scope_location_for_a_supervisor(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:CalendarioTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($propia);

        Livewire::actingAs($supervisor)
            ->test(CalendarioTurnos::class)
            ->set('sucursalId', $ajena->id)
            ->assertSet('sucursalId', $propia->id);
    }

    public function test_mass_assignment_page_lists_only_upcoming_assignments_within_scope(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:AsignarTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($propia);
        $turno = Turno::create(['nombre' => 'Turno prueba', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $propiaAsignacion = AsignacionTurno::create([
            'colaborador_id' => $this->colaborador($propia)->id,
            'turno_id' => $turno->id,
            'fecha' => now()->addDay()->toDateString(),
        ]);
        $ajenaAsignacion = AsignacionTurno::create([
            'colaborador_id' => $this->colaborador($ajena)->id,
            'turno_id' => $turno->id,
            'fecha' => now()->addDay()->toDateString(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(AsignarTurnos::class)
            ->assertSee('Próximas asignaciones')
            ->assertCanSeeTableRecords([$propiaAsignacion])
            ->assertCanNotSeeTableRecords([$ajenaAsignacion]);
    }

    public function test_mass_assignment_updates_an_existing_future_assignment_without_creating_duplicates(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:AsignarTurnos', 'web'),
            Permission::findOrCreate('AsignarMasivo:AsignarTurnos', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $anterior = Turno::create(['nombre' => 'Anterior', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $nuevo = Turno::create(['nombre' => 'Nuevo', 'hora_inicio' => '14:00', 'hora_fin' => '22:00', 'activo' => true]);
        $fecha = now()->addDay()->toDateString();
        $existente = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $anterior->id,
            'fecha' => $fecha,
            'observacion' => 'Programación inicial',
        ]);

        Livewire::actingAs($supervisor)
            ->test(AsignarTurnos::class)
            ->mountAction('asignarPorRango')
            ->set('mountedActions.0.data.sucursal_id', $sucursal->id)
            ->set('mountedActions.0.data.colaborador_ids', [$colaborador->id])
            ->set('mountedActions.0.data.turno_id', $nuevo->id)
            ->set('mountedActions.0.data.fecha_inicio', $fecha)
            ->set('mountedActions.0.data.fecha_fin', $fecha)
            ->set('mountedActions.0.data.dias_semana', [(string) now()->addDay()->isoWeekday()])
            ->set('mountedActions.0.data.observacion', 'Cobertura validada')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(1, AsignacionTurno::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('fecha', $fecha)
            ->count());
        $this->assertSame($nuevo->id, $existente->fresh()->turno_id);
        $this->assertSame('Cobertura validada', $existente->fresh()->observacion);
        $this->assertSame($supervisor->id, $existente->fresh()->asignado_por);
    }

    public function test_mass_assignment_does_not_overwrite_today_after_a_marking_exists(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:AsignarTurnos', 'web'),
            Permission::findOrCreate('AsignarMasivo:AsignarTurnos', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $anterior = Turno::create(['nombre' => 'Anterior', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $nuevo = Turno::create(['nombre' => 'Nuevo', 'hora_inicio' => '14:00', 'hora_fin' => '22:00', 'activo' => true]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $anterior->id,
            'fecha' => now()->toDateString(),
        ]);
        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $anterior->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(AsignarTurnos::class)
            ->mountAction('asignarPorRango')
            ->set('mountedActions.0.data.sucursal_id', $sucursal->id)
            ->set('mountedActions.0.data.colaborador_ids', [$colaborador->id])
            ->set('mountedActions.0.data.turno_id', $nuevo->id)
            ->set('mountedActions.0.data.fecha_inicio', now()->toDateString())
            ->set('mountedActions.0.data.fecha_fin', now()->toDateString())
            ->set('mountedActions.0.data.dias_semana', [(string) now()->isoWeekday()])
            ->callMountedAction();

        $this->assertSame($anterior->id, $asignacion->fresh()->turno_id);
    }

    public function test_mass_assignment_page_edits_a_future_assignment_in_a_native_table_modal(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:AsignarTurnos', 'web'),
            Permission::findOrCreate('Update:AsignacionTurno', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $apertura = Turno::create(['nombre' => 'Apertura', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $cierre = Turno::create(['nombre' => 'Cierre', 'hora_inicio' => '14:00', 'hora_fin' => '22:00', 'activo' => true]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $apertura->id,
            'fecha' => now()->addDay()->toDateString(),
        ]);
        $nuevaFecha = now()->addDays(2)->toDateString();

        $this->assertTrue($supervisor->can('update', $asignacion));

        Livewire::actingAs($supervisor)
            ->test(AsignarTurnos::class)
            ->assertTableActionExists('editar', null, $asignacion)
            ->callTableAction('editar', $asignacion, [
                'turno_id' => $cierre->id,
                'fecha' => $nuevaFecha,
                'observacion' => 'Cambio validado',
            ]);

        $asignacion->refresh();
        $this->assertSame($cierre->id, $asignacion->turno_id);
        $this->assertSame($nuevaFecha, $asignacion->fecha->toDateString());
        $this->assertSame('Cambio validado', $asignacion->observacion);
    }

    public function test_calendar_navigation_and_today_button_restore_the_current_period(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:CalendarioTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        Livewire::actingAs($supervisor)
            ->test(CalendarioTurnos::class)
            ->call('mesAnterior')
            ->assertSet('mes', now()->subMonthNoOverflow()->format('Y-m'))
            ->call('mesSiguiente')
            ->assertSet('mes', now()->format('Y-m'))
            ->call('irAHoy')
            ->assertSet('mes', now()->format('Y-m'))
            ->assertDispatched('calendario-turnos-ir-a-hoy');
    }

    public function test_calendar_only_renders_shifts_and_collaborators_for_the_supervisors_location_and_month(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:CalendarioTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($propia);
        $colaboradorPropio = $this->colaborador($propia);
        $colaboradorAjeno = $this->colaborador($ajena);
        $turnoPropio = Turno::create(['nombre' => 'Turno propio', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $turnoAjeno = Turno::create(['nombre' => 'Turno ajeno', 'hora_inicio' => '10:00', 'hora_fin' => '19:00', 'activo' => true]);

        AsignacionTurno::create(['colaborador_id' => $colaboradorPropio->id, 'turno_id' => $turnoPropio->id, 'fecha' => now()->toDateString()]);
        AsignacionTurno::create(['colaborador_id' => $colaboradorAjeno->id, 'turno_id' => $turnoAjeno->id, 'fecha' => now()->toDateString()]);

        Livewire::actingAs($supervisor)
            ->test(CalendarioTurnos::class)
            ->assertSee('Turno propio')
            ->assertDontSee('Turno ajeno')
            ->assertSee($colaboradorPropio->nombre_completo)
            ->assertDontSee($colaboradorAjeno->nombre_completo);
    }

    public function test_calendar_matches_an_entry_to_its_assigned_shift_and_keeps_historical_rows(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:CalendarioTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $turnoProgramado = Turno::create(['nombre' => 'Apertura', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'tolerancia_entrada_minutos' => 10, 'activo' => true]);
        $turnoDistinto = Turno::create(['nombre' => 'Cierre', 'hora_inicio' => '14:00', 'hora_fin' => '22:00', 'activo' => true]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turnoProgramado->id,
            'fecha' => now()->toDateString(),
        ]);

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turnoDistinto->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now()->setTime(14, 0, 1),
        ]);

        $this->actingAs($supervisor);
        $calendario = app(CalendarioTurnos::class);
        $calendario->mount();

        $estado = $calendario->estadoAsignacion($asignacion->fresh('turno'));
        $this->assertSame('turno_distinto', $estado['estado']);
        $this->assertSame('14:00:01', $estado['hora']);

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turnoProgramado->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now()->setTime(8, 10, 1),
        ]);

        $calendario = app(CalendarioTurnos::class);
        $calendario->mount();
        $estado = $calendario->estadoAsignacion($asignacion->fresh('turno'));
        $this->assertSame('tardanza', $estado['estado']);
        $this->assertSame('Tardanza de 1 min', $estado['label']);
        $this->assertSame('08:10:01', $estado['hora']);

        $colaborador->update(['activo' => false]);
        $turnoProgramado->update(['activo' => false]);

        $calendario = app(CalendarioTurnos::class);
        $calendario->mount();
        $this->assertCount(1, $calendario->colaboradores);
        $this->assertCount(1, $calendario->turnosActivos);
    }

    public function test_supervisor_can_assign_a_shift_from_the_calendar_modal_only_for_its_location(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:CalendarioTurnos', 'web'),
            Permission::findOrCreate('Create:AsignacionTurno', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $turno = Turno::create(['nombre' => 'Turno calendario', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $fecha = now()->addDay()->toDateString();

        Livewire::actingAs($supervisor)
            ->test(CalendarioTurnos::class)
            ->mountAction('asignarTurno')
            ->set('mountedActions.0.data.colaborador_id', $colaborador->id)
            ->set('mountedActions.0.data.turno_id', $turno->id)
            ->set('mountedActions.0.data.fecha', $fecha)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertTrue(AsignacionTurno::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('turno_id', $turno->id)
            ->whereDate('fecha', $fecha)
            ->where('asignado_por', $supervisor->id)
            ->exists());
    }

    public function test_calendar_uses_a_modal_to_update_a_future_assignment(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:CalendarioTurnos', 'web'),
            Permission::findOrCreate('Update:AsignacionTurno', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $apertura = Turno::create(['nombre' => 'Apertura', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $cierre = Turno::create(['nombre' => 'Cierre', 'hora_inicio' => '14:00', 'hora_fin' => '22:00', 'activo' => true]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $apertura->id,
            'fecha' => now()->addDay()->toDateString(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CalendarioTurnos::class)
            ->call('abrirEdicionAsignacion', $asignacion->id)
            ->set('mountedActions.0.data.turno_id', $cierre->id)
            ->set('mountedActions.0.data.fecha', now()->addDays(2)->toDateString())
            ->set('mountedActions.0.data.observacion', 'Cambio operativo')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $asignacion->refresh();
        $this->assertSame($cierre->id, $asignacion->turno_id);
        $this->assertSame(now()->addDays(2)->toDateString(), $asignacion->fecha->toDateString());
        $this->assertSame('Cambio operativo', $asignacion->observacion);
    }

    public function test_calendar_opens_the_edit_modal_through_the_assignment_identifier(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:CalendarioTurnos', 'web'),
            Permission::findOrCreate('Update:AsignacionTurno', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $turno = Turno::create(['nombre' => 'Apertura', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => now()->addDay()->toDateString(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CalendarioTurnos::class)
            ->call('abrirEdicionAsignacion', $asignacion->id)
            ->assertActionMounted('editarAsignacion')
            ->assertSet('asignacionEditandoId', $asignacion->id)
            ->assertHasNoActionErrors();
    }

    public function test_calendar_groups_every_location_by_location_and_shift_and_exports_the_period(): void
    {
        $primera = $this->sucursal('Local uno');
        $segunda = $this->sucursal('Local dos');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:CalendarioTurnos', 'web'));
        $supervisor->sucursalesSupervisadas()->attach([$primera->id, $segunda->id]);
        $turno = Turno::create(['nombre' => 'Apertura', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);

        AsignacionTurno::create(['colaborador_id' => $this->colaborador($primera)->id, 'turno_id' => $turno->id, 'fecha' => now()->toDateString()]);
        AsignacionTurno::create(['colaborador_id' => $this->colaborador($segunda)->id, 'turno_id' => $turno->id, 'fecha' => now()->toDateString()]);

        $this->actingAs($supervisor);
        $calendario = app(CalendarioTurnos::class);
        $calendario->mount();

        $this->assertNull($calendario->sucursalId);
        $this->assertCount(2, $calendario->filasCalendario);

        $respuesta = app(CalendarioTurnosSpreadsheetService::class)->exportar(
            AsignacionTurno::query()->whereIn('colaborador_id', $calendario->colaboradores->pluck('id')),
        );

        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $respuesta->headers->get('Content-Type'));

        ob_start();
        $respuesta->sendContent();
        $contenido = ob_get_clean();
        $archivo = tempnam(sys_get_temp_dir(), 'calendario-xlsx-');
        file_put_contents($archivo, $contenido);

        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($archivo) === true);
            $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $estilos = (string) $zip->getFromName('xl/styles.xml');
            $zip->close();

            $this->assertStringContainsString('<autoFilter', $hoja);
            $this->assertStringContainsString('<pane', $hoja);
            $this->assertStringContainsString('D97706', $estilos);
        } finally {
            @unlink($archivo);
        }
    }

    public function test_supervisor_assigns_a_date_range_from_the_assignments_list(): void
    {
        $sucursal = $this->sucursal('Local propio');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('ViewAny:AsignacionTurno', 'web'),
            Permission::findOrCreate('AsignarMasivo:AsignarTurnos', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal);
        $turno = Turno::create(['nombre' => 'Turno por rango', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $desde = now()->addDay()->toDateString();
        $hasta = now()->addDays(3)->toDateString();
        $dias = collect(range(1, 3))
            ->map(fn (int $diasDesdeManana): string => (string) now()->addDays($diasDesdeManana)->isoWeekday())
            ->unique()
            ->values()
            ->all();

        Livewire::actingAs($supervisor)
            ->test(ListAsignacionTurnos::class)
            ->mountAction('asignarPorRango')
            ->set('mountedActions.0.data.sucursal_id', $sucursal->id)
            ->set('mountedActions.0.data.colaborador_ids', [$colaborador->id])
            ->set('mountedActions.0.data.turno_id', $turno->id)
            ->set('mountedActions.0.data.fecha_inicio', $desde)
            ->set('mountedActions.0.data.fecha_fin', $hasta)
            ->set('mountedActions.0.data.dias_semana', $dias)
            ->callMountedAction()
            ->assertHasNoErrors();

        foreach (range(1, 3) as $diasDesdeManana) {
            $this->assertTrue(AsignacionTurno::query()
                ->where('colaborador_id', $colaborador->id)
                ->where('turno_id', $turno->id)
                ->whereDate('fecha', now()->addDays($diasDesdeManana)->toDateString())
                ->exists());
        }
    }

    public function test_assignment_detail_uses_modal_only_routes_and_validates_individual_creation_scope(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('ViewAny:AsignacionTurno', 'web'),
            Permission::findOrCreate('Create:AsignacionTurno', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($propia);
        $colaboradorPropio = $this->colaborador($propia);
        $colaboradorAjeno = $this->colaborador($ajena);
        $turno = Turno::create(['nombre' => 'Turno individual', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);

        $this->assertSame(['index'], array_keys(AsignacionTurnoResource::getPages()));

        Livewire::actingAs($supervisor)
            ->test(ListAsignacionTurnos::class)
            ->mountAction('create')
            ->set('mountedActions.0.data.colaborador_id', $colaboradorPropio->id)
            ->set('mountedActions.0.data.turno_id', $turno->id)
            ->set('mountedActions.0.data.fecha', now()->addDay()->toDateString())
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertTrue(AsignacionTurno::query()
            ->where('colaborador_id', $colaboradorPropio->id)
            ->where('turno_id', $turno->id)
            ->whereDate('fecha', now()->addDay())
            ->exists());
        $this->assertFalse(AsignacionTurno::query()
            ->where('colaborador_id', $colaboradorAjeno->id)
            ->whereDate('fecha', now()->addDay())
            ->exists());
    }

    private function sucursal(string $nombre): Sucursal
    {
        return Sucursal::create(['nombre' => $nombre, 'tipo' => 'tienda', 'activo' => true]);
    }

    private function colaborador(Sucursal $sucursal): Colaborador
    {
        return Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador ' . uniqid(),
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);
    }
}
