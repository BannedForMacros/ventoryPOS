import { useEffect, useMemo, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';
import { hoyLocal } from '@/lib/fechas';

interface Almacen { id: number; nombre: string; local?: { nombre: string } | null; }

interface ProductoFila {
    id: number;
    codigo: string | null;
    nombre: string;
    categoria: string | null;
    categoria_id: number | null;
    stock_sistema: number;
    costo: number;
}

interface ItemDeclarado {
    producto_id: number;
    stock_sistema: number;
    stock_declarado: string;
    observacion: string;
}

interface Props extends PageProps {
    almacenes: Almacen[];
    mostrarSelector: boolean;
    turnoId: number | null;
    almacenSugerido: number | null;
    precarga: boolean;
}

export default function CierreCreate({ almacenes, mostrarSelector, turnoId, almacenSugerido, precarga }: Props) {
    const { flash } = usePage<Props>().props;
    const [almacenId, setAlmacenId]     = useState<number | ''>(
        almacenSugerido ?? (almacenes.length === 1 ? almacenes[0].id : '')
    );
    const [productos, setProductos]     = useState<ProductoFila[]>([]);
    const [cargando, setCargando]       = useState(false);
    const [filtroNombre, setFiltroNombre] = useState('');
    const [filtroCategoria, setFiltroCategoria] = useState<number | ''>('');
    const [items, setItems]             = useState<Record<number, ItemDeclarado>>({});

    const { data, setData, post, processing, errors, transform } = useForm({
        almacen_id: '' as number | '',
        fecha: hoyLocal(),
        observacion: '',
        confirmar: false,
        items: [] as ItemDeclarado[],
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    useEffect(() => {
        setData('almacen_id', almacenId);
        if (!almacenId) { setProductos([]); return; }

        setCargando(true);
        axios.get(route('inventario.cierres.productos'), { params: { almacen_id: almacenId } })
            .then(r => {
                const prods: ProductoFila[] = r.data.productos ?? r.data;
                setProductos(prods);
                // Modo lógico (precarga): declarado arranca = stock del sistema (editas
                // solo lo que difiere). Modo en blanco: declarado vacío (declaras todo).
                const initial: Record<number, ItemDeclarado> = {};
                prods.forEach((p: ProductoFila) => {
                    initial[p.id] = {
                        producto_id: p.id,
                        stock_sistema: p.stock_sistema,
                        stock_declarado: precarga ? String(p.stock_sistema) : '',
                        observacion: '',
                    };
                });
                setItems(initial);
            })
            .catch(() => toast.error('Error al cargar productos'))
            .finally(() => setCargando(false));
    }, [almacenId]);

    const categorias = useMemo(() => {
        const map = new Map<number, string>();
        productos.forEach(p => { if (p.categoria_id && p.categoria) map.set(p.categoria_id, p.categoria); });
        return Array.from(map.entries()).map(([id, nombre]) => ({ value: String(id), label: nombre }));
    }, [productos]);

    const productosFiltrados = productos.filter(p => {
        const matchNombre = !filtroNombre
            || p.nombre.toLowerCase().includes(filtroNombre.toLowerCase())
            || (p.codigo ?? '').toLowerCase().includes(filtroNombre.toLowerCase());
        const matchCat = !filtroCategoria || p.categoria_id === filtroCategoria;
        return matchNombre && matchCat;
    });

    function setDeclarado(productoId: number, valor: string) {
        setItems(prev => ({
            ...prev,
            [productoId]: { ...prev[productoId], stock_declarado: valor },
        }));
    }

    function setObsItem(productoId: number, valor: string) {
        setItems(prev => ({
            ...prev,
            [productoId]: { ...prev[productoId], observacion: valor },
        }));
    }

    function calcularDiferencia(item: ItemDeclarado): number | null {
        if (item.stock_declarado === '') return null;
        const declarado = parseFloat(item.stock_declarado);
        if (isNaN(declarado)) return null;
        return +(declarado - item.stock_sistema).toFixed(4);
    }

    function submit(confirmar: boolean) {
        const itemsArr = Object.values(items).filter(i => i.stock_declarado !== '');

        if (itemsArr.length === 0) {
            toast.error('Debes declarar al menos un producto.');
            return;
        }

        // Modo en blanco (total): exige declarar TODOS los productos.
        if (!precarga && itemsArr.length < productos.length) {
            toast.error(`Faltan ${productos.length - itemsArr.length} productos por declarar (modo conteo total).`);
            return;
        }

        // Inertia useForm.post() no acepta `data` en sus options. Para inyectar
        // campos que no viven en el form-state (almacen_id, turno_id, items
        // calculados en cada submit) usamos transform(): se ejecuta justo
        // antes de enviar y produce el payload final.
        transform(d => ({
            ...d,
            almacen_id: almacenId,
            turno_id: turnoId,
            items: itemsArr,
            confirmar,
        }));

        post(route('inventario.cierres.store'), {
            forceFormData: false,
        });
    }

    const totalConDiferencia = Object.values(items).filter(i => {
        const d = calcularDiferencia(i);
        return d !== null && d !== 0;
    }).length;

    return (
        <AppLayout title="Nuevo cierre de inventario">
            <PageHeader
                title="Nuevo cierre de inventario"
                subtitle={precarga
                    ? 'Modo lógico: viene cargado el stock del sistema; corrige solo lo que difiera'
                    : 'Modo conteo total: declara la cantidad real de TODOS los productos'}
                backHref={route('inventario.cierres.index')}
            />

            <div className="space-y-6 max-w-6xl">
                <section className="rounded-2xl border p-6 space-y-4"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="grid grid-cols-3 gap-4">
                        <Select
                            label="Almacén"
                            required
                            value={almacenId}
                            onChange={v => setAlmacenId(v === '' ? '' : Number(v))}
                            options={almacenes.map(a => ({
                                value: a.id,
                                label: `${a.nombre}${a.local ? ' · ' + a.local.nombre : ''}`,
                            }))}
                            error={errors.almacen_id as string | undefined}
                            disabled={!mostrarSelector && almacenes.length === 1}
                        />
                        <Input
                            label="Fecha"
                            type="date"
                            required
                            value={data.fecha}
                            onChange={e => setData('fecha', e.target.value)}
                            error={errors.fecha}
                        />
                        <Input
                            label="Observación (opcional)"
                            value={data.observacion}
                            onChange={e => setData('observacion', e.target.value)}
                        />
                    </div>
                </section>

                {almacenId !== '' && (
                    <section className="rounded-2xl border p-6 space-y-4"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <div className="flex flex-wrap gap-3 items-end">
                            <div className="flex-1 min-w-[200px]">
                                <Input
                                    label="Buscar producto"
                                    placeholder="Nombre o código"
                                    value={filtroNombre}
                                    onChange={e => setFiltroNombre(e.target.value)}
                                />
                            </div>
                            <div className="min-w-[200px]">
                                <Select
                                    label="Categoría"
                                    value={filtroCategoria === '' ? '' : String(filtroCategoria)}
                                    onChange={v => setFiltroCategoria(v === '' ? '' : Number(v))}
                                    options={[
                                        { value: '', label: 'Todas' },
                                        ...categorias,
                                    ]}
                                />
                            </div>
                            <Badge variant="primary">
                                {productosFiltrados.length} productos
                            </Badge>
                            {totalConDiferencia > 0 && (
                                <Badge variant="warning">
                                    {totalConDiferencia} con diferencia
                                </Badge>
                            )}
                            {precarga && (
                                <Button variant="ghost" onClick={() => setItems(prev => {
                                    const next = { ...prev };
                                    productos.forEach(p => { next[p.id] = { ...next[p.id], stock_declarado: String(p.stock_sistema) }; });
                                    return next;
                                })}>
                                    Reponer todo al sistema
                                </Button>
                            )}
                        </div>
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            {precarga
                                ? 'Cada producto trae el stock del sistema. Cambia solo los que contaste distinto; los demás quedan sin diferencia.'
                                : 'Debes ingresar la cantidad real de todos los productos antes de confirmar.'}
                        </p>

                        {cargando && <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Cargando productos...</p>}

                        {!cargando && productosFiltrados.length === 0 && (
                            <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                No hay productos para este filtro.
                            </p>
                        )}

                        {!cargando && productosFiltrados.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead style={{ color: 'var(--color-text-muted)' }}>
                                        <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                            <th className="text-left py-2 px-2 font-medium">Producto</th>
                                            <th className="text-right py-2 px-2 font-medium">Stock sistema</th>
                                            <th className="text-right py-2 px-2 font-medium">Stock declarado</th>
                                            <th className="text-right py-2 px-2 font-medium">Diferencia</th>
                                            <th className="text-left py-2 px-2 font-medium">Obs.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {productosFiltrados.map(p => {
                                            const item = items[p.id];
                                            const diff = item ? calcularDiferencia(item) : null;
                                            return (
                                                <tr key={p.id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                                    <td className="py-2 px-2">
                                                        <div className="font-medium" style={{ color: 'var(--color-text)' }}>{p.nombre}</div>
                                                        {p.codigo && (
                                                            <div className="text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>{p.codigo}</div>
                                                        )}
                                                    </td>
                                                    <td className="py-2 px-2 text-right tabular-nums">
                                                        {p.stock_sistema.toFixed(2)}
                                                    </td>
                                                    <td className="py-2 px-2 w-32">
                                                        <input
                                                            type="number"
                                                            step="0.0001"
                                                            min="0"
                                                            value={item?.stock_declarado ?? ''}
                                                            onChange={e => setDeclarado(p.id, e.target.value)}
                                                            className="w-full rounded-lg border px-2 py-1 text-sm text-right"
                                                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                                        />
                                                    </td>
                                                    <td className="py-2 px-2 text-right tabular-nums">
                                                        {diff === null ? (
                                                            <span style={{ color: 'var(--color-text-muted)' }}>—</span>
                                                        ) : diff === 0 ? (
                                                            <span style={{ color: 'var(--color-success)' }}>0</span>
                                                        ) : (
                                                            <span style={{ color: diff < 0 ? 'var(--color-danger)' : 'var(--color-warning)' }}>
                                                                {diff > 0 ? '+' : ''}{diff.toFixed(2)}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-2 px-2 w-40">
                                                        <input
                                                            type="text"
                                                            value={item?.observacion ?? ''}
                                                            onChange={e => setObsItem(p.id, e.target.value)}
                                                            placeholder="—"
                                                            className="w-full rounded-lg border px-2 py-1 text-xs"
                                                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                )}

                <div className="flex gap-3">
                    <Button variant="ghost" onClick={() => router.visit(route('inventario.cierres.index'))}>
                        Cancelar
                    </Button>
                    <Button variant="ghost" loading={processing} onClick={() => submit(false)}>
                        Guardar borrador
                    </Button>
                    <Button loading={processing} onClick={() => submit(true)}>
                        Confirmar y ajustar stock
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
