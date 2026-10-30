<?php

namespace Tests\Feature;

use App\Filament\Pages\MatrizMarcaciones;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MatrizMarcacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_monthly_marks_only_for_collaborators_in_the_supervisor_scope(): void
    {
        $propia = $this->sucursal('Local propio');
        $ajena = $this->sucursal('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:MatrizMarcaciones', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($propia);

        $colaboradorPropio = $this->colaborador($propia, 'Ana de la Torre');
        $colaboradorAjeno = $this->colaborador($ajena, 'Bruno Fuera de Alcance');
        $fecha = now()->startOfMonth()->addDays(2)->toDateString();

        Marcacion::create([
            'colaborador_id' => $colaboradorPropio->id,
            'sucursal_id' => $propia->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => "{$fecha} 08:01:00",
        ]);
        Marcacion::create([
            'colaborador_id' => $colaboradorPropio->id,
            'sucursal_id' => $propia->id,
            'tipo' => Marcacion::TIPO_SALIDA,
            'fecha_hora' => "{$fecha} 17:35:00",
        ]);
        Marcacion::create([
            'colaborador_id' => $colaboradorAjeno->id,
            'sucursal_id' => $ajena->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => "{$fecha} 07:30:00",
        ]);
        $fechaAnterior = now()->subMonthNoOverflow()->startOfMonth()->addDays(2)->toDateString();
        Marcacion::create([
            'colaborador_id' => $colaboradorPropio->id,
            'sucursal_id' => $propia->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => "{$fechaAnterior} 07:45:00",
        ]);

        Livewire::actingAs($supervisor)
            ->test(MatrizMarcaciones::class)
            ->assertSee('Ana de la Torre')
            ->assertSee('Ingreso')
            ->assertSee('Salida')
            ->assertSee('08:01')
            ->assertSee('17:35')
            ->assertDontSee('Bruno Fuera de Alcance');

        Livewire::withQueryParams(['mes' => now()->subMonthNoOverflow()->format('Y-m')])
            ->actingAs($supervisor)
            ->test(MatrizMarcaciones::class)
            ->assertSee('07:45')
            ->assertDontSee('08:01');
    }

    public function test_it_filters_collaborators_without_an_entry_in_the_selected_month(): void
    {
        $sucursal = $this->sucursal('Local supervisado');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:MatrizMarcaciones', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        $conIngreso = $this->colaborador($sucursal, 'Carlos con registro');
        $sinIngreso = $this->colaborador($sucursal, 'María sin entrada');
        $fecha = now()->startOfMonth()->addDay()->toDateString();

        Marcacion::create([
            'colaborador_id' => $conIngreso->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => "{$fecha} 08:00:00",
        ]);
        Marcacion::create([
            'colaborador_id' => $sinIngreso->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_SALIDA_REFRIGERIO,
            'fecha_hora' => "{$fecha} 12:00:00",
        ]);

        Livewire::actingAs($supervisor)
            ->test(MatrizMarcaciones::class)
            ->set('tableDeferredFilters', ['estado_marcacion' => ['value' => 'sin_entrada']])
            ->call('applyTableFilters')
            ->assertSet('tableFilters.estado_marcacion.value', 'sin_entrada')
            ->assertSee('María sin entrada')
            ->assertSee('12:00')
            ->assertDontSee('08:00');
    }

    public function test_it_allows_exporting_marks_to_excel_with_date_range_and_store_filter(): void
    {
        $sucursal = $this->sucursal('Local exportable');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('View:MatrizMarcaciones', 'web'),
            Permission::findOrCreate('Exportar:Marcacion', 'web')
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        $colaborador = $this->colaborador($sucursal, 'Roberto Exportación');
        $fecha = now()->startOfMonth()->addDays(3)->toDateString();

        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => "{$fecha} 08:30:00",
        ]);

        Livewire::actingAs($supervisor)
            ->test(MatrizMarcaciones::class)
            ->assertActionVisible('exportar')
            ->callAction('exportar', [
                'fecha_inicio' => now()->startOfMonth()->toDateString(),
                'fecha_fin' => now()->endOfMonth()->toDateString(),
                'sucursal_ids' => [$sucursal->id],
            ])
            ->assertSuccessful();

        Livewire::actingAs($supervisor)
            ->test(MatrizMarcaciones::class)
            ->callAction('exportar', [
                'fecha_inicio' => now()->startOfMonth()->toDateString(),
                'fecha_fin' => now()->endOfMonth()->toDateString(),
                'sucursal_ids' => [],
            ])
            ->assertSuccessful();

        $sucursalVacia = $this->sucursal('Local sin marcas');
        $supervisor->sucursalesSupervisadas()->attach($sucursalVacia);

        Livewire::actingAs($supervisor)
            ->test(MatrizMarcaciones::class)
            ->callAction('exportar', [
                'fecha_inicio' => now()->startOfMonth()->toDateString(),
                'fecha_fin' => now()->endOfMonth()->toDateString(),
                'sucursal_ids' => [$sucursal->id, $sucursalVacia->id],
            ])
            ->assertSuccessful();
    }

    public function test_it_hides_export_action_when_user_lacks_export_permission(): void
    {
        $sucursal = $this->sucursal('Local sin export');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:MatrizMarcaciones', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        Livewire::actingAs($supervisor)
            ->test(MatrizMarcaciones::class)
            ->assertActionHidden('exportar');
    }

    private function sucursal(string $nombre): Sucursal
    {
        return Sucursal::create(['nombre' => $nombre, 'tipo' => 'tienda', 'activo' => true]);
    }

    private function colaborador(Sucursal $sucursal, string $nombre): Colaborador
    {
        return Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => $nombre,
            'documento_identidad' => 'DOC-'.uniqid(),
            'activo' => true,
        ]);
    }
}
