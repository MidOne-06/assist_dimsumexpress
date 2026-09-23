<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ColaboradorEstadoAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivation_revokes_access_without_deleting_labor_history(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $usuario = User::factory()->create([
            'email' => 'baja@dimsum.test',
            'password' => Hash::make('ClavePrueba2026!'),
            'remember_token' => 'token-anterior',
        ]);
        $colaborador = Colaborador::create([
            'user_id' => $usuario->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador de baja',
            'documento_identidad' => 'BAJA-001',
            'activo' => true,
        ]);
        DB::table('sessions')->insert([
            'id' => 'sesion-colaborador-baja',
            'user_id' => $usuario->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Prueba',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $colaborador->desactivarAcceso();

        $this->assertFalse($colaborador->fresh()->activo);
        $this->assertFalse($usuario->fresh()->estaActivoParaAcceso());
        $this->assertNotSame('token-anterior', $usuario->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $usuario->id]);
        $this->assertDatabaseHas('colaboradores', ['id' => $colaborador->id]);

        $this->withSession(['_token' => 'csrf-de-baja'])->post(route('login'), [
            '_token' => 'csrf-de-baja',
            'email' => 'baja@dimsum.test',
            'password' => 'ClavePrueba2026!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_reactivation_restores_login_without_changing_the_password(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $usuario = User::factory()->create([
            'email' => 'reactivar@dimsum.test',
            'password' => Hash::make('ClavePrueba2026!'),
        ]);
        $colaborador = Colaborador::create([
            'user_id' => $usuario->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Colaborador reactivado',
            'documento_identidad' => 'REACT-001',
            'activo' => false,
        ]);

        $colaborador->reactivarAcceso();

        $this->withSession(['_token' => 'csrf-reactivacion'])->post(route('login'), [
            '_token' => 'csrf-reactivacion',
            'email' => 'reactivar@dimsum.test',
            'password' => 'ClavePrueba2026!',
        ])->assertRedirect(route('marcacion.show'));

        $this->assertAuthenticatedAs($usuario);
    }
}
