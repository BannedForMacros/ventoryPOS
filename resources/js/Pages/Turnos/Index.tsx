import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import {
    AlertTriangle, Clock, HandCoins, Pencil, Plus, Printer, Receipt, Search, TrendingDown, Lock, Eye,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import { Panel, Empty, fmtS, fmtInt, plural } from '@/Components/Reportes/ReportUI';
import ModalAbrirTurno from './Partials/ModalAbrirTurno';
import ModalEditarApertura from './Partials/ModalEditarApertura';
import ModalRetiro from './Partials/ModalRetiro';
import { imprimirCierreTurno, type ShiftClosurePayload } from '@/lib/ticketPrinter';
import type { Caja, Gasto, MetodoPago, PageProps, Turno, TurnoRetiro, Venta } from '@/types';

interface CajaDisponible extends Caja {
    tiene_turno_abierto: boolean;
}

interface Paginado<T> {
    data:          T[];
    current_page:  number;
    last_page:     number;
    total:         number;
}

interface ConfigFondosLocal {
    usa_fondos_iniciales: boolean;
    fondos_iniciales_en_declaracion: boolean;
}

interface ConfigEfectivo {
    modo_apertura_caja:         'libre' | 'arrastre' | 'fondo_fijo';
    apertura_editable:          boolean;
    usa_retiros_caja:           boolean;
    retiro_requiere_aprobacion: boolean;
}

/** Lo que entró por un medio de pago en el turno (ventas sin vuelto + abonos + anticipos − reembolsos). */
interface Cobro {
    metodo_pago_id: number;
    nombre:         string;
    es_efectivo:    boolean;
    total:          number;
}

/** Resumen de un turno abierto (uno por caja). */
interface TurnoAbiertoResumen {
    id:                number;
    caja:              string | null;
    local:             string | null;
    usuario:           string | null;
    es_mio:            boolean;
    fecha_apertura:    string;
    monto_apertura:    number;
    ventas_count:      number;
    ventas_total:      number;
    gastos_total:      number;
    retiros_total:     number;
    efectivo_esperado: number;
    cobros:            Cobro[];
}

interface Props extends PageProps {
    turnos:           Paginado<Turno>;
    buscar?:          string;
    cajasDisponibles: CajaDisponible[];
    metodosPago:      MetodoPago[];
    /** El turno abierto del usuario (con ventas, gastos y retiros), si tiene. */
    turnoActivo:      Turno | null;
    /** Todos los turnos abiertos que el usuario puede ver. */
    turnosAbiertos:   TurnoAbiertoResumen[];
    configFondos:     Record<number, ConfigFondosLocal>;
    configEfectivo:   ConfigEfectivo;
}

const num = (v: string | number | null | undefined) => parseFloat(String(v ?? 0)) || 0;
const fechaHora = (iso: string) =>
    new Date(iso).toLocaleString('es-PE', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const hora = (iso: string) => new Date(iso).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });

/** "hace 2 días y 5 h", "hace 3 h", "hace 12 min" */
function haceCuanto(iso: string): string {
    const min = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
    if (min < 60) return `hace ${min} min`;
    const h = Math.floor(min / 60);
    if (h < 24) return `hace ${h} h`;
    const d = Math.floor(h / 24);
    const resto = h % 24;
    return `hace ${plural(d, 'día', 'días')}${resto ? ` y ${resto} h` : ''}`;
}

const esOtroDia = (iso: string) => new Date(iso).toDateString() !== new Date().toDateString();

export default function TurnosIndex({ turnos, buscar, cajasDisponibles, turnoActivo, turnosAbiertos, configFondos, configEfectivo }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalAbrir, setModalAbrir] = useState(false);
    const [modalEditarApertura, setModalEditarApertura] = useState(false);
    const [modalRetiro, setModalRetiro] = useState(false);
    const [imprimiendo, setImprimiendo] = useState<number | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    async function imprimir(t: Turno) {
        setImprimiendo(t.id);
        const tid = toast.loading(`Obteniendo cierre del turno #${t.id}...`);
        try {
            const { data } = await axios.get<ShiftClosurePayload>(route('turnos.cierre-ticket', t.id));
            if (!data?.token) {
                toast.error('Esta caja no tiene ticketera configurada.', { id: tid });
                return;
            }
            const ok = await imprimirCierreTurno(data);
            if (ok) toast.success(`Cierre del turno #${t.id} enviado a la impresora`, { id: tid });
            else    toast.error('No se pudo imprimir. Revisa VentoryPrint en esta PC.', { id: tid });
        } catch {
            toast.error('No se pudo obtener el reporte de cierre.', { id: tid });
        } finally {
            setImprimiendo(null);
        }
    }

    const columnasTurnos: Column<Turno>[] = [
        {
            key: 'caja', label: 'Turno',
            render: (t) => (
                <div className="min-w-0">
                    <p className="font-semibold" style={{ color: 'var(--color-text)' }}>{t.caja?.nombre ?? '—'}</p>
                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>#{t.id}</p>
                </div>
            ),
        },
        {
            key: 'fecha_apertura', label: 'Horario', sortable: true,
            render: (t) => (
                <div className="text-sm whitespace-nowrap">
                    <p style={{ color: 'var(--color-text)' }}>{fechaHora(t.fecha_apertura)}</p>
                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                        {t.fecha_cierre ? `hasta ${fechaHora(t.fecha_cierre)}` : 'sigue abierto'}
                    </p>
                </div>
            ),
        },
        {
            key: 'user', label: 'Quién',
            render: (t) => (
                <div className="text-sm">
                    <p style={{ color: 'var(--color-text)' }}>Abrió {t.user?.name ?? '—'}</p>
                    {t.user_cierre && <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Cerró {t.user_cierre.name}</p>}
                </div>
            ),
        },
        {
            key: 'monto_apertura', label: 'Abrió con',
            render: (t) => (
                <div className="tabular-nums">
                    <p style={{ color: 'var(--color-text)' }}>{fmtS(num(t.monto_apertura))}</p>
                    {num(t.monto_fondos_adicionales) > 0.009 && (
                        <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>+{fmtS(num(t.monto_fondos_adicionales))} adicionales</p>
                    )}
                </div>
            ),
        },
        {
            key: 'diferencia', label: 'Resultado de caja',
            render: (t) => <ResultadoCaja turno={t} />,
        },
        {
            key: 'acciones', label: '',
            render: (t) => (
                <div className="flex items-center justify-end gap-2">
                    {t.estado === 'cerrado' && (
                        <button onClick={() => imprimir(t)} disabled={imprimiendo === t.id}
                            title="Reimprimir el cierre" aria-label={`Reimprimir el cierre del turno ${t.id}`}
                            className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:opacity-80 disabled:opacity-40"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)', color: 'var(--color-primary)' }}>
                            <Printer size={15} />
                        </button>
                    )}
                    <button onClick={() => router.visit(route('turnos.show', t.id))}
                        className="text-[13px] px-3 py-1.5 rounded-lg font-semibold transition-colors hover:bg-black/[0.03]"
                        style={{ color: 'var(--color-primary)', border: '1px solid color-mix(in srgb, var(--color-primary) 40%, transparent)' }}>
                        Ver detalle
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Turnos">
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-5">
                <div className="min-w-0">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>Turnos</h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        Abre tu caja al empezar, ciérrala al terminar y revisa cómo cuadraron los turnos anteriores.
                    </p>
                </div>
                {!turnoActivo && (
                    <Button onClick={() => setModalAbrir(true)}>
                        <Plus size={16} className="mr-1 flex-shrink-0" />Abrir turno
                    </Button>
                )}
            </div>

            {/* ── Turnos abiertos: una tarjeta por caja ─────────────────────── */}
            {turnosAbiertos.length > 0 ? (
                <section className="mb-6">
                    <div className="flex items-baseline justify-between gap-3 mb-3">
                        <h2 className="font-display text-lg font-bold" style={{ color: 'var(--vp-navy)' }}>
                            {turnosAbiertos.length === 1 ? 'Turno abierto' : `${turnosAbiertos.length} turnos abiertos`}
                        </h2>
                        {!turnoActivo && (
                            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Tú no tienes un turno abierto</p>
                        )}
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-3">
                        {turnosAbiertos.map(t => (
                            <TarjetaTurno key={t.id} turno={t}
                                usaRetiros={configEfectivo.usa_retiros_caja}
                                onCerrar={() => router.visit(route('turnos.cerrar.page', t.id))}
                                onVer={() => router.visit(route('turnos.show', t.id))}
                                onRetiro={() => setModalRetiro(true)}
                                onEditarApertura={() => setModalEditarApertura(true)} />
                        ))}
                    </div>
                </section>
            ) : (
                <div className="rounded-2xl flex flex-col sm:flex-row items-center gap-4 px-6 py-6 mb-6"
                    style={{ border: '2px dashed color-mix(in srgb, var(--vp-navy) 20%, var(--color-border))', backgroundColor: 'var(--color-surface)' }}>
                    <span className="flex h-12 w-12 items-center justify-center rounded-xl flex-shrink-0"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 9%, var(--color-surface))', color: 'var(--vp-navy)' }}>
                        <Lock size={22} />
                    </span>
                    <div className="flex-1 text-center sm:text-left">
                        <p className="font-display text-lg font-bold" style={{ color: 'var(--vp-navy)' }}>No hay turnos abiertos</p>
                        <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                            Abre un turno con el efectivo que tienes en el cajón para empezar a vender.
                        </p>
                    </div>
                    <Button onClick={() => setModalAbrir(true)}>
                        <Plus size={16} className="mr-1" />Abrir turno
                    </Button>
                </div>
            )}

            {/* ── Detalle de mi turno ──────────────────────────────────────── */}
            {turnoActivo && <DetalleMiTurno turno={turnoActivo} usaRetiros={configEfectivo.usa_retiros_caja} />}

            {/* ── Historial ─────────────────────────────────────────────────── */}
            <section className="mt-2">
                <div className="flex items-baseline justify-between gap-3 mb-3">
                    <h2 className="font-display text-lg font-bold" style={{ color: 'var(--vp-navy)' }}>Turnos anteriores</h2>
                    <p className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{plural(turnos.total, 'turno', 'turnos')}</p>
                </div>
                <Table
                    data={turnos}
                    columns={columnasTurnos}
                    emptyMessage="Todavía no hay turnos cerrados"
                    searchPlaceholder="Buscar por caja, persona o local"
                    initialSearch={buscar}
                    onServerSearch={(t) => router.get(route('turnos.index'),
                        { buscar: t || undefined },
                        { preserveState: true, preserveScroll: true, replace: true })}
                />
            </section>

            <ModalAbrirTurno
                isOpen={modalAbrir}
                onClose={() => setModalAbrir(false)}
                cajasDisponibles={cajasDisponibles}
                configFondos={configFondos}
                configEfectivo={configEfectivo}
            />
            {turnoActivo && (
                <ModalEditarApertura
                    isOpen={modalEditarApertura}
                    onClose={() => setModalEditarApertura(false)}
                    turnoId={turnoActivo.id}
                    montoActual={turnoActivo.monto_apertura}
                    fondosAdicionalesActuales={turnoActivo.monto_fondos_adicionales}
                    editable={configEfectivo.apertura_editable}
                />
            )}
            {turnoActivo && (
                <ModalRetiro
                    isOpen={modalRetiro}
                    onClose={() => setModalRetiro(false)}
                    turnoId={turnoActivo.id}
                    requiereAprobacion={configEfectivo.retiro_requiere_aprobacion}
                />
            )}
        </AppLayout>
    );
}

/* ── Tarjeta de un turno abierto ───────────────────────────────────────── */
function TarjetaTurno({ turno, usaRetiros, onCerrar, onVer, onRetiro, onEditarApertura }: {
    turno: TurnoAbiertoResumen; usaRetiros: boolean;
    onCerrar: () => void; onVer: () => void; onRetiro: () => void; onEditarApertura: () => void;
}) {
    const viejo = esOtroDia(turno.fecha_apertura);
    const noEfectivo = turno.cobros.filter(c => !c.es_efectivo);
    const efectivoCobrado = turno.cobros.find(c => c.es_efectivo)?.total ?? 0;

    return (
        <article className="rounded-2xl overflow-hidden flex flex-col"
            style={{
                backgroundColor: 'var(--color-surface)',
                border: turno.es_mio ? '2px solid var(--vp-navy)' : '1px solid var(--color-border)',
                boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.14)',
            }}>
            {/* Cabecera: caja, quién y desde cuándo */}
            <header className="px-4 pt-4 pb-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="font-display text-lg font-bold leading-tight truncate" style={{ color: 'var(--vp-navy)' }}>
                            {turno.caja ?? 'Caja'}
                        </h3>
                        <p className="text-[13px] mt-0.5 truncate" style={{ color: 'var(--color-text-muted)' }}>
                            {turno.usuario ?? '—'}{turno.local ? `, ${turno.local}` : ''}
                        </p>
                    </div>
                    {turno.es_mio ? (
                        <span className="text-xs font-bold px-2.5 py-1 rounded-full text-white flex-shrink-0" style={{ backgroundColor: 'var(--vp-navy)' }}>Tu turno</span>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0"
                            style={{ color: 'var(--vp-mint-ink)', backgroundColor: 'color-mix(in srgb, var(--vp-mint) 13%, transparent)' }}>
                            <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: 'var(--vp-mint)' }} /> Abierto
                        </span>
                    )}
                </div>
                <p className="text-[13px] mt-2 flex items-center gap-1.5" style={{ color: viejo ? 'var(--vp-amber-ink)' : 'var(--color-text-muted)' }}>
                    {viejo ? <AlertTriangle size={14} className="flex-shrink-0" /> : <Clock size={14} className="flex-shrink-0" />}
                    Abierto el {fechaHora(turno.fecha_apertura)}, {haceCuanto(turno.fecha_apertura)}
                </p>
            </header>

            {/* Lo que debería haber, por medio de pago */}
            <div className="px-4">
                <div className="rounded-xl px-3.5 py-3" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 10%, var(--color-surface))' }}>
                    <p className="text-[13px] font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>Efectivo que debería haber en el cajón</p>
                    <p className="font-display text-[26px] font-extrabold tabular-nums leading-tight" style={{ color: 'var(--color-text)' }}>
                        {fmtS(turno.efectivo_esperado)}
                    </p>
                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                        Abrió con {fmtS(turno.monto_apertura)} y cobró {fmtS(efectivoCobrado)} en efectivo
                    </p>
                </div>

                <p className="text-[13px] font-semibold mt-3 mb-1" style={{ color: 'var(--color-text)' }}>Otros medios de pago</p>
                {noEfectivo.length === 0 ? (
                    <p className="text-[13px] pb-1" style={{ color: 'var(--color-text-muted)' }}>Sin cobros por Yape, tarjeta ni transferencia todavía.</p>
                ) : (
                    <ul>
                        {noEfectivo.map(c => (
                            <li key={c.metodo_pago_id} className="flex items-baseline justify-between gap-3 py-1.5"
                                style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 60%, transparent)' }}>
                                <span className="text-sm truncate" style={{ color: 'var(--color-text)' }}>{c.nombre}</span>
                                <span className="text-sm font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fmtS(c.total)}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {/* Números del turno */}
            <dl className="grid grid-cols-3 mt-3 mx-4 py-2.5" style={{ borderTop: '1px solid var(--color-border)' }}>
                <Mini label="Vendió" valor={fmtS(turno.ventas_total)} nota={plural(turno.ventas_count, 'venta', 'ventas')} />
                <Mini label="Gastos" valor={fmtS(turno.gastos_total)} />
                <Mini label="Retiros" valor={fmtS(turno.retiros_total)} />
            </dl>

            {/* Acciones */}
            <footer className="mt-auto flex flex-wrap items-center gap-2 px-4 pb-4 pt-1">
                <button onClick={onCerrar}
                    className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white transition-colors hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                    style={{ backgroundColor: 'var(--vp-navy)', outlineColor: 'var(--vp-navy)' }}>
                    <Lock size={15} /> Cerrar turno
                </button>
                {turno.es_mio && usaRetiros && (
                    <IconoAccion titulo="Retirar efectivo" onClick={onRetiro}><HandCoins size={16} /></IconoAccion>
                )}
                {turno.es_mio && (
                    <IconoAccion titulo="Corregir el monto de apertura" onClick={onEditarApertura}><Pencil size={15} /></IconoAccion>
                )}
                <IconoAccion titulo="Ver el detalle del turno" onClick={onVer}><Eye size={16} /></IconoAccion>
            </footer>
        </article>
    );
}

function Mini({ label, valor, nota }: { label: string; valor: string; nota?: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{label}</dt>
            <dd className="text-sm font-bold tabular-nums truncate" style={{ color: 'var(--color-text)' }}>{valor}</dd>
            {nota && <dd className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{nota}</dd>}
        </div>
    );
}

function IconoAccion({ titulo, onClick, children }: { titulo: string; onClick: () => void; children: React.ReactNode }) {
    return (
        <button onClick={onClick} title={titulo} aria-label={titulo}
            className="inline-flex items-center justify-center h-10 w-10 rounded-xl transition-colors hover:bg-black/[0.04] focus-visible:outline focus-visible:outline-2"
            style={{ border: '1px solid var(--color-border)', color: 'var(--vp-navy)', outlineColor: 'var(--color-primary)' }}>
            {children}
        </button>
    );
}

/* ── Detalle de mi turno: ventas, gastos y retiros ─────────────────────── */
function DetalleMiTurno({ turno, usaRetiros }: { turno: Turno; usaRetiros: boolean }) {
    const ventas  = (turno.ventas ?? []) as Venta[];
    const gastos  = (turno.gastos ?? []) as Gasto[];
    const retiros = (turno.retiros ?? []) as TurnoRetiro[];
    const totalGastos  = gastos.reduce((s, g) => s + num(g.monto), 0);
    const totalRetiros = retiros.reduce((s, r) => s + num(r.monto), 0);

    return (
        <section className="mb-6">
            <h2 className="font-display text-lg font-bold mb-3" style={{ color: 'var(--vp-navy)' }}>Movimientos de tu turno</h2>
            <div className="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">
                <VentasTurno ventas={ventas} />
                <div className="xl:col-span-5 space-y-3 min-w-0">
                    <Panel icon={<TrendingDown size={17} />} titulo="Gastos" color="var(--vp-coral)" tinta="var(--vp-coral-ink)"
                        detalle={gastos.length ? fmtS(totalGastos) : undefined} sinPadding>
                        {gastos.length === 0 ? <Empty text="Sin gastos en este turno." /> : (
                            <ul className="max-h-72 overflow-y-auto">
                                {gastos.map(g => (
                                    <li key={g.id} className="flex items-start justify-between gap-3 px-4 py-2.5"
                                        style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>
                                                {g.concepto?.nombre ?? g.tipo?.nombre ?? 'Gasto'}
                                            </p>
                                            <p className="text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                {[g.tipo?.nombre, g.comentario as string | undefined].filter(Boolean).join(', ') || new Date(g.fecha).toLocaleDateString('es-PE')}
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
                    {usaRetiros && (
                        <Panel icon={<HandCoins size={17} />} titulo="Retiros de efectivo" color="var(--vp-amber)" tinta="var(--vp-amber-ink)"
                            detalle={retiros.length ? fmtS(totalRetiros) : undefined} sinPadding>
                            {retiros.length === 0 ? (
                                <p className="px-4 pb-4 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                    Cuando entregues dinero a administración durante el turno, regístralo con el botón de retiro de tu tarjeta.
                                </p>
                            ) : (
                                <ul className="max-h-72 overflow-y-auto">
                                    {retiros.map(r => (
                                        <li key={r.id} className="flex items-start justify-between gap-3 px-4 py-2.5"
                                            style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                            <div className="min-w-0">
                                                <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>{r.concepto}</p>
                                                <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                                    {fechaHora(r.created_at as string)}, {r.user?.name ?? '—'}
                                                </p>
                                            </div>
                                            <div className="text-right flex-shrink-0">
                                                <p className="text-[15px] font-bold tabular-nums" style={{ color: 'var(--vp-amber-ink)' }}>−{fmtS(num(r.monto))}</p>
                                                {r.estado === 'registrado' && (
                                                    <span className="text-xs font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>falta aprobar</span>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    )}
                </div>
            </div>
        </section>
    );
}

/* ── Ventas del turno: lista con buscador y scroll propio ──────────────── */
function VentasTurno({ ventas }: { ventas: Venta[] }) {
    const [q, setQ] = useState('');
    const lista = useMemo(() => {
        const t = q.trim().toLowerCase();
        const orden = [...ventas].sort((a, b) => new Date(b.fecha_venta).getTime() - new Date(a.fecha_venta).getTime());
        return t ? orden.filter(v => String(v.numero).toLowerCase().includes(t)
            || (v.pagos ?? []).some((p: any) => (p.metodo_pago?.nombre ?? '').toLowerCase().includes(t))) : orden;
    }, [ventas, q]);

    return (
        <section className="xl:col-span-7 min-w-0 rounded-2xl overflow-hidden"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
            <header className="flex flex-wrap items-center gap-3 px-4 py-3.5"
                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 6%, var(--color-surface))', borderBottom: '1px solid var(--color-border)' }}>
                <span className="flex h-9 w-9 items-center justify-center rounded-lg text-white flex-shrink-0" style={{ backgroundColor: 'var(--vp-navy)' }}>
                    <Receipt size={18} />
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="font-display text-[17px] font-bold leading-tight" style={{ color: 'var(--vp-navy)' }}>Ventas</h3>
                    <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{plural(ventas.length, 'venta completada', 'ventas completadas')}, la más reciente primero</p>
                </div>
                {ventas.length > 0 && (
                    <div className="relative w-full sm:w-60">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                        <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="Buscar número o método" aria-label="Buscar venta del turno"
                            className="w-full text-sm rounded-xl pl-9 pr-3 py-2 border outline-none focus:ring-2"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 35%, transparent)' } as React.CSSProperties} />
                    </div>
                )}
            </header>
            {ventas.length === 0 ? (
                <div className="text-center py-10 px-4">
                    <Clock size={28} className="mx-auto mb-2.5" style={{ color: 'color-mix(in srgb, var(--vp-navy) 30%, transparent)' }} />
                    <p className="text-[15px] font-semibold" style={{ color: 'var(--color-text)' }}>Todavía no hay ventas en tu turno</p>
                    <p className="text-sm mt-1" style={{ color: 'var(--color-text-muted)' }}>Las ventas que hagas en el POS aparecerán aquí.</p>
                </div>
            ) : lista.length === 0 ? (
                <Empty text="Ninguna venta coincide con la búsqueda." />
            ) : (
                <ul className="max-h-[440px] overflow-y-auto">
                    {lista.map(v => (
                        <li key={v.id}>
                            <button onClick={() => router.visit(route('ventas.show', v.id))}
                                className="w-full text-left grid grid-cols-[4.5rem_minmax(0,1fr)_auto] items-center gap-3 px-4 py-2.5 transition-colors hover:bg-[color-mix(in_srgb,var(--color-primary)_4%,transparent)]"
                                style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                <span className="text-sm tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{hora(v.fecha_venta)}</span>
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold tabular-nums" style={{ color: 'var(--color-primary)' }}>{v.numero}</span>
                                    <span className="block text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                                        {(v.pagos ?? []).map((p: any) => p.metodo_pago?.nombre ?? '—').join(', ') || 'Sin pago'}
                                    </span>
                                </span>
                                <span className="font-display text-[15px] font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                    {fmtS(num(v.total))}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {ventas.length > 0 && (
                <p className="px-4 py-2.5 text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)', borderTop: '1px solid var(--color-border)' }}>
                    {q ? `${fmtInt(lista.length)} de ${fmtInt(ventas.length)}` : plural(ventas.length, 'venta', 'ventas')}
                </p>
            )}
        </section>
    );
}

/* ── Resultado de un cierre, en palabras ───────────────────────────────── */
function ResultadoCaja({ turno }: { turno: Turno }) {
    if (turno.estado === 'abierto') {
        return (
            <span className="inline-flex items-center gap-1.5 text-[13px] font-semibold px-2.5 py-1 rounded-full"
                style={{ color: 'var(--vp-navy)', backgroundColor: 'color-mix(in srgb, var(--vp-sky) 14%, transparent)' }}>
                <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: 'var(--vp-sky)' }} /> Abierto
            </span>
        );
    }
    if (turno.diferencia === null) return <span style={{ color: 'var(--color-text-muted)' }}>Sin arqueo</span>;
    const d = num(turno.diferencia);
    const [texto, color, tinta] = Math.abs(d) < 0.005
        ? ['Cuadró', 'var(--vp-mint)', 'var(--vp-mint-ink)']
        : d > 0
            ? [`Sobró ${fmtS(d)}`, 'var(--vp-amber)', 'var(--vp-amber-ink)']
            : [`Faltó ${fmtS(Math.abs(d))}`, 'var(--vp-coral)', 'var(--vp-coral-ink)'];
    return (
        <span className="inline-flex items-center gap-1.5 text-[13px] font-semibold px-2.5 py-1 rounded-full whitespace-nowrap tabular-nums"
            style={{ color: tinta, backgroundColor: `color-mix(in srgb, ${color} 14%, transparent)` }}>
            <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: color }} /> {texto}
        </span>
    );
}
