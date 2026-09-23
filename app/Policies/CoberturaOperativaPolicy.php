<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CoberturaOperativa;
use App\Models\User;
use App\Support\AlcanceSupervisor;

class CoberturaOperativaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:CoberturaOperativa');
    }

    public function view(User $user, CoberturaOperativa $cobertura): bool
    {
        return $user->can('View:CoberturaOperativa')
            && AlcanceSupervisor::puedeGestionarSucursal($user, $cobertura->sucursal_id);
    }

    public function update(User $user, CoberturaOperativa $cobertura): bool
    {
        return $user->can('Revisar:CoberturaOperativa')
            && AlcanceSupervisor::puedeGestionarSucursal($user, $cobertura->sucursal_id);
    }
}
