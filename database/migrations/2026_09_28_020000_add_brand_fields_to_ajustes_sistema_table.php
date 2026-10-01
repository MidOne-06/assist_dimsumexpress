<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes_sistema', function (Blueprint $table): void {
            $table->string('logo_oscuro')->nullable()->after('logo');
            $table->string('icono')->nullable()->after('logo_oscuro');
            $table->string('color_primario', 7)->default('#f59e0b')->after('icono');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes_sistema', function (Blueprint $table): void {
            $table->dropColumn(['logo_oscuro', 'icono', 'color_primario']);
        });
    }
};
