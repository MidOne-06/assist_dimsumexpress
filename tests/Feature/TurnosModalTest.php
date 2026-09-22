<?php

namespace Tests\Feature;

use App\Filament\Resources\Turnos\TurnoResource;
use App\Filament\Resources\Turnos\Pages\ListTurnos;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TurnosModalTest extends TestCase
{
    public function test_turnos_only_exposes_the_list_route_for_modal_management(): void
    {
        $this->assertSame(['index'], array_keys(TurnoResource::getPages()));
    }

    public function test_create_turno_modal_renders_its_reactive_fields(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('ViewAny:Turno', 'web'),
            Permission::findOrCreate('Create:Turno', 'web'),
        );

        Livewire::actingAs($usuario)
            ->test(ListTurnos::class)
            ->mountAction('create')
            ->assertHasNoErrors();
    }
}
