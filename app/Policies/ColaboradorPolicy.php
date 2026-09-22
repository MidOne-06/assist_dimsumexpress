<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Colaborador;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Illuminate\Auth\Access\HandlesAuthorization;

class ColaboradorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Colaborador');
    }

    public function view(AuthUser $authUser, Colaborador $colaborador): bool
    {
        return $authUser->can('View:Colaborador') && $this->puedeGestionar($authUser, $colaborador);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Colaborador');
    }

    public function update(AuthUser $authUser, Colaborador $colaborador): bool
    {
        return $authUser->can('Update:Colaborador') && $this->puedeGestionar($authUser, $colaborador);
    }

    public function delete(AuthUser $authUser, Colaborador $colaborador): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Colaborador $colaborador): bool
    {
        return $authUser->can('Restore:Colaborador');
    }

    public function forceDelete(AuthUser $authUser, Colaborador $colaborador): bool
    {
        return $authUser->can('ForceDelete:Colaborador');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Colaborador');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Colaborador');
    }

    public function replicate(AuthUser $authUser, Colaborador $colaborador): bool
    {
        return $authUser->can('Replicate:Colaborador');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Colaborador');
    }

    private function puedeGestionar(AuthUser $authUser, Colaborador $colaborador): bool
    {
        return $authUser instanceof User
            && AlcanceSupervisor::puedeGestionarSucursal($authUser, $colaborador->sucursal_id);
    }

}
