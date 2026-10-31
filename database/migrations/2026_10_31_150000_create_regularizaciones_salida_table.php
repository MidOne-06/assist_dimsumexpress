<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regularizaciones_salida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_turno_id')->constrained('asignaciones_turno')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('marcacion_id')->constrained('marcaciones')->cascadeOnDelete();
            $table->foreignId('regularizada_por_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('salida_en');
            $table->string('motivo', 500);
            $table->string('ip_origen', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamps();

            $table->index(['colaborador_id', 'created_at']);
            $table->index('asignacion_turno_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regularizaciones_salida');
    }
};
