<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    public function viewAny(User $user): bool { return $user->can('ViewAny:Area'); }
    public function view(User $user, Area $area): bool { return $user->can('View:Area'); }
    public function create(User $user): bool { return $user->can('Create:Area'); }
    public function update(User $user, Area $area): bool { return $user->can('Update:Area'); }
    public function delete(User $user, Area $area): bool { return false; }
}
