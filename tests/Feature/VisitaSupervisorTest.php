<?php

namespace Tests\Feature;

use App\Models\Sucursal;
use App\Models\PuntoVenta;
use App\Models\User;
use App\Models\VisitaSupervisor;
use App\Models\VisitaSupervisorMarcacion;
use App\Models\QrToken;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VisitaSupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_must_confirm_an_entry_and_exit_for_the_same_visit(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $propia = Sucursal::create(['nombre' => 'Local propio', 'tipo' => 'tienda', 'activo' => true]);
        $ajena = Sucursal::create(['nombre' => 'Local ajeno', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $supervisor->givePermissionTo(Permission::findOrCreate('Registrar:VisitaSupervisor', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($propia);

        $tokenPropio = $this->emitirTokenVisita($this->puntoVenta($propia));

        $this->actingAs($supervisor)
            ->get(route('visita-supervisor.show', ['token' => $tokenPropio->token]))
            ->assertOk()
            ->assertSee('Marcación de visita')
            ->assertSee('Registrar ingreso de visita')
            ->assertSee('Registrar salida de visita')
            ->assertSee('name="accion" value="ingreso"', false)
            ->assertSee('name="accion" value="salida"', false);

        $this->assertDatabaseMissing('visitas_supervisor', [
            'supervisor_id' => $supervisor->id,
            'sucursal_id' => $propia->id,
            'fecha' => today()->toDateString(),
        ]);

        $this->actingAs($supervisor)
            ->post(route('visita-supervisor.store'), ['token' => $tokenPropio->token, 'accion' => 'ingreso'])
            ->assertOk()
            ->assertSee('Ingreso registrado');

        $this->assertSame(1, VisitaSupervisor::query()
            ->where('supervisor_id', $supervisor->id)
            ->where('sucursal_id', $propia->id)
            ->whereDate('fecha', today())
            ->count());
        $this->assertDatabaseHas('visitas_supervisor', [
            'supervisor_id' => $supervisor->id,
            'sucursal_id' => $propia->id,
            'punto_venta_id' => $tokenPropio->punto_venta_id,
            'qr_token_id' => $tokenPropio->id,
            'estado' => VisitaSupervisor::EN_CURSO,
        ]);

        $tokenSalida = $this->emitirTokenVisita($this->puntoVenta($propia));
        $this->actingAs($supervisor)
            ->get(route('visita-supervisor.show', ['token' => $tokenSalida->token]))
            ->assertOk()
            ->assertSee('Registrar salida de visita');

        $this->actingAs($supervisor)
            ->post(route('visita-supervisor.store'), ['token' => $tokenSalida->token, 'accion' => 'salida'])
            ->assertOk()
            ->assertSee('Salida registrada');

        $this->assertDatabaseHas('visitas_supervisor', [
            'supervisor_id' => $supervisor->id,
            'sucursal_id' => $propia->id,
            'estado' => VisitaSupervisor::FINALIZADA,
        ]);
        $this->assertSame(2, VisitaSupervisorMarcacion::query()->where('supervisor_id', $supervisor->id)->count());

        $this->actingAs($supervisor)
            ->post(route('visita-supervisor.store'), ['token' => $tokenSalida->token, 'accion' => 'salida'])
            ->assertStatus(409)
            ->assertSee('Escanea nuevamente');

        $tokenAjeno = $this->emitirTokenVisita($this->puntoVenta($ajena));

        $this->actingAs($supervisor)
            ->get(route('visita-supervisor.show', ['token' => $tokenAjeno->token]))
            ->assertForbidden();
    }

    public function test_supervisor_cannot_open_a_visit_in_another_branch_before_closing_the_current_one(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $primera = Sucursal::create(['nombre' => 'Primera', 'tipo' => 'tienda', 'activo' => true]);
        $segunda = Sucursal::create(['nombre' => 'Segunda', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $supervisor->givePermissionTo(Permission::findOrCreate('Registrar:VisitaSupervisor', 'web'));
        $supervisor->sucursalesSupervisadas()->attach([$primera->id, $segunda->id]);

        $tokenEntrada = $this->emitirTokenVisita($this->puntoVenta($primera));
        $this->actingAs($supervisor)->post(route('visita-supervisor.store'), ['token' => $tokenEntrada->token, 'accion' => 'ingreso'])->assertOk();

        $tokenOtroLocal = $this->emitirTokenVisita($this->puntoVenta($segunda));
        $this->actingAs($supervisor)
            ->get(route('visita-supervisor.show', ['token' => $tokenOtroLocal->token]))
            ->assertStatus(409)
            ->assertSee('Primero registra tu salida')
            ->assertSee('Primera');

        $this->assertSame(1, VisitaSupervisor::query()->where('supervisor_id', $supervisor->id)->count());
        $this->assertSame(1, VisitaSupervisorMarcacion::query()->where('supervisor_id', $supervisor->id)->count());
    }

    public function test_supervisor_visit_rejects_a_stale_or_invalid_selected_action(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $sucursal = Sucursal::create(['nombre' => 'Local con acción protegida', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $supervisor->givePermissionTo(Permission::findOrCreate('Registrar:VisitaSupervisor', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $token = $this->emitirTokenVisita($this->puntoVenta($sucursal));

        $this->actingAs($supervisor)
            ->post(route('visita-supervisor.store'), ['token' => $token->token, 'accion' => 'salida'])
            ->assertRedirect()
            ->assertSessionHasErrors('accion');

        $this->assertDatabaseMissing('visitas_supervisor', [
            'supervisor_id' => $supervisor->id,
            'sucursal_id' => $sucursal->id,
        ]);
    }

    public function test_administrator_roles_cannot_register_a_supervisor_visit_from_the_link(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $sucursal = Sucursal::create(['nombre' => 'Local de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $administrador = User::factory()->create();
        $administrador->assignRole('super_admin');

        $token = $this->emitirTokenVisita($this->puntoVenta($sucursal));

        $this->actingAs($administrador)
            ->get(route('visita-supervisor.show', ['token' => $token->token]))
            ->assertForbidden();

        $this->assertDatabaseMissing('visitas_supervisor', [
            'supervisor_id' => $administrador->id,
            'sucursal_id' => $sucursal->id,
            'fecha' => today()->toDateString(),
        ]);
    }

    public function test_station_preview_shows_the_qr_without_recording_a_visit(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local QR', 'tipo' => 'tienda', 'activo' => true]);
        $puntoVenta = $this->puntoVenta($sucursal);

        $this->get($puntoVenta->enlaceEstacionVisita())
            ->assertOk()
            ->assertSee('Código QR dinámico de visita de supervisión')
            ->assertDontSee('La supervisora debe iniciar sesión');

        $this->assertDatabaseMissing('visitas_supervisor', ['sucursal_id' => $sucursal->id]);

        $this->get(route('estacion-visita.punto-venta.token', [
            'sucursal' => $sucursal->id,
            'puntoVenta' => $puntoVenta->id,
            'clave' => $puntoVenta->token_pantalla,
        ]))
            ->assertOk()
            ->assertJsonStructure(['qr', 'segundos_restantes'])
            ->assertJsonPath('segundos_restantes', 60);

        $this->assertDatabaseHas('qr_tokens', [
            'sucursal_id' => $sucursal->id,
            'proposito' => QrToken::PROPOSITO_VISITA_SUPERVISOR,
        ]);
    }

    public function test_legacy_static_visit_qr_opens_the_dynamic_station_without_recording_a_visit(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local anterior', 'tipo' => 'tienda', 'activo' => true]);
        $puntoVenta = $this->puntoVenta($sucursal);

        $this->get(route('visita-supervisor.legacy', [
            'sucursal' => $sucursal->id,
            'puntoVenta' => $puntoVenta->id,
            'clave' => $puntoVenta->token_pantalla,
        ]))
            ->assertOk()
            ->assertSee('Código QR dinámico de visita de supervisión');

        $this->assertDatabaseMissing('visitas_supervisor', ['sucursal_id' => $sucursal->id]);
    }

    public function test_expired_visit_qr_shows_a_clear_retry_message(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local vencido', 'tipo' => 'tienda', 'activo' => true]);
        $token = QrToken::generarPara(
            $sucursal,
            null,
            -1,
            QrToken::PROPOSITO_VISITA_SUPERVISOR,
        );

        $this->actingAs(User::factory()->create())
            ->get(route('visita-supervisor.show', ['token' => $token->token]))
            ->assertStatus(410)
            ->assertSee('Código QR vencido')
            ->assertSee('Vuelve a escanear');
    }

    public function test_supervisor_without_a_collaborator_profile_is_offered_visits_and_administration(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $supervisor = User::factory()->create([
            'email' => 'supervisor@example.test',
            'password' => Hash::make('ClaveDePrueba123!'),
        ]);
        $supervisor->assignRole('supervisor');

        $this->assertNull($supervisor->colaborador);

        $this->withSession(['_token' => 'token-de-prueba'])
            ->post(route('login'), [
                '_token' => 'token-de-prueba',
                'email' => $supervisor->email,
                'password' => 'ClaveDePrueba123!',
            ])
            ->assertRedirect(route('acceso.portal'));

        $this->actingAs($supervisor)
            ->get(route('acceso.portal'))
            ->assertOk()
            ->assertSee('Registrar visita')
            ->assertSee('Panel administrativo');

        $this->actingAs($supervisor)
            ->get(route('visita-supervisor.esperando'))
            ->assertOk()
            ->assertSee('Escanear QR de visita')
            ->assertSee('Ver acciones de visita')
            ->assertSee('data-validar-antes="0"', false)
            ->assertSee('/visitas-supervisor?token=');
    }

    private function emitirTokenVisita(PuntoVenta $puntoVenta): QrToken
    {
        $this->get(route('estacion-visita.punto-venta.token', [
            'sucursal' => $puntoVenta->sucursal_id,
            'puntoVenta' => $puntoVenta->id,
            'clave' => $puntoVenta->token_pantalla,
        ]))->assertOk();

        return QrToken::query()
            ->where('punto_venta_id', $puntoVenta->id)
            ->where('proposito', QrToken::PROPOSITO_VISITA_SUPERVISOR)
            ->latest('id')
            ->firstOrFail();
    }

    private function puntoVenta(Sucursal $sucursal): PuntoVenta
    {
        return PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Punto de venta ' . uniqid(),
            'activo' => true,
        ]);
    }
}
