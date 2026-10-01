<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PuntoVentaService
{
    /** @param array<string, mixed> $data */
    public function crear(User $usuario, array $data): PuntoVenta
    {
        abort_unless($usuario->can('Create:PuntoVenta'), 403);

        return PuntoVenta::query()->create($this->datosValidados($data));
    }

    /** @param array<string, mixed> $data */
    public function actualizar(User $usuario, PuntoVenta $puntoVenta, array $data): PuntoVenta
    {
        abort_unless($usuario->can('update', $puntoVenta), 403);

        $datos = $this->datosValidados($data, $puntoVenta);

        if ((int) $datos['sucursal_id'] !== (int) $puntoVenta->sucursal_id && $puntoVenta->tieneHistorialOperativo()) {
            throw ValidationException::withMessages([
                'sucursal_id' => 'No puedes mover una estación con historial. Crea un nuevo punto de marcado en la sucursal destino.',
            ]);
        }

        $puntoVenta->update($datos);

        return $puntoVenta;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{sucursal_id: int, nombre: string, tipo: string, activo: bool}
     */
    private function datosValidados(array $data, ?PuntoVenta $ignorando = null): array
    {
        $sucursalId = filter_var($data['sucursal_id'] ?? null, FILTER_VALIDATE_INT);
        $nombre = preg_replace('/\s+/', ' ', trim((string) ($data['nombre'] ?? '')));
        $tipo = Str::lower(trim((string) ($data['tipo'] ?? '')));
        $activo = $this->booleano($data['activo'] ?? true);

        if ($sucursalId === false || $sucursalId < 1) {
            throw ValidationException::withMessages(['sucursal_id' => 'Selecciona una sucursal.']);
        }

        $sucursal = Sucursal::query()->find($sucursalId);
        if (! $sucursal) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal seleccionada no existe.']);
        }

        if (! $sucursal->activo && $activo) {
            throw ValidationException::withMessages(['sucursal_id' => 'No puedes activar una estación en una sucursal inactiva.']);
        }

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Ingresa el nombre del punto de marcado.']);
        }

        if (mb_strlen($nombre) > 255) {
            throw ValidationException::withMessages(['nombre' => 'El nombre no debe superar 255 caracteres.']);
        }

        if (! in_array($tipo, ['caja', 'produccion', 'oficina', 'almacen', 'otro'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Selecciona un tipo de punto válido.']);
        }

        $consulta = PuntoVenta::query()
            ->where('sucursal_id', $sucursalId)
            ->whereRaw('lower(nombre) = ?', [Str::lower($nombre)]);

        if ($ignorando) {
            $consulta->whereKeyNot($ignorando->id);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un punto de marcado con ese nombre en la sucursal.']);
        }

        return [
            'sucursal_id' => $sucursalId,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'activo' => $activo,
        ];
    }

    private function booleano(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
