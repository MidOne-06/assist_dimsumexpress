<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->timestamp('refrigerio_retorno_esperado_en')->nullable()->after('fecha_hora');
            $table->integer('refrigerio_diferencia_segundos')->nullable()->after('refrigerio_retorno_esperado_en');
        });
    }

    public function down(): void
    {
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->dropColumn(['refrigerio_retorno_esperado_en', 'refrigerio_diferencia_segundos']);
        });
    }
};
