<?php

namespace Tests\Feature;

use App\Filament\Resources\CoberturaOperativas\CoberturaOperativaResource;
use App\Filament\Resources\CoberturaOperativas\Pages\ListCoberturaOperativas;
use App\Models\AsignacionTurno;
use App\Models\CoberturaOperativa;
use App\Models\Colaborador;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CoberturasOperativasTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_and_detail_only_include_coverages_from_allowed_locations(): void
    {
        [$cobertura, $sucursal] = $this->crearCobertura();
        [, $ajena] = $this->crearCobertura('Local ajeno');
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('ViewAny:CoberturaOperativa', 'web'),
            Permission::findOrCreate('View:CoberturaOperativa', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        $this->actingAs($supervisor);

        $this->assertSame([$cobertura->id], CoberturaOperativaResource::getEloquentQuery()->pluck('id')->all());
        $schema = (new \ReflectionMethod(CoberturaOperativaResource::class, 'detalleSchema'))
            ->invoke(null, $cobertura->load(['colaborador', 'asignacionTurno.turno', 'sucursal', 'puntoVenta', 'revisadaPor']));

        $this->assertCount(4, $schema);
        $this->assertInstanceOf(\Filament\Schemas\Components\Section::class, $schema[0]);
        $this->assertNotSame($sucursal->id, $ajena->id);
    }

    public function test_supervisor_can_review_a_coverage_with_a_required_observation_when_observed(): void
    {
        [$cobertura, $sucursal] = $this->crearCobertura();
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('ViewAny:CoberturaOperativa', 'web'),
            Permission::findOrCreate('View:CoberturaOperativa', 'web'),
            Permission::findOrCreate('Revisar:CoberturaOperativa', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        Livewire::actingAs($supervisor)
            ->test(ListCoberturaOperativas::class)
            ->callTableAction('revisar', $cobertura, [
                'estado' => CoberturaOperativa::ESTADO_OBSERVADA,
                'observacion_revision' => 'Cobertura no coordinada con supervisión.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('coberturas_operativas', [
            'id' => $cobertura->id,
            'estado' => CoberturaOperativa::ESTADO_OBSERVADA,
            'revisada_por_id' => $supervisor->id,
            'observacion_revision' => 'Cobertura no coordinada con supervisión.',
        ]);
    }

    public function test_observed_coverage_cannot_be_saved_without_an_observation(): void
    {
        [$cobertura, $sucursal] = $this->crearCobertura();
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(
            Permission::findOrCreate('ViewAny:CoberturaOperativa', 'web'),
            Permission::findOrCreate('View:CoberturaOperativa', 'web'),
            Permission::findOrCreate('Revisar:CoberturaOperativa', 'web'),
        );
        $supervisor->sucursalesSupervisadas()->attach($sucursal);

        Livewire::actingAs($supervisor)
            ->test(ListCoberturaOperativas::class)
            ->callTableAction('revisar', $cobertura, [
                'estado' => CoberturaOperativa::ESTADO_OBSERVADA,
            ])
            ->assertHasTableActionErrors(['observacion_revision' => 'required']);

        $this->assertDatabaseHas('coberturas_operativas', [
            'id' => $cobertura->id,
            'estado' => CoberturaOperativa::ESTADO_PENDIENTE,
            'revisada_en' => null,
        ]);
    }

    /** @return array{CoberturaOperativa, Sucursal} */
    private function crearCobertura(string $nombreSucursal = 'Local de cobertura'): array
    {
        $sucursal = Sucursal::create(['nombre' => $nombreSucursal, 'tipo' => 'tienda', 'activo' => true]);
        $puntoVenta = PuntoVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja 1', 'activo' => true]);
        $colaborador = Colaborador::create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta->id,
            'nombre_completo' => 'Colaborador de cobertura '.uniqid(),
            'documento_identidad' => 'COB-'.uniqid(),
            'activo' => true,
        ]);
        $turno = Turno::create([
            'nombre' => 'Turno de cobertura '.uniqid(),
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'tolerancia_entrada_minutos' => 10,
            'tolerancia_salida_minutos' => 10,
            'activo' => true,
        ]);
        $asignacion = AsignacionTurno::create([
            'colaborador_id' => $colaborador->id,
            'turno_id' => $turno->id,
            'fecha' => now()->toDateString(),
        ]);

        return [CoberturaOperativa::create([
            'asignacion_turno_id' => $asignacion->id,
            'colaborador_id' => $colaborador->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta->id,
            'origen' => CoberturaOperativa::ORIGEN_AUTOMATICA,
            'estado' => CoberturaOperativa::ESTADO_PENDIENTE,
            'detectada_en' => now(),
        ]), $sucursal];
    }
}
