<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->boolean('jornada_abierta')->default(false)->after('solo_entrada');
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->dropColumn('jornada_abierta');
        });
    }
};
