<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AsignacionTurno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CalendarioTurnosSpreadsheetService
{
    /** @var list<string> */
    private const COLUMNAS = [
        'Fecha', 'Local', 'Punto de venta', 'Colaborador', 'Código interno',
        'Empresa', 'Área', 'Turno', 'Inicio', 'Fin', 'Observación', 'Asignado por',
    ];

    public function exportar(Builder $query): StreamedResponse
    {
        $archivo = tempnam(sys_get_temp_dir(), 'calendario-turnos-');

        if ($archivo === false) {
            throw new RuntimeException('No se pudo generar el archivo.');
        }

        try {
            $writer = new XlsxWriter();
            $writer->openToFile($archivo);

            try {
                $hoja = $writer->getCurrentSheet();
                $hoja->setName('Calendario de turnos');
                $hoja->setColumnWidth(12, 1);
                $hoja->setColumnWidth(22, 2);
                $hoja->setColumnWidth(18, 3);
                $hoja->setColumnWidth(28, 4);
                $hoja->setColumnWidth(16, 5, 6, 7, 8, 12);
                $hoja->setColumnWidth(10, 9, 10);
                $hoja->setColumnWidth(32, 11);
                $hoja->setSheetView(
                    (new SheetView())
                        ->setFreezeRow(2)
                        ->setFreezeColumn('B'),
                );
                $writer->addRow(Row::fromValues(self::COLUMNAS, $this->estiloCabecera()));

                $query
                    ->with([
                        'colaborador.sucursal:id,nombre',
                        'colaborador.puntoVenta:id,nombre',
                        'colaborador.empresa:id,nombre',
                        'colaborador.area:id,nombre',
                        'turno:id,nombre,hora_inicio,hora_fin',
                        'asignadoPor:id,name',
                    ])
                    ->reorder()
                    ->orderBy('fecha')
                    ->orderBy('id')
                    ->cursor()
                    ->each(fn (AsignacionTurno $asignacion) => $writer->addRow(
                        Row::fromValuesWithStyles(
                            $this->fila($asignacion),
                            null,
                            [
                                0 => $this->estiloFecha(),
                                8 => $this->estiloHora(),
                                9 => $this->estiloHora(),
                                10 => $this->estiloObservacion(),
                            ],
                        ),
                    ));

                $hoja->setAutoFilter(new AutoFilter(0, 1, count(self::COLUMNAS) - 1, $hoja->getWrittenRowCount()));
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
        }, 'calendario-turnos-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /** @return list<\DateTimeInterface|string|null> */
    private function fila(AsignacionTurno $asignacion): array
    {
        return [
            $asignacion->fecha,
            $asignacion->colaborador?->sucursal?->nombre,
            $asignacion->colaborador?->puntoVenta?->nombre,
            $asignacion->colaborador?->nombre_completo,
            $asignacion->colaborador?->codigo_empresa,
            $asignacion->colaborador?->empresa?->nombre,
            $asignacion->colaborador?->area?->nombre,
            $asignacion->turno?->nombre,
            $asignacion->turno?->hora_inicio ? Carbon::parse('2000-01-01 ' . $asignacion->turno->hora_inicio) : null,
            $asignacion->turno?->hora_fin ? Carbon::parse('2000-01-01 ' . $asignacion->turno->hora_fin) : null,
            $asignacion->observacion,
            $asignacion->asignadoPor?->name,
        ];
    }

    private function estiloCabecera(): Style
    {
        return (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(217, 119, 6))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText();
    }

    private function estiloFecha(): Style
    {
        return (new Style())->setFormat('dd/mm/yyyy');
    }

    private function estiloHora(): Style
    {
        return (new Style())->setFormat('hh:mm');
    }

    private function estiloObservacion(): Style
    {
        return (new Style())
            ->setShouldWrapText()
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);
    }
}
