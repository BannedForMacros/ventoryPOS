<?php

namespace App\Services\Facturacion;

use App\Models\ConexionFacturacion;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Punto ÚNICO desde el que se pregunta "¿qué emisor tiene esta empresa?".
 *
 * ─── El fallo que esto cierra ────────────────────────────────────────────────
 *
 * Antes la respuesta salía del `.env`: `config('facturamac.enabled')` y
 * `config('facturamac.token')`, uno para toda la instalación. Un token de
 * FacturaMac identifica a UNA empresa emisora, así que con dos contribuyentes
 * en la misma instalación las ventas de la segunda salían firmadas con el RUC
 * de la primera. Ahora la conexión es una fila por empresa
 * (`facturacion_conexiones`) y el token del que se emite se resuelve SIEMPRE a
 * partir del `empresa_id` de lo que se está emitiendo.
 *
 * ─── Por qué UNA sola clase ──────────────────────────────────────────────────
 *
 * `config('facturamac.enabled')` se consultaba en nueve sitios (dos requests,
 * dos controladores, tres jobs, el scheduler y la propia configuración). Nueve
 * copias de la misma decisión son nueve sitios donde olvidarse de la empresa.
 * Aquí viven las tres preguntas del módulo y en ninguna se puede "olvidar" el
 * `empresa_id`, porque es un argumento obligatorio:
 *
 *   · `activa($empresaId)`       — ¿esta empresa emite? (reemplaza `enabled`)
 *   · `cliente($empresaId)`      — cliente HTTP con SU token
 *   · `configuracion($empresaId)`— configuración fiscal de SU emisor
 *
 * ─── Compatibilidad hacia atrás ──────────────────────────────────────────────
 *
 * Si la tabla todavía no existe —código desplegado antes que el SQL— el módulo
 * se comporta como APAGADO en vez de reventar con un "relation does not exist".
 * Mismo criterio que `VentaService::bloquearSiTieneComprobanteEmitido()`, y por
 * la misma razón: la facturación electrónica es opcional y nunca puede tumbar
 * al POS. Y es además la respuesta CORRECTA: sin tabla no hay token, y sin
 * token no se emite nada.
 */
class FacturacionEmpresa
{
    /**
     * Memoria por instancia (que es por request, porque se registra como
     * singleton). Se consulta varias veces por pantalla —el POS pregunta por el
     * modo, por el umbral y por las series— y no tiene sentido volver a la base
     * cada vez. En tests el contenedor se reconstruye por test, así que ningún
     * test hereda la memoria del anterior.
     *
     * @var array<int, ConexionFacturacion|null>
     */
    private array $conexiones = [];

    /** @var array<int, ConfiguracionFacturacion> */
    private array $configuraciones = [];

    private ?bool $tabla = null;

    /**
     * ¿Existe `facturacion_conexiones`?
     *
     * Se cachea en la instancia porque `Schema::hasTable()` consulta el catálogo
     * de la base y esto corre en el camino más caliente del sistema.
     * El `try` es deliberado: si ni siquiera se puede interrogar al catálogo
     * (base caída, permisos), la respuesta segura es "no hay módulo", nunca una
     * excepción que tumbe la pantalla de ventas.
     */
    public function tablaDisponible(): bool
    {
        if ($this->tabla !== null) {
            return $this->tabla;
        }

        try {
            return $this->tabla = Schema::hasTable('facturacion_conexiones');
        } catch (Throwable) {
            return $this->tabla = false;
        }
    }

    /** La conexión de una empresa, o `null` si no está conectada (o no hay tabla). */
    public function conexion(int $empresaId): ?ConexionFacturacion
    {
        if (array_key_exists($empresaId, $this->conexiones)) {
            return $this->conexiones[$empresaId];
        }

        if (! $this->tablaDisponible()) {
            return $this->conexiones[$empresaId] = null;
        }

        return $this->conexiones[$empresaId] = ConexionFacturacion::query()
            ->where('empresa_id', $empresaId)
            ->first();
    }

    /**
     * ¿Esta empresa emite comprobantes electrónicos?
     *
     * ESTE es el reemplazo de `config('facturamac.enabled')`. Todo lo que antes
     * preguntaba por el flag global pregunta ahora por aquí, con la empresa
     * delante. Devuelve `false` de forma segura cuando no hay tabla, no hay
     * conexión, no hay token o el emisor tiene la empresa en modo de ensayo.
     */
    public function activa(int $empresaId): bool
    {
        return (bool) $this->conexion($empresaId)?->emite();
    }

    /**
     * Cliente HTTP apuntando al emisor CON EL TOKEN DE ESA EMPRESA.
     *
     * Si la empresa no está conectada devuelve un cliente sin token: no lanza,
     * porque quien lo pide suele estar dentro de un job o de un controlador que
     * ya comprobó `activa()`. El cliente sin token falla con un error
     * DEFINITIVO y explícito en cuanto se le pide algo, que es mucho más fácil
     * de diagnosticar que un token nulo colándose en una cabecera Bearer.
     */
    public function cliente(int $empresaId): FacturaMacClient
    {
        return new FacturaMacClient($this->conexion($empresaId)?->token);
    }

    /**
     * Configuración fiscal del emisor de esa empresa (§7 del contrato).
     *
     * Se memoiza por empresa: `ConfiguracionFacturacion` guarda su propia
     * memoria por request, y crear una instancia nueva en cada llamada la
     * desperdiciaría.
     */
    public function configuracion(int $empresaId): ConfiguracionFacturacion
    {
        return $this->configuraciones[$empresaId] ??= new ConfiguracionFacturacion(
            $this->cliente($empresaId),
            $empresaId,
            $this->activa($empresaId),
        );
    }

    /**
     * Olvida lo memorizado para una empresa (o para todas) y tira su caché de
     * configuración remota. Se llama al conectar, al desconectar y al activar o
     * desactivar la emisión: sin esto, la pantalla seguiría enseñando el estado
     * anterior hasta que expirase el TTL de 10 minutos.
     */
    public function olvidar(?int $empresaId = null): void
    {
        if ($empresaId === null) {
            foreach (array_keys($this->configuraciones) as $id) {
                $this->configuraciones[$id]->olvidar();
            }

            $this->conexiones      = [];
            $this->configuraciones = [];
            $this->tabla           = null;

            return;
        }

        // Se construye si no existía: `olvidar()` tiene que borrar la caché
        // compartida aunque en ESTE request nadie hubiera leído la configuración.
        $this->configuracion($empresaId)->olvidar();

        unset($this->conexiones[$empresaId], $this->configuraciones[$empresaId]);
    }
}
