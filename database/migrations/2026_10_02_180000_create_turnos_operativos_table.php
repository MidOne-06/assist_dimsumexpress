<?php

use App\Models\Sucursal;
use App\Models\Turno;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        // Arranque controlado: solo tiendas heredan Apertura y Cierre. Planta
        // mantiene su jornada abierta y se habilita de forma explícita después.
        $turnos = Turno::query()->whereIn('nombre', ['Apertura', 'Cierre'])->pluck('id');
        Sucursal::query()->where('tipo', 'tienda')->where('activo', true)->each(function (Sucursal $sucursal) use ($turnos): void {
            foreach ($turnos as $turnoId) {
                \DB::table('turnos_operativos')->insert([
                    'turno_id' => $turnoId,
                    'sucursal_id' => $sucursal->id,
                    'punto_venta_id' => null,
                    'prioridad' => 100,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void { Schema::dropIfExists('turnos_operativos'); }
};
