import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Trash2, AlertCircle, AlertTriangle, Wallet, UserPlus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Switch from '@/Components/UI/Switch';
import Badge from '@/Components/UI/Badge';
import ModalCrearProveedor, { ProveedorLite } from './Partials/ModalCrearProveedor';
import type { PageProps } from '@/types';
import { hoyLocal } from '@/lib/fechas';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { id: number; unidad_medida_id: number; es_base: boolean; factor_conversion: string; unidad_medida?: UnidadMedida; }
interface Producto { id: number; codigo: string | null; nombre: string; unidades: ProductoUnidad[]; }
interface Almacen  { id: number; nombre: string; tipo: string; }

interface EntradaDetalleData {
    id?: number;
    producto_id: number;
    unidad_medida_id: number;
    cantidad: string;
    factor_conversion: string;
    precio_costo: string;
    numero_documento: string | null;
    producto?: Producto;
    unidad_medida?: UnidadMedida;
}

interface Proveedor { id: number; razon_social: string | null; nombre_comercial: string | null; numero_documento: string | null; tipo_documento: string; }
interface CuentaMP { id: number; nombre: string; banco: string | null; numero_cuenta: string | null; }
interface MetodoPagoForm { id: number; nombre: string; cuentas: CuentaMP[]; }
interface StockRow { almacen_id: number; producto_id: number; cantidad: string; }
// Pago ya registrado (histórico) de la entrada. Un admin puede editarlo/anularlo.
interface PagoPrevio {
    id: number;
    monto: string;
    fecha: string;
    referencia: string | null;
    metodo_pago_id: number | null;
    cuenta_id: number | null;
    turno_id: number | null;
    proveedor_adelanto_id: number | null;
    metodo_pago: { id: number; nombre: string } | null;
    cuenta: { id: number; nombre: string; banco: string | null } | null;
}

interface EntradaData {
    id: number;
    almacen_id: number;
    proveedor_id: number | null;
    proveedor: string | null;
    numero_documento: string | null;
    tipo: string;
    fecha: string;
    estado: 'borrador' | 'confirmado';
    observacion: string | null;
    estado_pago: 'pendiente' | 'parcial' | 'pagado';
    metodo_pago_id: number | null;
    cuenta_id: number | null;
    monto_pagado: string;
    total: string;
    detalles: EntradaDetalleData[];
}

interface TurnoLite {
    id: number; user_id: number; caja_id: number; fecha_apertura: string;
    estado: 'abierto' | 'cerrado';
    user?: { id: number; name: string } | null;
    caja?: { id: number; nombre: string } | null;
}

interface Props extends PageProps {
    entrada: EntradaData;
    pagosPrevios: PagoPrevio[];
    puedeEditarPagos: boolean;
    almacenes: Almacen[];
    productos: Producto[];
    proveedores: Proveedor[];
    metodosPago: MetodoPagoForm[];
    turnos: TurnoLite[];
    pagoTurnoId: number | null;
    stocks: StockRow[];
    /** Productos cuya mercadería quedó dentro del inventario inicial (entrada
     *  con fecha <= corte de apertura): editarlos no toca stock → sin restricción. */
    productosAbsorbidos: number[];
    mostrarSelector: boolean;
    modoAlmacen: 'simple' | 'central_y_local';
}

interface DetalleRow {
    producto_id: number | '';
    unidad_medida_id: number | '';
    cantidad: string;
    factor_conversion: string;
    precio_costo: string;
    // 'unitario' → teclea precio por unidad; 'total' → teclea lo pagado por toda
    // la línea y el sistema calcula el unitario. precio_costo siempre sincronizado.
    precio_modo: 'unitario' | 'total';
    precio_total: string;
    numero_documento: string;
}

/** Deriva el precio unitario a partir del total de la línea y la cantidad. */
function costoDesdeTotal(totalStr: string, cantidadStr: string): string {
    const t = parseFloat(totalStr);
    const q = parseFloat(cantidadStr);
    if (!isFinite(t) || !isFinite(q) || q <= 0) return '';
    return String(Math.round((t / q) * 10000) / 10000);
}

export default function EntradaEdit({ entrada, pagosPrevios, puedeEditarPagos, almacenes, productos, proveedores, metodosPago, turnos, pagoTurnoId, stocks, productosAbsorbidos, mostrarSelector, modoAlmacen }: Props) {
    // "Afecta caja a:" — por defecto NO afecta ninguna caja ('' = Sin turno). Solo
    // si el usuario elige un turno los pagos nuevos descuentan de esa caja. No
    // preseleccionamos el turno del pago anterior para no re-imputar sin querer.
    const [turnoIdCaja, setTurnoIdCaja] = useState<number | ''>('');
    void pagoTurnoId;
    const turnoLabel = (t: TurnoLite) => {
        const f = new Date(t.fecha_apertura).toLocaleDateString('es-PE');
        const hora = new Date(t.fecha_apertura).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
        return [`#${t.id}`, `${f} ${hora}`, t.user?.name, t.caja?.nombre].filter(Boolean).join(' · ')
            + (t.estado === 'abierto' ? ' · abierto' : '');
    };
    const [almacenId, setAlmacenId]     = useState<number | ''>(entrada.almacen_id);
    const [listaProveedores, setListaProveedores] = useState<Proveedor[]>(proveedores);
    const [modalProveedor, setModalProveedor]     = useState(false);
    const [proveedorId, setProveedorId] = useState<number | ''>(entrada.proveedor_id ?? '');
    const [nroDoc, setNroDoc]           = useState(entrada.numero_documento ?? '');
    const [tipo, setTipo]               = useState(entrada.tipo);
    // Misma trampa de zona horaria que en Index.fmtFecha: el backend manda ISO UTC.
    // El <input type="date"> espera "YYYY-MM-DD" — extraemos solo la porción de fecha
    // para evitar que se corra al día anterior en zonas con offset negativo.
    const [fecha, setFecha]             = useState(entrada.fecha?.slice(0, 10) ?? '');
    const [observacion, setObservacion] = useState(entrada.observacion ?? '');
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [processing, setProcessing]   = useState(false);
    // Modo inicial: si la entrada original tiene algún item con factura propia,
    // arrancamos en modo "por item" para no perder esos datos al renderizar.
    const [facturaPorItem, setFacturaPorItem] = useState(
        entrada.detalles.some(d => !!d.numero_documento)
    );


    const [detalles, setDetalles] = useState<DetalleRow[]>(
        entrada.detalles.map(d => ({
            producto_id:       d.producto_id,
            unidad_medida_id:  d.unidad_medida_id,
            cantidad:          String(d.cantidad),
            factor_conversion: String(d.factor_conversion),
            precio_costo:      String(d.precio_costo),
            // Lo guardado siempre es unitario; se puede cambiar a "total" por línea.
            precio_modo:       'unitario',
            precio_total:      '',
            numero_documento:  d.numero_documento ?? '',
        }))
    );

    function unidadesDeProducto(productoId: number | ''): ProductoUnidad[] {
        if (!productoId) return [];
        return productos.find(p => p.id === productoId)?.unidades ?? [];
    }

    function setDetalle(i: number, field: keyof DetalleRow, value: string | number) {
        setDetalles(prev => {
            const updated = prev.map((d, idx) => idx !== i ? d : { ...d, [field]: value });
            if (field === 'producto_id') {
                const unidades = unidadesDeProducto(value as number);
                const base = unidades.find(u => u.es_base);
                updated[i].unidad_medida_id  = base?.unidad_medida_id ?? '';
                updated[i].factor_conversion = '1';
            }
            if (field === 'unidad_medida_id') {
                const unidades = unidadesDeProducto(updated[i].producto_id);
                const unidad   = unidades.find(u => u.unidad_medida_id === Number(value));
                updated[i].factor_conversion = unidad ? String(unidad.factor_conversion) : '1';
            }
            if (updated[i].precio_modo === 'total' && (field === 'precio_total' || field === 'cantidad')) {
                updated[i].precio_costo = costoDesdeTotal(updated[i].precio_total, updated[i].cantidad);
            }
            return updated;
        });
    }

    /** Cambia entre ingresar por precio unitario o por total de la línea. */
    function setPrecioModo(i: number, modo: 'unitario' | 'total') {
        setDetalles(prev => prev.map((d, idx) => {
            if (idx !== i || d.precio_modo === modo) return d;
            if (modo === 'total') {
                const precio_total = subtotal(d) > 0 ? String(subtotal(d)) : '';
                return { ...d, precio_modo: 'total', precio_total,
                    precio_costo: costoDesdeTotal(precio_total, d.cantidad) || d.precio_costo };
            }
            return { ...d, precio_modo: 'unitario' };
        }));
    }

    function addDetalle()    { setDetalles(d => [...d, { producto_id: '', unidad_medida_id: '', cantidad: '', factor_conversion: '1', precio_costo: '', precio_modo: 'unitario', precio_total: '', numero_documento: '' }]); }
    function removeDetalle(i: number) { setDetalles(d => d.filter((_, idx) => idx !== i)); }

    function subtotal(d: DetalleRow): number {
        return Math.round((parseFloat(d.cantidad) || 0) * (parseFloat(d.precio_costo) || 0) * 100) / 100;
    }

    function cantidadBase(d: DetalleRow): number {
        return Math.round((parseFloat(d.cantidad) || 0) * (parseFloat(d.factor_conversion) || 1) * 10000) / 10000;
    }

    const total = detalles.reduce((sum, d) => sum + subtotal(d), 0);

    // ── Pagos NUEVOS desde la edición ─────────────────────────────────
    // Los pagos ya registrados no se tocan aquí (se corrigen en Finanzas →
    // Cuentas por pagar); esto permite pagar el saldo — p. ej. cuando se
    // agrega un producto y el total sube.
    interface LineaPago { key: string; metodo_pago_id: number | ''; cuenta_id: number | ''; monto: string; fecha: string; }
    const nuevaLinea = (monto = ''): LineaPago =>
        ({ key: Math.random().toString(36).slice(2), metodo_pago_id: '', cuenta_id: '', monto, fecha: hoyLocal() });
    const [pagosNuevos, setPagosNuevos] = useState<LineaPago[]>([]);

    const cuentasDeLinea = (l: LineaPago) =>
        metodosPago.find(m => m.id === l.metodo_pago_id)?.cuentas ?? [];
    function setPago(key: string, patch: Partial<LineaPago>) {
        setPagosNuevos(prev => prev.map(p => p.key === key ? { ...p, ...patch } : p));
    }

    // ── Edición de pagos YA registrados (solo admin) ──────────────────
    // Copia editable de pagosPrevios. Se re-sincroniza tras cada operación
    // (Inertia recarga los props con preserveState → este effect la refresca).
    interface EditPagoRow {
        id: number;
        metodo_pago_id: number | '';
        cuenta_id: number | '';
        monto: string;
        turno_id: number | '';
        esAdelanto: boolean;
        eliminar: boolean;   // marcado para anular al guardar
    }
    const mapEdit = (p: PagoPrevio): EditPagoRow => ({
        id: p.id,
        metodo_pago_id: p.metodo_pago_id ?? '',
        cuenta_id: p.cuenta_id ?? '',
        monto: String(p.monto),
        turno_id: p.turno_id ?? '',
        esAdelanto: !!p.proveedor_adelanto_id,
        eliminar: false,
    });
    const [editPagos, setEditPagos] = useState<EditPagoRow[]>(pagosPrevios.map(mapEdit));

    // Resincroniza la copia editable cuando cambian los props.
    useEffect(() => { setEditPagos(pagosPrevios.map(mapEdit)); }, [pagosPrevios]);

    function setEditPago(id: number, patch: Partial<EditPagoRow>) {
        setEditPagos(prev => prev.map(r => r.id === id ? { ...r, ...patch } : r));
    }
    const cuentasDeMetodo = (metodoId: number | '') =>
        metodosPago.find(m => m.id === metodoId)?.cuentas ?? [];

    // ¿La fila cambió respecto al valor original del backend?
    function pagoDirty(r: EditPagoRow): boolean {
        const orig = pagosPrevios.find(p => p.id === r.id);
        if (!orig) return false;
        return (r.metodo_pago_id || null) !== (orig.metodo_pago_id ?? null)
            || (r.cuenta_id || null) !== (orig.cuenta_id ?? null)
            || (r.turno_id || null) !== (orig.turno_id ?? null)
            || Math.abs((parseFloat(r.monto) || 0) - Number(orig.monto)) > 0.001;
    }

    // Monto ya pagado EFECTIVO considerando ediciones/anulaciones pendientes, para
    // que el saldo mostrado sea correcto ANTES de guardar (todo se aplica al enviar).
    const montoPagado = pagosPrevios.length > 0
        ? editPagos.filter(r => !r.eliminar).reduce((s, r) => s + (parseFloat(r.monto) || 0), 0)
        : Number(entrada.monto_pagado ?? 0);
    // Saldo contra el total ACTUAL del formulario (si agregaste un producto, sube en vivo).
    const saldoActual    = Math.max(0, Math.round((total - montoPagado) * 100) / 100);
    const totalPagoNuevo = pagosNuevos.reduce((s, p) => s + (parseFloat(p.monto) || 0), 0);
    const saldoDespues   = Math.max(0, Math.round((saldoActual - totalPagoNuevo) * 100) / 100);

    /**
     * Restricciones: si la entrada estaba confirmada, calculamos para cada producto
     * cuanto puede reducirse sin dejar stock negativo. Si la entrada original aporto
     * 10 unidades base y el stock actual es 3, significa que ya se consumieron 7 ⇒
     * el minimo total permitido es 7 (no se puede bajar de ahi).
     *
     * Devuelve: Map producto_id → { nombre, minimoBase, consumidoBase }
     */
    const restriccionesPorProducto = useMemo(() => {
        const m = new Map<number, { nombre: string; minimoBase: number; consumidoBase: number; aportoOriginal: number }>();
        if (entrada.estado !== 'confirmado') return m;

        // Stock actual indexado por producto en el almacen de la entrada
        const stockMap = new Map<number, number>();
        stocks.forEach(s => stockMap.set(s.producto_id, parseFloat(s.cantidad)));

        // Agrupar detalles originales por producto_id (puede haber varias lineas del mismo)
        const originalPorProducto = new Map<number, number>();
        entrada.detalles.forEach(d => {
            const cantBase = Number(d.cantidad) * Number(d.factor_conversion);
            originalPorProducto.set(d.producto_id, (originalPorProducto.get(d.producto_id) ?? 0) + cantBase);
        });

        originalPorProducto.forEach((aporto, prodId) => {
            // Absorbido por el inventario inicial: la entrada no aporta al stock
            // en vivo (vive en el conteo del corte) → reducirla es solo corrección
            // documental, sin restricción de stock (mismo criterio que el backend).
            if ((productosAbsorbidos ?? []).includes(prodId)) return;

            const stockAct = stockMap.get(prodId) ?? 0;
            const consumido = Math.max(0, aporto - stockAct);
            if (consumido > 0) {
                const prod = productos.find(p => p.id === prodId);
                m.set(prodId, {
                    nombre:        prod?.nombre ?? `Producto #${prodId}`,
                    minimoBase:    consumido,        // total mínimo permitido en cualquier estado
                    consumidoBase: consumido,
                    aportoOriginal: aporto,
                });
            }
        });

        return m;
    }, [entrada, stocks, productos, productosAbsorbidos]);

    /** Suma de cantidad_base por producto en el ESTADO ACTUAL del form (no el original). */
    const sumaActualPorProducto = useMemo(() => {
        const m = new Map<number, number>();
        detalles.forEach(d => {
            if (typeof d.producto_id !== 'number') return;
            m.set(d.producto_id, (m.get(d.producto_id) ?? 0) + cantidadBase(d));
        });
        return m;
    }, [detalles]);

    /** Lista de productos cuya suma actual no cumple el mínimo. Vacio = todo OK. */
    const violaciones = useMemo(() => {
        const out: Array<{ nombre: string; minimo: number; actual: number }> = [];
        restriccionesPorProducto.forEach((r, prodId) => {
            const actual = sumaActualPorProducto.get(prodId) ?? 0;
            if (actual + 0.0001 < r.minimoBase) {
                out.push({ nombre: r.nombre, minimo: r.minimoBase, actual });
            }
        });
        return out;
    }, [restriccionesPorProducto, sumaActualPorProducto]);

    function validar(): string[] {
        const errs: string[] = [];
        if (!almacenId) errs.push('Selecciona el almacén destino');

        pagosNuevos.forEach((p, idx) => {
            const n = idx + 1;
            if (!p.metodo_pago_id) errs.push(`Pago nuevo #${n}: falta el método de pago`);
            const m = parseFloat(p.monto);
            if (!p.monto || isNaN(m) || m <= 0) errs.push(`Pago nuevo #${n}: el monto debe ser mayor a 0`);
        });
        if (totalPagoNuevo > saldoActual + 0.009) {
            errs.push(`Los pagos nuevos (S/ ${totalPagoNuevo.toFixed(2)}) superan el saldo pendiente (S/ ${saldoActual.toFixed(2)})`);
        }
        // Pagos ya registrados editados (no anulados, con dinero): monto válido.
        editPagos.filter(r => !r.eliminar && !r.esAdelanto).forEach(r => {
            const m = parseFloat(r.monto);
            if (!r.monto || isNaN(m) || m <= 0) errs.push('Hay un pago ya registrado con monto inválido');
        });
        if (!tipo)      errs.push('Selecciona el tipo de entrada');
        if (!fecha)     errs.push('Indica la fecha');

        if (detalles.length === 0) {
            errs.push('Agrega al menos un producto al detalle');
        } else {
            detalles.forEach((d, idx) => {
                const n = idx + 1;
                if (!d.producto_id)       errs.push(`Producto #${n}: falta seleccionar el producto`);
                if (!d.unidad_medida_id)  errs.push(`Producto #${n}: falta seleccionar la unidad`);
                const qty = parseFloat(d.cantidad);
                if (!d.cantidad || isNaN(qty) || qty <= 0) {
                    errs.push(`Producto #${n}: la cantidad debe ser mayor a 0`);
                }
                const cost = parseFloat(d.precio_costo);
                if (d.precio_costo === '' || isNaN(cost) || cost < 0) {
                    errs.push(`Producto #${n}: precio de costo inválido`);
                }
                // Factura/comprobante siempre OPCIONAL — no bloqueamos.
            });
        }
        return errs;
    }

    function mostrarErroresValidacion(errs: string[]) {
        toast.error(
            () => (
                <div className="flex flex-col gap-1.5 max-w-xs">
                    <div className="flex items-center gap-2 font-semibold text-sm">
                        <AlertCircle size={15} />
                        <span>Faltan datos para guardar</span>
                    </div>
                    <ul className="text-xs space-y-0.5 list-disc list-inside opacity-95">
                        {errs.slice(0, 5).map((e, i) => <li key={i}>{e}</li>)}
                        {errs.length > 5 && (
                            <li className="opacity-70 list-none">…y {errs.length - 5} más</li>
                        )}
                    </ul>
                </div>
            ),
            { duration: 5500 }
        );
    }

    function submit() {
        const errs = validar();
        if (errs.length > 0) {
            mostrarErroresValidacion(errs);
            return;
        }
        // Bloqueo explicito si hay violaciones de stock — backend tambien valida,
        // pero atajamos aca para evitar el roundtrip y dar mensaje accionable.
        if (violaciones.length > 0) {
            mostrarErroresValidacion(violaciones.map(v =>
                `${v.nombre}: tienes ${v.actual.toFixed(2)} base, debes mantener al menos ${v.minimo.toFixed(2)} base`
            ));
            return;
        }
        setProcessing(true);
        router.put(route('inventario.entradas.update', entrada.id), {
            almacen_id: almacenId, proveedor_id: proveedorId || null,
            numero_documento: facturaPorItem ? null : (nroDoc || null),
            tipo, fecha, observacion,
            detalles: detalles.map(d => ({
                producto_id: d.producto_id, unidad_medida_id: d.unidad_medida_id,
                cantidad: d.cantidad, factor_conversion: d.factor_conversion, precio_costo: d.precio_costo,
                numero_documento: facturaPorItem ? (d.numero_documento.trim() || null) : null,
            })),
            pagos: pagosNuevos.map(p => ({
                metodo_pago_id: p.metodo_pago_id,
                cuenta_id:      p.cuenta_id || null,
                monto:          p.monto,
                fecha:          p.fecha,
            })),
            // Pagos YA registrados editados / anulados (solo admin) — se guardan en
            // el MISMO submit, sin botón por método.
            pagos_editados: puedeEditarPagos ? editPagos
                .filter(r => !r.eliminar && pagoDirty(r))
                .map(r => ({
                    id:             r.id,
                    monto:          r.esAdelanto ? undefined : r.monto,
                    metodo_pago_id: r.metodo_pago_id || null,
                    cuenta_id:      r.cuenta_id || null,
                    turno_id:       r.turno_id || null,
                })) : [],
            pagos_anulados: puedeEditarPagos ? editPagos.filter(r => r.eliminar).map(r => r.id) : [],
            turno_id: turnoIdCaja || null,
        }, {
            onSuccess: () => setProcessing(false),
            onError:   (e) => {
                setErrors(e);
                setProcessing(false);
                const first = Object.values(e)[0];
                toast.error(typeof first === 'string' ? first : 'Revisa los campos marcados.');
            },
        });
    }

    return (
        <AppLayout title="Editar entrada">
            <PageHeader
                title="Editar entrada"
                subtitle={entrada.estado === 'confirmado' ? 'Entrada confirmada — cambios recalcularán stock y costo promedio' : 'Entrada en borrador — los cambios no afectan stock'}
                backHref={route('inventario.entradas.index')}
            />

            {entrada.estado === 'confirmado' && (
                <div className="max-w-5xl mx-auto mb-5 rounded-2xl border p-4 flex items-start gap-3"
                    style={{
                        borderColor: 'color-mix(in srgb, var(--color-warning) 40%, var(--color-border))',
                        backgroundColor: 'color-mix(in srgb, var(--color-warning) 8%, transparent)',
                    }}>
                    <AlertCircle size={18} className="flex-shrink-0 mt-0.5" style={{ color: 'var(--color-warning)' }} />
                    <div className="text-sm" style={{ color: 'var(--color-text)' }}>
                        <p className="font-semibold mb-1">Esta entrada ya fue confirmada.</p>
                        <p style={{ color: 'var(--color-text-muted)' }}>
                            Al guardar: el stock se ajustará automáticamente con la diferencia y el <strong>costo promedio se recalculará</strong>.
                            Si reduces la cantidad de un producto que ya tiene ventas o transferencias posteriores, el sistema bloqueará el cambio
                            con un mensaje indicando la cantidad mínima permitida.
                        </p>
                    </div>
                </div>
            )}

            <div className="max-w-5xl mx-auto space-y-8">
                <section className="rounded-2xl border p-6 space-y-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Datos de la entrada
                    </h2>
                    {modoAlmacen === 'central_y_local' && (
                        <div className="rounded-xl px-4 py-3 text-sm"
                            style={{ backgroundColor: 'rgba(59,130,246,0.06)', border: '1px solid rgba(59,130,246,0.2)', color: 'var(--color-text)' }}>
                            Las entradas (compras) ingresan al <strong>almacén central</strong>. Para mover stock a un local usa el módulo de <strong>Transferencias</strong>.
                        </div>
                    )}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {almacenes.length > 1 ? (
                            <Select label="Almacén destino" required value={almacenId}
                                onChange={v => setAlmacenId(v === '' ? '' : Number(v))}
                                options={almacenes.map(a => ({ value: a.id, label: a.nombre }))}
                                error={errors.almacen_id} />
                        ) : almacenes.length === 1 ? (
                            <div>
                                <label className="text-sm font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Almacén destino</label>
                                <div className="rounded-xl border px-3 py-2 text-sm" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                    {almacenes[0].nombre}
                                </div>
                            </div>
                        ) : null}
                        <Select label="Tipo" required value={tipo} onChange={v => setTipo(String(v))}
                            options={[
                                { value: 'compra', label: 'Compra' }, { value: 'ajuste', label: 'Ajuste' },
                                { value: 'devolucion', label: 'Devolución' }, { value: 'otro', label: 'Otro' },
                            ]} />
                        <div className="flex items-end gap-2">
                            <div className="flex-1 min-w-0">
                                <Select
                                    label="Proveedor"
                                    placeholder="Sin proveedor"
                                    value={proveedorId}
                                    onChange={v => setProveedorId(v === '' ? '' : Number(v))}
                                    options={listaProveedores.map(p => ({
                                        value: p.id,
                                        label: `${p.razon_social ?? p.nombre_comercial ?? '—'}${p.numero_documento ? ` · ${p.tipo_documento} ${p.numero_documento}` : ''}`,
                                    }))}
                                    error={errors.proveedor_id}
                                />
                            </div>
                            <Button type="button" variant="secondary" onClick={() => setModalProveedor(true)} title="Crear nuevo proveedor">
                                <UserPlus size={15} className="mr-1" /> Nuevo
                            </Button>
                        </div>
                        {!facturaPorItem && (
                            <Input label="Nro. documento" value={nroDoc} onChange={e => setNroDoc(e.target.value)} />
                        )}
                        <Input label="Fecha" required type="date" value={fecha} onChange={e => setFecha(e.target.value)} />
                    </div>

                    <Switch
                        label="Cada producto tiene su propia factura"
                        description="Útil cuando el proveedor entregó la mercadería con varias facturas distintas. Si está apagado, todos los productos comparten el número de la cabecera."
                        checked={facturaPorItem}
                        onChange={setFacturaPorItem}
                    />

                    <div>
                        <label className="text-sm font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Observación</label>
                        <textarea rows={2} value={observacion} onChange={e => setObservacion(e.target.value)}
                            className="w-full rounded-xl border px-3 py-2 text-sm outline-none resize-none transition-all"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                            onFocus={e => e.currentTarget.style.borderColor = 'var(--color-primary)'}
                            onBlur={e => e.currentTarget.style.borderColor = 'var(--color-border)'} />
                    </div>
                </section>

                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Productos</h2>
                        <Button type="button" variant="ghost" onClick={addDetalle}>
                            <Plus size={14} className="mr-1" />Agregar producto
                        </Button>
                    </div>

                    {/* Banner de restricciones de stock (solo si la entrada original era confirmada
                        y algunos productos ya tuvieron movimientos posteriores). Educa al usuario
                        sobre por que no puede reducir esos productos por debajo del minimo. */}
                    {restriccionesPorProducto.size > 0 && (
                        <div className="rounded-xl border p-3 space-y-2"
                            style={{
                                borderColor: 'color-mix(in srgb, var(--color-warning) 40%, var(--color-border))',
                                backgroundColor: 'color-mix(in srgb, var(--color-warning) 6%, transparent)',
                            }}>
                            <div className="flex items-center gap-2 text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                <AlertTriangle size={15} style={{ color: 'var(--color-warning)' }} />
                                Productos con movimientos posteriores
                            </div>
                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                Estos productos ya tuvieron salidas/ventas después de confirmar esta entrada. Puedes redistribuir entre líneas, pero la <strong>suma total por producto</strong> debe ser ≥ al mínimo indicado. Si necesitas quitar uno, agrega otra línea del mismo producto con la cantidad restante.
                            </p>
                            <ul className="text-xs space-y-1 mt-1">
                                {[...restriccionesPorProducto.entries()].map(([prodId, r]) => {
                                    const actual = sumaActualPorProducto.get(prodId) ?? 0;
                                    const cumple = actual + 0.0001 >= r.minimoBase;
                                    return (
                                        <li key={prodId} className="flex items-center justify-between gap-2 px-2 py-1 rounded"
                                            style={{ backgroundColor: cumple ? 'transparent' : 'color-mix(in srgb, var(--color-danger) 10%, transparent)' }}>
                                            <span style={{ color: 'var(--color-text)' }}>{r.nombre}</span>
                                            <span className="font-mono" style={{ color: cumple ? '#16a34a' : 'var(--color-danger)' }}>
                                                {actual.toFixed(2)} / mín. {r.minimoBase.toFixed(2)} base
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    <div className="hidden md:grid grid-cols-12 gap-2 text-xs font-semibold uppercase tracking-wide px-1" style={{ color: 'var(--color-text-muted)' }}>
                        <div className={facturaPorItem ? 'col-span-3' : 'col-span-5'}>Producto</div>
                        <div className="col-span-2">Unidad</div>
                        <div className="col-span-2">Cantidad</div>
                        <div className="col-span-2">Precio</div>
                        {facturaPorItem && <div className="col-span-2">Factura</div>}
                        <div className="col-span-1 text-right">Subtotal</div>
                    </div>

                    {detalles.map((d, i) => {
                        const unidades = unidadesDeProducto(d.producto_id);
                        return (
                            <div key={i} className="rounded-xl p-3 space-y-3 md:space-y-2" style={{ backgroundColor: 'var(--color-bg)' }}>
                                <div className="flex items-center justify-between md:hidden">
                                    <span className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                        Producto #{i + 1}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <span className="text-base font-mono font-semibold" style={{ color: 'var(--color-text)' }}>
                                            S/ {subtotal(d).toFixed(2)}
                                        </span>
                                        {detalles.length > 1 && (
                                            <button type="button" onClick={() => removeDetalle(i)}
                                                className="rounded-lg p-1.5" style={{ color: 'var(--color-danger)' }}>
                                                <Trash2 size={15} />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3 md:grid md:grid-cols-12 md:gap-2 md:items-end md:space-y-0">
                                    <div className={facturaPorItem ? 'md:col-span-3' : 'md:col-span-5'}>
                                        <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Producto</label>
                                        <SearchableSelect
                                            placeholder="Buscar producto..."
                                            searchPlaceholder="Buscar por nombre o código..."
                                            emptyMessage="No hay productos que coincidan"
                                            value={d.producto_id}
                                            onChange={v => setDetalle(i, 'producto_id', Number(v))}
                                            options={productos.map(p => ({ value: p.id, label: p.codigo ? `[${p.codigo}] ${p.nombre}` : p.nombre }))}
                                            error={(errors)[`detalles.${i}.producto_id`]} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 md:contents">
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Unidad</label>
                                            <Select placeholder="Unidad" value={d.unidad_medida_id}
                                                onChange={v => setDetalle(i, 'unidad_medida_id', Number(v))}
                                                options={unidades.map(u => ({
                                                    value: u.unidad_medida_id,
                                                    label: u.unidad_medida ? `${u.unidad_medida.abreviatura}${u.es_base ? ' (base)' : ''}` : String(u.unidad_medida_id),
                                                }))} />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Cantidad</label>
                                            <Input placeholder="0" type="number" min="0" step="any" inputMode="decimal"
                                                value={d.cantidad} onChange={e => setDetalle(i, 'cantidad', e.target.value)} />
                                        </div>
                                    </div>

                                    <div className={`grid ${facturaPorItem ? 'grid-cols-2' : 'grid-cols-1'} gap-2 md:contents`}>
                                        <div className="md:col-span-2">
                                            <div className="flex items-center justify-between md:justify-end gap-2 mb-1">
                                                <label className="md:hidden text-xs font-medium" style={{ color: 'var(--color-text)' }}>
                                                    {d.precio_modo === 'total' ? 'Precio total' : 'Precio costo'}
                                                </label>
                                                <div className="inline-flex rounded-md border overflow-hidden text-[10px] font-semibold leading-none"
                                                    style={{ borderColor: 'var(--color-border)' }}>
                                                    {(['unitario', 'total'] as const).map(m => (
                                                        <button key={m} type="button" onClick={() => setPrecioModo(i, m)}
                                                            className="px-1.5 py-1 transition-colors"
                                                            style={{
                                                                backgroundColor: d.precio_modo === m ? 'var(--color-primary)' : 'transparent',
                                                                color: d.precio_modo === m ? '#fff' : 'var(--color-text-muted)',
                                                            }}>
                                                            {m === 'unitario' ? 'P.U.' : 'Total'}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                            {d.precio_modo === 'total' ? (
                                                <Input placeholder="0.00" type="number" min="0" step="0.01" inputMode="decimal"
                                                    value={d.precio_total} onChange={e => setDetalle(i, 'precio_total', e.target.value)} />
                                            ) : (
                                                <Input placeholder="0.00" type="number" min="0" step="0.0001" inputMode="decimal"
                                                    value={d.precio_costo} onChange={e => setDetalle(i, 'precio_costo', e.target.value)} />
                                            )}
                                            {d.precio_modo === 'total' && d.precio_costo !== '' && (
                                                <p className="mt-0.5 text-[11px] font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                                    = S/ {d.precio_costo} c/u
                                                </p>
                                            )}
                                        </div>
                                        {facturaPorItem && (
                                            <div className="md:col-span-2">
                                                <label className="md:hidden text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Factura</label>
                                                <Input placeholder="F001-..."
                                                    value={d.numero_documento}
                                                    onChange={e => setDetalle(i, 'numero_documento', e.target.value)}
                                                    error={(errors)[`detalles.${i}.numero_documento`]} />
                                            </div>
                                        )}
                                    </div>

                                    <div className="hidden md:flex md:col-span-1 md:text-right md:items-end md:justify-end md:gap-1">
                                        <p className="text-sm font-mono font-semibold pb-2" style={{ color: 'var(--color-text)' }}>S/ {subtotal(d).toFixed(2)}</p>
                                        {detalles.length > 1 && (
                                            <button type="button" onClick={() => removeDetalle(i)} className="mb-2 rounded p-0.5" style={{ color: 'var(--color-danger)' }}>
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs px-1" style={{ color: 'var(--color-text-muted)' }}>
                                    <span className="font-mono">×{d.factor_conversion}</span>
                                    <span className="font-mono">= {cantidadBase(d).toFixed(4)} base</span>
                                </div>
                            </div>
                        );
                    })}

                    <div className="flex justify-end pt-2 border-t" style={{ borderColor: 'var(--color-border)' }}>
                        <div className="text-right">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Total</p>
                            <p className="text-xl font-bold font-mono" style={{ color: 'var(--color-text)' }}>S/ {total.toFixed(2)}</p>
                        </div>
                    </div>
                </section>

                {/* ── Pago al proveedor: resumen + registrar pagos NUEVOS del saldo ── */}
                <section
                    className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}
                >
                    <div className="flex items-center gap-2">
                        <Wallet size={16} style={{ color: 'var(--color-text-muted)' }} />
                        <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                            Pago al proveedor
                        </h2>
                    </div>
                    <div className="flex flex-wrap items-center gap-5 text-sm">
                        <span style={{ color: 'var(--color-text-muted)' }}>
                            Ya pagado:{' '}
                            <strong style={{ color: 'var(--color-success)' }}>S/ {montoPagado.toFixed(2)}</strong>
                        </span>
                        <span style={{ color: 'var(--color-text-muted)' }}>
                            Saldo (con el total actual):{' '}
                            <strong style={{ color: saldoActual > 0.01 ? 'var(--color-danger)' : 'var(--color-text)' }}>
                                S/ {saldoActual.toFixed(2)}
                            </strong>
                        </span>
                        <Badge variant={saldoDespues <= 0.009 ? 'success' : montoPagado + totalPagoNuevo > 0 ? 'warning' : 'secondary'}>
                            {saldoDespues <= 0.009 ? 'Quedará pagada' : montoPagado + totalPagoNuevo > 0 ? 'Parcial' : 'Pendiente'}
                        </Badge>
                    </div>

                    {/* Pagos YA registrados. Admin: editables (método/cuenta/monto/caja) y
                        anulables; todo se guarda con el MISMO botón "Guardar cambios" (el
                        backend recompone tesorería y el saldo). No admin: solo lectura. */}
                    {pagosPrevios.length > 0 && (
                        <div className="rounded-xl border p-3 space-y-2" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                            <p className="text-[11px] font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                Pagos ya registrados
                            </p>

                            {!puedeEditarPagos ? (
                                <ul className="space-y-1.5">
                                    {pagosPrevios.map(p => (
                                        <li key={p.id} className="flex items-center justify-between gap-3 text-sm">
                                            <span style={{ color: 'var(--color-text)' }}>
                                                <span className="font-medium">{p.metodo_pago?.nombre ?? 'Sin método'}</span>
                                                {p.cuenta && <span style={{ color: 'var(--color-text-muted)' }}> · {p.cuenta.nombre}</span>}
                                                <span className="ml-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                    {p.fecha?.slice(0, 10).split('-').reverse().join('/')}
                                                </span>
                                            </span>
                                            <span className="font-mono font-semibold" style={{ color: 'var(--color-success)' }}>
                                                S/ {Number(p.monto).toFixed(2)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="space-y-2.5">
                                    {editPagos.map(r => {
                                        const cuentas  = cuentasDeMetodo(r.metodo_pago_id);
                                        const dirty    = pagoDirty(r);
                                        const disabled = r.eliminar || r.esAdelanto;
                                        return (
                                            <div key={r.id} className="rounded-lg border p-2.5 space-y-2"
                                                style={{
                                                    borderColor: r.eliminar ? 'var(--color-danger)' : 'var(--color-border)',
                                                    backgroundColor: 'var(--color-surface)',
                                                    opacity: r.eliminar ? 0.6 : 1,
                                                }}>
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-[11px]" style={{ color: r.eliminar ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                                        {r.eliminar
                                                            ? 'Se anulará al guardar'
                                                            : r.esAdelanto
                                                                ? 'Pago con adelanto — solo anular o cambiar de caja'
                                                                : (dirty ? 'Modificado — se guarda abajo' : 'Pago registrado')}
                                                    </span>
                                                    {r.eliminar ? (
                                                        <button type="button" onClick={() => setEditPago(r.id, { eliminar: false })}
                                                            className="text-xs font-medium underline" style={{ color: 'var(--color-primary)' }}>
                                                            Deshacer
                                                        </button>
                                                    ) : (
                                                        <button type="button" title="Anular este pago"
                                                            onClick={() => setEditPago(r.id, { eliminar: true })}
                                                            className="p-1.5 rounded-lg hover:bg-black/5" style={{ color: 'var(--color-danger)' }}>
                                                            <Trash2 size={15} />
                                                        </button>
                                                    )}
                                                </div>
                                                <div className="grid grid-cols-1 sm:grid-cols-[1.1fr_1.1fr_100px] gap-2">
                                                    <Select label="Método" placeholder="Sin método"
                                                        value={r.metodo_pago_id} disabled={disabled}
                                                        onChange={v => setEditPago(r.id, { metodo_pago_id: v === '' ? '' : Number(v), cuenta_id: '' })}
                                                        options={metodosPago.map(m => ({ value: m.id, label: m.nombre }))} />
                                                    <Select label="Cuenta"
                                                        placeholder={cuentas.length ? '(Opcional)' : 'Se asigna sola'}
                                                        value={r.cuenta_id} disabled={disabled || cuentas.length === 0}
                                                        onChange={v => setEditPago(r.id, { cuenta_id: v === '' ? '' : Number(v) })}
                                                        options={cuentas.map(c => ({ value: c.id, label: c.banco ? `${c.nombre} · ${c.banco}` : c.nombre }))} />
                                                    <Input label="Monto" type="number" min="0.01" step="0.01"
                                                        value={r.monto} disabled={disabled}
                                                        onChange={e => setEditPago(r.id, { monto: e.target.value })} />
                                                </div>
                                                {turnos.length > 0 && (
                                                    <Select label="Afecta caja a (turno)"
                                                        placeholder="Sin turno / no sale de caja"
                                                        value={r.turno_id} disabled={r.eliminar}
                                                        onChange={v => setEditPago(r.id, { turno_id: v === '' ? '' : Number(v) })}
                                                        options={[
                                                            { value: '', label: 'Sin turno / no sale de caja' },
                                                            ...turnos.map(t => ({ value: t.id, label: turnoLabel(t) })),
                                                        ]} />
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}

                    {/* "Afecta caja a:" — de qué caja/turno sale el efectivo de los pagos
                        NUEVOS que registres abajo. Los pagos ya registrados llevan su
                        propia caja por fila (arriba). */}
                    {turnos.length > 0 && (montoPagado > 0 || pagosNuevos.length > 0) && (
                        <div className="mb-3 sm:max-w-md">
                            <Select
                                label="Afecta caja a (turno)"
                                placeholder="Sin turno / no salió de caja"
                                value={turnoIdCaja}
                                onChange={v => setTurnoIdCaja(v === '' ? '' : Number(v))}
                                options={[
                                    { value: '', label: 'Sin turno / no salió de caja' },
                                    ...turnos.map(t => ({ value: t.id, label: turnoLabel(t) })),
                                ]}
                                hint="Elige de qué caja salió el efectivo, para que la consolidación de ese turno lo reste."
                            />
                        </div>
                    )}

                    {saldoActual <= 0.009 && pagosNuevos.length === 0 ? (
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            La compra está completamente pagada con el total actual. Si agregas productos o subes precios, aquí podrás pagar la diferencia.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {pagosNuevos.map((p, idx) => {
                                const cuentas = cuentasDeLinea(p);
                                return (
                                    <div key={p.key} className="grid grid-cols-1 sm:grid-cols-[1fr_1fr_150px_140px_auto] gap-3 items-end">
                                        <Select
                                            label={idx === 0 ? 'Método de pago' : undefined}
                                            required
                                            placeholder="Seleccionar método"
                                            value={p.metodo_pago_id}
                                            onChange={v => setPago(p.key, { metodo_pago_id: v === '' ? '' : Number(v), cuenta_id: '' })}
                                            options={metodosPago.map(m => ({ value: m.id, label: m.nombre }))}
                                        />
                                        <Select
                                            label={idx === 0 ? 'Cuenta' : undefined}
                                            placeholder={cuentas.length ? '(Opcional) elegir cuenta' : 'Se asigna sola'}
                                            value={p.cuenta_id}
                                            onChange={v => setPago(p.key, { cuenta_id: v === '' ? '' : Number(v) })}
                                            options={cuentas.map(c => ({
                                                value: c.id,
                                                label: c.banco ? `${c.nombre} · ${c.banco}` : c.nombre,
                                            }))}
                                            disabled={cuentas.length === 0}
                                        />
                                        <Input
                                            label={idx === 0 ? 'Fecha' : undefined}
                                            required type="date"
                                            value={p.fecha}
                                            onChange={e => setPago(p.key, { fecha: e.target.value })}
                                        />
                                        <Input
                                            label={idx === 0 ? 'Monto (S/)' : undefined}
                                            required type="number" min="0.01" step="0.01"
                                            value={p.monto}
                                            onChange={e => setPago(p.key, { monto: e.target.value })}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setPagosNuevos(prev => prev.filter(x => x.key !== p.key))}
                                            className="p-2 mb-0.5 rounded-lg hover:bg-black/5"
                                            title="Quitar línea de pago"
                                            style={{ color: 'var(--color-danger)' }}
                                        >
                                            <Trash2 size={15} />
                                        </button>
                                    </div>
                                );
                            })}

                            <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                                <div className="flex gap-2">
                                    {/* Una sola acción: la primera línea se precarga con el saldo
                                        (un clic = pagar todo), pero el monto es editable para dejarlo
                                        como pago parcial. Sin dos botones que confundían. */}
                                    <Button type="button" variant="ghost" size="sm"
                                        onClick={() => setPagosNuevos(prev => prev.length === 0
                                            ? [nuevaLinea(saldoActual > 0.009 ? saldoActual.toFixed(2) : '')]
                                            : [...prev, nuevaLinea()])}>
                                        <Plus size={14} className="mr-1" />
                                        {pagosNuevos.length === 0 ? 'Registrar pago' : 'Agregar otro método'}
                                    </Button>
                                </div>
                                {pagosNuevos.length > 0 && (
                                    <div className="flex items-center gap-5 text-sm">
                                        <span style={{ color: 'var(--color-text-muted)' }}>
                                            Pagos nuevos:{' '}
                                            <strong style={{ color: totalPagoNuevo > saldoActual + 0.009 ? 'var(--color-danger)' : 'var(--color-success)' }}>
                                                S/ {totalPagoNuevo.toFixed(2)}
                                            </strong>
                                        </span>
                                        <span style={{ color: 'var(--color-text-muted)' }}>
                                            Quedará como deuda:{' '}
                                            <strong style={{ color: saldoDespues > 0 ? 'var(--color-danger)' : 'var(--color-text)' }}>
                                                S/ {saldoDespues.toFixed(2)}
                                            </strong>
                                        </span>
                                    </div>
                                )}
                            </div>
                            {totalPagoNuevo > saldoActual + 0.009 && (
                                <p className="text-xs" style={{ color: 'var(--color-danger)' }}>
                                    Los pagos nuevos superan el saldo pendiente.
                                </p>
                            )}
                            {errors.pagos && <p className="text-xs" style={{ color: 'var(--color-danger)' }}>{errors.pagos}</p>}
                        </div>
                    )}

                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        Cada pago que registres aquí se asienta en tesorería con <strong>su propia fecha</strong> (el campo
                        Fecha de la línea, por defecto hoy). Para corregir o anular un pago <strong>ya registrado</strong>,
                        usa Finanzas → Cuentas por pagar.
                    </p>
                </section>

                <div className="flex gap-3">
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('inventario.entradas.index'))}>Cancelar</Button>
                    <Button type="button" loading={processing} onClick={submit}>Guardar cambios</Button>
                </div>
            </div>

            <ModalCrearProveedor
                isOpen={modalProveedor}
                onClose={() => setModalProveedor(false)}
                onCreated={(nuevo: ProveedorLite) => {
                    setListaProveedores(prev =>
                        prev.some(p => p.id === nuevo.id) ? prev : [nuevo as Proveedor, ...prev]);
                    setProveedorId(nuevo.id);
                    setModalProveedor(false);
                }}
            />
        </AppLayout>
    );
}
