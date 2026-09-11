import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { PackageSearch, Plus, Trash2, Undo2 } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Callout from '@/Components/UI/Callout';
import Badge from '@/Components/UI/Badge';
import PagoForm, { MetodoPagoOption, CuentaOption } from '@/Components/PagoForm';
import AfectaCajaSelect, { TurnoLite } from '@/Components/AfectaCajaSelect';
import { calcularTotalVenta, redondear2 } from '@/lib/totalesVenta';

/**
 * Modificar el pedido PENDIENTE POR ENTREGAR de una venta días después.
 * Lo que se conserva mantiene su precio congelado; lo nuevo (o lo que aumenta)
 * va al precio de hoy. La diferencia se liquida HOY: la caja de la venta
 * original no se toca. Si sobra dinero, por defecto queda como saldo a favor.
 */

interface Pendiente {
    id: number;
    venta_item_id: number | null;
    producto_id: number;
    producto_unidad_id: number;
    producto_nombre: string;
    unidad_nombre: string;
    cantidad_pendiente: number;
    entregado: number;
    precio_unitario: number;
    precio_hoy: number;
    incluye_igv: boolean;
    modificable: boolean;
}

interface VentaItemLite { id: number; cantidad: number; precio_unitario: number; descuento_item: number; incluye_igv: boolean; }

interface Datos {
    bloqueo: string | null;
    venta: {
        id: number; numero: string; fecha_venta: string; es_credito: boolean;
        total: number; pagado: number; saldo_pendiente: number;
        descuento_total: number; tasa_igv: number;
        cliente: { id: number; nombre: string; es_cliente_general: boolean } | null;
    };
    venta_items: VentaItemLite[];
    pendientes: Pendiente[];
    anticipos_dinero: { id: number; fecha: string; saldo: string }[];
    turno_activo_id: number | null;
    metodos_pago: MetodoPagoOption[];
    cuentas: CuentaOption[];
    turnos: TurnoLite[];
}

interface UnidadProd { id: number; precio_venta: string | null; factor_conversion: string; es_base: boolean; activo?: boolean; unidad_medida?: { nombre: string } | null; }
interface ProductoBusqueda { id: number; nombre: string; codigo: string | null; precio_venta: string; incluye_igv: boolean; unidades: UnidadProd[]; stock_disponible: number | null; }

interface LineaNueva {
    key: string;
    producto_id: number;
    producto_nombre: string;
    incluye_igv: boolean;
    unidades: UnidadProd[];
    producto_unidad_id: number;
    cantidad: string;
    precio_unitario: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    ventaId: number | null;
}

const money = (v: number) => `S/ ${v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const r2 = redondear2;
const r4 = (x: number) => Math.round((x + Number.EPSILON) * 10000) / 10000;
const num = (s: string | number) => { const n = parseFloat(String(s)); return Number.isFinite(n) ? n : 0; };
const fmtCant = (n: number) => String(r4(n));

export default function ModalModificarPedido({ isOpen, onClose, ventaId }: Props) {
    const [datos, setDatos]       = useState<Datos | null>(null);
    const [cargando, setCargando] = useState(false);
    const [saving, setSaving]     = useState(false);
    const [errors, setErrors]     = useState<Record<string, string>>({});

    // Nuevo pendiente por línea existente (texto del input).
    const [cantidades, setCantidades] = useState<Record<number, string>>({});
    const [nuevas, setNuevas]         = useState<LineaNueva[]>([]);
    const [motivo, setMotivo]         = useState('');

    // Búsqueda de productos para agregar.
    const [q, setQ]                   = useState('');
    const [resultados, setResultados] = useState<ProductoBusqueda[]>([]);
    const [buscando, setBuscando]     = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Liquidación.
    const [usarAnticipo, setUsarAnticipo]   = useState(false);
    const [anticipoId, setAnticipoId]       = useState<number | ''>('');
    const [montoAnticipo, setMontoAnticipo] = useState('');
    const [montoPago, setMontoPago]         = useState('');
    const [dejarCredito, setDejarCredito]   = useState(false);
    const [destino, setDestino]             = useState<'saldo_favor' | 'devolver'>('saldo_favor');
    const [pago, setPago]                   = useState<{ metodo_pago_id: string; cuenta_id: string; referencia: string }>({ metodo_pago_id: '', cuenta_id: '', referencia: '' });
    const [turnoId, setTurnoId]             = useState<number | ''>('');
    const montoPagoTocado = useRef(false);

    useEffect(() => {
        if (!isOpen || !ventaId) return;
        setDatos(null); setErrors({}); setNuevas([]); setMotivo(''); setQ(''); setResultados([]);
        setUsarAnticipo(false); setAnticipoId(''); setMontoAnticipo(''); setMontoPago(''); setDejarCredito(false);
        setPago({ metodo_pago_id: '', cuenta_id: '', referencia: '' });
        montoPagoTocado.current = false;
        setCargando(true);
        axios.get<Datos>(route('ventas.pedido-pendiente', ventaId))
            .then(({ data }) => {
                setDatos(data);
                setCantidades(Object.fromEntries(data.pendientes.map(p => [p.id, fmtCant(p.cantidad_pendiente)])));
                setTurnoId(data.turno_activo_id ?? '');
                // Cliente General no puede quedarse con saldo a favor.
                setDestino(data.venta.cliente?.es_cliente_general ? 'devolver' : 'saldo_favor');
            })
            .catch(() => { toast.error('No se pudo cargar el pedido pendiente.'); onClose(); })
            .finally(() => setCargando(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isOpen, ventaId]);

    // Búsqueda con debounce contra el mismo buscador del POS.
    useEffect(() => {
        if (!isOpen || !ventaId) return;
        if (timer.current) clearTimeout(timer.current);
        const texto = q.trim();
        if (texto.length < 2) { setResultados([]); return; }
        timer.current = setTimeout(() => {
            setBuscando(true);
            axios.get<{ productos: ProductoBusqueda[] }>(route('pos.productos'), { params: { q: texto, venta_id: ventaId } })
                .then(({ data }) => setResultados(data.productos.slice(0, 8)))
                .catch(() => setResultados([]))
                .finally(() => setBuscando(false));
        }, 350);
    }, [q, isOpen, ventaId]);

    // ── Cálculo en vivo ───────────────────────────────────────────────
    const calc = useMemo(() => {
        if (!datos) return null;
        const reducciones: { id: number; cantidad_pendiente: number }[] = [];
        const excesos: { producto_id: number; producto_unidad_id: number; cantidad: number; precio_unitario: number; incluye_igv: boolean; nombre: string }[] = [];
        const cantidadVi: Record<number, number> = Object.fromEntries(datos.venta_items.map(v => [v.id, v.cantidad]));

        for (const p of datos.pendientes) {
            if (!p.modificable) continue;
            const nuevo = Math.max(0, num(cantidades[p.id] ?? p.cantidad_pendiente));
            if (nuevo < p.cantidad_pendiente - 0.00009) {
                reducciones.push({ id: p.id, cantidad_pendiente: r4(nuevo) });
                if (p.venta_item_id) cantidadVi[p.venta_item_id] = r4((cantidadVi[p.venta_item_id] ?? 0) - (p.cantidad_pendiente - nuevo));
            } else if (nuevo > p.cantidad_pendiente + 0.00009) {
                excesos.push({
                    producto_id: p.producto_id, producto_unidad_id: p.producto_unidad_id,
                    cantidad: r4(nuevo - p.cantidad_pendiente), precio_unitario: p.precio_hoy,
                    incluye_igv: p.incluye_igv, nombre: p.producto_nombre,
                });
            }
        }

        const agregadas = nuevas
            .map(n => ({ n, cantidad: num(n.cantidad), precio: num(n.precio_unitario) }))
            .filter(x => x.cantidad > 0);

        const lineas = [
            ...datos.venta_items.map(v => ({ precio: v.precio_unitario, desc: v.descuento_item, cantidad: Math.max(0, cantidadVi[v.id] ?? v.cantidad), incluyeIgv: v.incluye_igv })),
            ...excesos.map(e => ({ precio: e.precio_unitario, desc: 0, cantidad: e.cantidad, incluyeIgv: e.incluye_igv })),
            ...agregadas.map(a => ({ precio: a.precio, desc: 0, cantidad: a.cantidad, incluyeIgv: a.n.incluye_igv })),
        ];

        const totalNuevo = calcularTotalVenta(lineas, datos.venta.descuento_total, datos.venta.tasa_igv);
        const saldo = r2(totalNuevo - datos.venta.pagado);
        const hayCambios = reducciones.length > 0 || excesos.length > 0 || agregadas.length > 0;

        return { reducciones, excesos, agregadas, totalNuevo, saldo, hayCambios };
    }, [datos, cantidades, nuevas]);

    const falta = calc && calc.saldo > 0.009 ? calc.saldo : 0;
    const sobra = calc && calc.saldo < -0.009 ? r2(-calc.saldo) : 0;
    const esGeneral = !!datos?.venta.cliente?.es_cliente_general;

    const anticipoSel = datos?.anticipos_dinero.find(a => a.id === anticipoId);
    const tomaAnticipo = usarAnticipo && anticipoSel ? Math.min(num(montoAnticipo), num(anticipoSel.saldo), falta) : 0;
    const pagoAhora = num(montoPago);
    const resto = r2(Math.max(0, falta - tomaAnticipo - pagoAhora));

    // Precarga "Cobrar ahora" con lo que falta, mientras la cajera no lo edite.
    useEffect(() => {
        if (montoPagoTocado.current) return;
        const sugerido = r2(Math.max(0, falta - tomaAnticipo));
        setMontoPago(sugerido > 0 ? sugerido.toFixed(2) : '');
    }, [falta, tomaAnticipo]);

    function agregarProducto(p: ProductoBusqueda) {
        const unidades = (p.unidades ?? []).filter(u => u.activo !== false);
        const base = unidades.find(u => u.es_base) ?? unidades[0];
        if (!base) { toast.error('El producto no tiene presentaciones activas.'); return; }
        setNuevas(prev => [...prev, {
            key: Math.random().toString(36).slice(2),
            producto_id: p.id, producto_nombre: p.nombre, incluye_igv: !!p.incluye_igv,
            unidades, producto_unidad_id: base.id,
            cantidad: '1', precio_unitario: String(num(base.precio_venta ?? p.precio_venta)),
        }]);
        setQ(''); setResultados([]);
    }

    function setNueva(key: string, patch: Partial<LineaNueva>) {
        setNuevas(prev => prev.map(n => n.key === key ? { ...n, ...patch } : n));
    }

    // ── Validación antes de enviar ───────────────────────────────────
    const problemas: string[] = [];
    if (calc && !calc.hayCambios) problemas.push('Cambia alguna cantidad o agrega un producto.');
    if (motivo.trim().length < 5) problemas.push('Escribe el motivo (mínimo 5 caracteres).');
    if (falta > 0) {
        if (usarAnticipo && (!anticipoId || tomaAnticipo <= 0)) problemas.push('Indica cuánto se toma del anticipo.');
        if (usarAnticipo && anticipoSel && num(montoAnticipo) > num(anticipoSel.saldo) + 0.009) problemas.push('El monto supera el saldo del anticipo.');
        if (tomaAnticipo + pagoAhora > falta + 0.009) problemas.push(`Lo cobrado supera lo que falta pagar (${money(falta)}).`);
        if (pagoAhora > 0 && !pago.metodo_pago_id) problemas.push('Elige el método del cobro.');
        if (resto > 0.009 && (esGeneral || (!datos?.venta.es_credito && !dejarCredito))) {
            problemas.push(esGeneral ? `Faltan ${money(resto)}: a «Cliente General» no se le puede dejar crédito.` : `Faltan ${money(resto)}: cóbralos o marca «Dejar el resto al crédito».`);
        }
    }
    if (sobra > 0 && destino === 'devolver' && !pago.metodo_pago_id) problemas.push('Elige por dónde se devuelve el dinero.');

    function enviar() {
        if (!datos || !calc || problemas.length > 0) return;
        setSaving(true); setErrors({});
        const nuevos = [
            ...calc.excesos.map(e => ({ producto_id: e.producto_id, producto_unidad_id: e.producto_unidad_id, cantidad: e.cantidad, precio_unitario: e.precio_unitario })),
            ...calc.agregadas.map(a => ({ producto_id: a.n.producto_id, producto_unidad_id: a.n.producto_unidad_id, cantidad: a.cantidad, precio_unitario: a.precio })),
        ];
        const moverDinero = (falta > 0 && pagoAhora > 0) || (sobra > 0 && destino === 'devolver');
        router.post(route('ventas.modificar-pedido', datos.venta.id), {
            motivo: motivo.trim(),
            items: calc.reducciones,
            nuevos,
            cobro_anticipo_id:    falta > 0 && usarAnticipo ? (anticipoId || null) : null,
            cobro_monto_anticipo: falta > 0 && usarAnticipo ? tomaAnticipo : null,
            cobro_monto_pago:     falta > 0 ? pagoAhora : null,
            dejar_credito:        falta > 0 && resto > 0.009 ? dejarCredito : false,
            excedente_destino:    sobra > 0 ? destino : null,
            metodo_pago_id:       moverDinero ? (pago.metodo_pago_id || null) : null,
            cuenta_id:            moverDinero ? (pago.cuenta_id || null) : null,
            referencia:           moverDinero ? (pago.referencia || null) : null,
            turno_id:             moverDinero ? (turnoId || null) : null,
        } as any, {
            preserveScroll: true,
            onSuccess: () => { setSaving(false); onClose(); },
            onError: (errs: any) => {
                setSaving(false); setErrors(errs);
                const first = Object.values(errs)[0];
                toast.error(typeof first === 'string' ? first : 'Revisa los datos.');
            },
        });
    }

    const pagoFormBlock = (
        <div className="space-y-3">
            <PagoForm
                value={pago}
                onChange={v => setPago({ metodo_pago_id: v.metodo_pago_id ? String(v.metodo_pago_id) : '', cuenta_id: v.cuenta_id ? String(v.cuenta_id) : '', referencia: v.referencia ?? '' })}
                metodosPago={datos?.metodos_pago ?? []}
                cuentas={datos?.cuentas ?? []}
                errors={errors}
                required
                showObservacion={false}
            />
            <AfectaCajaSelect
                modulo="cxc" modo="libre" formato="largo"
                label="Caja de hoy que mueve el dinero"
                sinTurnoLabel="Sin turno (no afecta caja)"
                turnos={datos?.turnos ?? []}
                value={turnoId}
                onChange={setTurnoId}
                error={errors.turno_id}
                hint="Por defecto, tu turno abierto. La caja del día de la venta no se toca."
            />
        </div>
    );

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            size="4xl"
            title={datos ? `Modificar pedido — venta ${datos.venta.numero}` : 'Modificar pedido'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={saving}>Cancelar</Button>
                    <Button onClick={enviar} loading={saving} disabled={!datos || !!datos.bloqueo || problemas.length > 0}>
                        {falta > 0 && (tomaAnticipo + pagoAhora) > 0 ? `Guardar y cobrar ${money(r2(tomaAnticipo + pagoAhora))}`
                            : sobra > 0 ? (destino === 'devolver' ? `Guardar y devolver ${money(sobra)}` : `Guardar (${money(sobra)} a favor)`)
                            : 'Guardar modificación'}
                    </Button>
                </>
            }
        >
            {cargando || !datos || !calc ? (
                <p className="text-sm text-center py-10" style={{ color: 'var(--color-text-muted)' }}>Cargando pedido pendiente…</p>
            ) : datos.bloqueo ? (
                <Callout variant="danger" title="No se puede modificar este pedido">{datos.bloqueo}</Callout>
            ) : (
                <div className="space-y-5">
                    <Callout variant="info" title={`${datos.venta.cliente?.nombre ?? 'Cliente'} · venta del ${new Date(datos.venta.fecha_venta).toLocaleDateString('es-PE')}`}>
                        Solo cambia lo que <strong>aún no se entregó</strong>. Lo que se conserva mantiene su precio; lo nuevo o lo que aumenta va al <strong>precio de hoy</strong>.
                        La diferencia se liquida hoy: la caja del día de la venta no se toca.
                    </Callout>

                    {/* ── Pendiente actual ─────────────────────────────── */}
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wider mb-1.5" style={{ color: 'var(--color-text-muted)' }}>Pendiente por entregar</p>
                        <div className="rounded-xl overflow-x-auto" style={{ border: '1px solid var(--color-border)' }}>
                            <table className="w-full text-sm min-w-[560px]">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                        {['Producto', 'Pendiente', 'Nuevo pendiente', 'Precio', 'Importe', ''].map(h => (
                                            <th key={h} className="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {datos.pendientes.map((p, idx) => {
                                        const nuevo = Math.max(0, num(cantidades[p.id] ?? p.cantidad_pendiente));
                                        const exceso = r4(nuevo - p.cantidad_pendiente);
                                        const conservado = Math.min(nuevo, p.cantidad_pendiente);
                                        const importe = r2(conservado * p.precio_unitario + Math.max(0, exceso) * p.precio_hoy);
                                        const cambiado = Math.abs(exceso) > 0.00009;
                                        return (
                                            <tr key={p.id} style={{ borderBottom: idx < datos.pendientes.length - 1 ? '1px solid var(--color-border)' : undefined }}>
                                                <td className="px-3 py-2">
                                                    <span className="font-medium" style={{ color: 'var(--color-text)' }}>{p.producto_nombre}</span>
                                                    <span className="block text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                                        {p.unidad_nombre}{p.entregado > 0 ? ` · ya entregado ${fmtCant(p.entregado)}` : ''}
                                                    </span>
                                                    {!p.modificable && <span className="block text-[11px]" style={{ color: 'var(--color-warning)' }}>Cambio de producto antiguo: usa «Cancelar pendiente» en Anticipos</span>}
                                                </td>
                                                <td className="px-3 py-2 tabular-nums">{fmtCant(p.cantidad_pendiente)}</td>
                                                <td className="px-3 py-2 w-40">
                                                    <Input type="number" min="0" step="any" value={cantidades[p.id] ?? ''} disabled={!p.modificable || saving}
                                                        onChange={e => setCantidades(c => ({ ...c, [p.id]: e.target.value }))} />
                                                    {exceso > 0.00009 && (
                                                        <span className="block text-[11px] mt-1" style={{ color: 'var(--color-primary)' }}>
                                                            +{fmtCant(exceso)} al precio de hoy {money(p.precio_hoy)}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 tabular-nums">
                                                    {money(p.precio_unitario)}
                                                    {Math.abs(p.precio_hoy - p.precio_unitario) > 0.005 && (
                                                        <span className="block text-[11px]" style={{ color: 'var(--color-text-muted)' }}>hoy {money(p.precio_hoy)}</span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 tabular-nums font-semibold">{money(importe)}</td>
                                                <td className="px-3 py-2 text-right whitespace-nowrap">
                                                    {p.modificable && (cambiado ? (
                                                        <button type="button" className="p-1.5 rounded-lg hover:bg-black/5" title="Deshacer"
                                                            onClick={() => setCantidades(c => ({ ...c, [p.id]: fmtCant(p.cantidad_pendiente) }))}
                                                            style={{ color: 'var(--color-text-muted)' }}><Undo2 size={14} /></button>
                                                    ) : (
                                                        <button type="button" className="p-1.5 rounded-lg hover:bg-black/5" title="Quitar del pedido"
                                                            onClick={() => setCantidades(c => ({ ...c, [p.id]: '0' }))}
                                                            style={{ color: 'var(--color-danger)' }}><Trash2 size={14} /></button>
                                                    ))}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* ── Agregar productos ────────────────────────────── */}
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wider mb-1.5" style={{ color: 'var(--color-text-muted)' }}>Agregar productos (precio de hoy)</p>
                        <div className="relative">
                            <Input value={q} onChange={e => setQ(e.target.value)} placeholder="Buscar producto por nombre o código…" disabled={saving} />
                            {(resultados.length > 0 || buscando) && (
                                <div className="absolute z-20 left-0 right-0 mt-1 rounded-xl overflow-hidden shadow-lg"
                                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                                    {buscando && <p className="px-3 py-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>Buscando…</p>}
                                    {resultados.map(p => (
                                        <button key={p.id} type="button" onClick={() => agregarProducto(p)}
                                            className="w-full flex items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-black/5">
                                            <span className="flex items-center gap-2 min-w-0">
                                                <PackageSearch size={14} style={{ color: 'var(--color-primary)' }} />
                                                <span className="truncate">{p.nombre}</span>
                                            </span>
                                            <span className="text-xs whitespace-nowrap" style={{ color: 'var(--color-text-muted)' }}>
                                                {money(num(p.precio_venta))}{p.stock_disponible !== null ? ` · stock ${fmtCant(p.stock_disponible)}` : ''}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {nuevas.length > 0 && (
                            <div className="mt-2 space-y-2">
                                {nuevas.map(n => (
                                    <div key={n.key} className="grid grid-cols-12 gap-2 items-end rounded-xl p-2" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                        <div className="col-span-12 sm:col-span-4 text-sm font-medium flex items-center gap-2 self-center">
                                            <Plus size={13} style={{ color: 'var(--color-success)' }} />{n.producto_nombre}
                                        </div>
                                        <div className="col-span-6 sm:col-span-3">
                                            <Select label="Presentación" value={n.producto_unidad_id}
                                                onChange={v => {
                                                    const u = n.unidades.find(x => x.id === Number(v));
                                                    setNueva(n.key, { producto_unidad_id: Number(v), precio_unitario: u?.precio_venta ? String(num(u.precio_venta)) : n.precio_unitario });
                                                }}
                                                options={n.unidades.map(u => ({ value: u.id, label: u.unidad_medida?.nombre ?? `x${num(u.factor_conversion)}` }))} />
                                        </div>
                                        <div className="col-span-3 sm:col-span-2">
                                            <Input label="Cant." type="number" min="0" step="any" value={n.cantidad} onChange={e => setNueva(n.key, { cantidad: e.target.value })} />
                                        </div>
                                        <div className="col-span-3 sm:col-span-2">
                                            <Input label="Precio" type="number" min="0" step="0.01" value={n.precio_unitario} onChange={e => setNueva(n.key, { precio_unitario: e.target.value })} />
                                        </div>
                                        <div className="col-span-12 sm:col-span-1 flex justify-end">
                                            <button type="button" className="p-2 rounded-lg hover:bg-black/5" title="Quitar" style={{ color: 'var(--color-danger)' }}
                                                onClick={() => setNuevas(prev => prev.filter(x => x.key !== n.key))}><Trash2 size={15} /></button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* ── Resumen y liquidación ────────────────────────── */}
                    {calc.hayCambios && (
                        <div className="space-y-3">
                            <div className="rounded-xl p-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                                <div><p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>Total anterior</p><p className="font-semibold tabular-nums">{money(datos.venta.total)}</p></div>
                                <div><p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>Total nuevo</p><p className="font-bold tabular-nums" style={{ color: 'var(--color-primary)' }}>{money(calc.totalNuevo)}</p></div>
                                <div><p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>Ya pagado</p><p className="font-semibold tabular-nums" style={{ color: 'var(--color-success)' }}>{money(datos.venta.pagado)}</p></div>
                                <div>
                                    <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>{falta > 0 ? 'Falta pagar' : sobra > 0 ? 'Sobra a favor' : 'Diferencia'}</p>
                                    <p className="font-bold tabular-nums" style={{ color: falta > 0 ? 'var(--color-danger)' : sobra > 0 ? 'var(--color-warning)' : 'var(--color-text)' }}>
                                        {money(falta > 0 ? falta : sobra)}
                                    </p>
                                </div>
                            </div>

                            {falta > 0 && (
                                <div className="space-y-3">
                                    {datos.anticipos_dinero.length > 0 && (
                                        <Callout variant="info" title="El cliente tiene anticipos de dinero"
                                            aside={money(datos.anticipos_dinero.reduce((s, a) => s + num(a.saldo), 0))}>
                                            <label className="flex items-center gap-2 text-sm cursor-pointer select-none">
                                                <input type="checkbox" checked={usarAnticipo} className="h-4 w-4 accent-[var(--color-primary)]"
                                                    onChange={e => {
                                                        const on = e.target.checked;
                                                        setUsarAnticipo(on); montoPagoTocado.current = false;
                                                        const unico = on && datos.anticipos_dinero.length === 1 ? datos.anticipos_dinero[0] : null;
                                                        setAnticipoId(unico ? unico.id : '');
                                                        setMontoAnticipo(unico ? Math.min(num(unico.saldo), falta).toFixed(2) : '');
                                                    }} />
                                                <span>Cobrar consumiendo su anticipo (sin mover caja)</span>
                                            </label>
                                            {usarAnticipo && (
                                                <div className="grid grid-cols-2 gap-2 mt-2">
                                                    <Select label="Anticipo" value={anticipoId === '' ? '' : anticipoId}
                                                        onChange={v => {
                                                            const a = datos.anticipos_dinero.find(x => x.id === Number(v));
                                                            setAnticipoId(v === '' ? '' : Number(v)); montoPagoTocado.current = false;
                                                            setMontoAnticipo(a ? Math.min(num(a.saldo), falta).toFixed(2) : '');
                                                        }}
                                                        placeholder="— Seleccionar —"
                                                        options={datos.anticipos_dinero.map(a => ({ value: a.id, label: `#${a.id} — saldo ${money(num(a.saldo))}` }))} />
                                                    <Input label="Tomar del anticipo" type="number" min="0" step="0.01" value={montoAnticipo}
                                                        onChange={e => { setMontoAnticipo(e.target.value); montoPagoTocado.current = false; }}
                                                        error={errors.cobro_monto_anticipo} />
                                                </div>
                                            )}
                                        </Callout>
                                    )}

                                    <div className="rounded-xl p-3 space-y-3" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                        <Input label="Cobrar ahora (S/)" type="number" min="0" step="0.01" value={montoPago}
                                            onChange={e => { montoPagoTocado.current = true; setMontoPago(e.target.value); }}
                                            error={errors.cobro_monto_pago}
                                            hint={`Falta ${money(r2(Math.max(0, falta - tomaAnticipo)))}${tomaAnticipo > 0 ? ' después del anticipo' : ''}`} />
                                        {pagoAhora > 0 && pagoFormBlock}
                                    </div>

                                    {resto > 0.009 && (esGeneral || !datos.venta.es_credito) && (
                                        esGeneral ? (
                                            <Callout variant="danger">Quedan {money(resto)} sin cobrar y la venta es de «Cliente General»: cobra la diferencia completa.</Callout>
                                        ) : (
                                            <label className="flex items-center gap-2 text-sm cursor-pointer select-none">
                                                <input type="checkbox" checked={dejarCredito} onChange={e => setDejarCredito(e.target.checked)} className="h-4 w-4 accent-[var(--color-primary)]" />
                                                <span>Dejar el resto (<strong>{money(resto)}</strong>) al crédito — aparecerá en Cuentas por Cobrar</span>
                                            </label>
                                        )
                                    )}
                                </div>
                            )}

                            {sobra > 0 && (
                                <div className="rounded-xl p-3 space-y-3" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                    <p className="text-sm font-semibold">Sobran {money(sobra)}: ¿qué hacemos con ese dinero?</p>
                                    <label className={`flex items-start gap-2 text-sm select-none ${esGeneral ? 'opacity-50' : 'cursor-pointer'}`}>
                                        <input type="radio" name="destino" checked={destino === 'saldo_favor'} disabled={esGeneral} onChange={() => setDestino('saldo_favor')} className="mt-0.5 accent-[var(--color-primary)]" />
                                        <span><strong>Dejar como saldo a favor</strong> del cliente (recomendado): no sale dinero de caja; lo usa en otra compra o para pagar deudas.
                                            {esGeneral && <span className="block text-xs" style={{ color: 'var(--color-danger)' }}>No disponible para «Cliente General».</span>}
                                        </span>
                                    </label>
                                    <label className="flex items-start gap-2 text-sm cursor-pointer select-none">
                                        <input type="radio" name="destino" checked={destino === 'devolver'} onChange={() => setDestino('devolver')} className="mt-0.5 accent-[var(--color-primary)]" />
                                        <span><strong>Devolver el dinero ahora</strong>: sale de la caja de hoy.</span>
                                    </label>
                                    {destino === 'devolver' && pagoFormBlock}
                                    {errors.excedente_destino && <p className="text-xs" style={{ color: 'var(--color-danger)' }}>{errors.excedente_destino}</p>}
                                </div>
                            )}

                            {/* Desglose final explícito */}
                            <div className="rounded-xl p-3 space-y-1.5 text-sm" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                                {calc.reducciones.length + calc.excesos.length + calc.agregadas.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5 pb-1.5" style={{ borderBottom: '1px solid var(--color-border)' }}>
                                        {calc.reducciones.length > 0 && <Badge variant="warning">{calc.reducciones.length} línea(s) reducida(s)</Badge>}
                                        {calc.excesos.length + calc.agregadas.length > 0 && <Badge variant="primary">{calc.excesos.length + calc.agregadas.length} producto(s) agregado(s)</Badge>}
                                    </div>
                                )}
                                {falta > 0 ? (
                                    <>
                                        {tomaAnticipo > 0 && <Fila label="Del anticipo (sin caja)" valor={money(tomaAnticipo)} />}
                                        {pagoAhora > 0 && <Fila label={`Cobrado hoy${pago.metodo_pago_id ? ` (${datos.metodos_pago.find(m => String(m.id) === pago.metodo_pago_id)?.nombre ?? ''})` : ''} — entra a caja`} valor={money(pagoAhora)} />}
                                        <Fila label="La venta queda" valor={resto > 0.009 ? `debiendo ${money(resto)}` : 'PAGADA ✓'} color={resto > 0.009 ? 'var(--color-danger)' : 'var(--color-success)'} />
                                    </>
                                ) : sobra > 0 ? (
                                    <Fila label={destino === 'devolver' ? 'Se devuelve hoy desde caja' : 'Queda a favor del cliente (anticipo)'} valor={money(sobra)} color="var(--color-warning)" />
                                ) : (
                                    <Fila label="Diferencia de dinero" valor="Ninguna — solo cambian los productos" />
                                )}
                            </div>
                        </div>
                    )}

                    <Input label="Motivo de la modificación" required value={motivo} onChange={e => setMotivo(e.target.value)}
                        placeholder="Ej: el cliente cambió 2 fierros por cemento" error={errors.motivo} />

                    {problemas.length > 0 && calc.hayCambios && (
                        <ul className="text-xs list-disc list-inside" style={{ color: 'var(--color-text-muted)' }}>
                            {problemas.map(p => <li key={p}>{p}</li>)}
                        </ul>
                    )}
                </div>
            )}
        </Modal>
    );
}

function Fila({ label, valor, color }: { label: string; valor: string; color?: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span style={{ color: 'var(--color-text-muted)' }}>{label}</span>
            <span className="font-semibold tabular-nums text-right" style={{ color: color ?? 'var(--color-text)' }}>{valor}</span>
        </div>
    );
}
