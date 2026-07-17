import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { ArrowLeft, Plus, Trash2, CheckCircle2, Lock, RefreshCw, ZoomIn, ChevronDown, ArrowDownCircle, ArrowUpCircle, Coins, Landmark, TrendingUp, ArrowUpRight, ArrowDownRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import StatGrid from '@/Components/UI/StatGrid';
import Collapse from '@/Components/UI/Collapse';
import DetalleAgrupado from '@/Components/Finanzas/DetalleAgrupado';
import type { PageProps } from '@/types';

interface Item {
    id: number;
    seccion: 'favor' | 'contra';
    categoria: string;
    descripcion: string;
    ref_id: number | null;
    monto: string;
    es_manual: boolean;
    conciliado: boolean;
}

interface Balance {
    id: number;
    fecha: string;
    estado: 'borrador' | 'confirmado';
    total_favor: string;
    total_contra: string;
    balance_neto: string;
    balance_anterior: string | null;
    diferencia: string | null;
    gastos_dia: string;
    utilidad_real: string | null;
    items: Item[];
    user?: { name: string } | null;
}

interface Gasto {
    id: number;
    monto: string;
    comentario: string | null;
    tipo?: { nombre: string } | null;
    concepto?: { nombre: string } | null;
    cuenta?: { nombre: string; es_efectivo: boolean } | null;
}

interface MovimientoDia {
    descripcion: string;
    cuenta: string;
    tipo: 'ingreso' | 'egreso';
    monto: number;
    user: string | null;
}

interface GrupoMovimientosDia {
    label: string;
    ingresos: number;
    egresos: number;
    items: MovimientoDia[];
}

interface Variacion {
    categoria: string;
    seccion: 'favor' | 'contra';
    label: string;
    hoy: number;
    ayer: number;
    delta: number;
}

interface Props extends PageProps {
    balance: Balance;
    gastos: Gasto[];
    salidasDia: { label: string; monto: number }[];
    movimientosDia: GrupoMovimientosDia[];
    saldosCuentas: { id: number; nombre: string; banco: string | null; es_efectivo: boolean; saldo: number }[];
    variaciones: Variacion[];
    balanceAnteriorFecha: string | null;
    esAdmin?: boolean;
    puedeReabrir?: boolean;
}

const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;

/** Categorías cuyo monto se puede "abrir" para ver de dónde sale. */
const CON_DETALLE = new Set([
    'efectivo', 'cuenta_bancaria', 'stock', 'stock_mov', 'cxc', 'cxp', 'gastos_emitidos',
    'anticipo_cliente', 'adelanto_proveedor', 'deuda', 'personal', 'prestamo_otorgado',
    'planilla_descuento',
]);

interface DetalleData {
    tipo: 'grupos';
    [k: string]: any;
}

const CATEGORIA_LABEL: Record<string, string> = {
    cuenta_bancaria:    'Cuenta',
    efectivo:           'Efectivo',
    stock:              'Stock',
    stock_mov:          'Movimientos de inventario',
    cxc:                'Por cobrar',
    adelanto_proveedor: 'Adelanto prov.',
    prestamo_otorgado:  'Préstamo otorgado',
    otro_favor:         'Otro',
    cxp:                'Proveedores',
    gastos_emitidos:    'Ya pagados',
    anticipo_cliente:   'Anticipos',
    deuda:              'Deuda',
    personal:           'Personal',
    otro_contra:        'Otro',
};

export default function BalanceDiarioDetalle({ balance, gastos, salidasDia, movimientosDia, saldosCuentas, variaciones, balanceAnteriorFecha, puedeReabrir }: Props) {
    const { flash } = usePage<Props>().props;
    const editable = balance.estado === 'borrador';

    const [agregandoEn, setAgregandoEn]   = useState<'favor' | 'contra' | null>(null);
    const [movAbiertos, setMovAbiertos]   = useState<string[]>([]);
    const [confirmando, setConfirmando]   = useState(false);
    // Reapertura (admin, solo el último confirmado): vuelve el balance a
    // borrador y lo regenera — p. ej. tras registrar una venta olvidada con
    // fecha de ese día en un turno reabierto.
    const [reabriendo, setReabriendo]     = useState(false);
    const [motivoReabrir, setMotivoReabrir] = useState('');
    const [saving, setSaving]             = useState(false);
    const [errors, setErrors]             = useState<Record<string, string>>({});
    const [formLinea, setFormLinea]       = useState({ descripcion: '', monto: '' });
    // Montos en edición local (se envían al perder foco / Enter)
    const [montosEdit, setMontosEdit]     = useState<Record<number, string>>({});

    // F9 — Modal de detalle de una línea ("¿de dónde sale este monto?")
    const [detalleDe, setDetalleDe]       = useState<Item | null>(null);
    const [detalleData, setDetalleData]   = useState<DetalleData | null>(null);
    const [detalleCargando, setDetalleCargando] = useState(false);
    const [rango, setRango]               = useState({ desde: '', hasta: '' });

    async function cargarDetalle(item: Item, desde?: string, hasta?: string) {
        setDetalleCargando(true);
        try {
            const params = new URLSearchParams();
            if (item.ref_id) params.set('ref_id', String(item.ref_id));
            if (desde) params.set('desde', desde);
            if (hasta) params.set('hasta', hasta);
            const url = route('finanzas.balance.detalle', { fecha: balance.fecha.slice(0, 10), categoria: item.categoria })
                + (params.toString() ? `?${params.toString()}` : '');
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.status === 404) {
                // La línea referencia un registro que ya no existe: la página
                // quedó abierta con un balance viejo (p.ej. tras regenerar datos).
                toast.error('Esta línea cambió desde que abriste la página. Recargando…');
                setTimeout(() => router.reload(), 800);
                setDetalleDe(null);
                return;
            }
            if (!res.ok) throw new Error('No se pudo cargar el detalle');
            const data = await res.json();
            setDetalleData(data);
            if (data.desde) setRango({ desde: data.desde, hasta: data.hasta });
        } catch {
            toast.error('No se pudo cargar el detalle de esta línea.');
            setDetalleDe(null);
        } finally {
            setDetalleCargando(false);
        }
    }

    function abrirDetalle(item: Item) {
        if (!CON_DETALLE.has(item.categoria)) return;
        setDetalleDe(item);
        setDetalleData(null);
        void cargarDetalle(item);
    }

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const fechaLabel = new Date(balance.fecha.slice(0, 10) + 'T00:00:00')
        .toLocaleDateString('es-PE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });

    function guardarMonto(item: Item) {
        const valor = montosEdit[item.id];
        if (valor === undefined || Number(valor) === Number(item.monto)) return;
        router.put(route('finanzas.balance.items.update', item.id), { monto: valor } as any, {
            preserveScroll: true,
            onSuccess: () => setMontosEdit(m => { const c = { ...m }; delete c[item.id]; return c; }),
        });
    }

    function toggleConciliado(item: Item) {
        router.put(route('finanzas.balance.items.update', item.id), { conciliado: !item.conciliado } as any, {
            preserveScroll: true,
        });
    }

    function eliminarLinea(item: Item) {
        router.delete(route('finanzas.balance.items.destroy', item.id), { preserveScroll: true });
    }

    function agregarLinea() {
        if (!agregandoEn) return;
        setSaving(true);
        router.post(route('finanzas.balance.items.store', balance.id), {
            seccion: agregandoEn, ...formLinea,
        } as any, {
            preserveScroll: true,
            onSuccess: () => { setAgregandoEn(null); setFormLinea({ descripcion: '', monto: '' }); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function confirmarBalance() {
        setSaving(true);
        router.post(route('finanzas.balance.confirmar', balance.id), {}, {
            onSuccess: () => { setConfirmando(false); setSaving(false); },
            onError:   () => setSaving(false),
        });
    }

    function renderSeccion(seccion: 'favor' | 'contra') {
        const items = balance.items.filter(i => i.seccion === seccion);
        const esFavor = seccion === 'favor';

        return (
            <div
                className="rounded-2xl overflow-hidden flex-1 min-w-0"
                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
            >
                <div
                    className="flex items-center justify-between px-4 py-3"
                    style={{
                        backgroundColor: esFavor
                            ? 'color-mix(in srgb, var(--color-success, #16a34a) 12%, var(--color-bg))'
                            : 'color-mix(in srgb, var(--color-danger) 12%, var(--color-bg))',
                    }}
                >
                    <h3 className="font-bold text-sm uppercase tracking-wider"
                        style={{ color: esFavor ? 'var(--color-success)' : 'var(--color-danger)' }}>
                        {esFavor ? 'A favor' : 'En contra'}
                    </h3>
                    <span className="font-bold" style={{ color: esFavor ? 'var(--color-success)' : 'var(--color-danger)' }}>
                        {money(esFavor ? balance.total_favor : balance.total_contra)}
                    </span>
                </div>

                <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                    {items.map(item => (
                        <div key={item.id} className="flex items-center gap-2 px-3 py-2">
                            {/* Conciliado: el "OK" del Excel */}
                            <button
                                onClick={() => editable && toggleConciliado(item)}
                                disabled={!editable}
                                title={item.conciliado ? 'Conciliado' : 'Marcar como conciliado (OK)'}
                                className="flex-shrink-0 disabled:cursor-default"
                            >
                                <CheckCircle2
                                    size={17}
                                    style={{ color: item.conciliado ? 'var(--color-success)' : 'var(--color-border)' }}
                                />
                            </button>

                            <div className="flex-1 min-w-0">
                                {CON_DETALLE.has(item.categoria) ? (
                                    <button
                                        onClick={() => abrirDetalle(item)}
                                        className="block w-full text-left group"
                                        title="Ver de dónde sale este monto"
                                    >
                                        <p className="text-sm font-medium truncate flex items-center gap-1 group-hover:underline"
                                            style={{ color: 'var(--color-primary)' }}>
                                            {item.descripcion}
                                            <ZoomIn size={12} className="flex-shrink-0 opacity-50" />
                                        </p>
                                        <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                                            {CATEGORIA_LABEL[item.categoria] ?? item.categoria}{item.es_manual ? ' · manual' : ' · automático'} · clic para detalle
                                        </p>
                                    </button>
                                ) : (
                                    <>
                                        <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                            {item.descripcion}
                                        </p>
                                        <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                                            {CATEGORIA_LABEL[item.categoria] ?? item.categoria}{item.es_manual ? ' · manual' : ' · automático'}
                                        </p>
                                    </>
                                )}
                            </div>

                            {item.es_manual && editable ? (
                                <input
                                    type="number"
                                    step="0.01"
                                    className="w-28 text-right text-sm rounded-lg px-2 py-1 border outline-none font-semibold"
                                    style={{
                                        borderColor: 'var(--color-border)',
                                        backgroundColor: 'var(--color-bg)',
                                        color: 'var(--color-text)',
                                    }}
                                    value={montosEdit[item.id] ?? String(Number(item.monto))}
                                    onChange={e => setMontosEdit(m => ({ ...m, [item.id]: e.target.value }))}
                                    onBlur={() => guardarMonto(item)}
                                    onKeyDown={e => { if (e.key === 'Enter') (e.target as HTMLInputElement).blur(); }}
                                />
                            ) : (
                                <span className="text-sm font-semibold whitespace-nowrap">{money(item.monto)}</span>
                            )}

                            {item.es_manual && editable && (item.categoria === 'otro_favor' || item.categoria === 'otro_contra') && (
                                <button onClick={() => eliminarLinea(item)} title="Eliminar línea"
                                    className="p-1 rounded hover:bg-black/5 flex-shrink-0"
                                    style={{ color: 'var(--color-danger)' }}>
                                    <Trash2 size={14} />
                                </button>
                            )}
                        </div>
                    ))}
                    {items.length === 0 && (
                        <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin líneas</p>
                    )}
                </div>



                {editable && (
                    <div className="px-3 py-2 flex items-center justify-between gap-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                        <button
                            onClick={() => { setErrors({}); setFormLinea({ descripcion: '', monto: '' }); setAgregandoEn(seccion); }}
                            className="flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-lg hover:bg-black/5"
                            style={{ color: 'var(--color-primary)' }}
                        >
                            <Plus size={13} />Agregar línea manual
                        </button>
                        {esFavor && (
                            <button
                                onClick={() => router.visit(route('finanzas.tesoreria.index'))}
                                className="text-[11px] underline hover:opacity-80"
                                style={{ color: 'var(--color-text-muted)' }}
                                title="El efectivo y las cuentas se calculan solos; si no cuadran, registra un ajuste con motivo"
                            >
                                ¿Efectivo no cuadra? Ajustar en Tesorería
                            </button>
                        )}
                    </div>
                )}
            </div>
        );
    }

    const stats: { label: string; valor: string | null; color?: string; signo?: boolean }[] = [
        { label: 'Balance hoy',  valor: balance.balance_neto },
        { label: 'Balance ayer', valor: balance.balance_anterior },
        { label: 'Diferencia',   valor: balance.diferencia, signo: true },
        { label: 'Gastos del día', valor: balance.gastos_dia, color: 'var(--color-warning)' },
        { label: 'Utilidad real',  valor: balance.utilidad_real, signo: true },
    ];

    return (
        <AppLayout title={`Balance ${balance.fecha}`}>
            {/* Cabecera */}
            <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div className="flex items-center gap-3">
                    <button onClick={() => router.visit(route('finanzas.balance.index'))}
                        className="p-2 rounded-lg hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}>
                        <ArrowLeft size={18} />
                    </button>
                    <div>
                        <h1 className="text-xl font-bold capitalize" style={{ color: 'var(--color-text)' }}>{fechaLabel}</h1>
                        <div className="flex items-center gap-2 mt-0.5">
                            <Badge variant={editable ? 'warning' : 'success'}>
                                {editable ? 'Borrador — editable' : 'Confirmado'}
                            </Badge>
                            {!editable && <Lock size={13} style={{ color: 'var(--color-text-muted)' }} />}
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {editable && (
                        <>
                            <Button variant="ghost" onClick={() => router.reload()}>
                                <RefreshCw size={14} className="mr-1" />Recalcular
                            </Button>
                            <Button onClick={() => setConfirmando(true)}>
                                <CheckCircle2 size={15} className="mr-1" />Confirmar balance
                            </Button>
                        </>
                    )}
                    {!editable && puedeReabrir && (
                        <Button variant="ghost" onClick={() => setReabriendo(true)}>
                            <RefreshCw size={14} className="mr-1" />Reabrir balance
                        </Button>
                    )}
                </div>
            </div>

            {/* Modal reabrir balance (admin) */}
            <Modal isOpen={reabriendo} onClose={() => setReabriendo(false)} title="Reabrir balance confirmado" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setReabriendo(false)}>Cancelar</Button>
                        <Button variant="danger" disabled={saving || motivoReabrir.trim().length < 10}
                            onClick={() => {
                                setSaving(true);
                                router.post(route('finanzas.balance.reabrir', balance.id), { motivo: motivoReabrir.trim() }, {
                                    onFinish: () => setSaving(false),
                                    onSuccess: () => { setReabriendo(false); setMotivoReabrir(''); },
                                    onError: (errs: any) => { const m = Object.values(errs)[0]; if (m) toast.error(m as string); },
                                });
                            }}>
                            {saving ? 'Reabriendo…' : 'Sí, reabrir'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <Callout variant="warning">
                        El balance vuelve a <strong>borrador</strong> y sus líneas automáticas se recalculan con los datos actuales
                        (por ejemplo, una venta registrada después con fecha de este día). Las líneas manuales se conservan.
                        Al terminar, <strong>confírmalo de nuevo</strong> — es la referencia del día siguiente.
                    </Callout>
                    <Input label="Motivo (mínimo 10 caracteres)" required value={motivoReabrir}
                        onChange={e => setMotivoReabrir(e.target.value)}
                        placeholder="Ej.: venta del 11/07 registrada tarde (turno reabierto)" />
                </div>
            </Modal>

            {/* Resumen */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
                {stats.map(s => {
                    const n = s.valor !== null ? Number(s.valor) : null;
                    const color = s.color ?? (s.signo && n !== null
                        ? (n >= 0 ? 'var(--color-success)' : 'var(--color-danger)')
                        : 'var(--color-text)');
                    return (
                        <div key={s.label} className="rounded-2xl px-4 py-3"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                                {s.label}
                            </p>
                            <p className="text-lg font-bold" style={{ color }}>
                                {n === null ? '—' : `${s.signo && n > 0 ? '+' : ''}${money(n)}`}
                            </p>
                        </div>
                    );
                })}
            </div>

            {/* ¿Cuánto tengo? — saldo REAL por cuenta a esta fecha (neto:
                ingresos bruto A FAVOR − gastos emitidos de esa cuenta) */}
            <div className="mb-5">
                <p className="text-[11px] font-semibold uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>
                    Cuánto tengo por cuenta — saldo real a esta fecha
                </p>
                <StatGrid cols="grid-cols-2 sm:grid-cols-3 lg:grid-cols-5" stats={saldosCuentas.map(c => ({
                    label: c.banco ? `${c.nombre} · ${c.banco}` : c.nombre,
                    valor: money(c.saldo),
                    color: c.saldo < 0 ? 'danger' as const : undefined,
                    icon: c.es_efectivo ? <Coins size={16} /> : <Landmark size={16} />,
                    sub: 'Clic: entradas/salidas + auditoría',
                    // Abre el mismo detalle de la línea del balance: TODO lo que
                    // entró/salió de la cuenta con registrado/editado (auditoría).
                    onClick: () => abrirDetalle({
                        id: 0,
                        seccion: 'favor',
                        categoria: c.es_efectivo ? 'efectivo' : 'cuenta_bancaria',
                        descripcion: c.banco ? `${c.nombre} · ${c.banco}` : c.nombre,
                        ref_id: c.id,
                        monto: String(c.saldo),
                        es_manual: false,
                        conciliado: false,
                    }),
                }))} />
            </div>

            {/* Secciones favor / contra */}
            <div className="flex flex-col lg:flex-row gap-4 items-start">
                {renderSeccion('favor')}
                {renderSeccion('contra')}

                {/* Panel GASTOS DEL DÍA (solo, como acordado) */}
                <div className="w-full lg:w-72 flex-shrink-0 rounded-2xl overflow-hidden"
                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between px-4 py-3"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning, #f59e0b) 12%, var(--color-bg))' }}>
                        <h3 className="font-bold text-sm uppercase tracking-wider" style={{ color: 'var(--color-warning)' }}>
                            Gastos del día
                        </h3>
                        <span className="font-bold" style={{ color: 'var(--color-warning)' }}>{money(balance.gastos_dia)}</span>
                    </div>
                    <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                        {gastos.length === 0 ? (
                            <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin gastos este día</p>
                        ) : gastos.map(g => (
                            <div key={g.id} className="px-3 py-2 flex justify-between items-start text-sm">
                                <div className="min-w-0">
                                    <p className="font-medium truncate">{g.concepto?.nombre ?? g.tipo?.nombre ?? 'Gasto'}</p>
                                    {g.comentario && (
                                        <p className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>{g.comentario}</p>
                                    )}
                                    {/* De qué cuenta salió el gasto (pedido del cliente) */}
                                    <p className="text-[11px] inline-flex items-center gap-1 mt-0.5 truncate" style={{ color: 'var(--color-text-muted)' }}>
                                        {g.cuenta?.es_efectivo ? <Coins size={11} /> : <Landmark size={11} />}
                                        {g.cuenta?.nombre ?? 'Efectivo'}
                                    </p>
                                </div>
                                <span className="font-semibold whitespace-nowrap ml-2" style={{ fontVariantNumeric: 'tabular-nums' }}>{money(g.monto)}</span>
                            </div>
                        ))}
                    </div>
                    <p className="px-3 py-2 text-[11px]" style={{ color: 'var(--color-text-muted)', borderTop: '1px solid var(--color-border)' }}>
                        Utilidad real = (balance hoy − balance ayer) + gastos del día.
                        El gasto baja el balance pero no es pérdida del negocio: se suma
                        de vuelta para ver cuánto generó la operación antes de gastos.
                    </p>
                </div>
            </div>

            {/* ── Variación vs día anterior: qué subió/bajó por categoría ──
                Compara cada monto contra el último balance confirmado. Cada fila
                es clickeable para abrir el detalle de esa subida/baja. */}
            <div className="mt-4 rounded-2xl overflow-hidden"
                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))' }}>
                    <h3 className="font-bold text-sm uppercase tracking-wider flex items-center gap-2" style={{ color: 'var(--color-primary)' }}>
                        <TrendingUp size={16} /> Variación vs día anterior
                    </h3>
                    <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        {balanceAnteriorFecha
                            ? `Comparado con el balance del ${new Date(balanceAnteriorFecha + 'T00:00:00').toLocaleDateString('es-PE')}`
                            : 'Sin balance anterior confirmado para comparar'}
                    </span>
                </div>

                {!balanceAnteriorFecha ? (
                    <p className="text-sm text-center py-6 px-4" style={{ color: 'var(--color-text-muted)' }}>
                        Confirma el balance de días anteriores para ver aquí qué subió o bajó respecto a ayer.
                    </p>
                ) : variaciones.length === 0 ? (
                    <p className="text-sm text-center py-6 px-4" style={{ color: 'var(--color-text-muted)' }}>
                        Sin cambios de monto respecto al día anterior.
                    </p>
                ) : (
                    <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                        {variaciones.map(v => {
                            const subio = v.delta > 0;
                            const clickeable = CON_DETALLE.has(v.categoria);
                            const color = subio ? 'var(--color-success, #16a34a)' : 'var(--color-danger)';
                            return (
                                <button
                                    key={`${v.seccion}-${v.categoria}`}
                                    onClick={() => clickeable && abrirDetalle({ id: 0, seccion: v.seccion, categoria: v.categoria === 'stock' ? 'stock_mov' : v.categoria, descripcion: `${v.label} — variación del día`, ref_id: null, monto: String(v.hoy), es_manual: false, conciliado: false })}
                                    disabled={!clickeable}
                                    className="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left transition-colors hover:bg-black/[0.02] disabled:cursor-default"
                                >
                                    <div className="min-w-0 flex items-center gap-2">
                                        <span className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full"
                                            style={{ backgroundColor: subio ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)', color }}>
                                            {subio ? <ArrowUpRight size={15} /> : <ArrowDownRight size={15} />}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                                {v.label}
                                                <span className="ml-1.5 text-[10px] font-normal uppercase tracking-wide"
                                                    style={{ color: 'var(--color-text-muted)' }}>
                                                    {v.seccion === 'favor' ? 'a favor' : 'en contra'}
                                                </span>
                                            </p>
                                            <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                                Ayer {money(v.ayer)} → Hoy {money(v.hoy)}
                                                {clickeable && <span className="ml-1 inline-flex items-center gap-0.5" style={{ color: 'var(--color-primary)' }}><ZoomIn size={10} /> ver detalle</span>}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="font-bold text-sm whitespace-nowrap" style={{ color, fontVariantNumeric: 'tabular-nums' }}>
                                        {subio ? '+' : '−'}{money(Math.abs(v.delta)).replace('S/ ', 'S/ ')}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* ── Movimientos del día: la película de HOY, por concepto ──
                Las líneas del balance son acumulados en bruto; aquí se ve cada
                operación de esta fecha (ventas, descuentos de caja, pagos...)
                para responder "¿por qué se movió el balance hoy?". */}
            <div className="mt-4 rounded-2xl overflow-hidden"
                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))' }}>
                    <h3 className="font-bold text-sm uppercase tracking-wider" style={{ color: 'var(--color-primary)' }}>
                        Movimientos del día
                    </h3>
                    <div className="flex items-center gap-4 text-sm font-semibold">
                        <span className="inline-flex items-center gap-1" style={{ color: 'var(--color-success)' }}>
                            <ArrowDownCircle size={14} />+{money(movimientosDia.reduce((s, g) => s + g.ingresos, 0))}
                        </span>
                        <span className="inline-flex items-center gap-1" style={{ color: 'var(--color-danger)' }}>
                            <ArrowUpCircle size={14} />−{money(movimientosDia.reduce((s, g) => s + g.egresos, 0))}
                        </span>
                    </div>
                </div>
                {movimientosDia.length === 0 ? (
                    <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>
                        Sin movimientos de dinero en esta fecha.
                    </p>
                ) : (
                    <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                        {movimientosDia.map(g => {
                            const abierto = movAbiertos.includes(g.label);
                            return (
                                <div key={g.label}>
                                    <button
                                        type="button"
                                        onClick={() => setMovAbiertos(prev =>
                                            prev.includes(g.label) ? prev.filter(l => l !== g.label) : [...prev, g.label])}
                                        className="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-black/[0.03]"
                                    >
                                        <span className="flex items-center gap-2 font-medium" style={{ color: 'var(--color-text)' }}>
                                            <ChevronDown size={15} className="transition-transform duration-300"
                                                style={{ transform: abierto ? 'rotate(0deg)' : 'rotate(-90deg)', color: 'var(--color-text-muted)' }} />
                                            {g.label}
                                            <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                                ({g.items.length})
                                            </span>
                                        </span>
                                        <span className="flex items-center gap-4 font-semibold" style={{ fontVariantNumeric: 'tabular-nums' }}>
                                            {g.ingresos > 0 && <span style={{ color: 'var(--color-success)' }}>+{money(g.ingresos)}</span>}
                                            {g.egresos > 0 && <span style={{ color: 'var(--color-danger)' }}>−{money(g.egresos)}</span>}
                                        </span>
                                    </button>
                                    <Collapse open={abierto}>
                                        <div className="px-4 pb-2.5">
                                            {g.items.map((m, i) => (
                                                <div key={i} className="flex items-center justify-between gap-3 py-1.5 pl-6 text-sm border-t"
                                                    style={{ borderColor: 'color-mix(in srgb, var(--color-border) 55%, transparent)' }}>
                                                    <span className="min-w-0 truncate" style={{ color: 'var(--color-text)' }}>
                                                        {m.descripcion}
                                                        <span className="text-xs ml-2" style={{ color: 'var(--color-text-muted)' }}>
                                                            {m.cuenta}{m.user ? ` · ${m.user}` : ''}
                                                        </span>
                                                    </span>
                                                    <span className="font-semibold whitespace-nowrap"
                                                        style={{ color: m.tipo === 'ingreso' ? 'var(--color-success)' : 'var(--color-danger)', fontVariantNumeric: 'tabular-nums' }}>
                                                        {m.tipo === 'ingreso' ? '+' : '−'}{money(m.monto)}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </Collapse>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Card consolidador: todo A FAVOR, todo EN CONTRA, gastos y el balance */}
            <div className="mt-4 rounded-2xl overflow-hidden"
                style={{ border: '2px solid var(--color-primary)', backgroundColor: 'var(--color-surface)' }}>
                <div className="px-4 py-2" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, var(--color-bg))' }}>
                    <p className="text-sm font-bold uppercase tracking-wider" style={{ color: 'var(--color-primary)' }}>
                        Consolidado del día
                    </p>
                </div>
                <div className="px-4 py-3 grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>Total a favor</p>
                        <p className="text-xl font-bold" style={{ color: 'var(--color-success)' }}>{money(balance.total_favor)}</p>
                    </div>
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>Total en contra</p>
                        <p className="text-xl font-bold" style={{ color: 'var(--color-danger)' }}>−{money(balance.total_contra)}</p>
                    </div>
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>Gastos del día</p>
                        <p className="text-xl font-bold" style={{ color: 'var(--color-warning)' }}>{money(balance.gastos_dia)}</p>
                        <p className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>ya restados en "Gastos emitidos" (en contra); no se restan dos veces</p>
                    </div>
                    <div className="rounded-xl px-3 py-2 -my-1"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, var(--color-bg))' }}>
                        <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-primary)' }}>
                            BALANCE = a favor − en contra
                        </p>
                        <p className="text-2xl font-bold" style={{ color: 'var(--color-primary)' }}>{money(balance.balance_neto)}</p>
                        {balance.utilidad_real !== null && (
                            <p className="text-xs font-semibold"
                                style={{ color: Number(balance.utilidad_real) >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }}>
                                Utilidad real: {Number(balance.utilidad_real) >= 0 ? '+' : ''}{money(balance.utilidad_real)}
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal agregar línea manual */}
            <Modal isOpen={agregandoEn !== null} onClose={() => setAgregandoEn(null)}
                title={`Nueva línea ${agregandoEn === 'favor' ? 'A FAVOR' : 'EN CONTRA'}`} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAgregandoEn(null)}>Cancelar</Button>
                        <Button onClick={agregarLinea} disabled={saving}>{saving ? 'Guardando...' : 'Agregar'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Callout variant="warning" title="Usa esto solo para casos puntuales.">
                        Lo recurrente debe ir en su módulo
                        para no perder trazabilidad: préstamos y cuotas (ej. la moto de un trabajador) →
                        <em> Deudas y préstamos</em>; dinero adelantado → <em>Anticipos/Adelantos</em>;
                        dinero en cuentas → <em>Tesorería</em>. Esta línea quedará registrada en auditoría
                        con tu usuario.
                    </Callout>
                    <Input label="Descripción" required placeholder='Ej: "Depósito Oscar Alberto", "16 fierros 3/4"'
                        value={formLinea.descripcion}
                        onChange={e => setFormLinea(f => ({ ...f, descripcion: e.target.value }))}
                        error={errors.descripcion}
                    />
                    <Input label="Monto" required type="number" min="0" step="0.01"
                        value={formLinea.monto}
                        onChange={e => setFormLinea(f => ({ ...f, monto: e.target.value }))}
                        error={errors.monto}
                    />
                </div>
            </Modal>

            {/* F9 — Modal detalle de línea: de dónde sale el monto */}
            <Modal isOpen={detalleDe !== null} onClose={() => setDetalleDe(null)}
                title={detalleDe ? `Detalle — ${detalleDe.descripcion} (${money(detalleDe.monto)})` : ''} size="5xl"
                footer={<Button variant="ghost" onClick={() => setDetalleDe(null)}>Cerrar</Button>}
            >
                {detalleCargando && (
                    <p className="text-sm text-center py-8" style={{ color: 'var(--color-text-muted)' }}>Cargando detalle...</p>
                )}

                {!detalleCargando && detalleData?.tipo === 'grupos' && (
                    <div className="space-y-4">
                        {/* Filtro de fechas (solo categorías con período) */}
                        {detalleData.desde && (
                            <div className="flex flex-wrap items-end gap-2">
                                <div>
                                    <label className="block text-[11px] font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>Desde</label>
                                    <input type="date" value={rango.desde} max={rango.hasta}
                                        onChange={e => setRango(r => ({ ...r, desde: e.target.value }))}
                                        className="text-sm rounded-lg px-2 py-1.5 border outline-none"
                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                </div>
                                <div>
                                    <label className="block text-[11px] font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>Hasta</label>
                                    <input type="date" value={rango.hasta} max={balance.fecha.slice(0, 10)}
                                        onChange={e => setRango(r => ({ ...r, hasta: e.target.value }))}
                                        className="text-sm rounded-lg px-2 py-1.5 border outline-none"
                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                </div>
                                <Button onClick={() => detalleDe && cargarDetalle(detalleDe, rango.desde, rango.hasta)}>Filtrar</Button>
                            </div>
                        )}

                        <DetalleAgrupado
                            cards={detalleData.cards ?? []}
                            grupos={detalleData.grupos ?? []}
                            itemCols={detalleData.itemCols}
                            montoLabel={detalleData.montoLabel}
                            emptyMessage="Sin datos en el período"
                        />
                    </div>
                )}
            </Modal>

            {/* Modal confirmar */}
            <Modal isOpen={confirmando} onClose={() => setConfirmando(false)} title="Confirmar balance del día" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmando(false)}>Cancelar</Button>
                        <Button onClick={confirmarBalance} disabled={saving}>{saving ? 'Confirmando...' : 'Sí, confirmar'}</Button>
                    </>
                }
            >
                <div className="space-y-3 text-sm" style={{ color: 'var(--color-text)' }}>
                    <p>Al confirmar, el balance queda <strong>inmutable</strong> y será la referencia ("balance de ayer") para el siguiente día.</p>
                    <StatGrid stats={[
                        { label: 'Balance del día', valor: money(balance.balance_neto), color: 'primary', destacado: true },
                        {
                            label: 'Utilidad real',
                            valor: balance.utilidad_real !== null ? money(balance.utilidad_real) : '—',
                            color: balance.utilidad_real !== null && Number(balance.utilidad_real) < 0 ? 'danger' : 'success',
                        },
                    ]} />
                </div>
            </Modal>
        </AppLayout>
    );
}
