<?php

namespace App\Support;

use App\Models\Colaborador;
use App\Models\Empresa;

final class CodigoInternoColaborador
{
    public static function siguienteParaEmpresa(int $empresaId): string
    {
        $empresa = Empresa::query()->lockForUpdate()->findOrFail($empresaId);
        $prefijo = mb_strtoupper($empresa->codigo);
        $ultimoConsecutivo = 0;

        Colaborador::query()
            ->where('empresa_id', $empresa->id)
            ->where('codigo_empresa', 'like', "{$prefijo}-%")
            ->pluck('codigo_empresa')
            ->each(function (?string $codigo) use ($prefijo, &$ultimoConsecutivo): void {
                if (! preg_match('/^' . preg_quote($prefijo, '/') . '-(\d+)$/', (string) $codigo, $coincidencias)) {
                    return;
                }

                $ultimoConsecutivo = max($ultimoConsecutivo, (int) $coincidencias[1]);
            });

        return sprintf('%s-%04d', $prefijo, $ultimoConsecutivo + 1);
    }
}
