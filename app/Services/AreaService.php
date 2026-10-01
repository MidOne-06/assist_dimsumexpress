<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AreaService
{
    /** @param array<string, mixed> $data */
    public function crear(User $usuario, array $data): Area
    {
        abort_unless($usuario->can('Create:Area'), 403);

        return Area::query()->create($this->datosValidados($data));
    }

    /** @param array<string, mixed> $data */
    public function actualizar(User $usuario, Area $area, array $data): Area
    {
        abort_unless($usuario->can('update', $area), 403);

        $area->update($this->datosValidados($data, $area));

        return $area;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nombre: string, codigo: string, activo: bool}
     */
    private function datosValidados(array $data, ?Area $ignorando = null): array
    {
        $nombre = preg_replace('/\s+/', ' ', trim((string) ($data['nombre'] ?? '')));
        $codigo = Str::upper(trim((string) ($data['codigo'] ?? '')));

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Ingresa el nombre del área.']);
        }

        if (mb_strlen($nombre) > 255) {
            throw ValidationException::withMessages(['nombre' => 'El nombre no debe superar 255 caracteres.']);
        }

        if (! preg_match('/^[A-Z0-9][A-Z0-9._-]{0,29}$/', $codigo)) {
            throw ValidationException::withMessages(['codigo' => 'Usa hasta 30 caracteres: letras, números, punto, guion o guion bajo.']);
        }

        $excluir = fn ($query) => $ignorando ? $query->whereKeyNot($ignorando->id) : $query;

        if ($excluir(Area::query()->whereRaw('lower(nombre) = ?', [Str::lower($nombre)]))->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un área con ese nombre.']);
        }

        if ($excluir(Area::query()->whereRaw('lower(codigo) = ?', [Str::lower($codigo)]))->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Ya existe un área con ese código.']);
        }

        return [
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activo' => (bool) ($data['activo'] ?? true),
        ];
    }
}
