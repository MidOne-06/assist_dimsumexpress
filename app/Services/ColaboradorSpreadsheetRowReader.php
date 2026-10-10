<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/** Lee filas normalizadas de la plantilla CSV/XLSX de colaboradores. */
final class ColaboradorSpreadsheetRowReader
{
    /** @return array{0: array<int, array<string, mixed>>, 1: list<string>} */
    public function leer(string $ruta): array
    {
        $reader = match (Str::lower(pathinfo($ruta, PATHINFO_EXTENSION))) {
            'xlsx' => new XlsxReader(),
            'csv' => new CsvReader(),
            default => null,
        };

        if ($reader === null) {
            return [[], ['Archivo no válido. Use XLSX o CSV.']];
        }

        $reader->open($ruta);
        $encabezados = null;
        $filas = [];
        $errores = [];
        $numero = 0;

        try {
            foreach ($reader->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $fila) {
                    ++$numero;
                    $valores = $fila->toArray();
                    if ($encabezados === null) {
                        $encabezados = array_map(fn ($valor): string => $this->normalizarEncabezado((string) $valor), $valores);
                        $faltantes = array_diff(['nombre_completo', 'documento_identidad', 'correo', 'empresa_codigo', 'area_codigo', 'sucursal'], $encabezados);
                        if ($faltantes !== []) {
                            return [[], ['Faltan columnas requeridas: '.implode(', ', $faltantes).'.']];
                        }
                        continue;
                    }
                    if ($fila->isEmpty()) {
                        continue;
                    }
                    if (count($filas) >= 5000) {
                        $errores[] = 'El archivo supera el límite de 5000 filas.';
                        break 2;
                    }
                    $filas[$numero] = array_combine($encabezados, array_pad(array_slice($valores, 0, count($encabezados)), count($encabezados), null));
                }
                break;
            }
        } finally {
            $reader->close();
        }

        if ($encabezados === null) {
            $errores[] = 'El archivo no contiene encabezados.';
        }

        return [$filas, $errores];
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = Str::of($valor)->ascii()->lower()->replace([' ', '-'], '_')->toString();

        return match ($valor) {
            'codigo_empresa' => 'codigo_interno', 'correo_electronico', 'email' => 'correo',
            'empresa' => 'empresa_codigo', 'area' => 'area_codigo', 'caja', 'punto_de_venta' => 'punto_venta',
            default => $valor,
        };
    }
}
