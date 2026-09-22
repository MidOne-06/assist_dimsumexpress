<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->boolean('incluye_refrigerio')->default(true)->after('solo_entrada');
            $table->unsignedSmallInteger('refrigerio_minutos')->default(60)->after('incluye_refrigerio');
            $table->unsignedSmallInteger('horas_efectivas_objetivo_minutos')->default(480)->after('refrigerio_minutos');
        });

        // Los turnos existentes conservan su duración efectiva real: rango
        // programado menos el refrigerio que el sistema ya aplicaba.
        DB::table('turnos')->orderBy('id')->each(function (object $turno): void {
            $inicio = strtotime('2000-01-01 ' . $turno->hora_inicio);
            $fin = strtotime('2000-01-01 ' . $turno->hora_fin);
            if ($fin <= $inicio || $turno->cruza_medianoche) {
                $fin += 86400;
            }
            $minutos = max(1, intdiv($fin - $inicio, 60) - 60);
            DB::table('turnos')->where('id', $turno->id)->update([
                'incluye_refrigerio' => true,
                'refrigerio_minutos' => 60,
                'horas_efectivas_objetivo_minutos' => $minutos,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->dropColumn(['incluye_refrigerio', 'refrigerio_minutos', 'horas_efectivas_objetivo_minutos']);
        });
    }
};
