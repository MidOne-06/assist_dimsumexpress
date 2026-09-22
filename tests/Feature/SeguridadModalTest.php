<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SeguridadModalTest extends TestCase
{
    public function test_security_resources_expose_only_their_list_routes_for_modal_management(): void
    {
        $this->assertSame(['index'], array_keys(UserResource::getPages()));
        $this->assertSame(['index'], array_keys(RoleResource::getPages()));
    }

    public function test_role_modal_uses_the_web_guard_internally(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('ViewAny:Role', 'web'),
            Permission::findOrCreate('Create:Role', 'web'),
        );

        Livewire::actingAs($usuario)
            ->test(ListRoles::class)
            ->mountAction('create')
            ->assertHasNoErrors();
    }
}
