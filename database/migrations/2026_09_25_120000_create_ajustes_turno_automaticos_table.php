<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_turno_automaticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_turno_id')->constrained('asignaciones_turno')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('turno_programado_id')->constrained('turnos')->restrictOnDelete();
            $table->foreignId('turno_efectivo_id')->constrained('turnos')->restrictOnDelete();
            $table->timestamp('detectado_en');
            $table->timestamps();

            $table->unique('asignacion_turno_id');
            $table->index(['colaborador_id', 'detectado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_turno_automaticos');
    }
};
