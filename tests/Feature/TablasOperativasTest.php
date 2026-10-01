<?php

namespace Tests\Feature;

use App\Filament\Resources\AsignacionTurnos\Pages\ListAsignacionTurnos;
use App\Filament\Resources\Colaboradors\Pages\ListColaboradors;
use App\Filament\Resources\Marcacions\Pages\ListMarcacions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TablasOperativasTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_tables_render_with_their_compact_empty_states(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(
            Permission::findOrCreate('ViewAny:Colaborador', 'web'),
            Permission::findOrCreate('ViewAny:Marcacion', 'web'),
            Permission::findOrCreate('ViewAny:AsignacionTurno', 'web'),
        );

        Livewire::actingAs($user)->test(ListColaboradors::class)->assertSee('Sin colaboradores');
        Livewire::actingAs($user)->test(ListMarcacions::class)->assertSee('Sin marcaciones');
        Livewire::actingAs($user)->test(ListAsignacionTurnos::class)->assertSee('Sin asignaciones');
    }
}
