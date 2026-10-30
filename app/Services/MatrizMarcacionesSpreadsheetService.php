<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Marcacion;
use App\Models\User;
use App\Support\AlcanceSupervisor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MatrizMarcacionesSpreadsheetService
{
    /** @var list<string> */
    private const COLUMNAS_DIARIAS = [
        'Fecha',
        'Colaborador',
        'Documento',
        'Cargo',
        'Local',
        'Punto de venta',
        'Ingreso',
        'Salida a refrigerio',
        'Regreso de refrigerio',
        'Salida',
        'Total marcaciones',
    ];

    /** @var list<string> */
    private const COLUMNAS_DETALLE = [
        'Fecha',
        'Hora',
        'Colaborador',
        'Documento',
        'Cargo',
        'Local',
        'Punto de venta',
        'Tipo de marcación',
    ];

    public function exportar(
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?int $sucursalId = null,
        ?User $user = null
    ): StreamedResponse {
        $archivo = tempnam(sys_get_temp_dir(), 'matriz-marcaciones-');

        if ($archivo === false) {
            throw new RuntimeException('No se pudo generar el archivo temporal para la exportación.');
        }

        $sucursalesPermitidas = AlcanceSupervisor::sucursalIdsHistoricos($user);

        if ($sucursalId !== null && ! in_array($sucursalId, $sucursalesPermitidas, true)) {
            abort(403, 'No tiene autorización para exportar información de este local.');
        }

        $sucursalesFiltro = $sucursalId !== null ? [$sucursalId] : $sucursalesPermitidas;

        $inicio = $fechaInicio->copy()->startOfDay();
        $fin = $fechaFin->copy()->endOfDay();

        $query = Marcacion::query()
            ->with([
                'colaborador:id,nombre_completo,documento_identidad,cargo',
                'sucursal:id,nombre',
                'puntoVenta:id,nombre',
            ])
            ->where('fecha_hora', '>=', $inicio)
            ->where('fecha_hora', '<=', $fin)
            ->whereIn('sucursal_id', $sucursalesFiltro)
            ->orderBy('fecha_hora')
            ->orderBy('colaborador_id');

        try {
            $writer = new XlsxWriter();
            $writer->openToFile($archivo);

            try {
                $marcaciones = $query->get();

                // 1. Hoja principal: Asistencia diaria (consolidado limpio sin textos explicativos)
                $hojaDiaria = $writer->getCurrentSheet();
                $hojaDiaria->setName('Asistencia diaria');
                $hojaDiaria->setColumnWidth(13, 1);
                $hojaDiaria->setColumnWidth(28, 2);
                $hojaDiaria->setColumnWidth(15, 3);
                $hojaDiaria->setColumnWidth(22, 4);
                $hojaDiaria->setColumnWidth(22, 5);
                $hojaDiaria->setColumnWidth(18, 6);
                $hojaDiaria->setColumnWidth(12, 7, 8, 9, 10);
                $hojaDiaria->setColumnWidth(16, 11);
                $hojaDiaria->setSheetView((new SheetView())->setFreezeRow(2));
                $writer->addRow(Row::fromValues(self::COLUMNAS_DIARIAS, $this->estiloCabecera()));

                $gruposDiarios = $marcaciones->groupBy(
                    fn (Marcacion $m): string => $m->colaborador_id.'_'.$m->fecha_hora->toDateString()
                );

                foreach ($gruposDiarios as $grupo) {
                    /** @var Collection<int, Marcacion> $grupo */
                    $primera = $grupo->first();
                    $colaborador = $primera->colaborador;
                    $sucursal = $primera->sucursal;
                    $puntoVenta = $primera->puntoVenta;
                    $fecha = Carbon::parse($primera->fecha_hora->toDateString());

                    $entradas = $grupo->where('tipo', Marcacion::TIPO_ENTRADA)->pluck('fecha_hora');
                    $salidasRefrig = $grupo->where('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO)->pluck('fecha_hora');
                    $regresosRefrig = $grupo->where('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO)->pluck('fecha_hora');
                    $salidas = $grupo->where('tipo', Marcacion::TIPO_SALIDA)->pluck('fecha_hora');

                    $formatearHoras = fn (Collection $fechas): string => $fechas->isEmpty()
                        ? '-'
                        : $fechas->map(fn (Carbon $f): string => $f->format('H:i'))->implode(', ');

                    $writer->addRow(Row::fromValuesWithStyles([
                        $fecha,
                        $colaborador?->nombre_completo ?? '-',
                        $colaborador?->documento_identidad ?? '-',
                        $colaborador?->cargo ?? '-',
                        $sucursal?->nombre ?? '-',
                        $puntoVenta?->nombre ?? '-',
                        $formatearHoras($entradas),
                        $formatearHoras($salidasRefrig),
                        $formatearHoras($regresosRefrig),
                        $formatearHoras($salidas),
                        $grupo->count(),
                    ], null, [
                        0 => $this->estiloFecha(),
                        6 => $this->estiloCentro(),
                        7 => $this->estiloCentro(),
                        8 => $this->estiloCentro(),
                        9 => $this->estiloCentro(),
                        10 => $this->estiloCentro(),
                    ]));
                }

                $hojaDiaria->setAutoFilter(new AutoFilter(0, 1, count(self::COLUMNAS_DIARIAS) - 1, $hojaDiaria->getWrittenRowCount()));

                // 2. Hoja secundaria: Detalle cronológico de marcaciones
                $hojaDetalle = $writer->addNewSheetAndMakeItCurrent();
                $hojaDetalle->setName('Detalle marcaciones');
                $hojaDetalle->setColumnWidth(13, 1);
                $hojaDetalle->setColumnWidth(10, 2);
                $hojaDetalle->setColumnWidth(28, 3);
                $hojaDetalle->setColumnWidth(15, 4);
                $hojaDetalle->setColumnWidth(22, 5);
                $hojaDetalle->setColumnWidth(22, 6);
                $hojaDetalle->setColumnWidth(18, 7);
                $hojaDetalle->setColumnWidth(22, 8);
                $hojaDetalle->setSheetView((new SheetView())->setFreezeRow(2));
                $writer->addRow(Row::fromValues(self::COLUMNAS_DETALLE, $this->estiloCabecera()));

                foreach ($marcaciones as $marcacion) {
                    $writer->addRow(Row::fromValuesWithStyles([
                        Carbon::parse($marcacion->fecha_hora->toDateString()),
                        $marcacion->fecha_hora->format('H:i'),
                        $marcacion->colaborador?->nombre_completo ?? '-',
                        $marcacion->colaborador?->documento_identidad ?? '-',
                        $marcacion->colaborador?->cargo ?? '-',
                        $marcacion->sucursal?->nombre ?? '-',
                        $marcacion->puntoVenta?->nombre ?? '-',
                        MatrizMarcacionesService::etiquetaTipo($marcacion->tipo),
                    ], null, [
                        0 => $this->estiloFecha(),
                        1 => $this->estiloCentro(),
                    ]));
                }

                $hojaDetalle->setAutoFilter(new AutoFilter(0, 1, count(self::COLUMNAS_DETALLE) - 1, $hojaDetalle->getWrittenRowCount()));
            } finally {
                $writer->close();
            }
        } catch (\Throwable $exception) {
            @unlink($archivo);

            throw $exception;
        }

        $nombreDescarga = 'matriz-marcaciones-'.$fechaInicio->format('Ymd').'-'.$fechaFin->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($archivo): void {
            try {
                readfile($archivo);
            } finally {
                @unlink($archivo);
            }
        }, $nombreDescarga, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function estiloCabecera(): Style
    {
        return (new Style())
            ->setFontBold()
            ->setFontColor(Color::rgb(31, 41, 55))
            ->setBackgroundColor(Color::rgb(229, 231, 235))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    private function estiloFecha(): Style
    {
        return (new Style())
            ->setFormat('dd/mm/yyyy')
            ->setCellAlignment(CellAlignment::CENTER);
    }

    private function estiloCentro(): Style
    {
        return (new Style())
            ->setCellAlignment(CellAlignment::CENTER);
    }
}
