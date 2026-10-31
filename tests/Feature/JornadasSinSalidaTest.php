<?php

namespace Tests\Feature;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use App\Services\JornadasSinSalidaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JornadasSinSalidaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_lists_only_expired_jornadas_with_entry_and_no_exit(): void
    {
        [$olvidada, $turno] = $this->jornada('2026-10-30', 'Olvidada');
        $this->marca($olvidada, $turno, Marcacion::TIPO_ENTRADA, '2026-10-30 14:05:00');

        [$cerrada] = $this->jornada('2026-10-30', 'Cerrada', $turno);
        $this->marca($cerrada, $turno, Marcacion::TIPO_ENTRADA, '2026-10-30 14:02:00');
        $this->marca($cerrada, $turno, Marcacion::TIPO_SALIDA, '2026-10-30 22:05:00');

        [$sinEntrada] = $this->jornada('2026-10-30', 'Falta', $turno);

        $this->jornada('2026-10-31', 'EnCurso', $turno);

        $lista = app(JornadasSinSalidaService::class)->listar(
            Carbon::parse('2026-10-29'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-31 11:00:00'),
        );

        $this->assertSame(['Olvidada'], $lista->map(fn (array $f) => $f['asignacion']->colaborador->nombre_completo)->all());
    }

    public function test_jornada_still_inside_its_grace_window_is_not_listed(): void
    {
        [$colaborador, $turno] = $this->jornada('2026-10-30', 'Reciente');
        $this->marca($colaborador, $turno, Marcacion::TIPO_ENTRADA, '2026-10-30 14:05:00');

        $lista = app(JornadasSinSalidaService::class)->listar(
            Carbon::parse('2026-10-30'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-30 23:30:00'),
        );

        $this->assertTrue($lista->isEmpty());
    }

    /** @return array{0: Colaborador, 1: Turno} */
    private function jornada(string $fecha, string $nombre, ?Turno $turno = null): array
    {
        $sucursal = Sucursal::first() ?? Sucursal::create(['nombre' => 'Sucursal de prueba', 'tipo' => 'tienda', 'activo' => true]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => $nombre,
            'documento_identidad' => 'DOC-'.uniqid(),
            'activo' => true,
        ]);
        $turno ??= Turno::create([
            'nombre' => 'Cierre',
            'hora_inicio' => '14:00:00',
            'hora_fin' => '22:00:00',
            'cruza_medianoche' => false,
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'activo' => true,
        ]);
        AsignacionTurno::create(['colaborador_id' => $colaborador->id, 'turno_id' => $turno->id, 'fecha' => $fecha]);

        return [$colaborador, $turno];
    }

    private function marca(Colaborador $colaborador, Turno $turno, string $tipo, string $fechaHora): void
    {
        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'sucursal_id' => $colaborador->sucursal_id,
            'tipo' => $tipo,
            'fecha_hora' => $fechaHora,
        ]);
    }
}
