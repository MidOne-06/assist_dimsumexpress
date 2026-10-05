<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VisitaSupervisor;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VisitaSupervisorSpreadsheetService
{
    /** @var list<string> */
    private const COLUMNAS = [
        'Estado', 'Fecha', 'Supervisor', 'Local', 'Estación de ingreso',
        'Ingreso', 'Estación de salida', 'Salida', 'Duración',
        'Regularizada por', 'Motivo de regularización',
    ];

    public function exportar(Builder $query): StreamedResponse
    {
        $archivo = tempnam(sys_get_temp_dir(), 'visitas-supervisor-');

        if ($archivo === false) {
            throw new RuntimeException('No se pudo generar el archivo.');
        }

        try {
            $writer = new XlsxWriter();
            $writer->openToFile($archivo);

            try {
                $writer->getCurrentSheet()->setName('Visitas supervisor');
                $writer->addRow(Row::fromValues(self::COLUMNAS));

                $query
                    ->with([
                        'supervisor:id,name',
                        'sucursal:id,nombre',
                        'puntoVentaIngreso:id,nombre',
                        'puntoVentaSalida:id,nombre',
                        'regularizadaPor:id,name',
                    ])
                    ->reorder('visitas_supervisor.id')
                    ->chunkById(500, function ($visitas) use ($writer): void {
                        foreach ($visitas as $visita) {
                            $writer->addRow(Row::fromValues($this->fila($visita)));
                        }
                    }, 'visitas_supervisor.id', 'id');
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
        }, 'visitas-supervision-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /** @return list<string|null> */
    private function fila(VisitaSupervisor $visita): array
    {
        return [
            $this->etiquetaEstado($visita),
            $visita->fecha?->format('Y-m-d'),
            $visita->supervisor?->name,
            $visita->sucursal?->nombre,
            $visita->puntoVentaIngreso?->nombre,
            $visita->ingreso_en?->format('Y-m-d H:i:s'),
            $visita->puntoVentaSalida?->nombre,
            $visita->salida_en?->format('Y-m-d H:i:s'),
            $this->formatoDuracion($visita->duracionEnSegundos()),
            $visita->regularizadaPor?->name,
            $visita->regularizacion_motivo,
        ];
    }

    private function etiquetaEstado(VisitaSupervisor $visita): string
    {
        if ($visita->estado === VisitaSupervisor::EN_CURSO && $visita->fecha?->isBefore(today())) {
            return 'Pendiente';
        }

        return match ($visita->estado) {
            VisitaSupervisor::EN_CURSO => 'En curso',
            VisitaSupervisor::FINALIZADA => 'Finalizada',
            VisitaSupervisor::REGULARIZADA => 'Regularizada',
            default => 'Histórica',
        };
    }

    private function formatoDuracion(?int $segundos): ?string
    {
        return $segundos === null ? null : sprintf('%d h %02d min', intdiv($segundos, 3600), intdiv($segundos % 3600, 60));
    }
}
