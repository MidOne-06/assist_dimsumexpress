<?php

namespace App\Services;

use App\Models\Colaborador;
use App\Models\EnlaceAccesoColaborador;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EnlacesAccesoColaboradorService
{
    /** @var array<int, string> */
    public const VIGENCIAS = [15 => '15 minutos', 30 => '30 minutos', 60 => '1 hora'];

    /** @return array{enlace: EnlaceAccesoColaborador, url: string} */
    public function generar(Colaborador $colaborador, User $actor, int $vigenciaMinutos): array
    {
        if (! array_key_exists($vigenciaMinutos, self::VIGENCIAS)) {
            throw ValidationException::withMessages(['vigencia_minutos' => 'Selecciona una vigencia válida.']);
        }

        return DB::transaction(function () use ($colaborador, $actor, $vigenciaMinutos): array {
            $colaborador = Colaborador::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($colaborador->id);

            if (! $colaborador->activo || ! $colaborador->user?->estaActivoParaAcceso()) {
                throw ValidationException::withMessages(['vigencia_minutos' => 'El colaborador no tiene una cuenta activa.']);
            }

            if (! $colaborador->user->can('Registrar:Marcacion')) {
                throw ValidationException::withMessages(['vigencia_minutos' => 'La cuenta no tiene acceso operativo para marcar asistencia.']);
            }

            EnlaceAccesoColaborador::query()
                ->where('colaborador_id', $colaborador->id)
                ->whereNull('usado_en')
                ->whereNull('revocado_en')
                ->update(['revocado_en' => now()]);

            $token = Str::random(64);
            $enlace = EnlaceAccesoColaborador::create([
                'colaborador_id' => $colaborador->id,
                'user_id' => $colaborador->user_id,
                'generado_por_id' => $actor->id,
                'token_hash' => hash('sha256', $token),
                'expira_en' => now()->addMinutes($vigenciaMinutos),
                'generado_desde_ip' => request()?->ip(),
            ]);

            return [
                'enlace' => $enlace,
                'url' => route('enlace-acceso.show', ['token' => $token]),
            ];
        });
    }

    public function buscar(string $token): ?EnlaceAccesoColaborador
    {
        return EnlaceAccesoColaborador::query()
            ->with(['colaborador', 'user'])
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    public function revocarActivos(Colaborador $colaborador): int
    {
        return $colaborador->enlacesAcceso()
            ->whereNull('usado_en')
            ->whereNull('revocado_en')
            ->update(['revocado_en' => now()]);
    }
}
