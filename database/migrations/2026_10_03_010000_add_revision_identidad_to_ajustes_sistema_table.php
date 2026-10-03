<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes_sistema', function (Blueprint $table): void {
            $table->unsignedBigInteger('revision_identidad')->default(1)->after('color_primario');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes_sistema', function (Blueprint $table): void {
            $table->dropColumn('revision_identidad');
        });
    }
};
