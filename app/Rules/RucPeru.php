<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RucPeru implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $ruc = preg_replace('/\D/', '', (string) $value);

        if (strlen($ruc) !== 11) {
            $fail('El RUC debe tener 11 dígitos.');

            return;
        }

        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        foreach ($pesos as $indice => $peso) {
            $suma += ((int) $ruc[$indice]) * $peso;
        }

        $digito = 11 - ($suma % 11);
        $digito = $digito >= 10 ? 0 : $digito;

        if ($digito !== (int) $ruc[10]) {
            $fail('El RUC no es válido.');
        }
    }
}
