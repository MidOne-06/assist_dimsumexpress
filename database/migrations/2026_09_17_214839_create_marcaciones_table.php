<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->enum('tipo', ['entrada', 'salida', 'salida_refrigerio', 'regreso_refrigerio']);
            $table->timestamp('fecha_hora');
            // Turno vigente del colaborador ese día (de asignaciones_turno), usado para
            // calcular tardanzas/salidas anticipadas sin recalcularlo después.
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->foreignId('qr_token_id')->nullable()->constrained('qr_tokens')->nullOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
            $table->string('ip_origen', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['colaborador_id', 'fecha_hora']);
            $table->index(['sucursal_id', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcaciones');
    }
};
