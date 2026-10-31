<?php

namespace App\Services;

use App\Models\AsignacionTurno;
use App\Models\Marcacion;
use App\Models\User;
use App\Support\AlcanceSupervisor;
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
    public function listar(
        Carbon $desde,
        Carbon $hasta,
        ?Carbon $referencia = null,
        ?User $usuario = null,
        ?int $sucursalId = null,
        ?int $colaboradorId = null,
    ): Collection {
        $referencia ??= now();

        $consulta = AsignacionTurno::query()
            ->with(['turno', 'colaborador.sucursal', 'colaborador.puntoVenta'])
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->whereHas('turno', fn ($query) => $query
                ->where('solo_entrada', false)
                ->where('jornada_abierta', false)
                ->whereNotNull('hora_fin'))
            ->when($colaboradorId, fn ($q) => $q->where('colaborador_id', $colaboradorId))
            ->when($sucursalId, fn ($q) => $q->whereHas('colaborador', fn ($sq) => $sq->where('sucursal_id', $sucursalId)))
            ->when($usuario && ! $usuario->hasRole('super_admin'), function ($q) use ($usuario) {
                $sucursalesPermitidas = AlcanceSupervisor::sucursalIds($usuario);
                $q->whereHas('colaborador', fn ($sq) => $sq->whereIn('sucursal_id', $sucursalesPermitidas));
            })
            ->orderByDesc('fecha');

        return $consulta->get()
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

    /**
     * @return array<int, int>
     */
    public function listarIds(
        Carbon $desde,
        Carbon $hasta,
        ?Carbon $referencia = null,
        ?User $usuario = null,
        ?int $sucursalId = null,
        ?int $colaboradorId = null,
    ): array {
        return $this->listar($desde, $hasta, $referencia, $usuario, $sucursalId, $colaboradorId)
            ->pluck('asignacion.id')
            ->all();
    }

    public function conteo(
        Carbon $desde,
        Carbon $hasta,
        ?Carbon $referencia = null,
        ?User $usuario = null,
    ): int {
        return $this->listar($desde, $hasta, $referencia, $usuario)->count();
    }
}
