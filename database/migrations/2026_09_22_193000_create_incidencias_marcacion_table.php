<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias_marcacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asignacion_turno_id')->constrained('asignaciones_turno')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->timestamp('detectada_en');
            $table->timestamp('resuelta_en')->nullable();
            $table->foreignId('resuelta_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion_resolucion')->nullable();
            $table->timestamps();

            $table->unique('asignacion_turno_id');
            $table->index(['colaborador_id', 'resuelta_en']);
            $table->index(['tipo', 'resuelta_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_marcacion');
    }
};
