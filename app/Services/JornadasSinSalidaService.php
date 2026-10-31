<?php

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Marcacion;
use App\Support\JornadaMarcacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Detecta jornadas con ingreso y sin salida cuya vigencia ya venció. Solo
 * lee: no crea marcaciones ni modifica resúmenes; la corrección es una
 * decisión de un supervisor, no del sistema.
 */
final class JornadasSinSalidaService
{
    /**
     * @return Collection<int, array{asignacion: AsignacionTurno, entrada: Marcacion, ultima: Marcacion, vencio_en: Carbon}>
     */
    public function listar(Carbon $desde, Carbon $hasta, ?Carbon $referencia = null): Collection
    {
        $referencia ??= now();

        return AsignacionTurno::query()
            ->with(['turno', 'colaborador.sucursal'])
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->whereHas('turno', fn ($query) => $query
                ->where('solo_entrada', false)
                ->where('jornada_abierta', false)
                ->whereNotNull('hora_fin'))
            ->orderBy('fecha')
            ->get()
            ->map(function (AsignacionTurno $asignacion) use ($referencia): ?array {
                $vencioEn = JornadaMarcacion::finVigencia($asignacion);

                if ($referencia->lte($vencioEn)) {
                    return null;
                }

                $marcaciones = JornadaMarcacion::marcaciones($asignacion->colaborador, $asignacion);
                $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
                $ultima = $marcaciones->last();

                if (! $entrada || $ultima?->tipo === Marcacion::TIPO_SALIDA) {
                    return null;
                }

                return [
                    'asignacion' => $asignacion,
                    'entrada' => $entrada,
                    'ultima' => $ultima,
                    'vencio_en' => $vencioEn,
                ];
            })
            ->filter()
            ->values();
    }
}
