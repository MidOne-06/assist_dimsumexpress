<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ColaboradorSpreadsheetService;
use App\Filament\Resources\Colaboradors\Pages\ListColaboradors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ColaboradorImportacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_is_a_valid_xlsx_download_without_sensitive_columns(): void
    {
        $respuesta = app(ColaboradorSpreadsheetService::class)->plantilla();

        ob_start();
        $respuesta->sendContent();
        $contenido = (string) ob_get_clean();

        $this->assertStringStartsWith('PK', $contenido);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $respuesta->headers->get('content-type'));
    }

    public function test_authorized_user_can_start_the_export_from_the_filament_list(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate('super_admin', 'web'));
        $actor->givePermissionTo(
            Permission::findOrCreate('ViewAny:Colaborador', 'web'),
            Permission::findOrCreate('Exportar:Colaborador', 'web'),
        );

        Livewire::actingAs($actor)
            ->test(ListColaboradors::class)
            ->callAction('exportarColaboradores')
            ->assertHasNoActionErrors();
    }

    public function test_import_creates_a_new_collaborator_from_a_csv_file(): void
    {
        [$actor, $empresa, $area, $sucursal] = $this->datosBase();

        $resultado = $this->importar($actor, $this->csv([
            ['Ana Torres', '12345678', 'ana@example.test', $empresa->codigo, $area->codigo, $sucursal->nombre, '', 'Cajera', '2026-09-25', 'si'],
        ]));

        $this->assertSame(['creados' => 1, 'actualizados' => 0, 'errores' => []], $resultado);
        $this->assertDatabaseHas('colaboradores', ['documento_identidad' => '12345678', 'activo' => true]);
        $this->assertDatabaseHas('users', ['email' => 'ana@example.test']);
    }

    public function test_import_updates_an_existing_collaborator_and_applies_deactivation(): void
    {
        [$actor, $empresa, $area, $sucursal] = $this->datosBase();
        $primero = $this->importar($actor, $this->csv([
            ['Ana Torres', '12345678', 'ana@example.test', $empresa->codigo, $area->codigo, $sucursal->nombre, '', 'Cajera', '2026-09-25', 'si'],
        ]));
        $this->assertSame(1, $primero['creados']);

        $resultado = $this->importar($actor, $this->csv([
            ['Ana Torres Actualizada', '12345678', 'ana.actualizada@example.test', $empresa->codigo, $area->codigo, $sucursal->nombre, '', 'Encargada', '2026-09-25', 'no'],
        ]), null);

        $this->assertSame(['creados' => 0, 'actualizados' => 1, 'errores' => []], $resultado);
        $this->assertDatabaseHas('colaboradores', ['documento_identidad' => '12345678', 'nombre_completo' => 'Ana Torres Actualizada', 'cargo' => 'Encargada', 'activo' => false]);
        $this->assertDatabaseHas('users', ['email' => 'ana.actualizada@example.test']);
    }

    public function test_import_rejects_invalid_locations_without_persisting_any_row(): void
    {
        [$actor, $empresa, $area, $sucursal] = $this->datosBase();
        PuntoVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja 1', 'activo' => true]);

        $resultado = $this->importar($actor, $this->csv([
            ['Ana Torres', '12345678', 'ana@example.test', $empresa->codigo, $area->codigo, $sucursal->nombre, 'Caja inexistente', 'Cajera', '2026-09-25', 'si'],
        ]));

        $this->assertSame(0, $resultado['creados']);
        $this->assertSame(0, $resultado['actualizados']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertDatabaseCount('colaboradores', 0);
    }

    /** @return array{0: User, 1: Empresa, 2: Area, 3: Sucursal} */
    private function datosBase(): array
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate('super_admin', 'web'));

        return [
            $actor,
            Empresa::create(['nombre' => 'Empresa de prueba', 'codigo' => 'PRU', 'activo' => true]),
            Area::create(['nombre' => 'Operaciones de prueba', 'codigo' => 'OPRPRU', 'activo' => true]),
            Sucursal::create(['nombre' => 'Sucursal de prueba', 'tipo' => 'tienda', 'activo' => true]),
        ];
    }

    /** @param list<list<string>> $filas */
    private function csv(array $filas): string
    {
        $archivo = 'importaciones-colaboradores/prueba.csv';
        $contenido = "nombre_completo,documento_identidad,correo,empresa_codigo,area_codigo,sucursal,punto_venta,cargo,fecha_ingreso,activo\n";

        foreach ($filas as $fila) {
            $contenido .= implode(',', $fila)."\n";
        }

        Storage::disk('local')->put($archivo, $contenido);

        return Storage::disk('local')->path($archivo);
    }

    /** @return array{creados: int, actualizados: int, errores: list<string>} */
    private function importar(User $actor, string $archivo, ?string $contrasena = 'Temporal2026!'): array
    {
        return app(ColaboradorSpreadsheetService::class)->importar($archivo, $actor, $contrasena);
    }
}
