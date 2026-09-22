<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_tokens', function (Blueprint $table): void {
            $table->string('proposito', 40)->default('asistencia')->after('token');
            $table->index(['proposito', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::table('qr_tokens', function (Blueprint $table): void {
            $table->dropIndex(['proposito', 'expira_en']);
            $table->dropColumn('proposito');
        });
    }
};
