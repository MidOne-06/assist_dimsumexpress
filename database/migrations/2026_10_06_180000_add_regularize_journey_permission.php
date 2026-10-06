<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISO = 'Regularizar:Jornada';

    public function up(): void
    {
        $permiso = DB::table('permissions')
            ->where('name', self::PERMISO)
            ->where('guard_name', 'web')
            ->first();

        $permisoId = $permiso?->id ?? DB::table('permissions')->insertGetId([
            'name' => self::PERMISO,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roles = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['super_admin', 'administrador', 'supervisor'])
            ->pluck('id');

        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permisoId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $permiso = DB::table('permissions')
            ->where('name', self::PERMISO)
            ->where('guard_name', 'web')
            ->first();

        if (! $permiso) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $permiso->id)->delete();
        DB::table('permissions')->where('id', $permiso->id)->delete();
    }
};
