<?php

namespace Tests\Feature;

use App\Filament\Pages\EstacionesQr;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EstacionesQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_searching_a_qr_station_does_not_require_an_undefined_record_field(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('VerEnlace:PuntoVenta', 'web'));

        $sucursal = Sucursal::create(['nombre' => 'Lima Centro', 'tipo' => 'tienda', 'activo' => true]);
        PuntoVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja 1', 'activo' => true]);

        $this->actingAs($user);

        $page = app(EstacionesQr::class);
        $method = new ReflectionMethod($page, 'registrosPaginados');
        $registros = $method->invoke($page, null, 'lima', 1, 10, null, null);

        $this->assertSame(1, $registros->total());
        $this->assertSame('Caja 1', $registros->first()['nombre']);
    }

    public function test_qr_station_access_requires_both_the_page_and_station_link_permissions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(EstacionesQr::canAccess());

        $user->givePermissionTo(Permission::findOrCreate('View:EstacionesQr', 'web'));
        $this->assertFalse(EstacionesQr::canAccess());

        $user->givePermissionTo(Permission::findOrCreate('VerEnlace:PuntoVenta', 'web'));
        $this->assertTrue(EstacionesQr::canAccess());
    }

    public function test_stations_only_include_active_points_of_sale_in_the_allowed_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('supervisor', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('VerEnlace:PuntoVenta', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('View:EstacionesQr', 'web'));
        $propia = Sucursal::create(['nombre' => 'Local propio', 'tipo' => 'tienda', 'activo' => true]);
        $ajena = Sucursal::create(['nombre' => 'Local ajeno', 'tipo' => 'tienda', 'activo' => true]);
        $user->sucursalesSupervisadas()->attach($propia);
        PuntoVenta::create(['sucursal_id' => $propia->id, 'nombre' => 'Caja propia', 'tipo' => 'caja', 'activo' => true]);
        PuntoVenta::create(['sucursal_id' => $propia->id, 'nombre' => 'Caja inactiva', 'tipo' => 'caja', 'activo' => false]);
        PuntoVenta::create(['sucursal_id' => $ajena->id, 'nombre' => 'Caja ajena', 'tipo' => 'caja', 'activo' => true]);

        $this->actingAs($user);
        $page = app(EstacionesQr::class);
        $method = new ReflectionMethod($page, 'registrosPaginados');
        $registros = $method->invoke($page, null, null, 1, 10, null, null);

        $this->assertSame(1, $registros->total());
        $this->assertSame('Caja propia', $registros->first()['nombre']);
        $this->assertSame('Caja', $registros->first()['tipo']);
    }

    public function test_regeneration_target_is_restricted_to_the_users_station_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('supervisor', 'web'));
        $propia = Sucursal::create(['nombre' => 'Local permitido', 'tipo' => 'tienda', 'activo' => true]);
        $ajena = Sucursal::create(['nombre' => 'Local no permitido', 'tipo' => 'tienda', 'activo' => true]);
        $user->sucursalesSupervisadas()->attach($propia);
        $puntoPropio = PuntoVenta::create(['sucursal_id' => $propia->id, 'nombre' => 'Caja permitida', 'activo' => true]);
        $puntoAjeno = PuntoVenta::create(['sucursal_id' => $ajena->id, 'nombre' => 'Caja ajena', 'activo' => true]);

        $this->actingAs($user);
        $page = app(EstacionesQr::class);
        $method = new ReflectionMethod($page, 'puntoVentaPermitido');

        $this->assertSame($puntoPropio->id, $method->invoke($page, ['__key' => "punto-venta-{$puntoPropio->id}"])->id);

        $this->expectException(ModelNotFoundException::class);
        $method->invoke($page, ['__key' => "punto-venta-{$puntoAjeno->id}"]);
    }
}
