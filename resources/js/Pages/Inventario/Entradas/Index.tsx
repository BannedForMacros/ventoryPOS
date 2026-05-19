import { useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, CheckCircle, Search, Edit2, Trash2, Package, FileText } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

interface Almacen { id: number; nombre: string; local?: { nombre: string } | null; }
interface UserItem { id: number; name: string; }
interface Entrada extends Record<string, unknown> {
    id: number;
    fecha: string;
    tipo: string;
    proveedor: string | null;
    numero_documento: string | null;
    almacen: Almacen;
    user: UserItem;
    total: string;
    estado: 'borrador' | 'confirmado';
}

// M19: paginado server-side. El filtro de almacén/estado se mantiene client-side
// sobre la página actual; en el futuro conviene mover los filtros al server
// para combinar paginación + filtro correctamente.
interface Paginado<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number; }

interface Props extends PageProps {
    entradas: Paginado<Entrada>;
    almacenes: Almacen[];
    mostrarSelector: boolean;
    filters: Record<string, string>;
}

const TIPOS: Record<string, string> = {
    compra: 'Compra', ajuste: 'Ajuste', devolucion: 'Devolución', otro: 'Otro',
};

export default function EntradasIndex({ entradas, almacenes, mostrarSelector, filters }: Props) {
    const { flash } = usePage<Props>().props;
    const [confirmId, setConfirmId]   = useState<number | null>(null);
    const [deleteId, setDeleteId]     = useState<number | null>(null);
    const [filtrAlmacen, setFiltrAlmacen] = useState(filters.almacen_id ?? '');
    const [filtrEstado, setFiltrEstado]   = useState(filters.estado ?? '');
    // Search vive a nivel de página para compartirse entre la vista de cards (mobile)
    // y la tabla (desktop). El Table interno recibe searchable=false para no duplicar.
    const [search, setSearch]             = useState('');

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
            render: (e) => <span className="text-sm">{e.fecha}</span>,
        },
        {
            key: 'tipo', label: 'Tipo', sortable: true,
            render: (e) => <Badge variant="primary">{TIPOS[e.tipo] ?? e.tipo}</Badge>,
        },
        {
            key: 'almacen', label: 'Almacén', sortable: true,
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
            render: (e) => <span className="font-mono text-sm">S/ {Number(e.total).toFixed(2)}</span>,
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
            key: 'acciones', label: 'Acciones',
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
                    <TableActions
                        onEdit={e.estado === 'borrador' ? () => router.visit(route('inventario.entradas.edit', e.id)) : undefined}
                        onDelete={e.estado === 'borrador' ? () => setDeleteId(e.id) : undefined}
                    />
                </div>
            ),
        },
    ];

    const filtered = useMemo(() => {
        const s = search.trim().toLowerCase();
        return entradas.data.filter(e => {
            if (filtrAlmacen && e.almacen.id !== Number(filtrAlmacen)) return false;
            if (filtrEstado  && e.estado !== filtrEstado) return false;
            if (!s) return true;
            const haystack = [
                e.proveedor ?? '', e.numero_documento ?? '', e.almacen.nombre,
                e.user.name, TIPOS[e.tipo] ?? e.tipo, e.fecha,
            ].join(' ').toLowerCase();
            return haystack.includes(s);
        });
    }, [entradas.data, filtrAlmacen, filtrEstado, search]);

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
            <div className="mb-4 space-y-3">
                <div className="relative w-full sm:max-w-md">
                    <Search
                        size={15}
                        className="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                        style={{ color: 'var(--color-text-muted)' }}
                    />
                    <input
                        type="text"
                        placeholder="Buscar por proveedor, documento, almacén..."
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        className="w-full rounded-xl border py-2 pl-9 pr-9 text-sm outline-none transition-all"
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
                <div className="flex flex-wrap gap-3">
                    {mostrarSelector && (
                        <div className="w-full sm:w-52">
                            <Select
                                placeholder="Todos los almacenes"
                                value={filtrAlmacen}
                                onChange={v => setFiltrAlmacen(String(v))}
                                options={[
                                    { value: '', label: 'Todos los almacenes' },
                                    ...almacenes.map(a => ({ value: a.id, label: a.nombre })),
                                ]}
                            />
                        </div>
                    )}
                    <div className="w-full sm:w-44">
                        <Select
                            placeholder="Todos los estados"
                            value={filtrEstado}
                            onChange={v => setFiltrEstado(String(v))}
                            options={[
                                { value: '',           label: 'Todos los estados' },
                                { value: 'borrador',   label: 'Borrador' },
                                { value: 'confirmado', label: 'Confirmado' },
                            ]}
                        />
                    </div>
                    {(filtrAlmacen || filtrEstado || search) && (
                        <Button variant="ghost" onClick={() => { setFiltrAlmacen(''); setFiltrEstado(''); setSearch(''); }}>
                            Limpiar filtros
                        </Button>
                    )}
                </div>
            </div>

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
                    <div key={e.id} className="rounded-2xl border p-4 space-y-3"
                        style={{
                            borderColor: e.estado === 'borrador' ? 'color-mix(in srgb, var(--color-warning) 35%, var(--color-border))' : 'var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                        }}>
                        {/* Fila 1: fecha + total. Lo que el usuario mira primero. */}
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                    {e.fecha}
                                </p>
                                <p className="text-[10px] uppercase tracking-wider mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    {TIPOS[e.tipo] ?? e.tipo}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-lg font-bold font-mono leading-tight" style={{ color: 'var(--color-text)' }}>
                                    S/ {Number(e.total).toFixed(2)}
                                </p>
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
                            {e.estado === 'borrador' ? (
                                <button
                                    type="button"
                                    onClick={() => setConfirmId(e.id)}
                                    className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium"
                                    style={{ color: 'var(--color-success)', backgroundColor: 'color-mix(in srgb, var(--color-success) 12%, transparent)' }}
                                >
                                    <CheckCircle size={13} />Confirmar
                                </button>
                            ) : <span />}
                            <div className="flex items-center gap-1">
                                {e.estado === 'borrador' && (
                                    <>
                                        <button
                                            onClick={() => router.visit(route('inventario.entradas.edit', e.id))}
                                            className="rounded-lg p-2"
                                            style={{ color: 'var(--color-text-muted)' }}
                                            aria-label="Editar"
                                        >
                                            <Edit2 size={15} />
                                        </button>
                                        <button
                                            onClick={() => setDeleteId(e.id)}
                                            className="rounded-lg p-2"
                                            style={{ color: 'var(--color-danger)' }}
                                            aria-label="Eliminar"
                                        >
                                            <Trash2 size={15} />
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Desktop: tabla */}
            <div className="hidden sm:block">
                <Table
                    data={filtered}
                    columns={columns}
                    searchable={false}
                    emptyMessage="No hay entradas registradas"
                />
            </div>

            {/* Paginación (M19) */}
            {entradas.last_page > 1 && (
                <div className="flex items-center justify-center gap-1 mt-4">
                    {Array.from({ length: entradas.last_page }, (_, i) => i + 1).map(page => (
                        <button
                            key={page}
                            onClick={() => router.get(route('inventario.entradas.index'), { page }, { preserveState: true })}
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
        </AppLayout>
    );
}
