<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            // Turno nocturno (ej. 22:00 - 06:00): hora_fin es al día siguiente.
            $table->boolean('cruza_medianoche')->default(false);
            $table->unsignedSmallInteger('tolerancia_entrada_minutos')->default(10);
            $table->unsignedSmallInteger('tolerancia_salida_minutos')->default(10);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
