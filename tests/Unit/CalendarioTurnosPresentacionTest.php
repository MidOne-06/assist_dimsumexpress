<?php

namespace Tests\Unit;

use App\Support\CalendarioTurnosPresentacion;
use PHPUnit\Framework\TestCase;

class CalendarioTurnosPresentacionTest extends TestCase
{
    public function test_abrevia_el_nombre_para_una_celda_compacta(): void
    {
        $this->assertSame('Ana T.', CalendarioTurnosPresentacion::abreviarNombre('Ana Torres Quispe'));
        $this->assertSame('Ana', CalendarioTurnosPresentacion::abreviarNombre('Ana'));
    }

    public function test_expone_colores_e_iconos_estables_por_estado_y_turno(): void
    {
        $this->assertSame('heroicon-s-check-circle', CalendarioTurnosPresentacion::iconoEstado('a_tiempo'));
        $this->assertSame('#f59e0b', CalendarioTurnosPresentacion::colorEstado('tardanza'));
        $this->assertNull(CalendarioTurnosPresentacion::iconoEstado('pendiente'));
        $this->assertSame(
            CalendarioTurnosPresentacion::colorParaTurno(1),
            CalendarioTurnosPresentacion::colorParaTurno(9),
        );
    }
}
