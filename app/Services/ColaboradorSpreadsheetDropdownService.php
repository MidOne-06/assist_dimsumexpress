<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Agrega al archivo XLSX las listas que Excel muestra en la plantilla de
 * colaboradores. Es infraestructura de hoja de cálculo y no modifica datos
 * de colaboradores ni su lógica de importación.
 */
final class ColaboradorSpreadsheetDropdownService
{
    /** @param array{empresas: string, areas: string, sucursales: string, cajas: string, estados: string} $rangos */
    public function agregar(string $archivo, array $rangos): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivo) !== true) {
            throw new RuntimeException('No se pudo preparar la plantilla.');
        }

        try {
            $this->actualizarLibro($zip, $rangos);
            $this->actualizarHojaColaboradores($zip);
        } finally {
            $zip->close();
        }
    }

    /** @param array{empresas: string, areas: string, sucursales: string, cajas: string, estados: string} $rangos */
    private function actualizarLibro(ZipArchive $zip, array $rangos): void
    {
        $documento = $this->cargarXml($zip, 'xl/workbook.xml');
        $xpath = new DOMXPath($documento);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        /** @var DOMElement|null $hojaCatalogos */
        $hojaCatalogos = $xpath->query('//x:sheet[@name="Catálogos"]')->item(0);
        $hojaCatalogos?->setAttribute('state', 'hidden');

        $espacio = $documento->documentElement?->namespaceURI;
        $nombres = $documento->createElementNS($espacio, 'definedNames');
        foreach (['empresas', 'areas', 'sucursales', 'cajas', 'estados'] as $nombre) {
            $this->agregarNombreDefinido($documento, $nombres, $nombre, $rangos[$nombre]);
        }

        /** @var DOMElement|null $hojas */
        $hojas = $xpath->query('//x:sheets')->item(0);
        if ($hojas === null) {
            throw new RuntimeException('La plantilla no contiene hojas.');
        }
        $hojas->parentNode?->insertBefore($nombres, $hojas->nextSibling);

        $this->guardarXml($zip, 'xl/workbook.xml', $documento);
    }

    private function agregarNombreDefinido(DOMDocument $documento, DOMElement $nombres, string $nombre, string $rango): void
    {
        $elemento = $documento->createElementNS($documento->documentElement?->namespaceURI, 'definedName');
        $elemento->setAttribute('name', $nombre);
        $elemento->nodeValue = $rango;
        $nombres->appendChild($elemento);
    }

    private function actualizarHojaColaboradores(ZipArchive $zip): void
    {
        $documento = $this->cargarXml($zip, 'xl/worksheets/sheet1.xml');
        $xpath = new DOMXPath($documento);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $espacio = $documento->documentElement?->namespaceURI;
        $listas = [
            ['E2:E5001', '=empresas'],
            ['F2:F5001', '=areas'],
            ['G2:G5001', '=sucursales'],
            ['H2:H5001', '=cajas'],
            ['K2:K5001', '=estados'],
        ];

        $validaciones = $documento->createElementNS($espacio, 'dataValidations');
        $validaciones->setAttribute('count', (string) count($listas));
        foreach ($listas as [$rango, $formula]) {
            $validacion = $documento->createElementNS($espacio, 'dataValidation');
            $validacion->setAttribute('type', 'list');
            $validacion->setAttribute('allowBlank', '1');
            $validacion->setAttribute('showErrorMessage', '1');
            $validacion->setAttribute('errorStyle', 'stop');
            $validacion->setAttribute('errorTitle', 'Valor no válido');
            $validacion->setAttribute('error', 'Seleccione una opción de la lista.');
            $validacion->setAttribute('showDropDown', '0');
            $validacion->setAttribute('sqref', $rango);
            $formulaUno = $documento->createElementNS($espacio, 'formula1');
            $formulaUno->appendChild($documento->createTextNode($formula));
            $validacion->appendChild($formulaUno);
            $validaciones->appendChild($validacion);
        }

        // En XLSX las validaciones deben declararse antes de dibujos y
        // elementos heredados; de otra forma Excel repara el libro.
        /** @var DOMElement|null $ancla */
        $ancla = $xpath->query('//x:drawing | //x:legacyDrawing | //x:legacyDrawingHF')->item(0);
        /** @var DOMElement|null $margenes */
        $margenes = $xpath->query('//x:pageMargins')->item(0);
        if ($ancla !== null) {
            $ancla->parentNode?->insertBefore($validaciones, $ancla);
        } elseif ($margenes !== null) {
            $margenes->parentNode?->insertBefore($validaciones, $margenes);
        } else {
            $documento->documentElement?->appendChild($validaciones);
        }

        $this->guardarXml($zip, 'xl/worksheets/sheet1.xml', $documento);
    }

    private function cargarXml(ZipArchive $zip, string $ruta): DOMDocument
    {
        $contenido = $zip->getFromName($ruta);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer la plantilla.');
        }

        $documento = new DOMDocument();
        $documento->preserveWhiteSpace = false;
        if (! $documento->loadXML($contenido)) {
            throw new RuntimeException('No se pudo procesar la plantilla.');
        }

        return $documento;
    }

    private function guardarXml(ZipArchive $zip, string $ruta, DOMDocument $documento): void
    {
        if ($zip->addFromString($ruta, $documento->saveXML()) === false) {
            throw new RuntimeException('No se pudo guardar la plantilla.');
        }
    }
}
