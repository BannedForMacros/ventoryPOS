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

    /** Umbral legal vigente en Perú. Solo se usa si no hay configuración remota. */
    private const UMBRAL_DEFAULT = 700.0;

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

    /** Importe desde el que SUNAT exige identificar al adquirente en una boleta. */
    public function umbralBoletaIdentificada(): float
    {
        $umbral = (float) ($this->todo()['umbral_boleta_identificada'] ?? 0);

        return $umbral > 0 ? $umbral : self::UMBRAL_DEFAULT;
    }
}
