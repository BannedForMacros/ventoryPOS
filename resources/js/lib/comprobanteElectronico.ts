/**
 * Reglas y etiquetas de la facturación electrónica (SUNAT), compartidas por el
 * POS (validación ANTES de cobrar) y el detalle de venta (estado del CPE).
 *
 * Está aquí y no dentro de una pantalla para que los DOS selectores de
 * comprobante del POS (barra superior y barra móvil) usen exactamente la misma
 * lógica, sin duplicarla.
 *
 * NADA de esto afecta a las ventas `ticket`: sin comprobante electrónico todas
 * las funciones devuelven null / "sin bloqueo".
 */
import type { Cliente, ComprobanteEstado, ModoFacturacion } from '@/types';

/** Tipos de comprobante del POS (ventas.tipo_comprobante). */
export type TipoComprobantePos = 'ticket' | 'boleta' | 'factura' | 'boleta_externa' | 'factura_externa';

/** Comprobantes que no se emiten electrónicamente por el sistema. */
export const COMPROBANTES_SIN_EMISION: TipoComprobantePos[] = ['ticket', 'boleta_externa', 'factura_externa'];

/**
 * Umbral SUNAT: desde este importe una boleta exige adquirente identificado.
 *
 * ES UN DEFAULT DE ÚLTIMO RECURSO, no "el umbral del POS": el valor bueno lo
 * dicta el EMISOR y llega en `facturacion.umbral_boleta_identificada` (§7 del
 * contrato). Este 700 solo se usa si el backend no mandó nada —módulo apagado o
 * props antiguas—, y coincide a propósito con el default de
 * ConfiguracionFacturacion para que los dos lados fallen igual.
 *
 * NO lo cambies aquí para adaptar una tienda: cámbialo en FacturaMac. Un umbral
 * distinto en cada sistema es exactamente el bug que esto viene a cerrar (con
 * 500 en el emisor y 700 aquí, el POS deja cobrar una boleta que el emisor
 * rechaza DESPUÉS).
 */
export const UMBRAL_BOLETA_IDENTIFICADA = 700;

/** Aviso de modo de emisión que el POS pinta sobre la barra de comprobante. */
export interface AvisoModoEmision {
    /** Texto exacto de la franja. */
    texto:  string;
    /** Color de fondo (var(--color-*)). */
    fondo:  string;
    /** Color del texto sobre ese fondo. */
    tinta:  string;
    /** true = irreversible; merece el icono de alerta y el tono más fuerte. */
    grave:  boolean;
}

/**
 * Traduce el `modo` del emisor a la franja que ve la cajera.
 *
 * POR QUÉ HACEN FALTA TRES AVISOS Y NO UN BOOLEANO: antes esto salía de
 * `/ping` + `sunat_beta`, que solo distingue "beta" de "todo lo demás". Con eso,
 * `simulacion` (nada sale del emisor) y `produccion` (documento fiscal real e
 * irreversible) se pintaban IGUAL. Son las dos situaciones que más importa no
 * confundir.
 *
 * FAIL-SAFE: `desconocido` —el emisor no respondió— y cualquier valor que no
 * reconozcamos se pintan como PRODUCCIÓN. Un aviso de más no cuesta nada; uno de
 * menos cuesta una Nota de Crédito.
 */
export function avisoModoEmision(
    modo: ModoFacturacion | string | null | undefined,
): AvisoModoEmision | null {
    // Emisión apagada en el emisor: no se crea comprobante ni se consume
    // correlativo (§4.2). No hay nada de lo que avisar.
    if (modo === 'desactivado') return null;

    if (modo === 'simulacion') {
        return {
            texto: 'SIMULACIÓN — se numera en serie de ensayo y NO se envía a SUNAT',
            fondo: 'var(--color-text-muted)', tinta: '#fff', grave: false,
        };
    }

    if (modo === 'beta') {
        return {
            texto: 'SUNAT BETA — entorno de pruebas: los comprobantes no tienen valor fiscal',
            fondo: 'var(--color-warning)', tinta: '#fff', grave: false,
        };
    }

    return {
        texto: '⚠️ SUNAT PRODUCCIÓN — los comprobantes son reales',
        fondo: 'var(--color-danger)', tinta: '#fff', grave: true,
    };
}

/**
 * Rutas del comprobante electrónico de una venta
 * (ver Ventas\ComprobanteElectronicoController). Se escriben literales, sin
 * `route()`, para que la pantalla no reviente si Ziggy todavía no conoce los
 * nombres (despliegue parcial, caché de rutas vieja).
 */
export const rutaComprobante = {
    estado:       (ventaId: number) => `/ventas/${ventaId}/comprobante/estado`,
    reintentar:   (ventaId: number) => `/ventas/${ventaId}/comprobante/reintentar`,
    pdf:          (ventaId: number) => `/ventas/${ventaId}/comprobante/pdf`,
    enviarCorreo: (ventaId: number) => `/ventas/${ventaId}/comprobante/enviar-correo`,
};

/**
 * Datos para hacerle llegar el comprobante al cliente. Los arma el BACKEND
 * (necesita la URL firmada del PDF y el teléfono del cliente) y viajan dentro
 * de la respuesta de `estado`, SOLO cuando el comprobante ya está emitido.
 *
 * `whatsapp` es un enlace wa.me normal con el mensaje ya escrito: se abre en
 * una pestaña y la conversación la manda la persona. No hay API de por medio.
 */
export interface CompartirComprobante {
    /** https://wa.me/51...?text=... — null si el cliente no tiene teléfono. */
    whatsapp:      string | null;
    /** URL firmada y pública del PDF (la que viaja en el mensaje). */
    pdf_publico:   string | null;
    /** Correo guardado del cliente; null → hay que pedirlo antes de enviar. */
    email_cliente: string | null;
}

/**
 * Respuesta de GET /ventas/{venta}/comprobante/estado.
 * Es plana a propósito (el POS la consulta cada pocos segundos).
 */
export interface EstadoComprobanteResp {
    tiene_comprobante:  boolean;
    /** false = esta venta nunca va a tener comprobante (ticket o módulo apagado). */
    emitible:           boolean;
    id?:                number;
    tipo?:              '01' | '03';
    serie?:             string;
    correlativo?:       number | string;
    /** Puede llegar null: un comprobante que falló al mapear nunca se numeró. */
    numero?:            string | null;
    estado?:            ComprobanteEstado | null;
    mensaje?:           string;
    hash_cpe?:          string | null;
    qr?:                string | null;
    sunat_codigo?:      string | null;
    sunat_descripcion?: string | null;
    error?:             string | null;
    intentos?:          number;
    enviado_at?:        string | null;
    puede_reintentar?:  boolean;
    tiene_pdf?:         boolean;
    /** Estado terminal según el backend: se puede dejar de preguntar. */
    final?:             boolean;
    /** Solo cuando el comprobante ya está emitido (ver CompartirComprobante). */
    compartir?:         CompartirComprobante | null;
}

/** El Cliente General no identifica al adquirente (flag `es_cliente_general`). */
export function esClienteGeneral(cliente: Cliente | null | undefined): boolean {
    if (!cliente) return true;
    if ((cliente as Cliente & { es_cliente_general?: boolean }).es_cliente_general) return true;
    // Fallback legado por si el backend no envía la flag.
    return cliente.numero_documento === '99999999';
}

/** Nombre imprimible del cliente (razón social o nombres + apellidos). */
export function nombreCliente(cliente: Cliente | null | undefined): string {
    if (!cliente) return 'Cliente general';
    return cliente.razon_social ?? `${cliente.nombres ?? ''} ${cliente.apellidos ?? ''}`.trim();
}

/** Motivo por el que NO se puede cerrar la venta con el comprobante elegido. */
export interface BloqueoComprobante {
    /** Texto corto y accionable para la cajera. */
    motivo: string;
    /** true → el problema se resuelve eligiendo/creando un cliente. */
    requiereCliente: boolean;
}

interface ArgsValidacion {
    tipoComprobante: TipoComprobantePos;
    cliente:         Cliente | null;
    /** Total de la venta tal como lo ve la cajera. */
    total:           number;
    moneda:          'PEN' | 'USD';
    /** Umbral SUNAT de boleta identificada (default 700). */
    umbral?:         number;
    /**
     * ¿Está encendida la emisión electrónica DE ESTA EMPRESA?
     * (`FacturacionEmpresa::activa($empresaId)`, no un flag del `.env`).
     * Debe reflejar EXACTAMENTE el mismo gate que StoreVentaRequest: si el
     * backend no valida, el POS tampoco debe bloquear, o le estaríamos
     * impidiendo a la cajera algo que hoy puede hacer sin consecuencias.
     * Default false = comportamiento previo a la integración.
     */
    emisionActiva?:  boolean;
}

/**
 * §5.4 del plan. Devuelve el motivo de bloqueo o null si se puede cobrar.
 *
 * El objetivo es que la cajera se entere ANTES de cobrar: SUNAT rechaza una
 * factura sin RUC + dirección, y exige identificar al adquirente en boletas
 * desde S/ 700. Descubrirlo después de cobrar obliga a Nota de Crédito.
 */
export function validarComprobante({
    tipoComprobante, cliente, total, moneda, umbral = UMBRAL_BOLETA_IDENTIFICADA,
    emisionActiva = false,
}: ArgsValidacion): BloqueoComprobante | null {
    // Con la integración apagada, 'boleta' y 'factura' son hoy meras etiquetas
    // que no emiten nada. Bloquear la venta por reglas de SUNAT que nadie va a
    // aplicar sería una regresión pura para la caja. El backend
    // (StoreVentaRequest::validarComprobanteElectronico) tiene este mismo gate:
    // los dos lados deben encenderse a la vez o la cajera vería un bloqueo en
    // pantalla sin respaldo, o peor, un 422 tras cobrar.
    if (! emisionActiva) return null;

    // Nota de venta interna o comprobantes externos: no se emite nada a SUNAT.
    if (tipoComprobante === 'ticket' || tipoComprobante === 'boleta_externa' || tipoComprobante === 'factura_externa') return null;

    // G12 — v1 emite siempre en soles (es lo que la venta contabiliza).
    if (moneda === 'USD') {
        return {
            motivo: 'Los comprobantes electrónicos solo se emiten en soles. Cambia la moneda a S/ o elige "Sin comprobante".',
            requiereCliente: false,
        };
    }

    if (tipoComprobante === 'factura') {
        const tieneRuc       = (cliente?.tipo_documento ?? '').toUpperCase() === 'RUC'
            && !!(cliente?.numero_documento ?? '').trim();
        const tieneDireccion = !!(cliente?.direccion ?? '').trim();
        if (esClienteGeneral(cliente) || !tieneRuc || !tieneDireccion) {
            return {
                motivo: 'Una factura requiere cliente con RUC y dirección.',
                requiereCliente: true,
            };
        }
        return null;
    }

    // Boleta: desde el umbral, SUNAT exige identificar al adquirente.
    // El margen de 0.01 es el mismo que aplica StoreVentaRequest: si el backend
    // va a rechazar la venta, la cajera tiene que enterarse ANTES de cobrar.
    if (total >= umbral - 0.01 && esClienteGeneral(cliente)) {
        return {
            motivo: `Desde S/ ${umbral} SUNAT exige identificar al cliente en la boleta. Selecciona un cliente con DNI.`,
            requiereCliente: true,
        };
    }

    return null;
}

/** Etiqueta larga del comprobante que se va a emitir. */
export function etiquetaComprobante(tipo: TipoComprobantePos): string {
    if (tipo === 'factura')         return 'Factura electrónica';
    if (tipo === 'factura_externa') return 'Factura electrónica externa';
    if (tipo === 'boleta')          return 'Boleta de venta electrónica';
    if (tipo === 'boleta_externa')  return 'Boleta de venta electrónica externa';
    return 'Nota de venta interna';
}

/** Etiqueta del comprobante YA emitido, según el catálogo 01 de SUNAT. */
export function etiquetaTipoSunat(tipo: string | null | undefined): string {
    if (tipo === '01') return 'Factura electrónica';
    if (tipo === '03') return 'Boleta de venta electrónica';
    return 'Comprobante electrónico';
}

/** Presentación de un estado del CPE: texto, color y si conviene seguir consultando. */
export interface EstadoMeta {
    label:  string;
    /** Color de la marca (var(--color-*)) para texto/borde. */
    color:  string;
    /** true = ya no va a cambiar solo: no tiene sentido seguir consultando. */
    terminal: boolean;
    /** Explicación de una línea para la cajera (null si el estado se explica solo). */
    detalle: string | null;
}

/**
 * Estados en los que todavía puede cambiar algo solo (job en curso, reintento
 * automático o resumen diario pendiente) → tiene sentido seguir consultando.
 * `error_mapeo` NO está: ahí el problema es el dato de la venta y no se
 * resuelve esperando.
 */
const ESTADOS_EN_CURSO: ComprobanteEstado[] = [
    'pendiente', 'enviando', 'enviado', 'pendiente_resumen', 'en_resumen',
    'pendiente_anulacion', 'error_envio',
];

const ESTADOS: Record<ComprobanteEstado, EstadoMeta> = {
    pendiente: {
        label: 'En cola', color: 'var(--color-text-muted)', terminal: false,
        detalle: 'Aún no se envía. Se emitirá en unos segundos.',
    },
    enviando: {
        label: 'Enviando a SUNAT', color: 'var(--color-primary)', terminal: false,
        detalle: 'SUNAT está procesando el comprobante.',
    },
    enviado: {
        label: 'Enviado', color: 'var(--color-primary)', terminal: false,
        detalle: 'Ya salió hacia SUNAT; falta interpretar la constancia.',
    },
    pendiente_resumen: {
        label: 'En resumen diario', color: 'var(--color-warning)', terminal: false,
        detalle: 'Las boletas se informan a SUNAT en el resumen del día: el estado final llega después. No falló nada.',
    },
    en_resumen: {
        label: 'Informado en resumen', color: 'var(--color-primary)', terminal: false,
        detalle: 'La boleta ya viaja en un resumen enviado a SUNAT. Falta su respuesta.',
    },
    pendiente_anulacion: {
        label: 'Anulación en trámite', color: 'var(--color-warning)', terminal: false,
        detalle: 'Se comunicó la baja a SUNAT y falta su confirmación.',
    },
    simulado: {
        label: 'Simulado', color: 'var(--color-text-muted)', terminal: true,
        detalle: 'Ensayo: se numeró en la serie de simulación y NO se envió a SUNAT. No tiene valor fiscal.',
    },
    no_emitido: {
        label: 'Sin emitir', color: 'var(--color-text-muted)', terminal: true,
        detalle: 'La emisión electrónica está desactivada para esta empresa. La venta quedó registrada con normalidad.',
    },
    aceptado: {
        label: 'Aceptado por SUNAT', color: 'var(--color-success)', terminal: true,
        detalle: null,
    },
    rechazado: {
        label: 'Rechazado por SUNAT', color: 'var(--color-danger)', terminal: true,
        detalle: 'SUNAT rechazó el comprobante. Corrige el motivo y reintenta.',
    },
    error_envio: {
        label: 'Error de envío', color: 'var(--color-danger)', terminal: false,
        detalle: 'No se pudo llegar a SUNAT. Se reintenta solo; también puedes reintentar ahora.',
    },
    error_mapeo: {
        label: 'Error de datos', color: 'var(--color-danger)', terminal: true,
        detalle: 'Los importes no cuadran y no se emitió nada. Avisa al administrador.',
    },
    anulado: {
        label: 'Anulado', color: 'var(--color-text-muted)', terminal: true,
        detalle: 'El comprobante fue dado de baja ante SUNAT.',
    },
};

const ESTADO_DESCONOCIDO: EstadoMeta = {
    label: 'Estado desconocido', color: 'var(--color-text-muted)', terminal: true, detalle: null,
};

export function metaEstado(estado: ComprobanteEstado | string | null | undefined): EstadoMeta {
    return ESTADOS[estado as ComprobanteEstado] ?? ESTADO_DESCONOCIDO;
}

/** ¿Tiene sentido seguir consultando el estado? (polling) */
export function estadoEnCurso(estado: ComprobanteEstado | string | null | undefined): boolean {
    return ESTADOS_EN_CURSO.includes(estado as ComprobanteEstado);
}

/**
 * Estados desde los que reintentar el envío es una acción válida.
 * Espejo de VentaComprobante::ESTADOS_REINTENTABLES: el reintento va SIEMPRE
 * sobre el mismo correlativo (G11) y `error_mapeo` queda fuera porque ahí hay
 * que corregir el dato de la venta, no reenviar.
 */
export function puedeReintentar(estado: ComprobanteEstado | string | null | undefined): boolean {
    return estado === 'pendiente' || estado === 'error_envio' || estado === 'rechazado';
}

/**
 * Estados en los que el comprobante YA existe para el cliente: tiene número y
 * se le puede mandar. Espejo de VentaComprobante::estadosEmitidos(), que a su
 * vez deriva de EstadoComprobante::bloqueaAnulacion() en el paquete compartido.
 *
 * `simulado` queda fuera a propósito: es un ensayo sin valor fiscal y mandárselo
 * al cliente sería peor que no mandar nada.
 *
 * `anulado` SÍ entra: el documento existe, tiene número y su PDF es real (lo que
 * lo anula es la nota de crédito, que es otro documento). El backend lo trata
 * igual desde que se corrigió la lista, y una divergencia aquí dejaría el botón
 * de compartir escondido para algo que el servidor sí ofrece.
 */
const ESTADOS_EMITIDOS: ComprobanteEstado[] = [
    'aceptado', 'enviando', 'enviado', 'pendiente_resumen', 'en_resumen',
    'pendiente_anulacion', 'anulado',
];

/** ¿El comprobante ya se puede compartir con el cliente? */
export function comprobanteEmitido(estado: ComprobanteEstado | string | null | undefined): boolean {
    return ESTADOS_EMITIDOS.includes(estado as ComprobanteEstado);
}

/**
 * Estados que exigen que alguien haga algo. Sirven para que en la LISTA de
 * ventas destaquen sobre el resto: la cajera repasa cien filas buscando
 * justo estas.
 */
export function comprobanteConProblema(estado: ComprobanteEstado | string | null | undefined): boolean {
    return estado === 'rechazado' || estado === 'error_envio' || estado === 'error_mapeo';
}
