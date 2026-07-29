<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conexión de UNA empresa con su emisor electrónico (FacturaMac).
 *
 * ─── El fallo que esto cierra ────────────────────────────────────────────────
 *
 * La conexión vivía en el `.env` (FACTURAMAC_ENABLED / FACTURAMAC_TOKEN), uno
 * solo para toda la instalación. Pero un token de FacturaMac identifica a UNA
 * empresa emisora: de él se deducen el RUC, el certificado y la clave SOL. Con
 * dos contribuyentes en la misma instalación (MacSoft y HYC FERROMATERIALES),
 * las ventas de la segunda habrían salido firmadas con el RUC de la primera —
 * facturar a nombre de otro contribuyente, sobre documentos irreversibles.
 *
 * No era arreglable en el `.env`: no hay dónde poner el segundo token. Por eso
 * la conexión es una FILA POR EMPRESA, y por eso `empresa_id` es UNIQUE: una
 * empresa, un emisor, un token.
 *
 * ─── Qué NO está aquí ────────────────────────────────────────────────────────
 *
 * La URL de FacturaMac. El emisor es un único servicio para toda la instalación
 * y sigue en `config/facturamac.php` leyendo del `.env`: eso es "dónde vive el
 * servicio", no configuración del usuario. Y toda la configuración FISCAL
 * —series, tasa, umbral, si se manda a SUNAT— sigue siendo competencia
 * exclusiva del emisor y se consume por `GET /api/v1/configuracion`.
 *
 * @property int         $empresa_id
 * @property string      $token                Descifrado al leer (cast `encrypted`).
 * @property string      $ruc_emisor
 * @property string|null $razon_social_emisor
 * @property string      $modo                 produccion|beta|simulacion|desactivado
 * @property bool        $emision_activa
 */
class ConexionFacturacion extends Model
{
    /**
     * Nombre explícito: la convención de Eloquent daría `conexion_facturacions`.
     * El script de producción crea `facturacion_conexiones`.
     */
    protected $table = 'facturacion_conexiones';

    /** Modo en el que el emisor SÍ deja activar la emisión. Ver `puedeEmitir()`. */
    public const MODO_PRODUCCION = 'produccion';

    /** Modos de ensayo: se puede conectar y diagnosticar, pero NO emitir. */
    public const MODOS_DE_PRUEBA = ['beta', 'simulacion'];

    protected $fillable = [
        'empresa_id',
        'token',
        'ruc_emisor',
        'razon_social_emisor',
        'modo',
        'emision_activa',
        'instalacion',
        'conectado_at',
        'diagnostico_at',
        'diagnostico_ok',
        'diagnostico_mensaje',
    ];

    /**
     * El token NUNCA se serializa hacia el frontend. Es la credencial que permite
     * firmar comprobantes fiscales a nombre del contribuyente: enseñarlo en un
     * `props` de Inertia lo deja en el HTML de la página y en el historial del
     * navegador de cualquiera que abra la pantalla de configuración.
     */
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            // Cifrado en reposo con APP_KEY. Un volcado de la base —o un acceso
            // de solo lectura— no puede emitir con él.
            'token'          => 'encrypted',
            'emision_activa' => 'boolean',
            'diagnostico_ok' => 'boolean',
            'conectado_at'   => 'datetime',
            'diagnostico_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * ¿Esta empresa emite comprobantes ahora mismo?
     *
     * Es el reemplazo exacto de `config('facturamac.enabled')`, con dos
     * condiciones extra que el flag global no podía comprobar:
     *
     *  · Hay token. Sin token no hay a quién emitir, y un `emision_activa=true`
     *    sin credencial solo consigue que cada venta muera con un error.
     *  · El modo es `produccion`. Se comprueba AQUÍ además de al activar porque
     *    el modo lo cambia el emisor por su cuenta: una empresa activada en
     *    producción que el emisor devuelve a beta debe dejar de emitir sola, sin
     *    esperar a que alguien entre a la pantalla de configuración.
     */
    public function emite(): bool
    {
        return $this->emision_activa
            && trim((string) $this->token) !== ''
            && $this->modo === self::MODO_PRODUCCION;
    }

    /** ¿El modo del emisor permite ACTIVAR la emisión? Solo producción. */
    public function puedeEmitir(): bool
    {
        return $this->modo === self::MODO_PRODUCCION;
    }

    /** ¿El emisor tiene a esta empresa en un modo de ensayo (beta o simulación)? */
    public function enPruebas(): bool
    {
        return in_array($this->modo, self::MODOS_DE_PRUEBA, true);
    }
}
