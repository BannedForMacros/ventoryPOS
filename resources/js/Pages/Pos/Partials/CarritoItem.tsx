import React, { useEffect, useRef, useState } from 'react';
import toast from 'react-hot-toast';
import { Trash2, Minus, Plus, Percent, X, AlertTriangle, Info, History } from 'lucide-react';
import type { DescuentoConcepto } from '@/types';

/** Historial de precios de venta de un producto a un cliente concreto. */
export interface HistorialPrecioCliente {
    ultimo_precio: number;
    ultima_fecha:  string;
    veces:         number;
    historial:     { fecha: string; precio: number; cantidad: number; unidad: string }[];
}

/** Sobre qué base aplica el descuento por línea. */
export type DescModo = 'pu' | 'total';
/** Cómo se expresa el valor del descuento. */
export type DescTipo = 'monto' | 'porcentaje';

export interface LineaCarrito {
    key:                  string;
    producto_id:          number;
    producto_unidad_id:   number;
    producto_nombre:      string;
    unidad_nombre:        string;
    precio_unitario:      number;
    precio_original:      number;
    // Piso del precio editable: costo de la presentación (costo de la unidad,
    // o costo base del producto × factor de conversión). 0 = sin costo definido,
    // en ese caso no se valida piso.
    costo_minimo:         number;
    // Stock disponible del producto (unidad base) al abrir el POS, y factor de
    // conversión de la presentación: sirven para mostrar el stock restante en
    // vivo (stock − cantidad×factor). stock_disponible null = desconocido.
    stock_disponible:     number | null;
    // Comprado pero aún no llegado (unidad base) + fecha del primer camión.
    stock_en_transito?:   number;
    transito_fecha?:      string | null;
    factor_conversion:    number;
    cantidad:             number;
    // Descuento por línea. `descuento_item` es SIEMPRE el descuento efectivo en
    // soles POR UNIDAD (lo que el backend persiste y con lo que se calcula el
    // subtotal: subtotal = (precio_unitario − descuento_item) × cantidad).
    // Los campos `descuento_modo/tipo/valor` son sólo de UI: guardan CÓMO lo
    // tecleó el cajero (afecta al P.U o al total; en soles o %) para poder
    // reeditarlo y recalcular `descuento_item` cuando cambia precio o cantidad.
    descuento_item:       number;
    descuento_modo:       DescModo;
    descuento_tipo:       DescTipo;
    descuento_valor:      number;
    descuento_concepto_id: number | null;
    subtotal:             number;
    incluye_igv:          boolean;
    // Flags opcionales (solo presentes en items que vienen de cita prellenada).
    // Cuando true, la linea NO se puede vender y se renderiza marcada en rojo.
    inactivo?:            boolean;
    motivo_inactivo?:     string;
}

interface Props {
    item:               LineaCarrito;
    conceptos:          DescuentoConcepto[];
    historial?:         HistorialPrecioCliente;
    onCantidad:         (key: string, delta: number) => void;
    onCantidadExacta:   (key: string, cantidad: number) => void;
    onPrecio:           (key: string, precio: number) => void;
    onDescuento:        (key: string, valor: number, modo: DescModo, tipo: DescTipo, conceptoId: number | null) => void;
    onEliminar:         (key: string) => void;
}

export default function CarritoItem({ item, conceptos, historial, onCantidad, onCantidadExacta, onPrecio, onDescuento, onEliminar }: Props) {
    const [showHistorial, setShowHistorial] = useState(false);
    const [showDescuento, setShowDescuento] = useState((item.descuento_valor || item.descuento_item) > 0);
    const [descuentoVal, setDescuentoVal]   = useState(String(item.descuento_valor || ''));
    const [descModo, setDescModo]           = useState<DescModo>(item.descuento_modo ?? 'pu');
    const [descTipo, setDescTipo]           = useState<DescTipo>(item.descuento_tipo ?? 'monto');
    const [conceptoId, setConceptoId]       = useState<number | null>(item.descuento_concepto_id);
    // Borradores locales de cantidad y precio: se escriben libremente y se
    // aplican al carrito al salir del input (blur) o con Enter.
    const [cantidadVal, setCantidadVal]     = useState(String(item.cantidad));
    const [precioVal, setPrecioVal]         = useState(item.precio_unitario.toFixed(2));
    // Foco del stepper de cantidad: el resaltado va en el CONTENEDOR (box-shadow),
    // no en el input, porque el input vive dentro de un overflow-hidden que
    // recortaba el ring arriba y abajo.
    const [cantFocus, setCantFocus]         = useState(false);
    const [precioFocus, setPrecioFocus]     = useState(false);
    const [descFocus, setDescFocus]         = useState(false);
    // Consulta informativa del precio de costo de la línea (toggle).
    const [showCosto, setShowCosto]         = useState(false);

    useEffect(() => {
        // Sincronizar los borradores locales con el estado real del carrito SOLO
        // cuando el input de descuento no está enfocado (mientras teclea, no le
        // reescribimos lo que escribe). El modo/tipo/concepto sí se reflejan
        // siempre porque se cambian con botones, no tecleando.
        if (!descFocus) setDescuentoVal(String(item.descuento_valor || ''));
        setDescModo(item.descuento_modo ?? 'pu');
        setDescTipo(item.descuento_tipo ?? 'monto');
        setConceptoId(item.descuento_concepto_id);
        if ((item.descuento_valor || item.descuento_item) > 0) setShowDescuento(true);
    }, [item.descuento_valor, item.descuento_item, item.descuento_modo, item.descuento_tipo, item.descuento_concepto_id, descFocus]);

    // Sincronizar el borrador con el valor real SOLO cuando el input no está
    // enfocado: mientras el usuario escribe, no reformateamos lo que teclea.
    useEffect(() => { if (!cantFocus) setCantidadVal(String(item.cantidad)); }, [item.cantidad, cantFocus]);
    useEffect(() => { if (!precioFocus) setPrecioVal(item.precio_unitario.toFixed(2)); }, [item.precio_unitario, precioFocus]);

    // Descuento EN VIVO: cualquier cambio (valor tecleado, modo, tipo o
    // concepto) recalcula el carrito al instante. El padre deriva el descuento
    // efectivo por unidad a partir de estos parámetros.
    function emitir(valor: number, modo: DescModo, tipo: DescTipo, cid: number | null) {
        onDescuento(item.key, valor, modo, tipo, valor > 0 ? cid : null);
    }
    function onCambioDescuento(valor: string) {
        setDescuentoVal(valor);
        emitir(parseFloat(valor) || 0, descModo, descTipo, conceptoId);
    }
    function cambiarModo(modo: DescModo) {
        setDescModo(modo);
        emitir(parseFloat(descuentoVal) || 0, modo, descTipo, conceptoId);
    }
    function cambiarTipo(tipo: DescTipo) {
        setDescTipo(tipo);
        emitir(parseFloat(descuentoVal) || 0, descModo, tipo, conceptoId);
    }
    function cambiarConcepto(cid: number | null) {
        setConceptoId(cid);
        const val = parseFloat(descuentoVal) || 0;
        if (val > 0) emitir(val, descModo, descTipo, cid);
    }

    function aplicarDescuento() {
        const val = parseFloat(descuentoVal) || 0;
        emitir(val, descModo, descTipo, conceptoId);
        if (val === 0) setShowDescuento(false);
    }

    function quitarDescuento() {
        setDescuentoVal('');
        setConceptoId(null);
        setDescModo('pu');
        setDescTipo('monto');
        onDescuento(item.key, 0, 'pu', 'monto', null);
        setShowDescuento(false);
    }

    // Precio efectivo por unidad tras el descuento (lo que realmente se cobra c/u).
    const precioEfectivo = Math.round((item.precio_unitario - item.descuento_item) * 100) / 100;
    const hayDescuento   = item.descuento_item > 0.0001;

    // Cantidad EN VIVO: aplica al carrito en cada tecla (los totales se
    // actualizan al instante). El borrador se conserva para poder borrar/retipear.
    function onCambioCantidad(valor: string) {
        setCantidadVal(valor);
        const val = parseFloat(valor);
        if (isFinite(val) && val > 0) {
            onCantidadExacta(item.key, val);
        }
    }

    function aplicarCantidad() {
        // Al salir, si quedó vacío o inválido, restaurar al valor real.
        const val = parseFloat(cantidadVal);
        if (!isFinite(val) || val <= 0) {
            setCantidadVal(String(item.cantidad));
        }
    }

    // Validación EN VIVO del precio: se evalúa sobre lo que se está tecleando,
    // no recién al salir del input. Mientras esté por debajo del costo, el
    // input se pinta rojo, aparece el aviso inline y se dispara un toast
    // (con id fijo para que no se acumulen por cada tecla). Además NO se
    // permite quedarse bajo el costo: tras una pausa breve de tipeo (que deja
    // terminar de escribir números como "11" cuyo primer dígito "1" cae bajo
    // el costo), el precio se ajusta solo al costo mínimo.
    const precioNum      = parseFloat(precioVal) || 0;
    const precioBajoCosto = item.costo_minimo > 0 && precioNum > 0 && precioNum < item.costo_minimo - 0.009;
    const clampTimer = useRef<number | null>(null);

    useEffect(() => () => {
        if (clampTimer.current) window.clearTimeout(clampTimer.current);
    }, []);

    function ajustarAlCosto() {
        const costo = Math.round(item.costo_minimo * 100) / 100;
        setPrecioVal(costo.toFixed(2));
        onPrecio(item.key, costo);
        toast.error(
            `Precio ajustado al costo mínimo: S/ ${costo.toFixed(2)} ("${item.producto_nombre}").`,
            { id: `precio-bajo-costo-${item.key}`, duration: 3500 },
        );
    }

    function onCambioPrecio(valor: string) {
        setPrecioVal(valor);
        if (clampTimer.current) window.clearTimeout(clampTimer.current);
        const num = parseFloat(valor) || 0;
        // Aplicar EN VIVO al carrito para que los totales se actualicen al instante.
        if (isFinite(num) && num > 0) {
            onPrecio(item.key, num);
        }
        if (item.costo_minimo > 0 && num > 0 && num < item.costo_minimo - 0.009) {
            toast.error(
                `El precio de "${item.producto_nombre}" no puede ser menor al costo: S/ ${item.costo_minimo.toFixed(2)}.`,
                { id: `precio-bajo-costo-${item.key}`, duration: 3500 },
            );
            // No dejar el precio bajo el costo: se corrige solo tras la pausa.
            clampTimer.current = window.setTimeout(ajustarAlCosto, 900);
        }
    }

    function aplicarPrecio() {
        if (clampTimer.current) window.clearTimeout(clampTimer.current);
        const val = Math.round((parseFloat(precioVal) || 0) * 100) / 100;
        if (val <= 0) {
            setPrecioVal(item.precio_unitario.toFixed(2));
            return;
        }
        if (item.costo_minimo > 0 && val < item.costo_minimo - 0.009) {
            // Bajo el costo al salir del campo: se fuerza al costo mínimo.
            ajustarAlCosto();
            return;
        }
        onPrecio(item.key, val);
    }

    const esInactivo = !!item.inactivo;

    // Stock restante EN VIVO = stock disponible − (cantidad × factor). Se
    // recalcula en cada render, así que baja conforme la cajera sube la cantidad.
    const stockRestante = item.stock_disponible != null
        ? Math.round((item.stock_disponible - item.cantidad * item.factor_conversion) * 10000) / 10000
        : null;
    const sinStock = stockRestante != null && stockRestante < 0;

    return (
        <div
            className="rounded-xl p-3 mb-2 transition-all"
            style={{
                backgroundColor: esInactivo ? 'rgba(239,68,68,0.06)' : 'var(--color-surface)',
                border: esInactivo
                    ? '1px solid var(--color-danger)'
                    : '1px solid var(--color-border)',
                boxShadow: '0 1px 2px rgba(0,0,0,0.04)',
            }}
        >
            {esInactivo && (
                <div
                    className="flex items-start gap-2 mb-2 p-2 rounded-lg"
                    style={{ backgroundColor: 'rgba(239,68,68,0.10)' }}
                >
                    <AlertTriangle size={14} style={{ color: 'var(--color-danger)' }} className="flex-shrink-0 mt-0.5" />
                    <div className="text-[11px] leading-tight" style={{ color: 'var(--color-danger)' }}>
                        <p className="font-semibold">No se puede vender este ítem.</p>
                        <p className="opacity-90">
                            {item.motivo_inactivo ?? 'Producto o presentación desactivada desde que se agendó la cita.'}
                            {' '}Elimínalo del carrito o pide al admin reactivarlo.
                        </p>
                    </div>
                </div>
            )}

            {/* Fila 1: Nombre + Subtotal */}
            <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                    <p
                        className="text-sm font-semibold truncate"
                        style={{ color: esInactivo ? 'var(--color-danger)' : 'var(--color-text)' }}
                    >
                        {item.producto_nombre}
                        {esInactivo && <span className="ml-1 text-[10px] font-normal opacity-80">(inactivo)</span>}
                    </p>
                    <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
                        <span className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                            {item.unidad_nombre}
                        </span>
                        <span className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>·</span>
                        {/* Precio editable: la cajera puede subirlo o bajarlo, pero nunca
                            por debajo del costo. La validación es EN VIVO: ni bien el
                            valor tecleado queda bajo el costo, el input se pinta rojo,
                            sale el aviso inline y el toast (backend revalida al cobrar). */}
                        <span
                            className="inline-flex items-center rounded-lg overflow-hidden border"
                            style={{
                                borderColor: precioBajoCosto
                                    ? 'var(--color-danger)'
                                    : item.precio_unitario !== item.precio_original
                                        ? 'var(--color-warning)'
                                        : 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                            }}
                        >
                            <span
                                className="px-1.5 text-[11px] font-semibold self-stretch flex items-center"
                                style={{
                                    color: precioBajoCosto ? '#fff' : 'var(--color-text-muted)',
                                    backgroundColor: precioBajoCosto
                                        ? 'var(--color-danger)'
                                        : 'color-mix(in srgb, var(--color-border) 35%, transparent)',
                                }}
                            >
                                S/
                            </span>
                            <input
                                type="number"
                                inputMode="decimal"
                                // min=0 (NO el costo): si pusiéramos min=costo, las
                                // flechitas del navegador frenarían en el costo SIN
                                // disparar onChange, y no saldría el aviso. Dejamos que
                                // JS controle el piso (aviso + auto-ajuste al costo).
                                min="0"
                                step="0.01"
                                value={precioVal}
                                onChange={e => onCambioPrecio(e.target.value)}
                                onBlur={() => { setPrecioFocus(false); aplicarPrecio(); }}
                                onKeyDown={e => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                                onFocus={e => { setPrecioFocus(true); e.target.select(); }}
                                aria-label="Precio de venta"
                                className="w-24 px-2 py-1 text-xs font-bold text-right focus:outline-none border-0"
                                style={{
                                    backgroundColor: 'transparent',
                                    color: precioBajoCosto ? 'var(--color-danger)' : 'var(--color-text)',
                                } as React.CSSProperties}
                            />
                        </span>
                        {/* Escalera de precios (historial de tachados): precio de
                            LISTA → precio de VENTA editado → precio con DESCUENTO.
                            Cada peldaño anterior queda tachado; el vigente resalta.
                            Ej.: ~~S/20~~ (lista) → ~~S/17~~ (venta) → S/15 (con dcto). */}
                        {item.precio_unitario !== item.precio_original && !precioBajoCosto && (
                            <span
                                className="text-[10px] line-through opacity-60"
                                title="Precio de lista (catálogo)"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                S/ {item.precio_original.toFixed(2)}
                            </span>
                        )}
                        {hayDescuento && !precioBajoCosto && (
                            <>
                                {/* El precio de venta también se tacha al aplicar el descuento */}
                                <span
                                    className="text-[10px] line-through"
                                    title="Descontado por descuento"
                                    style={{ color: 'var(--color-warning)', opacity: 0.75 }}
                                >
                                    S/ {item.precio_unitario.toFixed(2)}
                                </span>
                                <span className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>›</span>
                                {/* Precio vigente ya con el descuento aplicado */}
                                <span className="text-[11px] font-extrabold" style={{ color: 'var(--color-primary)' }}>
                                    S/ {precioEfectivo.toFixed(2)}
                                </span>
                                <span
                                    className="px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide inline-flex items-center gap-0.5"
                                    title="Descontado por descuento"
                                    style={{
                                        backgroundColor: 'color-mix(in srgb, var(--color-danger) 12%, transparent)',
                                        color: 'var(--color-danger)',
                                    }}
                                >
                                    <Percent size={8} />
                                    -{item.descuento_item.toFixed(2)}{item.descuento_tipo === 'porcentaje' ? ` (${item.descuento_valor}%)` : ''}
                                </span>
                            </>
                        )}
                    </div>
                    {/* Aviso EN VIVO: el precio tecleado está por debajo del costo. */}
                    {precioBajoCosto && (
                        <p className="flex items-center gap-1 text-[10px] font-semibold mt-1" style={{ color: 'var(--color-danger)' }}>
                            <AlertTriangle size={11} className="flex-shrink-0" />
                            No puede ser menor al costo: S/ {item.costo_minimo.toFixed(2)}
                        </p>
                    )}
                </div>
                <span className="text-sm font-bold whitespace-nowrap" style={{ color: 'var(--color-primary)' }}>
                    S/ {item.subtotal.toFixed(2)}
                </span>
            </div>

            {/* Fila 2: Cantidad + Acciones */}
            <div className="flex items-center justify-between mt-2 gap-2">
                <div
                    className="flex items-center rounded-xl overflow-hidden select-none transition-all"
                    style={{
                        border: `1px solid ${cantFocus ? 'var(--color-primary)' : 'var(--color-border)'}`,
                        // El ring va aquí (en el contenedor): el box-shadow se dibuja
                        // por fuera y NO lo recorta el overflow-hidden, así se ve
                        // el resaltado completo (arriba, abajo y a los lados).
                        boxShadow: cantFocus
                            ? '0 0 0 3px color-mix(in srgb, var(--color-primary) 22%, transparent)'
                            : 'none',
                    }}
                >
                    <button
                        onClick={() => onCantidad(item.key, -1)}
                        aria-label="Disminuir cantidad"
                        className="flex items-center justify-center w-9 h-10 flex-shrink-0 transition-colors hover:bg-black/5 active:bg-black/10"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Minus size={15} />
                    </button>
                    {/* Cantidad editable: se puede teclear directo (soporta decimales
                        para productos por metro/kilo), ademas de los botones +/-.
                        Ancho amplio para que un número grande no se pierda. */}
                    <input
                        type="number"
                        inputMode="decimal"
                        min="0"
                        step="any"
                        value={cantidadVal}
                        onChange={e => onCambioCantidad(e.target.value)}
                        onBlur={() => { setCantFocus(false); aplicarCantidad(); }}
                        onKeyDown={e => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                        onFocus={e => { setCantFocus(true); e.target.select(); }}
                        aria-label="Cantidad"
                        className="text-base font-bold w-20 text-center px-1 h-10 border-0 focus:outline-none"
                        style={{
                            color: cantFocus ? 'var(--color-primary)' : 'var(--color-text)',
                            backgroundColor: cantFocus
                                ? 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))'
                                : 'var(--color-bg)',
                            borderLeft: '1px solid var(--color-border)',
                            borderRight: '1px solid var(--color-border)',
                        } as React.CSSProperties}
                    />
                    <button
                        onClick={() => onCantidad(item.key, 1)}
                        aria-label="Aumentar cantidad"
                        className="flex items-center justify-center w-9 h-10 flex-shrink-0 transition-colors hover:bg-black/5 active:bg-black/10"
                        style={{ color: 'var(--color-primary)' }}
                    >
                        <Plus size={15} />
                    </button>
                </div>

                {/* Stock restante EN VIVO: baja conforme sube la cantidad. Rojo si
                    la cantidad excede el stock disponible (venta en negativo). */}
                {stockRestante != null && (
                    <span
                        className="text-[10px] font-bold px-1.5 py-1 rounded-md whitespace-nowrap flex-shrink-0"
                        title="Stock que quedaría tras esta línea"
                        style={{
                            color: sinStock ? 'var(--color-danger)' : 'var(--color-text-muted)',
                            backgroundColor: sinStock
                                ? 'rgba(239,68,68,0.10)'
                                : 'color-mix(in srgb, var(--color-border) 30%, transparent)',
                        }}
                    >
                        Stock: {stockRestante}
                    </span>
                )}

                <div className="flex items-center gap-1">
                    {/* Historial de precios de venta a ESTE cliente (solo si existe).
                        Icono resaltado + popover al hacer clic; permite aplicar el
                        último precio con un toque. */}
                    {historial && historial.historial.length > 0 && (
                        <div className="relative">
                            <button
                                onClick={() => setShowHistorial(v => !v)}
                                aria-label="Ver precios anteriores a este cliente"
                                title="Precios anteriores a este cliente"
                                className="flex items-center justify-center w-9 h-9 rounded-lg transition-colors hover:bg-black/5 active:bg-black/10"
                                style={{ color: showHistorial ? 'var(--color-primary)' : 'var(--color-warning)' }}
                            >
                                <History size={15} />
                            </button>
                            {showHistorial && (
                                <>
                                    {/* Capa para cerrar al tocar fuera */}
                                    <div className="fixed inset-0 z-20" onClick={() => setShowHistorial(false)} />
                                    <div className="absolute bottom-full right-0 mb-1.5 z-30 w-60 rounded-xl p-3"
                                        style={{
                                            backgroundColor: 'var(--color-surface)',
                                            border: '1px solid var(--color-border)',
                                            boxShadow: '0 12px 32px -8px rgba(15,23,42,0.35)',
                                        }}
                                    >
                                        <p className="text-[11px] font-bold mb-1.5" style={{ color: 'var(--color-text)' }}>
                                            Le vendiste antes ({historial.veces} {historial.veces === 1 ? 'vez' : 'veces'})
                                        </p>
                                        <div className="space-y-1">
                                            {historial.historial.map((h, i) => (
                                                <div key={i} className="flex items-center justify-between text-[11px]">
                                                    <span style={{ color: 'var(--color-text-muted)' }}>
                                                        {new Date(h.fecha).toLocaleDateString('es-PE')}
                                                        <span className="opacity-70"> · {Number(h.cantidad)} {h.unidad}</span>
                                                    </span>
                                                    <span className="font-mono font-semibold" style={{ color: 'var(--color-text)' }}>
                                                        S/ {h.precio.toFixed(2)}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                        <button
                                            onClick={() => { onPrecio(item.key, historial.ultimo_precio); setShowHistorial(false); }}
                                            className="mt-2 w-full text-[11px] font-semibold py-1.5 rounded-lg transition-colors"
                                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)', color: 'var(--color-primary)' }}
                                        >
                                            Usar último precio (S/ {historial.ultimo_precio.toFixed(2)})
                                        </button>
                                    </div>
                                </>
                            )}
                        </div>
                    )}
                    {/* Botón informativo: consulta el precio de costo de la línea.
                        Muestra un tooltip al pasar el mouse o al tocar (móvil). */}
                    <div className="relative group">
                        <button
                            onClick={() => setShowCosto(v => !v)}
                            aria-label="Consultar precio de costo"
                            className="flex items-center justify-center w-9 h-9 rounded-lg transition-colors hover:bg-black/5 active:bg-black/10"
                            style={{ color: showCosto ? 'var(--color-primary)' : 'var(--color-text-muted)' }}
                        >
                            <Info size={15} />
                        </button>
                        <div
                            className={`absolute bottom-full right-0 mb-1.5 z-20 pointer-events-none transition-opacity duration-150
                                ${showCosto ? 'opacity-100 visible' : 'opacity-0 invisible'} group-hover:opacity-100 group-hover:visible`}
                        >
                            <div
                                className="rounded-lg px-3 py-2 whitespace-nowrap"
                                style={{
                                    backgroundColor: 'var(--color-text)',
                                    color: 'var(--color-bg)',
                                    boxShadow: '0 8px 24px -6px rgba(15,23,42,0.4)',
                                }}
                            >
                                {item.costo_minimo > 0 ? (
                                    <>
                                        <p className="text-[11px] font-bold leading-tight">
                                            Costo: S/ {item.costo_minimo.toFixed(2)}
                                        </p>
                                        <p className="text-[10px] leading-tight mt-0.5 opacity-80">
                                            Margen: S/ {(item.precio_unitario - item.costo_minimo).toFixed(2)}
                                            {' '}({Math.round(((item.precio_unitario - item.costo_minimo) / item.costo_minimo) * 100)}%)
                                        </p>
                                    </>
                                ) : (
                                    <p className="text-[11px] font-semibold leading-tight">Sin costo registrado</p>
                                )}
                                {/* Stock: disponible al abrir el POS y lo que quedaría con esta línea */}
                                {item.stock_disponible != null && (
                                    <p className="text-[10px] leading-tight mt-1 pt-1"
                                        style={{ borderTop: '1px solid rgba(255,255,255,0.15)' }}>
                                        Stock: {item.stock_disponible}
                                        {' · '}
                                        <span style={{ color: sinStock ? '#fca5a5' : undefined }}>
                                            Quedaría: {stockRestante}
                                        </span>
                                    </p>
                                )}
                                {/* Lo que viene en camino nunca se suma al stock: se
                                    muestra aparte para que la cajera sepa qué prometer. */}
                                {!!item.stock_en_transito && item.stock_en_transito > 0 && (
                                    <p className="text-[10px] leading-tight" style={{ color: '#93c5fd' }}>
                                        En camino: {item.stock_en_transito}
                                        {item.transito_fecha ? ` · llega ${item.transito_fecha}` : ''}
                                    </p>
                                )}
                                {/* Flechita del tooltip */}
                                <span
                                    className="absolute top-full right-3 -mt-px w-0 h-0"
                                    style={{
                                        borderLeft: '5px solid transparent',
                                        borderRight: '5px solid transparent',
                                        borderTop: '5px solid var(--color-text)',
                                    }}
                                />
                            </div>
                        </div>
                    </div>
                    {!showDescuento && (
                        <button
                            onClick={() => setShowDescuento(true)}
                            className="flex items-center gap-1 text-xs px-2.5 py-2 rounded-lg transition-colors hover:bg-black/5 active:bg-black/10"
                            style={{ color: 'var(--color-text-muted)' }}
                        >
                            <Percent size={12} />
                            Dcto
                        </button>
                    )}
                    <button
                        onClick={() => onEliminar(item.key)}
                        aria-label="Eliminar del carrito"
                        className="flex items-center justify-center w-9 h-9 rounded-lg transition-colors hover:bg-red-50 active:bg-red-100 group"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Trash2 size={15} className="group-hover:text-red-500 transition-colors" />
                    </button>
                </div>
            </div>

            {/* Fila 3: Descuento por línea (expandible) */}
            {showDescuento && (
                <div
                    className="mt-2 pt-2 space-y-2"
                    style={{ borderTop: '1px dashed var(--color-border)' }}
                >
                    {/* Encabezado + cerrar */}
                    <div className="flex items-center justify-between">
                        <span className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide" style={{ color: 'var(--color-warning)' }}>
                            <Percent size={12} />
                            Descuento
                        </span>
                        <button
                            onClick={quitarDescuento}
                            className="p-1 rounded hover:bg-black/5 transition-colors flex-shrink-0"
                            title="Quitar descuento"
                            style={{ color: 'var(--color-text-muted)' }}
                        >
                            <X size={13} />
                        </button>
                    </div>

                    {/* Selectores: afecta a (P.U / Total) + tipo (S/ / %) */}
                    <div className="flex flex-wrap items-center gap-2">
                        {/* Afecta a */}
                        <div className="inline-flex rounded-lg overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {(['pu', 'total'] as DescModo[]).map(m => (
                                <button
                                    key={m}
                                    onClick={() => cambiarModo(m)}
                                    className="text-[11px] font-semibold px-2.5 py-1 transition-colors"
                                    title={m === 'pu' ? 'El descuento afecta al precio unitario' : 'El descuento afecta al total de la línea'}
                                    style={{
                                        backgroundColor: descModo === m ? 'var(--color-warning)' : 'transparent',
                                        color: descModo === m ? '#fff' : 'var(--color-text-muted)',
                                    }}
                                >
                                    {m === 'pu' ? 'P. Unit.' : 'Total'}
                                </button>
                            ))}
                        </div>
                        {/* Tipo: soles o porcentaje */}
                        <div className="inline-flex rounded-lg overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {(['monto', 'porcentaje'] as DescTipo[]).map(t => (
                                <button
                                    key={t}
                                    onClick={() => cambiarTipo(t)}
                                    className="text-[11px] font-bold px-2.5 py-1 transition-colors"
                                    title={t === 'monto' ? 'Descuento en soles' : 'Descuento en porcentaje'}
                                    style={{
                                        backgroundColor: descTipo === t ? 'var(--color-warning)' : 'transparent',
                                        color: descTipo === t ? '#fff' : 'var(--color-text-muted)',
                                    }}
                                >
                                    {t === 'monto' ? 'S/' : '%'}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Valor + concepto */}
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-24 flex-shrink-0">
                            <span className="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>
                                {descTipo === 'monto' ? 'S/' : ''}
                            </span>
                            <input
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step={descTipo === 'porcentaje' ? '0.1' : '0.01'}
                                max={descTipo === 'porcentaje' ? '100' : undefined}
                                value={descuentoVal}
                                onChange={e => onCambioDescuento(e.target.value)}
                                onFocus={e => { setDescFocus(true); e.target.select(); }}
                                onBlur={() => { setDescFocus(false); aplicarDescuento(); }}
                                onKeyDown={e => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                                placeholder={descTipo === 'porcentaje' ? '0' : '0.00'}
                                className={`w-full ${descTipo === 'monto' ? 'pl-6' : 'pl-2'} pr-5 py-1.5 text-xs border rounded-lg focus:outline-none focus:ring-2 text-right`}
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                    '--tw-ring-color': 'color-mix(in srgb, var(--color-warning) 40%, transparent)',
                                } as React.CSSProperties}
                            />
                            {descTipo === 'porcentaje' && (
                                <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>%</span>
                            )}
                        </div>
                        <select
                            value={conceptoId ?? ''}
                            onChange={e => cambiarConcepto(e.target.value ? Number(e.target.value) : null)}
                            className="flex-1 min-w-[100px] text-xs border rounded-lg px-1.5 py-1.5 focus:outline-none focus:ring-1"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-bg)',
                                color: 'var(--color-text)',
                                '--tw-ring-color': 'var(--color-warning)',
                            } as React.CSSProperties}
                        >
                            <option value="">Concepto...</option>
                            {conceptos.map(c => (
                                <option key={c.id} value={c.id}>{c.nombre}</option>
                            ))}
                        </select>
                    </div>

                    {/* Resultado en vivo del descuento aplicado */}
                    {hayDescuento && (
                        <p className="text-[10px] leading-tight" style={{ color: 'var(--color-text-muted)' }}>
                            {descModo === 'total' ? (
                                <>Descuento total línea: <span className="font-bold" style={{ color: 'var(--color-danger)' }}>−S/ {(item.descuento_item * item.cantidad).toFixed(2)}</span>{' · '}c/u queda en <span className="font-bold" style={{ color: 'var(--color-primary)' }}>S/ {precioEfectivo.toFixed(2)}</span></>
                            ) : (
                                <>Precio con dcto: <span className="font-bold" style={{ color: 'var(--color-primary)' }}>S/ {precioEfectivo.toFixed(2)}</span>{' '}c/u{' · '}<span className="font-bold" style={{ color: 'var(--color-danger)' }}>−S/ {(item.descuento_item * item.cantidad).toFixed(2)}</span> total</>
                            )}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
