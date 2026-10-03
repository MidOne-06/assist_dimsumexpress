<?php

namespace App\Http\Controllers;

use App\Models\AsignacionTurno;
use App\Services\AparienciaSistemaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HorarioColaboradorController extends Controller
{
    /**
     * El colaborador siempre sale de la sesión autenticada, nunca de un
     * parámetro de la URL -- mismo patrón que MarcacionController, para que
     * nadie pueda ver el horario de otro colaborador cambiando un id.
     */
    public function show(Request $request): View
    {
        abort_unless($request->user()?->can('View:MiHorario'), 403);

        $colaborador = $request->user()->colaborador;

        if (! $colaborador) {
            return view('marcacion.error', [
                'mensaje' => 'No podemos mostrar tu horario en esta cuenta. Consulta con tu supervisor.',
            ]);
        }

        $mes = $request->query('mes');
        $mes = ($mes && preg_match('/^\d{4}-\d{2}$/', $mes)) ? $mes : now()->format('Y-m');

        $inicio = Carbon::parse("{$mes}-01")->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $hoy = now()->toDateString();

        $asignaciones = AsignacionTurno::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->with('turno')
            ->get()
            ->keyBy(fn (AsignacionTurno $a) => $a->fecha->toDateString());

        $dias = [];
        for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay()) {
            $dias[] = [
                'fecha' => $dia->copy(),
                'asignacion' => $asignaciones->get($dia->toDateString()),
                'hoy' => $dia->toDateString() === $hoy,
            ];
        }

        // En móvil un mes completo en una sola columna obliga a recorrer una
        // pantalla demasiado larga. Se muestran semanas de siete días, sin
        // perder el contexto del mes ni cargar datos de otro colaborador.
        $hoyEnMes = collect($dias)->contains(fn (array $dia) => $dia['hoy']);
        $semanaHoy = $hoyEnMes ? intdiv(now()->day - 1, 7) + 1 : null;
        $totalSemanas = (int) ceil(count($dias) / 7);
        // Al abrir el mes actual, el colaborador ve de inmediato la semana
        // donde está hoy; los demás meses siguen empezando en su primera semana.
        $semanaSolicitada = $request->integer('semana', $semanaHoy ?? 1);
        $semana = min(max($semanaSolicitada, 1), $totalSemanas);
        $diasPagina = array_slice($dias, ($semana - 1) * 7, 7);

        return view('horario.show', [
            'apariencia' => app(AparienciaSistemaService::class),
            'colaborador' => $colaborador,
            'mes' => $mes,
            'mesLabel' => ucfirst($inicio->locale('es')->translatedFormat('F Y')),
            'mesAnterior' => $inicio->copy()->subMonthNoOverflow()->format('Y-m'),
            'mesSiguiente' => $inicio->copy()->addMonthNoOverflow()->format('Y-m'),
            'dias' => $diasPagina,
            'hoyEnMes' => $hoyEnMes,
            'semanaHoy' => $semanaHoy,
            'semana' => $semana,
            'totalSemanas' => $totalSemanas,
        ]);
    }
}
