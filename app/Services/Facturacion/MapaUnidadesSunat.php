<?php

namespace App\Services\Facturacion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Traduce las unidades de medida libres del POS al Catálogo 03 de SUNAT (gap G9).
 *
 * Existe como colaborador separado — y no como método de VentaAComprobante — porque
 * el mapper tiene que ser PURO y testeable sin base de datos. Aquí queda encerrada
 * la única lectura de BD que el mapeo necesita, y en los tests se precarga en memoria.
 *
 * La carga es perezosa y memoizada por empresa: mapear 300 ventas históricas con
 * `pos:simular-emision --todas` hace UNA consulta, no 300.
 */
class MapaUnidadesSunat
{
    /** Fallback universal del Catálogo 03: "unidad (bienes)". Nunca hace fallar una emisión. */
    public const DEFAULT = 'NIU';

    /**
     * Semilla de arranque para el match por texto: lo que una ferretería peruana
     * teclea de verdad. Sirve mientras la empresa no configure su propio mapeo, de
     * modo que el día 1 no salgan todos los ítems como NIU.
     *
     * @var array<string, string>
     */
    public const SEMILLA = [
        'UND' => 'NIU', 'UNID' => 'NIU', 'UNIDAD' => 'NIU', 'U' => 'NIU', 'PZA' => 'NIU', 'PIEZA' => 'NIU',
        'KG' => 'KGM', 'KGS' => 'KGM', 'KILO' => 'KGM', 'KILOGRAMO' => 'KGM',
        'G' => 'GRM', 'GR' => 'GRM', 'GRAMO' => 'GRM',
        'L' => 'LTR', 'LT' => 'LTR', 'LTS' => 'LTR', 'LITRO' => 'LTR',
        'GAL' => 'GLL', 'GALON' => 'GLL',
        'M' => 'MTR', 'MT' => 'MTR', 'METRO' => 'MTR',
        'M2' => 'MTK', 'M3' => 'MTQ',
        'CAJA' => 'BX', 'CJA' => 'BX', 'BOX' => 'BX',
        'BOL' => 'BG', 'BOLSA' => 'BG', 'SACO' => 'BG',
        'PAQ' => 'PK', 'PQT' => 'PK', 'PAQUETE' => 'PK', 'PACK' => 'PK',
        'CIENTO' => 'CEN', 'DOC' => 'DZN', 'DOCENA' => 'DZN',

        // 'SRV' es la que MÁS importa de esta línea, y faltaba: es la abreviatura
        // que `ProductoController::crearPresentacionDefaultServicio()` crea SOLA
        // para cada servicio dado de alta desde la interfaz. Sin ella, todo
        // servicio del sistema se declaraba como NIU ("unidad") en vez de ZZ
        // ("servicio"): no rompe la emisión, pero describe mal la operación en
        // todos los comprobantes de una empresa que vive de vender servicios.
        'SERV' => 'ZZ', 'SERVICIO' => 'ZZ', 'SRV' => 'ZZ',

        // Unidades de tiempo: licencias, suscripciones, soporte y capacitación se
        // facturan por mes, hora, día o año, y ninguna estaba contemplada. Una
        // licencia anual declarada como "unidad" pierde justo el dato que la
        // define.
        'MES' => 'MON', 'MESES' => 'MON', 'MENSUAL' => 'MON',
        'HORA' => 'HUR', 'HORAS' => 'HUR', 'HR' => 'HUR', 'HRS' => 'HUR',
        'DIA' => 'DAY', 'DIAS' => 'DAY',
        'ANIO' => 'ANN', 'AÑO' => 'ANN', 'ANUAL' => 'ANN',
    ];

    /**
     * Cache por empresa: ['ids' => [unidad_medida_id => codigo], 'abrevs' => [ABREV => codigo]].
     *
     * @var array<int, array{ids: array<int, string>, abrevs: array<string, string>}>
     */
    private array $cache = [];

    /**
     * Inyecta un mapeo en memoria sin tocar la BD. Es lo que usan los tests unitarios
     * (y lo que permite que el mapper no dependa de que la migración esté corrida).
     *
     * @param array<int, string>    $porId          unidad_medida_id => código SUNAT
     * @param array<string, string> $porAbreviatura abreviatura      => código SUNAT
     */
    public function precargar(int $empresaId, array $porId = [], array $porAbreviatura = []): void
    {
        $this->cache[$empresaId] = [
            'ids'    => $porId,
            'abrevs' => array_change_key_case($porAbreviatura, CASE_UPPER),
        ];
    }

    /**
     * Resuelve el código SUNAT de un ítem. Nunca lanza: ante la duda devuelve NIU.
     *
     * Orden de resolución: id exacto → abreviatura configurada → semilla por texto →
     * NIU. El fallback es deliberado: una unidad sin mapear no puede impedir que se
     * emita el comprobante de una venta ya cobrada; como mucho el ítem sale como
     * "unidad", y la abreviatura real del POS se conserva en la descripción.
     */
    public function codigoSunat(int $empresaId, ?int $unidadMedidaId, ?string $abreviatura): string
    {
        $mapa = $this->cache[$empresaId] ??= $this->cargar($empresaId);

        if ($unidadMedidaId !== null && isset($mapa['ids'][$unidadMedidaId])) {
            return $mapa['ids'][$unidadMedidaId];
        }

        $clave = mb_strtoupper(trim((string) $abreviatura));
        if ($clave === '') {
            return self::DEFAULT;
        }

        return $mapa['abrevs'][$clave] ?? self::SEMILLA[$clave] ?? self::DEFAULT;
    }

    /**
     * @return array{ids: array<int, string>, abrevs: array<string, string>}
     */
    private function cargar(int $empresaId): array
    {
        $vacio = ['ids' => [], 'abrevs' => []];

        try {
            $filas = DB::table('unidad_sunat_map')->where('empresa_id', $empresaId)->get();
        } catch (Throwable $e) {
            // La tabla puede no existir todavía: la migración se despliega aparte y
            // el comando de simulación se usa precisamente ANTES de correrla. Que
            // falte el mapeo degrada a NIU, no rompe el mapeo entero.
            Log::debug('unidad_sunat_map no disponible, se usa la semilla por texto', [
                'empresa_id' => $empresaId,
                'error'      => $e->getMessage(),
            ]);

            return $vacio;
        }

        $mapa = $vacio;
        foreach ($filas as $fila) {
            $codigo = (string) ($fila->codigo_sunat ?: self::DEFAULT);

            if ($fila->unidad_medida_id !== null) {
                $mapa['ids'][(int) $fila->unidad_medida_id] = $codigo;
            }
            if (! empty($fila->abreviatura)) {
                $mapa['abrevs'][mb_strtoupper(trim((string) $fila->abreviatura))] = $codigo;
            }
        }

        return $mapa;
    }
}
