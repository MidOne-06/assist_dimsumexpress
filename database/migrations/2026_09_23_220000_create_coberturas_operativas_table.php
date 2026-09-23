<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coberturas_operativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_turno_id')->constrained('asignaciones_turno')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
            $table->string('origen', 30)->default('automatica');
            $table->string('estado', 30)->default('pendiente');
            $table->timestamp('detectada_en');
            $table->timestamp('revisada_en')->nullable();
            $table->foreignId('revisada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion_revision')->nullable();
            $table->timestamps();

            $table->unique(['asignacion_turno_id', 'sucursal_id', 'punto_venta_id'], 'cobertura_turno_estacion_unique');
            $table->index(['sucursal_id', 'detectada_en']);
        });

        Schema::table('marcaciones', function (Blueprint $table) {
            $table->foreignId('cobertura_operativa_id')
                ->nullable()
                ->after('punto_venta_id')
                ->constrained('coberturas_operativas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cobertura_operativa_id');
        });

        Schema::dropIfExists('coberturas_operativas');
    }
};
