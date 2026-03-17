import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { RefreshCw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Input from '@/Components/UI/Input';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';

interface UnidadMedida { id: number; nombre: string; abreviatura: string; }
interface ProductoUnidad { es_base: boolean; unidad_medida?: UnidadMedida; }
interface Producto { id: number; codigo: string | null; nombre: string; unidad_base?: ProductoUnidad | null; }
interface Almacen  { id: number; nombre: string; tipo: string; local?: { nombre: string } | null; }

interface StockRow extends Record<string, unknown> {
    id: number;
    almacen_id: number;
    almacen: Almacen;
    producto_id: number;
    producto: Producto;
    cantidad: number;
    costo_promedio: number;
    valor_total: number;
}

interface Props extends PageProps {
    stocks: StockRow[];
    almacenes: Almacen[];
    mostrarSelector: boolean;
    filters: { almacen_id?: string; busqueda?: string };
}

export default function Stock({ stocks, almacenes, mostrarSelector, filters }: Props) {
    const { flash } = usePage<Props>().props;
    const [almacenId, setAlmacenId] = useState(filters.almacen_id ?? '');
    const [busqueda, setBusqueda]   = useState(filters.busqueda ?? '');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function filtrar() {
        router.get(route('inventario.stock.index'), {
            almacen_id: almacenId || undefined,
            busqueda:   busqueda  || undefined,
        }, { preserveState: true, replace: true });
    }

    function recalcular() {
        router.post(route('inventario.stock.recalcular'));
    }

    const columns: Column<StockRow>[] = [
        {
            key: 'producto', label: 'Producto', sortable: true,
            render: (s) => (
                <div>
                    <p className="font-medium text-sm" style={{ color: 'var(--color-text)' }}>{s.producto.nombre}</p>
                    {s.producto.codigo && (
                        <p className="font-mono text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{s.producto.codigo}</p>
                    )}
                </div>
            ),
        },
        {
            key: 'unidad', label: 'Unidad base', sortable: false,
            render: (s) => s.producto.unidad_base?.unidad_medida
                ? <span className="text-sm">{s.producto.unidad_base.unidad_medida.abreviatura}</span>
                : <span style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        ...(mostrarSelector ? [{
            key: 'almacen', label: 'Almacén', sortable: true,
            render: (s: StockRow) => (
                <span className="text-sm">
                    {s.almacen.nombre}
                    {s.almacen.local && (
                        <span className="ml-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            · {s.almacen.local.nombre}
                        </span>
                    )}
                </span>
            ),
        } as Column<StockRow>] : []),
        {
            key: 'cantidad', label: 'Cantidad', sortable: true,
            render: (s) => {
                const qty = s.cantidad;
                const variant = qty === 0 ? 'danger' : qty < 5 ? 'warning' : 'success';
                return (
                    <Badge variant={variant}>
                        {Number(qty).toFixed(2)}
                    </Badge>
                );
            },
        },
        {
            key: 'costo_promedio', label: 'Costo prom.', sortable: true,
            render: (s) => (
                <span className="font-mono text-sm">
                    S/ {Number(s.costo_promedio).toFixed(4)}
                </span>
            ),
        },
        {
            key: 'valor_total', label: 'Valor total', sortable: true,
            render: (s) => (
                <span className="font-mono text-sm font-semibold">
                    S/ {Number(s.valor_total).toFixed(2)}
                </span>
            ),
        },
    ];

    return (
        <AppLayout title="Stock actual">
            <PageHeader
                title="Stock actual"
                subtitle="Inventario en tiempo real por almacén"
                actions={
                    <Button variant="ghost" onClick={recalcular}>
                        <RefreshCw size={14} className="mr-1" />Recalcular stock
                    </Button>
                }
            />

            {/* Filtros */}
            <div className="mb-4 flex flex-wrap gap-3 items-end">
                {mostrarSelector && (
                    <div className="w-52">
                        <Select
                            placeholder="Todos los almacenes"
                            value={almacenId}
                            onChange={v => setAlmacenId(String(v))}
                            options={[
                                { value: '', label: 'Todos los almacenes' },
                                ...almacenes.map(a => ({ value: a.id, label: a.nombre })),
                            ]}
                        />
                    </div>
                )}
                <div className="w-72">
                    <Input
                        placeholder="Buscar por nombre o código..."
                        value={busqueda}
                        onChange={e => setBusqueda(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && filtrar()}
                    />
                </div>
                <Button onClick={filtrar}>Buscar</Button>
                {(almacenId || busqueda) && (
                    <Button variant="ghost" onClick={() => {
                        setAlmacenId('');
                        setBusqueda('');
                        router.get(route('inventario.stock.index'), {}, { replace: true });
                    }}>
                        Limpiar
                    </Button>
                )}
            </div>

            <Table
                data={stocks}
                columns={columns}
                searchPlaceholder="Filtrar resultados..."
                emptyMessage="No hay stock registrado"
            />
        </AppLayout>
    );
}
