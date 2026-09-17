<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->cascadeOnDelete();
            // Token opaco, distinto en cada rotación (cada 15-30s); no es reutilizable
            // pasada su expiración ni fuera de la sucursal/punto de venta que lo emitió.
            $table->string('token', 64)->unique();
            $table->timestamp('expira_en');
            $table->timestamps();

            $table->index(['sucursal_id', 'punto_venta_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_tokens');
    }
};
