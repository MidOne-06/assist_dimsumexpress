<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::findOrCreate('operador', 'web');

        User::query()
            ->whereHas('colaborador')
            ->doesntHave('roles')
            ->eachById(fn (User $user) => $user->assignRole($role), column: 'id');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No se retiran roles al revertir: podrían haberse asignado después
        // permisos o roles adicionales de forma intencional.
    }
};
