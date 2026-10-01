<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enlaces_acceso_colaborador', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('generado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expira_en');
            $table->timestamp('usado_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->string('generado_desde_ip', 45)->nullable();
            $table->string('usado_desde_ip', 45)->nullable();
            $table->text('user_agent_uso')->nullable();
            $table->timestamps();

            $table->index(['colaborador_id', 'expira_en']);
            $table->index(['user_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_acceso_colaborador');
    }
};
