<?php

namespace App\Http\Controllers;

use App\Models\Marcacion;
use App\Services\AparienciaSistemaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vista personal y deliberadamente mínima: muestra únicamente las
 * marcaciones efectivamente persistidas del colaborador autenticado durante
 * el día actual. No reutiliza el resumen de jornada porque este incluye
 * cálculos y contexto administrativo que no corresponden a la app móvil.
 */
class MisMarcacionesHoyController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless($request->user()?->can('View:MiHorario'), 403);

        $colaborador = $request->user()->colaborador;

        if (! $colaborador) {
            return view('marcacion.error', [
                'mensaje' => 'No podemos mostrar tus marcaciones en esta cuenta. Consulta con tu supervisor.',
            ]);
        }

        $hoy = now();
        $marcaciones = Marcacion::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereBetween('fecha_hora', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get()
            ->map(fn (Marcacion $marcacion): array => $this->presentar($marcacion))
            ->all();

        return view('marcacion.mis-marcaciones-hoy', [
            'apariencia' => app(AparienciaSistemaService::class),
            'fecha' => ucfirst($hoy->locale('es')->translatedFormat('l d \d\e F')),
            'marcaciones' => $marcaciones,
        ]);
    }

    /** @return array{etiqueta:string,hora:string,color:string,icono:string} */
    private function presentar(Marcacion $marcacion): array
    {
        $presentacion = match ($marcacion->tipo) {
            Marcacion::TIPO_ENTRADA => [
                'etiqueta' => 'Ingreso de turno',
                'color' => 'success',
                'icono' => 'heroicon-o-arrow-right-on-rectangle',
            ],
            Marcacion::TIPO_SALIDA_REFRIGERIO => [
                'etiqueta' => 'Salida a refrigerio',
                'color' => 'warning',
                'icono' => 'heroicon-o-clock',
            ],
            Marcacion::TIPO_REGRESO_REFRIGERIO => [
                'etiqueta' => 'Ingreso de refrigerio',
                'color' => 'info',
                'icono' => 'heroicon-o-arrow-right-circle',
            ],
            Marcacion::TIPO_SALIDA => [
                'etiqueta' => 'Salida de turno',
                'color' => 'danger',
                'icono' => 'heroicon-o-arrow-left-on-rectangle',
            ],
            default => [
                'etiqueta' => 'Marcación registrada',
                'color' => 'gray',
                'icono' => 'heroicon-o-check-circle',
            ],
        };

        return $presentacion + ['hora' => $marcacion->fecha_hora->format('H:i:s')];
    }
}
