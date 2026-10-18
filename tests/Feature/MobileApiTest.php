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
