import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    RefreshCw, AlertTriangle, Eye, Search, Boxes, PackageX, TriangleAlert,
    CircleDollarSign, SlidersHorizontal,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import { hoyLocal } from '@/lib/fechas';
import type { PageProps } from '@/types';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { es_base: boolean; unidad_medida?: UnidadMedida; }
interface Producto {
    id: number; codigo: string | null; nombre: string;
    unidad_base?: ProductoUnidad | null;
    categoria?: { id: number; nombre: string } | null;
}
interface Almacen  { id: number; nombre: string; tipo: string; local?: { nombre: string } | null; }
interface Categoria { id: number; nombre: string; }

interface StockRow extends Record<string, unknown> {
    id: number;
    almacen_id: number;
    almacen: Almacen;
    producto_id: number;
    producto: Producto;
    cantidad: number;
    costo_promedio: number;
    valor_total: number;
    es_negativo: boolean;
}

interface StockNegativo {
    producto_id: number;
    producto: string;
    codigo: string | null;
    almacen: string;
    cantidad: number;
}

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Filters {
    almacen_id?: string; busqueda?: string; categoria_id?: string;
    estado?: string; sort?: string; dir?: string;
}

interface Kpis { valor: number; total: number; agotados: number; bajos: number; negativos: number; }

interface Props extends PageProps {
    stocks: Paginado<StockRow>;
    almacenes: Almacen[];
    categorias: Categoria[];
    kpis: Kpis;
    umbralBajo: number;
    mostrarSelector: boolean;
    filters: Filters;
    stocksNegativosCount: number;
    stocksNegativos: StockNegativo[];
    puede?: { ajustar: boolean };
    autocorreccion?: {
        fecha: string;
        stock_corregidos: number;
        kardex_corregidos: number;
        sin_respaldo: number;
        productos: {
            producto: string;
            cantidad_antes: number;
            cantidad_despues: number;
            costo_antes: number;
            costo_despues: number;
            sin_respaldo: boolean;
        }[];
    } | null;
}

const money = (v: number) => `S/ ${Number(v ?? 0).toFixed(2)}`;

const num = (v: number, max = 4) =>
    Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: max });

/**
 * Qué cambió en un producto corregido por el autocontrol. Solo se nombra lo que
 * de verdad cambió: si la cantidad es la misma, decir "720 → 720 und" no
 * informa nada — lo que se corrigió fue el costo.
 */
function describirCorreccion(p: { cantidad_antes: number; cantidad_despues: number; costo_antes: number; costo_despues: number }): string {
    const partes: string[] = [];
    if (Math.abs(p.cantidad_antes - p.cantidad_despues) > 0.00005) {
        partes.push(`stock ${num(p.cantidad_antes)} → ${num(p.cantidad_despues)} und`);
    }
    if (Math.abs(p.costo_antes - p.costo_despues) > 0.00005) {
        partes.push(`costo S/ ${num(p.costo_antes)} → S/ ${num(p.costo_despues)}`);
    }
    return partes.length ? ` — ${partes.join(' · ')}` : '';
}

const ORDENES: Record<string, string> = {
    'nombre:asc':   'Nombre (A-Z)',
    'cantidad:asc': 'Menor stock primero',
    'cantidad:desc':'Mayor stock',
    'valor:desc':   'Mayor valor',
    'costo:desc':   'Mayor costo',
};

export default function Stock({
    stocks, almacenes, categorias, kpis, umbralBajo,
    mostrarSelector, filters, stocksNegativosCount, stocksNegativos, puede, autocorreccion,
}: Props) {
    const { flash } = usePage<Props>().props;
    const [busqueda, setBusqueda] = useState(filters.busqueda ?? '');
    const puedeAjustar = puede?.ajustar ?? false;

    // ── Modal "Ajustar stock" (ingreso/salida por ajuste, sin dinero) ──────
    const [ajuste, setAjuste]       = useState<StockRow | null>(null);
    const [ajDireccion, setAjDir]   = useState<'ingreso' | 'salida'>('ingreso');
    const [ajCantidad, setAjCant]   = useState('');
    const [ajFecha, setAjFecha]     = useState(hoyLocal());
    const [ajMotivo, setAjMotivo]   = useState('');
    const [ajErrors, setAjErrors]   = useState<Record<string, string>>({});
    const [ajSaving, setAjSaving]   = useState(false);

    function abrirAjuste(s: StockRow) {
        setAjErrors({});
        setAjDir('ingreso'); setAjCant(''); setAjFecha(hoyLocal()); setAjMotivo('');
        setAjuste(s);
    }

    // Stock que quedaría tras el ajuste (previsualización).
    const ajResultado = ajuste
        ? Number(ajuste.cantidad) + (ajDireccion === 'ingreso' ? 1 : -1) * (parseFloat(ajCantidad) || 0)
        : 0;

    function guardarAjuste() {
        if (!ajuste) return;
        setAjSaving(true);
        router.post(route('inventario.ajustes.store'), {
            almacen_id:  ajuste.almacen_id,
            producto_id: ajuste.producto_id,
            tipo:        ajDireccion,
            cantidad:    ajCantidad,
            fecha:       ajFecha,
            motivo:      ajMotivo,
        }, {
            preserveScroll: true,
            onSuccess: () => { setAjuste(null); setAjSaving(false); },
            onError:   (e) => { setAjErrors(e as Record<string, string>); setAjSaving(false); },
        });
    }

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // Búsqueda auto-aplicada con debounce (500 ms, igual que el resto de la
    // app). Enter sigue aplicando al instante.
    const primeraBusqueda = useRef(true);
    useEffect(() => {
        if (primeraBusqueda.current) { primeraBusqueda.current = false; return; }
        if (busqueda.trim() === (filters.busqueda ?? '')) return;
        const t = setTimeout(() => navegar({ busqueda: busqueda.trim() || undefined }), 500);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [busqueda]);

    // Navega mergeando los filtros actuales + la búsqueda local + un patch.
    // Los selects aplican al instante; la búsqueda de texto se auto-aplica
    // debounced (500 ms) o al instante con Enter.
    function navegar(patch: Partial<Filters & { page?: number }>) {
        const params: Record<string, string | number | undefined> = {
            almacen_id:   filters.almacen_id || undefined,
            categoria_id: filters.categoria_id || undefined,
            estado:       filters.estado || undefined,
            sort:         filters.sort || undefined,
            dir:          filters.dir || undefined,
            busqueda:     busqueda || undefined,
            ...patch,
        };
        router.get(route('inventario.stock.index'), params, {
            preserveState: true, replace: true, preserveScroll: true,
        });
    }

    // Click en una KPI de estado: filtra por ese estado (o lo quita si ya estaba).
    function toggleEstado(estado: string) {
        navegar({ estado: filters.estado === estado ? undefined : estado });
    }

    function limpiar() {
        setBusqueda('');
        router.get(route('inventario.stock.index'), {}, { replace: true });
    }

    function recalcular() {
        router.post(route('inventario.stock.recalcular'));
    }

    const hayFiltros = !!(filters.almacen_id || filters.busqueda || filters.categoria_id || filters.estado);
    const ordenActual = `${filters.sort ?? 'nombre'}:${filters.dir ?? 'asc'}`;

    const columns: Column<StockRow>[] = [
        {
            key: 'producto', label: 'Producto', sortable: false,
            render: (s) => (
                <div>
                    <p className="font-medium text-sm" style={{ color: 'var(--color-text)' }}>{s.producto.nombre}</p>
                    <div className="flex items-center gap-2 mt-0.5">
                        {s.producto.codigo && (
                            <span className="font-mono text-xs" style={{ color: 'var(--color-text-muted)' }}>{s.producto.codigo}</span>
                        )}
                        {s.producto.categoria && (
                            <span className="text-[10px] px-1.5 py-0.5 rounded"
                                style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }}>
                                {s.producto.categoria.nombre}
                            </span>
                        )}
                    </div>
                </div>
            ),
        },
        {
            key: 'unidad', label: 'Unidad base', sortable: false,
            render: (s) => s.producto.unidad_base?.unidad_medida
                ? <span className="text-sm">{s.producto.unidad_base.unidad_medida.abreviatura}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        ...(mostrarSelector ? [{
            key: 'almacen', label: 'Almacén', sortable: false,
            render: (s: StockRow) => (
                <span className="text-sm">
                    {s.almacen.nombre}
                    {s.almacen.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {s.almacen.local.nombre}
                        </span>
                    )}
                </span>
            ),
        } as Column<StockRow>] : []),
        {
            key: 'cantidad', label: 'Cantidad', sortable: false,
            render: (s) => {
                const qty = Number(s.cantidad);
                const variant = qty < 0 ? 'danger' : qty === 0 ? 'danger' : qty <= umbralBajo ? 'warning' : 'success';
                return (
                    <Badge variant={variant}>
                        {qty.toFixed(2)}
                        {qty < 0 && <span className="ml-1">⚠</span>}
                    </Badge>
                );
            },
        },
        {
            key: 'costo_promedio', label: 'Costo prom.', sortable: false, align: 'right',
            render: (s) => (
                <span className="font-mono text-sm">S/ {Number(s.costo_promedio).toFixed(4)}</span>
            ),
        },
        {
            key: 'valor_total', label: 'Valor total', sortable: false, align: 'right',
            render: (s) => (
                <span className="font-mono text-sm font-semibold">S/ {Number(s.valor_total).toFixed(2)}</span>
            ),
        },
        {
            key: 'acciones', label: '', sortable: false,
            render: (s) => (
                <div className="flex items-center gap-1">
                    {puedeAjustar && (
                        <button
                            onClick={() => abrirAjuste(s)}
                            title="Ajustar stock (ingreso / salida por ajuste)"
                            className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:bg-[color-mix(in_srgb,var(--color-primary)_12%,transparent)]"
                            style={{ color: 'var(--color-text-muted)' }}
                        >
                            <SlidersHorizontal size={16} />
                        </button>
                    )}
                    <button
                        onClick={() => router.get(route('reportes.kardex'), {
                            producto_id: s.producto_id, almacen_id: s.almacen_id,
                        })}
                        title="Ver movimientos (kardex)"
                        className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:bg-[color-mix(in_srgb,var(--color-primary)_12%,transparent)]"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Eye size={16} />
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Stock actual">
            <PageHeader
                title="Stock actual"
                subtitle="Inventario en tiempo real por almacén"
                actions={
                    <Button variant="ghost" onClick={recalcular}>
                        <RefreshCw size={14} className="mr-1" />Recalcular stock
                    </Button>
                }
            />

            {/* Corrección automática del inventario (autocontrol nocturno). */}
            {autocorreccion && (
                <Callout
                    variant={autocorreccion.sin_respaldo > 0 ? 'warning' : 'info'}
                    className="mb-4"
                    title={autocorreccion.stock_corregidos > 0
                        ? `El sistema corrigió automáticamente ${autocorreccion.stock_corregidos} producto(s)`
                        : `El sistema reordenó automáticamente el historial de ${autocorreccion.kardex_corregidos} producto(s)`}
                >
                    <p>
                        Revisión del {new Date(autocorreccion.fecha).toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' })}:
                        {' '}el inventario se rearmó desde las compras, ventas, entregas y ajustes registrados. No hace falta apretar "Recalcular".
                    </p>
                    {autocorreccion.productos.length > 0 && (
                        <ul className="mt-1.5 space-y-0.5">
                            {autocorreccion.productos.map((p, i) => (
                                <li key={i} className="tabular-nums">
                                    <span className="font-medium">{p.producto}</span>
                                    {p.sin_respaldo
                                        ? ` — ${num(p.cantidad_antes)} und sin ningún documento que las respalde (no se tocaron: revisar)`
                                        : describirCorreccion(p)}
                                </li>
                            ))}
                        </ul>
                    )}
                    {autocorreccion.stock_corregidos + autocorreccion.sin_respaldo > autocorreccion.productos.length && (
                        <p className="mt-1 opacity-80">
                            … y {autocorreccion.stock_corregidos + autocorreccion.sin_respaldo - autocorreccion.productos.length} más (detalle en Auditoría).
                        </p>
                    )}
                </Callout>
            )}

            {/* ── KPIs (clickables para filtrar por estado) ────────────── */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
                <Kpi icon={<CircleDollarSign size={18} />} label="Valor inventario" valor={money(kpis.valor)} color="primary" destacado />
                <Kpi icon={<Boxes size={18} />} label="Productos" valor={String(kpis.total)} />
                <Kpi icon={<TriangleAlert size={18} />} label="Stock bajo" valor={String(kpis.bajos)}
                    color="warning" activo={filters.estado === 'bajo'} onClick={() => toggleEstado('bajo')}
                    sub={`≤ ${umbralBajo} und`} />
                <Kpi icon={<PackageX size={18} />} label="Agotados" valor={String(kpis.agotados)}
                    color="danger" activo={filters.estado === 'agotado'} onClick={() => toggleEstado('agotado')} />
                <Kpi icon={<AlertTriangle size={18} />} label="Negativos" valor={String(kpis.negativos)}
                    color="danger" activo={filters.estado === 'negativo'} onClick={() => toggleEstado('negativo')} />
            </div>

            {/* Banner + lista exacta de negativos */}
            {stocksNegativosCount > 0 && (
                <div className="mb-4 rounded-lg overflow-hidden border" style={{ borderColor: '#fecaca' }}>
                    <div className="flex items-start gap-3 px-4 py-3" style={{ background: '#fef2f2', color: '#991b1b' }}>
                        <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                        <div className="text-sm">
                            <p className="font-semibold">
                                {stocksNegativosCount === 1
                                    ? 'Hay 1 producto con stock negativo'
                                    : `Hay ${stocksNegativosCount} productos con stock negativo`}
                            </p>
                            <p className="mt-0.5 opacity-90">
                                Salió más mercadería de la registrada (venta sin transferencia/entrada previa, etc.).
                                Regulariza el inventario con una entrada o transferencia, o usa "Recalcular stock".
                            </p>
                        </div>
                    </div>
                    <div className="overflow-x-auto" style={{ backgroundColor: 'var(--color-surface)' }}>
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                    <th className="text-left px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Producto</th>
                                    {mostrarSelector && (
                                        <th className="text-left px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Almacén</th>
                                    )}
                                    <th className="text-right px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Stock actual</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                {stocksNegativos.map(s => (
                                    <tr key={`${s.producto_id}-${s.almacen}`}>
                                        <td className="px-4 py-2" style={{ color: 'var(--color-text)' }}>
                                            <span className="font-medium">{s.producto}</span>
                                            {s.codigo && (
                                                <span className="ml-2 font-mono text-xs" style={{ color: 'var(--color-text-muted)' }}>{s.codigo}</span>
                                            )}
                                        </td>
                                        {mostrarSelector && (
                                            <td className="px-4 py-2" style={{ color: 'var(--color-text-muted)' }}>{s.almacen}</td>
                                        )}
                                        <td className="px-4 py-2 text-right font-bold" style={{ color: 'var(--color-danger)', fontVariantNumeric: 'tabular-nums' }}>
                                            {Number(s.cantidad)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* ── Filtros ──────────────────────────────────────────────── */}
            <FiltrosCard cols={4} tieneFiltros={hayFiltros} onClear={limpiar}>
                <div className="col-span-2">
                    <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Buscar</label>
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 z-10" style={{ color: 'var(--color-text-muted)' }} />
                        <Input
                            className="pl-9"
                            placeholder="Nombre o código… (Enter)"
                            value={busqueda}
                            onChange={e => setBusqueda(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && navegar({ busqueda: busqueda || undefined })}
                        />
                    </div>
                </div>
                {mostrarSelector && (
                    <Select
                        label="Almacén"
                        value={filters.almacen_id ?? ''}
                        onChange={v => navegar({ almacen_id: String(v) || undefined })}
                        options={[
                            { value: '', label: 'Todos los almacenes' },
                            ...almacenes.map(a => ({ value: a.id, label: a.nombre })),
                        ]}
                    />
                )}
                <Select
                    label="Categoría"
                    value={filters.categoria_id ?? ''}
                    onChange={v => navegar({ categoria_id: String(v) || undefined })}
                    options={[
                        { value: '', label: 'Todas las categorías' },
                        ...categorias.map(c => ({ value: c.id, label: c.nombre })),
                    ]}
                />
                <Select
                    label="Estado"
                    value={filters.estado ?? ''}
                    onChange={v => navegar({ estado: String(v) || undefined })}
                    options={[
                        { value: '', label: 'Todo el stock' },
                        { value: 'con_stock', label: 'Con stock' },
                        { value: 'bajo', label: 'Stock bajo' },
                        { value: 'agotado', label: 'Agotados' },
                        { value: 'negativo', label: 'Negativos' },
                    ]}
                />
                <Select
                    label="Ordenar por"
                    value={ordenActual}
                    onChange={v => {
                        const [sort, dir] = String(v).split(':');
                        navegar({ sort, dir });
                    }}
                    options={Object.entries(ORDENES).map(([value, label]) => ({ value, label }))}
                />
            </FiltrosCard>

            {/* Paginación server-side normalizada: la maneja el propio Table
                (recibe el paginador de Laravel y navega conservando la query). */}
            <Table
                data={stocks}
                columns={columns}
                searchable={false}
                sortable={false}
                emptyMessage="No hay stock para este filtro"
                onExportExcel={() => {
                    const params = new URLSearchParams(window.location.search);
                    params.delete('page');
                    const url = route('inventario.stock.exportar') + (params.toString() ? `?${params.toString()}` : '');
                    window.open(url, '_blank');
                }}
                exportFilename="stock"
            />

            {/* Modal: Ajustar stock — ingreso/salida por ajuste, sin dinero, con fecha propia */}
            <Modal
                isOpen={ajuste !== null}
                onClose={() => setAjuste(null)}
                title={ajuste ? `Ajustar stock — ${ajuste.producto.nombre}` : ''}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAjuste(null)}>Cancelar</Button>
                        <Button loading={ajSaving} onClick={guardarAjuste}>Guardar ajuste</Button>
                    </>
                }
            >
                {ajuste && (
                    <div className="space-y-4">
                        <div className="rounded-lg px-3 py-2 flex items-center justify-between text-sm"
                             style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
                            <span style={{ color: 'var(--color-text-muted)' }}>Stock en el sistema</span>
                            <span className="font-bold" style={{ fontVariantNumeric: 'tabular-nums' }}>{Number(ajuste.cantidad).toLocaleString('es-PE')}</span>
                        </div>

                        <Select label="Tipo de ajuste" required
                            options={[
                                { value: 'ingreso', label: 'Ingreso (+) — subir stock' },
                                { value: 'salida',  label: 'Salida (−) — bajar stock' },
                            ]}
                            value={ajDireccion}
                            onChange={(v) => setAjDir(v as 'ingreso' | 'salida')}
                        />
                        <Input label="Cantidad" required type="number" min="0" step="any" inputMode="decimal"
                            value={ajCantidad} onChange={(e) => setAjCant(e.target.value)} error={ajErrors.cantidad} />
                        <Input label="Fecha" required type="date" value={ajFecha}
                            onChange={(e) => setAjFecha(e.target.value)} error={ajErrors.fecha}
                            hint="Puedes fecharlo a un día anterior; el kardex y el saldo se recalculan a esa fecha." />
                        <Input label="Motivo" required value={ajMotivo}
                            onChange={(e) => setAjMotivo(e.target.value)} error={ajErrors.motivo}
                            placeholder="Ej. conteo físico, merma, regularización" />

                        {parseFloat(ajCantidad) > 0 && (
                            <div className="rounded-lg px-3 py-2 flex items-center justify-between text-sm"
                                 style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)' }}>
                                <span style={{ color: 'var(--color-text-muted)' }}>Stock resultante</span>
                                <span className="font-bold" style={{ color: ajResultado < 0 ? 'var(--color-danger)' : 'var(--color-primary)', fontVariantNumeric: 'tabular-nums' }}>
                                    {ajResultado.toLocaleString('es-PE')}
                                </span>
                            </div>
                        )}
                        <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                            No mueve dinero ni caja. Sale en el kardex como “Ajuste (+/−)” y sobrevive al Recalcular.
                        </p>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}

// ── KPI card ────────────────────────────────────────────────────────────────
function Kpi({ icon, label, valor, sub, color, destacado, activo, onClick }: {
    icon: React.ReactNode;
    label: string;
    valor: string;
    sub?: string;
    color?: 'primary' | 'danger' | 'warning';
    destacado?: boolean;
    activo?: boolean;
    onClick?: () => void;
}) {
    const accent = color === 'danger' ? 'var(--color-danger)'
        : color === 'warning' ? 'var(--color-warning)'
        : color === 'primary' ? 'var(--color-primary)'
        : 'var(--color-text-muted)';
    const clickable = !!onClick;
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={!clickable}
            className={`text-left rounded-xl p-3.5 flex flex-col gap-1.5 transition-all ${clickable ? 'hover:shadow-sm cursor-pointer' : 'cursor-default'}`}
            style={{
                backgroundColor: destacado ? 'color-mix(in srgb, var(--color-primary) 6%, var(--color-surface))' : 'var(--color-surface)',
                border: activo
                    ? `1px solid ${accent}`
                    : destacado
                        ? '1px solid color-mix(in srgb, var(--color-primary) 35%, transparent)'
                        : '1px solid var(--color-border)',
                boxShadow: activo ? `0 0 0 3px color-mix(in srgb, ${accent} 15%, transparent)` : undefined,
            }}
        >
            <div className="flex items-center gap-1.5" style={{ color: accent }}>
                {icon}
                <span className="text-[11px] font-semibold uppercase tracking-wide truncate">{label}</span>
            </div>
            <span className="text-lg font-bold leading-none" style={{ color: 'var(--color-text)' }}>{valor}</span>
            {sub && <span className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>{sub}</span>}
        </button>
    );
}
