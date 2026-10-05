<?php

namespace Tests\Feature;

use App\Filament\Pages\AuditoriaOperativa;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\QrToken;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\TurnoOperativo;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuditoriaOperativaTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_page_exposes_only_actionable_findings_in_the_allowed_scope(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $administrador = User::factory()->create();
        $administrador->assignRole('administrador');
        $sucursal = $this->sucursal(['nombre' => 'Local auditado']);
        $colaborador = Colaborador::create([
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Sin cuenta ni turno',
            'documento_identidad' => 'AUD-001',
            'activo' => true,
        ]);

        $this->actingAs($administrador);
        $this->assertTrue(AuditoriaOperativa::canAccess());

        $page = app(AuditoriaOperativa::class);
        $hallazgos = new ReflectionMethod($page, 'hallazgos');
        $resultadoInicial = $hallazgos->invoke($page);

        $this->assertCount(2, $resultadoInicial);
        $this->assertTrue($resultadoInicial->contains('hallazgo', 'Sin turno operativo aplicable'));
        $this->assertTrue($resultadoInicial->contains('hallazgo', 'Sin cuenta de acceso'));

        $turno = Turno::create([
            'nombre' => 'Apertura',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'activo' => true,
        ]);
        TurnoOperativo::create([
            'sucursal_id' => $sucursal->id,
            'turno_id' => $turno->id,
            'activo' => true,
        ]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => now()->toDateString(),
        ]);
        IncidenciaMarcacion::create([
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => IncidenciaMarcacion::TIPO_SALIDA_TURNO_PENDIENTE,
            'detectada_en' => now(),
        ]);

        $resultadoConfigurado = $hallazgos->invoke($page);
        $this->assertFalse($resultadoConfigurado->contains('hallazgo', 'Sin turno operativo aplicable'));
        $this->assertTrue($resultadoConfigurado->contains('hallazgo', 'Incidencia de marcación pendiente'));
    }

    public function test_expired_unused_qr_tokens_are_purged_but_used_ones_are_preserved(): void
    {
        $sucursal = $this->sucursal();
        $unused = QrToken::generarPara($sucursal, null, -7200);
        $used = QrToken::generarPara($sucursal, null, -7200);
        $colaborador = $this->colaborador($sucursal);

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'qr_token_id' => $used->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now()->subHours(2),
        ]);

        $this->artisan('qr:purge-expired', ['--hours' => 1])->assertExitCode(0);

        $this->assertDatabaseMissing('qr_tokens', ['id' => $unused->id]);
        $this->assertDatabaseHas('qr_tokens', ['id' => $used->id]);
    }

    public function test_only_an_active_point_of_sale_can_render_or_issue_qr(): void
    {
        $sucursal = $this->sucursal();
        $puntoVenta = PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja de prueba',
            'activo' => true,
        ]);

        $this->get(route('estacion-marcado.show', [
            'sucursal' => $sucursal->id,
            'clave' => $sucursal->token_pantalla,
        ]))->assertNotFound();

        $this->get($puntoVenta->enlaceEstacion())->assertOk();
        $this->get(route('estacion-marcado.punto-venta.token', [
            'sucursal' => $sucursal->id,
            'puntoVenta' => $puntoVenta->id,
            'clave' => $puntoVenta->token_pantalla,
        ]))->assertOk()->assertJsonStructure(['qr', 'segundos_restantes']);

        $puntoVenta->update(['activo' => false]);
        $this->get($puntoVenta->enlaceEstacion())->assertNotFound();
    }

    public function test_qr_station_modal_renders_using_only_point_of_sale_data(): void
    {
        $this->view('filament.actions.estacion-qr', [
            'estacion' => [
                'nombre' => 'Caja de prueba',
                'sucursal' => 'Tienda de prueba',
                'url' => 'https://example.test/estacion-marcado/1/1?clave=secreta',
            ],
            'qr' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
        ])
            ->assertSee('Tienda de prueba')
            ->assertSee('Caja de prueba');
    }

    public function test_supervisor_cannot_view_collaborators_outside_assigned_locations(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $propia = $this->sucursal(['nombre' => 'Local propio']);
        $ajena = $this->sucursal(['nombre' => 'Local ajeno']);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $supervisor->givePermissionTo(
            Permission::findByName('View:Marcacion', 'web'),
            Permission::findOrCreate('View:Colaborador', 'web'),
        );
        $supervisor = $supervisor->fresh();
        $this->assertTrue($supervisor->hasPermissionTo('View:Marcacion'));
        $supervisor->sucursalesSupervisadas()->attach($propia);
        $colaboradorPropio = $this->colaborador($propia);
        $colaboradorAjeno = $this->colaborador($ajena);
        $marcacionPropia = Marcacion::create([
            'colaborador_id' => $colaboradorPropio->id,
            'sucursal_id' => $propia->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now(),
        ]);
        $marcacionAjena = Marcacion::create([
            'colaborador_id' => $colaboradorAjeno->id,
            'sucursal_id' => $ajena->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now(),
        ]);

        $this->assertTrue($supervisor->can('view', $colaboradorPropio));
        $this->assertFalse($supervisor->can('view', $colaboradorAjeno));
        $this->assertTrue($supervisor->can('view', $marcacionPropia));
        $this->assertFalse($supervisor->can('view', $marcacionAjena));
    }

    public function test_past_schedule_and_employee_with_attendance_cannot_be_deleted_or_changed(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $admin->givePermissionTo(Permission::findOrCreate('Update:Turno', 'web'));
        $sucursal = $this->sucursal();
        $colaborador = $this->colaborador($sucursal);
        $turno = Turno::create(['nombre' => 'Turno histórico', 'hora_inicio' => '08:00', 'hora_fin' => '17:00', 'activo' => true]);
        $asignacion = AsignacionTurno::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'fecha' => now()->subDay()->toDateString()]);
        Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'sucursal_id' => $sucursal->id, 'tipo' => Marcacion::TIPO_ENTRADA, 'fecha_hora' => now()->subDay()]);

        $this->assertFalse($admin->can('update', $asignacion));
        $this->assertFalse($admin->can('delete', $asignacion));
        $this->assertTrue($admin->can('update', $turno));
        $this->assertFalse($admin->can('delete', $colaborador->user));
    }

    /** @param array<string, mixed> $attributes */
    private function sucursal(array $attributes = []): Sucursal
    {
        return Sucursal::create([...[
            'nombre' => 'Sucursal ' . uniqid(),
            'tipo' => 'planta',
            'activo' => true,
        ], ...$attributes]);
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
