<?php

namespace App\Services\Facturacion;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lee de FacturaMac la configuración fiscal vigente y la cachea.
 *
 * ─── Por qué existe ──────────────────────────────────────────────────────────────
 *
 * ventoryPOS declaraba sus propias series, su tasa de IGV y su umbral de boleta
 * identificada, duplicando lo que el emisor ya sabía. Dos copias de un mismo dato solo
 * tienen dos estados: iguales, o desincronizadas sin que nadie se entere. Y aquí eso
 * significa emitir un documento fiscal irreversible con el umbral equivocado. Ahora la
 * configuración la dicta quien firma el comprobante y el POS la CONSUME.
 *
 * ─── Dos garantías que parecen contradecirse ─────────────────────────────────────
 *
 * 1. FAIL-SAFE EN EL AVISO: si no se puede leer, `esProduccion()` devuelve TRUE y el
 *    POS pinta el aviso rojo. Un aviso de más no cuesta nada; uno de menos cuesta una
 *    Nota de Crédito.
 * 2. FAIL-OPEN EN LA CAJA: no poder leerla NUNCA bloquea una venta. El emisor caído es
 *    problema del emisor; la caja sigue cobrando. Por eso ningún getter lanza.
 *
 * No se contradicen: lo primero decide qué AVISA la pantalla, lo segundo qué BLOQUEA
 * el cobro. Ante la duda: avisar sí, bloquear no.
 */
class ConfiguracionFacturacion
{
    public const CACHE_KEY = 'facturamac.configuracion';

    /** Marca de "el emisor no respondió hace un momento": evita un timeout por pantalla. */
    private const CACHE_KEY_FALLO = 'facturamac.configuracion.fallo';

    public const MODO_DESACTIVADO = 'desactivado';

    /** El emisor no contesta. No es lo mismo que estar apagado, y no debe confundirse. */
    public const MODO_DESCONOCIDO = 'desconocido';

    /**
     * ─── Defaults de último recurso ──────────────────────────────────────────
     *
     * NO son "la configuración del POS": son lo que se usa mientras no se puede
     * hablar con el emisor, para que la caja siga cobrando. En cuanto
     * `/api/v1/configuracion` responde, mandan sus valores aunque difieran.
     *
     * Por eso son `private const` y no `config('facturamac.*')`: una clave de
     * configuración invita a editarla en el .env de cada tienda, y ahí es donde
     * empieza la duplicación que ya emitió una boleta con la serie equivocada.
     */
    private const UMBRAL_DEFAULT     = 700.0;   // umbral legal vigente en Perú
    private const TASA_IGV_DEFAULT   = 18.0;
    private const TOLERANCIA_DEFAULT = 0.05;
    private const MONEDAS_DEFAULT    = ['PEN']; // v1 solo PEN (§8.3)

    /** @var array<string, mixed>|null Memoria por request: se consulta varias veces por pantalla. */
    private ?array $memo = null;

    public function __construct(private readonly FacturaMacClient $client)
    {
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
        if (! config('facturamac.enabled')) {
            return $this->memo = [];
        }

        $cacheada = Cache::get(self::CACHE_KEY);

        if (is_array($cacheada)) {
            return $this->memo = $cacheada;
        }

        if (Cache::has(self::CACHE_KEY_FALLO)) {
            return [];   // no se memoiza: otro request, más tarde, sí podrá leerla
        }

        try {
            $remota = $this->client->configuracion();
        } catch (Throwable $e) {
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

    /** Invalida la caché para que el próximo acceso vuelva a preguntar. */
    public function olvidar(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_FALLO);
        $this->memo = null;
    }

    /** `desactivado` | `simulacion` | `beta` | `produccion` | `desconocido`. */
    public function modo(): string
    {
        if (! config('facturamac.enabled')) {
            return self::MODO_DESACTIVADO;
        }

        $modo = $this->todo()['modo'] ?? null;

        return is_string($modo) && $modo !== '' ? $modo : self::MODO_DESCONOCIDO;
    }

    /**
     * ¿El emisor va a crear un comprobante si le mandamos esta venta?
     *
     * Ante la duda, TRUE: más vale intentarlo —el emisor responderá `emitido: false` y
     * el Job lo tratará como caso normal— que dejar ventas sin comprobante por un
     * fallo de red.
     */
    public function emisionActiva(): bool
    {
        if (! config('facturamac.enabled')) {
            return false;
        }

        $datos = $this->todo();

        return array_key_exists('emision_activa', $datos)
            ? (bool) $datos['emision_activa']
            : ($datos['modo'] ?? null) !== self::MODO_DESACTIVADO;
    }

    /**
     * ¿El emisor apunta a SUNAT PRODUCCIÓN? Gobierna el aviso rojo del POS.
     *
     * FAIL-SAFE DELIBERADO: si el emisor no responde el modo es `desconocido`, que NO
     * está en la lista de inocuos, así que devuelve TRUE. Es la única lectura de esta
     * clase que miente a propósito, y lo hace hacia el lado seguro. Con la integración
     * apagada, en cambio, no hay nada que avisar: no se emite nada.
     */
    public function esProduccion(): bool
    {
        return ! in_array($this->modo(), [self::MODO_DESACTIVADO, 'simulacion', 'beta'], true);
    }

    /** ¿El emisor manda de verdad a SUNAT, o calcula y guarda sin enviar? */
    public function enviaASunat(): bool
    {
        $datos = $this->todo();

        return array_key_exists('envia_a_sunat', $datos)
            // Ante la duda, TRUE: es la lectura que hace que la interfaz avise.
            ? (bool) $datos['envia_a_sunat']
            : $this->esProduccion();
    }

    /** Importe desde el que SUNAT exige identificar al adquirente en una boleta. */
    public function umbralBoletaIdentificada(): float
    {
        $umbral = (float) ($this->todo()['umbral_boleta_identificada'] ?? 0);

        return $umbral > 0 ? $umbral : self::UMBRAL_DEFAULT;
    }

    /** Tasa de IGV con la que el emisor calcula (§8.4: sale de su config, no de una constante). */
    public function tasaImpuesto(): float
    {
        $tasa = (float) ($this->todo()['tasa_impuesto'] ?? 0);

        return $tasa > 0 ? $tasa : self::TASA_IGV_DEFAULT;
    }

    /** Céntimos que el emisor admite de diferencia en la verificación cruzada de `total` (§3.4). */
    public function toleranciaRedondeo(): float
    {
        $tolerancia = (float) ($this->todo()['tolerancia_redondeo'] ?? 0);

        return $tolerancia > 0 ? $tolerancia : self::TOLERANCIA_DEFAULT;
    }

    /**
     * Monedas que el emisor admite. v1: solo PEN (§8.3).
     *
     * @return list<string>
     */
    public function monedas(): array
    {
        $monedas = $this->todo()['monedas'] ?? null;

        if (! is_array($monedas) || $monedas === []) {
            return self::MONEDAS_DEFAULT;
        }

        return array_values(array_map(
            static fn ($m): string => strtoupper((string) $m),
            $monedas,
        ));
    }

    /**
     * Series con las que el emisor va a numerar, por tipo de comprobante.
     *
     * ─── El POS NO manda la serie al emitir ──────────────────────────────────
     *
     * Esto es SOLO para MOSTRARLA en caja. El payload de `POST /api/v1/ventas`
     * deja `serie` fuera a propósito (§3.1: "solo si se quiere forzar"), porque:
     *
     *  1. La numeración es competencia de quien firma el comprobante. El
     *     correlativo vive por serie EN EL EMISOR; si el POS eligiera la serie,
     *     dos sistemas decidirían sobre la misma secuencia fiscal.
     *  2. Esta lectura está CACHEADA 10 minutos. Mandarla significaría que,
     *     justo mientras alguien cambia de serie para separar pruebas de
     *     producción —el momento exacto en que salió una boleta por la serie
     *     real B001—, el POS seguiría forzando la serie vieja durante 10 min.
     *     No mandarla hace que el cambio surta efecto en el acto.
     *  3. Una serie que el emisor no tenga registrada se resuelve o abriendo una
     *     numeración nueva (huecos que justificar ante SUNAT) o con un rechazo
     *     DESPUÉS de haber cobrado.
     *
     * Mostrarla sí importa, y es el otro lado del mismo incidente: con la serie
     * a la vista en la franja del POS, una `B001` en un ensayo se ve ANTES de
     * cobrar, no al revisar la numeración al día siguiente.
     *
     * @return array{factura: string|null, boleta: string|null}
     */
    public function seriesPorDefecto(): array
    {
        $series = $this->todo()['series_por_defecto'] ?? [];
        $series = is_array($series) ? $series : [];

        $leer = static function (string $clave) use ($series): ?string {
            $valor = trim((string) ($series[$clave] ?? ''));

            return $valor !== '' ? $valor : null;
        };

        return [
            'factura' => $leer('factura'),
            'boleta'  => $leer('boleta'),
        ];
    }

    /**
     * Catálogo de unidades del emisor (`{"UND":"NIU", …}`), por si el POS quiere
     * avisar de una unidad no mapeada. Vacío si no se pudo leer: el emisor ya
     * responde con un aviso `unidad_no_mapeada` (§4.1) y cae a NIU, así que aquí
     * NO se inventa un mapa local que volvería a duplicar su catálogo.
     *
     * @return array<string, string>
     */
    public function unidades(): array
    {
        $unidades = $this->todo()['unidades'] ?? [];

        return is_array($unidades) ? $unidades : [];
    }

    /**
     * Lo que el POS necesita para avisar ANTES de cobrar.
     *
     * Se arma aquí y no en el controlador para que las pantallas (POS hoy, las que
     * vengan mañana) no vuelvan a inventarse cada una su forma de preguntar por el
     * modo o el umbral. Ninguna clave sale de `config/facturamac.php` salvo
     * `enabled`, que es el interruptor de DESPLIEGUE y sí es local.
     *
     * @return array<string, mixed>
     */
    public function paraPos(): array
    {
        return [
            'enabled'                    => (bool) config('facturamac.enabled'),
            // El banner del POS distingue simulacion / beta / produccion con esto.
            'modo'                       => $this->modo(),
            // Se mantiene aparte de `modo` porque es la lectura FAIL-SAFE: con el
            // emisor caído el modo es `desconocido` pero esto es `true`.
            'produccion'                 => $this->esProduccion(),
            'emision_activa'             => $this->emisionActiva(),
            'envia_a_sunat'              => $this->enviaASunat(),
            'umbral_boleta_identificada' => $this->umbralBoletaIdentificada(),
            'tasa_impuesto'              => $this->tasaImpuesto(),
            'monedas'                    => $this->monedas(),
            // Informativas: el POS las PINTA, no las manda (ver seriesPorDefecto()).
            'series'                     => $this->seriesPorDefecto(),
        ];
    }
}
