<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumenes_jornada', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asignacion_turno_id')->unique()->constrained('asignaciones_turno')->restrictOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
            $table->foreignId('entrada_marcacion_id')->constrained('marcaciones')->restrictOnDelete();
            $table->foreignId('salida_marcacion_id')->constrained('marcaciones')->restrictOnDelete();
            $table->foreignId('salida_refrigerio_marcacion_id')->nullable()->constrained('marcaciones')->nullOnDelete();
            $table->foreignId('regreso_refrigerio_marcacion_id')->nullable()->constrained('marcaciones')->nullOnDelete();
            $table->date('fecha_jornada');
            $table->string('estado', 30);
            $table->unsignedInteger('efectivos_segundos');
            $table->unsignedInteger('objetivo_segundos');
            $table->integer('diferencia_segundos');
            $table->unsignedInteger('extras_segundos');
            $table->unsignedInteger('refrigerio_segundos')->default(0);
            $table->timestamp('consolidado_en');
            $table->unsignedSmallInteger('calculo_version')->default(1);
            $table->string('huella_marcaciones', 64);
            $table->json('detalle');
            $table->timestamps();

            $table->index(['fecha_jornada', 'sucursal_id']);
            $table->index(['colaborador_id', 'fecha_jornada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumenes_jornada');
    }
};
