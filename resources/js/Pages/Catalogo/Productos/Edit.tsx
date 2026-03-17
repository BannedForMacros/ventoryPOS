import { useForm, router } from '@inertiajs/react';
import { Plus, Trash2, AlertCircle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Tabs from '@/Components/UI/Tabs';
import type { PageProps } from '@/types';

interface Categoria    { id: number; nombre: string; }
interface UnidadMedida { id: number; nombre: string; abreviatura: string; }

interface ProductoUnidadData {
    id?: number;
    unidad_medida_id: number | '';
    es_base: boolean;
    factor_conversion: string;
    tipo_precio: 'fijo' | 'referencial';
    precio_venta: string;
    precio_costo: string;
    activo: boolean;
}

interface ProductoData {
    id: number;
    categoria_id: number | null;
    codigo: string | null;
    nombre: string;
    descripcion: string | null;
    tipo: 'producto' | 'servicio';
    tipo_precio: 'fijo' | 'referencial';
    precio_venta: string;
    precio_costo: string;
    activo: boolean;
    unidades: ProductoUnidadData[];
}

interface FormData {
    categoria_id: number | '';
    codigo: string;
    nombre: string;
    descripcion: string;
    tipo: 'producto' | 'servicio';
    tipo_precio: 'fijo' | 'referencial';
    precio_venta: string;
    precio_costo: string;
    activo: boolean;
    unidades: ProductoUnidadData[];
}

interface Props extends PageProps {
    producto: ProductoData;
    categorias: Categoria[];
    unidades: UnidadMedida[];
}

const emptyUnidad = (): ProductoUnidadData => ({
    unidad_medida_id: '', es_base: false, factor_conversion: '1',
    tipo_precio: 'fijo', precio_venta: '', precio_costo: '', activo: true,
});

export default function Edit({ producto, categorias, unidades }: Props) {
    const { data, setData, put, processing, errors } = useForm<FormData>({
        categoria_id:  producto.categoria_id ?? '',
        codigo:        producto.codigo ?? '',
        nombre:        producto.nombre,
        descripcion:   producto.descripcion ?? '',
        tipo:          producto.tipo,
        tipo_precio:   producto.tipo_precio,
        precio_venta:  producto.precio_venta,
        precio_costo:  producto.precio_costo,
        activo:        producto.activo,
        unidades:      producto.unidades.map(u => ({
            id:                u.id,
            unidad_medida_id:  u.unidad_medida_id,
            es_base:           u.es_base,
            factor_conversion: String(u.factor_conversion),
            tipo_precio:       u.tipo_precio,
            precio_venta:      String(u.precio_venta),
            precio_costo:      String(u.precio_costo),
            activo:            u.activo,
        })),
    });

    function setUnidad(index: number, field: keyof ProductoUnidadData, value: unknown) {
        const updated = data.unidades.map((u, i) => i !== index ? u : { ...u, [field]: value });
        if (field === 'es_base' && value === true) {
            updated.forEach((u, i) => { if (i !== index) u.es_base = false; });
            updated[index].factor_conversion = '1';
        }
        setData('unidades', updated);
    }

    function addUnidad() { setData('unidades', [...data.unidades, emptyUnidad()]); }
    function removeUnidad(index: number) { setData('unidades', data.unidades.filter((_, i) => i !== index)); }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(route('catalogo.productos.update', producto.id), {
            onSuccess: () => router.visit(route('catalogo.productos.index')),
        });
    }

    const baseCount = data.unidades.filter(u => u.es_base).length;

    return (
        <AppLayout title="Editar producto">
            <PageHeader
                title="Editar producto / servicio"
                subtitle={producto.nombre}
                backHref={route('catalogo.productos.index')}
            />

            <form onSubmit={submit} className="max-w-3xl mx-auto space-y-8">

                {/* ── Sección 1: Datos generales ── */}
                <section
                    className="rounded-2xl border p-6 space-y-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}
                >
                    <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                        Datos generales
                    </h2>

                    <Tabs
                        tabs={[
                            { value: 'producto', label: 'Producto físico' },
                            { value: 'servicio', label: 'Servicio' },
                        ]}
                        value={data.tipo}
                        onChange={v => setData('tipo', v)}
                    />

                    <div className="grid grid-cols-2 gap-4">
                        <Select label="Categoría" placeholder="Sin categoría" value={data.categoria_id}
                            onChange={v => setData('categoria_id', v === '' ? '' : Number(v))}
                            options={categorias.map(c => ({ value: c.id, label: c.nombre }))}
                            error={errors.categoria_id} />
                        <Input label="Código" placeholder="Código interno o de barras" value={data.codigo}
                            onChange={e => setData('codigo', e.target.value)} error={errors.codigo} />
                    </div>

                    <Input label="Nombre" required value={data.nombre}
                        onChange={e => setData('nombre', e.target.value)} error={errors.nombre} />

                    <div>
                        <label className="text-sm font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Descripción</label>
                        <textarea rows={3} value={data.descripcion}
                            onChange={e => setData('descripcion', e.target.value)}
                            className="w-full rounded-xl border px-3 py-2 text-sm outline-none resize-none transition-all"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                            onFocus={e => e.currentTarget.style.borderColor = 'var(--color-primary)'}
                            onBlur={e => e.currentTarget.style.borderColor = 'var(--color-border)'} />
                    </div>

                    <div>
                        <p className="text-sm font-medium mb-2" style={{ color: 'var(--color-text)' }}>
                            Tipo de precio <span style={{ color: 'var(--color-danger)' }}>*</span>
                        </p>
                        <div className="flex flex-col gap-2">
                            {([
                                { value: 'fijo',        label: 'Fijo',        hint: 'El cajero no puede modificar este precio en la venta' },
                                { value: 'referencial', label: 'Referencial', hint: 'El cajero puede modificar el precio o aplicar descuento' },
                            ] as const).map(opt => (
                                <label key={opt.value} className="flex items-start gap-2 cursor-pointer">
                                    <input type="radio" name="tipo_precio" value={opt.value}
                                        checked={data.tipo_precio === opt.value}
                                        onChange={() => setData('tipo_precio', opt.value)}
                                        className="mt-0.5 accent-[var(--color-primary)]" />
                                    <span>
                                        <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{opt.label}</span>
                                        <span className="ml-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>{opt.hint}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Precio de venta" required type="number" min="0" step="0.01" placeholder="0.00"
                            value={data.precio_venta} onChange={e => setData('precio_venta', e.target.value)} error={errors.precio_venta} />
                        <Input label="Precio de costo" type="number" min="0" step="0.01" placeholder="0.00"
                            value={data.precio_costo} onChange={e => setData('precio_costo', e.target.value)} error={errors.precio_costo} />
                    </div>

                    <Switch label="Activo" checked={data.activo} onChange={v => setData('activo', v)} />
                </section>

                {/* ── Sección 2: Unidades de medida ── */}
                {data.tipo === 'producto' && (
                    <section className="rounded-2xl border p-6 space-y-4"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                                Unidades de medida
                            </h2>
                            <Button type="button" variant="ghost" onClick={addUnidad}>
                                <Plus size={14} className="mr-1" />Agregar unidad
                            </Button>
                        </div>

                        {errors.unidades && (
                            <div className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm"
                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-danger) 10%, transparent)', color: 'var(--color-danger)' }}>
                                <AlertCircle size={15} />{errors.unidades}
                            </div>
                        )}
                        {baseCount !== 1 && data.unidades.length > 0 && !errors.unidades && (
                            <div className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm"
                                style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 10%, transparent)', color: 'var(--color-warning)' }}>
                                <AlertCircle size={15} />
                                {baseCount === 0 ? 'Debes marcar una unidad como base.' : 'Solo una unidad puede ser la base.'}
                            </div>
                        )}

                        {data.unidades.map((u, i) => (
                            <div key={i} className="rounded-xl border p-4 space-y-3"
                                style={{
                                    borderColor: u.es_base ? 'var(--color-primary)' : 'var(--color-border)',
                                    backgroundColor: u.es_base ? 'color-mix(in srgb, var(--color-primary) 4%, transparent)' : 'var(--color-bg)',
                                }}>
                                <div className="flex items-center justify-between">
                                    <label className="flex items-center gap-2 cursor-pointer text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                        <input type="radio" name="unidad_base" checked={u.es_base}
                                            onChange={() => setUnidad(i, 'es_base', true)}
                                            className="accent-[var(--color-primary)]" />
                                        {u.es_base ? 'Unidad base' : 'Unidad secundaria'}
                                    </label>
                                    {data.unidades.length > 1 && (
                                        <button type="button" onClick={() => removeUnidad(i)}
                                            className="rounded-lg p-1 transition-colors" style={{ color: 'var(--color-danger)' }}>
                                            <Trash2 size={15} />
                                        </button>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <Select label="Unidad de medida" required value={u.unidad_medida_id}
                                        onChange={v => setUnidad(i, 'unidad_medida_id', Number(v))}
                                        options={unidades.map(um => ({ value: um.id, label: `${um.nombre} (${um.abreviatura})` }))}
                                        error={(errors as Record<string, string>)[`unidades.${i}.unidad_medida_id`]} />
                                    <Input label="Factor de conversión" type="number" min="0.0001" step="0.0001"
                                        value={u.factor_conversion}
                                        onChange={e => setUnidad(i, 'factor_conversion', e.target.value)}
                                        disabled={u.es_base}
                                        hint={u.es_base ? 'La unidad base siempre es 1' : 'Cuántas unidades base equivale'}
                                        error={(errors as Record<string, string>)[`unidades.${i}.factor_conversion`]} />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <Input label="Precio de venta" required type="number" min="0" step="0.01" placeholder="0.00"
                                        value={u.precio_venta} onChange={e => setUnidad(i, 'precio_venta', e.target.value)}
                                        error={(errors as Record<string, string>)[`unidades.${i}.precio_venta`]} />
                                    <Input label="Precio de costo" type="number" min="0" step="0.01" placeholder="0.00"
                                        value={u.precio_costo} onChange={e => setUnidad(i, 'precio_costo', e.target.value)} />
                                </div>

                                <div>
                                    <p className="text-sm font-medium mb-1.5" style={{ color: 'var(--color-text)' }}>Tipo de precio</p>
                                    <div className="flex gap-4">
                                        {([{ value: 'fijo', label: 'Fijo' }, { value: 'referencial', label: 'Referencial' }] as const).map(opt => (
                                            <label key={opt.value} className="flex items-center gap-1.5 cursor-pointer text-sm" style={{ color: 'var(--color-text)' }}>
                                                <input type="radio" checked={u.tipo_precio === opt.value}
                                                    onChange={() => setUnidad(i, 'tipo_precio', opt.value)}
                                                    className="accent-[var(--color-primary)]" />
                                                {opt.label}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </section>
                )}

                <div className="flex gap-3">
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('catalogo.productos.index'))}>
                        Cancelar
                    </Button>
                    <Button type="submit" loading={processing}>Guardar cambios</Button>
                </div>
            </form>
        </AppLayout>
    );
}
