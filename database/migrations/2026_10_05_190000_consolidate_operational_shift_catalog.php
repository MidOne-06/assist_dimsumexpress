<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Las migraciones iniciales de configuración pudieron ejecutarse
            // más de una vez. Conservamos una regla vigente por estación y
            // turno; las demás quedan archivadas para no perder referencias.
            $duplicadas = DB::table('turnos_operativos')
                ->select('sucursal_id', 'punto_venta_id', 'turno_id')
                ->where('activo', true)
                ->groupBy('sucursal_id', 'punto_venta_id', 'turno_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicadas as $duplicada) {
                $consultaBase = DB::table('turnos_operativos')
                    ->where('sucursal_id', $duplicada->sucursal_id)
                    ->where('turno_id', $duplicada->turno_id)
                    ->where('activo', true);

                if ($duplicada->punto_venta_id === null) {
                    $consultaBase->whereNull('punto_venta_id');
                } else {
                    $consultaBase->where('punto_venta_id', $duplicada->punto_venta_id);
                }

                // La menor prioridad gana; a igualdad de prioridad, la regla
                // más antigua es la canónica. Es la misma convención usada por
                // el detector de marcaciones.
                $idConservado = (clone $consultaBase)
                    ->orderBy('prioridad')
                    ->orderBy('id')
                    ->value('id');

                $consultaBase
                    ->where('id', '!=', $idConservado)
                    ->update(['activo' => false, 'updated_at' => now()]);
            }
        });

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            $condicionActiva = $driver === 'pgsql' ? 'activo' : 'activo = 1';
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS turnos_nombre_vigente_unico ON turnos (lower(trim(nombre))) WHERE {$condicionActiva}");
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS turno_operativo_vigente_unico ON turnos_operativos (sucursal_id, COALESCE(punto_venta_id, 0), turno_id) WHERE {$condicionActiva}");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS turnos_nombre_vigente_unico');
            DB::statement('DROP INDEX IF EXISTS turno_operativo_vigente_unico');
        }
    }
};
