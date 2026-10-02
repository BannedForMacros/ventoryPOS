import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Loader2, Package, PackageCheck, ShoppingCart, Wrench } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Switch from '@/Components/UI/Switch';
import type { Producto } from '@/types';
import AvisoProductosParecidos from '@/Components/Catalogo/AvisoProductosParecidos';

interface Datos {
    categorias: { id: number; nombre: string }[];
    unidades: { id: number; nombre: string; abreviatura: string }[];
    incluye_igv: boolean;
    inventarioInicial: { almacen: string; fecha: string } | null;
}

interface Form {
    tipo: 'producto' | 'servicio';
    nombre: string;
    codigo: string;
    categoria_id: number | '';
    unidad_medida_id: number | '';
    precio_venta: string;
    incluye_igv: boolean;
    stock_inicial: string;
    costo_inicial: string;
}

/**
 * Alta rápida de un producto sin salir del POS. Se crea igual que en el
 * Catálogo (una sola presentación; las demás se agregan luego allí) y entra
 * directo al carrito.
 */
export default function ModalNuevoProducto({ isOpen, onClose, nombreInicial = '', ventaId, onCreado }: {
    isOpen: boolean;
    onClose: () => void;
    /** Lo que la cajera estaba buscando: suele ser el nombre del producto. */
    nombreInicial?: string;
    ventaId?: number | null;
    onCreado: (producto: Producto) => void;
}) {
    const [datos, setDatos] = useState<Datos | null>(null);
    const [form, setForm] = useState<Form>(vacio(''));
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);
    const cargado = useRef(false);

    useEffect(() => {
        if (!isOpen) return;
        setErrores({});
        setForm(f => ({ ...vacio(nombreInicial), incluye_igv: datos?.incluye_igv ?? f.incluye_igv, unidad_medida_id: unidadPorDefecto(datos) }));
        if (cargado.current) return;
        cargado.current = true;
        axios.get<Datos>(route('pos.productos.nuevo')).then(({ data }) => {
            setDatos(data);
            setForm(f => ({ ...f, incluye_igv: data.incluye_igv, unidad_medida_id: f.unidad_medida_id || unidadPorDefecto(data) }));
        }).catch(() => {
            cargado.current = false;
            toast.error('No se pudieron cargar las categorías y presentaciones.');
        });
    }, [isOpen]); // eslint-disable-line react-hooks/exhaustive-deps

    const set = <K extends keyof Form>(k: K, v: Form[K]) => setForm(f => ({ ...f, [k]: v }));
    const esProducto = form.tipo === 'producto';
    const unidad = datos?.unidades.find(u => u.id === form.unidad_medida_id);

    async function guardar(e?: React.FormEvent) {
        e?.preventDefault();
        if (guardando) return;
        setGuardando(true);
        setErrores({});
        try {
            const { data } = await axios.post<{ producto: Producto; aviso: string | null }>(route('pos.productos.crear'), {
                tipo: form.tipo,
                nombre: form.nombre.trim(),
                codigo: form.codigo.trim() || null,
                categoria_id: form.categoria_id || null,
                incluye_igv: form.incluye_igv,
                activo: true,
                venta_id: ventaId ?? null,
                ...(esProducto ? {
                    unidades: [{
                        unidad_medida_id: form.unidad_medida_id || null, es_base: true, factor_conversion: 1,
                        tipo_precio: 'fijo', precio_venta: form.precio_venta || 0, activo: true,
                    }],
                    stock_inicial: form.stock_inicial.trim() || null,
                    costo_inicial: form.costo_inicial.trim() || null,
                } : {
                    tipo_precio: 'fijo', precio_venta: form.precio_venta || 0,
                }),
            });
            onCreado(data.producto);
            toast.success(`«${data.producto.nombre}» creado y agregado al carrito.${data.aviso ? ` ${data.aviso}` : ''}`, { duration: 3500 });
            onClose();
        } catch (err: unknown) {
            const r = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string } } }).response;
            if (r?.status === 422 && r.data?.errors) {
                const planos: Record<string, string> = {};
                Object.entries(r.data.errors).forEach(([k, v]) => {
                    const clave = k.startsWith('unidades.0.') ? k.replace('unidades.0.', '') : k === 'unidades' ? 'unidad_medida_id' : k;
                    planos[clave] = v[0];
                });
                setErrores(planos);
            } else {
                toast.error(r?.data?.message ?? 'No se pudo crear el producto.');
            }
        } finally {
            setGuardando(false);
        }
    }

    const fecha = datos?.inventarioInicial
        ? new Date(`${datos.inventarioInicial.fecha}T12:00:00`).toLocaleDateString('es-PE', { day: 'numeric', month: 'long' })
        : '';

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Nuevo producto" size="lg"
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                <Button onClick={() => guardar()} loading={guardando} disabled={!datos} startContent={<ShoppingCart size={15} />}>
                    Crear y agregar al carrito
                </Button>
            </>}>
            {!datos ? (
                <div className="flex items-center justify-center gap-2 py-12 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                    <Loader2 size={16} className="animate-spin" /> Cargando…
                </div>
            ) : (
                <form onSubmit={guardar} className="space-y-4">
                    {/* Tipo */}
                    <div className="grid grid-cols-2 gap-2 p-1 rounded-xl" style={{ backgroundColor: 'var(--color-bg)' }} role="group" aria-label="Tipo">
                        {([['producto', 'Producto físico', Package], ['servicio', 'Servicio', Wrench]] as const).map(([v, t, Icono]) => (
                            <button key={v} type="button" onClick={() => set('tipo', v)} aria-pressed={form.tipo === v}
                                className="flex items-center justify-center gap-1.5 py-2 rounded-lg text-sm font-semibold transition-colors"
                                style={{
                                    backgroundColor: form.tipo === v ? 'var(--color-surface)' : 'transparent',
                                    color: form.tipo === v ? 'var(--color-primary)' : 'var(--color-text-muted)',
                                    boxShadow: form.tipo === v ? '0 1px 3px rgba(0,0,0,0.08)' : undefined,
                                }}>
                                <Icono size={15} /> {t}
                            </button>
                        ))}
                    </div>

                    <div>
                        <Input label="Nombre" required autoFocus maxLength={150} value={form.nombre}
                            onChange={e => set('nombre', e.target.value)} error={errores.nombre} />
                        <AvisoProductosParecidos nombre={form.nombre} />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Input label="Código (opcional)" maxLength={50} value={form.codigo}
                            onChange={e => set('codigo', e.target.value)} error={errores.codigo} />
                        <SearchableSelect label="Categoría (opcional)" value={form.categoria_id}
                            onChange={v => set('categoria_id', v ? Number(v) : '')}
                            options={[{ value: '', label: 'Sin categoría' }, ...datos.categorias.map(c => ({ value: c.id, label: c.nombre }))]}
                            searchPlaceholder="Buscar categoría…" error={errores.categoria_id} />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {esProducto && (
                            <SearchableSelect label="Se vende por" required value={form.unidad_medida_id}
                                onChange={v => set('unidad_medida_id', v ? Number(v) : '')}
                                options={datos.unidades.map(u => ({ value: u.id, label: `${u.nombre} (${u.abreviatura})` }))}
                                searchPlaceholder="Buscar presentación…" emptyMessage="No hay presentaciones: créalas en Catálogo."
                                error={errores.unidad_medida_id} />
                        )}
                        <Input label={`Precio de venta${unidad ? ` (por ${unidad.nombre.toLowerCase()})` : ''}`} required
                            type="number" min="0" step="any" inputMode="decimal" placeholder="0.00"
                            value={form.precio_venta} onChange={e => set('precio_venta', e.target.value)} error={errores.precio_venta} />
                    </div>

                    <Switch label="El precio incluye IGV" checked={form.incluye_igv} onChange={v => set('incluye_igv', v)}
                        description="Viene marcado como la mayoría de tus productos." />

                    {esProducto && (
                        <div className="rounded-xl p-3.5 space-y-3" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 7%, var(--color-surface))', border: '1px solid color-mix(in srgb, var(--vp-mint) 30%, var(--color-border))' }}>
                            <p className="flex items-center gap-2 text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                <PackageCheck size={16} style={{ color: 'var(--vp-mint-ink)' }} />
                                Stock inicial <span className="font-normal" style={{ color: 'var(--color-text-muted)' }}>(opcional)</span>
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <Input label={`Cantidad${unidad ? ` (en ${unidad.nombre.toLowerCase()})` : ''}`} type="number" min="0" step="any" inputMode="decimal" placeholder="0"
                                    value={form.stock_inicial} onChange={e => set('stock_inicial', e.target.value)} error={errores.stock_inicial} />
                                <Input label="Costo unitario" type="number" min="0" step="any" inputMode="decimal" placeholder="0.00"
                                    value={form.costo_inicial} onChange={e => set('costo_inicial', e.target.value)} error={errores.costo_inicial} />
                            </div>
                            <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                {datos.inventarioInicial
                                    ? `Queda en ${datos.inventarioInicial.almacen}, contado al ${fecha}. La venta que estás haciendo lo descuenta.`
                                    : 'Tu usuario no tiene almacén asignado: el stock se podrá cargar luego en Inventario inicial.'}
                            </p>
                        </div>
                    )}

                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                        Se crea con una sola presentación. Si luego necesitas otras (caja, docena…), agrégalas en Catálogo, Productos.
                    </p>
                    <button type="submit" className="hidden" aria-hidden tabIndex={-1} />
                </form>
            )}
        </Modal>
    );
}

function vacio(nombre: string): Form {
    return {
        tipo: 'producto', nombre, codigo: '', categoria_id: '', unidad_medida_id: '',
        precio_venta: '', incluye_igv: false, stock_inicial: '', costo_inicial: '',
    };
}

/** "Unidad" (o la primera) viene elegida: es la presentación más común. */
function unidadPorDefecto(datos: Datos | null): number | '' {
    if (!datos?.unidades.length) return '';
    const und = datos.unidades.find(u => /^unidad/i.test(u.nombre) || /^und$/i.test(u.abreviatura));
    return (und ?? datos.unidades[0]).id;
}
