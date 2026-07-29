<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Jobs\EmitirComprobanteElectronico;
use App\Mail\ComprobanteElectronicoMail;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\AuditoriaService;
use App\Services\Facturacion\CompartirComprobante;
use App\Services\Facturacion\FacturacionEmpresa;
use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\FacturaMacException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * V8 — Cara visible del comprobante electrónico para el POS y la ficha de venta.
 *
 *  · estado()        → JSON liviano para el polling del POS tras cerrar la venta.
 *  · reintentar()    → re-encola la emisión cuando quedó en error o rechazada.
 *  · pdf()           → proxy del PDF de FacturaMac (interno, exige sesión).
 *  · pdfPublico()    → el MISMO PDF por URL firmada, para el cliente final.
 *  · enviarCorreo()  → manda el comprobante al correo del cliente, con PDF adjunto.
 *
 * Nada de esto llama a SUNAT en línea salvo el PDF: el estado se sirve desde
 * `venta_comprobantes`, que los jobs mantienen al día. Un POS que pregunta cada
 * pocos segundos no puede depender de la latencia de un tercero.
 */
class ComprobanteElectronicoController extends Controller
{
    /** @var array<int, bool> Memoria por request de la comprobación, POR EMPRESA. */
    private array $cpeDisponible = [];

    /**
     * ¿Se puede consultar `venta_comprobantes` de ESTA empresa sin riesgo de
     * reventar la pantalla?
     *
     * Una empresa puede no estar conectada (o estarlo pero con la emisión apagada)
     * y la migración del módulo puede no estar aplicada: consultar la tabla en esa
     * situación es un 500. Copia deliberada del criterio de VentaService /
     * DevolucionService / TicketPrintService — la facturación electrónica nunca
     * puede tumbar al POS.
     *
     * La memoria va POR EMPRESA y en la instancia (una por request), no en un
     * `static`: este controlador sirve a los dos contribuyentes de la instalación y
     * una respuesta compartida haría que la empresa conectada decidiera por la que
     * no lo está. Y en `static` un test heredaría la respuesta del anterior.
     */
    private function comprobantesDisponibles(int $empresaId): bool
    {
        if (array_key_exists($empresaId, $this->cpeDisponible)) {
            return $this->cpeDisponible[$empresaId];
        }

        if (! app(FacturacionEmpresa::class)->activa($empresaId)) {
            return $this->cpeDisponible[$empresaId] = false;
        }

        return $this->cpeDisponible[$empresaId] = $this->tablaComprobantes();
    }

    /**
     * ¿Existe la tabla `venta_comprobantes`?
     *
     * Aparte de `comprobantesDisponibles()` porque `pdfPublico()` tiene un problema
     * de huevo y gallina: no hay usuario del que deducir la empresa, y la empresa
     * está en el comprobante… que vive precisamente en esa tabla. Así que primero
     * se comprueba que la tabla exista, luego se lee el comprobante, y solo
     * entonces se puede preguntar por la empresa.
     */
    private function tablaComprobantes(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('venta_comprobantes');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Estado del comprobante de una venta. Pensado para que el POS lo consulte
     * cada pocos segundos tras cobrar sin costo apreciable (una fila, sin red).
     */
    public function estado(Request $request, Venta $venta, CompartirComprobante $compartir): JsonResponse
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        // La guarda va ANTES de la consulta, no después.
        //
        // Estaba después: se consultaba `venta_comprobantes` y solo entonces se
        // miraba `facturamac.enabled` para calcular `emitible`. En una instalación
        // con el módulo apagado —el default— y la migración sin aplicar, esa
        // consulta es un 500. El módulo tiene que ser INERTE cuando está apagado,
        // y eso incluye no tocar sus tablas. Mismo criterio que VentaService,
        // DevolucionService y TicketPrintService.
        $ce = $this->comprobantesDisponibles($venta->empresa_id)
            ? $venta->comprobanteElectronico()->first()
            : null;

        if (!$ce) {
            return response()->json([
                'tiene_comprobante' => false,
                // `emitible` le dice al POS si vale la pena seguir preguntando:
                // un ticket nunca va a tener comprobante, y con el módulo apagado
                // tampoco lo va a tener ninguna venta.
                'emitible' => $venta->tipo_comprobante !== 'ticket' && $this->comprobantesDisponibles($venta->empresa_id),
                'estado'   => null,
                'mensaje'  => $venta->tipo_comprobante === 'ticket'
                    ? 'Nota de venta interna: no se informa a SUNAT.'
                    : 'La emisión está en cola.',
            ]);
        }

        // ── Bloque `compartir` ────────────────────────────────────────────
        // SOLO si el comprobante ya está emitido. Compartir un comprobante que
        // aún está en cola o que falló sería mandarle al cliente un enlace a un
        // PDF que no existe — un 404 en su móvil y una llamada a la tienda.
        // Cuando no procede, la clave NO aparece: así el frontend decide con
        // `if (data.compartir)` y no puede pintar botones con enlaces vacíos.
        // Se calcula aquí y no en el navegador porque la firma exige APP_KEY.
        //
        // Se exige TAMBIÉN `facturamac_id`, no solo `esEmitido()`: el estado
        // `enviando` cuenta como emitido (SUNAT ya lo va a conocer y bloquea la
        // anulación) pero todavía no tiene id en FacturaMac, así que el PDF aún
        // no existe. Sin esta segunda condición habría una ventana de segundos
        // en la que el botón de compartir manda al cliente a un 404.
        $compartirBloque = [];
        if ($ce->esEmitido() && $ce->facturamac_id) {
            // La relación ya está en memoria: se ahorra una consulta por polling.
            $ce->setRelation('venta', $venta);
            $compartirBloque = ['compartir' => $compartir->payload($ce)];
        }

        return response()->json([
            'tiene_comprobante' => true,
            'emitible'          => true,
            'id'                => $ce->id,
            'tipo'              => $ce->tipo,
            'serie'             => $ce->serie,
            'correlativo'       => $ce->correlativo,
            'numero'            => $ce->numero,
            'estado'            => $ce->estado,
            'mensaje'           => $this->mensajeDeEstado($ce->estado),
            'hash_cpe'          => $ce->hash_cpe,
            'qr'                => $ce->qr,
            'sunat_codigo'      => $ce->sunat_codigo,
            'sunat_descripcion' => $ce->sunat_descripcion,
            'error'             => $ce->error,
            'intentos'          => (int) $ce->intentos,
            'enviado_at'        => $ce->enviado_at,
            'puede_reintentar'  => $ce->puedeReintentar(),
            'tiene_pdf'         => (bool) $ce->facturamac_id,
            // Estado terminal: el POS puede dejar de preguntar. La lista vive en
            // el modelo porque estaba repetida —y desalineada— en tres sitios.
            'final'             => $ce->esFinal(),
            ...$compartirBloque,
        ]);
    }

    /**
     * Reintento manual. No re-emite nada por su cuenta: solo vuelve a encolar
     * el job, que es el único que sabe distinguir entre "emitir de nuevo" y
     * "reenviar el mismo correlativo" (G11).
     */
    public function reintentar(Request $request, Venta $venta)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);
        abort_if($venta->tipo_comprobante === 'ticket', 422,
            'Un ticket es una nota de venta interna: no se emite ante SUNAT.');
        // Misma guarda que el resto: apagado o sin migrar, ni se consulta la tabla.
        abort_unless($this->comprobantesDisponibles($venta->empresa_id), 422,
            'La facturación electrónica de esta empresa está desactivada.');

        $ce = $venta->comprobanteElectronico()->first();

        // Sin fila previa (la emisión nunca llegó a arrancar) el reintento es
        // simplemente la primera emisión.
        if ($ce && !$ce->puedeReintentar()) {
            abort(422, $ce->esEmitido()
                ? "El comprobante {$ce->numero} ya fue informado a SUNAT."
                : 'Este comprobante no admite reintentos.');
        }

        EmitirComprobanteElectronico::dispatch($venta);

        $mensaje = "Reintentando la emisión del comprobante de la venta {$venta->numero}.";

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['message' => $mensaje]);
        }

        return back()->with('success', $mensaje);
    }

    /**
     * PDF del comprobante. Se proxea a propósito: el token de FacturaMac vive
     * en el servidor y NUNCA debe llegar al navegador del cajero. De paso, el
     * permiso de ventas sigue aplicando sobre el documento.
     */
    public function pdf(Request $request, Venta $venta, FacturacionEmpresa $facturacion)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        // Sin módulo no hay PDF que proxear (ni tabla que consultar): 404 limpio
        // en vez de un 500 por una relación inexistente.
        $ce = $this->comprobantesDisponibles($venta->empresa_id)
            ? $venta->comprobanteElectronico()->first()
            : null;

        abort_unless($ce && $ce->facturamac_id, 404,
            'Esta venta todavía no tiene comprobante electrónico emitido.');

        $ce->setRelation('venta', $venta);

        // El PDF se pide con el token de LA EMPRESA DE LA VENTA. Con el token
        // global del `.env` este proxy habría pedido a MacSoft el PDF de un
        // comprobante de HYC: un 404 en el mejor caso, el documento de otro
        // contribuyente en el peor.
        return $this->responderPdf($ce, $facturacion->cliente($venta->empresa_id));
    }

    /**
     * EL MISMO PDF, pero para el CLIENTE FINAL: ruta pública protegida por firma
     * temporal (middleware `signed`), sin sesión ni permisos.
     *
     * POR QUÉ EXISTE: el cliente no es usuario del POS. Mandarle por WhatsApp el
     * enlace de `pdf()` solo le enseña la pantalla de login. Y una ruta pública
     * "a pelo" sería enumerable por id: bastaría recorrer /comprobante/1..N para
     * leerse los comprobantes de toda la instalación (nombres, RUC, importes).
     *
     * La firma resuelve las dos cosas a la vez: el id viaja con un HMAC derivado
     * de APP_KEY y una fecha de caducidad. Tocar el id, la caducidad o cualquier
     * parte de la URL invalida la firma y Laravel responde 403 por su cuenta,
     * antes de llegar aquí — por eso este método no valida nada de eso.
     *
     * OJO 1 — El parámetro se recibe como ESCALAR y el modelo se busca a mano,
     * en lugar de dejar que actúe el route-model binding. No es un descuido:
     * `SubstituteBindings` va en el grupo `web` y por tanto se ejecuta ANTES que
     * el middleware `signed` de la ruta. Con binding automático, un id inexistente
     * moría en 404 sin llegar a comprobar la firma, y uno existente con firma mala
     * daba 403 — es decir, la diferencia entre 404 y 403 delataba qué ids existen.
     * Un oráculo de enumeración justo en lo que esta ruta debía evitar. Buscando
     * el modelo aquí dentro, la firma se valida SIEMPRE primero y quien no la trae
     * recibe 403 exista o no el comprobante.
     *
     * OJO 2 — No hay comprobación de empresa, y es CORRECTO que no la haya: no
     * hay usuario del que deducir una empresa. La autorización aquí es poseer el
     * enlace firmado. Ver App\Services\Facturacion\CompartirComprobante.
     */
    public function pdfPublico(string $ventaComprobante, FacturacionEmpresa $facturacion)
    {
        // OJO 3 — Aquí NO hay usuario, así que la empresa no se puede deducir de la
        // sesión: sale del comprobante. Por eso el orden es tabla → comprobante →
        // empresa → token, y no el de los demás métodos. Lo que NO cambia es de
        // quién es el token con el que se pide el PDF: el de la empresa dueña del
        // comprobante, nunca uno global.
        $ce = $this->tablaComprobantes()
            ? VentaComprobante::with('venta:id,empresa_id,numero')->find($ventaComprobante)
            : null;

        abort_unless($ce && $ce->facturamac_id && $ce->venta, 404,
            'Este comprobante todavía no está disponible.');

        $empresaId = (int) $ce->venta->empresa_id;

        abort_unless($this->comprobantesDisponibles($empresaId), 404,
            'Este comprobante todavía no está disponible.');

        return $this->responderPdf($ce, $facturacion->cliente($empresaId));
    }

    /**
     * Envía el comprobante al correo del cliente, con el PDF adjunto y el enlace
     * firmado en el cuerpo.
     *
     * A diferencia de WhatsApp —donde el enlace lo abre la persona— aquí sí
     * SALE un correo del servidor, de ahí el throttle en la ruta.
     */
    public function enviarCorreo(
        Request $request,
        Venta $venta,
        FacturacionEmpresa $facturacion,
        CompartirComprobante $compartir,
    ) {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        $datos = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $ce = $this->comprobantesDisponibles($venta->empresa_id)
            ? $venta->comprobanteElectronico()->first()
            : null;

        // Mismo criterio que el bloque `compartir` de estado(): sin comprobante
        // emitido no hay nada que mandar, y prometerlo por correo es peor que
        // negarlo aquí.
        abort_unless($ce && $ce->esEmitido(), 422,
            'El comprobante electrónico de esta venta todavía no está emitido.');

        $ce->setRelation('venta', $venta);

        // El correo del formulario manda sobre el registrado: la cajera puede
        // corregirlo en el momento sin tener que editar la ficha del cliente.
        $destino = trim((string) ($datos['email'] ?? '')) ?: $compartir->emailCliente($ce);

        if (! $destino) {
            // 422 con mensaje para la CAJERA, no para el programador: es quien
            // lo va a leer en pantalla y quien tiene que saber qué hacer.
            return response()->json([
                'message' => 'Este cliente no tiene correo registrado. Escribe uno para enviarle el comprobante.',
                'errors'  => ['email' => ['Indica un correo de destino.']],
            ], 422);
        }

        // El PDF se descarga AQUÍ y no dentro del Mailable: si FacturaMac falla,
        // este es el único sitio donde todavía se le puede decir algo útil a la
        // cajera. Y si falla, el correo se manda igual SIN adjunto: el enlace
        // firmado del cuerpo seguirá funcionando cuando FacturaMac vuelva, así
        // que es mejor que el cliente reciba algo a que no reciba nada.
        $pdf = null;
        if ($ce->facturamac_id) {
            try {
                $pdf = $facturacion->cliente($venta->empresa_id)->pdf((int) $ce->facturamac_id);
            } catch (FacturaMacException $e) {
                Log::warning('Correo del comprobante: no se pudo adjuntar el PDF', [
                    'venta_id'      => $venta->id,
                    'facturamac_id' => $ce->facturamac_id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        // `send` y no `queue`: si el envío falla (SMTP caído, correo inexistente)
        // la cajera se entera EN EL ACTO y puede probar otra dirección. Encolarlo
        // le diría "enviado" a algo que quizá nunca salga, y además metería el
        // binario del PDF entero dentro del payload del job.
        Mail::to($destino)->send(new ComprobanteElectronicoMail($ce, $pdf));

        AuditoriaService::log('comprobante.enviado_correo', $venta, [
            'venta_numero'       => $venta->numero,
            'comprobante_numero' => $ce->numero,
            'email'              => $destino,
            'con_adjunto'        => $pdf !== null,
        ], $request->user());

        $mensaje = "Comprobante enviado a {$destino}.";

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'message'     => $mensaje,
                'email'       => $destino,
                'con_adjunto' => $pdf !== null,
            ]);
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Descarga el PDF de FacturaMac y lo devuelve al navegador.
     *
     * Se proxea a propósito: el token de FacturaMac vive en el servidor y NUNCA
     * debe llegar al navegador —ni al del cajero ni, mucho menos, al del cliente.
     * Compartido entre la ruta interna y la firmada para que ambas devuelvan
     * exactamente el mismo documento con las mismas cabeceras.
     */
    private function responderPdf(VentaComprobante $ce, FacturaMacClient $client)
    {
        try {
            $binario = $client->pdf((int) $ce->facturamac_id);
        } catch (FacturaMacException $e) {
            Log::warning('No se pudo obtener el PDF del comprobante', [
                'venta_id'      => $ce->venta_id,
                'facturamac_id' => $ce->facturamac_id,
                'error'         => $e->getMessage(),
            ]);
            abort(502, 'No se pudo obtener el PDF desde FacturaMac. Intenta de nuevo en unos segundos.');
        }

        $nombre = ($ce->numero ?: 'comprobante-' . ($ce->venta?->numero ?? $ce->id)) . '.pdf';

        return response($binario, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombre . '"',
        ]);
    }

    /** Traducción del estado técnico a algo que una cajera entienda. */
    private function mensajeDeEstado(?string $estado): string
    {
        return match ($estado) {
            'pendiente'         => 'En cola para enviarse a SUNAT.',
            'enviando'          => 'Enviándose a SUNAT…',
            // G10 — La boleta NO está aceptada todavía: viaja en el Resumen
            // Diario de las 23:55. Decir "aceptada" aquí sería mentir.
            'pendiente_resumen' => 'Se informará a SUNAT en el resumen diario de hoy.',
            'aceptado'          => 'Aceptado por SUNAT.',
            'rechazado'         => 'Rechazado por SUNAT. Revisa el detalle del error.',
            'error_envio'       => 'No se pudo enviar a SUNAT. Puedes reintentar.',
            'error_mapeo'       => 'La venta no se pudo convertir en comprobante. Requiere revisión del administrador.',
            'anulado'           => 'Anulado ante SUNAT.',
            default             => 'Estado desconocido.',
        };
    }
}
