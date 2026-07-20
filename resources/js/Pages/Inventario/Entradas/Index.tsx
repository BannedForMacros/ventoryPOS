import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, CheckCircle, Search, Edit2, Trash2, Package, FileText, Wallet, Eye, Loader2 } from 'lucide-react';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import { fmtFecha } from '@/lib/fechas';
import type { PageProps } from '@/types';

interface Almacen { id: number; nombre: string; local?: { nombre: string } | null; }
interface UserItem { id: number; name: string; }
interface CuentaMP { id: number; nombre: string; banco: string | null; numero_cuenta: string | null; }
interface MetodoPagoOpt { id: number; nombre: string; cuentas: CuentaMP[]; }

interface DetalleItem {
    id: number;
    cantidad: string;
    factor_conversion: string;
    cantidad_base: string;
    precio_costo: string;
    subtotal: string;
    numero_documento: string | null;
    producto: { id: number; nombre: string; codigo: string | null } | null;
    unidad_medida: { id: number; nombre: string; abreviatura: string } | null;
}

interface EntradaDetallada extends Entrada {
    observacion: string | null;
    detalles: DetalleItem[];
    proveedor_rel?: { razon_social: string | null; nombre_comercial: string | null; numero_documento: string | null; tipo_documento: string } | null;
}
interface Entrada extends Record<string, unknown> {
    id: number;
    fecha: string;
    tipo: string;
    proveedor: string | null;
    correlativo: string | null;
    numero_documento: string | null;
    almacen: Almacen;
    user: UserItem;
    total: string;
    estado: 'borrador' | 'confirmado';
    estado_pago: 'pendiente' | 'parcial' | 'pagado';
    monto_pagado: string;
    metodo_pago_id: number | null;
    cuenta_id: number | null;
    metodo_pago?: { id: number; nombre: string } | null;
    cuenta?: { id: number; nombre: string } | null;
}

// M19: paginado server-side. El filtro de almacén/estado se mantiene client-side
// sobre la página actual; en el futuro conviene mover los filtros al server
// para combinar paginación + filtro correctamente.
interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Props extends PageProps {
    entradas: Paginado<Entrada>;
    almacenes: Almacen[];
    metodosPago: MetodoPagoOpt[];
    mostrarSelector: boolean;
    filters: Record<string, string>;
}

const TIPOS: Record<string, string> = {
    compra: 'Compra', ajuste: 'Ajuste', devolucion: 'Devolución', otro: 'Otro',
};

/** Item de metadato label+valor para el modal Ver detalle. */
function Meta({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <p className="text-[10px] uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className={`text-sm ${mono ? 'font-mono' : ''}`} style={{ color: 'var(--color-text)' }}>{value}</p>
        </div>
    );
}

export default function EntradasIndex({ entradas, almacenes, metodosPago, mostrarSelector, filters }: Props) {
    const { flash } = usePage<Props>().props;
    const [confirmId, setConfirmId]   = useState<number | null>(null);
    const [deleteId, setDeleteId]     = useState<number | null>(null);
    const [filtrAlmacen, setFiltrAlmacen] = useState(filters.almacen_id ?? '');
    const [filtrEstado, setFiltrEstado]   = useState(filters.estado ?? '');
    // Search vive a nivel de página para compartirse entre la vista de cards (mobile)
    // y la tabla (desktop). El Table interno recibe searchable=false para no duplicar.
    // Se inicializa desde el server para conservar el término al paginar/recargar.
    const [search, setSearch]             = useState(filters.buscar ?? '');

    // Búsqueda/filtros SERVER-SIDE: cada cambio consulta TODA la base (debounced),
    // no solo las 25 filas de la página actual. Así una entrada en otra página
    // aparece al buscar (antes se filtraba en el cliente y no la encontraba).
    const primeraCarga = useRef(true);
    useEffect(() => {
        if (primeraCarga.current) { primeraCarga.current = false; return; }
        const t = setTimeout(() => {
            router.get(
                route('inventario.entradas.index'),
                {
                    buscar: search || undefined,
                    almacen_id: filtrAlmacen || undefined,
                    estado: filtrEstado || undefined,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 500);
        return () => clearTimeout(t);
    }, [search, filtrAlmacen, filtrEstado]);

    // Modal Ver detalle: id de la entrada cuya info se está mostrando + payload del fetch.
    const [verEntradaId, setVerEntradaId]   = useState<number | null>(null);
    const [verDetalle, setVerDetalle]       = useState<EntradaDetallada | null>(null);
    const [verLoading, setVerLoading]       = useState(false);

    function abrirVerDetalle(id: number) {
        setVerEntradaId(id);
        setVerDetalle(null);
        setVerLoading(true);
        axios.get(route('inventario.entradas.detalle-json', id))
            .then(res => setVerDetalle(res.data.entrada))
            .catch(() => toast.error('No se pudo cargar el detalle.'))
            .finally(() => setVerLoading(false));
    }

    // Quick-pago modal: la entrada que se está editando + el form local.
    const [pagoEntrada, setPagoEntrada]     = useState<Entrada | null>(null);
    const [pagoForm, setPagoForm]           = useState<{ pagado: boolean; metodoId: number | ''; cuentaId: number | '' }>(
        { pagado: false, metodoId: '', cuentaId: '' }
    );
    const [savingPago, setSavingPago]       = useState(false);

    const metodoQuickSel = metodosPago.find(m => m.id === pagoForm.metodoId) ?? null;
    const cuentasQuick   = metodoQuickSel?.cuentas ?? [];

    // Detecta si el form cambió respecto al estado actual de la entrada. Si no
    // cambió nada, no tiene sentido permitir "Guardar" — el botón se deshabilita
    // y el usuario entiende que solo está viendo el estado, no editandolo.
    const pagoFormDirty = !!pagoEntrada && (
        pagoForm.pagado !== (pagoEntrada.estado_pago === 'pagado') ||
        (pagoForm.metodoId || null) !== (pagoEntrada.metodo_pago_id ?? null) ||
        (pagoForm.cuentaId || null) !== (pagoEntrada.cuenta_id ?? null)
    );

    function abrirPagoModal(e: Entrada) {
        setPagoEntrada(e);
        setPagoForm({
            pagado:   e.estado_pago === 'pagado',
            metodoId: e.metodo_pago_id ?? '',
            cuentaId: e.cuenta_id ?? '',
        });
    }

    function guardarPago() {
        if (!pagoEntrada) return;
        setSavingPago(true);
        router.post(route('inventario.entradas.pago', pagoEntrada.id), {
            estado_pago:    pagoForm.pagado ? 'pagado' : 'pendiente',
            metodo_pago_id: pagoForm.pagado ? (pagoForm.metodoId || null) : null,
            cuenta_id:      pagoForm.pagado ? (pagoForm.cuentaId || null) : null,
        }, {
            preserveScroll: true,
            onSuccess: () => { setPagoEntrada(null); },
            onError:   (errs) => {
                const first = Object.values(errs)[0];
                toast.error(typeof first === 'string' ? first : 'No se pudo guardar el pago.');
                // Eject info al console para diagnosticar si el toast no es claro.
                console.error('[entradas.pago] errores backend:', errs);
            },
            // onFinish dispara SIEMPRE (success, error, network failure, cancel).
            // Garantiza que el loader nunca se quede colgado — el bug que reportaste
            // venia de que ni onSuccess ni onError corrian (ej: 419 CSRF expirado).
            onFinish: () => { setSavingPago(false); },
        });
    }

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function confirmar(id: number) {
        setConfirmId(null);
        router.post(route('inventario.entradas.confirmar', id));
    }

    function eliminar(id: number) {
        setDeleteId(null);
        router.delete(route('inventario.entradas.destroy', id));
    }

    const columns: Column<Entrada>[] = [
        {
            key: 'fecha', label: 'Fecha', sortable: true,
            render: (e) => <span className="text-sm">{fmtFecha(e.fecha)}</span>,
        },
        {
            key: 'correlativo', label: 'Correlativo', sortable: true,
            render: (e) => e.correlativo
                ? <span className="font-mono text-xs" style={{ color: 'var(--color-text)' }}>{e.correlativo}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'tipo', label: 'Tipo', sortable: true,
            render: (e) => <Badge variant="primary">{TIPOS[e.tipo] ?? e.tipo}</Badge>,
        },
        {
            key: 'almacen', label: 'Almacén', sortKey: 'almacen.nombre',
            render: (e) => (
                <span className="text-sm">
                    {e.almacen.nombre}
                    {e.almacen.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {e.almacen.local.nombre}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'proveedor', label: 'Proveedor', sortable: true,
            render: (e) => e.proveedor
                ? <span className="text-sm">{e.proveedor}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'numero_documento', label: 'Nro. doc.', sortable: true,
            render: (e) => e.numero_documento
                ? <span className="font-mono text-xs">{e.numero_documento}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'total', label: 'Total', sortable: true,
            render: (e) => (
                <div className="leading-tight">
                    <span className="font-mono text-sm">S/ {Number(e.total).toFixed(2)}</span>
                    {/* "Pendiente" inline en la misma columna del total — sin agregar otra
                        columna. El tint amarillo en la fila ya hace de visual primario;
                        este texto es la etiqueta clara para el usuario. */}
                    {e.estado_pago !== 'pagado' && (
                        <p className="text-[10px] font-medium mt-0.5 flex items-center gap-1" style={{ color: '#ca8a04' }}>
                            <Wallet size={10} />
                            {e.estado_pago === 'parcial'
                                ? `debe S/ ${(Number(e.total) - Number(e.monto_pagado ?? 0)).toFixed(2)}`
                                : 'pendiente'}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'estado', label: 'Estado', sortable: true,
            render: (e) => (
                <Badge variant={e.estado === 'confirmado' ? 'success' : 'warning'}>
                    {e.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones', sortable: false,
            render: (e) => (
                <div className="flex items-center gap-1">
                    {e.estado === 'borrador' && (
                        <button
                            type="button"
                            onClick={() => setConfirmId(e.id)}
                            className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                            style={{ color: 'var(--color-success)', backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, transparent)' }}
                            title="Confirmar entrada"
                        >
                            <CheckCircle size={13} />Confirmar
                        </button>
                    )}
                    {/* Pagar: solo si AÚN debe algo. Una entrada ya pagada no muestra el
                        botón (no hay nada que cobrar). Para corregir un pago ya hecho se usa
                        Editar o Finanzas → Cuentas por pagar. */}
                    {e.estado_pago !== 'pagado' && (
                        <button
                            type="button"
                            onClick={() => abrirPagoModal(e)}
                            className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                            style={{ color: '#ca8a04', backgroundColor: 'rgba(250,204,21,0.12)' }}
                            title="Marcar como pagado"
                        >
                            <Wallet size={13} />Pagar
                        </button>
                    )}
                    <TableActions
                        onView={() => abrirVerDetalle(e.id)}
                        onEdit={() => router.visit(route('inventario.entradas.edit', e.id))}
                        onDelete={e.estado === 'borrador' ? () => setDeleteId(e.id) : undefined}
                    />
                </div>
            ),
        },
    ];

    // El filtrado ya lo hace el servidor sobre toda la base; aquí solo pintamos
    // lo que llega. (Antes se filtraba en cliente sobre la página → no cruzaba páginas.)
    const filtered = entradas.data;

    return (
        <AppLayout title="Entradas">
            <PageHeader
                title="Entradas de inventario"
                subtitle="Registro de ingresos de mercadería"
                actions={
                    <Link href={route('inventario.entradas.create')}>
                        <Button><Plus size={15} className="mr-1 flex-shrink-0" />Nueva entrada</Button>
                    </Link>
                }
            />

            {/* Búsqueda + filtros (compartidos entre mobile y desktop) */}
            <FiltrosCard
                cols={4}
                tieneFiltros={!!(filtrAlmacen || filtrEstado || search)}
                onClear={() => { setFiltrAlmacen(''); setFiltrEstado(''); setSearch(''); }}
            >
                <div className="col-span-2">
                    <label className="text-[10px] font-medium uppercase mb-1 block" style={{ color: 'var(--color-text-muted)' }}>Buscar</label>
                    <div className="relative">
                        <Search
                            size={15}
                            className="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                            style={{ color: 'var(--color-text-muted)' }}
                        />
                        <input
                            type="text"
                            placeholder="Documento o proveedor (toda la base)..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="w-full rounded-lg border py-2 pl-9 pr-9 text-sm outline-none transition-all"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-surface)',
                                color: 'var(--color-text)',
                            }}
                            onFocus={e => (e.currentTarget.style.borderColor = 'var(--color-primary)')}
                            onBlur={e => (e.currentTarget.style.borderColor = 'var(--color-border)')}
                        />
                        {search && (
                            <button
                                onClick={() => setSearch('')}
                                className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-0.5"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                ✕
                            </button>
                        )}
                    </div>
                </div>
                {mostrarSelector && (
                    <Select
                        label="Almacén"
                        value={filtrAlmacen}
                        onChange={v => setFiltrAlmacen(String(v))}
                        options={[
                            { value: '', label: 'Todos los almacenes' },
                            ...almacenes.map(a => ({ value: a.id, label: a.nombre })),
                        ]}
                    />
                )}
                <Select
                    label="Estado"
                    value={filtrEstado}
                    onChange={v => setFiltrEstado(String(v))}
                    options={[
                        { value: '',           label: 'Todos los estados' },
                        { value: 'borrador',   label: 'Borrador' },
                        { value: 'confirmado', label: 'Confirmado' },
                    ]}
                />
            </FiltrosCard>

            {/* Mobile: cards */}
            <div className="sm:hidden space-y-2.5">
                {filtered.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-16 rounded-2xl border" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <div className="rounded-full p-4" style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
                            <Package size={22} style={{ color: 'var(--color-text-muted)', opacity: 0.5 }} />
                        </div>
                        <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                            {search || filtrAlmacen || filtrEstado ? 'Sin resultados con esos filtros' : 'No hay entradas registradas'}
                        </p>
                    </div>
                ) : filtered.map(e => (
                    <div key={e.id}
                        className={`rounded-2xl border p-4 space-y-3 ${e.estado_pago !== 'pagado' ? 'pago-pendiente-card' : ''}`}
                        style={{
                            borderColor: e.estado === 'borrador' ? 'color-mix(in srgb, var(--color-warning) 35%, var(--color-border))' : 'var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                        }}>
                        {/* Fila 1: fecha + total. Lo que el usuario mira primero. */}
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                    {fmtFecha(e.fecha)}
                                </p>
                                <p className="text-[10px] uppercase tracking-wider mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    {TIPOS[e.tipo] ?? e.tipo}
                                </p>
                                {e.correlativo && (
                                    <p className="text-[11px] font-mono mt-0.5" style={{ color: 'var(--color-text)' }}>
                                        {e.correlativo}
                                    </p>
                                )}
                            </div>
                            <div className="text-right">
                                <p className="text-lg font-bold font-mono leading-tight" style={{ color: 'var(--color-text)' }}>
                                    S/ {Number(e.total).toFixed(2)}
                                </p>
                                {e.estado_pago !== 'pagado' && (
                                    <p className="text-[10px] font-medium mt-0.5 flex items-center justify-end gap-1" style={{ color: '#ca8a04' }}>
                                        <Wallet size={10} />
                                        {e.estado_pago === 'parcial'
                                            ? `debe S/ ${(Number(e.total) - Number(e.monto_pagado ?? 0)).toFixed(2)}`
                                            : 'pago pendiente'}
                                    </p>
                                )}
                                <Badge variant={e.estado === 'confirmado' ? 'success' : 'warning'}>
                                    {e.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                                </Badge>
                            </div>
                        </div>

                        {/* Fila 2: meta */}
                        <div className="space-y-1 text-sm">
                            <p style={{ color: 'var(--color-text)' }}>
                                <span className="font-medium">{e.almacen.nombre}</span>
                                {e.almacen.local && (
                                    <span className="text-xs ml-1" style={{ color: 'var(--color-text-muted)' }}>
                                        · {e.almacen.local.nombre}
                                    </span>
                                )}
                            </p>
                            {e.proveedor && (
                                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                    {e.proveedor}
                                </p>
                            )}
                            {e.numero_documento && (
                                <p className="text-xs flex items-center gap-1 font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                    <FileText size={11} />
                                    {e.numero_documento}
                                </p>
                            )}
                        </div>

                        {/* Fila 3: acciones */}
                        <div className="flex items-center justify-between gap-2 pt-2 border-t" style={{ borderColor: 'var(--color-border)' }}>
                            <div className="flex items-center gap-2">
                                {e.estado === 'borrador' && (
                                    <button
                                        type="button"
                                        onClick={() => setConfirmId(e.id)}
                                        className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium"
                                        style={{ color: 'var(--color-success)', backgroundColor: 'color-mix(in srgb, var(--color-success) 12%, transparent)' }}
                                    >
                                        <CheckCircle size={13} />Confirmar
                                    </button>
                                )}
                                {e.estado_pago !== 'pagado' && (
                                    <button
                                        type="button"
                                        onClick={() => abrirPagoModal(e)}
                                        className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium"
                                        style={{ color: '#ca8a04', backgroundColor: 'rgba(250,204,21,0.15)' }}
                                    >
                                        <Wallet size={13} />Pagar
                                    </button>
                                )}
                            </div>
                            <div className="flex items-center gap-1">
                                <button
                                    onClick={() => abrirVerDetalle(e.id)}
                                    className="rounded-lg p-2"
                                    style={{ color: 'var(--color-text-muted)' }}
                                    aria-label="Ver detalle"
                                >
                                    <Eye size={15} />
                                </button>
                                <button
                                    onClick={() => router.visit(route('inventario.entradas.edit', e.id))}
                                    className="rounded-lg p-2"
                                    style={{ color: 'var(--color-text-muted)' }}
                                    aria-label="Editar"
                                >
                                    <Edit2 size={15} />
                                </button>
                                {/* Eliminar sigue siendo solo borrador — borrar una confirmada
                                    requiere otro flujo (anulación) que aún no existe. */}
                                {e.estado === 'borrador' && (
                                    <button
                                        onClick={() => setDeleteId(e.id)}
                                        className="rounded-lg p-2"
                                        style={{ color: 'var(--color-danger)' }}
                                        aria-label="Eliminar"
                                    >
                                        <Trash2 size={15} />
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Desktop: tabla. Las filas con pago pendiente reciben tint amarillo
                pulsante via rowClassName (estilos definidos en el <style> del final). */}
            <div className="hidden sm:block">
                <Table
                    data={filtered}
                    columns={columns}
                    searchable={false}
                    emptyMessage="No hay entradas registradas"
                    rowClassName={(e: Entrada) => e.estado_pago !== 'pagado' ? 'pago-pendiente-row' : ''}
                />
            </div>

            {/* Paginación (M19) */}
            {entradas.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: entradas.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(
                                route('inventario.entradas.index'),
                                {
                                    page,
                                    buscar: search || undefined,
                                    almacen_id: filtrAlmacen || undefined,
                                    estado: filtrEstado || undefined,
                                },
                                { preserveState: true, preserveScroll: true },
                            )}
                            className="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            style={{
                                backgroundColor: page === entradas.current_page ? 'var(--color-primary)' : 'transparent',
                                color: page === entradas.current_page ? '#fff' : 'var(--color-text-muted)',
                            }}
                        >
                            {page}
                        </button>
                    ))}
                </div>
            )}

            {/* Modal confirmar */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Confirmar entrada"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="success" onClick={() => confirmId && confirmar(confirmId)}>
                            Confirmar y actualizar stock
                        </Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Al confirmar, el stock se actualizará automáticamente y el costo promedio se recalculará.
                    Esta acción es <strong>irreversible</strong>.
                </p>
            </Modal>

            {/* Modal eliminar */}
            <Modal
                isOpen={deleteId !== null}
                onClose={() => setDeleteId(null)}
                title="Eliminar entrada"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setDeleteId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => deleteId && eliminar(deleteId)}>Eliminar</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se eliminará la entrada en borrador. Esta acción no se puede deshacer.
                </p>
            </Modal>

            {/* Modal quick-pago: para actualizar el estado de pago sin entrar al form completo.
                Funciona tanto en entradas borrador como confirmadas. */}
            <Modal
                isOpen={pagoEntrada !== null}
                onClose={() => !savingPago && setPagoEntrada(null)}
                title="Estado de pago"
                size="sm"
                footer={
                    <>
                        {/* Hint a la izquierda explicando por qué Guardar puede estar deshabilitado */}
                        {pagoEntrada && !pagoFormDirty && !savingPago && (
                            <span className="text-xs mr-auto" style={{ color: 'var(--color-text-muted)' }}>
                                Sin cambios
                            </span>
                        )}
                        <Button variant="ghost" onClick={() => setPagoEntrada(null)} disabled={savingPago}>
                            {pagoFormDirty ? 'Cancelar' : 'Cerrar'}
                        </Button>
                        <Button onClick={guardarPago} loading={savingPago} disabled={!pagoFormDirty}>
                            Guardar
                        </Button>
                    </>
                }
            >
                {pagoEntrada && (
                    <div className="space-y-4">
                        <div className="rounded-xl border p-3 text-sm space-y-1"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Entrada</span>
                                <span className="font-medium" style={{ color: 'var(--color-text)' }}>
                                    {fmtFecha(pagoEntrada.fecha)} · {TIPOS[pagoEntrada.tipo] ?? pagoEntrada.tipo}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Total</span>
                                <span className="font-mono font-bold" style={{ color: 'var(--color-text)' }}>
                                    S/ {Number(pagoEntrada.total).toFixed(2)}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Pagado hasta ahora</span>
                                <span className="font-mono" style={{ color: 'var(--color-success)' }}>
                                    S/ {Number(pagoEntrada.monto_pagado ?? 0).toFixed(2)}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Saldo pendiente</span>
                                <span className="font-mono font-bold" style={{ color: 'var(--color-danger)' }}>
                                    S/ {Math.max(0, Number(pagoEntrada.total) - Number(pagoEntrada.monto_pagado ?? 0)).toFixed(2)}
                                </span>
                            </div>
                        </div>

                        {pagoForm.pagado && pagoEntrada.estado_pago !== 'pagado' && (
                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                Se registrará un pago por el saldo pendiente con su egreso en tesorería
                                (trazable en Cuentas por pagar). Para pagos parciales usa Finanzas → Cuentas por pagar.
                            </p>
                        )}
                        {!pagoForm.pagado && pagoEntrada.estado_pago !== 'pendiente' && (
                            <p className="text-xs font-medium" style={{ color: 'var(--color-danger)' }}>
                                Al volver a "Pendiente" se eliminan los pagos registrados de esta entrada
                                y se revierten sus asientos de tesorería (queda en auditoría).
                            </p>
                        )}

                        <div className="flex items-center justify-between gap-3">
                            <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                {pagoForm.pagado ? 'Pagado' : 'Pendiente de pago'}
                            </p>
                            <label className="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    className="sr-only peer"
                                    checked={pagoForm.pagado}
                                    onChange={e => setPagoForm(f => ({
                                        ...f, pagado: e.target.checked,
                                        metodoId: e.target.checked ? f.metodoId : '',
                                        cuentaId: e.target.checked ? f.cuentaId : '',
                                    }))}
                                />
                                <div className="w-11 h-6 rounded-full transition-colors bg-[var(--color-border)] peer-checked:bg-[var(--color-primary)]" />
                                <div className="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5" />
                            </label>
                        </div>

                        {pagoForm.pagado && (
                            <div className="space-y-3">
                                <Select
                                    label="Método de pago"
                                    placeholder="Seleccionar método"
                                    value={pagoForm.metodoId}
                                    onChange={v => setPagoForm(f => ({
                                        ...f,
                                        metodoId: v === '' ? '' : Number(v),
                                        cuentaId: '',
                                    }))}
                                    options={metodosPago.map(m => ({ value: m.id, label: m.nombre }))}
                                />
                                {cuentasQuick.length > 0 && (
                                    <Select
                                        label="Cuenta (opcional)"
                                        placeholder="No especificada"
                                        value={pagoForm.cuentaId}
                                        onChange={v => setPagoForm(f => ({ ...f, cuentaId: v === '' ? '' : Number(v) }))}
                                        options={cuentasQuick.map(c => ({
                                            value: c.id,
                                            label: c.banco ? `${c.nombre} · ${c.banco}` : c.nombre,
                                        }))}
                                    />
                                )}
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            {/* Modal Ver detalle: muestra entrada + items + pago + totales en read-only.
                Disponible tanto en borrador como confirmado — sirve para revisar lo
                que se compró sin tocar nada. */}
            <Modal
                isOpen={verEntradaId !== null}
                onClose={() => setVerEntradaId(null)}
                title="Detalle de entrada"
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setVerEntradaId(null)}>Cerrar</Button>
                        {/* Editar siempre disponible. Si la entrada está confirmada, el form
                            mostrará un warning y el backend recalcula stock + CPP al guardar. */}
                        {verDetalle && (
                            <Button onClick={() => router.visit(route('inventario.entradas.edit', verDetalle.id))}>
                                <Edit2 size={14} className="mr-1.5" />
                                Editar
                            </Button>
                        )}
                    </>
                }
            >
                {verLoading ? (
                    <div className="flex items-center justify-center py-12 gap-2" style={{ color: 'var(--color-text-muted)' }}>
                        <Loader2 size={18} className="animate-spin" />
                        <span className="text-sm">Cargando detalle...</span>
                    </div>
                ) : !verDetalle ? null : (
                    <div className="space-y-5">
                        {/* Badges arriba del todo — lo primero que ve el usuario al abrir.
                            Estado de la entrada + estado de pago juntos para scaneo rápido. */}
                        <div className="flex flex-wrap items-center gap-2 pb-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                            <Badge variant={verDetalle.estado === 'confirmado' ? 'success' : 'warning'}>
                                {verDetalle.estado === 'confirmado' ? 'Confirmado' : 'Borrador'}
                            </Badge>
                            {verDetalle.estado_pago === 'pagado' ? (
                                <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                    style={{ color: '#16a34a', backgroundColor: 'color-mix(in srgb, #16a34a 12%, transparent)' }}>
                                    <Wallet size={11} />Pagado
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                    style={{ color: '#ca8a04', backgroundColor: 'rgba(250,204,21,0.15)' }}>
                                    <Wallet size={11} />Pago pendiente
                                </span>
                            )}
                            <span className="ml-auto text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                #{verDetalle.id}
                            </span>
                        </div>

                        {/* Cabecera: metadatos clave en grid 2 col */}
                        <div className="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                            {verDetalle.correlativo && <Meta label="Correlativo" value={verDetalle.correlativo} mono />}
                            <Meta label="Fecha" value={fmtFecha(verDetalle.fecha)} />
                            <Meta label="Tipo" value={TIPOS[verDetalle.tipo] ?? verDetalle.tipo} />
                            <Meta label="Almacén" value={`${verDetalle.almacen.nombre}${verDetalle.almacen.local ? ' · ' + verDetalle.almacen.local.nombre : ''}`} />
                            <Meta label="Registrado por" value={verDetalle.user.name} />
                            {verDetalle.proveedor && <Meta label="Proveedor" value={verDetalle.proveedor} />}
                            {verDetalle.numero_documento && <Meta label="Nº documento" value={verDetalle.numero_documento} mono />}
                        </div>

                        {/* Items */}
                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide mb-2" style={{ color: 'var(--color-text-muted)' }}>
                                Productos ({verDetalle.detalles.length})
                            </h3>
                            <div className="rounded-xl border divide-y" style={{ borderColor: 'var(--color-border)', borderStyle: 'solid' }}>
                                {verDetalle.detalles.map(d => (
                                    <div key={d.id} className="px-3 py-2.5 text-sm">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                                    {d.producto?.nombre ?? '—'}
                                                </p>
                                                <p className="text-xs mt-0.5 font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                                    {Number(d.cantidad).toFixed(2)} {d.unidad_medida?.abreviatura ?? ''}
                                                    {parseFloat(d.factor_conversion) !== 1 && (
                                                        <span> · ×{d.factor_conversion} = {Number(d.cantidad_base).toFixed(2)} base</span>
                                                    )}
                                                    {' · '}S/ {Number(d.precio_costo).toFixed(2)} c/u
                                                </p>
                                                {d.numero_documento && (
                                                    <p className="text-xs mt-0.5 flex items-center gap-1 font-mono" style={{ color: 'var(--color-text-muted)' }}>
                                                        <FileText size={10} />{d.numero_documento}
                                                    </p>
                                                )}
                                            </div>
                                            <p className="text-sm font-mono font-semibold whitespace-nowrap" style={{ color: 'var(--color-text)' }}>
                                                S/ {Number(d.subtotal).toFixed(2)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Total + pago: bloque destacado al cierre del detalle. Cuando
                            esta pagado, el metodo+cuenta vive aca abajo del total —
                            es donde el usuario espera ver "cómo se pagó esto". */}
                        <div className="rounded-xl p-3" style={{ backgroundColor: 'var(--color-bg)' }}>
                            <div className="flex justify-between items-center">
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text-muted)' }}>Total</span>
                                <span className="text-lg font-bold font-mono" style={{ color: 'var(--color-text)' }}>
                                    S/ {Number(verDetalle.total).toFixed(2)}
                                </span>
                            </div>
                            {verDetalle.estado_pago === 'pagado' && verDetalle.metodo_pago && (
                                <div className="flex justify-between items-center mt-2 pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                                    <span className="text-xs flex items-center gap-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                        <Wallet size={12} />Pagado con
                                    </span>
                                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                        {verDetalle.metodo_pago.nombre}
                                        {verDetalle.cuenta && (
                                            <span style={{ color: 'var(--color-text-muted)' }}> · {verDetalle.cuenta.nombre}</span>
                                        )}
                                    </span>
                                </div>
                            )}
                        </div>

                        {verDetalle.observacion && (
                            <div>
                                <p className="text-[10px] uppercase tracking-wide mb-1" style={{ color: 'var(--color-text-muted)' }}>Observación</p>
                                <p className="text-sm rounded-xl border p-3" style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}>
                                    {verDetalle.observacion}
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            {/* Animación pulse suave para filas/cards con pago pendiente.
                Usamos box-shadow inset (en vez de background-color) para no
                pelearnos con el hover de la tabla que también pinta el bg. */}
            <style>{`
                @keyframes pagoPendientePulse {
                    0%, 100% { box-shadow: inset 0 0 0 9999px rgba(250, 204, 21, 0.06); }
                    50%      { box-shadow: inset 0 0 0 9999px rgba(250, 204, 21, 0.14); }
                }
                .pago-pendiente-row {
                    animation: pagoPendientePulse 3s ease-in-out infinite;
                }
                .pago-pendiente-card {
                    animation: pagoPendientePulse 3s ease-in-out infinite;
                }
                @media (prefers-reduced-motion: reduce) {
                    .pago-pendiente-row, .pago-pendiente-card {
                        animation: none;
                        box-shadow: inset 0 0 0 9999px rgba(250, 204, 21, 0.10);
                    }
                }
            `}</style>
        </AppLayout>
    );
}
