<?php

namespace Tests\Feature;

use App\Filament\Pages\ControlJornadas;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ControlJornadasTest extends TestCase
{
    use RefreshDatabase;

    public function test_exceptional_marks_without_a_detected_shift_are_visible_without_becoming_a_scheduled_journey(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:ControlJornadas', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador excepcional',
            'documento_identidad' => 'CJ-' . uniqid(),
            'cargo' => 'Operario',
            'activo' => true,
        ]);
        $fecha = now()->startOfMonth()->addDay()->setTime(8, 15);
        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => $fecha,
        ]);

        Livewire::actingAs($supervisor)
            ->test(ControlJornadas::class)
            ->set('mes', $fecha->format('Y-m'))
            ->assertSee('Colaborador excepcional')
            ->assertSee('Sin turno · marcaciones registradas')
            ->assertSee('08:15');
    }
}
