<?php

namespace Tests\Feature;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Areas\Pages\ListAreas;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\PuntoVentas\Pages\ListPuntoVentas;
use App\Filament\Resources\PuntoVentas\PuntoVentaResource;
use App\Models\Area;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrganizacionEmpresarialTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_area_and_internal_code_are_preserved_in_each_attendance_mark(): void
    {
        $this->assertDatabaseHas('empresas', ['codigo' => 'JAP', 'nombre' => 'Corporacion JAP Inversions']);
        $this->assertDatabaseHas('empresas', ['codigo' => 'DSE', 'nombre' => 'DSE']);
        $this->assertDatabaseHas('empresas', ['codigo' => 'KOOCHOY', 'nombre' => 'Corporacion Koochoy']);
        $this->assertDatabaseHas('areas', ['codigo' => 'MKT']);
        $this->assertDatabaseHas('areas', ['codigo' => 'RRHH']);
        $this->assertDatabaseHas('areas', ['codigo' => 'TI']);

        $empresa = Empresa::query()->where('codigo', 'JAP')->firstOrFail();
        $area = Area::query()->where('codigo', 'TI')->firstOrFail();
        $sucursal = Sucursal::create(['nombre' => 'Planta central', 'tipo' => 'planta', 'activo' => true]);
        $punto = PuntoVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Oficina TI', 'tipo' => 'oficina', 'activo' => true]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'empresa_id' => $empresa->id,
            'area_id' => $area->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $punto->id,
            'nombre_completo' => 'Colaborador de prueba',
            'documento_identidad' => '99999999',
            'codigo_empresa' => 'TI-001',
            'activo' => true,
        ]);

        $marcacion = Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'empresa_id' => $empresa->id,
            'area_id' => $area->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => now(),
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $punto->id,
        ]);

        $this->assertSame('Oficina TI', $marcacion->puntoVenta->nombre);
        $this->assertSame('JAP', $marcacion->empresa->codigo);
        $this->assertSame('TI', $marcacion->area->codigo);
        $this->assertSame('TI-001', $marcacion->colaborador->codigo_empresa);
        $this->assertSame(['index'], array_keys(EmpresaResource::getPages()));
        $this->assertSame(['index'], array_keys(AreaResource::getPages()));
    }

    public function test_company_and_area_are_managed_from_native_filament_modals(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('ViewAny:Empresa', 'web'),
            Permission::findOrCreate('Create:Empresa', 'web'),
            Permission::findOrCreate('ViewAny:Area', 'web'),
            Permission::findOrCreate('Create:Area', 'web'),
            Permission::findOrCreate('ViewAny:PuntoVenta', 'web'),
            Permission::findOrCreate('Create:PuntoVenta', 'web'),
        );

        Livewire::actingAs($usuario)->test(ListEmpresas::class)->mountAction('create')->assertHasNoErrors();
        Livewire::actingAs($usuario)->test(ListAreas::class)->mountAction('create')->assertHasNoErrors();
        Livewire::actingAs($usuario)->test(ListPuntoVentas::class)->mountAction('create')->assertHasNoErrors();
        $this->assertSame(['index'], array_keys(PuntoVentaResource::getPages()));
    }
}
