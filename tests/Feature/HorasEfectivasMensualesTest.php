<?php

namespace Tests\Feature;

use App\Filament\Pages\HorasEfectivasMensuales;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HorasEfectivasMensualesTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_summary_accumulates_closed_journeys_against_each_shift_target(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Local propio', 'tipo' => 'tienda', 'activo' => true]);
        $ajena = Sucursal::create(['nombre' => 'Local ajeno', 'tipo' => 'tienda', 'activo' => true]);
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(Permission::findOrCreate('View:HorasEfectivasMensuales', 'web'));
        $supervisor->sucursalesSupervisadas()->attach($sucursal);
        $colaborador = $this->colaborador($sucursal, 'Operario propio');
        $this->colaborador($ajena, 'Operario ajeno');
        $turno = Turno::create([
            'nombre' => 'Turno dinámico',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'incluye_refrigerio' => false,
            'refrigerio_minutos' => 0,
            'horas_efectivas_objetivo_minutos' => 480,
            'activo' => true,
        ]);
        $primerDia = now()->startOfMonth()->addDay();
        $segundoDia = $primerDia->copy()->addDay();

        foreach ([
            [$primerDia, '08:00:00', '17:00:00'],
            [$segundoDia, '08:00:00', '16:00:00'],
        ] as [$fecha, $entrada, $salida]) {
            $asignacion = AsignacionTurno::create([
                'colaborador_id' => $colaborador->id,
                'turno_id' => $turno->id,
                'fecha' => $fecha->toDateString(),
            ]);
            Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $asignacion->turno_id, 'sucursal_id' => $sucursal->id, 'tipo' => Marcacion::TIPO_ENTRADA, 'fecha_hora' => $fecha->toDateString() . " {$entrada}"]);
            Marcacion::create(['colaborador_id' => $colaborador->id, 'turno_id' => $asignacion->turno_id, 'sucursal_id' => $sucursal->id, 'tipo' => Marcacion::TIPO_SALIDA, 'fecha_hora' => $fecha->toDateString() . " {$salida}"]);
        }

        Livewire::actingAs($supervisor)
            ->test(HorasEfectivasMensuales::class)
            ->assertSee('Operario propio')
            ->assertDontSee('Operario ajeno')
            ->assertSee('17 h')
            ->assertSee('16 h')
            ->assertSee('1 h');
    }

    private function colaborador(Sucursal $sucursal, string $nombre): Colaborador
    {
        return Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => $nombre,
            'documento_identidad' => 'DOC-' . uniqid(),
            'activo' => true,
        ]);
    }
}
