import { useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Banknote, Check, Lock, MessageSquarePlus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import DynamicIcon from '@/Components/DynamicIcon';
import { fmtS, plural } from '@/Components/Reportes/ReportUI';
import { imprimirCierreAuto } from './Partials/imprimirCierre';
import AvisoStockNegativo, { type ProductoStockNegativo } from './Partials/AvisoStockNegativo';
import type { MetodoPago, Turno } from '@/types';

/**
 * Cierre rápido de caja: para locales que no cuentan billetes.
 *
 * La cajera solo quiere ver cuánto vendió hoy y cuánto debe tener en cada
 * medio de pago (cajón, Yape, tarjeta…) y cerrar. Lo demás (stock negativo,
 * nota) va plegado para no estorbar.
 */

interface Cobro {
    metodo_pago_id: number;
    nombre:         string;
    es_efectivo:    boolean;
    ventas:         number;
    otros:          number;
    devoluciones:   number;
    total:          number;
}

type DestinoEfectivo = 'caja' | 'administracion' | 'parcial';

interface Props {
    turno:                  Turno;
    cobrosPorMetodo:        Cobro[];
    productosStockNegativo: ProductoStockNegativo[];
    totalVentas:            number;
    totalGastos:            number;
    montoEsperado:          number;
    metodosPago:            MetodoPago[];
    preguntaDestino:        boolean;
    totalRetiros:           number;
}

const num = (v: string | number | null | undefined) => parseFloat(String(v ?? 0)) || 0;
const fechaHora = (iso: string) =>
    new Date(iso).toLocaleString('es-PE', { weekday: 'long', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

export default function CerrarRapido({ turno, cobrosPorMetodo, productosStockNegativo, totalVentas, totalGastos, montoEsperado, metodosPago, preguntaDestino, totalRetiros }: Props) {
    const caja = turno.caja!;
    const negativos = productosStockNegativo ?? [];
    const cantidadVentas = (turno.ventas ?? []).length;

    const [observacion, setObservacion] = useState('');
    const [verNota, setVerNota]         = useState(false);
    const [destino, setDestino]         = useState<DestinoEfectivo>('caja');
    const [queda, setQueda]             = useState('');
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [saving, setSaving]           = useState(false);
    const [confirmar, setConfirmar]     = useState(false);
    const impreso = useRef(false);

    // Efectivo: lo que debe haber en el cajón. Las salidas se deducen del
    // esperado para que la cuenta mostrada sume exacto.
    const apertura      = num(turno.monto_apertura);
    const cobroEfectivo = cobrosPorMetodo.find(c => c.es_efectivo)?.total ?? 0;
    const salidas       = Math.max(0, Math.round((apertura + cobroEfectivo - montoEsperado) * 100) / 100);
    const otrosMedios   = cobrosPorMetodo.filter(c => !c.es_efectivo && Math.abs(c.total) > 0.009);

    const quedaParcial = Math.min(parseFloat(queda) || 0, Math.max(0, montoEsperado));
    const entrega = destino === 'caja' ? 0 : destino === 'administracion' ? Math.max(0, montoEsperado) : Math.max(0, montoEsperado - quedaParcial);

    function cerrar() {
        if (negativos.length > 0) {
            setConfirmar(true);
            return;
        }
        enviar(false);
    }

    function enviar(confirmaStockNegativo: boolean) {
        setSaving(true);
        const payload: Record<string, unknown> = {
            observacion_cierre: observacion,
            confirma_stock_negativo: confirmaStockNegativo,
        };
        if (preguntaDestino) {
            payload.destino_efectivo = destino;
            if (destino === 'parcial') payload.efectivo_queda = quedaParcial;
        }

        router.post(route('turnos.cerrar', turno.id), payload as any, {
            onSuccess: () => {
                setSaving(false);
                setConfirmar(false);
                if (!impreso.current) {
                    impreso.current = true;
                    void imprimirCierreAuto(turno.id);
                }
            },
            onError: (errs: any) => { setErrors(errs); setSaving(false); setConfirmar(false); },
        });
    }

    const partesEfectivo = [
        apertura > 0.009 ? `abriste con ${fmtS(apertura)}` : null,
        `cobraste ${fmtS(cobroEfectivo)}`,
        salidas > 0.009 ? `salieron ${fmtS(salidas)} en gastos${totalRetiros > 0 ? ' y retiros' : ''}` : null,
    ].filter(Boolean).join(', ');

    return (
        <AppLayout title="Cerrar caja">
            <div className="max-w-3xl mx-auto pb-2">
                {/* ── Encabezado ─────────────────────────────────────────── */}
                <Link href={route('turnos.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-semibold mb-2 hover:underline" style={{ color: 'var(--color-primary)' }}>
                    <ArrowLeft size={15} /> Volver a turnos
                </Link>
                <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>
                    Cerrar caja
                </h1>
                <p className="text-[15px] mt-2 mb-4" style={{ color: 'var(--color-text-muted)' }}>
                    {caja.nombre}, abierta el {fechaHora(turno.fecha_apertura)}
                </p>

                {/* ── Lo vendido ─────────────────────────────────────────── */}
                <section className="rounded-2xl px-5 py-4 mb-3 flex flex-wrap items-end justify-between gap-x-6 gap-y-1"
                    style={{ backgroundColor: 'var(--vp-navy)', color: '#fff' }}>
                    <div>
                        <p className="text-sm font-semibold opacity-80">Vendiste en este turno</p>
                        <p className="font-display text-[36px] font-extrabold tracking-tight leading-tight tabular-nums">{fmtS(totalVentas)}</p>
                    </div>
                    <p className="text-[15px] font-semibold opacity-80 pb-1.5">{plural(cantidadVentas, 'venta', 'ventas')}</p>
                </section>

                {/* ── Cuánto debe haber en cada medio ────────────────────── */}
                <section className="rounded-2xl overflow-hidden mb-3"
                    style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
                    <header className="px-5 pt-4 pb-2">
                        <h2 className="text-base font-bold" style={{ color: 'var(--color-text)' }}>Esto debes tener</h2>
                        <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                            Compáralo con tu cajón, tu Yape, el POS de tarjetas o el banco.
                        </p>
                    </header>
                    <ul>
                        <Medio icono={<Banknote size={20} />} nombre="Efectivo en el cajón" monto={montoEsperado} nota={partesEfectivo} />
                        {otrosMedios.map(c => {
                            const m = metodosPago.find(x => x.id === c.metodo_pago_id);
                            const icono = (m?.tipo as { icono?: string } | null)?.icono;
                            return (
                                <Medio key={c.metodo_pago_id} nombre={c.nombre} monto={c.total}
                                    icono={<DynamicIcon name={icono || 'Wallet'} size={20} />}
                                    nota={c.devoluciones ? `cobraste ${fmtS(c.ventas + c.otros)}, devoluciones −${fmtS(c.devoluciones)}` : undefined} />
                            );
                        })}
                    </ul>
                    {otrosMedios.length === 0 && (
                        <p className="px-5 py-3 text-[13px]" style={{ color: 'var(--color-text-muted)', borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                            No hubo cobros por Yape, tarjeta ni transferencia.
                        </p>
                    )}
                    {totalGastos > 0 && (
                        <p className="px-5 py-2.5 text-[13px] flex justify-between gap-3"
                            style={{ color: 'var(--color-text-muted)', borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                            <span>{plural((turno.gastos ?? []).length, 'gasto', 'gastos')} en el turno</span>
                            <span className="tabular-nums font-semibold">−{fmtS(totalGastos)}</span>
                        </p>
                    )}
                </section>

                {/* ── Destino del efectivo (si la empresa lo pide) ───────── */}
                {preguntaDestino && (
                    <section className="rounded-2xl p-4 sm:p-5 mb-3"
                        style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                        <h2 className="text-base font-bold mb-3" style={{ color: 'var(--color-text)' }}>¿Qué haces con el efectivo?</h2>
                        <div className="grid sm:grid-cols-3 gap-2" role="radiogroup" aria-label="Destino del efectivo">
                            {([
                                ['caja', 'Queda en la caja'],
                                ['administracion', 'Lo entrego todo'],
                                ['parcial', 'Entrego una parte'],
                            ] as [DestinoEfectivo, string][]).map(([valor, titulo]) => {
                                const activo = destino === valor;
                                return (
                                    <button key={valor} type="button" role="radio" aria-checked={activo} disabled={saving}
                                        onClick={() => setDestino(valor)}
                                        className="flex items-center justify-between gap-2 text-left rounded-xl px-3.5 py-2.5 text-sm font-bold transition-colors"
                                        style={{
                                            border: `1.5px solid ${activo ? 'var(--vp-navy)' : 'var(--color-border)'}`,
                                            backgroundColor: activo ? 'color-mix(in srgb, var(--vp-navy) 7%, var(--color-surface))' : 'var(--color-surface)',
                                            color: 'var(--color-text)',
                                        }}>
                                        {titulo}
                                        {activo && <Check size={16} style={{ color: 'var(--vp-navy)' }} />}
                                    </button>
                                );
                            })}
                        </div>
                        {destino === 'parcial' && (
                            <label className="flex flex-wrap items-center gap-3 mt-3">
                                <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>¿Cuánto queda en la caja?</span>
                                <span className="relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                                    <input type="number" inputMode="decimal" step="0.01" min="0" placeholder="0.00"
                                        value={queda} disabled={saving} onChange={e => setQueda(e.target.value)}
                                        className="w-36 rounded-xl pl-9 pr-3 py-2 text-[15px] font-semibold text-right tabular-nums border outline-none focus:ring-2"
                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 30%, transparent)' } as React.CSSProperties} />
                                </span>
                            </label>
                        )}
                        {destino !== 'caja' && (
                            <p className="flex items-center justify-between gap-3 mt-3 rounded-xl px-4 py-2.5 text-sm font-semibold"
                                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 12%, var(--color-surface))', color: 'var(--vp-amber-ink)' }}>
                                Entregas a administración
                                <span className="font-display text-lg font-bold tabular-nums">{fmtS(entrega)}</span>
                            </p>
                        )}
                        {errors.destino_efectivo && <p className="text-sm mt-2 font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>{errors.destino_efectivo}</p>}
                        {errors.efectivo_queda && <p className="text-sm mt-2 font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>{errors.efectivo_queda}</p>}
                    </section>
                )}

                {/* ── Stock negativo, plegado ────────────────────────────── */}
                {negativos.length > 0 && <AvisoStockNegativo productos={negativos} />}

                {/* ── Nota opcional, plegada ─────────────────────────────── */}
                {verNota ? (
                    <section className="rounded-2xl p-4 mb-3" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                        <label className="block text-sm font-bold mb-2" style={{ color: 'var(--color-text)' }} htmlFor="nota-cierre">
                            Nota del cierre
                        </label>
                        <textarea id="nota-cierre" rows={2} value={observacion} disabled={saving} autoFocus
                            onChange={e => setObservacion(e.target.value)}
                            placeholder="Por ejemplo, por qué faltó o sobró dinero"
                            className="w-full rounded-xl px-3 py-2.5 text-sm resize-none border outline-none focus:ring-2"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 30%, transparent)' } as React.CSSProperties} />
                    </section>
                ) : (
                    <button type="button" onClick={() => setVerNota(true)}
                        className="mb-3 inline-flex items-center gap-2 text-sm font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                        <MessageSquarePlus size={16} /> Agregar una nota (opcional)
                    </button>
                )}

                {/* ── Cerrar ─────────────────────────────────────────────── */}
                <div className="sticky bottom-0 z-20 -mx-1 rounded-t-2xl px-4 sm:px-5 py-3 flex flex-wrap items-center gap-x-6 gap-y-2"
                    style={{ backgroundColor: 'var(--color-surface)', borderTop: '1px solid var(--color-border)', boxShadow: '0 -10px 24px -16px rgb(15 76 129 / 0.35)' }}>
                    <p className="text-xs flex-1 min-w-[12rem]" style={{ color: 'var(--color-text-muted)' }}>
                        Al cerrar ya no podrás vender en este turno. Solo un administrador puede reabrirlo.
                    </p>
                    {errors.stock_negativo && <p className="w-full text-sm font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>{errors.stock_negativo}</p>}
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <Button variant="ghost" onClick={() => router.visit(route('turnos.index'))} disabled={saving}>Cancelar</Button>
                        <button onClick={cerrar} disabled={saving}
                            className="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl px-6 py-2.5 text-[15px] font-bold text-white transition-colors disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                            style={{ backgroundColor: 'var(--vp-navy)', outlineColor: 'var(--vp-navy)' }}>
                            <Lock size={16} /> {saving ? 'Cerrando...' : 'Cerrar caja'}
                        </button>
                    </div>
                </div>
            </div>

            <Modal
                isOpen={confirmar}
                onClose={() => setConfirmar(false)}
                title="¿Cerrar la caja?"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmar(false)} disabled={saving}>Volver</Button>
                        <Button onClick={() => enviar(true)} loading={saving}>Sí, cerrar caja</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    {plural(negativos.length, 'producto quedó', 'productos quedaron')} con stock negativo.
                    Puedes cerrar igual y regularizarlo después con una entrada de mercadería.
                </p>
            </Modal>
        </AppLayout>
    );
}

function Medio({ icono, nombre, monto, nota }: { icono: React.ReactNode; nombre: string; monto: number; nota?: string }) {
    return (
        <li className="flex items-center gap-3.5 px-5 py-3.5"
            style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
            <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl"
                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 14%, var(--color-surface))', color: 'var(--vp-mint-ink)' }}>
                {icono}
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-[15px] font-bold" style={{ color: 'var(--color-text)' }}>{nombre}</p>
                {nota && <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{nota}</p>}
            </div>
            <span className="font-display text-2xl font-extrabold tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fmtS(monto)}</span>
        </li>
    );
}
