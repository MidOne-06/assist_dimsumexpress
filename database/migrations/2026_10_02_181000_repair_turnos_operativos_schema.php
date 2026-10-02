<?php

use App\Models\Sucursal;
use App\Models\Turno;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('turnos_operativos')) {
            Schema::create('turnos_operativos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->cascadeOnDelete();
                $table->unsignedSmallInteger('prioridad')->default(100);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->index(['sucursal_id', 'punto_venta_id', 'activo'], 'turno_operativo_estacion_activo');
            });
        }

        $turnos = Turno::query()->whereIn('nombre', ['Apertura', 'Cierre'])->pluck('id');
        Sucursal::query()->where('tipo', 'tienda')->where('activo', true)->each(function (Sucursal $sucursal) use ($turnos): void {
            foreach ($turnos as $turnoId) {
                DB::table('turnos_operativos')->updateOrInsert(
                    ['turno_id' => $turnoId, 'sucursal_id' => $sucursal->id, 'punto_venta_id' => null],
                    ['prioridad' => 100, 'activo' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        Schema::table('asignaciones_turno', function (Blueprint $table): void {
            if (! Schema::hasColumn('asignaciones_turno', 'turno_operativo_id')) {
                $table->foreignId('turno_operativo_id')->nullable()->after('turno_id')->constrained('turnos_operativos')->nullOnDelete();
            }
            if (! Schema::hasColumn('asignaciones_turno', 'origen')) {
                $table->string('origen', 30)->default('manual')->after('fecha');
            }
            if (! Schema::hasColumn('asignaciones_turno', 'detectado_en')) {
                $table->timestamp('detectado_en')->nullable()->after('origen');
            }
        });
    }

    public function down(): void { /* Reparación deliberadamente no destructiva. */ }
};
