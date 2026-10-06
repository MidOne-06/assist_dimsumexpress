<?php

namespace Tests\Feature;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Areas\Pages\ListAreas;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\PuntoVentas\Pages\ListPuntoVentas;
use App\Filament\Resources\PuntoVentas\PuntoVentaResource;
use App\Services\EmpresaService;
use App\Services\AreaService;
use App\Services\SucursalService;
use App\Services\PuntoVentaService;
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
use Illuminate\Validation\ValidationException;

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

    public function test_company_creation_normalizes_values_and_validates_its_ruc(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('ViewAny:Empresa', 'web'),
            Permission::findOrCreate('Create:Empresa', 'web'),
        );

        Livewire::actingAs($usuario)
            ->test(ListEmpresas::class)
            ->mountAction('create')
            ->set('mountedActions.0.data.nombre', '  Empresa   Nueva  ')
            ->set('mountedActions.0.data.codigo', ' nueva ')
            ->set('mountedActions.0.data.ruc', '20100070970')
            ->set('mountedActions.0.data.activo', true)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('empresas', [
            'nombre' => 'Empresa Nueva',
            'codigo' => 'NUEVA',
            'ruc' => '20100070970',
            'activo' => true,
        ]);
    }

    public function test_company_rejects_case_insensitive_duplicates_and_keeps_collaborator_history_when_deactivated(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('Create:Empresa', 'web'),
            Permission::findOrCreate('Update:Empresa', 'web'),
        );
        $empresa = Empresa::create(['nombre' => 'Empresa Principal', 'codigo' => 'PRI', 'activo' => true]);
        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal de historial',
            'tipo' => 'planta',
            'activo' => true,
        ]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador histórico',
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);

        try {
            app(EmpresaService::class)->crear($usuario, [
                'nombre' => 'empresa principal',
                'codigo' => 'OTRA',
                'activo' => true,
            ]);
            $this->fail('La razón social duplicada no fue rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nombre', $exception->errors());
        }

        app(EmpresaService::class)->actualizar($usuario, $empresa, [
            'nombre' => 'Empresa Principal',
            'codigo' => 'PRI',
            'activo' => false,
        ]);

        $this->assertFalse($empresa->fresh()->activo);
        $this->assertSame($empresa->id, $colaborador->fresh()->empresa_id);
    }

    public function test_area_normalizes_values_rejects_duplicates_and_keeps_collaborator_history_when_deactivated(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('Create:Area', 'web'),
            Permission::findOrCreate('Update:Area', 'web'),
        );

        $area = app(AreaService::class)->crear($usuario, [
            'nombre' => '  Control   de Calidad ',
            'codigo' => ' cc ',
            'activo' => true,
        ]);
        $this->assertSame('Control de Calidad', $area->nombre);
        $this->assertSame('CC', $area->codigo);

        try {
            app(AreaService::class)->crear($usuario, [
                'nombre' => 'control de calidad',
                'codigo' => 'CC-2',
                'activo' => true,
            ]);
            $this->fail('El nombre de área duplicado no fue rechazado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nombre', $exception->errors());
        }

        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal del área',
            'tipo' => 'planta',
            'activo' => true,
        ]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'area_id' => $area->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador de área',
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);

        app(AreaService::class)->actualizar($usuario, $area, [
            'nombre' => 'Control de Calidad',
            'codigo' => 'CC',
            'activo' => false,
        ]);

        $this->assertFalse($area->fresh()->activo);
        $this->assertSame($area->id, $colaborador->fresh()->area_id);
    }

    public function test_sucursal_is_managed_in_a_modal_with_safe_type_and_history_rules(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('ViewAny:Sucursal', 'web'),
            Permission::findOrCreate('Create:Sucursal', 'web'),
            Permission::findOrCreate('Update:Sucursal', 'web'),
        );

        Livewire::actingAs($usuario)->test(\App\Filament\Resources\Sucursals\Pages\ListSucursals::class)
            ->mountAction('create')
            ->assertHasNoErrors();

        $sucursal = app(SucursalService::class)->crear($usuario, [
            'nombre' => '  Tienda   Central ',
            'tipo' => 'tienda',
            'direccion' => '  Av.   Principal  123 ',
            'activo' => true,
        ]);
        $this->assertSame('Tienda Central', $sucursal->nombre);
        $this->assertSame('Av. Principal 123', $sucursal->direccion);
        $this->assertSame(['index'], array_keys(\App\Filament\Resources\Sucursals\SucursalResource::getPages()));

        PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Central',
            'tipo' => 'caja',
            'activo' => true,
        ]);

        try {
            app(SucursalService::class)->actualizar($usuario, $sucursal, [
                'nombre' => 'Tienda Central',
                'tipo' => 'planta',
                'direccion' => 'Av. Principal 123',
                'activo' => true,
            ]);
            $this->fail('Una tienda con puntos de marcado no debe convertirse en planta.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tipo', $exception->errors());
        }

        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador de sucursal',
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);
        app(SucursalService::class)->actualizar($usuario, $sucursal, [
            'nombre' => 'Tienda Central',
            'tipo' => 'tienda',
            'direccion' => 'Av. Principal 123',
            'activo' => false,
        ]);

        $this->assertFalse($sucursal->fresh()->activo);
        $this->assertSame($sucursal->id, $colaborador->fresh()->sucursal_id);
    }

    public function test_sucursal_rejects_duplicates_that_only_differ_by_formatting(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(Permission::findOrCreate('Create:Sucursal', 'web'));

        Sucursal::create([
            'nombre' => 'DIM SUM PLAZA NORTE',
            'tipo' => 'tienda',
            'activo' => true,
        ]);

        try {
            app(SucursalService::class)->crear($usuario, [
                'nombre' => '  dim-sum  plaza norte  ',
                'tipo' => 'tienda',
                'activo' => true,
            ]);
            $this->fail('No debe crearse una sucursal con el mismo nombre normalizado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nombre', $exception->errors());
        }
    }

    public function test_punto_de_marcado_validates_its_station_and_preserves_operational_history(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::findOrCreate('ViewAny:PuntoVenta', 'web'),
            Permission::findOrCreate('Create:PuntoVenta', 'web'),
            Permission::findOrCreate('Update:PuntoVenta', 'web'),
        );
        $origen = Sucursal::create(['nombre' => 'Local origen', 'tipo' => 'tienda', 'activo' => true]);
        $destino = Sucursal::create(['nombre' => 'Local destino', 'tipo' => 'tienda', 'activo' => true]);

        Livewire::actingAs($usuario)->test(ListPuntoVentas::class)
            ->mountAction('create')
            ->assertHasNoErrors();

        $punto = app(PuntoVentaService::class)->crear($usuario, [
            'sucursal_id' => $origen->id,
            'nombre' => '  Caja   principal ',
            'tipo' => 'caja',
            'activo' => true,
        ]);
        $this->assertSame('Caja principal', $punto->nombre);
        $this->assertSame(['index'], array_keys(PuntoVentaResource::getPages()));

        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $origen->id,
            'punto_venta_id' => $punto->id,
            'nombre_completo' => 'Colaborador de estación',
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);

        try {
            app(PuntoVentaService::class)->actualizar($usuario, $punto, [
                'sucursal_id' => $destino->id,
                'nombre' => 'Caja principal',
                'tipo' => 'caja',
                'activo' => true,
            ]);
            $this->fail('Una estación con historial no debe trasladarse entre sucursales.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sucursal_id', $exception->errors());
        }

        app(PuntoVentaService::class)->actualizar($usuario, $punto, [
            'sucursal_id' => $origen->id,
            'nombre' => 'Caja principal',
            'tipo' => 'caja',
            'activo' => false,
        ]);
        $this->assertFalse($punto->fresh()->activo);
        $this->assertSame($punto->id, $colaborador->fresh()->punto_venta_id);
    }
}
