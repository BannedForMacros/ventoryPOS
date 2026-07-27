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
import type { Cliente, ComprobanteEstado } from '@/types';

/** Tipos de comprobante del POS (ventas.tipo_comprobante). */
export type TipoComprobantePos = 'ticket' | 'boleta' | 'factura';

/**
 * Umbral SUNAT: desde este importe una boleta exige adquirente identificado.
 * Default local; el backend puede sobreescribirlo con
 * config('facturamac.umbral_boleta_identificada').
 */
export const UMBRAL_BOLETA_IDENTIFICADA = 700;

/** Rutas del comprobante electrónico de una venta (ver ComprobanteElectronicoController). */
export const rutaComprobante = {
    estado:     (ventaId: number) => `/ventas/${ventaId}/comprobante/estado`,
    reintentar: (ventaId: number) => `/ventas/${ventaId}/comprobante/reintentar`,
    pdf:        (ventaId: number) => `/ventas/${ventaId}/comprobante/pdf`,
};

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
}: ArgsValidacion): BloqueoComprobante | null {
    // Nota de venta interna: no se emite nada a SUNAT. Cero validaciones.
    if (tipoComprobante === 'ticket') return null;

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
        if (!tieneRuc || !tieneDireccion) {
            return {
                motivo: 'Una factura requiere cliente con RUC y dirección.',
                requiereCliente: true,
            };
        }
        return null;
    }

    // Boleta: desde el umbral, SUNAT exige identificar al adquirente.
    if (total >= umbral && esClienteGeneral(cliente)) {
        return {
            motivo: `Desde S/ ${umbral} SUNAT exige identificar al cliente en la boleta. Selecciona un cliente con DNI.`,
            requiereCliente: true,
        };
    }

    return null;
}

/** Etiqueta larga del comprobante que se va a emitir. */
export function etiquetaComprobante(tipo: TipoComprobantePos): string {
    if (tipo === 'factura') return 'Factura electrónica';
    if (tipo === 'boleta')  return 'Boleta de venta electrónica';
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

const ESTADOS: Record<ComprobanteEstado, EstadoMeta> = {
    pendiente: {
        label: 'En cola', color: 'var(--color-text-muted)', terminal: false,
        detalle: 'Aún no se envía. Se emitirá en unos segundos.',
    },
    enviando: {
        label: 'Enviando a SUNAT', color: 'var(--color-primary)', terminal: false,
        detalle: 'SUNAT está procesando el comprobante.',
    },
    pendiente_resumen: {
        label: 'En resumen diario', color: 'var(--color-warning)', terminal: false,
        detalle: 'Las boletas se informan a SUNAT en el resumen del día: el estado final llega después. No falló nada.',
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
        label: 'Error de envío', color: 'var(--color-danger)', terminal: true,
        detalle: 'No se pudo llegar a SUNAT. Puedes reintentar el envío.',
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
    return !metaEstado(estado).terminal;
}

/** Estados desde los que reintentar el envío es una acción válida. */
export function puedeReintentar(estado: ComprobanteEstado | string | null | undefined): boolean {
    return estado === 'rechazado' || estado === 'error_envio' || estado === 'error_mapeo';
}
