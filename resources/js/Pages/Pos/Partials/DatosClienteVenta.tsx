import { useState } from 'react';
import { ChevronDown, MapPin, MessageSquareText, Phone } from 'lucide-react';

export interface DatosCliente {
    telefono: string;
    direccion: string;
    observacion: string;
}

export const datosClienteVacios: DatosCliente = { telefono: '', direccion: '', observacion: '' };

/**
 * Teléfono, dirección y observación con que se atiende ESTA venta. Se llenan
 * solos con la ficha del cliente y se pueden cambiar sin tocar la ficha (la
 * obra puede estar en otra dirección). Solo aparece si la empresa lo activó
 * en Configuración, Ticket.
 */
export default function DatosClienteVenta({ valor, onChange }: { valor: DatosCliente; onChange: (v: DatosCliente) => void }) {
    // En pantallas chicas empieza cerrado para no quitarle sitio a los productos.
    const [abierto, setAbierto] = useState(() => typeof window === 'undefined' || window.innerWidth >= 768);
    const llenos = [valor.telefono, valor.direccion, valor.observacion].filter(v => v.trim()).length;
    const set = (k: keyof DatosCliente) => (e: React.ChangeEvent<HTMLInputElement>) => onChange({ ...valor, [k]: e.target.value });

    return (
        <div className="px-3 sm:px-4 py-2 border-b flex-shrink-0"
            style={{ backgroundColor: 'color-mix(in srgb, var(--vp-sky) 9%, var(--color-bg))', borderColor: 'color-mix(in srgb, var(--vp-sky) 35%, var(--color-border))' }}>
            <button type="button" onClick={() => setAbierto(a => !a)} aria-expanded={abierto}
                className="md:hidden flex w-full items-center justify-between gap-2 text-sm font-semibold" style={{ color: 'var(--vp-navy)' }}>
                <span>Datos del cliente para el ticket{llenos > 0 ? ` (${llenos} de 3)` : ''}</span>
                <ChevronDown size={16} className={`transition-transform ${abierto ? 'rotate-180' : ''}`} />
            </button>

            {abierto && (
                <div className="grid grid-cols-1 md:grid-cols-[11rem_minmax(0,1fr)_minmax(0,1fr)] gap-2 mt-2 md:mt-0">
                    <Campo icono={<Phone size={14} />} etiqueta="Teléfono del cliente">
                        <input type="tel" inputMode="tel" maxLength={30} value={valor.telefono} onChange={set('telefono')}
                            placeholder="Teléfono" aria-label="Teléfono del cliente" className={CLASE} style={ESTILO} />
                    </Campo>
                    <Campo icono={<MapPin size={14} />} etiqueta="Dirección">
                        <input type="text" maxLength={255} value={valor.direccion} onChange={set('direccion')}
                            placeholder="Dirección (de entrega, si es otra)" aria-label="Dirección del cliente" data-envio-direccion className={CLASE} style={ESTILO} />
                    </Campo>
                    <Campo icono={<MessageSquareText size={14} />} etiqueta="Observación">
                        <input type="text" maxLength={500} value={valor.observacion} onChange={set('observacion')}
                            placeholder="Observación (ej.: dejar por la puerta lateral)" aria-label="Observación de la venta" className={CLASE} style={ESTILO} />
                    </Campo>
                </div>
            )}
        </div>
    );
}

const CLASE = 'w-full rounded-lg border pl-8 pr-2.5 py-1.5 text-sm outline-none focus:ring-2';
const ESTILO: React.CSSProperties = { borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' };

function Campo({ icono, etiqueta, children }: { icono: React.ReactNode; etiqueta: string; children: React.ReactNode }) {
    return (
        <div className="relative min-w-0" title={etiqueta}>
            <span className="absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" style={{ color: 'var(--vp-navy)' }}>{icono}</span>
            {children}
        </div>
    );
}
