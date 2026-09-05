<?php

namespace App\Support;

use ZipArchive;

/**
 * Generador de libros .xlsx NATIVOS (hoja de cálculo estructurada) sin
 * dependencias externas — usa ZipArchive (ext-zip, incluido en PHP).
 *
 * Reemplaza a los CSV "disfrazados" de Excel: al abrir el archivo en Excel
 * (con cualquier configuración regional) cada campo —texto, montos y
 * usuarios— cae en su propia columna, sin depender del delimitador de la
 * máquina. Los montos se guardan como NÚMEROS (sumables) con formato de
 * miles y 2 decimales; los códigos/correlativos con ceros a la izquierda
 * se conservan como texto.
 *
 * Uso:
 *   return Xlsx::descargar(
 *       ['Fecha', 'Concepto', 'Monto'],           // cabeceras
 *       [['03/09/2026', 'Venta #1', 120.50]],     // filas (montos como float)
 *       'reporte',                                // nombre base del archivo
 *       [2 => '#,##0.00'],                        // columnas numéricas
 *   );
 */
class Xlsx
{
    /** Formato monetario por defecto para las columnas numéricas. */
    private const MONEDA = '#,##0.00';

    /**
     * @param  array<int, string>  $headers  Nombres de columna (fila 0).
     * @param  array<int, array<int, mixed>>  $filas  Datos; montos como int/float.
     * @param  array<int, bool|string>  $numCols  Índices de columna que son
     *         numéricos (bool true = moneda #,##0.00, o un formato Excel a medida).
     */
    public static function binario(array $headers, array $filas, array $numCols = [], string $hoja = 'Reporte'): string
    {
        $hoja = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $hoja) ?: 'Reporte';
        $hoja = mb_substr($hoja, 0, 31);

        // Cabecera + datos en una sola matriz.
        $filas = array_values($filas);
        array_unshift($filas, array_values($headers));

        $totales = 0;
        foreach ($filas as $f) {
            $n = count($f);
            if ($n > $totales) $totales = $n;
        }
        $nCols = max($totales, count($headers), 1);
        $nFilas = count($filas);

        // Formatos de número por columna.
        $numFmtPorCol = [];
        foreach ($numCols as $col => $formato) {
            $numFmtPorCol[(int) $col] = $formato === true || $formato === null ? self::MONEDA : (string) $formato;
        }

        // Mapa formato => id de estilo (0 = texto, 1 = cabecera, 2+ = numéricos).
        $estiloNum = [];
        $siguiente = 2;
        foreach (array_unique(array_values($numFmtPorCol)) as $fmt) {
            $estiloNum[$fmt] = $siguiente++;
        }

        // ── XML de la hoja ──────────────────────────────────────────────
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';

        // Anchos aproximados (mín. 8, máx. 50).
        $xml .= '<cols>';
        for ($c = 0; $c < $nCols; $c++) {
            $max = 0;
            foreach ($filas as $f) {
                $v = $f[$c] ?? '';
                if ($v === null || $v === '') continue;
                $len = mb_strlen((string) $v);
                $max = max($max, $len);
            }
            $wch = max(8, min($max + 2, 50));
            $xml .= "<col min=\"" . ($c + 1) . "\" max=\"" . ($c + 1) . "\" width=\"{$wch}\" customWidth=\"1\"/>";
        }
        $xml .= '</cols>';

        $xml .= '<sheetData>';

        // Cabecera (negrita).
        $xml .= '<row r="1">';
        foreach ($headers as $c => $texto) {
            $col = self::colName($c);
            $xml .= '<c r="' . $col . '1" s="1" t="inlineStr"><is><t xml:space="preserve">' . self::esc($texto) . '</t></is></c>';
        }
        $xml .= '</row>';

        foreach ($filas as $ri => $fila) {
            $r = $ri + 1;
            $xml .= '<row r="' . $r . '">';
            foreach ($fila as $c => $valor) {
                if ($valor === null || $valor === '') continue;
                $col = self::colName($c);

                if (isset($numFmtPorCol[$c]) && is_numeric($valor)) {
                    $s = $estiloNum[$numFmtPorCol[$c]];
                    $xml .= '<c r="' . $col . $r . '" s="' . $s . '"><v>' . self::numero($valor) . '</v></c>';
                } elseif (is_int($valor) || is_float($valor)) {
                    $xml .= '<c r="' . $col . $r . '"><v>' . self::numero($valor) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $col . $r . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $valor) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        // ── Paquetes OPC (zip) ─────────────────────────────────────────
        $fmtXml = '';
        foreach ($estiloNum as $fmt => $id) {
            $numFmtId = 164 + array_search($fmt, array_keys($estiloNum), true);
            $fmtXml .= '<numFmt numFmtId="' . $numFmtId . '" formatCode="' . self::esc($fmt) . '"/>';
        }

        $cellXfs = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>';
        foreach ($estiloNum as $fmt => $sId) {
            $numFmtId = 164 + array_search($fmt, array_keys($estiloNum), true);
            $cellXfs .= '<xf numFmtId="' . $numFmtId
                . '" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>';
        }

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="' . count($estiloNum) . '">' . $fmtXml . '</numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . (2 + count($estiloNum)) . '">' . $cellXfs . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $workbook .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::esc($hoja) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $workbookRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal para el Excel.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                throw new \RuntimeException('No se pudo abrir el ZipArchive para el Excel.');
            }
            $zip->addFromString('[Content_Types].xml', $contentTypes);
            $zip->addFromString('_rels/.rels', $rels);
            $zip->addFromString('xl/workbook.xml', $workbook);
            $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
            $zip->addFromString('xl/styles.xml', $styles);
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            $zip->close();

            $binario = file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        return $binario === false ? '' : $binario;
    }

    /** Descarga como .xlsx con nombre "base_AAAAMMDD_HHMMSS.xlsx". */
    public static function descargar(array $headers, array $filas, string $nombreBase, array $numCols = [], string $hoja = 'Reporte')
    {
        $contenido = static::binario($headers, $filas, $numCols, $hoja);

        return response($contenido, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^a-z0-9\-_]/i', '_', $nombreBase)
                . '_' . date('Ymd_His') . '.xlsx"',
        ]);
    }

    /** Letra de columna de Excel (A, B, ..., Z, AA, AB...). */
    private static function colName(int $index): string
    {
        $letra = '';
        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letra = chr(65 + ($i % 26)) . $letra;
        }
        return $letra;
    }

    /** Escapa texto a XML (entidades, sin romper tildes/ñ). */
    private static function esc(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Número a string sin separador de decimales dependiente de locale. */
    private static function numero(mixed $valor): string
    {
        if (is_int($valor)) return (string) $valor;
        $str = (string) $valor; // PHP castea float con '.' siempre (locale-independiente)
        return str_contains(strtolower($str), 'e') ? sprintf('%.10F', (float) $valor) : $str;
    }
}
