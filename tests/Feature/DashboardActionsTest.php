<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_only_the_shortcuts_allowed_for_the_user(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(
            Permission::findOrCreate('Create:Colaborador', 'web'),
            Permission::findOrCreate('View:AsignarTurnos', 'web'),
            Permission::findOrCreate('ViewAny:Marcacion', 'web'),
        );

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Crear colaborador')
            ->assertSee('Asignar turnos')
            ->assertSee('Marcaciones');
    }
}
