import { useMemo, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { AlertTriangle, ArrowLeft, Check, ClipboardCheck, Lock, Minus, PackageX, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import { fmtS, plural } from '@/Components/Reportes/ReportUI';
import { agenteActivo, imprimirCierreTurno, type ShiftClosurePayload } from '@/lib/ticketPrinter';
import type { MetodoPago, ModoCierreCaja, ModoCierreInventario, Turno } from '@/types';

interface CierreInventarioRef {
    id: number;
    estado: 'borrador' | 'confirmado';
}

// Producto vendido en el turno cuyo stock quedó negativo (aviso al cierre).
interface ProductoStockNegativo {
    producto_id:      number;
    producto_nombre:  string;
    cantidad_vendida: number;
    stock_actual:     number;
}

const BILLETES = [200, 100, 50, 20, 10];
const MONEDAS  = [5, 2, 1, 0.5, 0.2, 0.1];

interface FilaArqueo {
    denominacion: number;
    cantidad:     number;
}

interface FilaMetodo {
    metodo_pago_id:  number;
    monto_declarado: string;
}

type DestinoEfectivo = 'caja' | 'administracion' | 'parcial';

interface CerrarForm {
    arqueo:             FilaArqueo[];
    arqueo_metodos:     FilaMetodo[];
    observacion_cierre: string;
    destino_efectivo:   DestinoEfectivo;
    efectivo_queda:     string;
}

/** Lo que entró por un medio de pago en el turno (ventas sin vuelto + abonos + anticipos − reembolsos). */
interface Cobro {
    metodo_pago_id: number;
    nombre:         string;
    es_efectivo:    boolean;
    ventas:         number;
    otros:          number;
    devoluciones:   number;
    total:          number;
}

interface Props {
    turno:                          Turno;
    cobrosPorMetodo:                Cobro[];
    productosStockNegativo:         ProductoStockNegativo[];
    ventasPorMetodo:                Record<string, number>;
    totalVentas:                    number;
    totalGastos:                    number;
    montoEsperado:                  number;
    metodosPago:                    MetodoPago[];
    modoCierreCaja:                 ModoCierreCaja;
    modoCierreInventario:           ModoCierreInventario;
    cierreInventarioTurno:          CierreInventarioRef | null;
    usaFondosIniciales:             boolean;
    fondosInicialesEnDeclaracion:   boolean;
    /** Config empresa: preguntar qué pasa con el efectivo final al cerrar. */
    preguntaDestino:                boolean;
    /** Retiros de efectivo registrados durante el turno (ya descontados del esperado). */
    totalRetiros:                   number;
}

const num = (v: string | number | null | undefined) => parseFloat(String(v ?? 0)) || 0;
const fechaHora = (iso: string) =>
    new Date(iso).toLocaleString('es-PE', { weekday: 'long', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const etiquetaDenominacion = (d: number) => (d >= 1 ? `S/ ${d}` : `${Math.round(d * 100)} cént.`);

export default function CerrarTurno({ turno, cobrosPorMetodo, productosStockNegativo, totalVentas, totalGastos, montoEsperado, metodosPago, modoCierreCaja, modoCierreInventario, cierreInventarioTurno, usaFondosIniciales, fondosInicialesEnDeclaracion, preguntaDestino, totalRetiros }: Props) {
    const caja = turno.caja!;
    const requiereArqueo    = modoCierreCaja === 'con_declaraciones';
    const requiereCierreInv = modoCierreInventario === 'declarado';
    const inventarioListo   = !requiereCierreInv || cierreInventarioTurno?.estado === 'confirmado';
    const fondosCajaChica   = num(turno.monto_caja_chica);

    const [form, setForm] = useState<CerrarForm>({
        arqueo: [...BILLETES, ...MONEDAS].map(d => ({ denominacion: d, cantidad: 0 })),
        arqueo_metodos: metodosPago
            .filter(m => m.tipo?.slug !== 'efectivo')
            .map(m => ({ metodo_pago_id: m.id, monto_declarado: '' })),
        observacion_cierre: '',
        destino_efectivo:   'caja',
        efectivo_queda:     '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    // Modal de confirmación cuando el turno vendió productos que quedaron
    // con stock negativo: "¿deseas cerrar la caja de todas formas?"
    const [modalStockNegativo, setModalStockNegativo] = useState(false);
    const hayStockNegativo = (productosStockNegativo ?? []).length > 0;
    const autoImpreso = useRef(false);

    const totalEfectivo = useMemo(() =>
        form.arqueo.reduce((sum, f) => sum + f.denominacion * f.cantidad, 0),
    [form.arqueo]);
    const contoAlgo = form.arqueo.some(f => f.cantidad > 0);

    const diferencia = totalEfectivo - montoEsperado;

    // Efectivo final del cajón: lo contado (arqueo) o, en modo rápido, el esperado.
    const efectivoFinal = requiereArqueo ? totalEfectivo : montoEsperado;
    const quedaParcial  = Math.min(parseFloat(form.efectivo_queda) || 0, Math.max(0, efectivoFinal));
    const entregaCalculada = form.destino_efectivo === 'caja'
        ? 0
        : form.destino_efectivo === 'administracion'
            ? Math.max(0, efectivoFinal)
            : Math.max(0, efectivoFinal - quedaParcial);

    const metodosFiltrados = metodosPago.filter(m => m.tipo?.slug !== 'efectivo');

    function setCantidad(denominacion: number, cantidad: number) {
        setForm(f => ({
            ...f,
            arqueo: f.arqueo.map(row =>
                row.denominacion === denominacion ? { ...row, cantidad: Math.max(0, cantidad) } : row
            ),
        }));
    }

    function setMontoMetodo(metodoPagoId: number, monto: string) {
        setForm(f => ({
            ...f,
            arqueo_metodos: f.arqueo_metodos.map(row =>
                row.metodo_pago_id === metodoPagoId ? { ...row, monto_declarado: monto } : row
            ),
        }));
    }

    function submit() {
        if (requiereCierreInv && cierreInventarioTurno?.estado !== 'confirmado') {
            setErrors({ cierre_inventario: 'Debes confirmar el cierre de inventario asociado al turno antes de cerrar la caja.' });
            document.getElementById('paso-inventario')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Si se vendieron productos que quedaron con stock negativo, pedir
        // confirmación explícita antes de enviar el cierre (el backend también
        // lo exige vía confirma_stock_negativo).
        if (hayStockNegativo) {
            setModalStockNegativo(true);
            return;
        }

        enviarCierre(false);
    }

    function enviarCierre(confirmaStockNegativo: boolean) {
        setSaving(true);
        const payload: Record<string, unknown> = {
            observacion_cierre: form.observacion_cierre,
            confirma_stock_negativo: confirmaStockNegativo,
        };

        if (preguntaDestino) {
            payload.destino_efectivo = form.destino_efectivo;
            if (form.destino_efectivo === 'parcial') {
                payload.efectivo_queda = quedaParcial;
            }
        }

        if (requiereArqueo) {
            payload.arqueo = form.arqueo;
            payload.arqueo_metodos = form.arqueo_metodos.map(m => ({
                metodo_pago_id:  m.metodo_pago_id,
                monto_declarado: parseFloat(m.monto_declarado) || 0,
            }));
        }

        router.post(route('turnos.cerrar', turno.id), payload as any, {
            onSuccess: () => {
                setSaving(false);
                setModalStockNegativo(false);
                void imprimirCierreAuto();
            },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); setModalStockNegativo(false); },
        });
    }

    /** Auto-imprime el reporte de cierre tras cerrar el turno. */
    async function imprimirCierreAuto() {
        if (autoImpreso.current) return;
        autoImpreso.current = true;

        if (!(await agenteActivo())) {
            toast.error('No se imprimió el cierre: el agente VentoryPrint no está activo en esta PC.');
            return;
        }

        try {
            const { data } = await axios.get<ShiftClosurePayload>(route('turnos.cierre-ticket', turno.id));
            if (!data?.token) {
                toast.error('Esta caja no tiene ticketera configurada.');
                return;
            }
            const ok = await imprimirCierreTurno(data);
            if (ok) toast.success('Reporte de cierre enviado a la impresora');
            else    toast.error('No se pudo imprimir el cierre. Revisa VentoryPrint en esta PC.');
        } catch {
            toast.error('No se pudo obtener el reporte de cierre para imprimir.');
        }
    }

    // Pasos que aplican según la configuración del local.
    let n = 0;
    const paso = () => ++n;

    return (
        <AppLayout title="Cerrar turno">
            {/* ── Encabezado ─────────────────────────────────────────────────── */}
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0">
                    <Link href={route('turnos.index')}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold mb-2 hover:underline" style={{ color: 'var(--color-primary)' }}>
                        <ArrowLeft size={15} /> Volver a turnos
                    </Link>
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>
                        Cerrar turno
                    </h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        {caja.nombre}, abierto el {fechaHora(turno.fecha_apertura)}
                    </p>
                </div>
            </div>

            {/* ── Aviso: productos con stock negativo ───────────────────────── */}
            {hayStockNegativo && (
                <div className="mb-4 rounded-2xl overflow-hidden"
                    style={{ border: '1px solid color-mix(in srgb, var(--vp-coral) 40%, transparent)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-start gap-3 px-4 py-3.5" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-coral) 10%, var(--color-surface))' }}>
                        <PackageX size={20} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--vp-coral-ink)' }} />
                        <div>
                            <p className="text-[15px] font-bold" style={{ color: 'var(--vp-coral-ink)' }}>
                                {plural(productosStockNegativo.length, 'producto quedó', 'productos quedaron')} con stock negativo
                            </p>
                            <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                Se vendió más de lo que había registrado. Regulariza con una entrada o transferencia, o confirma el cierre de todas formas.
                            </p>
                        </div>
                    </div>
                    <ul>
                        {productosStockNegativo.map(p => (
                            <li key={p.producto_id} className="flex items-center justify-between gap-3 px-4 py-2 text-sm"
                                style={{ borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
                                <span className="font-semibold min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</span>
                                <span className="tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
                                    vendiste {Number(p.cantidad_vendida)}, quedan <strong style={{ color: 'var(--vp-coral-ink)' }}>{Number(p.stock_actual)}</strong>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="grid lg:grid-cols-[minmax(0,1fr)_360px] gap-4 items-start">
                {/* ── Pasos ─────────────────────────────────────────────────── */}
                <div className="space-y-3 min-w-0">
                    {requiereCierreInv && (
                        <Paso id="paso-inventario" numero={paso()} titulo="Cierra el inventario del turno"
                            listo={inventarioListo}
                            descripcion="Tu local pide contar la mercadería antes de cerrar la caja.">
                            {cierreInventarioTurno?.estado === 'confirmado' ? (
                                <Estado color="var(--vp-mint)" tinta="var(--vp-mint-ink)" icon={<ClipboardCheck size={16} />}>
                                    Cierre de inventario #{cierreInventarioTurno.id} confirmado.
                                </Estado>
                            ) : cierreInventarioTurno?.estado === 'borrador' ? (
                                <div className="flex flex-wrap items-center gap-3">
                                    <Estado color="var(--vp-amber)" tinta="var(--vp-amber-ink)" icon={<ClipboardCheck size={16} />}>
                                        El borrador #{cierreInventarioTurno.id} falta confirmar.
                                    </Estado>
                                    <Link href={route('inventario.cierres.show', cierreInventarioTurno.id)}>
                                        <Button variant="secondary">Terminar el cierre de inventario</Button>
                                    </Link>
                                </div>
                            ) : (
                                <div className="flex flex-wrap items-center gap-3">
                                    <Estado color="var(--vp-coral)" tinta="var(--vp-coral-ink)" icon={<AlertTriangle size={16} />}>
                                        Aún no hiciste el cierre de inventario de este turno.
                                    </Estado>
                                    <Link href={`${route('inventario.cierres.create')}?turno_id=${turno.id}`}>
                                        <Button>Hacer el cierre de inventario</Button>
                                    </Link>
                                </div>
                            )}
                            {errors.cierre_inventario && (
                                <p className="text-sm mt-2 font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>{errors.cierre_inventario}</p>
                            )}
                        </Paso>
                    )}

                    {requiereArqueo ? (
                        <>
                            <Paso numero={paso()} titulo="Cuenta el efectivo del cajón" listo={contoAlgo}
                                descripcion="Anota cuántos billetes y monedas de cada tipo tienes. El total se calcula solo.">
                                <GrupoDenominaciones titulo="Billetes" valores={BILLETES} arqueo={form.arqueo} onCambiar={setCantidad} disabled={saving} />
                                <GrupoDenominaciones titulo="Monedas" valores={MONEDAS} arqueo={form.arqueo} onCambiar={setCantidad} disabled={saving} />
                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 6%, var(--color-surface))' }}>
                                    <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Contaste en efectivo</span>
                                    <span className="font-display text-2xl font-extrabold tabular-nums" style={{ color: 'var(--vp-navy)' }}>{fmtS(totalEfectivo)}</span>
                                </div>
                                {errors.arqueo && <p className="text-sm mt-2 font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>{errors.arqueo}</p>}
                            </Paso>

                            {metodosFiltrados.length > 0 && (
                                <Paso numero={paso()} titulo="Revisa cuánto entró por cada medio de pago"
                                    listo={form.arqueo_metodos.some(m => m.monto_declarado !== '')}
                                    descripcion="Anota lo que ves en el POS, el banco o la app. A la derecha tienes lo que registró el sistema.">
                                    <ul className="space-y-2">
                                        {form.arqueo_metodos.map(row => {
                                            const metodo = metodosPago.find(m => m.id === row.metodo_pago_id)!;
                                            const sistema = cobrosPorMetodo.find(c => c.metodo_pago_id === row.metodo_pago_id)?.total ?? 0;
                                            const escrito = row.monto_declarado !== '';
                                            const dif = (parseFloat(row.monto_declarado) || 0) - sistema;
                                            return (
                                                <li key={row.metodo_pago_id} className="grid grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[minmax(0,1fr)_9rem_minmax(8rem,auto)] items-center gap-x-4 gap-y-1 rounded-xl px-3 py-2.5"
                                                    style={{ border: '1px solid var(--color-border)' }}>
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>{metodo.nombre as string}</p>
                                                        <p className="text-[13px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>el sistema registró {fmtS(sistema)}</p>
                                                    </div>
                                                    <label className="relative">
                                                        <span className="sr-only">Monto de {metodo.nombre as string}</span>
                                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                                                        <input type="number" inputMode="decimal" step="0.01" min="0" placeholder="0.00"
                                                            value={row.monto_declarado} disabled={saving}
                                                            onChange={e => setMontoMetodo(row.metodo_pago_id, e.target.value)}
                                                            className="w-full rounded-xl pl-9 pr-3 py-2 text-[15px] font-semibold text-right tabular-nums border outline-none focus:ring-2"
                                                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 30%, transparent)' } as React.CSSProperties} />
                                                    </label>
                                                    <span className="col-span-2 sm:col-span-1 sm:text-right text-[13px] font-semibold">
                                                        {escrito && <Diferencia valor={dif} />}
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </Paso>
                            )}
                        </>
                    ) : (
                        <Paso numero={paso()} titulo="Revisa cuánto entró por cada medio de pago"
                            descripcion="Tu local cierra en modo rápido: no hace falta contar billetes. Compara estos montos con tu cajón, tu Yape, el POS de tarjetas o el banco.">
                            <ul className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                <FilaMedio nombre="Efectivo en el cajón" monto={montoEsperado} destacado
                                    nota={`Abriste con ${fmtS(num(turno.monto_apertura))}, cobraste ${fmtS(cobrosPorMetodo.find(c => c.es_efectivo)?.total ?? 0)} en efectivo y salieron gastos, retiros o devoluciones en efectivo.`} />
                                {cobrosPorMetodo.filter(c => !c.es_efectivo).map(c => (
                                    <FilaMedio key={c.metodo_pago_id} nombre={c.nombre} monto={c.total} nota={notaCobro(c)} />
                                ))}
                            </ul>
                            {cobrosPorMetodo.filter(c => !c.es_efectivo).length === 0 && (
                                <p className="text-[13px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                                    En este turno no hubo cobros por Yape, tarjeta ni transferencia.
                                </p>
                            )}
                        </Paso>
                    )}

                    {preguntaDestino && (
                        <Paso numero={paso()} titulo="¿Qué haces con el efectivo?"
                            descripcion="Decide si el dinero se queda en la caja para el siguiente turno o se entrega a administración.">
                            <div className="grid sm:grid-cols-3 gap-2" role="radiogroup" aria-label="Destino del efectivo">
                                {([
                                    ['caja', 'Queda en la caja', 'Será la apertura del siguiente turno.'],
                                    ['administracion', 'Lo entrego todo', 'Pasa a administración; el siguiente turno abre sin él.'],
                                    ['parcial', 'Entrego una parte', 'Una parte queda en caja y el resto se entrega.'],
                                ] as [DestinoEfectivo, string, string][]).map(([valor, titulo, hint]) => {
                                    const activo = form.destino_efectivo === valor;
                                    return (
                                        <button key={valor} type="button" role="radio" aria-checked={activo} disabled={saving}
                                            onClick={() => setForm(f => ({ ...f, destino_efectivo: valor }))}
                                            className="text-left rounded-xl px-3.5 py-3 transition-colors focus-visible:outline focus-visible:outline-2"
                                            style={{
                                                border: `1.5px solid ${activo ? 'var(--vp-navy)' : 'var(--color-border)'}`,
                                                backgroundColor: activo ? 'color-mix(in srgb, var(--vp-navy) 7%, var(--color-surface))' : 'var(--color-surface)',
                                                outlineColor: 'var(--color-primary)',
                                            }}>
                                            <span className="flex items-center justify-between gap-2">
                                                <span className="text-sm font-bold" style={{ color: 'var(--color-text)' }}>{titulo}</span>
                                                {activo && <Check size={16} style={{ color: 'var(--vp-navy)' }} />}
                                            </span>
                                            <span className="block text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{hint}</span>
                                        </button>
                                    );
                                })}
                            </div>

                            {form.destino_efectivo === 'parcial' && (
                                <label className="flex flex-wrap items-center gap-3 mt-3">
                                    <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>¿Cuánto queda en la caja?</span>
                                    <span className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                                        <input type="number" inputMode="decimal" step="0.01" min="0" placeholder="0.00"
                                            value={form.efectivo_queda} disabled={saving}
                                            onChange={e => setForm(f => ({ ...f, efectivo_queda: e.target.value }))}
                                            className="w-36 rounded-xl pl-9 pr-3 py-2 text-[15px] font-semibold text-right tabular-nums border outline-none focus:ring-2"
                                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 30%, transparent)' } as React.CSSProperties} />
                                    </span>
                                </label>
                            )}

                            {form.destino_efectivo !== 'caja' && (
                                <div className="flex items-center justify-between gap-3 mt-3 rounded-xl px-4 py-2.5"
                                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 12%, var(--color-surface))' }}>
                                    <span className="text-sm font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>Entregas a administración</span>
                                    <span className="font-display text-lg font-bold tabular-nums" style={{ color: 'var(--vp-amber-ink)' }}>{fmtS(entregaCalculada)}</span>
                                </div>
                            )}
                            {errors.destino_efectivo && <p className="text-sm mt-2 font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>{errors.destino_efectivo}</p>}
                            {errors.efectivo_queda && <p className="text-sm mt-2 font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>{errors.efectivo_queda}</p>}
                        </Paso>
                    )}

                    <Paso numero={paso()} titulo="Deja una nota, si hace falta"
                        descripcion="Por ejemplo, por qué faltó o sobró dinero. Queda en el historial del turno.">
                        <textarea rows={3} value={form.observacion_cierre} disabled={saving}
                            onChange={e => setForm(f => ({ ...f, observacion_cierre: e.target.value }))}
                            placeholder="Opcional"
                            aria-label="Nota de cierre"
                            className="w-full rounded-xl px-3 py-2.5 text-sm resize-none border outline-none focus:ring-2"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 30%, transparent)' } as React.CSSProperties} />
                    </Paso>
                </div>

                {/* ── Resumen del turno ─────────────────────────────────────── */}
                <aside className="lg:sticky lg:top-20 rounded-2xl overflow-hidden"
                    style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.14)' }}>
                    <div className="p-4" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 10%, var(--color-surface))' }}>
                        <p className="text-[13px] font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>Debería haber en efectivo</p>
                        <p className="font-display text-[30px] font-extrabold tracking-tight leading-tight tabular-nums" style={{ color: 'var(--color-text)' }}>{fmtS(montoEsperado)}</p>
                        <p className="text-[13px] mt-1" style={{ color: 'var(--color-text-muted)' }}>
                            Apertura más lo cobrado en efectivo, menos gastos{totalRetiros > 0 ? ', retiros' : ''} y devoluciones en efectivo.
                        </p>
                    </div>
                    <p className="px-4 pt-3 pb-1 text-[13px] font-bold" style={{ color: 'var(--color-text)' }}>Resumen del turno</p>
                    <dl className="pb-2">
                        <LineaResumen label="Abriste con" valor={fmtS(num(turno.monto_apertura))} />
                        <LineaResumen label={`Vendiste (${plural((turno.ventas ?? []).length, 'venta', 'ventas')})`} valor={fmtS(totalVentas)} />
                        {cobrosPorMetodo.map(c => (
                            <LineaResumen key={c.metodo_pago_id} label={c.es_efectivo ? 'Cobrado en efectivo' : c.nombre} valor={fmtS(c.total)} sub />
                        ))}
                        <LineaResumen label={`Gastos (${(turno.gastos ?? []).length})`} valor={`−${fmtS(totalGastos)}`} />
                        {totalRetiros > 0 && <LineaResumen label="Retiros" valor={`−${fmtS(totalRetiros)}`} />}
                    </dl>
                    {usaFondosIniciales && fondosCajaChica > 0 && (
                        <p className="mx-4 mb-4 rounded-xl px-3 py-2.5 text-[13px]"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 12%, var(--color-surface))', color: 'var(--vp-amber-ink)' }}>
                            <strong>Caja chica {fmtS(fondosCajaChica)}:</strong>{' '}
                            {fondosInicialesEnDeclaracion ? 'va incluida en lo que debes contar.' : 'queda aparte, no la cuentes en el arqueo.'}
                        </p>
                    )}
                </aside>
            </div>

            {/* ── Barra fija: resultado en vivo + cerrar ───────────────────── */}
            <div className="sticky bottom-0 z-20 mt-4 -mx-1 rounded-t-2xl px-4 sm:px-5 py-3 flex flex-wrap items-center gap-x-6 gap-y-3"
                style={{ backgroundColor: 'var(--color-surface)', borderTop: '1px solid var(--color-border)', boxShadow: '0 -10px 24px -16px rgb(15 76 129 / 0.35)' }}>
                {requiereArqueo ? (
                    <div className="flex flex-wrap items-center gap-x-6 gap-y-1 min-w-0">
                        <ResumenVivo label="Contaste" valor={fmtS(totalEfectivo)} />
                        <ResumenVivo label="Debería haber" valor={fmtS(montoEsperado)} />
                        <div className="min-w-0">
                            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Resultado</p>
                            {contoAlgo ? <Diferencia valor={diferencia} grande /> : <p className="text-sm font-semibold" style={{ color: 'var(--color-text-muted)' }}>Cuenta el efectivo</p>}
                        </div>
                    </div>
                ) : (
                    <ResumenVivo label="Efectivo final" valor={fmtS(montoEsperado)} />
                )}
                <div className="ml-auto flex flex-col items-stretch sm:items-end gap-1.5 w-full sm:w-auto">
                    {errors.stock_negativo && <p className="text-sm font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>{errors.stock_negativo}</p>}
                    <div className="flex items-center gap-2">
                        <Button variant="ghost" onClick={() => router.visit(route('turnos.index'))} disabled={saving}>Cancelar</Button>
                        <button onClick={submit} disabled={saving}
                            className="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-[15px] font-bold text-white transition-colors disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                            style={{ backgroundColor: 'var(--vp-navy)', outlineColor: 'var(--vp-navy)' }}>
                            <Lock size={16} /> {saving ? 'Cerrando...' : 'Cerrar turno'}
                        </button>
                    </div>
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        Al cerrar ya no podrás vender en este turno. Solo un administrador puede reabrirlo.
                    </p>
                </div>
            </div>

            {/* Confirmación de cierre con stock negativo */}
            <Modal
                isOpen={modalStockNegativo}
                onClose={() => setModalStockNegativo(false)}
                title="Productos con stock negativo"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalStockNegativo(false)} disabled={saving}>
                            Volver
                        </Button>
                        <Button variant="danger" onClick={() => enviarCierre(true)} loading={saving}>
                            Sí, cerrar de todas formas
                        </Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <div className="flex items-start gap-2">
                        <AlertTriangle size={18} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-danger)' }} />
                        <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                            Durante este turno vendiste los siguientes productos y su stock quedó
                            <strong> en negativo</strong>. ¿Deseas cerrar la caja de todas formas?
                        </p>
                    </div>
                    <ul className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                        {(productosStockNegativo ?? []).map((p, i) => (
                            <li key={p.producto_id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                style={{ borderTop: i ? '1px solid var(--color-border)' : undefined }}>
                                <span className="font-medium min-w-0 truncate" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</span>
                                <span className="tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
                                    vendiste {Number(p.cantidad_vendida)}, quedan <strong style={{ color: 'var(--color-danger)' }}>{Number(p.stock_actual)}</strong>
                                </span>
                            </li>
                        ))}
                    </ul>
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        Sugerencia: registra la entrada o transferencia de mercadería pendiente para regularizar el inventario. El stock negativo también aparece resaltado en el módulo de inventario.
                    </p>
                </div>
            </Modal>
        </AppLayout>
    );
}

/* ── Paso del cierre ───────────────────────────────────────────────────── */
function Paso({ id, numero, titulo, descripcion, listo = false, children }: {
    id?: string; numero: number; titulo: string; descripcion?: string; listo?: boolean; children: React.ReactNode;
}) {
    return (
        <section id={id} className="rounded-2xl p-4 sm:p-5 scroll-mt-24"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
            <header className="flex items-start gap-3 mb-4">
                <span className="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold flex-shrink-0 font-display"
                    style={listo
                        ? { backgroundColor: 'var(--vp-mint)', color: '#003B2B' }
                        : { backgroundColor: 'color-mix(in srgb, var(--vp-navy) 10%, var(--color-surface))', color: 'var(--vp-navy)' }}>
                    {listo ? <Check size={16} /> : numero}
                </span>
                <div className="min-w-0">
                    <h2 className="text-base font-bold" style={{ color: 'var(--color-text)' }}>{titulo}</h2>
                    {descripcion && <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{descripcion}</p>}
                </div>
            </header>
            {children}
        </section>
    );
}

function Estado({ color, tinta, icon, children }: { color: string; tinta: string; icon: React.ReactNode; children: React.ReactNode }) {
    return (
        <p className="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold"
            style={{ color: tinta, backgroundColor: `color-mix(in srgb, ${color} 13%, var(--color-surface))` }}>
            {icon}{children}
        </p>
    );
}

/* ── Conteo de billetes y monedas ──────────────────────────────────────── */
function GrupoDenominaciones({ titulo, valores, arqueo, onCambiar, disabled }: {
    titulo: string; valores: number[]; arqueo: FilaArqueo[];
    onCambiar: (d: number, c: number) => void; disabled: boolean;
}) {
    return (
        <div className="mb-3 last:mb-0">
            <p className="text-[13px] font-semibold mb-2" style={{ color: 'var(--color-text-muted)' }}>{titulo}</p>
            {/* Billetes 5 y monedas 6: cada grupo cabe en una sola fila en pantallas anchas. */}
            <div className={`grid grid-cols-2 sm:grid-cols-3 gap-2 ${valores.length > 5 ? 'xl:grid-cols-6' : 'xl:grid-cols-5'}`}>
                {valores.map(d => {
                    const cantidad = arqueo.find(f => f.denominacion === d)?.cantidad ?? 0;
                    const activo = cantidad > 0;
                    return (
                        <div key={d} className="rounded-xl p-2.5 transition-colors"
                            style={{
                                border: `1px solid ${activo ? 'color-mix(in srgb, var(--vp-navy) 35%, transparent)' : 'var(--color-border)'}`,
                                backgroundColor: activo ? 'color-mix(in srgb, var(--vp-navy) 5%, var(--color-surface))' : 'var(--color-surface)',
                            }}>
                            <p className="font-display text-[15px] font-bold" style={{ color: 'var(--vp-navy)' }}>{etiquetaDenominacion(d)}</p>
                            <div className="flex items-center gap-1 mt-1.5">
                                <button type="button" onClick={() => onCambiar(d, cantidad - 1)} disabled={disabled || cantidad === 0}
                                    aria-label={`Quitar uno de ${etiquetaDenominacion(d)}`}
                                    className="h-8 w-8 flex-shrink-0 rounded-lg flex items-center justify-center transition-colors disabled:opacity-30 hover:bg-black/5"
                                    style={{ border: '1px solid var(--color-border)', color: 'var(--color-text)' }}>
                                    <Minus size={14} />
                                </button>
                                <input type="number" inputMode="numeric" min="0" value={cantidad || ''} placeholder="0" disabled={disabled}
                                    aria-label={`Cantidad de ${etiquetaDenominacion(d)}`}
                                    onChange={e => onCambiar(d, parseInt(e.target.value) || 0)}
                                    onFocus={e => e.target.select()}
                                    className="w-full min-w-0 h-8 rounded-lg text-center text-[15px] font-bold tabular-nums border outline-none focus:ring-2 px-1"
                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 30%, transparent)' } as React.CSSProperties} />
                                <button type="button" onClick={() => onCambiar(d, cantidad + 1)} disabled={disabled}
                                    aria-label={`Agregar uno de ${etiquetaDenominacion(d)}`}
                                    className="h-8 w-8 flex-shrink-0 rounded-lg flex items-center justify-center transition-colors disabled:opacity-30 hover:bg-black/5"
                                    style={{ border: '1px solid var(--color-border)', color: 'var(--color-text)' }}>
                                    <Plus size={14} />
                                </button>
                            </div>
                            <p className="text-[13px] tabular-nums mt-1.5 text-right" style={{ color: activo ? 'var(--color-text)' : 'var(--color-text-muted)' }}>
                                {fmtS(d * cantidad)}
                            </p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/* ── Resultado: cuadra / sobra / falta ─────────────────────────────────── */
function Diferencia({ valor, grande = false }: { valor: number; grande?: boolean }) {
    const [texto, color, tinta] = Math.abs(valor) < 0.005
        ? ['Cuadra', 'var(--vp-mint)', 'var(--vp-mint-ink)']
        : valor > 0
            ? [`Sobran ${fmtS(valor)}`, 'var(--vp-amber)', 'var(--vp-amber-ink)']
            : [`Faltan ${fmtS(Math.abs(valor))}`, 'var(--vp-coral)', 'var(--vp-coral-ink)'];
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full font-bold tabular-nums whitespace-nowrap ${grande ? 'px-3 py-1 text-[15px]' : 'px-2.5 py-0.5 text-[13px]'}`}
            style={{ color: tinta, backgroundColor: `color-mix(in srgb, ${color} 15%, transparent)` }}>
            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: color }} /> {texto}
        </span>
    );
}

function ResumenVivo({ label, valor }: { label: string; valor: string }) {
    return (
        <div className="min-w-0">
            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className="font-display text-xl font-bold tabular-nums leading-tight" style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}

function LineaResumen({ label, valor, sub = false }: { label: string; valor: string; sub?: boolean }) {
    return (
        <div className={`flex items-center justify-between gap-3 px-4 ${sub ? 'py-1' : 'py-2'}`}
            style={sub ? undefined : { borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)' }}>
            <dt className={sub ? 'text-[13px] pl-3' : 'text-sm'} style={{ color: sub ? 'var(--color-text-muted)' : 'var(--color-text)' }}>{label}</dt>
            <dd className={`tabular-nums ${sub ? 'text-[13px]' : 'text-[15px] font-bold'}`} style={{ color: sub ? 'var(--color-text-muted)' : 'var(--color-text)' }}>{valor}</dd>
        </div>
    );
}

/** "ventas S/ 120, abonos y anticipos S/ 30, devoluciones −S/ 10" (solo lo que aplica) */
function notaCobro(c: Cobro): string {
    const partes = [
        c.ventas ? `ventas ${fmtS(c.ventas)}` : null,
        c.otros ? `abonos y anticipos ${fmtS(c.otros)}` : null,
        c.devoluciones ? `devoluciones −${fmtS(c.devoluciones)}` : null,
    ].filter(Boolean);
    return partes.length > 1 ? partes.join(', ') : '';
}

function FilaMedio({ nombre, monto, nota, destacado = false }: { nombre: string; monto: number; nota?: string; destacado?: boolean }) {
    return (
        <li className="flex items-start justify-between gap-4 px-4 py-3"
            style={{
                borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)',
                backgroundColor: destacado ? 'color-mix(in srgb, var(--vp-mint) 8%, var(--color-surface))' : undefined,
            }}>
            <div className="min-w-0">
                <p className="text-[15px] font-semibold" style={{ color: 'var(--color-text)' }}>{nombre}</p>
                {nota && <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{nota}</p>}
            </div>
            <span className="font-display text-lg font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--color-text)' }}>{fmtS(monto)}</span>
        </li>
    );
}
