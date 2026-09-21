<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AlcanceSupervisor
{
    /**
     * Administradores trabajan sobre toda la organización. Un supervisor
     * queda limitado estrictamente a las sucursales enlazadas a su usuario.
     * Sin locales asignados, el alcance es vacío por defecto.
     *
     * @return array<int, int>
     */
    public static function sucursalIds(User $user): array
    {
        if ($user->hasAnyRole(['super_admin', 'administrador'])) {
            return Sucursal::query()
                ->where('activo', true)
                ->pluck('id')
                ->all();
        }

        return $user->sucursalesSupervisadas()
            ->where('activo', true)
            ->pluck('sucursales.id')
            ->all();
    }

    public static function sucursalesQuery(User $user): Builder
    {
        return Sucursal::query()
            ->where('activo', true)
            ->whereIn('id', self::sucursalIds($user))
            ->orderBy('nombre');
    }

    public static function puedeGestionarSucursal(User $user, int $sucursalId): bool
    {
        return in_array($sucursalId, self::sucursalIds($user), true);
    }
}
