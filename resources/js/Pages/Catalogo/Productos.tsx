import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Plus, Image as ImageIcon } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Select from '@/Components/UI/Select';
import FiltrosCard from '@/Components/UI/FiltrosCard';
import Modal from '@/Components/UI/Modal';
import TableActions from '@/Components/UI/TableActions';
import type { PageProps } from '@/types';

interface Categoria extends Record<string, unknown> { id: number; nombre: string; }
interface UnidadMedida extends Record<string, unknown> { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad extends Record<string, unknown> {
    id: number; es_base: boolean; factor_conversion: string;
    tipo_precio: 'fijo' | 'referencial'; precio_venta: string;
    unidad_medida?: UnidadMedida;
}
interface Producto extends Record<string, unknown> {
    id: number; codigo: string | null; nombre: string;
    tipo: 'producto' | 'servicio'; tipo_precio: 'fijo' | 'referencial';
    precio_venta: string; activo: boolean;
    imagen: string | null;
    categoria?: Categoria | null;
    unidad_base?: ProductoUnidad | null;
}

interface Props extends PageProps {
    productos: Producto[];
    categorias: Categoria[];
}

function ProductoThumbnail({ url, alt }: { url: string | null; alt: string }) {
    const [failed, setFailed] = useState(false);
    const showImg = !!url && !failed;
    return (
        <div
            className="w-9 h-9 rounded-lg overflow-hidden border flex items-center justify-center flex-shrink-0"
            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}
        >
            {showImg ? (
                <img
                    src={url!}
                    alt={alt}
                    className="w-full h-full object-cover"
                    onError={() => setFailed(true)}
                />
            ) : (
                <ImageIcon size={14} style={{ color: 'var(--color-text-muted)', opacity: 0.4 }} />
            )}
        </div>
    );
}

export default function Productos({ productos, categorias }: Props) {
    const { flash } = usePage<Props>().props;
    const [filtrTipo, setFiltrTipo]     = useState<string>('');
    const [filtrCat, setFiltrCat]       = useState<string>('');
    const [filtrEstado, setFiltrEstado] = useState<string>('');
    const [confirmId, setConfirmId]     = useState<number | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    }, [flash]);

    const filtered = productos.filter(p => {
        if (filtrTipo && p.tipo !== filtrTipo) return false;
        if (filtrCat  && p.categoria?.id !== Number(filtrCat)) return false;
        if (filtrEstado === 'activos'   && !p.activo) return false;
        if (filtrEstado === 'inactivos' &&  p.activo) return false;
        return true;
    });

    function deactivate(id: number) {
        setConfirmId(null);
        router.delete(route('catalogo.productos.destroy', id));
    }

    const columns: Column<Producto>[] = [
        {
            key: 'codigo', label: 'Código', sortable: true,
            render: (p) => p.codigo
                ? <span className="font-mono text-xs">{p.codigo}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'nombre', label: 'Nombre', sortable: true,
            render: (p) => (
                <div className="flex items-center gap-2.5">
                    <ProductoThumbnail url={p.imagen} alt={p.nombre} />
                    <span className="font-medium">{p.nombre}</span>
                </div>
            ),
        },
        {
            key: 'categoria', label: 'Categoría', sortKey: 'categoria.nombre',
            render: (p) => p.categoria
                ? <span>{p.categoria.nombre}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'tipo', label: 'Tipo', sortable: true,
            render: (p) => (
                <Badge variant={p.tipo === 'servicio' ? 'warning' : 'primary'}>
                    {p.tipo === 'servicio' ? 'Servicio' : 'Producto'}
                </Badge>
            ),
        },
        {
            key: 'precio_venta', label: 'Precio venta', sortable: true,
            render: (p) => {
                if (p.tipo === 'servicio') {
                    return <span className="font-mono text-sm">S/ {Number(p.precio_venta).toFixed(2)}</span>;
                }
                // Producto físico: mostrar precio de la unidad base
                if (p.unidad_base) {
                    return (
                        <span className="font-mono text-sm">
                            S/ {Number(p.unidad_base.precio_venta).toFixed(2)}
                            <span className="ml-1 text-xs font-sans" style={{ color: 'var(--color-text-muted)' }}>
                                /{p.unidad_base.unidad_medida?.abreviatura}
                            </span>
                        </span>
                    );
                }
                return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;
            },
        },
        {
            key: 'activo', label: 'Estado', sortable: true,
            render: (p) => (
                <Badge variant={p.activo ? 'success' : 'secondary'}>
                    {p.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones', label: 'Acciones', sortable: false,
            render: (p) => (
                <TableActions
                    onEdit={() => router.visit(route('catalogo.productos.edit', p.id))}
                    onDelete={() => setConfirmId(p.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Productos y servicios">
            <PageHeader
                title="Productos y servicios"
                subtitle="Catálogo de ítems disponibles para la venta"
                actions={
                    <Link href={route('catalogo.productos.create')}>
                        <Button><Plus size={15} className="mr-1 flex-shrink-0" /><span className="whitespace-nowrap">Nuevo Producto/Servicio</span></Button>
                    </Link>
                }
            />

            <FiltrosCard
                cols={3}
                tieneFiltros={!!(filtrTipo || filtrCat || filtrEstado)}
                onClear={() => { setFiltrTipo(''); setFiltrCat(''); setFiltrEstado(''); }}
            >
                <Select
                    label="Tipo"
                    value={filtrTipo}
                    onChange={v => setFiltrTipo(String(v))}
                    options={[
                        { value: '', label: 'Todos los tipos' },
                        { value: 'producto', label: 'Productos' },
                        { value: 'servicio', label: 'Servicios' },
                    ]}
                />
                <Select
                    label="Categoría"
                    value={filtrCat}
                    onChange={v => setFiltrCat(String(v))}
                    options={[
                        { value: '', label: 'Todas las categorías' },
                        ...categorias.map(c => ({ value: c.id, label: c.nombre })),
                    ]}
                />
                <Select
                    label="Estado"
                    value={filtrEstado}
                    onChange={v => setFiltrEstado(String(v))}
                    options={[
                        { value: '', label: 'Todos los estados' },
                        { value: 'activos', label: 'Activos' },
                        { value: 'inactivos', label: 'Inactivos' },
                    ]}
                />
            </FiltrosCard>

            <Table
                data={filtered}
                columns={columns}
                searchPlaceholder="Buscar por nombre o código..."
                emptyMessage="No hay productos registrados"
            />

            {/* Confirm deactivate */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Desactivar producto"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => confirmId && deactivate(confirmId)}>
                            Desactivar
                        </Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    El producto se marcará como inactivo. No se eliminará de la base de datos.
                </p>
            </Modal>
        </AppLayout>
    );
}
