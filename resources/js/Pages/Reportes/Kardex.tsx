import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import axios from 'axios';
import { Filter, X, Search, ScrollText, ArrowDownCircle, ArrowUpCircle, Package, Wallet, Loader2, ShoppingCart } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Modal from '@/Components/UI/Modal';
import type { PageProps } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; }

interface MovimientoRow {
    id:               number;
    fecha:            string | null;
    tipo:             string;
    tipo_label:       string;
    documento:        string | null;
    referencia_tipo:  string | null;
    referencia_id:    number | null;
    almacen:          string;
    producto:         string;
    producto_codigo:  string | null;
    entra:            number | null;
    sale:             number | null;
    costo_unitario:   number;
    costo_promedio:   number;
    saldo_cantidad:   number;
    saldo_valorizado: number;
    usuario:          string | null;
}

// ── Tipos del detalle bajo demanda (modal) ────────────────────────────────
// Entrada: subset del payload de `inventario.entradas.detalle-json`.
interface EntradaDetItem {
    id: number;
    cantidad: string;
    factor_conversion: string;
    cantidad_base: string;
    precio_costo: string;
    subtotal: string;
    producto: { id: number; nombre: string; codigo: string | null } | null;
    unidad_medida: { id: number; nombre: string; abreviatura: string } | null;
}
interface EntradaDet {
    id: number;
    fecha: string;
    tipo: string;
    proveedor: string | null;
    numero_documento: string | null;
    total: string;
    estado: 'borrador' | 'confirmado';
    estado_pago: 'pendiente' | 'parcial' | 'pagado';
    monto_pagado: string;
    almacen: { nombre: string; local?: { nombre: string } | null };
    user: { name: string };
    detalles: EntradaDetItem[];
    metodo_pago?: { id: number; nombre: string } | null;
    cuenta?: { id: number; nombre: string } | null;
}
// Venta: subset del payload del ticket (`ventas.ticket`).
interface VentaTicketItem { cant: number; desc: string; precio: number; importe: number; unidad: string | null; }
interface VentaTicket {
    documento: { tipo: string; numero: string | null; fecha: string | null; vendedor: string | null; caja: string | null };
    cliente:   { nombre: string; doc: string | null };
    items:     VentaTicketItem[];
    totales:   { subtotal: number; igv: number; descuento: number; total: number; moneda: string };
    pago:      { metodo: string | null; recibido: number | null; vuelto: number | null };
}

interface Kpis { movimientos: number; total_entra: number; total_sale: number; stock_actual: number; }
interface Almacen { id: number; nombre: string; }
interface TipoOpt { value: string; label: string; }
interface ProductoSel { id: number; nombre: string; codigo: string | null; }

interface Filters {
    fecha_desde:  string;
    fecha_hasta:  string;
    almacen_id?:  string;
    producto_id?: string;
    tipo?:        string;
    buscar?:      string;
}

interface Props extends PageProps {
    movimientos:     Paginado<MovimientoRow>;
    kpis:            Kpis;
    almacenes:       Almacen[];
    mostrarSelector: boolean;
    productoSel:     ProductoSel | null;
    tipos:           TipoOpt[];
    filters:         Filters;
}

const num = (n: number) => n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const sol = (n: number) => 'S/ ' + num(n);

export default function ReporteKardex({ movimientos, kpis, almacenes, mostrarSelector, productoSel, tipos, filters, flash }: Props) {
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // ── Modal de detalle (venta / entrada) ────────────────────────────────
    // `detalleTipo` marca qué payload esperamos; `detalle*` guardan el fetch.
    const [detalleTipo,   setDetalleTipo]   = useState<'venta' | 'entrada' | null>(null);
    const [detalleLoading, setDetalleLoading] = useState(false);
    const [entradaDet,    setEntradaDet]    = useState<EntradaDet | null>(null);
    const [ventaDet,      setVentaDet]      = useState<VentaTicket | null>(null);

    // Una fila abre modal solo si apunta a una venta o entrada con id.
    const filaClicable = (m: MovimientoRow) =>
        (m.referencia_tipo === 'venta' || m.referencia_tipo === 'entrada') && !!m.referencia_id;

    function abrirDetalle(m: MovimientoRow) {
        if (!filaClicable(m) || !m.referencia_id) return;
        const tipo = m.referencia_tipo as 'venta' | 'entrada';
        setDetalleTipo(tipo);
        setEntradaDet(null);
        setVentaDet(null);
        setDetalleLoading(true);

        const req = tipo === 'entrada'
            ? axios.get(route('inventario.entradas.detalle-json', m.referencia_id)).then(res => setEntradaDet(res.data.entrada))
            : axios.get(route('ventas.ticket', m.referencia_id)).then(res => setVentaDet(res.data));

        req.catch(() => toast.error('No se pudo cargar el detalle.'))
           .finally(() => setDetalleLoading(false));
    }

    function cerrarDetalle() {
        setDetalleTipo(null);
    }

    function filtrar(patch: Partial<Filters>) {
        router.get(route('reportes.kardex'), { ...filters, ...patch }, { preserveState: true, replace: true });
    }
    function limpiar() {
        router.get(route('reportes.kardex'), {}, { preserveState: true, replace: true });
    }
    function quitarProducto() {
        filtrar({ producto_id: undefined });
    }

    const tieneFiltros = !!(filters.almacen_id || filters.producto_id || filters.tipo || filters.buscar);

    const ringStyle = {
        borderColor: 'var(--color-border)',
        backgroundColor: 'var(--color-bg)',
        color: 'var(--color-text)',
        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
    } as React.CSSProperties;

    return (
        <AppLayout title="Kardex">
            <PageHeader
                title="Kardex de inventario"
                subtitle={`${filters.fecha_desde} → ${filters.fecha_hasta} · ${kpis.movimientos} movimientos`}
            />

            {/* KPIs */}
            <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <KpiCard icon={<ScrollText size={18} />} label="Movimientos" value={kpis.movimientos.toLocaleString('es-PE')} color="primary" />
                <KpiCard icon={<ArrowDownCircle size={18} />} label="Total ingresado (base)" value={num(kpis.total_entra)} color="success" />
                <KpiCard icon={<ArrowUpCircle size={18} />} label="Total salido (base)" value={num(kpis.total_sale)} color="danger" />
                <KpiCard icon={<Package size={18} />} label="Stock actual (base)" value={num(kpis.stock_actual)} color="primary" />
            </div>

            {/* Producto fijado (cuando se entra desde el ojo de Stock actual) */}
            {productoSel && (
                <div className="mb-4 flex items-center gap-2 rounded-lg px-3 py-2 text-sm"
                     style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', color: 'var(--color-text)' }}>
                    <Package size={14} style={{ color: 'var(--color-primary)' }} />
                    <span>Viendo el kardex de <strong>{productoSel.nombre}</strong>{productoSel.codigo ? ` · ${productoSel.codigo}` : ''}</span>
                    <button onClick={quitarProducto} className="ml-auto inline-flex items-center gap-1 text-xs font-medium hover:underline" style={{ color: 'var(--color-primary)' }}>
                        <X size={12} /> Ver todos
                    </button>
                </div>
            )}

            {/* Filtros */}
            <div className="rounded-xl p-3 sm:p-4 mb-4" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <div className="flex items-center gap-2 mb-3">
                    <Filter size={14} style={{ color: 'var(--color-text-muted)' }} />
                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Filtros</span>
                    {tieneFiltros && (
                        <button onClick={limpiar} className="ml-auto inline-flex items-center gap-1 text-xs font-medium hover:underline" style={{ color: 'var(--color-primary)' }}>
                            <X size={12} /> Limpiar
                        </button>
                    )}
                </div>
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-2">
                    <Field label="Desde">
                        <input type="date" value={filters.fecha_desde} onChange={e => filtrar({ fecha_desde: e.target.value })}
                               className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                    </Field>
                    <Field label="Hasta">
                        <input type="date" value={filters.fecha_hasta} onChange={e => filtrar({ fecha_hasta: e.target.value })}
                               className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                    </Field>
                    {mostrarSelector && (
                        <Field label="Almacén">
                            <select value={filters.almacen_id ?? ''} onChange={e => filtrar({ almacen_id: e.target.value || undefined })}
                                    className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                                <option value="">Todos</option>
                                {almacenes.map(a => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                            </select>
                        </Field>
                    )}
                    <Field label="Tipo">
                        <select value={filters.tipo ?? ''} onChange={e => filtrar({ tipo: e.target.value || undefined })}
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle}>
                            <option value="">Todos</option>
                            {tipos.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Buscar producto">
                        <div className="relative">
                            <Search size={12} className="absolute left-2 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                            <input type="text" placeholder="Nombre o código..." defaultValue={filters.buscar ?? ''}
                                   onKeyDown={e => { if (e.key === 'Enter') filtrar({ buscar: (e.target as HTMLInputElement).value || undefined }); }}
                                   className="w-full text-sm border rounded-lg pl-7 pr-2.5 py-1.5 focus:outline-none focus:ring-2" style={ringStyle} />
                        </div>
                    </Field>
                </div>
            </div>

            {/* Tabla */}
            <div className="rounded-xl overflow-x-auto" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <table className="w-full text-sm" style={{ minWidth: 920 }}>
                    <thead>
                        <tr style={{ backgroundColor: 'var(--color-bg)', borderBottom: '2px solid var(--color-border)' }}>
                            {['Fecha', 'Tipo', 'Documento', ...(mostrarSelector ? ['Almacén'] : []), 'Producto', 'Entra', 'Sale', 'Costo unit.', 'Costo prom.', 'Saldo', 'Saldo valor.', 'Usuario'].map((h, i) => (
                                <th key={h} className={`px-3 py-2.5 text-[10px] font-bold uppercase tracking-wide ${i >= 4 + (mostrarSelector ? 1 : 0) ? 'text-right' : 'text-left'}`}
                                    style={{ color: 'var(--color-text-muted)', whiteSpace: 'nowrap' }}>
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {movimientos.data.map(m => {
                            const up = m.entra != null; // suma stock = verde, resta = rojo
                            const clic = filaClicable(m);
                            return (
                                <tr key={m.id}
                                    onClick={clic ? () => abrirDetalle(m) : undefined}
                                    className={clic ? 'kardex-row-clicable' : ''}
                                    style={{ borderBottom: '1px solid var(--color-border)', cursor: clic ? 'pointer' : 'default' }}>
                                    <td className="px-3 py-2 text-xs whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>{m.fecha ?? '—'}</td>
                                    <td className="px-3 py-2">
                                        <span className="inline-block text-[10px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap"
                                              style={{
                                                  backgroundColor: `color-mix(in srgb, var(--color-${up ? 'success' : 'danger'}) 14%, transparent)`,
                                                  color: `var(--color-${up ? 'success' : 'danger'})`,
                                              }}>
                                            {m.tipo_label}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{m.documento ?? '—'}</td>
                                    {mostrarSelector && <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{m.almacen}</td>}
                                    <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text)' }}>
                                        <span className="font-medium">{m.producto}</span>
                                        {m.producto_codigo && <span className="ml-1.5 font-mono" style={{ color: 'var(--color-text-muted)' }}>{m.producto_codigo}</span>}
                                    </td>
                                    <td className="px-3 py-2 text-xs text-right font-medium" style={{ color: 'var(--color-success)', fontVariantNumeric: 'tabular-nums' }}>{m.entra != null ? num(m.entra) : ''}</td>
                                    <td className="px-3 py-2 text-xs text-right font-medium" style={{ color: 'var(--color-danger)', fontVariantNumeric: 'tabular-nums' }}>{m.sale != null ? num(m.sale) : ''}</td>
                                    <td className="px-3 py-2 text-xs text-right font-mono" style={{ color: 'var(--color-text-muted)' }}>{num(m.costo_unitario)}</td>
                                    <td className="px-3 py-2 text-xs text-right font-mono" style={{ color: 'var(--color-text-muted)' }}>{num(m.costo_promedio)}</td>
                                    <td className="px-3 py-2 text-xs text-right font-semibold" style={{ color: 'var(--color-text)', fontVariantNumeric: 'tabular-nums' }}>{num(m.saldo_cantidad)}</td>
                                    <td className="px-3 py-2 text-xs text-right font-mono font-semibold" style={{ color: 'var(--color-text)' }}>{sol(m.saldo_valorizado)}</td>
                                    <td className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{m.usuario ?? '—'}</td>
                                </tr>
                            );
                        })}
                        {movimientos.data.length === 0 && (
                            <tr>
                                <td colSpan={12} className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
                                    <ScrollText size={36} className="mx-auto mb-2 opacity-20" />
                                    <p className="text-sm">No hay movimientos en el rango seleccionado</p>
                                    <p className="text-xs mt-1 opacity-70">Si acabas de instalar el kardex, corre <code>php artisan kardex:reconstruir</code>.</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Paginación */}
            {movimientos.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4 flex-wrap">
                    {Array.from({ length: movimientos.last_page }, (_, i) => i + 1).map(page => (
                        <button key={page}
                                onClick={() => router.get(route('reportes.kardex'), { ...filters, page }, { preserveState: true })}
                                className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                                style={{
                                    backgroundColor: page === movimientos.current_page ? 'var(--color-primary)' : 'transparent',
                                    color: page === movimientos.current_page ? '#fff' : 'var(--color-text-muted)',
                                }}>
                            {page}
                        </button>
                    ))}
                </div>
            )}

            {/* Modal de detalle: se abre al clicar una fila de venta o entrada.
                Un solo Modal; el cuerpo cambia según `detalleTipo`. */}
            <Modal
                isOpen={detalleTipo !== null}
                onClose={cerrarDetalle}
                title={detalleTipo === 'venta' ? 'Detalle de venta' : 'Detalle de entrada'}
                size="lg"
            >
                {detalleLoading ? (
                    <div className="flex items-center justify-center py-12 gap-2" style={{ color: 'var(--color-text-muted)' }}>
                        <Loader2 size={18} className="animate-spin" />
                        <span className="text-sm">Cargando detalle...</span>
                    </div>
                ) : detalleTipo === 'entrada' && entradaDet ? (
                    <DetalleEntrada e={entradaDet} />
                ) : detalleTipo === 'venta' && ventaDet ? (
                    <DetalleVenta v={ventaDet} />
                ) : null}
            </Modal>

            {/* Hover para filas clicables — indica que hay detalle disponible. */}
            <style>{`
                .kardex-row-clicable:hover {
                    background-color: color-mix(in srgb, var(--color-primary) 6%, transparent);
                }
            `}</style>
        </AppLayout>
    );
}

// ── Cuerpo del modal: ENTRADA ──────────────────────────────────────────────
function DetalleEntrada({ e }: { e: EntradaDet }) {
    const saldo = Math.max(0, Number(e.total) - Number(e.monto_pagado ?? 0));
    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center gap-2 pb-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                      style={{ color: e.estado === 'confirmado' ? '#16a34a' : '#ca8a04', backgroundColor: e.estado === 'confirmado' ? 'color-mix(in srgb, #16a34a 12%, transparent)' : 'rgba(250,204,21,0.15)' }}>
                    {e.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </span>
                <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                      style={{ color: e.estado_pago === 'pagado' ? '#16a34a' : '#ca8a04', backgroundColor: e.estado_pago === 'pagado' ? 'color-mix(in srgb, #16a34a 12%, transparent)' : 'rgba(250,204,21,0.15)' }}>
                    <Wallet size={11} />{e.estado_pago === 'pagado' ? 'Pagado' : e.estado_pago === 'parcial' ? 'Pago parcial' : 'Pago pendiente'}
                </span>
                <span className="ml-auto text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>#{e.id}</span>
            </div>

            <div className="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                <MetaKardex label="Almacén" value={`${e.almacen.nombre}${e.almacen.local ? ' · ' + e.almacen.local.nombre : ''}`} />
                <MetaKardex label="Registrado por" value={e.user.name} />
                {e.proveedor && <MetaKardex label="Proveedor" value={e.proveedor} />}
                {e.numero_documento && <MetaKardex label="Nº documento" value={e.numero_documento} mono />}
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
                {e.estado_pago === 'pagado' && e.metodo_pago ? (
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
                )}
            </div>
        </div>
    );
}

// ── Cuerpo del modal: VENTA ────────────────────────────────────────────────
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
                <MetaKardex label="Cliente" value={v.cliente.nombre} />
                {v.cliente.doc && <MetaKardex label="Documento" value={v.cliente.doc} mono />}
                {v.documento.vendedor && <MetaKardex label="Vendedor" value={v.documento.vendedor} />}
                {v.documento.caja && <MetaKardex label="Caja" value={v.documento.caja} />}
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

/** Item label+valor para los modales de detalle del kardex. */
function MetaKardex({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <p className="text-[10px] uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className={`text-sm ${mono ? 'font-mono' : ''}`} style={{ color: 'var(--color-text)' }}>{value}</p>
        </div>
    );
}

function KpiCard({ icon, label, value, color }: {
    icon: React.ReactNode; label: string; value: string;
    color: 'primary' | 'success' | 'danger' | 'warning';
}) {
    return (
        <div className="rounded-xl p-3 flex items-center gap-3" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <div className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style={{ backgroundColor: `color-mix(in srgb, var(--color-${color}) 10%, transparent)`, color: `var(--color-${color})` }}>
                {icon}
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-medium uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
                <p className="text-base font-bold" style={{ color: 'var(--color-text)' }}>{value}</p>
            </div>
        </div>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>{label}</label>
            {children}
        </div>
    );
}
