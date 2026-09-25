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
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class ColaboradorImportacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_uses_current_catalogs_as_dependent_dropdowns(): void
    {
        [, $empresa, $area, $sucursal] = $this->datosBase();
        PuntoVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja de prueba', 'activo' => true]);
        $respuesta = app(ColaboradorSpreadsheetService::class)->plantilla();

        ob_start();
        $respuesta->sendContent();
        $contenido = (string) ob_get_clean();

        $this->assertStringStartsWith('PK', $contenido);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename=plantilla-colaboradores-', (string) $respuesta->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('cache-control'));

        $archivo = tempnam(sys_get_temp_dir(), 'plantilla-');
        file_put_contents($archivo, $contenido);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archivo) === true);

        try {
            $libro = $this->xml((string) $zip->getFromName('xl/workbook.xml'));
            $libroXpath = new DOMXPath($libro);
            $libroXpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('hidden', $libroXpath->evaluate('string(//x:sheet[@name="Catálogos"]/@state)'));
            $this->assertMatchesRegularExpression("/^'Catálogos'!\\\$A\\\$2:\\\$A\\\$[2-9][0-9]*$/", $libroXpath->evaluate('string(//x:definedName[@name="empresas"])'));
            $this->assertMatchesRegularExpression("/^'Catálogos'!\\\$B\\\$2:\\\$B\\\$[2-9][0-9]*$/", $libroXpath->evaluate('string(//x:definedName[@name="areas"])'));
            $this->assertMatchesRegularExpression("/^'Catálogos'!\\\$C\\\$2:\\\$C\\\$[2-9][0-9]*$/", $libroXpath->evaluate('string(//x:definedName[@name="sucursales"])'));
            $this->assertMatchesRegularExpression("/^'Catálogos'!\\\$E\\\$2:\\\$E\\\$[2-9][0-9]*$/", $libroXpath->evaluate('string(//x:definedName[@name="punto_1"])'));
            $catalogos = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertStringContainsString($empresa->codigo, $catalogos);
            $this->assertStringContainsString($area->codigo, $catalogos);
            $this->assertStringContainsString($sucursal->nombre, $catalogos);
            $this->assertStringContainsString('Caja de prueba', $catalogos);

            $hoja = $this->xml((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
            $hojaXpath = new DOMXPath($hoja);
            $hojaXpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('5', $hojaXpath->evaluate('string(//x:dataValidations/@count)'));
            $this->assertLessThan(
                strpos((string) $zip->getFromName('xl/worksheets/sheet1.xml'), '<legacyDrawing'),
                strpos((string) $zip->getFromName('xl/worksheets/sheet1.xml'), '<dataValidations'),
            );
            $this->assertSame('=empresas', $hojaXpath->evaluate('string(//x:dataValidation[@sqref="E2:E5001"]/x:formula1)'));
            $this->assertSame('=sucursales', $hojaXpath->evaluate('string(//x:dataValidation[@sqref="G2:G5001"]/x:formula1)'));
            $this->assertSame('=IFERROR(INDIRECT("punto_"&MATCH($G2,sucursales,0)),"")', $hojaXpath->evaluate('string(//x:dataValidation[@sqref="H2:H5001"]/x:formula1)'));
            $this->assertSame('=estados', $hojaXpath->evaluate('string(//x:dataValidation[@sqref="K2:K5001"]/x:formula1)'));
        } finally {
            $zip->close();
            @unlink($archivo);
        }
    }

    public function test_authorized_user_can_start_the_export_from_the_filament_list(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate('super_admin', 'web'));
        $actor->givePermissionTo(
            Permission::findOrCreate('ViewAny:Colaborador', 'web'),
            Permission::findOrCreate('Exportar:Colaborador', 'web'),
        );
        $sucursal = Sucursal::create(['nombre' => 'Sucursal exportable', 'tipo' => 'tienda', 'activo' => true]);
        $usuarioColaborador = User::factory()->create(['email' => 'exportable@example.test']);
        Colaborador::create([
            'user_id' => $usuarioColaborador->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador exportable',
            'documento_identidad' => '99887766',
            'activo' => true,
        ]);

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

    private function xml(string $contenido): DOMDocument
    {
        $documento = new DOMDocument();
        $this->assertTrue($documento->loadXML($contenido));

        return $documento;
    }

    /** @return array{creados: int, actualizados: int, errores: list<string>} */
    private function importar(User $actor, string $archivo, ?string $contrasena = 'Temporal2026!'): array
    {
        return app(ColaboradorSpreadsheetService::class)->importar($archivo, $actor, $contrasena);
    }
}
