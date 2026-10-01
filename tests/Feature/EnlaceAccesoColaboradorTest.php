<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\EnlaceAccesoColaborador;
use App\Models\Sucursal;
use App\Models\User;
use App\Livewire\ActivarAccesoColaborador;
use App\Services\EnlacesAccesoColaboradorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Livewire\Livewire;

class EnlaceAccesoColaboradorTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_sets_password_is_single_use_and_does_not_log_in_on_get(): void
    {
        [$colaborador, $actor] = $this->colaboradorActivo();
        $resultado = app(EnlacesAccesoColaboradorService::class)->generar($colaborador, $actor, 15);

        $enlace = $resultado['enlace'];
        $this->assertNotSame($resultado['url'], $enlace->token_hash);
        $this->assertSame(64, strlen($enlace->token_hash));

        $this->get($resultado['url'])
            ->assertOk()
            ->assertSee('Establece tu contraseña')
            ->assertSee($colaborador->user->email)
            ->assertSee('8 caracteres')
            ->assertSee('Mayúscula')
            ->assertSee('Minúscula')
            ->assertSee('Número');

        $this->assertGuest();
        $this->assertNull($enlace->fresh()->usado_en);

        $this->post($resultado['url'], [
            'password' => 'AccesoSeguro2026!',
            'password_confirmation' => 'AccesoSeguro2026!',
            'recordar' => true,
        ])
            ->assertRedirect(route('marcacion.show'))
            ->assertSessionHas('acceso_operativo_via_enlace', true);

        $this->assertAuthenticatedAs($colaborador->user);
        $this->assertTrue(Hash::check('AccesoSeguro2026!', $colaborador->user->fresh()->password));
        $this->assertNotNull($enlace->fresh()->usado_en);

        $this->post($resultado['url'], [
            'password' => 'OtraClaveSegura2026!',
            'password_confirmation' => 'OtraClaveSegura2026!',
        ])
            ->assertStatus(410)
            ->assertSee('Enlace no disponible');
    }

    public function test_generating_a_new_link_revokes_the_previous_one(): void
    {
        [$colaborador, $actor] = $this->colaboradorActivo();
        $primero = app(EnlacesAccesoColaboradorService::class)->generar($colaborador, $actor, 15)['enlace'];
        $segundo = app(EnlacesAccesoColaboradorService::class)->generar($colaborador, $actor, 30)['enlace'];

        $this->assertNotNull($primero->fresh()->revocado_en);
        $this->assertTrue($segundo->fresh()->estaVigente());
        $this->assertSame(2, EnlaceAccesoColaborador::query()->count());
    }

    public function test_revoked_or_inactive_collaborator_link_cannot_start_a_session(): void
    {
        [$colaborador, $actor] = $this->colaboradorActivo();
        $resultado = app(EnlacesAccesoColaboradorService::class)->generar($colaborador, $actor, 15);
        $colaborador->desactivarAcceso();

        $this->post($resultado['url'], [
            'password' => 'AccesoSeguro2026!',
            'password_confirmation' => 'AccesoSeguro2026!',
        ])
            ->assertStatus(410)
            ->assertSee('Enlace no disponible');

        $this->assertGuest();
        $this->assertNotNull($resultado['enlace']->fresh()->revocado_en);
    }

    public function test_temporary_access_session_cannot_enter_the_admin_panel(): void
    {
        [$colaborador, $actor] = $this->colaboradorActivo();
        $colaborador->user->givePermissionTo(Permission::findOrCreate('Access:AdminPanel', 'web'));
        $resultado = app(EnlacesAccesoColaboradorService::class)->generar($colaborador, $actor, 15);

        $this->post($resultado['url'], [
            'password' => 'AccesoSeguro2026!',
            'password_confirmation' => 'AccesoSeguro2026!',
        ])->assertRedirect(route('marcacion.show'));

        $this->get('/admin')->assertForbidden();
    }

    public function test_link_keeps_its_validity_when_the_password_is_invalid(): void
    {
        [$colaborador, $actor] = $this->colaboradorActivo();
        $resultado = app(EnlacesAccesoColaboradorService::class)->generar($colaborador, $actor, 15);

        $this->from($resultado['url'])
            ->post($resultado['url'], [
                'password' => 'corta',
                'password_confirmation' => 'distinta',
            ])
            ->assertRedirect($resultado['url'])
            ->assertSessionHasErrors(['password']);

        $this->assertTrue($resultado['enlace']->fresh()->estaVigente());
        $this->assertGuest();
    }

    public function test_link_accepts_an_accessible_password_without_symbol(): void
    {
        [$colaborador, $actor] = $this->colaboradorActivo();
        $resultado = app(EnlacesAccesoColaboradorService::class)->generar($colaborador, $actor, 15);

        $this->post($resultado['url'], [
            'password' => 'Clave123A',
            'password_confirmation' => 'Clave123A',
        ])->assertRedirect(route('marcacion.show'));

        $this->assertTrue(Hash::check('Clave123A', $colaborador->user->fresh()->password));
    }

    public function test_filament_activation_form_renders_and_validates_the_password(): void
    {
        [$colaborador, $actor] = $this->colaboradorActivo();
        $resultado = app(EnlacesAccesoColaboradorService::class)->generar($colaborador, $actor, 15);
        $token = str($resultado['url'])->after('/acceso/')->toString();

        Livewire::test(ActivarAccesoColaborador::class, ['token' => $token])
            ->assertSet('disponible', true)
            ->assertSee('Establece tu contraseña')
            ->assertSee('8 caracteres')
            ->assertSee('Mayúscula')
            ->set('data.password', 'corta')
            ->set('data.password_confirmation', 'distinta')
            ->call('activar')
            ->assertHasErrors(['data.password', 'data.password_confirmation']);

        $this->assertGuest();
        $this->assertNull($resultado['enlace']->fresh()->usado_en);
    }

    /** @return array{Colaborador, User} */
    private function colaboradorActivo(): array
    {
        $sucursal = Sucursal::create(['nombre' => 'Local de enlace', 'tipo' => 'tienda', 'activo' => true]);
        $user = User::factory()->create(['activo' => true]);
        $user->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));
        $colaborador = Colaborador::create([
            'user_id' => $user->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador enlace',
            'documento_identidad' => 'LINK-' . uniqid(),
            'activo' => true,
        ]);

        return [$colaborador, User::factory()->create()];
    }
}
