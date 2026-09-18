<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Marcacion;
use Illuminate\Auth\Access\HandlesAuthorization;

class MarcacionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Marcacion');
    }

    public function view(AuthUser $authUser, Marcacion $marcacion): bool
    {
        return $authUser->can('View:Marcacion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Marcacion');
    }

    public function update(AuthUser $authUser, Marcacion $marcacion): bool
    {
        return $authUser->can('Update:Marcacion');
    }

    public function delete(AuthUser $authUser, Marcacion $marcacion): bool
    {
        return $authUser->can('Delete:Marcacion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Marcacion');
    }

    public function restore(AuthUser $authUser, Marcacion $marcacion): bool
    {
        return $authUser->can('Restore:Marcacion');
    }

    public function forceDelete(AuthUser $authUser, Marcacion $marcacion): bool
    {
        return $authUser->can('ForceDelete:Marcacion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Marcacion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Marcacion');
    }

    public function replicate(AuthUser $authUser, Marcacion $marcacion): bool
    {
        return $authUser->can('Replicate:Marcacion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Marcacion');
    }

}