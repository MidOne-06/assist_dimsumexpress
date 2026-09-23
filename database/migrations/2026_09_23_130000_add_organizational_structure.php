<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('codigo', 30)->unique();
            $table->string('ruc', 20)->nullable()->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('codigo', 30)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('puntos_venta', function (Blueprint $table): void {
            $table->string('tipo', 30)->default('caja')->after('nombre');
        });

        Schema::table('colaboradores', function (Blueprint $table): void {
            $table->foreignId('empresa_id')->nullable()->after('user_id')->constrained('empresas')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('empresa_id')->constrained('areas')->nullOnDelete();
            $table->string('codigo_empresa', 60)->nullable()->after('documento_identidad');
            $table->unique(['empresa_id', 'codigo_empresa'], 'colaboradores_empresa_codigo_unique');
        });

        Schema::table('marcaciones', function (Blueprint $table): void {
            $table->foreignId('empresa_id')->nullable()->after('colaborador_id')->constrained('empresas')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('empresa_id')->constrained('areas')->nullOnDelete();
            $table->index(['empresa_id', 'fecha_hora']);
        });

        $now = now();

        DB::table('empresas')->upsert([
            ['nombre' => 'Corporacion JAP Inversions', 'codigo' => 'JAP', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'DSE', 'codigo' => 'DSE', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Corporacion Koochoy', 'codigo' => 'KOOCHOY', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['codigo'], ['nombre', 'activo', 'updated_at']);

        DB::table('areas')->upsert([
            ['nombre' => 'Marketing', 'codigo' => 'MKT', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Recursos Humanos', 'codigo' => 'RRHH', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Tecnología de la Información', 'codigo' => 'TI', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Producción', 'codigo' => 'PROD', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Operaciones', 'codigo' => 'OPE', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['codigo'], ['nombre', 'activo', 'updated_at']);
    }

    public function down(): void
    {
        Schema::table('marcaciones', function (Blueprint $table): void {
            $table->dropIndex(['empresa_id', 'fecha_hora']);
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('empresa_id');
        });

        Schema::table('colaboradores', function (Blueprint $table): void {
            $table->dropUnique('colaboradores_empresa_codigo_unique');
            $table->dropColumn('codigo_empresa');
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('empresa_id');
        });

        Schema::table('puntos_venta', function (Blueprint $table): void {
            $table->dropColumn('tipo');
        });

        Schema::dropIfExists('areas');
        Schema::dropIfExists('empresas');
    }
};
