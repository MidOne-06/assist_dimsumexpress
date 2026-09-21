<?php

namespace App\Filament\Concerns;

use Filament\Support\Enums\Width;

/**
 * Los listados y calendarios aprovechan el ancho total del panel; los
 * formularios de mantenimiento no. Este ancho común evita controles
 * excesivamente largos sin repetir configuración en cada recurso.
 */
trait HasCompactFormWidth
{
    public function getMaxContentWidth(): Width | string | null
    {
        return Width::FourExtraLarge;
    }
}
