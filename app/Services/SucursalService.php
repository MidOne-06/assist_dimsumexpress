<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SucursalService
{
    /** @param array<string, mixed> $data */
    public function crear(User $usuario, array $data): Sucursal
    {
        abort_unless($usuario->can('Create:Sucursal'), 403);

        return Sucursal::query()->create($this->datosValidados($data));
    }

    /** @param array<string, mixed> $data */
    public function actualizar(User $usuario, Sucursal $sucursal, array $data): Sucursal
    {
        abort_unless($usuario->can('update', $sucursal), 403);

        $datos = $this->datosValidados($data, $sucursal);

        if ($datos['tipo'] === 'planta' && $sucursal->puntosVenta()->exists()) {
            throw ValidationException::withMessages([
                'tipo' => 'Una sucursal con puntos de marcado debe mantenerse como tienda.',
            ]);
        }

        $sucursal->update($datos);

        return $sucursal;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nombre: string, tipo: string, direccion: ?string, activo: bool}
     */
    private function datosValidados(array $data, ?Sucursal $ignorando = null): array
    {
        $nombre = preg_replace('/\s+/', ' ', trim((string) ($data['nombre'] ?? '')));
        $tipo = Str::lower(trim((string) ($data['tipo'] ?? '')));
        $direccion = filled($data['direccion'] ?? null)
            ? preg_replace('/\s+/', ' ', trim((string) $data['direccion']))
            : null;

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Ingresa el nombre de la sucursal.']);
        }

        if (mb_strlen($nombre) > 255) {
            throw ValidationException::withMessages(['nombre' => 'El nombre no debe superar 255 caracteres.']);
        }

        if (! in_array($tipo, ['tienda', 'planta'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Selecciona Tienda o Planta.']);
        }

        if ($direccion !== null && mb_strlen($direccion) > 255) {
            throw ValidationException::withMessages(['direccion' => 'La dirección no debe superar 255 caracteres.']);
        }

        $nombreNormalizado = $this->normalizarNombre($nombre);
        $consulta = Sucursal::query()->select(['id', 'nombre']);

        if ($ignorando) {
            $consulta->whereKeyNot($ignorando->id);
        }

        if ($consulta->get()->contains(
            fn (Sucursal $existente): bool => $this->normalizarNombre($existente->nombre) === $nombreNormalizado,
        )) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe una sucursal con ese nombre, incluso considerando guiones, espacios y mayúsculas.',
            ]);
        }

        return [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'direccion' => $direccion,
            'activo' => (bool) ($data['activo'] ?? true),
        ];
    }

    /** Clave de comparación: evita duplicados por formato, no por identidad. */
    private function normalizarNombre(string $nombre): string
    {
        $nombreAscii = Str::lower(Str::ascii(trim($nombre)));

        return (string) preg_replace(
            '/[^a-z0-9]/',
            '',
            $nombreAscii,
        );
    }
}
