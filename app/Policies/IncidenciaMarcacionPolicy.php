<?php

namespace App\Policies;

use App\Models\IncidenciaMarcacion;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Illuminate\Auth\Access\HandlesAuthorization;

class IncidenciaMarcacionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:IncidenciaMarcacion');
    }

    public function view(User $user, IncidenciaMarcacion $incidencia): bool
    {
        return $user->can('View:IncidenciaMarcacion')
            && AlcanceSupervisor::puedeGestionarSucursal($user, $incidencia->colaborador->sucursal_id);
    }
}
