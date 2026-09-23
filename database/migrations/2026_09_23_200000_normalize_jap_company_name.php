<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('empresas')
            ->where('codigo', 'JAP')
            ->update([
                'nombre' => 'Corporacion JAP Inversions',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('empresas')
            ->where('codigo', 'JAP')
            ->update([
                'nombre' => 'Corporación JAP',
                'updated_at' => now(),
            ]);
    }
};
