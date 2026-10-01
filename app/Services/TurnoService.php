<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TurnoService
{
    /** @param array<string, mixed> $data */
    public function crear(User $usuario, array $data): Turno
    {
        abort_unless($usuario->can('Create:Turno'), 403);

        return Turno::query()->create($this->datosValidados($data));
    }

    /** @param array<string, mixed> $data */
    public function actualizar(User $usuario, Turno $turno, array $data): Turno
    {
        abort_unless($usuario->can('update', $turno), 403);

        return $turno->actualizarParaFuturo($this->datosValidados($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool|int|string|null>
     */
    private function datosValidados(array $data): array
    {
        $nombre = preg_replace('/\s+/', ' ', trim((string) ($data['nombre'] ?? '')));
        $inicio = $this->hora($data['hora_inicio'] ?? null, 'hora_inicio');
        $cruzaMedianoche = $this->booleano($data['cruza_medianoche'] ?? false);
        $soloEntrada = $this->booleano($data['solo_entrada'] ?? false);

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Ingresa el nombre del turno.']);
        }

        if (mb_strlen($nombre) > 255) {
            throw ValidationException::withMessages(['nombre' => 'El nombre no debe superar 255 caracteres.']);
        }

        $toleranciaEntrada = $this->entero($data['tolerancia_entrada_minutos'] ?? 10, 'tolerancia_entrada_minutos', 0, 120);

        if ($soloEntrada) {
            return [
                'nombre' => $nombre,
                'hora_inicio' => $inicio,
                'hora_fin' => null,
                'cruza_medianoche' => false,
                'tolerancia_entrada_minutos' => $toleranciaEntrada,
                'tolerancia_salida_minutos' => 0,
                'solo_entrada' => true,
                'incluye_refrigerio' => false,
                'refrigerio_minutos' => 0,
                'horas_efectivas_objetivo_minutos' => 0,
                'horas_efectivas_jornada_completa_minutos' => null,
                'activo' => $this->booleano($data['activo'] ?? true),
            ];
        }

        $fin = $this->hora($data['hora_fin'] ?? null, 'hora_fin');
        if (! $cruzaMedianoche && $fin <= $inicio) {
            throw ValidationException::withMessages(['cruza_medianoche' => 'Activa Cruza medianoche si la salida corresponde al día siguiente.']);
        }

        if ($cruzaMedianoche && $fin >= $inicio) {
            throw ValidationException::withMessages(['hora_fin' => 'En un turno nocturno, la hora de fin debe ser anterior a la hora de inicio.']);
        }

        $toleranciaSalida = $this->entero($data['tolerancia_salida_minutos'] ?? 10, 'tolerancia_salida_minutos', 0, 120);

        $incluyeRefrigerio = $this->booleano($data['incluye_refrigerio'] ?? true);
        $refrigerio = $incluyeRefrigerio
            ? $this->entero($data['refrigerio_minutos'] ?? 60, 'refrigerio_minutos', 1, 240)
            : 0;
        $objetivo = $this->entero($data['horas_efectivas_objetivo_minutos'] ?? 480, 'horas_efectivas_objetivo_minutos', 1, 1440);
        $objetivoJornadaCompleta = filled($data['horas_efectivas_jornada_completa_minutos'] ?? null)
            ? $this->entero($data['horas_efectivas_jornada_completa_minutos'], 'horas_efectivas_jornada_completa_minutos', $objetivo + 1, 1440)
            : null;

        return [
            'nombre' => $nombre,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'cruza_medianoche' => $cruzaMedianoche,
            'tolerancia_entrada_minutos' => $toleranciaEntrada,
            'tolerancia_salida_minutos' => $toleranciaSalida,
            'solo_entrada' => false,
            'incluye_refrigerio' => $incluyeRefrigerio,
            'refrigerio_minutos' => $refrigerio,
            'horas_efectivas_objetivo_minutos' => $objetivo,
            'horas_efectivas_jornada_completa_minutos' => $objetivoJornadaCompleta,
            'activo' => $this->booleano($data['activo'] ?? true),
        ];
    }

    private function hora(mixed $valor, string $campo): string
    {
        $hora = trim((string) $valor);

        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $hora)) {
            throw ValidationException::withMessages([$campo => 'Ingresa una hora válida.']);
        }

        return substr($hora, 0, 5);
    }

    private function entero(mixed $valor, string $campo, int $minimo, int $maximo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT);

        if ($entero === false || $entero < $minimo || $entero > $maximo) {
            throw ValidationException::withMessages([$campo => "Ingresa un valor entre {$minimo} y {$maximo}."]);
        }

        return $entero;
    }

    private function booleano(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
