import { useEffect, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Loader2, ShoppingCart, Wallet } from 'lucide-react';
import Modal from '@/Components/UI/Modal';

/**
 * Modal de detalle de un documento (venta o entrada/compra) bajo demanda.
 * Mismo payload y diseño que los modales del Kardex:
 *   venta   → GET ventas.ticket (JSON del ticket)
 *   entrada → GET inventario.entradas.detalle-json
 * Uso: <DocumentoDetalleModal doc={{ tipo: 'venta', id: 123 }} onClose={...} />
 * (doc = null → cerrado). El fetch corre al abrir; los errores avisan por toast.
 */

export interface DocumentoRef { tipo: 'venta' | 'entrada'; id: number; }

interface EntradaDetItem {
    id: number;
    cantidad: string;
    precio_costo: string;
    subtotal: string;
    producto: { id: number; nombre: string; codigo: string | null } | null;
    unidad_medida: { id: number; nombre: string; abreviatura: string } | null;
}
interface EntradaDet {
    id: number;
    proveedor: string | null;
    numero_documento: string | null;
    total: string;
    estado: 'borrador' | 'confirmado' | 'anulada';
    estado_pago: 'pendiente' | 'parcial' | 'pagado';
    monto_pagado: string;
    almacen: { nombre: string; local?: { nombre: string } | null };
    user: { name: string };
    detalles: EntradaDetItem[];
    metodo_pago?: { id: number; nombre: string } | null;
    cuenta?: { id: number; nombre: string } | null;
}
interface VentaTicketItem { cant: number; desc: string; precio: number; importe: number; unidad: string | null; }
interface VentaTicket {
    documento: { tipo: string; numero: string | null; fecha: string | null; vendedor: string | null; caja: string | null };
    cliente:   { nombre: string; doc: string | null };
    items:     VentaTicketItem[];
    totales:   { subtotal: number; igv: number; descuento: number; total: number; moneda: string };
    pago:      { metodo: string | null; recibido: number | null; vuelto: number | null };
}

export default function DocumentoDetalleModal({ doc, onClose }: { doc: DocumentoRef | null; onClose: () => void }) {
    const [loading, setLoading] = useState(false);
    const [venta, setVenta]     = useState<VentaTicket | null>(null);
    const [entrada, setEntrada] = useState<EntradaDet | null>(null);

    useEffect(() => {
        if (!doc) return;
        setVenta(null);
        setEntrada(null);
        setLoading(true);
        const req = doc.tipo === 'venta'
            ? axios.get(route('ventas.ticket', doc.id)).then(res => setVenta(res.data))
            : axios.get(route('inventario.entradas.detalle-json', doc.id)).then(res => setEntrada(res.data.entrada));
        req.catch(() => toast.error('No se pudo cargar el detalle.'))
           .finally(() => setLoading(false));
    }, [doc]);

    return (
        <Modal
            isOpen={doc !== null}
            onClose={onClose}
            title={doc?.tipo === 'venta' ? 'Detalle de venta' : 'Detalle de compra'}
            size="lg"
        >
            {loading ? (
                <div className="flex items-center justify-center py-12 gap-2" style={{ color: 'var(--color-text-muted)' }}>
                    <Loader2 size={18} className="animate-spin" />
                    <span className="text-sm">Cargando detalle...</span>
                </div>
            ) : doc?.tipo === 'venta' && venta ? (
                <DetalleVenta v={venta} />
            ) : doc?.tipo === 'entrada' && entrada ? (
                <DetalleEntrada e={entrada} />
            ) : null}
        </Modal>
    );
}

function DetalleEntrada({ e }: { e: EntradaDet }) {
    const saldo = Math.max(0, Number(e.total) - Number(e.monto_pagado ?? 0));
    const anulada = e.estado === 'anulada';
    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center gap-2 pb-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                      style={{
                          color: anulada ? 'var(--color-danger)' : e.estado === 'confirmado' ? '#16a34a' : '#ca8a04',
                          backgroundColor: anulada ? 'color-mix(in srgb, var(--color-danger) 12%, transparent)'
                              : e.estado === 'confirmado' ? 'color-mix(in srgb, #16a34a 12%, transparent)' : 'rgba(250,204,21,0.15)',
                      }}>
                    {anulada ? 'Anulada' : e.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </span>
                {!anulada && (
                    <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                          style={{ color: e.estado_pago === 'pagado' ? '#16a34a' : '#ca8a04', backgroundColor: e.estado_pago === 'pagado' ? 'color-mix(in srgb, #16a34a 12%, transparent)' : 'rgba(250,204,21,0.15)' }}>
                        <Wallet size={11} />{e.estado_pago === 'pagado' ? 'Pagado' : e.estado_pago === 'parcial' ? 'Pago parcial' : 'Pago pendiente'}
                    </span>
                )}
                <span className="ml-auto text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>#{e.id}</span>
            </div>

            <div className="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                <Meta label="Almacén" value={`${e.almacen.nombre}${e.almacen.local ? ' · ' + e.almacen.local.nombre : ''}`} />
                <Meta label="Registrado por" value={e.user.name} />
                {e.proveedor && <Meta label="Proveedor" value={e.proveedor} />}
                {e.numero_documento && <Meta label="Nº documento" value={e.numero_documento} mono />}
            </div>

            <div>
                <h3 className="text-xs font-semibold uppercase tracking-wide mb-2" style={{ color: 'var(--color-text-muted)' }}>
                    Productos ({e.detalles.length})
                </h3>
                <div className="rounded-xl border divide-y" style={{ borderColor: 'var(--color-border)' }}>
                    {e.detalles.map(d => (
                        <div key={d.id} className="px-3 py-2.5 text-sm flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="font-medium truncate" style={{ color: 'var(--color-text)' }}>{d.producto?.nombre ?? '—'}</p>
                                <p className="text-xs mt-0.5 font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                    {Number(d.cantidad).toFixed(2)} {d.unidad_medida?.abreviatura ?? ''}
                                    {' · '}S/ {Number(d.precio_costo).toFixed(2)} c/u
                                </p>
                            </div>
                            <p className="text-sm font-mono font-semibold whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                S/ {Number(d.subtotal).toFixed(2)}
                            </p>
                        </div>
                    ))}
                </div>
            </div>

            <div className="rounded-xl p-3" style={{ backgroundColor: 'var(--color-bg)' }}>
                <div className="flex justify-between items-center">
                    <span className="text-sm font-medium" style={{ color: 'var(--color-text-muted)' }}>Total</span>
                    <span className="text-lg font-bold font-mono" style={{ color: 'var(--color-text)' }}>S/ {Number(e.total).toFixed(2)}</span>
                </div>
                {!anulada && (e.estado_pago === 'pagado' && e.metodo_pago ? (
                    <div className="flex justify-between items-center mt-2 pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                        <span className="text-xs flex items-center gap-1.5" style={{ color: 'var(--color-text-muted)' }}><Wallet size={12} />Pagado con</span>
                        <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                            {e.metodo_pago.nombre}
                            {e.cuenta && <span style={{ color: 'var(--color-text-muted)' }}> · {e.cuenta.nombre}</span>}
                        </span>
                    </div>
                ) : e.estado_pago !== 'pagado' && (
                    <div className="flex justify-between items-center mt-2 pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                        <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Saldo pendiente</span>
                        <span className="text-sm font-mono font-bold" style={{ color: 'var(--color-danger)' }}>S/ {saldo.toFixed(2)}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function DetalleVenta({ v }: { v: VentaTicket }) {
    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center gap-2 pb-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                      style={{ color: 'var(--color-primary)', backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)' }}>
                    <ShoppingCart size={11} />{v.documento.tipo}
                </span>
                {v.documento.numero && <span className="text-sm font-mono font-semibold" style={{ color: 'var(--color-text)' }}>{v.documento.numero}</span>}
                <span className="ml-auto text-xs" style={{ color: 'var(--color-text-muted)' }}>{v.documento.fecha ?? ''}</span>
            </div>

            <div className="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                <Meta label="Cliente" value={v.cliente.nombre} />
                {v.cliente.doc && <Meta label="Documento" value={v.cliente.doc} mono />}
                {v.documento.vendedor && <Meta label="Vendedor" value={v.documento.vendedor} />}
                {v.documento.caja && <Meta label="Caja" value={v.documento.caja} />}
            </div>

            <div>
                <h3 className="text-xs font-semibold uppercase tracking-wide mb-2" style={{ color: 'var(--color-text-muted)' }}>
                    Productos ({v.items.length})
                </h3>
                <div className="rounded-xl border divide-y" style={{ borderColor: 'var(--color-border)' }}>
                    {v.items.map((it, i) => (
                        <div key={i} className="px-3 py-2.5 text-sm flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="font-medium truncate" style={{ color: 'var(--color-text)' }}>{it.desc}</p>
                                <p className="text-xs mt-0.5 font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                    {it.cant.toFixed(2)} {it.unidad ?? ''}{' · '}S/ {it.precio.toFixed(2)} c/u
                                </p>
                            </div>
                            <p className="text-sm font-mono font-semibold whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                S/ {it.importe.toFixed(2)}
                            </p>
                        </div>
                    ))}
                </div>
            </div>

            <div className="rounded-xl p-3 space-y-1.5" style={{ backgroundColor: 'var(--color-bg)' }}>
                {v.totales.descuento > 0 && (
                    <div className="flex justify-between items-center text-sm">
                        <span style={{ color: 'var(--color-text-muted)' }}>Descuento</span>
                        <span className="font-mono" style={{ color: 'var(--color-danger)' }}>- S/ {v.totales.descuento.toFixed(2)}</span>
                    </div>
                )}
                <div className="flex justify-between items-center">
                    <span className="text-sm font-medium" style={{ color: 'var(--color-text-muted)' }}>Total</span>
                    <span className="text-lg font-bold font-mono" style={{ color: 'var(--color-text)' }}>S/ {v.totales.total.toFixed(2)}</span>
                </div>
                <div className="flex justify-between items-center mt-2 pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                    <span className="text-xs flex items-center gap-1.5" style={{ color: 'var(--color-text-muted)' }}><Wallet size={12} />Método de pago</span>
                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{v.pago.metodo ?? '—'}</span>
                </div>
                {v.pago.recibido != null && (
                    <div className="flex justify-between items-center text-xs">
                        <span style={{ color: 'var(--color-text-muted)' }}>Recibido (efectivo)</span>
                        <span className="font-mono" style={{ color: 'var(--color-text)' }}>S/ {v.pago.recibido.toFixed(2)}</span>
                    </div>
                )}
                {v.pago.vuelto != null && (
                    <div className="flex justify-between items-center text-xs">
                        <span style={{ color: 'var(--color-text-muted)' }}>Vuelto</span>
                        <span className="font-mono" style={{ color: 'var(--color-text)' }}>S/ {v.pago.vuelto.toFixed(2)}</span>
                    </div>
                )}
            </div>
        </div>
    );
}

function Meta({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <p className="text-[10px] uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className={`text-sm ${mono ? 'font-mono' : ''}`} style={{ color: 'var(--color-text)' }}>{value}</p>
        </div>
    );
}
