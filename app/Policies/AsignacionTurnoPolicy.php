<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AsignacionTurno;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Illuminate\Auth\Access\HandlesAuthorization;

class AsignacionTurnoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AsignacionTurno');
    }

    public function view(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        return $authUser->can('View:AsignacionTurno')
            && $this->puedeGestionarAsignacion($authUser, $asignacionTurno);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AsignacionTurno');
    }

    public function update(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        return $authUser->can('Update:AsignacionTurno')
            && $asignacionTurno->fecha->isAfter(today())
            && $this->puedeGestionarAsignacion($authUser, $asignacionTurno);
    }

    public function delete(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        return $authUser->can('Delete:AsignacionTurno')
            && $asignacionTurno->fecha->isAfter(today())
            && $this->puedeGestionarAsignacion($authUser, $asignacionTurno);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        return $authUser->can('Restore:AsignacionTurno');
    }

    public function forceDelete(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        return $authUser->can('ForceDelete:AsignacionTurno');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AsignacionTurno');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AsignacionTurno');
    }

    public function replicate(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        return $authUser->can('Replicate:AsignacionTurno');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AsignacionTurno');
    }

    private function puedeGestionarAsignacion(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        if (! $authUser instanceof User) {
            return false;
        }

        return AlcanceSupervisor::puedeGestionarSucursal(
            $authUser,
            $asignacionTurno->colaborador->sucursal_id,
        );
    }

}
