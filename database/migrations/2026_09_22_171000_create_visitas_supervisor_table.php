<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas_supervisor', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
            $table->date('fecha');
            $table->timestamp('fecha_hora');
            $table->string('ip_origen', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamps();

            $table->unique(['supervisor_id', 'sucursal_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_supervisor');
    }
};
