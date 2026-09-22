<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->boolean('solo_entrada')->default(false)->after('tolerancia_salida_minutos');
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->dropColumn('solo_entrada');
        });
    }
};
