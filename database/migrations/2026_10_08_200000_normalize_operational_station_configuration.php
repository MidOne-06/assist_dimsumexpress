<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Una regla que referencia un turno archivado no participa en la
            // detección, pero antes seguía apareciendo como "vigente" en la
            // administración. Se conserva como histórico sin confundir la
            // configuración actual de la estación.
            DB::table('turnos_operativos')
                ->where('activo', true)
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('turnos')
                        ->whereColumn('turnos.id', 'turnos_operativos.turno_id')
                        ->where('turnos.activo', false);
                })
                ->update([
                    'activo' => false,
                    'updated_at' => now(),
                ]);

            $this->habilitarTurnosDeEstacion(
                sucursal: 'DIM SUM - METRO LA MARINA',
                puntoVenta: 'Caja 1',
                turnos: ['Apertura', 'Cierre'],
            );

            $this->habilitarTurnosDeEstacion(
                sucursal: 'FABRICA',
                puntoVenta: 'Terminal 2',
                turnos: ['TURNO PLANTA'],
            );
        });
    }

    public function down(): void
    {
        // La normalización archiva configuración histórica y habilita reglas
        // operativas expresamente aprobadas. No se revierte de forma ciega,
        // pues podría reactivar un turno que administración haya archivado.
    }

    /** @param array<int, string> $turnos */
    private function habilitarTurnosDeEstacion(string $sucursal, string $puntoVenta, array $turnos): void
    {
        $sucursalId = DB::table('sucursales')
            ->where('activo', true)
            ->whereRaw('lower(trim(nombre)) = ?', [mb_strtolower($sucursal)])
            ->value('id');

        if (! $sucursalId) {
            return;
        }

        $puntoVentaId = DB::table('puntos_venta')
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->whereRaw('lower(trim(nombre)) = ?', [mb_strtolower($puntoVenta)])
            ->value('id');

        if (! $puntoVentaId) {
            return;
        }

        foreach ($turnos as $nombreTurno) {
            $turnoId = DB::table('turnos')
                ->where('activo', true)
                ->whereRaw('lower(trim(nombre)) = ?', [mb_strtolower($nombreTurno)])
                ->value('id');

            if (! $turnoId) {
                continue;
            }

            $regla = DB::table('turnos_operativos')
                ->where('sucursal_id', $sucursalId)
                ->where('punto_venta_id', $puntoVentaId)
                ->where('turno_id', $turnoId)
                ->orderBy('id')
                ->first();

            if ($regla) {
                DB::table('turnos_operativos')
                    ->where('id', $regla->id)
                    ->update(['activo' => true, 'prioridad' => 100, 'updated_at' => now()]);

                continue;
            }

            DB::table('turnos_operativos')->insert([
                'sucursal_id' => $sucursalId,
                'punto_venta_id' => $puntoVentaId,
                'turno_id' => $turnoId,
                'prioridad' => 100,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
