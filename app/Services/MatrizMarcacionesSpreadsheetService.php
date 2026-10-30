<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Colaborador;
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
    private const COLUMNAS_SIN_MARCACION = [
        'Colaborador',
        'Cargo',
        'Local',
        'Punto de venta',
        'Estado',
        'Última marcación registrada',
    ];

    /** @var list<string> */
    private const COLUMNAS_DETALLE = [
        'Fecha',
        'Hora',
        'Colaborador',
        'Cargo',
        'Local',
        'Punto de venta',
        'Tipo de marcación',
    ];

    /**
     * @param  array<int, int>|int|null  $sucursalIds
     */
    public function exportar(
        Carbon $fechaInicio,
        Carbon $fechaFin,
        array|int|null $sucursalIds = null,
        ?User $user = null
    ): StreamedResponse {
        $archivo = tempnam(sys_get_temp_dir(), 'matriz-marcaciones-');

        if ($archivo === false) {
            throw new RuntimeException('No se pudo generar el archivo temporal para la exportación.');
        }

        $sucursalesPermitidas = AlcanceSupervisor::sucursalIdsHistoricos($user);

        if ($sucursalIds !== null) {
            $idsSolicitados = is_array($sucursalIds)
                ? array_values(array_filter(array_map('intval', $sucursalIds)))
                : [(int) $sucursalIds];

            if (! empty($idsSolicitados)) {
                $noPermitidos = array_diff($idsSolicitados, $sucursalesPermitidas);
                if (! empty($noPermitidos)) {
                    abort(403, 'No tiene autorización para exportar información de uno o más locales seleccionados.');
                }
                $sucursalesFiltro = $idsSolicitados;
            } else {
                $sucursalesFiltro = $sucursalesPermitidas;
            }
        } else {
            $sucursalesFiltro = $sucursalesPermitidas;
        }

        $inicio = $fechaInicio->copy()->startOfDay();
        $fin = $fechaFin->copy()->endOfDay();

        $query = Marcacion::query()
            ->with([
                'colaborador:id,nombre_completo,cargo',
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
                $hojaDiaria->setColumnWidth(22, 3);
                $hojaDiaria->setColumnWidth(22, 4);
                $hojaDiaria->setColumnWidth(18, 5);
                $hojaDiaria->setColumnWidth(12, 6, 7, 8, 9);
                $hojaDiaria->setColumnWidth(16, 10);
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
                        5 => $this->estiloCentro(),
                        6 => $this->estiloCentro(),
                        7 => $this->estiloCentro(),
                        8 => $this->estiloCentro(),
                        9 => $this->estiloCentro(),
                    ]));
                }

                $hojaDiaria->setAutoFilter(new AutoFilter(0, 1, count(self::COLUMNAS_DIARIAS) - 1, $hojaDiaria->getWrittenRowCount()));

                // 2. Hoja secundaria: Colaboradores sin marcaciones en el período
                $hojaSinMarcas = $writer->addNewSheetAndMakeItCurrent();
                $hojaSinMarcas->setName('Sin marcaciones');
                $hojaSinMarcas->setColumnWidth(28, 1);
                $hojaSinMarcas->setColumnWidth(22, 2);
                $hojaSinMarcas->setColumnWidth(22, 3);
                $hojaSinMarcas->setColumnWidth(18, 4);
                $hojaSinMarcas->setColumnWidth(12, 5);
                $hojaSinMarcas->setColumnWidth(26, 6);
                $hojaSinMarcas->setSheetView((new SheetView())->setFreezeRow(2));
                $writer->addRow(Row::fromValues(self::COLUMNAS_SIN_MARCACION, $this->estiloCabecera()));

                $colaboradoresSinMarcas = Colaborador::query()
                    ->with([
                        'sucursal:id,nombre',
                        'puntoVenta:id,nombre',
                    ])
                    ->withMax('marcaciones', 'fecha_hora')
                    ->whereIn('sucursal_id', $sucursalesFiltro)
                    ->whereDoesntHave('marcaciones', function ($q) use ($inicio, $fin) {
                        $q->where('fecha_hora', '>=', $inicio)
                          ->where('fecha_hora', '<=', $fin);
                    })
                    ->orderByDesc('activo')
                    ->orderBy('nombre_completo')
                    ->get();

                foreach ($colaboradoresSinMarcas as $colab) {
                    $ultimaMarca = $colab->marcaciones_max_fecha_hora;
                    $ultimaMarcaTexto = $ultimaMarca
                        ? Carbon::parse($ultimaMarca)->format('d/m/Y H:i')
                        : 'Sin registros históricos';

                    $writer->addRow(Row::fromValuesWithStyles([
                        $colab->nombre_completo,
                        $colab->cargo ?? '-',
                        $colab->sucursal?->nombre ?? '-',
                        $colab->puntoVenta?->nombre ?? '-',
                        $colab->activo ? 'Activo' : 'Inactivo',
                        $ultimaMarcaTexto,
                    ], null, [
                        4 => $this->estiloCentro(),
                        5 => $this->estiloCentro(),
                    ]));
                }

                $hojaSinMarcas->setAutoFilter(new AutoFilter(0, 1, count(self::COLUMNAS_SIN_MARCACION) - 1, $hojaSinMarcas->getWrittenRowCount()));

                // 3. Hoja terciaria: Detalle cronológico de marcaciones
                $hojaDetalle = $writer->addNewSheetAndMakeItCurrent();
                $hojaDetalle->setName('Detalle marcaciones');
                $hojaDetalle->setColumnWidth(13, 1);
                $hojaDetalle->setColumnWidth(10, 2);
                $hojaDetalle->setColumnWidth(28, 3);
                $hojaDetalle->setColumnWidth(22, 4);
                $hojaDetalle->setColumnWidth(22, 5);
                $hojaDetalle->setColumnWidth(18, 6);
                $hojaDetalle->setColumnWidth(22, 7);
                $hojaDetalle->setSheetView((new SheetView())->setFreezeRow(2));
                $writer->addRow(Row::fromValues(self::COLUMNAS_DETALLE, $this->estiloCabecera()));

                foreach ($marcaciones as $marcacion) {
                    $writer->addRow(Row::fromValuesWithStyles([
                        Carbon::parse($marcacion->fecha_hora->toDateString()),
                        $marcacion->fecha_hora->format('H:i'),
                        $marcacion->colaborador?->nombre_completo ?? '-',
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
