<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->date('fecha');
            $table->string('observacion')->nullable();
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un colaborador solo puede tener un turno asignado por día.
            $table->unique(['colaborador_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_turno');
    }
};
