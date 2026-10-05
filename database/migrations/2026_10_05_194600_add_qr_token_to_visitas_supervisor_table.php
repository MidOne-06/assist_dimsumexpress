<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitas_supervisor', function (Blueprint $table): void {
            $table->foreignId('qr_token_id')
                ->nullable()
                ->after('punto_venta_id')
                ->constrained('qr_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visitas_supervisor', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('qr_token_id');
        });
    }
};
