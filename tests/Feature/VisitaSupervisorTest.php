<?php

namespace Tests\Feature;

use App\Models\Sucursal;
use App\Models\PuntoVenta;
use App\Models\User;
use App\Models\VisitaSupervisor;
use App\Models\QrToken;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VisitaSupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_must_confirm_the_scan_before_one_daily_visit_is_registered(): void
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
            ->assertSee('Confirmar visita')
            ->assertSee('Registrar visita');

        $this->assertDatabaseMissing('visitas_supervisor', [
            'supervisor_id' => $supervisor->id,
            'sucursal_id' => $propia->id,
            'fecha' => today()->toDateString(),
        ]);

        $this->actingAs($supervisor)
            ->post(route('visita-supervisor.store'), ['token' => $tokenPropio->token])
            ->assertOk()
            ->assertSee('Visita registrada');

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
        ]);

        $this->actingAs($supervisor)
            ->post(route('visita-supervisor.store'), ['token' => $tokenPropio->token])
            ->assertOk()
            ->assertSee('Visita ya registrada hoy');

        $tokenAjeno = $this->emitirTokenVisita($this->puntoVenta($ajena));

        $this->actingAs($supervisor)
            ->get(route('visita-supervisor.show', ['token' => $tokenAjeno->token]))
            ->assertForbidden();
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

    public function test_supervisor_without_a_collaborator_profile_is_sent_to_the_visit_qr_flow(): void
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
            ->assertRedirect(route('visita-supervisor.esperando'));

        $this->actingAs($supervisor)
            ->get(route('visita-supervisor.esperando'))
            ->assertOk()
            ->assertSee('Escanear QR de visita')
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
