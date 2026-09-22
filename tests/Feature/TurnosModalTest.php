<?php

namespace Tests\Feature;

use App\Filament\Resources\Turnos\TurnoResource;
use Tests\TestCase;

class TurnosModalTest extends TestCase
{
    public function test_turnos_only_exposes_the_list_route_for_modal_management(): void
    {
        $this->assertSame(['index'], array_keys(TurnoResource::getPages()));
    }
}
