<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;

class EmpresaPolicy
{
    public function viewAny(User $user): bool { return $user->can('ViewAny:Empresa'); }
    public function view(User $user, Empresa $empresa): bool { return $user->can('View:Empresa'); }
    public function create(User $user): bool { return $user->can('Create:Empresa'); }
    public function update(User $user, Empresa $empresa): bool { return $user->can('Update:Empresa'); }
    public function delete(User $user, Empresa $empresa): bool { return false; }
}
