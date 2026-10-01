<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PuntoVenta;
use Illuminate\Auth\Access\HandlesAuthorization;

class PuntoVentaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PuntoVenta');
    }

    public function view(AuthUser $authUser, PuntoVenta $puntoVenta): bool
    {
        return $authUser->can('View:PuntoVenta');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PuntoVenta');
    }

    public function update(AuthUser $authUser, PuntoVenta $puntoVenta): bool
    {
        return $authUser->can('Update:PuntoVenta');
    }

    public function delete(AuthUser $authUser, PuntoVenta $puntoVenta): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, PuntoVenta $puntoVenta): bool
    {
        return $authUser->can('Restore:PuntoVenta');
    }

    public function forceDelete(AuthUser $authUser, PuntoVenta $puntoVenta): bool
    {
        return $authUser->can('ForceDelete:PuntoVenta');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PuntoVenta');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PuntoVenta');
    }

    public function replicate(AuthUser $authUser, PuntoVenta $puntoVenta): bool
    {
        return $authUser->can('Replicate:PuntoVenta');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PuntoVenta');
    }

}
