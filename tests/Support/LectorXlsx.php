<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Lee el texto de una hoja .xlsx para poder afirmar sobre ella en un test.
 *
 * ─── POR QUÉ HACE FALTA ────────────────────────────────────────────────────────
 *
 * Las exportaciones del sistema pasaron de CSV a Excel, y un .xlsx es un ZIP con
 * XML dentro: buscar `"Producto A"` en el cuerpo de la respuesta no encuentra nada
 * aunque el dato esté. Dos pruebas de stock llevaban rotas desde ese cambio por
 * exactamente eso, comprobando un formato que el sistema ya no devuelve.
 *
 * Esto NO valida el formato del archivo: solo saca el texto para poder preguntar si
 * un valor salió o no. Para lo que necesitan estas pruebas es suficiente, y evita
 * meter una librería entera de lectura de hojas de cálculo.
 */
final class LectorXlsx
{
    /**
     * Todo el texto de la hoja, listo para buscar dentro.
     *
     * Las celdas de texto de un .xlsx no viven en la hoja sino en `sharedStrings`,
     * así que se concatenan las dos partes: sin eso, los nombres de producto no
     * aparecerían y las cifras sí, que es justo la mitad equivocada.
     */
    public static function texto(string $contenido): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $contenido);

        $zip = new ZipArchive();

        if ($zip->open($tmp) !== true) {
            unlink($tmp);

            throw new \RuntimeException(
                'La respuesta no es un archivo .xlsx válido. ¿Cambió el formato de exportación?',
            );
        }

        $texto = ($zip->getFromName('xl/sharedStrings.xml') ?: '')
            . ($zip->getFromName('xl/worksheets/sheet1.xml') ?: '');

        $zip->close();
        unlink($tmp);

        // Fuera las etiquetas XML: lo que interesa es el contenido de las celdas.
        return html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
