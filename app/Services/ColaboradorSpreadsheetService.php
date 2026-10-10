<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\ActualizarColaborador;
use App\Actions\CrearColaborador;
use App\Models\Area;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ColaboradorSpreadsheetService
{
    /** @var list<string> */
    private const COLUMNAS = [
        'codigo_interno', 'nombre_completo', 'documento_identidad', 'correo',
        'empresa_codigo', 'area_codigo', 'sucursal', 'caja', 'cargo',
        'fecha_ingreso', 'activo',
    ];

    public function exportar(Builder $query): StreamedResponse
    {
        return $this->descargarConCatalogos('colaboradores-'.now()->format('Ymd-His').'.xlsx', function (XlsxWriter $writer) use ($query): void {
            $writer->addRow(Row::fromValues(self::COLUMNAS));

            $query->with(['user:id,email', 'empresa:id,codigo', 'area:id,codigo', 'sucursal:id,nombre', 'puntoVenta:id,nombre'])
                ->orderBy('colaboradores.nombre_completo')
                ->chunkById(500, function ($colaboradores) use ($writer): void {
                    foreach ($colaboradores as $colaborador) {
                        $writer->addRow(Row::fromValues($this->filaExportacion($colaborador)));
                    }
                }, 'colaboradores.id', 'id');
        });
    }

    public function plantilla(): StreamedResponse
    {
        return $this->descargarConCatalogos('plantilla-colaboradores-'.now()->format('Ymd-His').'.xlsx', function (XlsxWriter $writer): void {
            $writer->addRow(Row::fromValues(self::COLUMNAS));
        });
    }

    private function descargarConCatalogos(string $nombre, callable $escribir): StreamedResponse
    {
        $archivo = tempnam(sys_get_temp_dir(), 'colaboradores-');
        if ($archivo === false) {
            throw new RuntimeException('No se pudo generar el archivo.');
        }

        try {
            $catalogos = $this->catalogosPlantilla();
            $writer = new XlsxWriter();
            $writer->openToFile($archivo);

            try {
                $writer->getCurrentSheet()->setName('Colaboradores');
                $escribir($writer);

                $writer->addNewSheetAndMakeItCurrent()->setName('Catálogos');
                $writer->addRow(Row::fromValues($catalogos['encabezados']));

                foreach ($catalogos['filas'] as $fila) {
                    $writer->addRow(Row::fromValues($fila));
                }
            } finally {
                $writer->close();
            }

            app(ColaboradorSpreadsheetDropdownService::class)->agregar($archivo, $catalogos['rangos']);
        } catch (\Throwable $exception) {
            @unlink($archivo);

            throw $exception;
        }

        return response()->streamDownload(function () use ($archivo): void {
            try {
                readfile($archivo);
            } finally {
                @unlink($archivo);
            }
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * @return array{
     *     encabezados: list<string>,
     *     filas: list<list<string|null>>,
     *     rangos: array{empresas: string, areas: string, sucursales: string, cajas: string, estados: string}
     * }
     */
    private function catalogosPlantilla(): array
    {
        $empresas = Empresa::query()->where('activo', true)->orderBy('codigo')->pluck('codigo')->map(fn ($valor): string => (string) $valor)->all();
        $areas = Area::query()->where('activo', true)->orderBy('codigo')->pluck('codigo')->map(fn ($valor): string => (string) $valor)->all();
        $sucursales = Sucursal::query()->where('activo', true)->orderBy('nombre')->pluck('nombre')->map(fn ($valor): string => (string) $valor)->all();
        $cajas = PuntoVenta::query()->where('activo', true)->orderBy('nombre')->pluck('nombre')->unique()->values()->map(fn ($valor): string => (string) $valor)->all();
        $encabezados = ['Empresas', 'Áreas', 'Sucursales', 'Cajas', 'Estados'];

        $filas = [];
        $cantidad = max(1, count($empresas), count($areas), count($sucursales), count($cajas), 2);
        for ($indice = 0; $indice < $cantidad; ++$indice) {
            $fila = [
                $empresas[$indice] ?? null,
                $areas[$indice] ?? null,
                $sucursales[$indice] ?? null,
                $cajas[$indice] ?? null,
                ['si', 'no'][$indice] ?? null,
            ];

            $filas[] = $fila;
        }

        return [
            'encabezados' => $encabezados,
            'filas' => $filas,
            'rangos' => [
                'empresas' => $this->rangoCatalogo('A', count($empresas)),
                'areas' => $this->rangoCatalogo('B', count($areas)),
                'sucursales' => $this->rangoCatalogo('C', count($sucursales)),
                'cajas' => $this->rangoCatalogo('D', count($cajas)),
                'estados' => $this->rangoCatalogo('E', 2),
            ],
        ];
    }

    private function rangoCatalogo(string $columna, int $cantidad): string
    {
        $ultimaFila = max(2, $cantidad + 1);

        return "'Catálogos'!\${$columna}\$2:\${$columna}\${$ultimaFila}";
    }

    /**
     * @return array{creados: int, actualizados: int, errores: list<string>}
     */
    public function importar(string $ruta, User $actor, ?string $contrasenaInicial): array
    {
        [$filas, $errores] = app(ColaboradorSpreadsheetRowReader::class)->leer($ruta);
        $preparadas = [];
        $documentos = [];
        $correos = [];

        foreach ($filas as $numero => $fila) {
            $resultado = $this->prepararFila($fila, $numero, $actor, $contrasenaInicial);

            if (is_string($resultado)) {
                $errores[] = $resultado;
                continue;
            }

            $documento = $resultado['documento_identidad'];
            $correo = $resultado['email'];

            if (isset($documentos[$documento])) {
                $errores[] = "Fila {$numero}: documento repetido en el archivo.";
                continue;
            }

            if (isset($correos[$correo])) {
                $errores[] = "Fila {$numero}: correo repetido en el archivo.";
                continue;
            }

            $documentos[$documento] = true;
            $correos[$correo] = true;
            $preparadas[] = $resultado;
        }

        if ($errores !== []) {
            return ['creados' => 0, 'actualizados' => 0, 'errores' => $errores];
        }

        return DB::transaction(function () use ($preparadas, $actor): array {
            $creados = 0;
            $actualizados = 0;

            foreach ($preparadas as $fila) {
                /** @var Colaborador|null $colaborador */
                $colaborador = Colaborador::query()
                    ->with('user')
                    ->where('documento_identidad', $fila['documento_identidad'])
                    ->lockForUpdate()
                    ->first();

                if ($colaborador === null) {
                    app(CrearColaborador::class)->handle($fila, $actor);
                    ++$creados;
                    continue;
                }

                $activoAnterior = $colaborador->activo;
                $activoNuevo = $fila['activo'];
                $datos = $fila;
                $datos['activo'] = $activoAnterior;
                unset($datos['password']);

                app(ActualizarColaborador::class)->handle($colaborador, $datos, $actor);

                if ($activoAnterior && ! $activoNuevo) {
                    $colaborador->desactivarAcceso();
                } elseif (! $activoAnterior && $activoNuevo) {
                    $colaborador->reactivarAcceso();
                }

                ++$actualizados;
            }

            return ['creados' => $creados, 'actualizados' => $actualizados, 'errores' => []];
        });
    }

    /** @return list<string> */
    private function filaExportacion(Colaborador $colaborador): array
    {
        return [
            $colaborador->codigo_empresa,
            $colaborador->nombre_completo,
            $colaborador->documento_identidad,
            $colaborador->user?->email,
            $colaborador->empresa?->codigo,
            $colaborador->area?->codigo,
            $colaborador->sucursal?->nombre,
            $colaborador->puntoVenta?->nombre,
            $colaborador->cargo,
            $colaborador->fecha_ingreso?->format('Y-m-d'),
            $colaborador->activo ? 'si' : 'no',
        ];
    }

    /** @param array<string, mixed> $fila */
    private function prepararFila(array $fila, int $numero, User $actor, ?string $contrasenaInicial): array|string
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
