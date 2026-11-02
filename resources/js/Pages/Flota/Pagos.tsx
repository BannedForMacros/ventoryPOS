import { useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Ban, ChevronDown, HandCoins, Plus, Settings2, Trash2, Truck, User } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import { Kpi } from '@/Components/Reportes/ReportUI';
import { Pastilla, fmtCantidad, fmtDia } from '@/Components/Flota/FlotaUI';
import { soles } from '@/lib/dinero';
import { hoyLocal } from '@/lib/fechas';
import { avisoError } from '@/lib/avisoError';
import type { GastoTipo, Local, MetodoPagoConCuentas, PageProps, Turno } from '@/types';

interface DetalleViaje {
    viaje_id: number; numero: string; fecha: string; tipo: 'reparto' | 'abastecimiento' | 'flete'; rol: 'chofer' | 'ayudante';
    placa: string | null; unidades: number; monto: number;
    productos: { producto: string; unidades: number; monto: number; sin_tarifa: boolean }[];
}
interface PorPagar { conductor_id: number; nombre: string; tipo: string; viajes: number; vueltas: number; unidades: number; monto: number; detalle: DetalleViaje[] }
interface Liquidacion {
    id: number; numero: string; persona: string | null; desde: string; hasta: string; viajes: number; vueltas: number; unidades: number;
    monto_calculado: number; ajuste: number; ajuste_motivo: string | null; total: number; estado: 'pagada' | 'anulada';
    fecha_pago: string; detalle: DetalleViaje[]; motivo_anulacion: string | null;
}
interface Tarifa { producto_id?: number; producto?: string | null; por_cantidad: number; monto_chofer: number; monto_ayudante: number }

interface Props extends PageProps {
    periodo: { clave: 'semana_pasada' | 'semana' | 'rango'; desde: string; hasta: string };
    porPagar: PorPagar[];
    liquidaciones: Liquidacion[];
    tarifas: { general: Tarifa | null; productos: Tarifa[]; vuelta_chofer: number; vuelta_ayudante: number };
    productos: { value: number; label: string }[];
    gasto: { tipos: GastoTipo[]; locales: Local[]; turnosAbiertos: Turno[]; esAdmin: boolean; metodosPago: MetodoPagoConCuentas[] };
    puede: { editar: boolean; crear: boolean };
}

const fecha = (ymd: string) => {
    const [y, m, d] = ymd.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-PE', { day: 'numeric', month: 'short' });
};

export default function FlotaPagos({ periodo, porPagar, liquidaciones, tarifas, productos, gasto, puede }: Props) {
    const { flash } = usePage<Props>().props;
    const [pagando, setPagando] = useState<PorPagar | null>(null);
    const [abierto, setAbierto] = useState<number | null>(null);
    const [config, setConfig] = useState(false);
    const [anulando, setAnulando] = useState<Liquidacion | null>(null);
    const [desde, setDesde] = useState(periodo.desde);
    const [hasta, setHasta] = useState(periodo.hasta);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error) toast.error(flash.error as string);
    }, [flash]);

    const ir = (p: Record<string, string>) => router.get(route('flota.pagos.index'), p, { preserveScroll: true });
    const totalPorPagar = porPagar.reduce((s, p) => s + p.monto, 0);
    const pagado = liquidaciones.filter(l => l.estado === 'pagada').reduce((s, l) => s + l.total, 0);
    const sinTarifa = !tarifas.general && tarifas.productos.length === 0;

    return (
        <AppLayout title="Pago a choferes">
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-5">
                <div className="min-w-0 max-w-2xl">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>Pago a choferes</h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        Lo que toca a cada chofer y ayudante por lo que entregó y por sus vueltas. Al pagar se registra como gasto de cada camión.
                    </p>
                </div>
                {puede.editar && <Button variant="ghost" onClick={() => setConfig(true)} startContent={<Settings2 size={15} />}>Tarifas</Button>}
            </div>

            {sinTarifa && (
                <Callout variant="warning" className="mb-4" title="Primero define las tarifas"
                    accion={puede.editar ? { label: 'Definir tarifas', onClick: () => setConfig(true) } : undefined}>
                    Por ejemplo: S/ 12.50 por millar repartido al chofer y a cada ayudante, S/ 70 el millar de techo y S/ 20 por vuelta de fábrica.
                </Callout>
            )}

            <section className="rounded-2xl p-3 mb-4 flex flex-wrap items-center gap-3" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                <div className="flex flex-wrap rounded-xl p-0.5" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 5%, transparent)' }}>
                    {([['semana_pasada', 'Semana pasada'], ['semana', 'Esta semana']] as const).map(([v, t]) => {
                        const activo = periodo.clave === v;
                        return (
                            <button key={v} onClick={() => ir({ periodo: v })} aria-pressed={activo} className="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                style={{ backgroundColor: activo ? 'var(--color-surface)' : 'transparent', color: activo ? 'var(--vp-navy)' : 'var(--color-text-muted)', boxShadow: activo ? '0 1px 3px rgb(0 0 0 / 0.1)' : undefined }}>
                                {t}
                            </button>
                        );
                    })}
                </div>
                <div className="flex items-center gap-2">
                    <input type="date" value={desde} onChange={e => setDesde(e.target.value)} aria-label="Desde" className="rounded-xl border px-3 py-2 text-sm"
                        style={{ borderColor: periodo.clave === 'rango' ? 'var(--vp-navy)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                    <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>al</span>
                    <input type="date" value={hasta} onChange={e => setHasta(e.target.value)} aria-label="Hasta" className="rounded-xl border px-3 py-2 text-sm"
                        style={{ borderColor: periodo.clave === 'rango' ? 'var(--vp-navy)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                    <Button size="sm" variant="ghost" onClick={() => ir({ periodo: 'rango', desde, hasta })}>Ver</Button>
                </div>
                <span className="text-[13px] ml-auto" style={{ color: 'var(--color-text-muted)' }}>{fecha(periodo.desde)} al {fecha(periodo.hasta)}</span>
            </section>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <Kpi icon={<HandCoins size={18} />} label="Por pagar" value={soles(totalPorPagar)} sub={`${porPagar.length} ${porPagar.length === 1 ? 'persona' : 'personas'}`} color="var(--vp-amber)" />
                <Kpi icon={<HandCoins size={18} />} label="Ya pagado en el período" value={soles(pagado)} sub={`${liquidaciones.filter(l => l.estado === 'pagada').length} liquidaciones`} color="var(--vp-mint)" />
                <Kpi icon={<Truck size={18} />} label="Vuelta de fábrica" value={soles(tarifas.vuelta_chofer)} sub="al chofer, por vuelta" color="var(--vp-navy)" />
            </div>

            {/* ── Por pagar ── */}
            <h2 className="text-base font-bold mb-2" style={{ color: 'var(--color-text)' }}>Por pagar</h2>
            {porPagar.length === 0 ? (
                <p className="text-sm rounded-2xl px-4 py-8 text-center mb-6" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}>
                    Nada pendiente en este período: todos los viajes están pagados o no hubo entregas.
                </p>
            ) : (
                <ul className="space-y-2 mb-6">
                    {porPagar.map((p, i) => (
                        <li key={p.conductor_id} className="vp-fade-up rounded-2xl overflow-hidden" style={{ animationDelay: `${Math.min(i, 8) * 30}ms`, backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                            <div className="flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                                <button type="button" onClick={() => setAbierto(abierto === p.conductor_id ? null : p.conductor_id)} aria-expanded={abierto === p.conductor_id}
                                    className="flex items-center gap-2.5 min-w-[12rem] flex-1 text-left">
                                    <ChevronDown size={16} className="transition-transform duration-150" style={{ transform: abierto === p.conductor_id ? 'rotate(180deg)' : undefined, color: 'var(--color-text-muted)' }} />
                                    <span className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 10%, transparent)', color: 'var(--vp-navy)' }}><User size={15} /></span>
                                    <span>
                                        <span className="block text-sm font-bold" style={{ color: 'var(--color-text)' }}>{p.nombre}</span>
                                        <span className="block text-[12px]" style={{ color: 'var(--color-text-muted)' }}>{p.tipo === 'chofer' ? 'Chofer' : 'Ayudante'}</span>
                                    </span>
                                </button>
                                <Dato titulo="Viajes" valor={String(p.viajes)} />
                                <Dato titulo="Unidades" valor={fmtCantidad(p.unidades)} />
                                <Dato titulo="Vueltas" valor={String(p.vueltas)} />
                                <div className="text-right">
                                    <p className="text-[11px] uppercase tracking-wide font-semibold" style={{ color: 'var(--color-text-muted)' }}>Le toca</p>
                                    <p className="font-display text-lg font-extrabold tabular-nums" style={{ color: 'var(--vp-navy)' }}>{soles(p.monto)}</p>
                                </div>
                                {puede.crear && <Button onClick={() => setPagando(p)} startContent={<HandCoins size={15} />}>Pagar</Button>}
                            </div>
                            {abierto === p.conductor_id && <DetalleViajes detalle={p.detalle} />}
                        </li>
                    ))}
                </ul>
            )}

            {/* ── Liquidaciones ── */}
            <h2 className="text-base font-bold mb-2" style={{ color: 'var(--color-text)' }}>Pagado en el período</h2>
            {liquidaciones.length === 0 ? (
                <p className="text-sm rounded-2xl px-4 py-6 text-center" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', color: 'var(--color-text-muted)' }}>
                    Todavía no se pagó a nadie en este período.
                </p>
            ) : (
                <ul className="rounded-2xl overflow-hidden" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    {liquidaciones.map((l, i) => (
                        <li key={l.id} className="px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-1" style={{ borderTop: i ? '1px solid var(--color-border)' : undefined, opacity: l.estado === 'anulada' ? 0.6 : 1 }}>
                            <span className="text-sm font-bold w-20" style={{ color: 'var(--color-text)' }}>{l.numero}</span>
                            <span className="text-sm flex-1 min-w-[10rem]" style={{ color: 'var(--color-text)' }}>
                                {l.persona} <span style={{ color: 'var(--color-text-muted)' }}>· {fecha(l.desde)} al {fecha(l.hasta)} · {l.viajes} viajes</span>
                            </span>
                            {Math.abs(l.ajuste) >= 0.01 && (
                                <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }} title={l.ajuste_motivo ?? undefined}>
                                    {soles(l.monto_calculado)} {l.ajuste < 0 ? '−' : '+'} {soles(Math.abs(l.ajuste))} ({l.ajuste_motivo})
                                </span>
                            )}
                            <span className="text-sm font-bold tabular-nums" style={{ color: 'var(--color-text)' }}>{soles(l.total)}</span>
                            {l.estado === 'anulada'
                                ? <Pastilla color="var(--color-text-muted)">Anulada</Pastilla>
                                : <Pastilla color="var(--vp-mint-ink)">Pagada {fmtDia(l.fecha_pago).toLowerCase()}</Pastilla>}
                            {l.estado === 'pagada' && puede.editar && (
                                <Button size="sm" variant="ghost" onClick={() => setAnulando(l)} startContent={<Ban size={14} />}>Anular</Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <ModalPagar persona={pagando} periodo={periodo} gasto={gasto} onClose={() => setPagando(null)} />
            <ModalTarifas abierto={config} onClose={() => setConfig(false)} tarifas={tarifas} productos={productos} />
            <ModalAnular liq={anulando} onClose={() => setAnulando(null)} />
        </AppLayout>
    );
}

function Dato({ titulo, valor }: { titulo: string; valor: string }) {
    return (
        <div className="min-w-[4.5rem]">
            <p className="text-[11px] uppercase tracking-wide font-semibold" style={{ color: 'var(--color-text-muted)' }}>{titulo}</p>
            <p className="text-sm font-semibold tabular-nums" style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}

function DetalleViajes({ detalle }: { detalle: DetalleViaje[] }) {
    return (
        <ul className="px-4 pb-3 space-y-1.5" style={{ borderTop: '1px solid var(--color-border)' }}>
            {detalle.map(d => (
                <li key={d.viaje_id + d.rol} className="pt-2 flex flex-wrap items-start gap-x-4 gap-y-0.5 text-[13px]">
                    <Link href={route('viajes.show', d.viaje_id)} className="font-semibold hover:underline w-20" style={{ color: 'var(--color-primary)' }}>{d.numero}</Link>
                    <span className="w-24" style={{ color: 'var(--color-text-muted)' }}>{fmtDia(d.fecha)}</span>
                    <span className="font-mono w-20" style={{ color: 'var(--vp-navy)' }}>{d.placa ?? '—'}</span>
                    <span className="w-20" style={{ color: 'var(--color-text-muted)' }}>{d.rol === 'chofer' ? 'chofer' : 'ayudante'}</span>
                    <span className="flex-1 min-w-[10rem]" style={{ color: 'var(--color-text)' }}>
                        {d.tipo === 'abastecimiento' ? 'Vuelta de fábrica'
                            : d.productos.map(p => `${fmtCantidad(p.unidades)} ${p.producto}${p.sin_tarifa ? ' (sin tarifa)' : ''}`).join(' · ')}
                    </span>
                    <span className="font-semibold tabular-nums" style={{ color: 'var(--color-text)' }}>{soles(d.monto)}</span>
                </li>
            ))}
        </ul>
    );
}

function ModalPagar({ persona, periodo, gasto, onClose }: { persona: PorPagar | null; periodo: Props['periodo']; gasto: Props['gasto']; onClose: () => void }) {
    const { auth, turno_activo } = usePage<PageProps>().props;
    const afectaCaja = auth.user.empresa?.afecta_caja?.gastos?.activo ?? false;
    const turnoId = afectaCaja ? (turno_activo?.id ?? null) : null;
    const tiposPago = useMemo(() => gasto.tipos.filter(t => t.activo && (t.conceptos ?? []).some(c => c.activo && !c.flota_uso)), [gasto.tipos]);

    const [tipoId, setTipoId] = useState<number | ''>('');
    const [conceptoId, setConceptoId] = useState<number | ''>('');
    const [metodoId, setMetodoId] = useState<number | ''>('');
    const [cuentaId, setCuentaId] = useState<number | ''>('');
    const [localId, setLocalId] = useState<number | ''>('');
    const [ajuste, setAjuste] = useState('');
    const [motivo, setMotivo] = useState('');
    const [fechaPago, setFechaPago] = useState(hoyLocal());
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);

    useEffect(() => {
        if (!persona) return;
        const sugerido = tiposPago.find(t => /planilla|personal|chofer|pago/i.test(t.nombre)) ?? (tiposPago.length === 1 ? tiposPago[0] : null);
        setTipoId(sugerido?.id ?? '');
        const conceptos = (sugerido?.conceptos ?? []).filter(c => c.activo && !c.flota_uso);
        setConceptoId(conceptos.length === 1 ? conceptos[0].id : '');
        const efectivo = gasto.metodosPago.find(m => m.tipo?.slug === 'efectivo') ?? gasto.metodosPago[0];
        setMetodoId(efectivo?.id ?? '');
        setCuentaId(cuentaUnica(efectivo));
        setLocalId(gasto.locales.length === 1 ? gasto.locales[0].id : '');
        setAjuste(''); setMotivo(''); setFechaPago(hoyLocal()); setErrores({});
    }, [persona?.conductor_id]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!persona) return <Modal isOpen={false} onClose={onClose} title="">{null}</Modal>;

    const tipo = tiposPago.find(t => t.id === tipoId);
    const conceptos = (tipo?.conceptos ?? []).filter(c => c.activo && !c.flota_uso);
    const metodo = gasto.metodosPago.find(m => m.id === metodoId);
    const cuentas = (metodo?.cuentas ?? []).filter(c => c.pivot?.id);
    const ajusteNum = Number(ajuste) || 0;
    const total = Math.round((persona.monto + ajusteNum) * 100) / 100;
    const placas = [...new Set(persona.detalle.map(d => d.placa).filter(Boolean))];

    function pagar() {
        setGuardando(true);
        router.post(route('flota.pagos.pagar'), {
            conductor_id: persona!.conductor_id, desde: periodo.desde, hasta: periodo.hasta, fecha: fechaPago,
            ajuste: ajusteNum || null, ajuste_motivo: motivo || null,
            gasto_tipo_id: tipoId, gasto_concepto_id: conceptoId, metodo_pago_id: metodoId,
            cuenta_metodo_pago_id: cuentaId || null, turno_id: turnoId, local_id: turnoId ? null : (localId || null),
        } as unknown as Record<string, string>, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: e => { setErrores(e as Record<string, string>); avisoError(Object.values(e)[0]); },
            onFinish: () => setGuardando(false),
        });
    }

    return (
        <Modal isOpen onClose={onClose} size="md" title={`Pagar a ${persona.nombre}`}
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                <Button onClick={pagar} loading={guardando} disabled={total < 0.01} startContent={<HandCoins size={15} />}>Pagar {soles(total)}</Button>
            </>}>
            <div className="space-y-4">
                <div className="rounded-xl px-3 py-2.5 text-sm" style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
                    <div className="flex justify-between"><span style={{ color: 'var(--color-text-muted)' }}>{persona.viajes} viajes · {fmtCantidad(persona.unidades)} unidades · {persona.vueltas} vueltas</span>
                        <span className="font-semibold tabular-nums">{soles(persona.monto)}</span></div>
                    {ajusteNum !== 0 && <div className="flex justify-between mt-1"><span style={{ color: 'var(--color-text-muted)' }}>{ajusteNum < 0 ? 'Descuento / adelanto' : 'Bono'}</span>
                        <span className="tabular-nums" style={{ color: ajusteNum < 0 ? 'var(--vp-coral-ink)' : 'var(--vp-mint-ink)' }}>{ajusteNum < 0 ? '−' : '+'} {soles(Math.abs(ajusteNum))}</span></div>}
                    <div className="flex justify-between mt-1.5 pt-1.5 font-bold" style={{ borderTop: '1px solid var(--color-border)' }}><span>Total a pagar</span><span className="tabular-nums" style={{ color: 'var(--vp-navy)' }}>{soles(total)}</span></div>
                    {placas.length > 0 && <p className="text-[12px] mt-1.5" style={{ color: 'var(--color-text-muted)' }}>Se registra como gasto de {placas.join(' y ')}, en proporción a lo que trabajó en cada camión.</p>}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Input label="Ajuste (S/)" type="number" step="0.01" value={ajuste} onChange={e => setAjuste(e.target.value)} placeholder="−50 adelanto, +20 bono"
                        error={errores.ajuste} hint="Negativo descuenta, positivo suma" />
                    <Input label="Motivo del ajuste" value={motivo} onChange={e => setMotivo(e.target.value)} placeholder="Adelanto del martes" error={errores.ajuste_motivo} />
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Select label="Tipo de gasto" required value={tipoId} placeholder="Elegir…" error={errores.gasto_tipo_id}
                        onChange={v => { setTipoId(Number(v) || ''); setConceptoId(''); }} options={tiposPago.map(t => ({ value: t.id, label: t.nombre }))} />
                    <Select label="Concepto" required value={conceptoId} placeholder={tipoId ? 'Elegir…' : 'Primero el tipo'} disabled={!tipoId} error={errores.gasto_concepto_id}
                        onChange={v => setConceptoId(Number(v) || '')} options={conceptos.map(c => ({ value: c.id, label: c.nombre }))} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <Select label="Se paga con" required value={metodoId} error={errores.metodo_pago_id}
                        onChange={v => { const m = gasto.metodosPago.find(x => x.id === Number(v)); setMetodoId(Number(v) || ''); setCuentaId(cuentaUnica(m)); }}
                        options={gasto.metodosPago.map(m => ({ value: m.id, label: m.nombre }))} />
                    <Input label="Fecha del pago" type="date" value={fechaPago} onChange={e => setFechaPago(e.target.value)} error={errores.fecha} />
                </div>
                {cuentas.length > 0 && (
                    <Select label="Cuenta" required value={cuentaId} placeholder="Elegir cuenta…" error={errores.cuenta_metodo_pago_id}
                        onChange={v => setCuentaId(Number(v) || '')} options={cuentas.map(c => ({ value: c.pivot!.id, label: c.nombre }))} />
                )}
                {!turnoId && gasto.locales.length > 1 && (
                    <Select label="Local" required value={localId} placeholder="Elegir local…" error={errores.local_id}
                        onChange={v => setLocalId(Number(v) || '')} options={gasto.locales.map(l => ({ value: l.id, label: l.nombre }))} />
                )}
                <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                    {turnoId ? 'Sale de la caja de tu turno abierto.' : 'Se registra como gasto administrativo (no sale de un turno de caja).'}
                </p>
            </div>
        </Modal>
    );
}

function cuentaUnica(m?: MetodoPagoConCuentas | null): number | '' {
    const cts = (m?.cuentas ?? []).filter(c => c.pivot?.id);
    return cts.length === 1 ? cts[0].pivot!.id : '';
}

function ModalTarifas({ abierto, onClose, tarifas, productos }: { abierto: boolean; onClose: () => void; tarifas: Props['tarifas']; productos: Props['productos'] }) {
    const vacia = { por_cantidad: '1000', monto_chofer: '', monto_ayudante: '' };
    const [general, setGeneral] = useState(vacia);
    const [filas, setFilas] = useState<{ producto_id: number | ''; por_cantidad: string; monto_chofer: string; monto_ayudante: string }[]>([]);
    const [vuelta, setVuelta] = useState({ chofer: '', ayudante: '' });
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);

    useEffect(() => {
        if (!abierto) return;
        const g = tarifas.general;
        setGeneral(g ? { por_cantidad: String(g.por_cantidad), monto_chofer: String(g.monto_chofer), monto_ayudante: String(g.monto_ayudante) } : vacia);
        setFilas(tarifas.productos.map(t => ({ producto_id: t.producto_id ?? '', por_cantidad: String(t.por_cantidad), monto_chofer: String(t.monto_chofer), monto_ayudante: String(t.monto_ayudante) })));
        setVuelta({ chofer: tarifas.vuelta_chofer ? String(tarifas.vuelta_chofer) : '', ayudante: tarifas.vuelta_ayudante ? String(tarifas.vuelta_ayudante) : '' });
        setErrores({});
    }, [abierto]); // eslint-disable-line react-hooks/exhaustive-deps

    function guardar() {
        setGuardando(true);
        const tieneGeneral = general.monto_chofer !== '' || general.monto_ayudante !== '';
        router.put(route('flota.pagos.tarifas'), {
            general: tieneGeneral ? { por_cantidad: Number(general.por_cantidad) || 1000, monto_chofer: Number(general.monto_chofer) || 0, monto_ayudante: Number(general.monto_ayudante) || 0 } : null,
            productos: filas.filter(f => f.producto_id).map(f => ({ producto_id: f.producto_id, por_cantidad: Number(f.por_cantidad) || 1000, monto_chofer: Number(f.monto_chofer) || 0, monto_ayudante: Number(f.monto_ayudante) || 0 })),
            vuelta_chofer: Number(vuelta.chofer) || 0, vuelta_ayudante: Number(vuelta.ayudante) || 0,
        } as unknown as Record<string, string>, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: e => { setErrores(e as Record<string, string>); avisoError(Object.values(e)[0]); },
            onFinish: () => setGuardando(false),
        });
    }

    const campo = (v: string, on: (x: string) => void, label: string) => (
        <input type="number" min={0} step="0.01" inputMode="decimal" aria-label={label} value={v} onChange={e => on(e.target.value)}
            className="w-full h-10 rounded-lg border px-2 text-right text-sm tabular-nums outline-none focus:ring-2"
            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
    );

    return (
        <Modal isOpen={abierto} onClose={onClose} size="xl" title="Tarifas de pago"
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                <Button onClick={guardar} loading={guardando}>Guardar</Button>
            </>}>
            <div className="space-y-5">
                <div>
                    <p className="text-sm font-semibold mb-1" style={{ color: 'var(--color-text)' }}>Por lo que se reparte</p>
                    <p className="text-[13px] mb-2" style={{ color: 'var(--color-text-muted)' }}>Se paga al chofer y a CADA ayudante que fue, por cada tantas unidades entregadas.</p>
                    <div className="grid grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto] gap-2 items-center text-[12px] font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        <span>Producto</span><span className="text-right">Cada (und)</span><span className="text-right">Chofer S/</span><span className="text-right">Ayudante S/</span><span className="w-8" />
                    </div>
                    <div className="mt-1 grid grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto] gap-2 items-center">
                        <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Todos los demás</span>
                        {campo(general.por_cantidad, v => setGeneral(g => ({ ...g, por_cantidad: v })), 'Cada cuántas unidades, tarifa general')}
                        {campo(general.monto_chofer, v => setGeneral(g => ({ ...g, monto_chofer: v })), 'Pago al chofer, tarifa general')}
                        {campo(general.monto_ayudante, v => setGeneral(g => ({ ...g, monto_ayudante: v })), 'Pago al ayudante, tarifa general')}
                        <span className="w-8" />
                        {filas.map((f, i) => (
                            <FilaTarifa key={i} f={f} i={i} productos={productos} campo={campo} error={errores[`productos.${i}.producto_id`]}
                                onCambio={(k, v) => setFilas(fs => fs.map((x, j) => j === i ? { ...x, [k]: v } : x))}
                                onQuitar={() => setFilas(fs => fs.filter((_, j) => j !== i))} />
                        ))}
                    </div>
                    <Button size="sm" variant="ghost" className="mt-2" onClick={() => setFilas(fs => [...fs, { producto_id: '', por_cantidad: '1000', monto_chofer: '', monto_ayudante: '' }])}
                        startContent={<Plus size={14} />}>Tarifa para un producto</Button>
                </div>
                <div>
                    <p className="text-sm font-semibold mb-1" style={{ color: 'var(--color-text)' }}>Por vuelta de fábrica (abastecimiento)</p>
                    <div className="grid grid-cols-2 gap-3 max-w-sm">
                        <label className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Chofer S/{campo(vuelta.chofer, v => setVuelta(x => ({ ...x, chofer: v })), 'Pago al chofer por vuelta')}</label>
                        <label className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Ayudante S/{campo(vuelta.ayudante, v => setVuelta(x => ({ ...x, ayudante: v })), 'Pago al ayudante por vuelta')}</label>
                    </div>
                </div>
            </div>
        </Modal>
    );
}

function FilaTarifa({ f, productos, campo, error, onCambio, onQuitar }: {
    f: { producto_id: number | ''; por_cantidad: string; monto_chofer: string; monto_ayudante: string }; i: number;
    productos: Props['productos']; campo: (v: string, on: (x: string) => void, label: string) => React.ReactNode; error?: string;
    onCambio: (k: string, v: string | number) => void; onQuitar: () => void;
}) {
    return (
        <>
            <div className="min-w-0">
                <SearchableSelect value={f.producto_id} placeholder="Elegir producto…" options={productos} error={error}
                    onChange={(v: string | number) => onCambio('producto_id', Number(v) || '')} />
            </div>
            {campo(f.por_cantidad, v => onCambio('por_cantidad', v), 'Cada cuántas unidades')}
            {campo(f.monto_chofer, v => onCambio('monto_chofer', v), 'Pago al chofer')}
            {campo(f.monto_ayudante, v => onCambio('monto_ayudante', v), 'Pago al ayudante')}
            <button type="button" onClick={onQuitar} aria-label="Quitar tarifa" className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-black/5" style={{ color: 'var(--color-danger)' }}><Trash2 size={15} /></button>
        </>
    );
}

function ModalAnular({ liq, onClose }: { liq: Liquidacion | null; onClose: () => void }) {
    const [motivo, setMotivo] = useState('');
    const [enviando, setEnviando] = useState(false);
    useEffect(() => { if (liq) setMotivo(''); }, [liq]);

    return (
        <Modal isOpen={!!liq} onClose={onClose} size="sm" title={`Anular ${liq?.numero ?? ''}`}
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={enviando}>Cancelar</Button>
                <Button variant="danger" loading={enviando} disabled={!motivo.trim()} onClick={() => {
                    setEnviando(true);
                    router.post(route('flota.pagos.anular', liq!.id), { motivo }, {
                        preserveScroll: true, onSuccess: onClose, onError: e => avisoError(Object.values(e)[0]), onFinish: () => setEnviando(false),
                    });
                }}>Anular pago</Button>
            </>}>
            <div className="space-y-3">
                <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                    Se eliminan los gastos con que se pagó (vuelve el dinero a la caja o cuenta) y sus viajes quedan otra vez por pagar.
                </p>
                <Input label="Motivo" required value={motivo} onChange={e => setMotivo(e.target.value)} autoFocus />
            </div>
        </Modal>
    );
}
