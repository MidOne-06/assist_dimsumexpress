<?php

namespace Tests\Feature;

use App\Actions\CrearColaborador;
use App\Actions\ActualizarColaborador;
use App\Models\Area;
use App\Models\Empresa;
use App\Filament\Resources\Colaboradors\Pages\ListColaboradors;
use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrearColaboradorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_collaborator_also_creates_an_operator_account(): void
    {
        $this->seed(RolesYPermisosSeeder::class);

        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal de prueba',
            'tipo' => 'planta',
            'activo' => true,
        ]);
        $empresa = Empresa::query()->where('codigo', 'DSE')->firstOrFail();
        $area = Area::query()->where('codigo', 'OPE')->firstOrFail();

        $colaborador = app(CrearColaborador::class)->handle([
            'nombre_completo' => 'María Operadora',
            'email' => 'maria.operadora@example.test',
            'password' => 'ClaveSegura2026!',
            'documento_identidad' => 'DNI-12345678',
            'empresa_id' => $empresa->id,
            'area_id' => $area->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => null,
            'cargo' => 'Cajera',
            'fecha_ingreso' => now()->toDateString(),
            'activo' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $colaborador->user_id,
            'email' => 'maria.operadora@example.test',
        ]);
        $this->assertTrue($colaborador->user->hasRole('operador'));
        $this->assertTrue($colaborador->user->can('Registrar:Marcacion'));
        $this->assertTrue($colaborador->user->can('View:MiHorario'));
        $this->assertSame('DSE-0001', $colaborador->codigo_empresa);
    }

    public function test_collaborator_modal_renders_with_the_responsive_native_layout(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $usuario = User::factory()->create();
        $usuario->assignRole('super_admin');
        $usuario->givePermissionTo(
            Permission::findOrCreate('ViewAny:Colaborador', 'web'),
            Permission::findOrCreate('Create:Colaborador', 'web'),
        );

        Livewire::actingAs($usuario)
            ->test(ListColaboradors::class)
            ->mountAction('create')
            ->assertHasNoErrors();
    }

    public function test_collaborator_modal_uses_the_creation_service_and_generates_the_code(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $administrador = User::factory()->create();
        $administrador->assignRole('super_admin');
        $administrador->givePermissionTo(
            Permission::findOrCreate('ViewAny:Colaborador', 'web'),
            Permission::findOrCreate('Create:Colaborador', 'web'),
        );
        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal modal',
            'tipo' => 'planta',
            'activo' => true,
        ]);

        Livewire::actingAs($administrador)
            ->test(ListColaboradors::class)
            ->mountAction('create')
            ->set('mountedActions.0.data.nombre_completo', 'Operador del modal')
            ->set('mountedActions.0.data.email', 'operador.modal@example.test')
            ->set('mountedActions.0.data.password', 'ClaveSegura2026!')
            ->set('mountedActions.0.data.password_confirmation', 'ClaveSegura2026!')
            ->set('mountedActions.0.data.documento_identidad', 'DNI-00000009')
            ->set('mountedActions.0.data.empresa_id', Empresa::query()->where('codigo', 'DSE')->value('id'))
            ->set('mountedActions.0.data.area_id', Area::query()->where('codigo', 'OPE')->value('id'))
            ->set('mountedActions.0.data.sucursal_id', $sucursal->id)
            ->set('mountedActions.0.data.fecha_ingreso', now()->toDateString())
            ->set('mountedActions.0.data.activo', true)
            ->callMountedAction()
            ->assertHasNoErrors();

        $this->assertDatabaseHas('colaboradores', [
            'documento_identidad' => 'DNI-00000009',
            'codigo_empresa' => 'DSE-0001',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'operador.modal@example.test']);
    }

    public function test_internal_code_is_sequential_per_company_and_changes_with_company(): void
    {
        $this->seed(RolesYPermisosSeeder::class);

        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal para códigos',
            'tipo' => 'planta',
            'activo' => true,
        ]);
        $dse = Empresa::query()->where('codigo', 'DSE')->firstOrFail();
        $jap = Empresa::query()->where('codigo', 'JAP')->firstOrFail();
        $area = Area::query()->where('codigo', 'OPE')->firstOrFail();

        $base = [
            'empresa_id' => $dse->id,
            'area_id' => $area->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => null,
            'cargo' => 'Operador',
            'fecha_ingreso' => now()->toDateString(),
            'activo' => true,
        ];

        $primero = app(CrearColaborador::class)->handle($base + [
            'nombre_completo' => 'Primer Operador',
            'email' => 'primer.operador@example.test',
            'password' => 'ClaveSegura2026!',
            'documento_identidad' => 'DNI-00000001',
        ]);
        $segundo = app(CrearColaborador::class)->handle($base + [
            'nombre_completo' => 'Segundo Operador',
            'email' => 'segundo.operador@example.test',
            'password' => 'ClaveSegura2026!',
            'documento_identidad' => 'DNI-00000002',
        ]);

        $this->assertSame('DSE-0001', $primero->codigo_empresa);
        $this->assertSame('DSE-0002', $segundo->codigo_empresa);

        $actualizado = app(ActualizarColaborador::class)->handle($primero, array_replace($base, [
            'empresa_id' => $jap->id,
            'nombre_completo' => 'Primer Operador',
            'email' => 'primer.operador@example.test',
            'documento_identidad' => 'DNI-00000001',
        ]));

        $this->assertSame('JAP-0001', $actualizado->fresh()->codigo_empresa);
    }

    public function test_supervisor_cannot_create_or_move_a_collaborator_outside_its_locations(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $supervisor = User::factory()->create();
        $localPermitido = Sucursal::create(['nombre' => 'Local permitido', 'tipo' => 'tienda', 'activo' => true]);
        $localRestringido = Sucursal::create(['nombre' => 'Local restringido', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor->sucursalesSupervisadas()->attach($localPermitido);
        $empresa = Empresa::query()->where('codigo', 'DSE')->firstOrFail();
        $area = Area::query()->where('codigo', 'OPE')->firstOrFail();
        $datos = [
            'nombre_completo' => 'Colaborador restringido',
            'email' => 'restringido@example.test',
            'password' => 'ClaveSegura2026!',
            'documento_identidad' => 'DNI-00000012',
            'empresa_id' => $empresa->id,
            'area_id' => $area->id,
            'sucursal_id' => $localRestringido->id,
            'fecha_ingreso' => now()->toDateString(),
            'activo' => true,
        ];

        try {
            app(CrearColaborador::class)->handle($datos, $supervisor);
            $this->fail('La creación fuera del alcance debió ser rechazada.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('colaboradores', ['documento_identidad' => 'DNI-00000012']);
        }

        $colaborador = app(CrearColaborador::class)->handle(array_replace($datos, [
            'email' => 'permitido@example.test',
            'documento_identidad' => 'DNI-00000013',
            'sucursal_id' => $localPermitido->id,
        ]));

        $this->expectException(AuthorizationException::class);

        app(ActualizarColaborador::class)->handle($colaborador, array_replace($datos, [
            'email' => 'permitido@example.test',
            'documento_identidad' => 'DNI-00000013',
        ]), $supervisor);
    }
}
