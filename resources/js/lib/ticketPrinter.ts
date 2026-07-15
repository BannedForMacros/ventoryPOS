/**
 * Conector con el agente local "Ventory Print" (VentoryPrint.exe).
 *
 * El agente corre en la PC de la caja y escucha en http://127.0.0.1:9111.
 * Desde el navegador le mandamos el ticket y él lo imprime en la ticketera
 * (USB o red) con comandos ESC/POS. Ver /agente-impresion.
 */

const AGENTE_URL = 'http://127.0.0.1:9111';

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
  negocio?: { nombre?: string; ruc?: string; direccion?: string; telefono?: string };
  documento?: { tipo?: string; serie?: string; numero?: string; fecha?: string; vendedor?: string; caja?: string };
  cliente?: { nombre?: string; doc?: string; direccion?: string };
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

/** ¿Está el agente corriendo en esta PC? (timeout corto para no colgar la UI) */
export async function agenteActivo(timeoutMs = 1200): Promise<boolean> {
  try {
    const ctrl = new AbortController();
    const id = setTimeout(() => ctrl.abort(), timeoutMs);
    const r = await fetch(`${AGENTE_URL}/status`, { signal: ctrl.signal });
    clearTimeout(id);
    return r.ok;
  } catch {
    return false;
  }
}

/** Envía el ticket al agente para imprimir. Devuelve true si se aceptó. */
export async function imprimirTicket(ticket: TicketPayload): Promise<boolean> {
  try {
    const r = await fetch(`${AGENTE_URL}/print`, {
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

/** Abre la página de configuración del agente en una pestaña nueva. */
export function abrirConfiguracionAgente(): void {
  window.open(AGENTE_URL, '_blank');
}
