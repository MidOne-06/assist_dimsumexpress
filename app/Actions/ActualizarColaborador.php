<?php

namespace App\Actions;

use App\Models\Colaborador;
use App\Support\CodigoInternoColaborador;
use Illuminate\Support\Facades\DB;

final class ActualizarColaborador
{
    /** @param array<string, mixed> $data */
    public function handle(Colaborador $colaborador, array $data, ?int $actorId = null): Colaborador
    {
        return DB::transaction(function () use ($colaborador, $data, $actorId): Colaborador {
            $user = $colaborador->user;

            $user?->update([
                'name' => $data['nombre_completo'],
                'email' => $data['email'],
            ]);

            if (filled($data['password'] ?? null)) {
                $user?->restablecerContrasena($data['password'], $actorId);
            }

            unset($data['email'], $data['password'], $data['codigo_empresa']);

            if (empty($data['punto_venta_id'])) {
                $data['punto_venta_id'] = null;
            }

            if (blank($colaborador->codigo_empresa) || (int) $data['empresa_id'] !== (int) $colaborador->empresa_id) {
                $data['codigo_empresa'] = CodigoInternoColaborador::siguienteParaEmpresa((int) $data['empresa_id']);
            }

            $colaborador->update($data);

            return $colaborador;
        });
    }
}
