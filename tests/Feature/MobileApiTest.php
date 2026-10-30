<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\QrToken;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_issues_a_scoped_device_token_without_administrative_data(): void
    {
        [$user] = $this->crearColaborador();
        $user->forceFill(['password' => Hash::make('ClaveMovil123!')])->save();

        $this->postJson('/api/mobile/v1/sesion', [
            'email' => $user->email,
            'password' => 'ClaveMovil123!',
            'dispositivo' => 'Android de prueba',
        ])
            ->assertCreated()
            ->assertJsonPath('usuario.accesos.marcacion', true)
            ->assertJsonPath('usuario.accesos.visitas', false)
            ->assertJsonMissingPath('usuario.roles')
            ->assertJsonStructure(['token', 'vence_en', 'usuario' => ['id', 'nombre', 'accesos']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_mobile_login_accepts_an_email_with_different_letter_case(): void
    {
        [$user] = $this->crearColaborador();
        $user->forceFill([
            'email' => 'Colaborador.Movil@dimsum.com.pe',
            'password' => Hash::make('ClaveMovil123!'),
        ])->save();

        $this->postJson('/api/mobile/v1/sesion', [
            'email' => 'colaborador.movil@DIMSUM.COM.PE',
            'password' => 'ClaveMovil123!',
            'dispositivo' => 'Android con correo mixto',
        ])
            ->assertCreated()
            ->assertJsonPath('usuario.accesos.marcacion', true);
    }

    public function test_mobile_flow_validates_and_records_the_action_permitted_by_the_dynamic_qr(): void
    {
        [$user, $colaborador, $sucursal] = $this->crearColaborador();
        $token = $user->createToken('mobile:prueba', ['mobile:access'], now()->addDay())->plainTextToken;
        $qr = QrToken::generarPara($sucursal, null, 60, QrToken::PROPOSITO_ASISTENCIA);

        $this->withToken($token)
            ->postJson('/api/mobile/v1/marcaciones/validar-qr', ['token' => $qr->token])
            ->assertOk()
            ->assertJsonPath('confirmado', true)
            ->assertJsonPath('acciones.0.tipo', Marcacion::TIPO_ENTRADA);

        $this->withToken($token)
            ->postJson('/api/mobile/v1/marcaciones', [
                'token' => $qr->token,
                'accion' => Marcacion::TIPO_ENTRADA,
            ])
            ->assertCreated()
            ->assertJsonPath('marcacion.tipo', Marcacion::TIPO_ENTRADA)
            ->assertJsonPath('marcacion.confirmacion.evento', 'Ingreso de turno registrado');

        $this->assertDatabaseHas('marcaciones', [
            'colaborador_id' => $colaborador->id,
            'qr_token_id' => $qr->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
        ]);
    }

    public function test_mobile_login_rejects_account_without_mobile_operation_enabled(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $user = User::factory()->create([
            'email' => 'sin.acceso@dimsum.com.pe',
            'password' => Hash::make('Clave123!'),
            'activo' => true,
        ]);

        $this->postJson('/api/mobile/v1/sesion', [
            'email' => 'sin.acceso@dimsum.com.pe',
            'password' => 'Clave123!',
            'dispositivo' => 'Android sin permisos',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Esta cuenta no tiene una operación móvil habilitada.');
    }

    public function test_mobile_can_retrieve_today_attendance_summary(): void
    {
        [$user, $colaborador, $sucursal] = $this->crearColaborador();
        $token = $user->createToken('mobile:prueba', ['mobile:access'], now()->addDay())->plainTextToken;

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now()->startOfDay()->addHours(8),
        ]);

        $this->withToken($token)
            ->getJson('/api/mobile/v1/marcaciones/hoy')
            ->assertOk()
            ->assertJsonPath('fecha', now()->toDateString())
            ->assertJsonPath('marcaciones.0.tipo', Marcacion::TIPO_ENTRADA)
            ->assertJsonPath('marcaciones.0.etiqueta', 'Ingreso de turno');
    }

    public function test_mobile_logout_deletes_current_access_token(): void
    {
        [$user] = $this->crearColaborador();
        $token = $user->createToken('mobile:dispositivo', ['mobile:access'], now()->addDay())->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->deleteJson('/api/mobile/v1/sesion')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_supervisor_mobile_flow_records_store_visit(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $supervisor = User::factory()->create(['activo' => true]);
        $supervisor->assignRole('supervisor');
        $sucursal = Sucursal::create(['nombre' => 'Local supervisado', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        $token = $supervisor->createToken('mobile:supervisor', ['mobile:access'], now()->addDay())->plainTextToken;
        $qr = QrToken::generarPara($sucursal, null, 60, QrToken::PROPOSITO_VISITA_SUPERVISOR);

        $this->withToken($token)
            ->postJson('/api/mobile/v1/visitas/validar-qr', ['token' => $qr->token])
            ->assertOk()
            ->assertJsonPath('confirmado', true)
            ->assertJsonPath('accion', 'ingreso')
            ->assertJsonPath('local', 'Local supervisado');

        $this->withToken($token)
            ->postJson('/api/mobile/v1/visitas', [
                'token' => $qr->token,
                'accion' => 'ingreso',
            ])
            ->assertCreated()
            ->assertJsonPath('visita.accion', 'ingreso')
            ->assertJsonPath('visita.mensaje', 'Ingreso de visita registrado');
    }

    /** @return array{User, Colaborador, Sucursal} */
    private function crearColaborador(): array
    {
        $this->seed(RolesYPermisosSeeder::class);
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole('operador');
        $sucursal = Sucursal::create(['nombre' => 'Local móvil', 'tipo' => 'tienda', 'activo' => true]);
        $colaborador = Colaborador::create([
            'user_id' => $user->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador móvil',
            'documento_identidad' => 'MOBILE-'.uniqid(),
            'activo' => true,
        ]);

        return [$user, $colaborador, $sucursal];
    }
}
