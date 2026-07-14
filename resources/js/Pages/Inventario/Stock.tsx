import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { RefreshCw, AlertTriangle, Eye } from 'lucide-react';
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
    es_negativo: boolean;       // A9: marcado por el backend cuando cantidad < 0
}

interface StockNegativo {
    producto_id: number;
    producto: string;
    codigo: string | null;
    almacen: string;
    cantidad: number;
}

interface Props extends PageProps {
    stocks: StockRow[];
    almacenes: Almacen[];
    mostrarSelector: boolean;
    filters: { almacen_id?: string; busqueda?: string };
    stocksNegativosCount: number; // A9: total de stocks con saldo negativo en almacenes visibles
    stocksNegativos: StockNegativo[]; // lista exacta de negativos (producto + almacén + cantidad)
}

export default function Stock({ stocks, almacenes, mostrarSelector, filters, stocksNegativosCount, stocksNegativos }: Props) {
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
                const qty = Number(s.cantidad);
                // A9: negativos en rojo intenso (es una inconsistencia real)
                const variant = qty < 0 ? 'danger' : qty === 0 ? 'danger' : qty < 5 ? 'warning' : 'success';
                return (
                    <Badge variant={variant}>
                        {qty.toFixed(2)}
                        {qty < 0 && <span className="ml-1">⚠</span>}
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
        {
            key: 'acciones', label: '', sortable: false,
            render: (s) => (
                <button
                    onClick={() => router.get(route('reportes.kardex'), {
                        producto_id: s.producto_id,
                        almacen_id:  s.almacen_id,
                    })}
                    title="Ver movimientos (kardex)"
                    className="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:bg-[color-mix(in_srgb,var(--color-primary)_12%,transparent)]"
                    style={{ color: 'var(--color-text-muted)' }}
                >
                    <Eye size={16} />
                </button>
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

            {/* A9: banner + LISTA EXACTA de los productos con saldo negativo */}
            {stocksNegativosCount > 0 && (
                <div className="mb-4 rounded-lg overflow-hidden border" style={{ borderColor: '#fecaca' }}>
                    <div className="flex items-start gap-3 px-4 py-3" style={{ background: '#fef2f2', color: '#991b1b' }}>
                        <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                        <div className="text-sm">
                            <p className="font-semibold">
                                {stocksNegativosCount === 1
                                    ? 'Hay 1 producto con stock negativo'
                                    : `Hay ${stocksNegativosCount} productos con stock negativo`}
                            </p>
                            <p className="mt-0.5 opacity-90">
                                Salió más mercadería de la registrada (venta sin transferencia/entrada previa, etc.).
                                Regulariza el inventario con una entrada o transferencia, o usa "Recalcular stock".
                            </p>
                        </div>
                    </div>
                    <div className="overflow-x-auto" style={{ backgroundColor: 'var(--color-surface)' }}>
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                    <th className="text-left px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Producto</th>
                                    {mostrarSelector && (
                                        <th className="text-left px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Almacén</th>
                                    )}
                                    <th className="text-right px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Stock actual</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                {stocksNegativos.map(s => (
                                    <tr key={`${s.producto_id}-${s.almacen}`}>
                                        <td className="px-4 py-2" style={{ color: 'var(--color-text)' }}>
                                            <span className="font-medium">{s.producto}</span>
                                            {s.codigo && (
                                                <span className="ml-2 font-mono text-xs" style={{ color: 'var(--color-text-muted)' }}>{s.codigo}</span>
                                            )}
                                        </td>
                                        {mostrarSelector && (
                                            <td className="px-4 py-2" style={{ color: 'var(--color-text-muted)' }}>{s.almacen}</td>
                                        )}
                                        <td className="px-4 py-2 text-right font-bold" style={{ color: 'var(--color-danger)', fontVariantNumeric: 'tabular-nums' }}>
                                            {Number(s.cantidad)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

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
