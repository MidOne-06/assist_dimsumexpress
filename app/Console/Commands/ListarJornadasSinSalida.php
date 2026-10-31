<?php

namespace App\Console\Commands;

use App\Services\JornadasSinSalidaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ListarJornadasSinSalida extends Command
{
    protected $signature = 'jornadas:sin-salida {--dias=7 : Días hacia atrás a revisar}';

    protected $description = 'Lista jornadas con ingreso, sin salida y con vigencia vencida (solo lectura)';

    public function handle(JornadasSinSalidaService $servicio): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $hasta = Carbon::today(config('app.timezone'));
        $pendientes = $servicio->listar($hasta->copy()->subDays($dias), $hasta);

        if ($pendientes->isEmpty()) {
            $this->info('No hay jornadas sin salida en el periodo.');

            return self::SUCCESS;
        }

        $this->table(
            ['Fecha', 'Colaborador', 'Sucursal', 'Turno', 'Entrada', 'Última marca', 'Venció'],
            $pendientes->map(fn (array $fila): array => [
                $fila['asignacion']->fecha->format('d/m/Y'),
                $fila['asignacion']->colaborador->nombre_completo,
                $fila['asignacion']->colaborador->sucursal?->nombre,
                $fila['asignacion']->turno->nombre,
                $fila['entrada']->fecha_hora->format('d/m H:i'),
                $fila['ultima']->tipo.' '.$fila['ultima']->fecha_hora->format('d/m H:i'),
                $fila['vencio_en']->format('d/m H:i'),
            ])->all(),
        );
        $this->warn($pendientes->count().' jornada(s) sin salida.');

        return self::SUCCESS;
    }
}
