<?php

namespace Database\Seeders;

use App\Models\AsignacionTurno;
use App\Models\Colaborador;
use App\Models\Marcacion;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    private const PASSWORD_DEMO = 'Demo1234!';

    /**
     * Carga datos de demostración persistentes (no se limpian solos) para
     * poder navegar el sistema con contenido real: sucursales, puntos de
     * venta, turnos, colaboradores con su propio login, y un mes completo
     * de asignaciones con un patrón rotativo (2 días de un turno, 2 del
     * otro, 2 de descanso) para que el calendario visual se vea poblado.
     */
    public function run(): void
    {
        $sucursales = $this->crearSucursales();
        $turnos = $this->crearTurnos();
        $colaboradores = $this->crearColaboradores($sucursales);
        $this->crearAsignacionesDelMes($colaboradores, $turnos);
        $this->crearMarcacionesHistoricas($colaboradores);

        $this->command?->table(
            ['Sucursal', 'Tipo', 'Colaboradores'],
            $sucursales->map(fn (Sucursal $s) => [
                $s->nombre,
                $s->tipo,
                $colaboradores->where('sucursal_id', $s->id)->count(),
            ])
        );

        $this->command?->info('Contraseña de todos los colaboradores demo: ' . self::PASSWORD_DEMO);
        $this->command?->info('Colaborador sugerido para probar el marcado: ' . $colaboradores->first()->user->email);
    }

    /** @return \Illuminate\Support\Collection<int, Sucursal> */
    private function crearSucursales()
    {
        $tiendaSanIsidro = Sucursal::firstOrCreate(
            ['nombre' => 'Tienda San Isidro'],
            ['tipo' => 'tienda', 'direccion' => 'Av. Javier Prado 1234, San Isidro', 'activo' => true]
        );

        $tiendaMiraflores = Sucursal::firstOrCreate(
            ['nombre' => 'Tienda Miraflores'],
            ['tipo' => 'tienda', 'direccion' => 'Av. Larco 890, Miraflores', 'activo' => true]
        );

        $planta = Sucursal::firstOrCreate(
            ['nombre' => 'Planta Central'],
            ['tipo' => 'planta', 'direccion' => 'Av. Argentina 4500, Callao', 'activo' => true]
        );

        PuntoVenta::firstOrCreate(['sucursal_id' => $tiendaSanIsidro->id, 'nombre' => 'Caja 1']);
        PuntoVenta::firstOrCreate(['sucursal_id' => $tiendaSanIsidro->id, 'nombre' => 'Caja 2']);
        PuntoVenta::firstOrCreate(['sucursal_id' => $tiendaMiraflores->id, 'nombre' => 'Caja 1']);

        return collect([$tiendaSanIsidro, $tiendaMiraflores, $planta]);
    }

    /** @return \Illuminate\Support\Collection<int, Turno> */
    private function crearTurnos()
    {
        $manana = Turno::firstOrCreate(
            ['nombre' => 'Turno Mañana'],
            ['hora_inicio' => '06:00', 'hora_fin' => '14:00', 'tolerancia_entrada_minutos' => 10, 'tolerancia_salida_minutos' => 10, 'activo' => true]
        );

        $tarde = Turno::firstOrCreate(
            ['nombre' => 'Turno Tarde'],
            ['hora_inicio' => '14:00', 'hora_fin' => '22:00', 'tolerancia_entrada_minutos' => 10, 'tolerancia_salida_minutos' => 10, 'activo' => true]
        );

        $noche = Turno::firstOrCreate(
            ['nombre' => 'Turno Noche'],
            ['hora_inicio' => '22:00', 'hora_fin' => '06:00', 'cruza_medianoche' => true, 'tolerancia_entrada_minutos' => 15, 'tolerancia_salida_minutos' => 15, 'activo' => true]
        );

        return collect([$manana, $tarde, $noche]);
    }

    /** @return \Illuminate\Support\Collection<int, Colaborador> */
    private function crearColaboradores($sucursales)
    {
        [$sanIsidro, $miraflores, $planta] = $sucursales->all();
        $pvSanIsidro = PuntoVenta::where('sucursal_id', $sanIsidro->id)->orderBy('nombre')->get();
        $pvMiraflores = PuntoVenta::where('sucursal_id', $miraflores->id)->first();

        $definiciones = [
            ['nombre' => 'Ana Torres Quispe', 'doc' => 'DEMO0001', 'sucursal' => $sanIsidro, 'pv' => $pvSanIsidro[0], 'cargo' => 'Cajera'],
            ['nombre' => 'Luis Ramírez Soto', 'doc' => 'DEMO0002', 'sucursal' => $sanIsidro, 'pv' => $pvSanIsidro[0], 'cargo' => 'Vendedor'],
            ['nombre' => 'Carla Mendoza Ríos', 'doc' => 'DEMO0003', 'sucursal' => $sanIsidro, 'pv' => $pvSanIsidro[1], 'cargo' => 'Cajera'],
            ['nombre' => 'Jorge Salazar Vega', 'doc' => 'DEMO0004', 'sucursal' => $sanIsidro, 'pv' => $pvSanIsidro[1], 'cargo' => 'Vendedor'],
            ['nombre' => 'María Flores Castro', 'doc' => 'DEMO0005', 'sucursal' => $miraflores, 'pv' => $pvMiraflores, 'cargo' => 'Cajera'],
            ['nombre' => 'Pedro Huamán Díaz', 'doc' => 'DEMO0006', 'sucursal' => $miraflores, 'pv' => $pvMiraflores, 'cargo' => 'Vendedor'],
            ['nombre' => 'Rosa Chávez León', 'doc' => 'DEMO0007', 'sucursal' => $miraflores, 'pv' => $pvMiraflores, 'cargo' => 'Supervisora'],
            ['nombre' => 'Miguel Paredes Rojas', 'doc' => 'DEMO0008', 'sucursal' => $planta, 'pv' => null, 'cargo' => 'Operario de producción'],
            ['nombre' => 'Diana Vargas Núñez', 'doc' => 'DEMO0009', 'sucursal' => $planta, 'pv' => null, 'cargo' => 'Operaria de producción'],
            ['nombre' => 'Carlos Espinoza Ibáñez', 'doc' => 'DEMO0010', 'sucursal' => $planta, 'pv' => null, 'cargo' => 'Jefe de planta'],
        ];

        return collect($definiciones)->map(function (array $def, int $i) {
            $email = 'demo.' . str($def['nombre'])->before(' ')->lower() . ($i + 1) . '@asistencias.local';

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $def['nombre'], 'password' => self::PASSWORD_DEMO]
            );

            return Colaborador::firstOrCreate(
                ['documento_identidad' => $def['doc']],
                [
                    'user_id' => $user->id,
                    'sucursal_id' => $def['sucursal']->id,
                    'punto_venta_id' => $def['pv']?->id,
                    'nombre_completo' => $def['nombre'],
                    'cargo' => $def['cargo'],
                    'fecha_ingreso' => now()->subMonths(rand(1, 24))->toDateString(),
                    'activo' => true,
                ]
            );
        });
    }

    /**
     * Patrón rotativo 2x2x2 (2 días turno A, 2 días turno B, 2 días de
     * descanso) sobre el mes actual completo, con un desfase distinto por
     * colaborador para que la grilla se vea variada, igual que un cuadro de
     * turnos real.
     */
    private function crearAsignacionesDelMes($colaboradores, $turnos): void
    {
        $inicio = now()->startOfMonth();
        $fin = now()->endOfMonth();
        [$manana, $tarde, $noche] = $turnos->all();

        $patronesPorColaborador = $colaboradores->values()->map(function (Colaborador $colaborador, int $indice) use ($manana, $tarde, $noche) {
            $ciclo = $colaborador->sucursal->esPlanta()
                ? [$noche, $noche, $tarde, $tarde, null, null]
                : [$manana, $manana, $tarde, $tarde, null, null];

            return ['colaborador' => $colaborador, 'ciclo' => $ciclo, 'desfase' => $indice];
        });

        foreach ($patronesPorColaborador as $patron) {
            $dia = $inicio->copy();
            $posicion = $patron['desfase'];

            while ($dia->lte($fin)) {
                $turno = $patron['ciclo'][$posicion % count($patron['ciclo'])];

                if ($turno) {
                    AsignacionTurno::firstOrCreate(
                        ['colaborador_id' => $patron['colaborador']->id, 'fecha' => $dia->toDateString()],
                        ['turno_id' => $turno->id]
                    );
                }

                $dia->addDay();
                $posicion++;
            }
        }
    }

    /**
     * Marcaciones reales de los últimos 6 días (sin incluir hoy, para dejar
     * el día de hoy "libre" y que el usuario pueda probar el marcado en
     * vivo desde la estación de marcado o su celular). Solo marca los días en que el
     * colaborador tenía un turno asignado, con pequeñas variaciones
     * aleatorias de minutos para simular tardanzas reales, y refrigerio en
     * la mitad de los casos.
     */
    private function crearMarcacionesHistoricas($colaboradores): void
    {
        foreach ($colaboradores as $colaborador) {
            for ($i = 6; $i >= 1; $i--) {
                $fecha = now()->subDays($i)->toDateString();

                $asignacion = AsignacionTurno::where('colaborador_id', $colaborador->id)
                    ->where('fecha', $fecha)
                    ->with('turno')
                    ->first();

                if (! $asignacion) {
                    continue;
                }

                if (Marcacion::where('colaborador_id', $colaborador->id)->whereDate('fecha_hora', $fecha)->exists()) {
                    continue;
                }

                $turno = $asignacion->turno;
                $baseEntrada = Carbon::parse("{$fecha} {$turno->hora_inicio}")->addMinutes(rand(-3, 12));
                $baseSalida = Carbon::parse("{$fecha} {$turno->hora_fin}")
                    ->when($turno->cruza_medianoche, fn (Carbon $c) => $c->addDay())
                    ->addMinutes(rand(-5, 15));

                $comun = [
                    'colaborador_id' => $colaborador->id,
                    'turno_id' => $turno->id,
                    'qr_token_id' => null,
                    'sucursal_id' => $colaborador->sucursal_id,
                    'punto_venta_id' => $colaborador->punto_venta_id,
                    'ip_origen' => '190.12.34.' . rand(10, 250),
                    'user_agent' => 'Demo Seeder',
                ];

                Marcacion::create([...$comun, 'tipo' => Marcacion::TIPO_ENTRADA, 'fecha_hora' => $baseEntrada]);

                if (rand(0, 1) === 1) {
                    $salidaRef = $baseEntrada->copy()->addHours(4)->addMinutes(rand(0, 20));
                    $regresoRef = $salidaRef->copy()->addMinutes(30 + rand(0, 10));

                    Marcacion::create([...$comun, 'tipo' => Marcacion::TIPO_SALIDA_REFRIGERIO, 'fecha_hora' => $salidaRef]);
                    Marcacion::create([...$comun, 'tipo' => Marcacion::TIPO_REGRESO_REFRIGERIO, 'fecha_hora' => $regresoRef]);
                }

                Marcacion::create([...$comun, 'tipo' => Marcacion::TIPO_SALIDA, 'fecha_hora' => $baseSalida]);
            }
        }
    }
}
