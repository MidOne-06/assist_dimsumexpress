<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\UserResource;
use App\Models\Colaborador;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperatorAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_account_cannot_be_given_the_operator_role(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $operatorId = Role::query()->where('name', 'operador')->valueOrFail('id');

        $this->expectException(ValidationException::class);

        UserResource::validarCuentaOperador(['roles' => [$operatorId]]);
    }

    public function test_linked_collaborator_account_can_keep_the_operator_role(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $operatorId = Role::query()->where('name', 'operador')->valueOrFail('id');
        $user = User::factory()->create();
        $sucursal = Sucursal::create(['nombre' => 'Sucursal ' . uniqid(), 'tipo' => 'planta', 'activo' => true]);
        Colaborador::create([
            'user_id' => $user->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Operador ' . uniqid(),
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);

        UserResource::validarCuentaOperador(['roles' => [$operatorId]], $user);

        $this->assertTrue(true);
    }
}
