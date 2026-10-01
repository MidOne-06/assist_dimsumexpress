<?php

namespace Tests\Feature;

use App\Filament\Pages\AparienciaSistema;
use App\Models\User;
use App\Services\AparienciaSistemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AparienciaSistemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_a_safe_default_identity_when_no_logo_has_been_uploaded(): void
    {
        $servicio = app(AparienciaSistemaService::class);
        $servicio->actual()->forceFill([
            'logo' => null,
            'logo_oscuro' => null,
            'icono' => null,
            'logo_app_movil' => null,
        ])->save();

        $this->assertSame('Sistema de Asistencias', $servicio->nombre());
        $this->assertStringContainsString('images/sistema-asistencias.svg', $servicio->logoUrl());
        $this->assertStringContainsString('images/sistema-asistencias.svg', $servicio->logoOscuroUrl());
        $this->assertStringContainsString('images/sistema-asistencias.svg', $servicio->iconoUrl());
        $this->assertStringContainsString('images/sistema-asistencias.svg', $servicio->logoAppMovilUrl());
        $this->assertSame('#f59e0b', $servicio->colorPrimario());
        $this->assertDatabaseHas('ajustes_sistema', ['id' => 1]);
    }

    public function test_only_authorized_users_can_update_the_identity(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::findOrCreate('Gestionar:AparienciaSistema', 'web'));

        $actualizado = app(AparienciaSistemaService::class)->actualizar($actor, [
            'nombre_sistema' => 'Control de Asistencia',
            'logo' => null,
            'logo_oscuro' => null,
            'icono' => null,
            'logo_app_movil' => null,
            'color_primario' => '#2563EB',
        ]);

        $this->assertSame('Control de Asistencia', $actualizado->nombre_sistema);
        $this->assertSame('#2563eb', $actualizado->color_primario);
        $this->assertDatabaseHas('ajustes_sistema', [
            'id' => 1,
            'nombre_sistema' => 'Control de Asistencia',
        ]);
    }

    public function test_an_unauthorized_user_cannot_update_the_identity(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(AparienciaSistemaService::class)->actualizar(User::factory()->create(), [
            'nombre_sistema' => 'Cambio no autorizado',
        ]);
    }

    public function test_the_native_filament_page_loads_and_saves_for_an_authorized_user(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(
            Permission::findOrCreate('View:AparienciaSistema', 'web'),
            Permission::findOrCreate('Gestionar:AparienciaSistema', 'web'),
        );

        Livewire::actingAs($actor)
            ->test(AparienciaSistema::class)
            ->assertSet('data.nombre_sistema', 'Sistema de Asistencias')
            ->set('data.nombre_sistema', 'Asistencias Dimsum')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ajustes_sistema', ['nombre_sistema' => 'Asistencias Dimsum']);
    }

    public function test_an_authorized_user_can_restore_the_default_identity(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::findOrCreate('Gestionar:AparienciaSistema', 'web'));

        app(AparienciaSistemaService::class)->actualizar($actor, [
            'nombre_sistema' => 'Marca temporal',
            'logo' => null,
            'logo_oscuro' => null,
            'icono' => null,
            'logo_app_movil' => null,
            'color_primario' => '#2563eb',
        ]);

        $restablecido = app(AparienciaSistemaService::class)->restablecer($actor);

        $this->assertSame('Sistema de Asistencias', $restablecido->nombre_sistema);
        $this->assertSame('#f59e0b', $restablecido->color_primario);
    }
}
