<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->unsignedSmallInteger('horas_efectivas_jornada_completa_minutos')
                ->nullable()
                ->after('horas_efectivas_objetivo_minutos');
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->dropColumn('horas_efectivas_jornada_completa_minutos');
        });
    }
};
