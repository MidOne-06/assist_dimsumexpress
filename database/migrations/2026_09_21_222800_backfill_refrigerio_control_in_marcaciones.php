<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('marcaciones')
            ->where('tipo', 'regreso_refrigerio')
            ->whereNull('refrigerio_retorno_esperado_en')
            ->orderBy('id')
            ->eachById(function (object $retorno): void {
                $salida = DB::table('marcaciones')
                    ->where('colaborador_id', $retorno->colaborador_id)
                    ->where('tipo', 'salida_refrigerio')
                    ->where('fecha_hora', '<=', $retorno->fecha_hora)
                    ->when(
                        $retorno->turno_id === null,
                        fn ($query) => $query->whereNull('turno_id'),
                        fn ($query) => $query->where('turno_id', $retorno->turno_id),
                    )
                    ->orderByDesc('fecha_hora')
                    ->orderByDesc('id')
                    ->first();

                if (! $salida) {
                    return;
                }

                $esperado = Carbon::parse($salida->fecha_hora, config('app.timezone'))->addHour();
                $registrado = Carbon::parse($retorno->fecha_hora, config('app.timezone'));

                DB::table('marcaciones')
                    ->where('id', $retorno->id)
                    ->update([
                        'refrigerio_retorno_esperado_en' => $esperado,
                        'refrigerio_diferencia_segundos' => $registrado->getTimestamp() - $esperado->getTimestamp(),
                    ]);
            }, column: 'id');
    }

    public function down(): void
    {
        DB::table('marcaciones')
            ->where('tipo', 'regreso_refrigerio')
            ->update([
                'refrigerio_retorno_esperado_en' => null,
                'refrigerio_diferencia_segundos' => null,
            ]);
    }
};
