<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Marcacion;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MarcacionSpreadsheetService
{
    /** @var list<string> */
    private const COLUMNAS = [
        'Fecha y hora', 'Colaborador', 'Código interno', 'Empresa', 'Área', 'Tipo',
        'Turno', 'Sucursal', 'Punto de venta', 'Retorno de refrigerio',
        'Diferencia de refrigerio (s)', 'Cobertura', 'IP de origen',
    ];

    public function exportar(Builder $query): StreamedResponse
    {
        $archivo = tempnam(sys_get_temp_dir(), 'marcaciones-');

        if ($archivo === false) {
            throw new RuntimeException('No se pudo generar el archivo.');
        }

        try {
            $writer = new XlsxWriter();
            $writer->openToFile($archivo);

            try {
                $writer->getCurrentSheet()->setName('Marcaciones');
                $writer->addRow(Row::fromValues(self::COLUMNAS));

                $query
                    ->with([
                        'colaborador:id,nombre_completo,codigo_empresa',
                        'empresa:id,nombre',
                        'area:id,nombre',
                        'turno:id,nombre',
                        'sucursal:id,nombre',
                        'puntoVenta:id,nombre',
                        'coberturaOperativa:id,estado',
                    ])
                    ->reorder('marcaciones.id')
                    ->chunkById(500, function ($marcaciones) use ($writer): void {
                        foreach ($marcaciones as $marcacion) {
                            $writer->addRow(Row::fromValues($this->fila($marcacion)));
                        }
                    }, 'marcaciones.id', 'id');
            } finally {
                $writer->close();
            }
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
        }, 'marcaciones-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /** @return list<string|int|null> */
    private function fila(Marcacion $marcacion): array
    {
        return [
            $marcacion->fecha_hora?->format('Y-m-d H:i:s'),
            $marcacion->colaborador?->nombre_completo,
            $marcacion->colaborador?->codigo_empresa,
            $marcacion->empresa?->nombre,
            $marcacion->area?->nombre,
            $this->etiquetaTipo($marcacion->tipo),
            $marcacion->turno?->nombre,
            $marcacion->sucursal?->nombre,
            $marcacion->puntoVenta?->nombre,
            $marcacion->refrigerio_retorno_esperado_en?->format('Y-m-d H:i:s'),
            $marcacion->refrigerio_diferencia_segundos,
            $marcacion->coberturaOperativa?->estado,
            $marcacion->ip_origen,
        ];
    }

    private function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            Marcacion::TIPO_ENTRADA => 'Entrada',
            Marcacion::TIPO_SALIDA => 'Salida',
            Marcacion::TIPO_SALIDA_REFRIGERIO => 'Salida a refrigerio',
            Marcacion::TIPO_REGRESO_REFRIGERIO => 'Regreso de refrigerio',
            default => $tipo,
        };
    }
}
