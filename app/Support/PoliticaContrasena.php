<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/** Reglas únicas para altas, cambios y activaciones de cuentas. */
final class PoliticaContrasena
{
    public const MINIMO_CARACTERES = 8;

    public static function regla(): Password
    {
        return Password::min(self::MINIMO_CARACTERES)
            ->mixedCase()
            ->numbers();
    }
}
