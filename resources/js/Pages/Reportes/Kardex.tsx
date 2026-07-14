import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Filter, X, Search, ScrollText, ArrowDownCircle, ArrowUpCircle, Package } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import type { PageProps } from '@/types';

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; }

interface MovimientoRow {
    id:               number;
    fecha:            string | null;
    tipo:             string;
    tipo_label:       string;
    documento:        string | null;
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

interface Kpis { movimientos: number; total_entra: number; total_sale: number; }
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
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <KpiCard icon={<ScrollText size={18} />} label="Movimientos" value={kpis.movimientos.toLocaleString('es-PE')} color="primary" />
                <KpiCard icon={<ArrowDownCircle size={18} />} label="Total ingresado (base)" value={num(kpis.total_entra)} color="success" />
                <KpiCard icon={<ArrowUpCircle size={18} />} label="Total salido (base)" value={num(kpis.total_sale)} color="danger" />
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
                            return (
                                <tr key={m.id} style={{ borderBottom: '1px solid var(--color-border)' }}>
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
        </AppLayout>
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
