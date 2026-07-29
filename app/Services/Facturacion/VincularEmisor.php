<?php

namespace App\Services\Facturacion;

use App\Models\ConexionFacturacion;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Conecta UNA empresa de ventoryPOS con su emisor en FacturaMac, y guarda las dos
 * guardas que impiden emitir a nombre de quien no toca.
 *
 * ─── El fallo que esto cierra ────────────────────────────────────────────────────
 *
 * El token del emisor vivía en el `.env`, uno para toda la instalación, y un token
 * identifica a UNA empresa emisora: de él salen el RUC, el certificado y la clave
 * SOL. Con dos contribuyentes en el mismo ventoryPOS —MacSoft (20612345678) y HYC
 * FERROMATERIALES (20600134648)—, las boletas de HYC habrían salido firmadas con el
 * RUC de MacSoft. Facturar a nombre de otro contribuyente, y sobre documentos que no
 * se pueden deshacer sin una Nota de Crédito.
 *
 * ─── Las dos guardas, en este orden ──────────────────────────────────────────────
 *
 * 1. EL RUC DEBE COINCIDIR. El emisor dice a qué RUC pertenece el token; si no es el
 *    de la empresa de ventoryPOS que está conectando, NO SE GUARDA NADA. No es un
 *    aviso ni una casilla que se pueda ignorar: es exactamente el escenario de
 *    arriba, y dejar la conexión guardada "por si acaso" ya bastaría para que un
 *    despiste posterior la activara.
 *
 * 2. SOLO SE EMITE EN PRODUCCIÓN. Si el emisor tiene la empresa en `beta` o
 *    `simulacion`, la conexión SÍ se guarda —hay que poder comprobar que la tubería
 *    funciona antes de pasar la empresa a producción— pero la emisión NO se puede
 *    activar. Bloquear los dos modos de ensayo es decisión explícita: una boleta
 *    "de prueba" que consume correlativo real es un hueco que justificar ante SUNAT.
 *
 * La segunda guarda se comprueba tanto al ACTIVAR como en cada venta
 * (`ConexionFacturacion::emite()`), porque el modo lo cambia el emisor por su cuenta
 * y una empresa devuelta a beta debe dejar de emitir sola.
 */
class VincularEmisor
{
    public function __construct(private readonly FacturacionEmpresa $facturacion)
    {
    }

    /**
     * Canjea el código corto por el token y guarda la conexión de la empresa.
     *
     * @throws ValidationException  Con el mensaje ya escrito para quien lo va a leer.
     */
    public function conectar(Empresa $empresa, string $codigo, string $instalacion): ConexionFacturacion
    {
        $this->exigirTabla();

        // Cliente SIN token: es justo lo que se viene a buscar. `/api/v1/vincular`
        // es público por contrato.
        $cliente = new FacturaMacClient();

        try {
            $respuesta = $cliente->vincular(trim($codigo), $instalacion);
        } catch (FacturaMacException $e) {
            // El cliente ya tradujo los códigos del contrato a frases legibles.
            throw ValidationException::withMessages(['codigo' => $e->getMessage()]);
        }

        $token  = trim((string) ($respuesta['token'] ?? ''));
        $emisor = is_array($respuesta['emisor'] ?? null) ? $respuesta['emisor'] : [];
        $ruc    = $this->soloDigitos((string) ($emisor['ruc'] ?? ''));
        $razon  = trim((string) ($emisor['razon_social'] ?? ''));
        $modo   = trim((string) ($emisor['modo'] ?? ''));

        if ($token === '' || $ruc === '') {
            throw ValidationException::withMessages([
                'codigo' => 'FacturaMac aceptó el código pero no devolvió el emisor. '
                    . 'Avisa a soporte: la vinculación no se puede completar.',
            ]);
        }

        // ── Guarda 1: el RUC del emisor DEBE ser el de esta empresa ───────────
        //
        // Va ANTES de tocar la base a propósito. Si esto se guardara y se
        // comprobara después, quedaría en `facturacion_conexiones` una fila con
        // el token de otro contribuyente esperando a que alguien la active.
        $rucEmpresa = $this->soloDigitos((string) $empresa->ruc);

        if ($rucEmpresa === '' || $rucEmpresa !== $ruc) {
            Log::warning('Vinculación de facturación RECHAZADA: el RUC del emisor no es el de la empresa.', [
                'empresa_id'  => $empresa->id,
                'ruc_empresa' => $rucEmpresa,
                'ruc_emisor'  => $ruc,
            ]);

            throw ValidationException::withMessages([
                'codigo' => sprintf(
                    'Ese código pertenece a otro contribuyente. La empresa «%s» tiene RUC %s, '
                    . 'pero el código conecta con %s%s. No se ha guardado nada: emitir con ese '
                    . 'token pondría el RUC equivocado en los comprobantes. Pide en FacturaMac '
                    . 'un código de la empresa con RUC %s.',
                    $empresa->razon_social,
                    $rucEmpresa !== '' ? $rucEmpresa : '(sin RUC registrado)',
                    $ruc,
                    $razon !== '' ? " ({$razon})" : '',
                    $rucEmpresa !== '' ? $rucEmpresa : 'correcto',
                ),
            ]);
        }

        // ── Se guarda ────────────────────────────────────────────────────────
        //
        // `emision_activa` SIEMPRE arranca en false, incluso reconectando una
        // empresa que ya emitía. Conectar y emitir son dos decisiones distintas:
        // la primera es técnica, la segunda es fiscal e irreversible. Que
        // reconectar reactivara sola la emisión convertiría un cambio de token en
        // un cambio de estado fiscal sin que nadie lo pidiera.
        $conexion = ConexionFacturacion::updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'token'               => $token,
                'ruc_emisor'          => $ruc,
                'razon_social_emisor' => $razon !== '' ? $razon : null,
                'modo'                => $modo !== '' ? $modo : ConfiguracionFacturacion::MODO_DESCONOCIDO,
                'emision_activa'      => false,
                'instalacion'         => mb_substr($instalacion, 0, 120),
                'conectado_at'        => now(),
                'diagnostico_at'      => now(),
                'diagnostico_ok'      => true,
                'diagnostico_mensaje' => 'Vinculación correcta.',
            ],
        );

        $this->facturacion->olvidar($empresa->id);

        return $conexion->refresh();
    }

    /**
     * Enciende o apaga la emisión de una empresa.
     *
     * @throws ValidationException  Si el emisor tiene la empresa en modo de ensayo.
     */
    public function activar(Empresa $empresa, bool $activar): ConexionFacturacion
    {
        $this->exigirTabla();

        $conexion = $this->facturacion->conexion($empresa->id);

        if (! $conexion) {
            throw ValidationException::withMessages([
                'emision_activa' => 'Esta empresa todavía no está conectada con FacturaMac.',
            ]);
        }

        // ── Guarda 2: solo se emite en PRODUCCIÓN ─────────────────────────────
        //
        // Apagar siempre se puede: si alguna vez hay que parar la emisión, el
        // modo del emisor no puede ser lo que lo impida.
        if ($activar && ! $conexion->puedeEmitir()) {
            throw ValidationException::withMessages([
                'emision_activa' => 'Esta empresa está en modo de pruebas en FacturaMac '
                    . '(BETA/SIMULACIÓN). Comuníquese con soporte para activarla.',
            ]);
        }

        $conexion->update(['emision_activa' => $activar]);

        $this->facturacion->olvidar($empresa->id);

        return $conexion->refresh();
    }

    /** Borra la conexión de la empresa. Sin token no se emite: la emisión queda parada. */
    public function desconectar(Empresa $empresa): void
    {
        $this->exigirTabla();

        $this->facturacion->conexion($empresa->id)?->delete();

        $this->facturacion->olvidar($empresa->id);
    }

    /**
     * Prueba la conexión contra el emisor y refresca el modo conocido.
     *
     * Sirve para dos cosas a la vez: comprobar que la tubería funciona (token
     * válido, red, servicio arriba) y ENTERARSE de que el emisor cambió el modo.
     * Por eso el modo se guarda aquí y no solo al vincular: es lo que permite
     * activar la emisión el día que soporte pasa la empresa a producción, sin
     * tener que pedir un código nuevo.
     *
     * Nunca lanza por un fallo del emisor: el resultado se guarda y se devuelve
     * para pintarlo. Un diagnóstico es información, no una operación que falle.
     *
     * @return array{ok: bool, mensaje: string, modo: string|null}
     */
    public function diagnosticar(Empresa $empresa): array
    {
        $this->exigirTabla();

        $conexion = $this->facturacion->conexion($empresa->id);

        if (! $conexion) {
            return [
                'ok'      => false,
                'mensaje' => 'Esta empresa todavía no está conectada con FacturaMac.',
                'modo'    => null,
            ];
        }

        // Cliente construido a mano con el token de la conexión: `activa()` puede
        // ser false (empresa en beta, o emisión apagada) y aun así hay que poder
        // diagnosticar. Ese es justo el caso que el dueño necesita ver.
        $cliente = new FacturaMacClient($conexion->token);

        try {
            $remota = $cliente->configuracion();
        } catch (FacturaMacException $e) {
            $conexion->update([
                'diagnostico_at'      => now(),
                'diagnostico_ok'      => false,
                'diagnostico_mensaje' => $e->getMessage(),
            ]);

            $this->facturacion->olvidar($empresa->id);

            return ['ok' => false, 'mensaje' => $e->getMessage(), 'modo' => $conexion->modo];
        }

        $modo = trim((string) ($remota['modo'] ?? '')) ?: ConfiguracionFacturacion::MODO_DESCONOCIDO;
        $ruc  = $this->soloDigitos((string) (($remota['empresa']['ruc'] ?? null) ?: ''));

        // El RUC se re-verifica en cada diagnóstico. Si el emisor movió el token de
        // tenant, la conexión se marca en rojo y —lo importante— se APAGA la emisión:
        // seguir emitiendo con un token que ya no es de esta empresa es exactamente
        // el fallo que todo esto viene a cerrar.
        $rucEmpresa = $this->soloDigitos((string) $empresa->ruc);

        if ($ruc !== '' && $rucEmpresa !== '' && $ruc !== $rucEmpresa) {
            $mensaje = sprintf(
                'El emisor responde ahora con el RUC %s, pero esta empresa es %s. '
                . 'Se ha desactivado la emisión: vuelve a conectar con un código correcto.',
                $ruc,
                $rucEmpresa,
            );

            $conexion->update([
                'modo'                => $modo,
                'emision_activa'      => false,
                'diagnostico_at'      => now(),
                'diagnostico_ok'      => false,
                'diagnostico_mensaje' => $mensaje,
            ]);

            $this->facturacion->olvidar($empresa->id);

            return ['ok' => false, 'mensaje' => $mensaje, 'modo' => $modo];
        }

        // El modo cambió a uno de ensayo: se apaga la emisión sin preguntar. Es la
        // misma decisión que `ConexionFacturacion::emite()` toma en cada venta, y
        // dejarla reflejada en la fila hace que la pantalla no mienta.
        $apagar = $conexion->emision_activa && $modo !== ConexionFacturacion::MODO_PRODUCCION;

        $mensaje = $apagar
            ? 'Conexión correcta, pero el emisor tiene esta empresa en modo ' . mb_strtoupper($modo)
                . '. Se ha desactivado la emisión.'
            : 'Conexión correcta.';

        $conexion->update([
            'modo'                => $modo,
            'razon_social_emisor' => trim((string) (($remota['empresa']['razon_social'] ?? null) ?: ''))
                ?: $conexion->razon_social_emisor,
            'emision_activa'      => $apagar ? false : $conexion->emision_activa,
            'diagnostico_at'      => now(),
            'diagnostico_ok'      => true,
            'diagnostico_mensaje' => $mensaje,
        ]);

        $this->facturacion->olvidar($empresa->id);

        return ['ok' => true, 'mensaje' => $mensaje, 'modo' => $modo];
    }

    /**
     * Sin tabla no hay nada que gestionar: el SQL de producción no se ha ejecutado.
     * Se dice con todas las letras en vez de dejar salir un "relation does not exist".
     */
    private function exigirTabla(): void
    {
        if (! $this->facturacion->tablaDisponible()) {
            throw ValidationException::withMessages([
                'codigo' => 'La facturación electrónica no está instalada en este servidor: '
                    . 'falta ejecutar produccion/sql/2026_07_facturacion_conexion_empresa.sql.',
            ]);
        }
    }

    /** Los RUC se comparan como dígitos: espacios o guiones no pueden decidir esto. */
    private function soloDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }
}
