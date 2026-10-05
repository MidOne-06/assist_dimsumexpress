<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitas_supervisor', function (Blueprint $table): void {
            $table->string('estado', 32)->default('historica')->after('qr_token_id');
            $table->timestamp('ingreso_en')->nullable()->after('fecha_hora');
            $table->timestamp('salida_en')->nullable()->after('ingreso_en');
            $table->foreignId('punto_venta_ingreso_id')->nullable()->after('punto_venta_id')->constrained('puntos_venta')->nullOnDelete();
            $table->foreignId('punto_venta_salida_id')->nullable()->after('punto_venta_ingreso_id')->constrained('puntos_venta')->nullOnDelete();
            $table->foreignId('ingreso_qr_token_id')->nullable()->after('qr_token_id')->constrained('qr_tokens')->nullOnDelete();
            $table->foreignId('salida_qr_token_id')->nullable()->after('ingreso_qr_token_id')->constrained('qr_tokens')->nullOnDelete();
            $table->string('ingreso_ip_origen', 45)->nullable()->after('ip_origen');
            $table->string('salida_ip_origen', 45)->nullable()->after('ingreso_ip_origen');
            $table->string('ingreso_user_agent', 1000)->nullable()->after('user_agent');
            $table->string('salida_user_agent', 1000)->nullable()->after('ingreso_user_agent');
            $table->foreignId('regularizada_por_id')->nullable()->after('salida_qr_token_id')->constrained('users')->nullOnDelete();
            $table->timestamp('regularizada_en')->nullable()->after('salida_en');
            $table->string('regularizacion_motivo', 1000)->nullable()->after('regularizada_en');
            $table->index(['supervisor_id', 'estado'], 'visitas_supervisor_supervisor_estado_index');
            $table->index(['sucursal_id', 'estado'], 'visitas_supervisor_sucursal_estado_index');
        });

        // Los registros previos representan una visita de un único momento.
        // Se conservan como evidencia histórica, sin inferir una salida.
        DB::table('visitas_supervisor')->update([
            'ingreso_en' => DB::raw('fecha_hora'),
            'punto_venta_ingreso_id' => DB::raw('punto_venta_id'),
            'ingreso_qr_token_id' => DB::raw('qr_token_id'),
            'ingreso_ip_origen' => DB::raw('ip_origen'),
            'ingreso_user_agent' => DB::raw('user_agent'),
        ]);

        Schema::table('visitas_supervisor', function (Blueprint $table): void {
            $table->dropUnique(['supervisor_id', 'sucursal_id', 'fecha']);
        });

        Schema::create('visita_supervisor_marcaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visita_supervisor_id')->constrained('visitas_supervisor')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
            $table->foreignId('qr_token_id')->nullable()->constrained('qr_tokens')->nullOnDelete();
            $table->string('tipo', 32);
            $table->timestamp('fecha_hora');
            $table->string('ip_origen', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['supervisor_id', 'qr_token_id'], 'visita_supervisor_marcaciones_supervisor_qr_unique');
            $table->index(['visita_supervisor_id', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visita_supervisor_marcaciones');

        Schema::table('visitas_supervisor', function (Blueprint $table): void {
            $table->dropIndex('visitas_supervisor_supervisor_estado_index');
            $table->dropIndex('visitas_supervisor_sucursal_estado_index');
            $table->dropConstrainedForeignId('punto_venta_ingreso_id');
            $table->dropConstrainedForeignId('punto_venta_salida_id');
            $table->dropConstrainedForeignId('ingreso_qr_token_id');
            $table->dropConstrainedForeignId('salida_qr_token_id');
            $table->dropConstrainedForeignId('regularizada_por_id');
            $table->dropColumn([
                'estado', 'ingreso_en', 'salida_en', 'ingreso_ip_origen', 'salida_ip_origen',
                'ingreso_user_agent', 'salida_user_agent', 'regularizada_en', 'regularizacion_motivo',
            ]);
        });
    }
};
