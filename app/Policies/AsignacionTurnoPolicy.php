<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AsignacionTurno;
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
        return $authUser->can('View:AsignacionTurno');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AsignacionTurno');
    }

    public function update(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        return $authUser->can('Update:AsignacionTurno');
    }

    public function delete(AuthUser $authUser, AsignacionTurno $asignacionTurno): bool
    {
        return $authUser->can('Delete:AsignacionTurno');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AsignacionTurno');
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

}