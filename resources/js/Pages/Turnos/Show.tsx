import { useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    ArrowLeft, HandCoins, Lock, Package, Pencil, RotateCcw, Search,
    ShoppingCart, TrendingDown, Wallet, Scale, X,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import { Panel, Filas, Empty, fmtS, fmtCant, plural } from '@/Components/Reportes/ReportUI';
import { CHART_COLORS } from '@/Components/UI/Charts';
import ModalEditarApertura from './Partials/ModalEditarApertura';
import ReporteCajaTurno from './Partials/ReporteCajaTurno';
import type { PageProps, Turno, TurnoRetiro } from '@/types';
import { useTiempoReal } from '@/lib/useTiempoReal';
import Callout from '@/Components/UI/Callout';

/** Lo que entró por un medio de pago en el turno. */
interface Cobro { metodo_pago_id: number; nombre: string; es_efectivo: boolean; total: number; }

interface Props extends PageProps {
    turno:            Turno;
    ventasPorMetodo:  Record<string, number>;
    totalVentas:      number;
    totalGastos:      number;
    esAdmin:          boolean;
    configEfectivo?:  {
        modo_apertura_caja: 'libre' | 'arrastre' | 'fondo_fijo';
        apertura_editable:  boolean;
    };
    /** Ventas sin vuelto + abonos + anticipos − reembolsos, por medio de pago. */
    cobrosPorMetodo?: Cobro[];
    /** La empresa tiene el reporte de caja por turno (función opcional). */
    reporteCaja?:     boolean;
}

const num = (v: string | number | null | undefined) => parseFloat(String(v ?? 0)) || 0;
const fechaHora = (iso: string) =>
    new Date(iso).toLocaleString('es-PE', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const hora = (iso: string) => new Date(iso).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });

/** "5 h 36 min", "2 días y 3 h" */
function duracion(desde: string, hasta: string | null): string {
    const min = Math.max(0, Math.floor(((hasta ? new Date(hasta) : new Date()).getTime() - new Date(desde).getTime()) / 60000));
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    if (h < 24) return `${h} h ${min % 60} min`;
    return `${plural(Math.floor(h / 24), 'día', 'días')} y ${h % 24} h`;
}

export default function TurnoShow({ turno, totalVentas, totalGastos, esAdmin, configEfectivo, cobrosPorMetodo = [], reporteCaja = false }: Props) {
    // Tiempo real: la caja del turno se pone al día con cada venta, gasto o retiro.
    useTiempoReal(['turnos', 'ventas'], () => router.reload());
    const { flash, auth } = usePage<Props>().props;
    const [modalReabrir, setModalReabrir] = useState(false);
    const [modalEditarApertura, setModalEditarApertura] = useState(false);
    const [reabriendo, setReabriendo]     = useState(false);
    // A8: motivo obligatorio. El backend exige min 10 chars; bloqueamos el
    // submit aqui mismo para feedback inmediato sin viajar al server.
    const [motivoReabrir, setMotivoReabrir] = useState('');

    const user = auth.user as { id: number; rol?: { es_admin?: boolean } } | undefined;
    const puedeEditarApertura = turno.estado === 'abierto' && (esAdmin || user?.id === turno.user_id);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const esCerrado = turno.estado === 'cerrado';
    const motivoValido = motivoReabrir.trim().length >= 10;

    const retiros = (turno.retiros ?? []) as TurnoRetiro[];
    const totalRetiros = retiros.reduce((s, r) => s + num(r.monto), 0);
    const entregadoAlCierre = retiros.filter(r => r.momento === 'cierre').reduce((s, r) => s + num(r.monto), 0);
    const gastos = turno.gastos ?? [];

    function aprobarRetiro(retiro: TurnoRetiro) {
        router.post(route('turnos.retiros.aprobar', retiro.id), {}, { preserveScroll: true });
    }

    /** Rechazar un retiro pendiente: el efectivo vuelve al esperado del cajón. */
    function rechazarRetiro(retiro: TurnoRetiro) {
        if (!window.confirm(`¿Rechazar el retiro de ${fmtS(num(retiro.monto))}? El efectivo vuelve a contarse en el cajón.`)) return;
        router.post(route('turnos.retiros.rechazar', retiro.id), {}, {
            preserveScroll: true,
            onError: (errs: Record<string, string>) => {
                const msj = Object.values(errs)[0];
                if (msj) toast.error(msj);
            },
        });
    }

    function reabrir() {
        if (!motivoValido) {
            toast.error('El motivo es obligatorio (mínimo 10 caracteres).');
            return;
        }
        setReabriendo(true);
        router.post(route('turnos.reabrir', turno.id), { motivo: motivoReabrir.trim() }, {
            onFinish: () => { setReabriendo(false); },
            onSuccess: () => {
                setModalReabrir(false);
                setMotivoReabrir('');
            },
            onError: (errs: Record<string, string>) => {
                const msj = Object.values(errs)[0];
                if (msj) toast.error(msj);
            },
        });
    }

    // ── Productos vendidos (ventas completadas), de mayor a menor ──
    const productosVendidos = useMemo(() => {
        const mapa: Record<string, { nombre: string; cantidad: number; total: number }> = {};
        (turno.ventas ?? [])
            .filter(v => v.estado === 'completada')
            .forEach(v => {
                (v.items ?? []).forEach(item => {
                    const key = item.producto_nombre as string;
                    if (!mapa[key]) mapa[key] = { nombre: key, cantidad: 0, total: 0 };
                    mapa[key].cantidad += num(item.cantidad as string);
                    mapa[key].total    += num(item.subtotal as string);
                });
            });
        return Object.values(mapa).sort((a, b) => b.total - a.total);
    }, [turno.ventas]);

    const totalEfectivoArqueo = useMemo(() =>
        (turno.arqueo ?? []).reduce((s, r) => s + r.denominacion * r.cantidad, 0),
    [turno.arqueo]);

    const ventasCompletadas = (turno.ventas ?? []).filter(v => v.estado === 'completada').length;
    const ventasAnuladas    = (turno.ventas ?? []).filter(v => v.estado === 'anulada').length;

    return (
        <AppLayout title={`Turno #${turno.id}`}>
            {/* ── Encabezado ─────────────────────────────────────────────────── */}
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0">
                    <Link href={route('turnos.index')}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold mb-2 hover:underline" style={{ color: 'var(--color-primary)' }}>
                        <ArrowLeft size={15} /> Volver a turnos
                    </Link>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>
                            {turno.caja?.nombre ?? 'Turno'} <span className="tabular-nums" style={{ color: 'var(--color-text-muted)' }}>#{turno.id}</span>
                        </h1>
                        <EstadoTurno cerrado={esCerrado} />
                    </div>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        <strong style={{ color: 'var(--color-text)' }}>{turno.user?.name ?? '—'}</strong>
                        {turno.local?.nombre ? `, ${turno.local.nombre}` : ''}. {fechaHora(turno.fecha_apertura)}
                        {turno.fecha_cierre ? ` a ${esMismoDia(turno.fecha_apertura, turno.fecha_cierre) ? hora(turno.fecha_cierre) : fechaHora(turno.fecha_cierre)}` : ', sigue abierto'}
                        {' '}({duracion(turno.fecha_apertura, turno.fecha_cierre)})
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {esAdmin && esCerrado && (
                        <Button variant="secondary" onClick={() => setModalReabrir(true)}>
                            <RotateCcw size={15} className="mr-1.5" />Reabrir turno
                        </Button>
                    )}
                    {/* Turno abierto (p. ej. reabierto para regularizar): el admin
                        puede registrar ventas EN este turno (con la fecha del turno)
                        y volver a cerrarlo, aunque no sea suyo. */}
                    {esAdmin && turno.estado === 'abierto' && (
                        <>
                            <Button onClick={() => router.visit(route('pos.index', { turno_id: turno.id }))}>
                                <ShoppingCart size={15} className="mr-1.5" />Registrar ventas
                            </Button>
                            <Button variant="secondary" onClick={() => router.visit(route('turnos.cerrar.page', turno.id))}>
                                <Lock size={15} className="mr-1.5" />Cerrar turno
                            </Button>
                        </>
                    )}
                </div>
            </div>

            {/* ── Resumen ────────────────────────────────────────────────────── */}
            <section className="rounded-2xl mb-4 grid grid-cols-2 lg:grid-cols-4 overflow-hidden"
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.14)' }}>
                <Dato label="Abrió con"
                    accion={puedeEditarApertura && (
                        <button onClick={() => setModalEditarApertura(true)} title="Corregir el monto de apertura" aria-label="Corregir el monto de apertura"
                            className="inline-flex items-center justify-center w-7 h-7 rounded-lg transition-colors hover:opacity-80"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)', color: 'var(--color-primary)' }}>
                            <Pencil size={13} />
                        </button>
                    )}>
                    <Valor>{fmtS(num(turno.monto_apertura))}</Valor>
                    <Nota>
                        {num(turno.monto_fondos_adicionales) > 0.009
                            ? `arrastre ${fmtS(num(turno.monto_apertura) - num(turno.monto_fondos_adicionales))} + ${fmtS(num(turno.monto_fondos_adicionales))} adicionales`
                            : `a las ${hora(turno.fecha_apertura)}`}
                        {turno.caja?.caja_chica_activa && `, caja chica ${fmtS(num(turno.monto_caja_chica))}`}
                    </Nota>
                </Dato>
                <Dato label="Vendió">
                    <Valor>{fmtS(totalVentas)}</Valor>
                    <Nota>
                        {plural(ventasCompletadas, 'venta', 'ventas')}
                        {ventasAnuladas > 0 && <span style={{ color: 'var(--vp-coral-ink)' }}>, {plural(ventasAnuladas, 'anulada', 'anuladas')}</span>}
                    </Nota>
                </Dato>
                <Dato label="Gastos y retiros">
                    <Valor>{fmtS(totalGastos + totalRetiros)}</Valor>
                    <Nota>{plural(gastos.length, 'gasto', 'gastos')}{retiros.length > 0 ? `, ${plural(retiros.length, 'retiro', 'retiros')}` : ''}</Nota>
                </Dato>
                <Dato label="Resultado de caja">
                    {esCerrado && turno.monto_cierre_declarado == null ? (
                        <>
                            <p className="font-display text-[24px] font-extrabold leading-tight" style={{ color: 'var(--color-text-muted)' }}>Sin conteo</p>
                            <Nota>se cerró sin contar el efectivo</Nota>
                        </>
                    ) : esCerrado ? (
                        <>
                            <ResultadoCaja diferencia={num(turno.diferencia)} />
                            <Nota>contó {fmtS(num(turno.monto_cierre_declarado))} de {fmtS(num(turno.monto_cierre_esperado))} esperados</Nota>
                        </>
                    ) : (
                        <>
                            <p className="text-[15px] font-bold mt-1" style={{ color: 'var(--vp-navy)' }}>Turno abierto</p>
                            <Nota>se sabrá al cerrarlo</Nota>
                        </>
                    )}
                </Dato>
            </section>

            {/* ── Reporte de caja: solo descarga (función opcional por empresa) ── */}
            {reporteCaja && <ReporteCajaTurno turnoId={turno.id} />}

            {/* ── Productos (alto fijo) + lo demás apilado ──────────────────── */}
            <div className="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start mb-4">
                <ProductosVendidos productos={productosVendidos} total={totalVentas} turnoId={turno.id} />

                <div className="xl:col-span-5 space-y-4 min-w-0">
                    <Panel icon={<Wallet size={17} />} titulo="Cómo le pagaron" color="var(--vp-mint)" tinta="var(--vp-mint-ink)"
                        detalle={cobrosPorMetodo.length ? fmtS(cobrosPorMetodo.reduce((s, c) => s + c.total, 0)) : undefined}>
                        <Filas items={cobrosPorMetodo.map((c, i) => ({
                            clave: c.metodo_pago_id, label: c.nombre, valor: c.total, color: CHART_COLORS[i % CHART_COLORS.length],
                        }))} vacio="No hubo cobros en este turno." />
                    </Panel>

                    <Panel icon={<TrendingDown size={17} />} titulo="Gastos" color="var(--vp-coral)" tinta="var(--vp-coral-ink)"
                        detalle={gastos.length ? fmtS(totalGastos) : undefined} sinPadding>
                        {gastos.length === 0 ? <Empty text="Sin gastos en este turno." /> : (
                            <ul className="max-h-72 overflow-y-auto">
                                {gastos.map(g => (
                                    <li key={g.id as number} className="flex items-start justify-between gap-3 px-4 py-2.5"
                                        style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>
                                                {g.concepto?.nombre ?? g.tipo?.nombre ?? 'Gasto'}
                                            </p>
                                            <p className="text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                {[g.tipo?.nombre, g.comentario as string | undefined].filter(Boolean).join(', ')}
                                            </p>
                                        </div>
                                        <span className="text-[15px] font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--vp-coral-ink)' }}>
                                            −{fmtS(num(g.monto))}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    {retiros.length > 0 && (
                        <Panel icon={<HandCoins size={17} />} titulo="Retiros de efectivo" color="var(--vp-amber)" tinta="var(--vp-amber-ink)"
                            detalle={fmtS(totalRetiros)} sinPadding>
                            <ul className="max-h-72 overflow-y-auto">
                                {retiros.map(r => (
                                    <li key={r.id} className="flex items-start justify-between gap-3 px-4 py-2.5"
                                        style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                                {r.concepto}
                                                {r.momento === 'cierre' && <span className="ml-1.5 text-xs font-semibold" style={{ color: 'var(--color-primary)' }}>al cierre</span>}
                                            </p>
                                            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                                {fechaHora(r.created_at as string)}, {r.user?.name ?? '—'}
                                            </p>
                                            {r.observacion && <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{r.observacion}</p>}
                                        </div>
                                        <div className="text-right flex-shrink-0">
                                            <p className="text-[15px] font-bold tabular-nums" style={{ color: 'var(--vp-amber-ink)' }}>−{fmtS(num(r.monto))}</p>
                                            {r.estado === 'aprobado' ? (
                                                <span className="text-xs font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>aprobado</span>
                                            ) : esAdmin ? (
                                                <span className="inline-flex gap-2">
                                                    <button onClick={() => aprobarRetiro(r)} className="text-xs font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                                                        Aprobar
                                                    </button>
                                                    <button onClick={() => rechazarRetiro(r)} className="text-xs font-semibold hover:underline" style={{ color: 'var(--color-danger)' }}>
                                                        Rechazar
                                                    </button>
                                                </span>
                                            ) : (
                                                <span className="text-xs font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>falta aprobar</span>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    )}

                    {esCerrado && (
                        <CierreDelTurno turno={turno} totalEfectivoArqueo={totalEfectivoArqueo} entregadoAlCierre={entregadoAlCierre} />
                    )}
                </div>
            </div>

            {/* Modal confirmar reabrir */}
            <Modal
                isOpen={modalReabrir}
                onClose={() => { setModalReabrir(false); setMotivoReabrir(''); }}
                title="Reabrir turno"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => { setModalReabrir(false); setMotivoReabrir(''); }}>
                            Cancelar
                        </Button>
                        <Button variant="danger" onClick={reabrir} disabled={reabriendo || !motivoValido}>
                            {reabriendo ? 'Reabriendo...' : 'Sí, reabrir'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <Callout variant="danger">
                        <p style={{ color: 'var(--color-text)' }}>
                            Se eliminará el arqueo de cierre y se anulará el cierre de inventario asociado.
                            El cajero podrá registrar ventas y gastos nuevamente hasta que se vuelva a cerrar.
                        </p>
                    </Callout>

                    {/* A8: motivo obligatorio para auditoria */}
                    <div>
                        <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                            Motivo de la reapertura <span style={{ color: 'var(--color-danger)' }}>*</span>
                        </label>
                        <textarea
                            value={motivoReabrir}
                            onChange={(e) => setMotivoReabrir(e.target.value)}
                            rows={3}
                            maxLength={500}
                            placeholder="Ej: El cajero olvidó declarar un pago Yape de S/ 150"
                            className="w-full rounded-lg border px-3 py-2 text-sm"
                            style={{
                                borderColor: motivoReabrir.length > 0 && !motivoValido
                                    ? 'var(--color-danger)'
                                    : 'var(--color-border)',
                                background: 'var(--color-surface)',
                                color: 'var(--color-text)',
                            }}
                        />
                        <div className="flex justify-between mt-1 text-xs">
                            <span style={{ color: motivoValido ? 'var(--color-text-muted)' : 'var(--color-danger)' }}>
                                {motivoValido
                                    ? 'Mínimo 10 caracteres ✓'
                                    : `Mínimo 10 caracteres (${motivoReabrir.trim().length}/10)`}
                            </span>
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                {motivoReabrir.length}/500
                            </span>
                        </div>
                    </div>
                </div>
            </Modal>

            <ModalEditarApertura
                isOpen={modalEditarApertura}
                onClose={() => setModalEditarApertura(false)}
                turnoId={turno.id}
                montoActual={turno.monto_apertura}
                fondosAdicionalesActuales={Number(turno.monto_fondos_adicionales ?? 0)}
                editable={configEfectivo?.apertura_editable ?? true}
            />
        </AppLayout>
    );
}

// ── Componentes auxiliares ──────────────────────────────────────────────────

const esMismoDia = (a: string, b: string) => new Date(a).toDateString() === new Date(b).toDateString();

function EstadoTurno({ cerrado }: { cerrado: boolean }) {
    const [texto, color, tinta] = cerrado
        ? ['Cerrado', 'var(--color-text-muted)', 'var(--color-text-muted)']
        : ['Abierto', 'var(--vp-mint)', 'var(--vp-mint-ink)'];
    return (
        <span className="inline-flex items-center gap-1.5 text-[13px] font-semibold px-2.5 py-1 rounded-full"
            style={{ color: tinta, backgroundColor: `color-mix(in srgb, ${color} 14%, transparent)` }}>
            <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: color }} /> {texto}
        </span>
    );
}

function Dato({ label, accion, children }: { label: string; accion?: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="px-5 py-4 min-w-0 border-b lg:border-b-0 lg:border-l lg:first:border-l-0 [&:nth-child(odd)]:border-r lg:[&:nth-child(odd)]:border-r-0"
            style={{ borderColor: 'var(--color-border)' }}>
            <div className="flex items-center justify-between gap-2 mb-1">
                <p className="text-[13px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
                {accion}
            </div>
            {children}
        </div>
    );
}

const Valor = ({ children }: { children: React.ReactNode }) => (
    <p className="font-display text-[24px] font-extrabold tabular-nums leading-tight" style={{ color: 'var(--color-text)' }}>{children}</p>
);
const Nota = ({ children }: { children: React.ReactNode }) => (
    <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{children}</p>
);

function ResultadoCaja({ diferencia }: { diferencia: number }) {
    const [texto, tinta] = Math.abs(diferencia) < 0.005
        ? ['Cuadró', 'var(--vp-mint-ink)']
        : diferencia > 0
            ? [`Sobró ${fmtS(diferencia)}`, 'var(--vp-amber-ink)']
            : [`Faltó ${fmtS(Math.abs(diferencia))}`, 'var(--vp-coral-ink)'];
    return <p className="font-display text-[24px] font-extrabold tabular-nums leading-tight" style={{ color: tinta }}>{texto}</p>;
}

/**
 * Productos vendidos con alto fijo y scroll propio: con 5 o con 200
 * productos el panel mide lo mismo y no deja espacio en blanco al lado.
 */
function ProductosVendidos({ productos, total, turnoId }: {
    productos: { nombre: string; cantidad: number; total: number }[]; total: number; turnoId: number;
}) {
    const [q, setQ] = useState('');
    const lista = useMemo(() => {
        const t = q.trim().toLowerCase();
        return t ? productos.filter(p => p.nombre.toLowerCase().includes(t)) : productos;
    }, [productos, q]);
    const max = Math.max(1, ...productos.map(p => p.total));

    return (
        <section className="xl:col-span-7 min-w-0 rounded-2xl overflow-hidden flex flex-col"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
            <header className="flex flex-wrap items-center gap-3 px-4 py-3.5"
                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 6%, var(--color-surface))', borderBottom: '1px solid var(--color-border)' }}>
                <span className="flex h-9 w-9 items-center justify-center rounded-lg text-white flex-shrink-0" style={{ backgroundColor: 'var(--vp-navy)' }}>
                    <Package size={18} />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="font-display text-[17px] font-bold leading-tight" style={{ color: 'var(--vp-navy)' }}>Productos vendidos</h2>
                    <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                        {plural(productos.length, 'producto', 'productos')}, de lo que más vendió a lo que menos
                    </p>
                </div>
                {productos.length > 6 && (
                    <div className="relative w-full sm:w-56">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                        <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="Buscar producto" aria-label="Buscar producto vendido"
                            className="w-full text-sm rounded-xl pl-9 pr-8 py-2 border outline-none focus:ring-2"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 35%, transparent)' } as React.CSSProperties} />
                        {q && (
                            <button onClick={() => setQ('')} aria-label="Quitar búsqueda" className="absolute right-2.5 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }}>
                                <X size={14} />
                            </button>
                        )}
                    </div>
                )}
            </header>

            {productos.length === 0 ? (
                <Empty text="No hubo ventas en este turno." />
            ) : lista.length === 0 ? (
                <Empty text="Ningún producto coincide con la búsqueda." />
            ) : (
                <ul className="overflow-y-auto max-h-[520px]" style={{ overscrollBehavior: 'contain' }}>
                    {lista.map(p => (
                        <li key={p.nombre} className="grid grid-cols-[minmax(0,1fr)_4.5rem_6.5rem] items-center gap-3 px-4 py-2"
                            style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
                            <div className="min-w-0">
                                <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }} title={p.nombre}>{p.nombre}</p>
                                <div className="h-1 mt-1 rounded-full overflow-hidden" style={{ backgroundColor: 'color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
                                    <div className="h-full rounded-full" style={{ width: `${Math.max(2, (p.total / max) * 100)}%`, backgroundColor: 'color-mix(in srgb, var(--vp-sky) 60%, var(--color-surface))' }} />
                                </div>
                            </div>
                            <span className="text-sm text-right tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{fmtCant(p.cantidad)} und.</span>
                            <span className="text-sm text-right font-bold tabular-nums" style={{ color: 'var(--color-text)' }}>{fmtS(p.total)}</span>
                        </li>
                    ))}
                </ul>
            )}

            <footer className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                style={{ borderTop: '1px solid var(--color-border)', backgroundColor: 'color-mix(in srgb, var(--vp-navy) 3%, var(--color-surface))' }}>
                <Link href={route('ventas.index', { turno_id: turnoId })} className="text-sm font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                    Ver las ventas de este turno
                </Link>
                <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                    Total <strong className="font-display text-base tabular-nums" style={{ color: 'var(--vp-navy)' }}>{fmtS(total)}</strong>
                </span>
            </footer>
        </section>
    );
}

/** Cierre: lo contado, lo esperado, el resultado y qué pasó con el efectivo. */
function CierreDelTurno({ turno, totalEfectivoArqueo, entregadoAlCierre }: { turno: Turno; totalEfectivoArqueo: number; entregadoAlCierre: number }) {
    const diferencia = num(turno.diferencia);
    const contado = turno.monto_cierre_declarado != null;
    const denominaciones = (turno.arqueo ?? []).filter(r => r.cantidad > 0);
    const metodos = turno.arqueo_metodos ?? [];

    return (
        <Panel icon={<Scale size={17} />} titulo="Cierre del turno" color="var(--vp-navy)"
            detalle={turno.user_cierre ? `lo cerró ${turno.user_cierre.name}` : undefined}>
            <dl className="space-y-1.5 text-sm">
                <Fila label="Efectivo contado" valor={contado ? fmtS(num(turno.monto_cierre_declarado)) : totalEfectivoArqueo > 0 ? fmtS(totalEfectivoArqueo) : 'No se contó'} />
                <Fila label="Efectivo esperado" valor={fmtS(num(turno.monto_cierre_esperado))} />
                <div className="flex items-baseline justify-between pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                    <dt className="font-bold" style={{ color: 'var(--color-text)' }}>Resultado</dt>
                    <dd className="font-bold tabular-nums" style={{
                        color: !contado ? 'var(--color-text-muted)' : Math.abs(diferencia) < 0.005 ? 'var(--vp-mint-ink)' : diferencia > 0 ? 'var(--vp-amber-ink)' : 'var(--vp-coral-ink)',
                    }}>
                        {!contado ? 'Sin conteo' : Math.abs(diferencia) < 0.005 ? 'Cuadró' : diferencia > 0 ? `Sobró ${fmtS(diferencia)}` : `Faltó ${fmtS(Math.abs(diferencia))}`}
                    </dd>
                </div>
            </dl>

            {turno.destino_efectivo && (
                <dl className="mt-3 rounded-xl px-3 py-2.5 space-y-1 text-sm" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-sky) 7%, var(--color-surface))' }}>
                    <Fila label="Quedó en caja para el siguiente turno" valor={fmtS(num(turno.efectivo_arrastre))} />
                    {entregadoAlCierre > 0 && <Fila label="Entregado a administración" valor={fmtS(entregadoAlCierre)} />}
                </dl>
            )}

            {denominaciones.length > 0 && (
                <details className="mt-3 group">
                    <summary className="cursor-pointer text-[13px] font-semibold list-none" style={{ color: 'var(--color-primary)' }}>
                        Ver billetes y monedas contados ({denominaciones.length})
                    </summary>
                    <ul className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-[13px]">
                        {denominaciones.map(r => (
                            <li key={r.id} className="flex justify-between tabular-nums">
                                <span style={{ color: 'var(--color-text-muted)' }}>{Number(r.denominacion) >= 1 ? `S/ ${Number(r.denominacion)}` : `${Math.round(Number(r.denominacion) * 100)} cént.`} × {r.cantidad}</span>
                                <span className="font-semibold" style={{ color: 'var(--color-text)' }}>{fmtS(r.denominacion * r.cantidad)}</span>
                            </li>
                        ))}
                    </ul>
                </details>
            )}

            {metodos.length > 0 && (
                <div className="mt-3">
                    <p className="text-[13px] font-semibold mb-1" style={{ color: 'var(--color-text)' }}>Otros medios declarados</p>
                    <dl className="space-y-1 text-sm">
                        {metodos.map(m => <Fila key={m.id} label={m.metodo_pago?.nombre ?? '—'} valor={fmtS(num(m.monto_declarado))} />)}
                    </dl>
                </div>
            )}

            {turno.observacion_cierre && (
                <p className="mt-3 rounded-xl px-3 py-2.5 text-sm" style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }}>
                    <span className="font-semibold" style={{ color: 'var(--color-text)' }}>Nota: </span>{turno.observacion_cierre as string}
                </p>
            )}
        </Panel>
    );
}

function Fila({ label, valor }: { label: string; valor: string }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt style={{ color: 'var(--color-text-muted)' }}>{label}</dt>
            <dd className="font-semibold tabular-nums" style={{ color: 'var(--color-text)' }}>{valor}</dd>
        </div>
    );
}
