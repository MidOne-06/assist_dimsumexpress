<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PwaEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_installable_and_uses_the_current_login_as_start_url(): void
    {
        $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8')
            ->assertJsonPath('start_url', route('login'))
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('orientation', 'portrait')
            ->assertJsonPath('icons.0.src', app(\App\Services\AparienciaSistemaService::class)->logoAppMovilUrl());
    }

    public function test_service_worker_only_caches_static_assets(): void
    {
        $this->get(route('pwa.service-worker'))
            ->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/')
            ->assertSee("url.pathname.startsWith('/images/')", false)
            ->assertSee('caches.open', false);
    }

    public function test_an_unlinked_marking_account_is_not_offered_a_qr_scanner(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('Registrar:Marcacion', 'web'));

        $this->actingAs($user)
            ->get(route('marcacion.show'))
            ->assertOk()
            ->assertSee('No podemos habilitar la marcación de esta cuenta.')
            ->assertDontSee('Escanear QR de la estación')
            ->assertDontSee('data-qr-scanner', false);
    }
}
