<?php

namespace Tests\Feature;

use App\Filament\Pages\EstacionesQr;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
