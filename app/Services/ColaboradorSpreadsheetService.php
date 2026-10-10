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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
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
        $preparador = app(ColaboradorSpreadsheetRowPreparer::class);

        foreach ($filas as $numero => $fila) {
            $resultado = $preparador->preparar($fila, $numero, $actor, $contrasenaInicial);

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

}
