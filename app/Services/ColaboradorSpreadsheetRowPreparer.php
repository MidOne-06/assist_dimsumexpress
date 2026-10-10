<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Area;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;

final class ColaboradorSpreadsheetRowPreparer
{
    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>|string
     */
    public function preparar(array $fila, int $numero, User $actor, ?string $contrasenaInicial): array|string
    {
        $nombre = $this->texto($fila['nombre_completo'] ?? null);
        $documento = $this->texto($fila['documento_identidad'] ?? null);
        $correo = Str::lower($this->texto($fila['correo'] ?? null));
        $empresaCodigo = Str::upper($this->texto($fila['empresa_codigo'] ?? null));
        $areaCodigo = Str::upper($this->texto($fila['area_codigo'] ?? null));
        $sucursalNombre = $this->texto($fila['sucursal'] ?? null);
        $puntoVentaNombre = $this->texto($fila['punto_venta'] ?? null);

        if ($nombre === '' || $documento === '' || $correo === '' || $empresaCodigo === '' || $areaCodigo === '' || $sucursalNombre === '') {
            return "Fila {$numero}: complete nombre, documento, correo, empresa, área y sucursal.";
        }

        if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return "Fila {$numero}: correo no válido.";
        }

        $empresa = Empresa::query()->whereRaw('lower(codigo) = ?', [Str::lower($empresaCodigo)])->where('activo', true)->first();
        $area = Area::query()->whereRaw('lower(codigo) = ?', [Str::lower($areaCodigo)])->where('activo', true)->first();
        $sucursales = Sucursal::query()->whereRaw('lower(nombre) = ?', [Str::lower($sucursalNombre)])->where('activo', true)->get();

        if ($empresa === null || $area === null || $sucursales->count() !== 1) {
            return "Fila {$numero}: empresa, área o sucursal no válida.";
        }

        $sucursal = $sucursales->first();
        if (! AlcanceSupervisor::puedeGestionarSucursal($actor, $sucursal->id)) {
            return "Fila {$numero}: no tiene acceso a la sucursal indicada.";
        }

        $puntoVenta = null;
        if ($puntoVentaNombre !== '') {
            $puntos = PuntoVenta::query()
                ->where('sucursal_id', $sucursal->id)
                ->whereRaw('lower(nombre) = ?', [Str::lower($puntoVentaNombre)])
                ->where('activo', true)
                ->get();

            if ($puntos->count() !== 1) {
                return "Fila {$numero}: punto de venta no válido para la sucursal indicada.";
            }

            $puntoVenta = $puntos->first();
        }

        $fechaIngreso = $this->fecha($fila['fecha_ingreso'] ?? null);
        $hayFecha = isset($fila['fecha_ingreso'])
            && ($fila['fecha_ingreso'] instanceof DateTimeInterface || $this->texto($fila['fecha_ingreso']) !== '');
        if ($hayFecha && $fechaIngreso === null) {
            return "Fila {$numero}: fecha de ingreso no válida.";
        }

        $activo = $this->booleano($fila['activo'] ?? 'si');
        if ($activo === null) {
            return "Fila {$numero}: el estado debe ser si o no.";
        }

        $existente = Colaborador::query()->with('user')->where('documento_identidad', $documento)->first();
        if ($existente !== null && ! AlcanceSupervisor::puedeGestionarSucursal($actor, $existente->sucursal_id)) {
            return "Fila {$numero}: no tiene acceso al colaborador indicado.";
        }

        $correoEnUso = User::query()->whereRaw('lower(email) = ?', [$correo]);
        if ($existente !== null) {
            $correoEnUso->where('id', '!=', $existente->user_id);
        }

        if ($correoEnUso->exists()) {
            return "Fila {$numero}: el correo ya pertenece a otra cuenta.";
        }

        if ($existente === null && blank($contrasenaInicial)) {
            return "Fila {$numero}: indique la contraseña inicial para crear nuevas cuentas.";
        }

        return [
            'nombre_completo' => $nombre,
            'documento_identidad' => $documento,
            'email' => $correo,
            'password' => $existente === null ? $contrasenaInicial : null,
            'empresa_id' => $empresa->id,
            'area_id' => $area->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta?->id,
            'cargo' => $this->texto($fila['cargo'] ?? null) ?: null,
            'fecha_ingreso' => $fechaIngreso?->toDateString(),
            'activo' => $activo,
        ];
    }

    private function texto(mixed $valor): string
    {
        return trim((string) $valor);
    }

    private function fecha(mixed $valor): ?Carbon
    {
        if ($valor instanceof DateTimeInterface) {
            return Carbon::instance($valor);
        }

        $valor = $this->texto($valor);
        if ($valor === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $valor)->startOfDay();
            } catch (\Throwable) {
                // Se prueba el siguiente formato permitido.
            }
        }

        return null;
    }

    private function booleano(mixed $valor): ?bool
    {
        return match (Str::lower($this->texto($valor))) {
            '1', 'si', 'sí', 'true', 'activo' => true,
            '0', 'no', 'false', 'inactivo' => false,
            default => null,
        };
    }
}
