import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { HandCoins, Eye, Receipt, ChevronDown, ChevronRight, Pencil, Trash2, Users, CalendarClock } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Table, { Column } from '@/Components/UI/Table';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import StatGrid from '@/Components/UI/StatGrid';
import AfectaCajaSelect from '@/Components/AfectaCajaSelect';
import PagoForm from '@/Components/PagoForm';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Timeline from '@/Components/UI/Timeline';
import BotonGuardar, { ErroresSueltos, useProblema, type Problema } from '@/Components/UI/BotonGuardar';
import type { PageProps } from '@/types';

interface Abono {
    id: number;
    fecha: string;
    monto: string;
    referencia: string | null;
    observacion: string | null;
    metodo_pago_id?: number | null;
    cuenta_id?: number | null;
    metodo_pago?: { nombre: string } | null;
    cuenta?: { nombre: string } | null;
    // Abono por compensación con una compra (sin dinero): no se edita, solo se anula.
    compensacion_grupo_id?: string | null;
    // Abono cobrado consumiendo un anticipo del cliente (sin dinero nuevo).
    cliente_anticipo_id?: number | null;
    user?: { name: string } | null;
}

/** Anticipo de DINERO del cliente con saldo, usable para cobrar su deuda. */
interface AnticipoCliente { id: number; cliente_id: number; fecha: string; saldo: string; }

interface PagoInicial {
    id: number;
    monto: string;
    vuelto: string;
    metodo_pago?: { nombre: string } | null;
}

interface VentaItemCxc {
    id: number;
    producto_nombre: string;
    unidad_nombre: string;
    cantidad: string;
    precio_unitario: string;
    descuento_item: string;
    subtotal: string;
}

interface VentaCxc extends Record<string, unknown> {
    id: number;
    numero: string | null;
    fecha_venta: string;
    fecha_vencimiento: string | null;
    tipo_comprobante?: string;
    total: string;
    subtotal?: string;
    igv?: string;
    descuento_total?: string;
    observacion?: string | null;
    monto_pagado: string;
    saldo_pendiente: string;
    cliente?: { id: number; nombres?: string; apellidos?: string; razon_social?: string; numero_documento?: string | null } | null;
    user?: { name: string } | null;
    caja?: { nombre: string } | null;
    items?: VentaItemCxc[];
    abonos: Abono[];
    pagos: PagoInicial[];
}

interface TurnoLite {
    id: number; user_id: number; caja_id: number; fecha_apertura: string;
    estado: 'abierto' | 'cerrado';
    user?: { id: number; name: string } | null;
    caja?: { id: number; nombre: string } | null;
}

interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

/** Compra con saldo contra la que se puede compensar esta CxC (sin mover caja). */
interface CompraCompensable {
    id: number;
    correlativo: string | null;
    numero_documento: string | null;
    proveedor: string | null;
    proveedor_id: number | null;
    fecha: string;
    total: string;
    monto_pagado: string;
    proveedor_rel?: { id: number; razon_social?: string | null; nombre_comercial?: string | null; numero_documento?: string | null } | null;
}

interface Props extends PageProps {
    ventas: Paginado<VentaCxc>;
    totalPendiente: number;
    kpis: { ventas_con_saldo: number; clientes_con_deuda: number; vencidas: number; monto_vencido: number };
    estado: string;
    busqueda: string;
    metodosPago: { id: number; nombre: string; tipo_slug?: string | null; cuentas?: { id: number; nombre: string }[] }[];
    cuentas: { id: number; nombre: string; es_efectivo?: boolean }[];
    puede: { editar: boolean; eliminar: boolean };
    turnos: TurnoLite[];
    turnoActivoId: number | null;
    comprasCompensables: CompraCompensable[];
    puedeCompensar: boolean;
    anticiposClientes: AnticipoCliente[];
}

import { hoyLocal } from '@/lib/fechas';

const hoy = () => hoyLocal();
const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const nombreCliente = (v: VentaCxc) =>
    v.cliente?.razon_social ?? (`${v.cliente?.nombres ?? ''} ${v.cliente?.apellidos ?? ''}`.trim() || '—');

export default function CuentasPorCobrar({ ventas, totalPendiente, kpis, estado, busqueda, metodosPago, cuentas, puede, turnos, turnoActivoId, comprasCompensables, puedeCompensar, anticiposClientes }: Props) {
    const { flash } = usePage<Props>().props;
    const [abonando, setAbonando] = useState<VentaCxc | null>(null);
    const [detalle, setDetalle]   = useState<VentaCxc | null>(null);
    // Colapsable de la venta relacionada dentro del modal de detalle.
    const [ventaAbierta, setVentaAbierta] = useState(false);
    const [saving, setSaving]     = useState(false);
    const [errors, setErrors]     = useState<Record<string, string>>({});
    const [form, setForm] = useState({
        monto: '', fecha: hoy(), metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '', turno_id: '',
    });
    // Compensar con una compra (CxP): el abono no entra como dinero, se cancela
    // contra lo que le debemos al tercero como proveedor. Sin movimiento de caja.
    const [compensarActivo, setCompensarActivo]       = useState(false);
    const [compensarEntradaId, setCompensarEntradaId] = useState<number | ''>('');
    // Cobrar consumiendo el anticipo del cliente: no entra dinero nuevo (ya
    // entró al crear el anticipo) — baja la deuda y el pasivo a la vez.
    const [usarAnticipo, setUsarAnticipo]   = useState(false);
    const [anticipoId, setAnticipoId]       = useState<number | ''>('');
    // Pago MIXTO: lo que se toma del anticipo + un pago adicional opcional
    // (efectivo/yape/etc.) en la misma operación, con desglose explícito.
    const [montoAnticipo, setMontoAnticipo]   = useState('');
    const [pagoAdicional, setPagoAdicional]   = useState(false);
    const [montoAdicional, setMontoAdicional] = useState('');

    const anticiposDelCliente = abonando?.cliente
        ? anticiposClientes.filter(a => a.cliente_id === abonando.cliente!.id && Number(a.saldo) > 0)
        : [];
    const saldoAnticiposDisponibles = anticiposDelCliente.reduce((s, a) => s + Number(a.saldo), 0);
    const anticipoSel = usarAnticipo
        ? anticiposDelCliente.find(a => a.id === anticipoId)
        : undefined;
    // Números del desglose (todos en vivo, para que la cajera VEA qué pasa):
    const saldoVenta        = Number(abonando?.saldo_pendiente ?? 0);
    const anticipoSaldo     = Number(anticipoSel?.saldo ?? 0);
    const topeAnticipo      = Math.round(Math.min(saldoVenta, anticipoSaldo) * 100) / 100;
    const tomaAnticipo      = usarAnticipo ? (parseFloat(montoAnticipo) || 0) : 0;
    const adicional         = usarAnticipo && pagoAdicional ? (parseFloat(montoAdicional) || 0) : 0;
    const totalCobro        = Math.round((tomaAnticipo + adicional) * 100) / 100;
    const restanteDeuda     = Math.max(0, Math.round((saldoVenta - totalCobro) * 100) / 100);
    const sobraEnAnticipo   = Math.max(0, Math.round((anticipoSaldo - tomaAnticipo) * 100) / 100);
    const anticipoCubreTodo = anticipoSel ? anticipoSaldo >= saldoVenta - 0.009 : false;

    const compraSeleccionada = comprasCompensables.find(c => c.id === compensarEntradaId) ?? null;
    const saldoCompra = (c: CompraCompensable) => Math.max(0, Number(c.total) - Number(c.monto_pagado));
    const nombreProveedorCompra = (c: CompraCompensable) =>
        c.proveedor_rel?.razon_social ?? c.proveedor_rel?.nombre_comercial ?? c.proveedor ?? '—';
    // Compras del MISMO RUC que el cliente de la venta van primero (es el caso típico:
    // el tercero es cliente y proveedor a la vez), pero se puede elegir cualquiera.
    const comprasOrdenadas = abonando
        ? [...comprasCompensables].sort((a, b) => {
            const ruc = abonando.cliente?.numero_documento ?? null;
            const am = ruc && a.proveedor_rel?.numero_documento === ruc ? 0 : 1;
            const bm = ruc && b.proveedor_rel?.numero_documento === ruc ? 0 : 1;
            return am - bm;
        })
        : comprasCompensables;
    const esMismoRuc = (c: CompraCompensable) =>
        !!abonando?.cliente?.numero_documento && c.proveedor_rel?.numero_documento === abonando.cliente.numero_documento;
    const topeCompensar = abonando && compraSeleccionada
        ? Math.round(Math.min(Number(abonando.saldo_pendiente), saldoCompra(compraSeleccionada)) * 100) / 100
        : Number(abonando?.saldo_pendiente ?? 0);
    // Edición / anulación de un abono ya registrado (según permisos).
    const [editandoAbono, setEditandoAbono] = useState<Abono | null>(null);
    const [anulandoAbono, setAnulandoAbono] = useState<Abono | null>(null);
    const [motivoAnular, setMotivoAnular]   = useState('');
    const [formAbono, setFormAbono] = useState({
        monto: '', fecha: '', metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
    });

    /** Tope al editar un abono: saldo pendiente de la venta + monto actual del abono. */
    const topeEditar = detalle && editandoAbono
        ? Math.round((Number(detalle.saldo_pendiente) + Number(editandoAbono.monto)) * 100) / 100
        : 0;

    function abrirEditarAbono(a: Abono) {
        setErrors({});
        valEditar.reiniciar();
        setFormAbono({
            monto:          String(Number(a.monto)),
            fecha:          a.fecha.slice(0, 10),
            metodo_pago_id: a.metodo_pago_id ? String(a.metodo_pago_id) : '',
            cuenta_id:      a.cuenta_id ? String(a.cuenta_id) : '',
            referencia:     a.referencia ?? '',
            observacion:    a.observacion ?? '',
        });
        setEditandoAbono(a);
    }

    function submitEditarAbono() {
        if (!editandoAbono) return;
        if (problemaEd) { valEditar.corregir(); return; }
        setSaving(true);
        router.put(route('finanzas.cxc.abonos.update', editandoAbono.id), {
            monto:          formAbono.monto,
            fecha:          formAbono.fecha,
            metodo_pago_id: formAbono.metodo_pago_id || null,
            cuenta_id:      formAbono.cuenta_id || null,
            referencia:     formAbono.referencia || null,
            observacion:    formAbono.observacion || null,
        } as any, {
            onSuccess: () => { setEditandoAbono(null); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function submitAnularAbono() {
        if (!anulandoAbono) return;
        if (problemaAnular) { valAnular.corregir(); return; }
        setSaving(true);
        router.delete(route('finanzas.cxc.abonos.destroy', anulandoAbono.id), {
            data: { motivo: motivoAnular.trim() },
            onSuccess: () => { setAnulandoAbono(null); setMotivoAnular(''); setDetalle(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        } as any);
    }

    // ── Validación en pantalla (mismas reglas que el backend) ──────────────
    // Cada función devuelve el PRIMER problema que impide guardar, en palabras
    // de la cajera; el botón lo muestra en ámbar y "Corregir" lleva al campo.

    /** Método elegido que tiene cuentas vinculadas → la cuenta es obligatoria. */
    const metodoConCuentas = (mid: string) => {
        const m = metodosPago.find(x => String(x.id) === String(mid));
        return m && (m.cuentas?.length ?? 0) > 0 ? m : null;
    };

    /** Monto: escrito, > 0 y ≤ tope. `que` = cómo se llama ese monto para la cajera. */
    function problemaMonto(valor: string, tope: number, campo: string, que: string, detalleTope: string): Problema | null {
        if (valor.trim() === '') return { texto: `Escribe ${que}`, campo };
        const n = Number(valor);
        if (!Number.isFinite(n) || n < 0.01) return { texto: 'El monto debe ser mayor a S/ 0.00', campo };
        if (n > tope + 0.009) return { texto: `El monto no puede pasar ${detalleTope} ${money(tope)}`, campo };
        return null;
    }

    /** Método + cuenta de un pago con dinero (PagoForm envuelto en data-campo="pago-…"). */
    function problemaMetodo(metodoId: string, cuentaId: string, contenedor: string, que = 'Elige el método de pago'): Problema | null {
        if (!metodoId) return { texto: que, campo: 'metodo_pago_id', foco: { campo: contenedor, indice: 0 } };
        const m = metodoConCuentas(metodoId);
        if (m && !cuentaId) return { texto: `Elige la cuenta de ${m.nombre}`, campo: 'cuenta_id', foco: { campo: contenedor, indice: 1 } };
        return null;
    }

    function problemaAbono(): Problema | null {
        if (!abonando) return null;
        if (usarAnticipo) {
            if (!anticipoId) return { texto: 'Elige el anticipo a consumir', campo: 'cliente_anticipo_id' };
            const pm = problemaMonto(montoAnticipo, topeAnticipo, 'monto', 'cuánto tomas del anticipo',
                anticipoSaldo < saldoVenta ? 'del saldo del anticipo' : 'del saldo de la venta');
            if (pm) return pm;
            if (!form.fecha) return { texto: 'Elige la fecha del cobro', campo: 'fecha' };
            if (pagoAdicional) {
                const pa = problemaMonto(montoAdicional, Math.max(0, Math.round((saldoVenta - tomaAnticipo) * 100) / 100),
                    'monto_adicional', 'el monto del pago adicional', 'de lo que falta:');
                if (pa) return pa;
                const pmet = problemaMetodo(form.metodo_pago_id, form.cuenta_id, 'pago-adicional', 'Elige el método del pago adicional');
                if (pmet) return pmet;
            }
            return null;
        }
        if (compensarActivo) {
            if (!compensarEntradaId) return { texto: 'Elige la compra contra la que compensas', campo: 'entrada_id' };
            const pm = problemaMonto(form.monto, topeCompensar, 'monto', 'el monto a compensar', 'del menor de los dos saldos:');
            if (pm) return pm;
            if (!form.fecha) return { texto: 'Elige la fecha', campo: 'fecha' };
            return null;
        }
        const pm = problemaMonto(form.monto, saldoVenta, 'monto', 'el monto del abono', 'del saldo');
        if (pm) return pm;
        if (!form.fecha) return { texto: 'Elige la fecha del abono', campo: 'fecha' };
        return problemaMetodo(form.metodo_pago_id, form.cuenta_id, 'pago-abono');
    }
    const problemaAb = problemaAbono();
    const valAbono   = useProblema(problemaAb, errors);

    function problemaEditarAbono(): Problema | null {
        if (!editandoAbono) return null;
        const pm = problemaMonto(formAbono.monto, topeEditar, 'monto', 'el monto del abono', 'del máximo permitido');
        if (pm) return pm;
        if (!formAbono.fecha) return { texto: 'Elige la fecha del abono', campo: 'fecha' };
        return problemaMetodo(formAbono.metodo_pago_id, formAbono.cuenta_id, 'pago-editar-abono');
    }
    const problemaEd = problemaEditarAbono();
    const valEditar  = useProblema(problemaEd, errors);

    const problemaAnular: Problema | null = motivoAnular.trim().length < 5
        ? { texto: motivoAnular.trim() === '' ? 'Escribe el motivo de la anulación' : 'El motivo debe tener al menos 5 letras', campo: 'motivo' }
        : null;
    const valAnular = useProblema(problemaAnular, errors);

    // La búsqueda server-side y la paginación las maneja el componente Table
    // (debounce 500 ms + loading interno + botones de páginas del servidor).

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function abrirAbono(v: VentaCxc) {
        setAbonando(v);
        setErrors({});
        valAbono.reiniciar();
        setCompensarActivo(false);
        setCompensarEntradaId('');
        setUsarAnticipo(false);
        setAnticipoId('');
        setMontoAnticipo('');
        setPagoAdicional(false);
        setMontoAdicional('');
        setForm({
            monto: String(v.saldo_pendiente), fecha: hoy(), metodo_pago_id: '', cuenta_id: '', referencia: '', observacion: '',
            // Cobro entra normalmente a la caja del cajero: preselecciona el turno activo.
            turno_id: turnoActivoId ? String(turnoActivoId) : '',
        });
    }

    function submitAbono() {
        if (!abonando) return;
        if (problemaAb) { valAbono.corregir(); return; }
        setSaving(true);
        if (usarAnticipo) {
            // Cobro del anticipo (sin caja) + pago adicional opcional (sí entra a caja).
            router.post(route('finanzas.cxc.abonar', abonando.id), {
                monto:               montoAnticipo,
                monto_adicional:     pagoAdicional && adicional > 0 ? montoAdicional : null,
                fecha:               form.fecha,
                cliente_anticipo_id: anticipoId || null,
                metodo_pago_id:      pagoAdicional ? (form.metodo_pago_id || null) : null,
                cuenta_id:           pagoAdicional ? (form.cuenta_id || null) : null,
                referencia:          pagoAdicional ? (form.referencia || null) : null,
                turno_id:            pagoAdicional ? (form.turno_id || null) : null,
                observacion:         form.observacion || null,
            } as any, {
                onSuccess: () => { setAbonando(null); setSaving(false); },
                onError:   (errs: any) => { setErrors(errs); setSaving(false); },
            });
            return;
        }
        if (compensarActivo) {
            // Compensación: no entra dinero — se cancela contra una compra.
            router.post(route('finanzas.compensaciones.cxc-cxp'), {
                venta_id:    abonando.id,
                entrada_id:  compensarEntradaId || null,
                monto:       form.monto,
                fecha:       form.fecha,
                observacion: form.observacion || null,
            } as any, {
                onSuccess: () => { setAbonando(null); setSaving(false); },
                onError:   (errs: any) => { setErrors(errs); setSaving(false); },
            });
            return;
        }
        router.post(route('finanzas.cxc.abonar', abonando.id), {
            ...form,
            metodo_pago_id: form.metodo_pago_id || null,
            cuenta_id:      form.cuenta_id || null,
            turno_id:       form.turno_id || null,
        } as any, {
            onSuccess: () => { setAbonando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const columns: Column<VentaCxc>[] = [
        {
            key: 'fecha_venta', label: 'Fecha', sortable: true,
            render: (v) => <span className="text-sm">{new Date(v.fecha_venta).toLocaleDateString('es-PE')}</span>,
        },
        { key: 'numero', label: 'N°', render: (v) => <span className="font-mono text-sm">{v.numero ?? '—'}</span> },
        { key: 'cliente', label: 'Cliente', sortKey: 'cliente.nombres', render: (v) => <span className="font-medium">{nombreCliente(v)}</span> },
        { key: 'total', label: 'Total', align: 'right', render: (v) => <span>{money(v.total)}</span> },
        { key: 'monto_pagado', label: 'Pagado', align: 'right', render: (v) => <span style={{ color: 'var(--color-success, #16a34a)' }}>{money(v.monto_pagado)}</span> },
        {
            key: 'saldo_pendiente', label: 'Saldo', sortable: true, align: 'right',
            render: (v) => Number(v.saldo_pendiente) > 0
                ? <span className="font-bold" style={{ color: 'var(--color-danger)' }}>{money(v.saldo_pendiente)}</span>
                : <Badge variant="success">Saldada</Badge>,
        },
        {
            key: 'fecha_vencimiento', label: 'Vence',
            render: (v) => {
                if (!v.fecha_vencimiento) return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;
                const vencida = Number(v.saldo_pendiente) > 0 && v.fecha_vencimiento < hoy();
                return (
                    <span className="text-sm" style={{ color: vencida ? 'var(--color-danger)' : 'var(--color-text)' }}>
                        {new Date(v.fecha_vencimiento + 'T00:00:00').toLocaleDateString('es-PE')}
                        {vencida && <Badge variant="danger">Vencida</Badge>}
                    </span>
                );
            },
        },
        {
            key: 'acciones', label: 'Acciones', sortable: false,
            render: (v) => (
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => { setDetalle(v); setVentaAbierta(false); }}
                        className="p-1.5 rounded-lg hover:bg-black/5"
                        title="Ver detalle y trazabilidad"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Eye size={15} />
                    </button>
                    {Number(v.saldo_pendiente) > 0 && (
                        <button
                            onClick={() => abrirAbono(v)}
                            className="p-1.5 rounded-lg hover:bg-black/5"
                            title="Abonar"
                            style={{ color: 'var(--color-success)' }}
                        >
                            <HandCoins size={15} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Cuentas por cobrar">
            <PageHeader
                icon={<HandCoins size={22} />}
                title="Cuentas por cobrar"
                subtitle="Ventas a crédito y abonos de clientes"
            />

            <div className="mb-5">
                <StatGrid size="lg" cols="grid-cols-2 lg:grid-cols-4" stats={[
                    {
                        label: 'Total por cobrar', valor: money(totalPendiente), color: 'danger', destacado: true,
                        icon: <HandCoins size={19} />, sub: 'Saldo pendiente de ventas a crédito',
                    },
                    {
                        label: 'Ventas con saldo', valor: kpis.ventas_con_saldo, color: 'primary',
                        icon: <Receipt size={19} />, sub: 'Créditos abiertos',
                    },
                    {
                        label: 'Clientes con deuda', valor: kpis.clientes_con_deuda, color: 'primary',
                        icon: <Users size={19} />, sub: 'Clientes distintos por cobrar',
                    },
                    {
                        label: 'Vencidas', valor: kpis.vencidas, color: 'warning',
                        icon: <CalendarClock size={19} />,
                        sub: kpis.vencidas > 0 ? `${money(kpis.monto_vencido)} pasada la fecha` : 'Nada fuera de fecha',
                    },
                ]} />
            </div>

            <FiltrosCard cols={3}>
                <Select label="Estado" value={estado}
                    onChange={(v) => router.get(route('finanzas.cxc.index'), { estado: v, busqueda: busqueda || undefined }, { preserveState: true, replace: true })}
                    options={[
                        { value: 'pendientes', label: 'Con saldo pendiente' },
                        { value: 'saldadas',   label: 'Saldadas' },
                        { value: 'todas',      label: 'Todas las ventas a crédito' },
                    ]} />
            </FiltrosCard>

            <Table
                data={ventas}
                columns={columns}
                searchPlaceholder="Buscar por número de venta o cliente..."
                emptyMessage="No hay ventas a crédito"
                initialSearch={busqueda}
                exportFilename="cuentas_por_cobrar"
                onServerSearch={(t) => router.get(route('finanzas.cxc.index'),
                    { estado, busqueda: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })}
                onExportExcel={() => {
                    const params = new URLSearchParams(window.location.search);
                    params.delete('page');
                    params.set('estado', estado);
                    if (busqueda) params.set('busqueda', busqueda);
                    else params.delete('busqueda');
                    const url = route('finanzas.cxc.exportar') + (params.toString() ? `?${params.toString()}` : '');
                    window.open(url, '_blank');
                }}
            />

            {/* Modal abonar */}
            <Modal
                isOpen={abonando !== null}
                onClose={() => setAbonando(null)}
                title={abonando ? `Abonar venta ${abonando.numero ?? ''} — ${nombreCliente(abonando)}` : ''}
                size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAbonando(null)}>Cancelar</Button>
                        <BotonGuardar problema={problemaAb} onGuardar={submitAbono} onCorregir={valAbono.corregir} guardando={saving}>
                            {saving ? 'Guardando...'
                                : usarAnticipo ? `Cobrar ${money(totalCobro)}`
                                : compensarActivo ? 'Compensar'
                                : 'Registrar abono'}
                        </BotonGuardar>
                    </>
                }
            >
                {abonando && (
                    <div className="space-y-4">
                        {/* Errores del servidor sin campo visible en el modo actual. */}
                        <ErroresSueltos errors={errors} visibles={[
                            'monto', 'fecha', 'observacion',
                            ...(usarAnticipo
                                ? ['cliente_anticipo_id', ...(pagoAdicional ? ['monto_adicional', 'metodo_pago_id', 'cuenta_id', 'referencia', 'turno_id'] : [])]
                                : compensarActivo ? ['entrada_id'] : ['metodo_pago_id', 'cuenta_id', 'referencia', 'turno_id']),
                        ].filter(k => !(k === 'cliente_anticipo_id' && anticiposDelCliente.length <= 1))} />
                        <StatGrid stats={[
                            { label: 'Total', valor: money(abonando.total) },
                            { label: 'Pagado', valor: money(abonando.monto_pagado), color: 'success' },
                            { label: 'Saldo pendiente', valor: money(abonando.saldo_pendiente), color: 'danger', destacado: true },
                        ]} />
                        <div className="grid grid-cols-2 gap-3">
                            {/* Con anticipo activo el monto se maneja en el DESGLOSE de
                                abajo (del anticipo + adicional): este input se oculta
                                para que la cajera no vea dos montos y se confunda. */}
                            {!usarAnticipo && (
                                <Input label="Monto del abono" required type="number" min="0.01" step="0.01"
                                    max={compensarActivo ? topeCompensar : Number(abonando.saldo_pendiente)}
                                    data-campo="monto"
                                    value={form.monto}
                                    onChange={e => setForm(f => ({ ...f, monto: e.target.value }))}
                                    error={valAbono.err('monto')}
                                />
                            )}
                            <Input label="Fecha" required type="date"
                                data-campo="fecha"
                                value={form.fecha}
                                onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))}
                                error={valAbono.err('fecha')}
                            />
                        </div>

                        {/* Nuevo saldo en vivo + validación de tope */}
                        {!usarAnticipo && form.monto !== '' && Number(form.monto) > 0 && (() => {
                            const saldo = Number(abonando.saldo_pendiente);
                            const montoNum = Number(form.monto);
                            const nuevo = Math.round((saldo - montoNum) * 100) / 100;
                            if (montoNum > saldo + 0.009) {
                                return (
                                    <Callout variant="danger">
                                        El abono ({money(montoNum)}) no puede superar el saldo pendiente ({money(saldo)}).
                                    </Callout>
                                );
                            }
                            return nuevo <= 0.009
                                ? <Callout variant="success" title="Con este abono la venta queda SALDADA" />
                                : <Callout variant="info" title="Nuevo saldo pendiente" aside={money(nuevo)} />;
                        })()}
                        {/* Cobrar del anticipo del cliente: el dinero ya entró cuando
                            dejó el anticipo — baja su deuda y su anticipo a la vez,
                            sin mover caja. */}
                        {anticiposDelCliente.length > 0 && (
                            <Callout variant="info" title="Este cliente tiene anticipos de dinero" aside={money(saldoAnticiposDisponibles)}>
                                <label className="flex items-center gap-2 text-sm cursor-pointer select-none">
                                    <input type="checkbox" checked={usarAnticipo}
                                        onChange={e => {
                                            const on = e.target.checked;
                                            setUsarAnticipo(on);
                                            setErrors({});
                                            if (on) {
                                                setCompensarActivo(false);
                                                setCompensarEntradaId('');
                                            }
                                            // Con un solo anticipo lo elegimos y precargamos el monto
                                            // AUTOMÁTICO: lo que la deuda necesite, nunca más.
                                            const unico = on && anticiposDelCliente.length === 1 ? anticiposDelCliente[0] : null;
                                            setAnticipoId(unico ? unico.id : '');
                                            setMontoAnticipo(unico && abonando
                                                ? Math.min(Number(abonando.saldo_pendiente), Number(unico.saldo)).toFixed(2)
                                                : '');
                                            setPagoAdicional(false);
                                            setMontoAdicional('');
                                            if (!on) {
                                                setForm(f => ({ ...f, monto: String(abonando?.saldo_pendiente ?? ''), metodo_pago_id: '', cuenta_id: '' }));
                                            }
                                        }}
                                        className="h-4 w-4 accent-[var(--color-primary)]"
                                    />
                                    <span style={{ color: 'var(--color-text)' }}>Cobrar consumiendo el anticipo del cliente (sin mover caja)</span>
                                </label>
                            </Callout>
                        )}

                        {usarAnticipo && (
                            <div className="space-y-3">
                                {anticiposDelCliente.length > 1 && (
                                    <Select label="Anticipo a consumir" required
                                        triggerAttrs={{ 'data-campo': 'cliente_anticipo_id' }}
                                        options={anticiposDelCliente.map(a => ({
                                            value: String(a.id),
                                            label: `Anticipo #${a.id} — ${new Date(a.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')} — saldo ${money(a.saldo)}`,
                                        }))}
                                        value={anticipoId === '' ? '' : String(anticipoId)}
                                        onChange={v => {
                                            const id = v === '' ? '' : Number(v);
                                            setAnticipoId(id);
                                            const a = anticiposDelCliente.find(x => x.id === id);
                                            setMontoAnticipo(a && abonando
                                                ? Math.min(Number(abonando.saldo_pendiente), Number(a.saldo)).toFixed(2)
                                                : '');
                                            setErrors({});
                                        }}
                                        placeholder="— Seleccionar —"
                                        error={valAbono.err('cliente_anticipo_id')}
                                    />
                                )}

                                {anticipoSel && (
                                    <>
                                        {/* Mensaje INTELIGENTE según alcance el anticipo o no */}
                                        {anticipoCubreTodo ? (
                                            <Callout variant="success" title="El anticipo alcanza para toda la deuda">
                                                Se tomarán solo <strong>{money(topeAnticipo)}</strong> del anticipo #{anticipoSel.id} (lo que la deuda necesita).
                                                {sobraEnAnticipo > 0.009 && <> Los <strong>{money(sobraEnAnticipo)}</strong> restantes SIGUEN a favor del cliente en su anticipo.</>}
                                                {' '}Sin ningún movimiento de caja.
                                            </Callout>
                                        ) : (
                                            <Callout variant="warning" title={`El anticipo solo cubre ${money(topeAnticipo)} de los ${money(saldoVenta)}`}>
                                                Puedes cobrar el resto ahora mismo agregando un pago adicional abajo, o dejarlo como saldo pendiente de la venta.
                                            </Callout>
                                        )}

                                        <Input label="Monto a tomar del anticipo (S/)" required type="number" min="0.01" step="0.01"
                                            max={topeAnticipo}
                                            data-campo="monto"
                                            value={montoAnticipo}
                                            onChange={e => setMontoAnticipo(e.target.value)}
                                            error={valAbono.err('monto')}
                                            hint={`Máximo ${money(topeAnticipo)} (saldo del anticipo: ${money(anticipoSaldo)})`}
                                        />
                                        {tomaAnticipo > topeAnticipo + 0.009 && (
                                            <Callout variant="danger">
                                                No puedes tomar más de {money(topeAnticipo)}: es lo que {anticipoSaldo < saldoVenta ? 'tiene el anticipo' : 'necesita la deuda'}.
                                            </Callout>
                                        )}

                                        {/* Pago adicional en la MISMA operación (mixto) */}
                                        <label className="flex items-center gap-2 text-sm cursor-pointer select-none">
                                            <input type="checkbox" checked={pagoAdicional}
                                                onChange={e => {
                                                    const on = e.target.checked;
                                                    setPagoAdicional(on);
                                                    setErrors({});
                                                    // Precarga el adicional con lo que falta para saldar.
                                                    setMontoAdicional(on ? restanteDeuda.toFixed(2) : '');
                                                    if (!on) setForm(f => ({ ...f, metodo_pago_id: '', cuenta_id: '', referencia: '' }));
                                                }}
                                                className="h-4 w-4 accent-[var(--color-primary)]"
                                            />
                                            <span style={{ color: 'var(--color-text)' }}>
                                                Agregar un pago adicional ahora (efectivo, yape, transferencia…)
                                            </span>
                                        </label>

                                        {pagoAdicional && (
                                            <div className="space-y-3 rounded-xl p-3" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                                <Input label="Monto adicional (S/)" required type="number" min="0.01" step="0.01"
                                                    max={Math.max(0, Math.round((saldoVenta - tomaAnticipo) * 100) / 100)}
                                                    data-campo="monto_adicional"
                                                    value={montoAdicional}
                                                    onChange={e => setMontoAdicional(e.target.value)}
                                                    error={valAbono.err('monto_adicional')}
                                                />
                                                <div data-campo="pago-adicional">
                                                <PagoForm
                                                    value={{
                                                        metodo_pago_id: form.metodo_pago_id,
                                                        cuenta_id: form.cuenta_id,
                                                        referencia: form.referencia,
                                                    }}
                                                    onChange={v => setForm(f => ({
                                                        ...f,
                                                        metodo_pago_id: v.metodo_pago_id ? String(v.metodo_pago_id) : '',
                                                        cuenta_id: v.cuenta_id ? String(v.cuenta_id) : '',
                                                        referencia: v.referencia ?? '',
                                                    }))}
                                                    metodosPago={metodosPago}
                                                    cuentas={cuentas}
                                                    errors={{ ...errors, ...valAbono.errs('metodo_pago_id', 'cuenta_id') }}
                                                    required={true}
                                                    showObservacion={false}
                                                />
                                                </div>
                                                <AfectaCajaSelect
                                                    modulo="cxc" modo="libre" formato="largo"
                                                    label="El pago adicional afecta caja a (turno)"
                                                    sinTurnoLabel="Sin turno (no afecta caja)"
                                                    turnos={turnos}
                                                    value={form.turno_id === '' ? '' : Number(form.turno_id)}
                                                    onChange={v => setForm(f => ({ ...f, turno_id: v === '' ? '' : String(v) }))}
                                                    error={errors.turno_id}
                                                    hint="Solo el pago adicional entra a caja; lo del anticipo no mueve caja."
                                                />
                                            </div>
                                        )}

                                        {/* DESGLOSE explícito: qué se cobra, de dónde y qué queda */}
                                        <div className="rounded-xl p-3 space-y-1.5 text-sm" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                                            <div className="flex justify-between">
                                                <span style={{ color: 'var(--color-text-muted)' }}>Del anticipo #{anticipoSel.id} (sin caja)</span>
                                                <span className="font-semibold tabular-nums">{money(tomaAnticipo)}</span>
                                            </div>
                                            {pagoAdicional && adicional > 0 && (
                                                <div className="flex justify-between">
                                                    <span style={{ color: 'var(--color-text-muted)' }}>
                                                        Pago adicional{form.metodo_pago_id ? ` (${metodosPago.find(m => String(m.id) === form.metodo_pago_id)?.nombre ?? ''})` : ''} — entra a caja
                                                    </span>
                                                    <span className="font-semibold tabular-nums">{money(adicional)}</span>
                                                </div>
                                            )}
                                            <div className="flex justify-between pt-1.5" style={{ borderTop: '1px solid var(--color-border)' }}>
                                                <span className="font-semibold">Total del cobro</span>
                                                <span className="font-bold tabular-nums" style={{ color: 'var(--color-primary)' }}>{money(totalCobro)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span style={{ color: 'var(--color-text-muted)' }}>La venta queda</span>
                                                {restanteDeuda <= 0.009
                                                    ? <span className="font-semibold" style={{ color: 'var(--color-success)' }}>SALDADA ✓</span>
                                                    : <span className="font-semibold" style={{ color: 'var(--color-danger)' }}>debiendo {money(restanteDeuda)}</span>}
                                            </div>
                                            {sobraEnAnticipo > 0.009 && (
                                                <div className="flex justify-between">
                                                    <span style={{ color: 'var(--color-text-muted)' }}>En su anticipo le queda</span>
                                                    <span className="font-semibold tabular-nums" style={{ color: 'var(--color-success)' }}>{money(sobraEnAnticipo)}</span>
                                                </div>
                                            )}
                                        </div>
                                    </>
                                )}

                                <Input label="Observación"
                                    value={form.observacion}
                                    onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))}
                                />
                            </div>
                        )}

                        {/* Compensar contra una compra: el cobro NO entra como dinero,
                            se cancela contra lo que le debemos al mismo tercero como
                            proveedor. Cero movimientos de caja. */}
                        {!usarAnticipo && puedeCompensar && comprasCompensables.length > 0 && (
                            <label className="flex items-center gap-2 text-sm cursor-pointer select-none">
                                <input type="checkbox" checked={compensarActivo}
                                    onChange={e => {
                                        setCompensarActivo(e.target.checked);
                                        setCompensarEntradaId('');
                                        setErrors({});
                                    }} />
                                <span style={{ color: 'var(--color-text)' }}>
                                    Compensar con una compra (Cuentas por Pagar) — sin mover caja
                                </span>
                            </label>
                        )}

                        {usarAnticipo ? null : compensarActivo ? (
                            <div className="space-y-3">
                                <div data-campo="entrada_id">
                                <SearchableSelect
                                    label="Compra contra la que se compensa"
                                    required
                                    placeholder="— Seleccionar compra con saldo —"
                                    searchPlaceholder="Buscar por proveedor, correlativo o documento..."
                                    value={compensarEntradaId}
                                    onChange={v => {
                                        const id = v === '' ? '' : Number(v);
                                        setCompensarEntradaId(id);
                                        // Precarga el monto con el máximo compensable.
                                        const c = comprasCompensables.find(x => x.id === id);
                                        if (c && abonando) {
                                            const tope = Math.min(Number(abonando.saldo_pendiente), saldoCompra(c));
                                            setForm(f => ({ ...f, monto: tope.toFixed(2) }));
                                        }
                                    }}
                                    options={comprasOrdenadas.map(c => ({
                                        value: c.id,
                                        label: `${c.correlativo ?? c.numero_documento ?? `#${c.id}`} — ${nombreProveedorCompra(c)} — saldo ${money(saldoCompra(c))}${esMismoRuc(c) ? ' · mismo RUC' : ''}`,
                                    }))}
                                    error={valAbono.err('entrada_id')}
                                />
                                </div>
                                {compraSeleccionada && (
                                    <Callout variant="info" title={`Máximo compensable: ${money(topeCompensar)}`}>
                                        Se registrará un abono en esta venta y un pago en la compra {compraSeleccionada.correlativo ?? compraSeleccionada.numero_documento ?? ''} por el mismo monto, <strong>sin ningún movimiento de caja</strong>. Ambos saldos bajan a la vez.
                                    </Callout>
                                )}
                                <Input label="Observación"
                                    value={form.observacion}
                                    onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))}
                                />
                            </div>
                        ) : (
                            <>
                                <div data-campo="pago-abono">
                                <PagoForm
                                    value={{
                                        metodo_pago_id: form.metodo_pago_id,
                                        cuenta_id: form.cuenta_id,
                                        referencia: form.referencia,
                                    }}
                                    onChange={v => setForm(f => ({
                                        ...f,
                                        metodo_pago_id: v.metodo_pago_id ? String(v.metodo_pago_id) : '',
                                        cuenta_id: v.cuenta_id ? String(v.cuenta_id) : '',
                                        referencia: v.referencia ?? '',
                                    }))}
                                    metodosPago={metodosPago}
                                    cuentas={cuentas}
                                    errors={{ ...errors, ...valAbono.errs('metodo_pago_id', 'cuenta_id') }}
                                    required={true}
                                    showObservacion={false}
                                />
                                </div>
                                <Input label="Observación"
                                    value={form.observacion}
                                    onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))}
                                />

                                {/* "Afecta caja a:" — a qué caja/turno entra el cobro (opt-in, modo
                                    libre). Preseleccionado con el turno activo; se auto-oculta si la
                                    empresa apaga el módulo 'cxc'. */}
                                <AfectaCajaSelect
                                    modulo="cxc" modo="libre" formato="largo"
                                    label="Afecta caja a (turno)"
                                    sinTurnoLabel="Sin turno (no afecta caja)"
                                    turnos={turnos}
                                    value={form.turno_id === '' ? '' : Number(form.turno_id)}
                                    onChange={v => setForm(f => ({ ...f, turno_id: v === '' ? '' : String(v) }))}
                                    error={errors.turno_id}
                                    hint="A qué caja entra el efectivo, para que la consolidación de ese turno lo sume."
                                />
                            </>
                        )}
                    </div>
                )}
            </Modal>

            {/* Modal detalle de la cuenta por cobrar — trazabilidad completa */}
            <Modal
                isOpen={detalle !== null}
                onClose={() => setDetalle(null)}
                title={detalle ? `Detalle — venta ${detalle.numero ?? ''} · ${nombreCliente(detalle)}` : ''}
                size="3xl"
                footer={
                    <>
                        {detalle && Number(detalle.saldo_pendiente) > 0 && (
                            <Button variant="primary" onClick={() => { const d = detalle; setDetalle(null); abrirAbono(d); }}>
                                <HandCoins size={15} className="mr-1.5" /> Registrar abono
                            </Button>
                        )}
                        <Button variant="ghost" onClick={() => setDetalle(null)}>Cerrar</Button>
                    </>
                }
            >
                {detalle && (
                    <div className="space-y-4">
                        {/* Resumen del crédito */}
                        <StatGrid stats={[
                            { label: 'Total', valor: money(detalle.total) },
                            { label: 'Pagado', valor: money(detalle.monto_pagado), color: 'success' },
                            { label: 'Saldo pendiente', valor: money(detalle.saldo_pendiente), color: 'danger', destacado: true },
                        ]} />

                        {/* Datos generales / trazabilidad */}
                        <div className="rounded-xl p-3 grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2.5"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <Dato label="Cliente" valor={nombreCliente(detalle)} />
                            <Dato label="Comprobante" valor={detalle.tipo_comprobante ?? '—'} capitalize />
                            <Dato label="Vendedor" valor={detalle.user?.name ?? '—'} />
                            <Dato label="Fecha de venta" valor={new Date(detalle.fecha_venta).toLocaleString('es-PE')} />
                            <Dato label="Vencimiento" valor={detalle.fecha_vencimiento
                                ? new Date(detalle.fecha_vencimiento + 'T00:00:00').toLocaleDateString('es-PE') : '—'} />
                            <Dato label="Caja" valor={detalle.caja?.nombre ?? '—'} />
                        </div>

                        {/* Venta relacionada — minimizada, se despliega con clic */}
                        <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            <button
                                onClick={() => setVentaAbierta(v => !v)}
                                className="w-full flex items-center justify-between px-3 py-2.5 transition-colors hover:bg-black/[0.03]"
                                style={{ backgroundColor: 'var(--color-bg)' }}
                            >
                                <span className="flex items-center gap-2 text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                    <Receipt size={15} style={{ color: 'var(--color-primary)' }} />
                                    Venta relacionada {detalle.numero ?? ''}
                                    <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                        · {detalle.items?.length ?? 0} producto(s)
                                    </span>
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="font-bold text-sm" style={{ color: 'var(--color-primary)' }}>{money(detalle.total)}</span>
                                    {ventaAbierta ? <ChevronDown size={16} style={{ color: 'var(--color-text-muted)' }} /> : <ChevronRight size={16} style={{ color: 'var(--color-text-muted)' }} />}
                                </span>
                            </button>

                            {ventaAbierta && (
                                <div className="p-3 space-y-3" style={{ borderTop: '1px solid var(--color-border)' }}>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-xs">
                                            <thead>
                                                <tr style={{ color: 'var(--color-text-muted)' }}>
                                                    <th className="text-left py-1.5 font-medium">Producto</th>
                                                    <th className="text-right py-1.5 font-medium">Cant.</th>
                                                    <th className="text-right py-1.5 font-medium">P. Unit.</th>
                                                    <th className="text-right py-1.5 font-medium">Dcto.</th>
                                                    <th className="text-right py-1.5 font-medium">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {(detalle.items ?? []).map(it => (
                                                    <tr key={it.id} style={{ borderTop: '1px solid var(--color-border)' }}>
                                                        <td className="py-1.5">
                                                            <span className="font-medium" style={{ color: 'var(--color-text)' }}>{it.producto_nombre}</span>
                                                            <span className="block text-[11px]" style={{ color: 'var(--color-text-muted)' }}>{it.unidad_nombre}</span>
                                                        </td>
                                                        <td className="text-right" style={{ color: 'var(--color-text)' }}>{Number(it.cantidad)}</td>
                                                        <td className="text-right" style={{ color: 'var(--color-text)' }}>{money(it.precio_unitario)}</td>
                                                        <td className="text-right" style={{ color: Number(it.descuento_item) > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                                            {Number(it.descuento_item) > 0 ? '- ' + money(it.descuento_item) : '—'}
                                                        </td>
                                                        <td className="text-right font-medium" style={{ color: 'var(--color-text)' }}>{money(it.subtotal)}</td>
                                                    </tr>
                                                ))}
                                                {(detalle.items?.length ?? 0) === 0 && (
                                                    <tr><td colSpan={5} className="py-3 text-center" style={{ color: 'var(--color-text-muted)' }}>Sin productos</td></tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Resumen financiero de la venta */}
                                    <div className="flex flex-col gap-1 items-end pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                                        <Resumen label="Subtotal" valor={money(detalle.subtotal ?? 0)} />
                                        {Number(detalle.descuento_total ?? 0) > 0 && <Resumen label="Descuento" valor={'- ' + money(detalle.descuento_total)} danger />}
                                        <Resumen label="IGV" valor={money(detalle.igv ?? 0)} />
                                        <Resumen label="Total" valor={money(detalle.total)} bold />
                                    </div>

                                    {detalle.observacion && (
                                        <p className="text-xs pt-1" style={{ color: 'var(--color-text-muted)' }}>
                                            <span className="font-medium">Observación:</span> {detalle.observacion}
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Historial de pagos y abonos */}
                        <div>
                            <p className="text-sm font-semibold mb-2" style={{ color: 'var(--color-text)' }}>
                                Historial de pagos y abonos
                            </p>
                            {(puede.editar || puede.eliminar) ? (
                                (() => {
                                    const pagosIniciales = detalle.pagos.filter(p => Number(p.monto) - Number(p.vuelto) > 0);
                                    const totalFilas = pagosIniciales.length + detalle.abonos.length;
                                    if (totalFilas === 0) {
                                        return <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin pagos registrados</p>;
                                    }
                                    return (
                                        <div className="space-y-3">
                                            <div className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                                                {pagosIniciales.map((p, idx) => (
                                                    <div key={`pi-${p.id}`} className="flex items-center gap-3 px-4 py-2.5"
                                                        style={{
                                                            borderBottom: idx < totalFilas - 1 ? '1px solid var(--color-border)' : undefined,
                                                            backgroundColor: idx % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                                        }}>
                                                        <Badge variant="primary">Pago inicial</Badge>
                                                        <div className="flex-1 min-w-0 text-xs">
                                                            <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                                                                {new Date(detalle.fecha_venta).toLocaleDateString('es-PE')}
                                                                <span className="ml-2 font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                                                    {[p.metodo_pago?.nombre, 'al momento de la venta'].filter(Boolean).join(' · ')}
                                                                </span>
                                                            </p>
                                                            {detalle.user?.name && (
                                                                <p className="truncate" style={{ color: 'var(--color-text-muted)' }}>por {detalle.user.name}</p>
                                                            )}
                                                        </div>
                                                        <span className="font-bold text-sm whitespace-nowrap" style={{ color: 'var(--color-success)' }}>
                                                            +{money(Number(p.monto) - Number(p.vuelto))}
                                                        </span>
                                                    </div>
                                                ))}
                                                {detalle.abonos.map((a, idx) => (
                                                    <div key={`ab-${a.id}`} className="flex items-center gap-3 px-4 py-2.5"
                                                        style={{
                                                            borderBottom: pagosIniciales.length + idx < totalFilas - 1 ? '1px solid var(--color-border)' : undefined,
                                                            backgroundColor: (pagosIniciales.length + idx) % 2 === 0 ? 'var(--color-surface)' : 'var(--color-bg)',
                                                        }}>
                                                        <Badge variant="success">Abono</Badge>
                                                        <div className="flex-1 min-w-0 text-xs">
                                                            <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                                                                {new Date(a.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}
                                                                <span className="ml-2 font-normal" style={{ color: 'var(--color-text-muted)' }}>
                                                                    {[a.compensacion_grupo_id ? 'Compensación con compra (sin caja)' : null, a.cliente_anticipo_id ? `Consumió anticipo #${a.cliente_anticipo_id} (sin caja)` : null, a.metodo_pago?.nombre, a.cuenta?.nombre, a.referencia].filter(Boolean).join(' · ') || '—'}
                                                                </span>
                                                            </p>
                                                            {(a.observacion || a.user?.name) && (
                                                                <p className="truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                                    {[a.observacion, a.user?.name ? `por ${a.user.name}` : null].filter(Boolean).join(' · ')}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <span className="font-bold text-sm whitespace-nowrap" style={{ color: 'var(--color-success)' }}>
                                                            +{money(a.monto)}
                                                        </span>
                                                        <div className="flex items-center gap-1 flex-shrink-0">
                                                            {puede.editar && !a.compensacion_grupo_id && !a.cliente_anticipo_id && (
                                                                <button onClick={() => abrirEditarAbono(a)}
                                                                    className="p-1.5 rounded-lg hover:bg-black/5" title="Editar abono"
                                                                    style={{ color: 'var(--color-primary)' }}>
                                                                    <Pencil size={14} />
                                                                </button>
                                                            )}
                                                            {puede.eliminar && (
                                                                <button onClick={() => { setErrors({}); valAnular.reiniciar(); setMotivoAnular(''); setAnulandoAbono(a); }}
                                                                    className="p-1.5 rounded-lg hover:bg-black/5" title="Anular abono"
                                                                    style={{ color: 'var(--color-danger)' }}>
                                                                    <Trash2 size={14} />
                                                                </button>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            {detalle.abonos.length > 0 && (
                                                <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                                    Editar o anular un abono recalcula tesorería y el saldo pendiente de la venta automáticamente. Todo queda en auditoría.
                                                </p>
                                            )}
                                        </div>
                                    );
                                })()
                            ) : (
                                <Timeline
                                    emptyMessage="Sin pagos registrados"
                                    items={[
                                        ...detalle.pagos.filter(p => Number(p.monto) - Number(p.vuelto) > 0).map(p => ({
                                            fecha: new Date(detalle.fecha_venta).toLocaleDateString('es-PE'),
                                            badge: { texto: 'Pago inicial', variant: 'primary' as const },
                                            tipo: 'ingreso' as const,
                                            detalle: [p.metodo_pago?.nombre, 'al momento de la venta'].filter(Boolean).join(' · '),
                                            user: detalle.user?.name,
                                            monto: Number(p.monto) - Number(p.vuelto),
                                        })),
                                        ...detalle.abonos.map(a => ({
                                            fecha: new Date(a.fecha + 'T00:00:00').toLocaleDateString('es-PE'),
                                            badge: { texto: 'Abono', variant: 'success' as const },
                                            tipo: 'ingreso' as const,
                                            detalle: [a.compensacion_grupo_id ? 'Compensación con compra (sin caja)' : null, a.cliente_anticipo_id ? `Consumió anticipo #${a.cliente_anticipo_id} (sin caja)` : null, a.metodo_pago?.nombre, a.cuenta?.nombre, a.referencia].filter(Boolean).join(' · ') || undefined,
                                            user: a.user?.name,
                                            monto: Number(a.monto),
                                        })),
                                    ]}
                                />
                            )}
                        </div>
                    </div>
                )}
            </Modal>

            {/* Modal editar abono */}
            <Modal isOpen={editandoAbono !== null} onClose={() => setEditandoAbono(null)}
                title={editandoAbono ? `Editar abono — ${money(editandoAbono.monto)} del ${new Date(editandoAbono.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}` : ''} size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditandoAbono(null)}>Cancelar</Button>
                        <BotonGuardar problema={problemaEd} onGuardar={submitEditarAbono} onCorregir={valEditar.corregir} guardando={saving}>
                            {saving ? 'Guardando...' : 'Guardar cambios'}
                        </BotonGuardar>
                    </>
                }
            >
                {editandoAbono && (
                    <div className="space-y-4">
                        <ErroresSueltos errors={errors} visibles={['monto', 'fecha', 'metodo_pago_id', 'cuenta_id', 'referencia', 'observacion']} />
                        <Callout variant="info">
                            Al guardar, el ingreso en tesorería se revierte y se vuelve a asentar con los datos nuevos; el saldo pendiente de la venta se recalcula.
                        </Callout>
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Monto" required type="number" min="0.01" step="0.01"
                                max={topeEditar}
                                data-campo="monto"
                                value={formAbono.monto}
                                onChange={e => setFormAbono(f => ({ ...f, monto: e.target.value }))}
                                error={valEditar.err('monto')}
                            />
                            <Input label="Fecha" required type="date" value={formAbono.fecha}
                                data-campo="fecha"
                                onChange={e => setFormAbono(f => ({ ...f, fecha: e.target.value }))}
                                error={valEditar.err('fecha')}
                            />
                        </div>
                        {/* Validación en vivo del tope */}
                        {formAbono.monto !== '' && Number(formAbono.monto) > 0 && (
                            Number(formAbono.monto) > topeEditar + 0.009
                                ? (
                                    <Callout variant="danger">
                                        El abono ({money(formAbono.monto)}) no puede superar el máximo permitido ({money(topeEditar)} = saldo pendiente + monto actual del abono).
                                    </Callout>
                                )
                                : <Callout variant="info" title="Máximo permitido" aside={money(topeEditar)} />
                        )}
                        <div data-campo="pago-editar-abono">
                        <PagoForm
                            value={{
                                metodo_pago_id: formAbono.metodo_pago_id,
                                cuenta_id: formAbono.cuenta_id,
                                referencia: formAbono.referencia,
                                observacion: formAbono.observacion,
                            }}
                            onChange={v => setFormAbono(f => ({
                                ...f,
                                metodo_pago_id: v.metodo_pago_id ? String(v.metodo_pago_id) : '',
                                cuenta_id: v.cuenta_id ? String(v.cuenta_id) : '',
                                referencia: v.referencia ?? '',
                                observacion: v.observacion ?? '',
                            }))}
                            metodosPago={metodosPago}
                            cuentas={cuentas}
                            errors={{ ...errors, ...valEditar.errs('metodo_pago_id', 'cuenta_id') }}
                            required={true}
                        />
                        </div>
                    </div>
                )}
            </Modal>

            {/* Modal anular abono */}
            <Modal isOpen={anulandoAbono !== null} onClose={() => setAnulandoAbono(null)}
                title={anulandoAbono ? `Anular abono — ${money(anulandoAbono.monto)} del ${new Date(anulandoAbono.fecha.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE')}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAnulandoAbono(null)}>Cancelar</Button>
                        <BotonGuardar variant="danger" problema={problemaAnular} onGuardar={submitAnularAbono} onCorregir={valAnular.corregir} guardando={saving}>
                            {saving ? 'Anulando...' : 'Sí, anular abono'}
                        </BotonGuardar>
                    </>
                }
            >
                {anulandoAbono && (
                    <div className="space-y-3">
                        <Callout variant="warning">
                            Se revierte el ingreso en tesorería y la venta recupera su saldo pendiente.
                        </Callout>
                        <ErroresSueltos errors={errors} visibles={['motivo']} />
                        <Input label="Motivo (mínimo 5 caracteres)" required value={motivoAnular}
                            data-campo="motivo" maxLength={500}
                            onChange={e => setMotivoAnular(e.target.value)}
                            placeholder="Ej.: se registró doble / monto equivocado"
                            error={valAnular.err('motivo')}
                        />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}

/** Celda etiqueta/valor para la trazabilidad. */
function Dato({ label, valor, capitalize }: { label: string; valor: string; capitalize?: boolean }) {
    return (
        <div className="min-w-0">
            <p className="text-[11px] font-medium uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className={`text-sm truncate ${capitalize ? 'capitalize' : ''}`} style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}

/** Fila del resumen financiero de la venta. */
function Resumen({ label, valor, bold, danger }: { label: string; valor: string; bold?: boolean; danger?: boolean }) {
    return (
        <div className="flex items-center justify-between gap-8 text-xs w-full max-w-[220px]">
            <span style={{ color: 'var(--color-text-muted)' }}>{label}</span>
            <span className={bold ? 'font-bold text-sm' : 'font-medium'}
                style={{ color: danger ? 'var(--color-danger)' : 'var(--color-text)' }}>
                {valor}
            </span>
        </div>
    );
}
