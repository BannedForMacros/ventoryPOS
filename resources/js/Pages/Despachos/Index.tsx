import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { PackageCheck, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';

interface ClienteLite {
    id: number;
    nombres?: string | null;
    apellidos?: string | null;
    razon_social?: string | null;
    es_cliente_general?: boolean;
}

interface ProductoLite {
    id: number;
    nombre: string;
    precio_venta: string | number;
}

interface UnidadLite {
    id: number;
    precio_venta: string | number;
}

interface DespachoItem {
    id: number;
    producto_id: number;
    producto_nombre: string;
    unidad_nombre: string;
    cantidad: string | number;
    cantidad_pendiente: string | number;
    factor_conversion: string | number;
    precio_unitario: string | number;
    producto?: ProductoLite | null;
    unidad?: UnidadLite | null;
}

interface VentaLite {
    id: number;
    numero: string;
    fecha_venta?: string | null;
    local?: { id: number; nombre: string } | null;
}

interface UsuarioLite {
    id: number;
    name: string;
}

interface Pendiente extends Record<string, unknown> {
    id: number;
    fecha: string;
    monto: string;
    saldo: string;
    observacion: string | null;
    fecha_entrega_estimada?: string | null;
    cliente?: ClienteLite | null;
    user?: UsuarioLite | null;
    venta?: VentaLite | null;
    items: DespachoItem[];
}

interface Paginado<T> {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props extends PageProps {
    pendientes: Paginado<Pendiente>;
    buscar?: string;
}

function nombreCliente(c?: ClienteLite | null): string {
    if (!c || c.es_cliente_general) return 'Cliente general';
    const nombre = c.razon_social ?? `${c.nombres ?? ''} ${c.apellidos ?? ''}`.trim();
    return nombre || '—';
}

function fmtNum(v: string | number | undefined, dec = 2): string {
    return Number(v ?? 0).toFixed(dec);
}

function fmtCantidad(v: string | number | undefined): string {
    const n = Number(v ?? 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
}

export default function Despachos({ pendientes, buscar = '' }: Props) {
    const { flash } = usePage<Props>().props;
    const [q, setQ] = useState(buscar);
    const [despachando, setDespachando] = useState<Pendiente | null>(null);
    const [cantidades, setCantidades] = useState<Record<number, string>>({});
    const [fecha, setFecha] = useState(() => new Date().toISOString().split('T')[0]);
    const [observacion, setObservacion] = useState('');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    useEffect(() => {
        const t = setTimeout(() => {
            if (q !== buscar) {
                router.get(route('despachos.index'), { buscar: q }, { preserveState: true, preserveScroll: true });
            }
        }, 400);
        return () => clearTimeout(t);
    }, [q, buscar]);

    const columns: Column<Pendiente>[] = useMemo(() => [
        {
            key: 'venta',
            label: 'Nota de venta',
            sortable: false,
            render: (p) => (
                <div className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                    {p.venta?.numero ?? `Despacho #${p.id}`}
                </div>
            ),
        },
        {
            key: 'cliente',
            label: 'Cliente',
            sortable: false,
            render: (p) => (
                <div className="text-sm" style={{ color: 'var(--color-text)' }}>
                    {nombreCliente(p.cliente)}
                </div>
            ),
        },
        {
            key: 'vendedor',
            label: 'Vendedor',
            sortable: false,
            render: (p) => (
                <div className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                    {p.user?.name ?? '—'}
                </div>
            ),
        },
        {
            key: 'local',
            label: 'Local',
            sortable: false,
            render: (p) => (
                <div className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                    {p.venta?.local?.nombre ?? '—'}
                </div>
            ),
        },
        {
            key: 'productos',
            label: 'Productos pendientes',
            sortable: false,
            render: (p) => (
                <div className="space-y-0.5">
                    {p.items.map((item) => (
                        <div key={item.id} className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            <span className="font-medium" style={{ color: 'var(--color-text)' }}>{item.producto_nombre}</span>
                            <span className="ml-1">× {fmtCantidad(item.cantidad_pendiente)} {item.unidad_nombre}</span>
                        </div>
                    ))}
                </div>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            sortable: false,
            render: (p) => (
                <Button
                    size="sm"
                    onClick={() => abrirDespacho(p)}
                    startContent={<PackageCheck size={14} />}
                >
                    Despachar
                </Button>
            ),
        },
    ], []);

    function abrirDespacho(p: Pendiente) {
        setDespachando(p);
        setCantidades(
            Object.fromEntries(p.items.map((i) => [i.id, fmtCantidad(i.cantidad_pendiente)])),
        );
        setFecha(new Date().toISOString().split('T')[0]);
        setObservacion('');
        setErrors({});
    }

    function cerrarDespacho() {
        setDespachando(null);
        setCantidades({});
        setObservacion('');
        setErrors({});
    }

    function confirmarDespacho() {
        if (!despachando) return;
        setSaving(true);

        const items = despachando.items
            .map((item) => ({
                id: item.id,
                cantidad: Number(cantidades[item.id] ?? 0),
            }))
            .filter((i) => i.cantidad > 0.00009);

        router.post(
            route('despachos.confirmar', despachando.id),
            {
                fecha,
                observacion,
                items,
                _method: 'post',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaving(false);
                    cerrarDespacho();
                    toast.success('Despacho confirmado.');
                },
                onError: (errs) => {
                    setSaving(false);
                    setErrors(errs as Record<string, string>);
                    const first = Object.values(errs)[0];
                    if (first) toast.error(first as string);
                },
            },
        );
    }

    const totalADespachar = useMemo(() => {
        if (!despachando) return 0;
        return despachando.items.reduce((sum, item) => {
            const c = Math.max(0, Number(cantidades[item.id] ?? 0));
            return sum + c * Number(item.precio_unitario);
        }, 0);
    }, [despachando, cantidades]);

    return (
        <AppLayout title="Despachos en almacén">
            <PageHeader
                title="Despachos en almacén"
                subtitle="Mercadería vendida pendiente de entrega desde el almacén."
            />

            <div className="mb-4 flex items-center gap-2">
                <div className="relative flex-1 max-w-md">
                    <Search size={16} className="absolute left-2.5 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Buscar nota, cliente, producto..."
                        className="pl-9"
                    />
                </div>
            </div>

            <Table
                data={pendientes.data}
                columns={columns}
                emptyMessage="No hay despachos pendientes."
            />

            {pendientes.last_page > 1 && (
                <div className="mt-4 flex justify-center gap-1">
                    {pendientes.links.map((link, idx) => (
                        <button
                            key={idx}
                            disabled={!link.url || link.active}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className="px-3 py-1 text-xs rounded border"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: link.active ? 'var(--color-primary)' : 'var(--color-surface)',
                                color: link.active ? '#fff' : 'var(--color-text)',
                            }}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            <Modal
                isOpen={!!despachando}
                onClose={cerrarDespacho}
                title={despachando ? `Despachar ${despachando.venta?.numero ?? `#${despachando.id}`}` : 'Despachar'}
                size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={cerrarDespacho} disabled={saving}>
                            Cancelar
                        </Button>
                        <Button onClick={confirmarDespacho} loading={saving}>
                            Confirmar despacho
                        </Button>
                    </>
                }
            >
                {despachando && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between text-sm">
                            <span style={{ color: 'var(--color-text-muted)' }}>Cliente</span>
                            <span className="font-medium" style={{ color: 'var(--color-text)' }}>{nombreCliente(despachando.cliente)}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span style={{ color: 'var(--color-text-muted)' }}>Vendedor</span>
                            <span className="font-medium" style={{ color: 'var(--color-text)' }}>{despachando.user?.name ?? '—'}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span style={{ color: 'var(--color-text-muted)' }}>Local</span>
                            <span className="font-medium" style={{ color: 'var(--color-text)' }}>{despachando.venta?.local?.nombre ?? '—'}</span>
                        </div>

                        <div>
                            <p className="text-sm font-semibold mb-2" style={{ color: 'var(--color-text)' }}>
                                Cantidades a entregar
                            </p>
                            <div className="space-y-2">
                                {despachando.items.map((item) => (
                                    <div key={item.id} className="flex items-center gap-3">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                                {item.producto_nombre}
                                            </p>
                                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                Pendiente: {fmtCantidad(item.cantidad_pendiente)} {item.unidad_nombre}
                                            </p>
                                        </div>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={Number(item.cantidad_pendiente)}
                                            step="any"
                                            value={cantidades[item.id] ?? '0'}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setCantidades((prev) => ({ ...prev, [item.id]: val }));
                                            }}
                                            error={errors[`items.${item.id}.cantidad`] ?? errors.items}
                                            className="w-24 text-right"
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Input
                                label="Fecha de entrega"
                                type="date"
                                value={fecha}
                                onChange={(e) => setFecha(e.target.value)}
                                error={errors.fecha}
                            />
                            <Input
                                label="Observación"
                                value={observacion}
                                onChange={(e) => setObservacion(e.target.value)}
                                placeholder="Opcional"
                            />
                        </div>

                        <div
                            className="rounded-lg px-3 py-2 text-sm"
                            style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                        >
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Valor a despachar</span>
                                <span className="font-bold" style={{ color: 'var(--color-primary)' }}>
                                    S/ {totalADespachar.toFixed(2)}
                                </span>
                            </div>
                            <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                Al confirmar, el stock saldrá del almacén y el pendiente se actualizará.
                            </p>
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
