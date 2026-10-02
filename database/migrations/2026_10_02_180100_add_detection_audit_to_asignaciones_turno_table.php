<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_turno', function (Blueprint $table): void {
            $table->string('origen', 30)->default('manual')->after('fecha');
            $table->foreignId('turno_operativo_id')->nullable()->after('turno_id')->constrained('turnos_operativos')->nullOnDelete();
            $table->timestamp('detectado_en')->nullable()->after('origen');
        });
    }
    public function down(): void
    {
        Schema::table('asignaciones_turno', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('turno_operativo_id');
            $table->dropColumn(['origen', 'detectado_en']);
        });
    }
};
