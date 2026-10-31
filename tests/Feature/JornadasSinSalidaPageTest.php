<?php

namespace Tests\Feature;

use App\Filament\Pages\JornadasSinSalida;
use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JornadasSinSalidaPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_page_can_be_accessed_by_authorized_user(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');

        $role = Role::findOrCreate('administrador', 'web');
        $permission = Permission::findOrCreate('Regularizar:Jornada', 'web');
        $role->givePermissionTo($permission);

        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin, 'web');

        $this->assertTrue(JornadasSinSalida::canAccess());
    }

    public function test_page_displays_unclosed_expired_jornadas_in_table(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');

        $role = Role::findOrCreate('administrador', 'web');
        $role->givePermissionTo(Permission::findOrCreate('Regularizar:Jornada', 'web'));
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $sucursal = Sucursal::create(['nombre' => 'Sucursal Central', 'tipo' => 'tienda', 'activo' => true]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'nombre_completo' => 'Juan Sin Salida',
            'documento_identidad' => 'DOC-'.uniqid(),
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Apertura',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'cruza_medianoche' => false,
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'incluye_refrigerio' => false,
            'refrigerio_minutos' => 0,
            'horas_efectivas_objetivo_minutos' => 540,
            'activo' => true,
        ]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => '2026-10-30',
        ]);
        Marcacion::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => Marcacion::TIPO_ENTRADA,
            'fecha_hora' => '2026-10-30 08:05:00',
        ]);

        Livewire::actingAs($admin)
            ->test(JornadasSinSalida::class)
            ->assertCanSeeTableRecords([$asignacion]);
    }
}
