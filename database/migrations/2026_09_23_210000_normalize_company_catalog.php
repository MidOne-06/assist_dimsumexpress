<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'JAP' => 'Corporacion JAP Inversions',
            'DSE' => 'DSE',
            'KOOCHOY' => 'Corporacion Koochoy',
        ] as $codigo => $nombre) {
            DB::table('empresas')
                ->where('codigo', $codigo)
                ->update([
                    'nombre' => $nombre,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Esta normalización de catálogo no elimina empresas ni referencias.
    }
};
