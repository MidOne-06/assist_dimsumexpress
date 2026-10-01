<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidencias_marcacion', function (Blueprint $table): void {
            $table->dropUnique(['asignacion_turno_id']);
            $table->foreignId('sucursal_id')->nullable()->after('colaborador_id')->constrained('sucursales')->nullOnDelete();
            $table->foreignId('punto_venta_id')->nullable()->after('sucursal_id')->constrained('puntos_venta')->nullOnDelete();
            $table->unique(['asignacion_turno_id', 'tipo'], 'incidencia_turno_tipo_unique');
            $table->index(['sucursal_id', 'resuelta_en']);
        });

        DB::table('incidencias_marcacion')
            ->orderBy('id')
            ->chunkById(200, function ($incidencias): void {
                foreach ($incidencias as $incidencia) {
                    $sucursalId = DB::table('colaboradores')
                        ->where('id', $incidencia->colaborador_id)
                        ->value('sucursal_id');

                    DB::table('incidencias_marcacion')
                        ->where('id', $incidencia->id)
                        ->update(['sucursal_id' => $sucursalId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('incidencias_marcacion', function (Blueprint $table): void {
            $table->dropIndex(['sucursal_id', 'resuelta_en']);
            $table->dropUnique('incidencia_turno_tipo_unique');
            $table->dropConstrainedForeignId('punto_venta_id');
            $table->dropConstrainedForeignId('sucursal_id');
            $table->unique('asignacion_turno_id');
        });
    }
};
