import { Package, Tag } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import type { Producto, ProductoUnidad } from '@/types';

interface Props {
    isOpen:    boolean;
    onClose:   () => void;
    producto:  Producto | null;
    onElegir:  (producto: Producto, unidad: ProductoUnidad) => void;
}

const sol = (n: string | number) =>
    `S/. ${Number(n).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * Modal que se abre cuando un producto/servicio tiene mas de una presentacion.
 * Muestra cada presentacion como un boton grande tactil con su precio destacado.
 *
 * UX intencional para usuarios torpes:
 * - Botones GRANDES, pensados para tablet/dedo.
 * - Precio en grande, nombre arriba.
 * - El nombre de la presentacion (ej. "Talla grande") es lo que distingue.
 * - Si hay solo 1 presentacion, este modal nunca se abre (el caller decide).
 */
export default function ModalSelectorPresentacion({ isOpen, onClose, producto, onElegir }: Props) {
    if (!producto) return null;

    const presentaciones = (producto.unidades ?? [])
        .filter(u => u.activo !== false)
        .sort((a, b) => Number(b.es_base) - Number(a.es_base));

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Elige la presentación" size="md">
            <div className="mb-3">
                <div className="flex items-center gap-2 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                    <Package size={14} />
                    <span>Producto</span>
                </div>
                <p className="text-base font-bold mt-0.5" style={{ color: 'var(--color-text)' }}>
                    {producto.nombre}
                </p>
                {producto.codigo && (
                    <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                        Código: {producto.codigo}
                    </p>
                )}
            </div>

            <p className="text-xs mb-3" style={{ color: 'var(--color-text-muted)' }}>
                Selecciona la presentación que el cliente está comprando.
            </p>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {presentaciones.map(u => (
                    <button
                        key={u.id}
                        onClick={() => { onElegir(producto, u); onClose(); }}
                        className="group relative rounded-xl p-4 text-left transition-all hover:scale-[1.02] active:scale-[0.98]"
                        style={{
                            backgroundColor: 'var(--color-surface)',
                            border: '2px solid var(--color-border)',
                        }}
                        onMouseEnter={e => { e.currentTarget.style.borderColor = 'var(--color-primary)'; }}
                        onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--color-border)'; }}
                    >
                        {u.es_base && (
                            <span
                                className="absolute top-2 right-2 text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                                style={{
                                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 15%, transparent)',
                                    color: 'var(--color-primary)',
                                }}
                            >
                                Principal
                            </span>
                        )}

                        <div className="flex items-center gap-1.5">
                            <Tag size={12} style={{ color: 'var(--color-text-muted)' }} />
                            <span className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>
                                Presentación
                            </span>
                        </div>

                        <p className="text-base font-bold mt-1" style={{ color: 'var(--color-text)' }}>
                            {u.unidad_medida?.nombre ?? '—'}
                        </p>

                        <p
                            className="mt-2 text-xl font-extrabold tracking-tight"
                            style={{ color: 'var(--color-primary)' }}
                        >
                            {sol(u.precio_venta)}
                        </p>
                    </button>
                ))}
            </div>

            {presentaciones.length === 0 && (
                <p className="text-center text-sm py-4" style={{ color: 'var(--color-text-muted)' }}>
                    Este producto no tiene presentaciones activas configuradas.
                </p>
            )}
        </Modal>
    );
}
