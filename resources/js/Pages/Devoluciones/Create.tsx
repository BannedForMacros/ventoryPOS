import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import AfectaCajaSelect from '@/Components/AfectaCajaSelect';
import Badge from '@/Components/UI/Badge';
import type { MetodoPago, PageProps } from '@/types';

interface Motivo { id: number; nombre: string; afecta_restock_default: 'permite' | 'impide' | 'obliga_merma' }

interface VentaItem {
    id: number;
    producto_id: number;
    producto_nombre: string;
    unidad_nombre: string;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    es_retornable: boolean;
    cantidad_devuelta: number;
    cantidad_disponible: number;
}

interface VentaResultado {
    id: number;
    numero: string;
    fecha_venta: string;
    total: number;
    cliente: { id: number; nombre_completo: string; numero_documento: string | null } | null;
    local: { id: number; nombre: string };
    pagos: { metodo_pago_id: number; metodo_pago_nombre: string; metodo_pago_tipo: string; monto: number }[];
    items: VentaItem[];
}

interface ConfigDev {
    permite_devoluciones: boolean;
    dias_max_devolucion: number;
    requiere_aprobacion: boolean;
    restock_default: boolean;
    dentro_del_plazo: boolean;
}

interface CuentaMetodo { cuenta_metodo_pago_id: number; nombre: string; }
interface MetodoPagoDev { id: number; nombre: string; tipo_id: number; tipo_slug: string | null; cuentas: CuentaMetodo[]; }
interface TurnoLite { id: number; caja_id: number; fecha_apertura: string; user?: { id: number; name: string } | null; caja?: { id: number; nombre: string } | null; }

interface Props extends PageProps {
    motivos: Motivo[];
    metodosPago: MetodoPagoDev[];
    turnoActivo: { id: number; caja_id: number; caja: { nombre: string } } | null;
    turnos: TurnoLite[];
    esAdmin: boolean;
}

interface ItemSeleccionado {
    venta_item_id: number;
    cantidad: string;
    estado_producto: 'bueno' | 'defectuoso' | 'vencido' | 'dañado';
    restock: boolean;
    motivo_id: number | '';
    observacion: string;
}

interface PagoRow {
    metodo_pago_id: number | '';
    cuenta_metodo_pago_id: number | '';
    monto: string;
    referencia: string;
}

export default function DevolucionCreate({ motivos, metodosPago, turnoActivo, turnos }: Props) {
    const [turnoAfecta, setTurnoAfecta] = useState<number | ''>(turnoActivo?.id ?? (turnos.length === 1 ? turnos[0].id : ''));
    const [busqueda, setBusqueda] = useState('');
    const [buscando, setBuscando] = useState(false);
    const [venta, setVenta] = useState<VentaResultado | null>(null);
    const [config, setConfig] = useState<ConfigDev | null>(null);

    const [motivoId, setMotivoId] = useState<number | ''>('');
    const [formaReembolso, setFormaReembolso] = useState<string>('efectivo');
    const [observacion, setObservacion] = useState('');
    const [items, setItems] = useState<Record<number, ItemSeleccionado>>({});
    const [pagos, setPagos] = useState<PagoRow[]>([{ metodo_pago_id: '', cuenta_metodo_pago_id: '', monto: '', referencia: '' }]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    async function buscar() {
        if (!busqueda.trim()) return;
        setBuscando(true);
        setVenta(null); setConfig(null); setItems({});
        try {
            const { data } = await axios.get(route('devoluciones.buscar-venta'), { params: { q: busqueda.trim() } });
            setVenta(data.venta);
            setConfig(data.configuracion);
            if (!data.configuracion.permite_devoluciones) {
                toast.error('Las devoluciones están deshabilitadas para este local.');
            }
            if (!data.configuracion.dentro_del_plazo) {
                toast.error('La venta excede el plazo permitido para devoluciones.');
            }
        } catch (e) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.error ?? 'Error al buscar') : 'Error';
            toast.error(msg);
        } finally {
            setBuscando(false);
        }
    }

    function toggleItem(it: VentaItem, marcar: boolean) {
        setItems(prev => {
            const next = { ...prev };
            if (marcar) {
                next[it.id] = {
                    venta_item_id:   it.id,
                    cantidad:        String(it.cantidad_disponible),
                    estado_producto: 'bueno',
                    restock:         it.es_retornable && (config?.restock_default ?? true),
                    motivo_id:       '',
                    observacion:     '',
                };
            } else {
                delete next[it.id];
            }
            return next;
        });
    }

    function setItemField(id: number, field: keyof ItemSeleccionado, value: unknown) {
        setItems(prev => ({ ...prev, [id]: { ...prev[id], [field]: value } as ItemSeleccionado }));
    }

    const totalDevolucion = Object.values(items).reduce((sum, i) => {
        const it = venta?.items.find(v => v.id === i.venta_item_id);
        if (!it) return sum;
        const cant = parseFloat(i.cantidad) || 0;
        return sum + cant * it.precio_unitario;
    }, 0);

    const totalReembolso = pagos.reduce((s, p) => s + (parseFloat(p.monto) || 0), 0);

    function addPago()    { setPagos(p => [...p, { metodo_pago_id: '', cuenta_metodo_pago_id: '', monto: '', referencia: '' }]); }
    // Cuentas del método elegido (para el selector de cuenta del reembolso).
    const cuentasDe = (mid: number | '') => (metodosPago.find(m => m.id === mid)?.cuentas ?? []);
    function removePago(i: number) { setPagos(p => p.filter((_, idx) => idx !== i)); }

    function submit() {
        if (!venta || Object.keys(items).length === 0) {
            toast.error('Selecciona al menos un producto a devolver.');
            return;
        }
        if (!motivoId) {
            toast.error('Selecciona un motivo.');
            return;
        }

        setProcessing(true);
        router.post(route('devoluciones.store'), {
            venta_id:        venta.id,
            motivo_id:       motivoId,
            forma_reembolso: formaReembolso,
            observacion,
            items: Object.values(items).map(i => ({
                venta_item_id:   i.venta_item_id,
                cantidad:        parseFloat(i.cantidad) || 0,
                estado_producto: i.estado_producto,
                restock:         i.restock,
                motivo_id:       i.motivo_id || null,
                observacion:     i.observacion,
            })),
            turno_id: turnoAfecta || null,
            pagos: formaReembolso === 'sin_reembolso' || formaReembolso === 'vale_credito'
                ? []
                : pagos.filter(p => p.metodo_pago_id && parseFloat(p.monto) > 0).map(p => ({
                    metodo_pago_id:        p.metodo_pago_id,
                    cuenta_metodo_pago_id: p.cuenta_metodo_pago_id || null,
                    monto:                 parseFloat(p.monto),
                    referencia:            p.referencia || null,
                })),
        }, {
            onSuccess: () => setProcessing(false),
            onError:   (e) => { setErrors(e); setProcessing(false); },
        });
    }

    const requierePagos = formaReembolso !== 'sin_reembolso' && formaReembolso !== 'vale_credito';

    return (
        <AppLayout title="Nueva devolución">
            <PageHeader
                title="Nueva devolución"
                subtitle="Busca la venta original y selecciona los productos a devolver"
                backHref={route('devoluciones.index')}
            />

            <div className="space-y-6 max-w-6xl">
                {/* Afecta caja a: el turno cuya caja recibe el reembolso (modo forzado:
                    el cajero se imputa a su turno; el admin elige). Se auto-oculta si la
                    empresa apaga el módulo 'devoluciones'. */}
                <AfectaCajaSelect
                    modulo="devoluciones" modo="forzado" formato="corto"
                    turnos={turnos}
                    value={turnoAfecta}
                    onChange={setTurnoAfecta}
                    error={errors.turno_id}
                    hint='El reembolso en efectivo saldrá de la caja de este turno. Si eliges "Sin turno", queda registrado pero no afecta ninguna caja.'
                />

                {/* Buscador */}
                <section className="rounded-2xl border p-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h2 className="text-sm font-semibold uppercase tracking-wide mb-3" style={{ color: 'var(--color-text-muted)' }}>
                        Buscar venta
                    </h2>
                    <div className="flex gap-2">
                        <div className="flex-1">
                            <Input
                                placeholder="Número de venta o ID"
                                value={busqueda}
                                onChange={e => setBusqueda(e.target.value)}
                                onKeyDown={e => { if (e.key === 'Enter') buscar(); }}
                            />
                        </div>
                        <Button onClick={buscar} disabled={buscando}>
                            <Search size={14} className="mr-1" />{buscando ? 'Buscando...' : 'Buscar'}
                        </Button>
                    </div>
                </section>

                {/* Datos de la venta */}
                {venta && config && (
                    <>
                        <section className="rounded-2xl border p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <Field label="N° venta" value={venta.numero} mono />
                            <Field label="Fecha" value={new Date(venta.fecha_venta).toLocaleString('es-PE')} />
                            <Field label="Cliente" value={venta.cliente?.nombre_completo ?? '—'} />
                            <Field label="Total" value={`S/ ${venta.total.toFixed(2)}`} />

                            {!config.permite_devoluciones && (
                                <div className="col-span-full">
                                    <Badge variant="danger">Las devoluciones están deshabilitadas</Badge>
                                </div>
                            )}
                            {!config.dentro_del_plazo && (
                                <div className="col-span-full">
                                    <Badge variant="warning">Fuera del plazo (máx. {config.dias_max_devolucion} días)</Badge>
                                </div>
                            )}
                            {config.requiere_aprobacion && (
                                <div className="col-span-full">
                                    <Badge variant="primary">Esta devolución requerirá aprobación de un administrador</Badge>
                                </div>
                            )}
                        </section>

                        {/* Items */}
                        <section className="rounded-2xl border p-5"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                                Productos a devolver
                            </h3>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead style={{ color: 'var(--color-text-muted)' }}>
                                        <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                            <th className="text-left py-2 px-2 font-medium w-8"></th>
                                            <th className="text-left py-2 px-2 font-medium">Producto</th>
                                            <th className="text-right py-2 px-2 font-medium">Vendido</th>
                                            <th className="text-right py-2 px-2 font-medium">Devuelto</th>
                                            <th className="text-right py-2 px-2 font-medium">Disponible</th>
                                            <th className="text-right py-2 px-2 font-medium">Devolver</th>
                                            <th className="text-left py-2 px-2 font-medium">Estado</th>
                                            <th className="text-center py-2 px-2 font-medium">Restock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {venta.items.map(it => {
                                            const sel = items[it.id];
                                            const noRetornable = !it.es_retornable;
                                            const sinDisponible = it.cantidad_disponible <= 0;
                                            return (
                                                <tr key={it.id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                                    <td className="py-2 px-2">
                                                        <input type="checkbox"
                                                            disabled={sinDisponible || noRetornable}
                                                            checked={!!sel}
                                                            onChange={e => toggleItem(it, e.target.checked)}
                                                            className="accent-[var(--color-primary)]" />
                                                    </td>
                                                    <td className="py-2 px-2">
                                                        <div className="font-medium" style={{ color: 'var(--color-text)' }}>{it.producto_nombre}</div>
                                                        <div className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                            {it.unidad_nombre} · S/ {it.precio_unitario.toFixed(2)}
                                                            {noRetornable && <span className="ml-2"><Badge variant="danger">No retornable</Badge></span>}
                                                        </div>
                                                    </td>
                                                    <td className="py-2 px-2 text-right tabular-nums">{it.cantidad.toFixed(2)}</td>
                                                    <td className="py-2 px-2 text-right tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                                        {it.cantidad_devuelta.toFixed(2)}
                                                    </td>
                                                    <td className="py-2 px-2 text-right tabular-nums">
                                                        <span style={{ color: sinDisponible ? 'var(--color-text-muted)' : 'var(--color-success)' }}>
                                                            {it.cantidad_disponible.toFixed(2)}
                                                        </span>
                                                    </td>
                                                    <td className="py-2 px-2 w-28">
                                                        <input type="number" step="0.0001" min="0" max={it.cantidad_disponible}
                                                            disabled={!sel}
                                                            value={sel?.cantidad ?? ''}
                                                            onChange={e => setItemField(it.id, 'cantidad', e.target.value)}
                                                            className="w-full rounded-lg border px-2 py-1 text-sm text-right"
                                                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                                    </td>
                                                    <td className="py-2 px-2 w-32">
                                                        <Select
                                                            value={sel?.estado_producto ?? 'bueno'}
                                                            onChange={v => setItemField(it.id, 'estado_producto', v)}
                                                            disabled={!sel}
                                                            options={[
                                                                { value: 'bueno',      label: 'Bueno' },
                                                                { value: 'defectuoso', label: 'Defectuoso' },
                                                                { value: 'vencido',    label: 'Vencido' },
                                                                { value: 'dañado',     label: 'Dañado' },
                                                            ]}
                                                        />
                                                    </td>
                                                    <td className="py-2 px-2 text-center">
                                                        <input type="checkbox"
                                                            disabled={!sel || noRetornable}
                                                            checked={sel?.restock ?? false}
                                                            onChange={e => setItemField(it.id, 'restock', e.target.checked)}
                                                            className="accent-[var(--color-primary)]" />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            {errors.items && <p className="text-xs mt-2" style={{ color: 'var(--color-danger)' }}>{errors.items}</p>}
                        </section>

                        {/* Motivo + reembolso */}
                        <section className="rounded-2xl border p-5 space-y-4"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <h3 className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Motivo y reembolso</h3>

                            <div className="grid grid-cols-2 gap-4">
                                <Select
                                    label="Motivo general"
                                    required
                                    value={motivoId}
                                    onChange={v => setMotivoId(v === '' ? '' : Number(v))}
                                    options={motivos.map(m => ({ value: m.id, label: m.nombre }))}
                                    error={errors.motivo_id}
                                />
                                <Select
                                    label="Forma de reembolso"
                                    required
                                    value={formaReembolso}
                                    onChange={v => setFormaReembolso(String(v))}
                                    options={[
                                        { value: 'efectivo',        label: 'Efectivo' },
                                        { value: 'mismo_metodo',    label: 'Mismo método de pago' },
                                        { value: 'vale_credito',    label: 'Vale / Crédito a favor' },
                                        { value: 'cambio_producto', label: 'Cambio por otro producto' },
                                        { value: 'sin_reembolso',   label: 'Sin reembolso (queda registro)' },
                                    ]}
                                />
                            </div>

                            <Input label="Observación general" value={observacion} onChange={e => setObservacion(e.target.value)} />

                            {requierePagos && (
                                <div>
                                    <div className="flex items-center justify-between mb-2">
                                        <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Métodos del reembolso</p>
                                        <Button type="button" variant="ghost" onClick={addPago}>+ Agregar</Button>
                                    </div>
                                    {pagos.map((p, i) => {
                                        const cuentas = cuentasDe(p.metodo_pago_id);
                                        return (
                                        <div key={i} className="grid grid-cols-12 gap-2 mb-2">
                                            <div className="col-span-4">
                                                <Select
                                                    placeholder="Método"
                                                    value={p.metodo_pago_id}
                                                    onChange={v => {
                                                        const mid = v === '' ? '' : Number(v);
                                                        const cts = cuentasDe(mid);
                                                        setPagos(prev => prev.map((row, idx) => idx === i ? { ...row, metodo_pago_id: mid, cuenta_metodo_pago_id: cts.length === 1 ? cts[0].cuenta_metodo_pago_id : '' } : row));
                                                    }}
                                                    options={metodosPago.map(m => ({ value: m.id, label: m.nombre }))}
                                                />
                                            </div>
                                            <div className="col-span-3">
                                                {cuentas.length > 0 ? (
                                                    <Select
                                                        placeholder="Cuenta"
                                                        value={p.cuenta_metodo_pago_id}
                                                        onChange={v => setPagos(prev => prev.map((row, idx) => idx === i ? { ...row, cuenta_metodo_pago_id: v === '' ? '' : Number(v) } : row))}
                                                        options={cuentas.map(c => ({ value: c.cuenta_metodo_pago_id, label: c.nombre }))}
                                                    />
                                                ) : (
                                                    <div className="text-xs px-1 py-2" style={{ color: 'var(--color-text-muted)' }}>Cuenta automática</div>
                                                )}
                                            </div>
                                            <div className="col-span-2">
                                                <input type="number" step="0.01" min="0" placeholder="Monto"
                                                    value={p.monto}
                                                    onChange={e => setPagos(prev => prev.map((row, idx) => idx === i ? { ...row, monto: e.target.value } : row))}
                                                    className="w-full rounded-xl border px-3 py-2 text-sm text-right"
                                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                            </div>
                                            <div className="col-span-2">
                                                <input type="text" placeholder="Ref. (opc.)"
                                                    value={p.referencia}
                                                    onChange={e => setPagos(prev => prev.map((row, idx) => idx === i ? { ...row, referencia: e.target.value } : row))}
                                                    className="w-full rounded-xl border px-3 py-2 text-sm"
                                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                                            </div>
                                            <div className="col-span-1">
                                                {pagos.length > 1 && (
                                                    <button type="button" onClick={() => removePago(i)}
                                                        className="rounded-lg p-2" style={{ color: 'var(--color-danger)' }}>×</button>
                                                )}
                                            </div>
                                        </div>
                                        );
                                    })}
                                </div>
                            )}
                        </section>

                        {/* Resumen */}
                        <div className="rounded-xl border p-4 grid grid-cols-2 gap-4 text-sm"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                            <div>
                                <p style={{ color: 'var(--color-text-muted)' }}>Monto a devolver</p>
                                <p className="text-lg font-bold" style={{ color: 'var(--color-text)' }}>S/ {totalDevolucion.toFixed(2)}</p>
                            </div>
                            {requierePagos && (
                                <div>
                                    <p style={{ color: 'var(--color-text-muted)' }}>Total reembolsado</p>
                                    <p className="text-lg font-bold" style={{ color: totalReembolso === totalDevolucion ? 'var(--color-success)' : 'var(--color-warning)' }}>
                                        S/ {totalReembolso.toFixed(2)}
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="flex gap-3">
                            <Button variant="ghost" onClick={() => router.visit(route('devoluciones.index'))}>Cancelar</Button>
                            <Button onClick={submit} loading={processing}
                                disabled={!config.permite_devoluciones || !config.dentro_del_plazo}>
                                Registrar devolución
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function Field({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className={`font-medium ${mono ? 'font-mono' : ''}`} style={{ color: 'var(--color-text)' }}>{value}</p>
        </div>
    );
}
