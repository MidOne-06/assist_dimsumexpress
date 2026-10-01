<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes_sistema', function (Blueprint $table): void {
            $table->string('logo_app_movil')->nullable()->after('icono');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes_sistema', function (Blueprint $table): void {
            $table->dropColumn('logo_app_movil');
        });
    }
};
