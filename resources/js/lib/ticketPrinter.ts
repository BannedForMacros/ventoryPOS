/**
 * Conector con el agente local "Ventory Print" (VentoryPrint.exe).
 *
 * Por defecto el agente corre en la misma PC (http://127.0.0.1:9111), pero
 * cada dispositivo puede apuntar a otro agente en la red local guardando la
 * URL en localStorage (clave `ventoryprint:url`). Útil para tablets.
 */

const AGENTE_URL_DEFAULT = 'http://127.0.0.1:9111';
const AGENTE_URL_KEY = 'ventoryprint:url';

function getAgenteUrl(): string {
  if (typeof window === 'undefined') return AGENTE_URL_DEFAULT;
  const saved = window.localStorage.getItem(AGENTE_URL_KEY);
  if (!saved) return AGENTE_URL_DEFAULT;
  return saved.trim().replace(/\/$/, '') || AGENTE_URL_DEFAULT;
}

/** Cambia la URL del agente para este dispositivo. Pasa '' para volver al default. */
export function setAgenteUrl(url: string): void {
  if (typeof window === 'undefined') return;
  const clean = url.trim().replace(/\/$/, '');
  if (!clean || clean === AGENTE_URL_DEFAULT) {
    window.localStorage.removeItem(AGENTE_URL_KEY);
  } else {
    window.localStorage.setItem(AGENTE_URL_KEY, clean);
  }
}

export function getAgenteUrlConfigured(): string {
  return getAgenteUrl();
}

// ---- Tipos del payload (deben coincidir con Ticket.cs del agente) ----
export interface TicketItem {
  cant: number;
  desc: string;
  precio: number;
  importe: number;
  unidad?: string;
}

export interface TicketPayload {
  /** Token de la caja (columna token_impresora) que valida el agente local. */
  token?: string;
  negocio?: { nombre?: string; ruc?: string; direccion?: string; telefono?: string; mostrarRuc?: boolean; logoEscala?: number };
  documento?: { tipo?: string; serie?: string; numero?: string; fecha?: string; vendedor?: string; caja?: string };
  cliente?: { nombre?: string; doc?: string; direccion?: string; telefono?: string };
  items: TicketItem[];
  totales?: { subtotal?: number; igv?: number; descuento?: number; total: number; moneda?: string };
  pago?: { metodo?: string; recibido?: number; vuelto?: number };
  pie?: string;
  qr?: string;
  /** Logo del negocio en data URI base64 (sale si el agente lo soporta). */
  logo?: string;
  abrirCajon?: boolean;
  copias?: number;
  anchoPapelMm?: number;
  /** URL base del POS; el agente la usa para auto-actualizarse. Se inyecta al imprimir. */
  origen?: string;
}

// ---- Payload de cierre de turno (ShiftClosurePayload.cs del agente) ----
export interface TurnoInfo {
  id?: string;
  nombre?: string;
  cajero?: string;
  caja?: string;
  fechaApertura?: string;
  fechaCierre?: string;
}

export interface ResumenCierre {
  numeroVentas?: number;
  subtotal?: number;
  igv?: number;
  descuento?: number;
  total: number;
  moneda?: string;
}

export interface MetodoPagoCierre {
  nombre: string;
  monto: number;
  cantidad?: number;
}

export interface CajaCierre {
  montoApertura?: number;
  ventasEfectivo?: number;
  entradas?: number;
  salidas?: number;
  efectivoEsperado?: number;
  efectivoDeclarado?: number;
  diferencia?: number;
}

export interface ShiftClosurePayload {
  token?: string;
  negocio?: { nombre?: string; ruc?: string; direccion?: string; telefono?: string; mostrarRuc?: boolean; logoEscala?: number };
  turno?: TurnoInfo;
  resumen?: ResumenCierre;
  metodosPago: MetodoPagoCierre[];
  caja?: CajaCierre;
  pie?: string;
  logo?: string;
  copias?: number;
  anchoPapelMm?: number;
  origen?: string;
}

/** ¿Está el agente corriendo en esta PC? (timeout corto para no colgar la UI) */
export async function agenteActivo(timeoutMs = 1200): Promise<boolean> {
  try {
    const ctrl = new AbortController();
    const id = setTimeout(() => ctrl.abort(), timeoutMs);
    const r = await fetch(`${getAgenteUrl()}/status`, { signal: ctrl.signal });
    clearTimeout(id);
    return r.ok;
  } catch {
    return false;
  }
}

/** Envía el ticket al agente para imprimir. Devuelve true si se aceptó. */
export async function imprimirTicket(ticket: TicketPayload): Promise<boolean> {
  try {
    const r = await fetch(`${getAgenteUrl()}/print`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      // origen: le decimos al agente desde qué servidor buscar sus actualizaciones.
      body: JSON.stringify({ ...ticket, origen: ticket.origen ?? window.location.origin }),
    });
    const j = await r.json().catch(() => ({}));
    return r.ok && j?.ok !== false;
  } catch {
    return false;
  }
}

/** Envía el reporte de cierre de turno al agente para imprimir. */
export async function imprimirCierreTurno(payload: ShiftClosurePayload): Promise<boolean> {
  try {
    const r = await fetch(`${getAgenteUrl()}/print-shift-closure`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...payload, origen: payload.origen ?? window.location.origin }),
    });
    const j = await r.json().catch(() => ({}));
    return r.ok && j?.ok !== false;
  } catch {
    return false;
  }
}

/** Abre la página de configuración del agente en una pestaña nueva. */
export function abrirConfiguracionAgente(): void {
  window.open(getAgenteUrl(), '_blank');
}
