<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marcaciones')) {
            return;
        }

        DB::table('marcaciones')
            ->select(['id', 'colaborador_id', 'turno_id', 'fecha_hora'])
            ->where('tipo', 'regreso_refrigerio')
            ->where(function ($query): void {
                $query->whereNull('refrigerio_retorno_esperado_en')
                    ->orWhereNull('refrigerio_diferencia_segundos');
            })
            ->orderBy('id')
            ->eachById(function (object $retorno): void {
                $turno = DB::table('turnos')
                    ->select(['incluye_refrigerio', 'refrigerio_minutos'])
                    ->where('id', $retorno->turno_id)
                    ->first();

                if (! $turno || ! $turno->incluye_refrigerio) {
                    return;
                }

                $salida = DB::table('marcaciones')
                    ->select('fecha_hora')
                    ->where('colaborador_id', $retorno->colaborador_id)
                    ->where('turno_id', $retorno->turno_id)
                    ->where('tipo', 'salida_refrigerio')
                    ->where('fecha_hora', '<=', $retorno->fecha_hora)
                    ->orderByDesc('fecha_hora')
                    ->orderByDesc('id')
                    ->first();

                if (! $salida) {
                    return;
                }

                $esperado = Carbon::parse($salida->fecha_hora, config('app.timezone'))
                    ->addMinutes((int) $turno->refrigerio_minutos);
                $retornoEn = Carbon::parse($retorno->fecha_hora, config('app.timezone'));

                DB::table('marcaciones')
                    ->where('id', $retorno->id)
                    ->update([
                        'refrigerio_retorno_esperado_en' => $esperado,
                        'refrigerio_diferencia_segundos' => $retornoEn->getTimestamp() - $esperado->getTimestamp(),
                    ]);
            }, 'id');
    }

    public function down(): void
    {
        // Se preservan los valores normalizados: son trazabilidad operativa.
    }
};
