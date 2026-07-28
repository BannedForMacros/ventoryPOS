<?php

namespace App\Services\Facturacion;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fuente ÚNICA de verdad de la configuración fiscal, leída de FacturaMac.
 *
 * ─── Por qué existe ──────────────────────────────────────────────────────────────
 *
 * Hasta ahora ventoryPOS declaraba sus propias series, su tasa de IGV y su umbral de
 * boleta identificada en `config/facturamac.php`, duplicando lo que FacturaMac ya
 * sabía. Dos copias de un mismo dato solo tienen dos estados posibles: iguales, o
 * desincronizadas sin que nadie se entere. Y aquí "sin que nadie se entere" significa
 * emitir un documento fiscal irreversible con la serie o el umbral equivocados.
 *
 * Ahora la configuración la dicta quien firma el comprobante (FacturaMac, que tiene el
 * certificado y la clave SOL) y el POS la CONSUME. Este servicio es esa lectura.
 *
 * ─── Dos garantías que se contradicen, y cómo se resuelven ──────────────────────
 *
 * 1. FAIL-SAFE EN EL AVISO: si no se puede leer la configuración, `esProduccion()`
 *    devuelve TRUE y el POS pinta el aviso rojo. Ya hubo un incidente por emitir
 *    contra producción creyendo estar en beta (AUDITORIA_PRODUCCION_SUNAT.md). Un
 *    aviso de más no cuesta nada; uno de menos cuesta una Nota de Crédito.
 *
 * 2. FAIL-OPEN EN LA CAJA: no poder leer la configuración NUNCA bloquea una venta.
 *    El emisor caído es un problema del emisor; la caja sigue cobrando. Por eso los
 *    getters devuelven defaults razonables en vez de lanzar.
 *
 * No se contradicen porque aplican a cosas distintas: lo primero decide qué AVISA la
 * pantalla, lo segundo decide qué BLOQUEA la venta. Ante la duda: avisar sí, bloquear no.
 */
class ConfiguracionFacturacion
{
    /** Clave de caché de la respuesta completa de `GET /api/v1/configuracion`. */
    public const CACHE_KEY = 'facturamac.configuracion';

    /** Marca de "FacturaMac no respondió hace un momento": evita reintentar en cada request. */
    private const CACHE_KEY_FALLO = 'facturamac.configuracion.fallo';

    /** Modo que reportamos cuando la integración no está cableada en esta instalación. */
    public const MODO_DESACTIVADO = 'desactivado';

    /** Modo que reportamos cuando FacturaMac no contesta: no es lo mismo que estar apagado. */
    public const MODO_DESCONOCIDO = 'desconocido';

    /**
     * Defaults de emergencia. Solo se usan cuando NO hay configuración remota, y
     * únicamente para no romper la caja: son los valores vigentes de SUNAT en Perú,
     * no una configuración alternativa que alguien deba mantener.
     */
    private const TASA_IGV_DEFAULT = 18.0;
    private const UMBRAL_DEFAULT   = 700.0;

    /** POS → Catálogo 01 SUNAT, solo para poder preguntar la serie por el nombre del POS. */
    private const TIPOS = ['factura' => '01', 'boleta' => '03'];

    /**
     * Memoria por request. La configuración se consulta varias veces al pintar una
     * pantalla (umbral, serie, modo); sin esto cada getter iría a la caché.
     *
     * @var array<string, mixed>|null
     */
    private ?array $memo = null;

    /** ¿Ya sabemos, en este request, que no se puede leer? Evita reintentos en bucle. */
    private bool $fallidoEnEsteRequest = false;

    /**
     * Si false, este ejemplar NUNCA sale a la red: responde solo con lo que ya esté
     * cacheado. Ver `sinRed()`.
     */
    private bool $permitirRed = true;

    public function __construct(private readonly FacturaMacClient $client)
    {
    }

    /**
     * Copia que se conforma con la caché y jamás abre una conexión.
     *
     * Existe por un motivo muy concreto: StoreVentaRequest necesita el umbral de
     * boleta en pleno camino de validación del cobro. Con la caché fría, una llamada
     * HTTP ahí dentro dejaría a la cajera mirando la pantalla hasta que expire el
     * timeout, con el cliente delante, para averiguar un número que casi siempre es
     * 700. Preferimos el default a colgar el cobro.
     */
    public function sinRed(): self
    {
        $copia = clone $this;
        $copia->permitirRed = false;

        return $copia;
    }

    /**
     * Configuración remota completa, o `[]` si no se pudo obtener.
     *
     * @return array<string, mixed>
     */
    public function todo(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        // Integración no cableada: ni siquiera hay a quién preguntar.
        if (! $this->integracionCableada()) {
            return $this->memo = [];
        }

        $cacheada = Cache::get(self::CACHE_KEY);
        if (is_array($cacheada)) {
            return $this->memo = $cacheada;
        }

        if (! $this->permitirRed || $this->fallidoEnEsteRequest || Cache::has(self::CACHE_KEY_FALLO)) {
            return [];   // no memoizamos: otro request (o el mismo, más tarde) sí podrá leerla
        }

        try {
            $remota = $this->client->configuracion();
        } catch (Throwable $e) {
            $this->fallidoEnEsteRequest = true;

            // Cooldown: mientras FacturaMac esté caído no queremos pagar un timeout
            // por cada carga del POS. Se anota el fallo, no la ausencia de datos.
            Cache::put(self::CACHE_KEY_FALLO, true, (int) config('facturamac.cooldown_fallo', 30));

            Log::warning('No se pudo leer la configuración de FacturaMac; el POS usa sus defaults y avisa de PRODUCCIÓN.', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        Cache::put(self::CACHE_KEY, $remota, (int) config('facturamac.config_ttl', 600));
        Cache::forget(self::CACHE_KEY_FALLO);

        return $this->memo = $remota;
    }

    /** ¿Hay configuración remota fresca? Si es false, todo lo demás son defaults. */
    public function disponible(): bool
    {
        return $this->todo() !== [];
    }

    /** ¿Esta instalación sabe hablar con FacturaMac? (despliegue, no negocio). */
    public function integracionCableada(): bool
    {
        return (bool) config('facturamac.enabled');
    }

    /**
     * Invalida la caché para que el próximo acceso vuelva a preguntar.
     * La usa `facturacion:estado --refrescar` y cualquier sitio que cambie el token.
     */
    public function olvidar(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_FALLO);
        $this->memo = null;
        $this->fallidoEnEsteRequest = false;
    }

    // ── Lecturas ─────────────────────────────────────────────────────────────────

    /** `desactivado` | `simulacion` | `beta` | `produccion` | `desconocido`. */
    public function modo(): string
    {
        if (! $this->integracionCableada()) {
            return self::MODO_DESACTIVADO;
        }

        $modo = $this->todo()['modo'] ?? null;

        return is_string($modo) && $modo !== '' ? $modo : self::MODO_DESCONOCIDO;
    }

    /**
     * ¿FacturaMac va a crear un comprobante si le mandamos esta venta?
     *
     * Ante la duda devolvemos TRUE: si no sabemos el estado del emisor, más vale
     * intentar la emisión (FacturaMac responderá `emitido:false` y el Job lo tratará
     * como caso normal) que dejar ventas sin comprobante por un fallo de red.
     */
    public function emisionActiva(): bool
    {
        if (! $this->integracionCableada()) {
            return false;
        }

        $datos = $this->todo();

        if ($datos === []) {
            return true;
        }

        if (array_key_exists('emision_activa', $datos)) {
            return (bool) $datos['emision_activa'];
        }

        return ($datos['modo'] ?? null) !== self::MODO_DESACTIVADO;
    }

    /** ¿Los comprobantes llegan de verdad a SUNAT, o se quedan en FacturaMac? */
    public function enviaASunat(): bool
    {
        $datos = $this->todo();

        if (array_key_exists('envia_a_sunat', $datos)) {
            return (bool) $datos['envia_a_sunat'];
        }

        // Sin dato: asumimos que sí. Es la lectura pesimista, coherente con esProduccion().
        return $this->emisionActiva();
    }

    /**
     * ¿El emisor apunta a SUNAT PRODUCCIÓN? Gobierna el aviso rojo del POS.
     *
     * FAIL-SAFE DELIBERADO: sin configuración legible devuelve TRUE. Es la única
     * lectura de esta clase que miente a propósito hacia el lado seguro.
     */
    public function esProduccion(): bool
    {
        $datos = $this->todo();

        if ($datos === []) {
            return true;
        }

        $modo = $datos['modo'] ?? null;

        if ($modo === 'produccion') {
            return true;
        }

        if (in_array($modo, [self::MODO_DESACTIVADO, 'simulacion', 'beta'], true)) {
            return false;
        }

        // Modo ausente o desconocido: caemos a sunat_beta, y su ausencia también
        // significa producción. Nunca se llega a "no es producción" por descarte.
        return ! (bool) ($datos['sunat_beta'] ?? false);
    }

    /** Atajo legible: emisión real contra SUNAT producción. */
    public function esSimulacion(): bool
    {
        return $this->modo() === 'simulacion';
    }

    public function tasaIgv(): float
    {
        $tasa = (float) ($this->todo()['tasa_igv'] ?? 0);

        return $tasa > 0 ? $tasa : self::TASA_IGV_DEFAULT;
    }

    /** Importe desde el que SUNAT exige identificar al adquirente en una boleta. */
    public function umbralBoletaIdentificada(): float
    {
        $umbral = (float) ($this->todo()['umbral_boleta_identificada'] ?? 0);

        return $umbral > 0 ? $umbral : self::UMBRAL_DEFAULT;
    }

    /**
     * Serie por defecto del tipo. Admite tanto el idioma del POS (`boleta`,
     * `factura`) como el código del Catálogo 01 (`03`, `01`), porque los dos
     * circulan por el sistema y obligar a traducir en cada llamada solo produce
     * traducciones olvidadas.
     */
    public function seriePorDefecto(string $tipo): ?string
    {
        $codigo = self::TIPOS[strtolower($tipo)] ?? $tipo;
        $serie  = $this->seriesPorDefecto()[$codigo] ?? null;

        return is_string($serie) && $serie !== '' ? $serie : null;
    }

    /**
     * Series por defecto indexadas por código SUNAT: `['01' => 'F002', '03' => 'B002']`.
     * Es la forma que consume el POS desde antes de este refactor y se conserva tal cual.
     *
     * @return array<string, string>
     */
    public function seriesPorDefecto(): array
    {
        $series = $this->todo()['series_por_defecto'] ?? [];

        return is_array($series) ? array_map(strval(...), $series) : [];
    }

    /**
     * Todas las series habilitadas por tipo: `['01' => ['F001','F002'], ...]`.
     *
     * @return array<string, list<string>>
     */
    public function series(): array
    {
        $series = $this->todo()['series'] ?? [];

        return is_array($series) ? $series : [];
    }

    /**
     * Código del Catálogo 03 de SUNAT para una abreviatura del POS, o null si
     * FacturaMac no lo conoce.
     *
     * Ojo: quien traduce de verdad es FacturaMac, que recibe la abreviatura cruda en
     * el payload. Esto solo sirve para MOSTRAR el mapeo (diagnóstico, pantallas de
     * configuración); no lo usa la emisión.
     */
    public function unidadSunat(string $abrev): ?string
    {
        $unidades = $this->todo()['unidades_sunat'] ?? [];

        if (! is_array($unidades)) {
            return null;
        }

        $clave = mb_strtoupper(trim($abrev));

        foreach ($unidades as $desde => $hacia) {
            if (mb_strtoupper(trim((string) $desde)) === $clave) {
                return (string) $hacia;
            }
        }

        return null;
    }

    /**
     * Tipos de documento de identidad admitidos (Catálogo 06), tal como los declara
     * FacturaMac: `['0' => 'Sin documento', '1' => 'DNI', ...]`.
     *
     * @return array<string, string>
     */
    public function tiposDocumento(): array
    {
        $tipos = $this->todo()['tipos_documento'] ?? [];

        return is_array($tipos) ? array_map(strval(...), $tipos) : [];
    }

    /**
     * Datos del emisor (razón social, RUC, dirección). Sirven para que la pantalla de
     * diagnóstico diga CON QUÉ RUC se está emitiendo, que es la pregunta que uno se
     * hace justo antes de encender producción.
     *
     * @return array<string, mixed>
     */
    public function empresa(): array
    {
        $empresa = $this->todo()['empresa'] ?? [];

        return is_array($empresa) ? $empresa : [];
    }
}
