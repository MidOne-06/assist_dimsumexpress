<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->time('hora_fin')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            // Los turnos de solo entrada creados con la nueva regla deben
            // regularizarse antes de revertir esta migración.
            $table->time('hora_fin')->nullable(false)->change();
        });
    }
};
