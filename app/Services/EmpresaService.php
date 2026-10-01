<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empresa;
use App\Models\User;
use App\Rules\RucPeru;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmpresaService
{
    /** @param array<string, mixed> $data */
    public function crear(User $usuario, array $data): Empresa
    {
        abort_unless($usuario->can('Create:Empresa'), 403);

        return Empresa::query()->create($this->datosValidados($data));
    }

    /** @param array<string, mixed> $data */
    public function actualizar(User $usuario, Empresa $empresa, array $data): Empresa
    {
        abort_unless($usuario->can('update', $empresa), 403);

        $empresa->update($this->datosValidados($data, $empresa));

        return $empresa;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nombre: string, codigo: string, ruc: ?string, activo: bool}
     */
    private function datosValidados(array $data, ?Empresa $ignorando = null): array
    {
        $nombre = preg_replace('/\s+/', ' ', trim((string) ($data['nombre'] ?? '')));
        $codigo = Str::upper(trim((string) ($data['codigo'] ?? '')));
        $ruc = filled($data['ruc'] ?? null) ? preg_replace('/\D/', '', (string) $data['ruc']) : null;

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Ingresa la razón social.']);
        }

        if (mb_strlen($nombre) > 255) {
            throw ValidationException::withMessages(['nombre' => 'La razón social no debe superar 255 caracteres.']);
        }

        if (! preg_match('/^[A-Z0-9][A-Z0-9._-]{0,29}$/', $codigo)) {
            throw ValidationException::withMessages(['codigo' => 'Usa hasta 30 caracteres: letras, números, punto, guion o guion bajo.']);
        }

        if ($ruc !== null) {
            $reglaRuc = new RucPeru();
            $mensaje = null;
            $reglaRuc->validate('ruc', $ruc, function (string $error) use (&$mensaje): void {
                $mensaje = $error;
            });

            if ($mensaje) {
                throw ValidationException::withMessages(['ruc' => $mensaje]);
            }
        }

        $excluir = fn ($query) => $ignorando ? $query->whereKeyNot($ignorando->id) : $query;

        if ($excluir(Empresa::query()->whereRaw('lower(nombre) = ?', [Str::lower($nombre)]))->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una empresa con esa razón social.']);
        }

        if ($excluir(Empresa::query()->whereRaw('lower(codigo) = ?', [Str::lower($codigo)]))->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Ya existe una empresa con ese código.']);
        }

        if ($ruc !== null && $excluir(Empresa::query()->where('ruc', $ruc))->exists()) {
            throw ValidationException::withMessages(['ruc' => 'Ya existe una empresa con ese RUC.']);
        }

        return [
            'nombre' => $nombre,
            'codigo' => $codigo,
            'ruc' => $ruc,
            'activo' => (bool) ($data['activo'] ?? true),
        ];
    }
}
