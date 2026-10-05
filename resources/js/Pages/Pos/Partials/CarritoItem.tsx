import React, { useEffect, useRef, useState } from 'react';
import toast from 'react-hot-toast';
import { Trash2, Minus, Plus, Percent, X, AlertTriangle, History } from 'lucide-react';
import type { DescuentoConcepto } from '@/types';
import Select from '@/Components/UI/Select';

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
    /** Si es true y el precio base es 0, se enfoca el input de precio al montar. */
    autoFocusPrecio?:   boolean;
    /** Se llama después de hacer el autofoco, para que el padre limpie el flag. */
    onAutoFocusPrecio?: () => void;
    onCantidad:         (key: string, delta: number) => void;
    onCantidadExacta:   (key: string, cantidad: number) => void;
    onPrecio:           (key: string, precio: number) => void;
    onDescuento:        (key: string, valor: number, modo: DescModo, tipo: DescTipo, conceptoId: number | null) => void;
    onEliminar:         (key: string) => void;
    /** Cambia cada vez que se agrega (o suma) este producto: la fila se ilumina y se hace visible. */
    pulso?:             number;
}

export default function CarritoItem({ item, conceptos, historial, autoFocusPrecio, onAutoFocusPrecio, onCantidad, onCantidadExacta, onPrecio, onDescuento, onEliminar, pulso }: Props) {
    // Recién agregado: la fila se ilumina un instante y queda a la vista. Así la
    // cajera ve QUÉ entró sin un aviso flotante que tape la barra superior.
    const filaRef = useRef<HTMLLIElement | null>(null);
    useEffect(() => {
        const el = filaRef.current;
        if (!pulso || !el) return;
        el.classList.remove('vp-linea-nueva');
        void el.offsetWidth; // reinicia la animación si se agrega dos veces seguidas
        el.classList.add('vp-linea-nueva');
        el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }, [pulso]);

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

    // Tope del descuento: nunca más que lo que vale la línea. Antes se podía
    // teclear S/ 50 en un producto de S/ 20: el cálculo lo recortaba en
    // silencio pero la casilla seguía diciendo 50 y confundía.
    const [avisoTope, setAvisoTope] = useState<string | null>(null);
    function maxDescuento(modo: DescModo, tipo: DescTipo): number {
        if (tipo === 'porcentaje') return 100;
        const base = modo === 'total' ? item.precio_unitario * item.cantidad : item.precio_unitario;
        return Math.round(base * 100) / 100;
    }
    /** Devuelve el valor dentro del tope y muestra/limpia el aviso. */
    function topar(valor: number, modo: DescModo, tipo: DescTipo): number {
        const max = maxDescuento(modo, tipo);
        if (valor > max) {
            setAvisoTope(tipo === 'porcentaje'
                ? 'El descuento no puede superar el 100 %.'
                : `El descuento no puede superar S/ ${max.toFixed(2)}${modo === 'total' ? ' (total de la línea)' : ' (precio unitario)'}.`);
            return max;
        }
        setAvisoTope(null);
        return valor;
    }

    function onCambioDescuento(valor: string) {
        const n = parseFloat(valor) || 0;
        const topado = topar(n, descModo, descTipo);
        setDescuentoVal(topado !== n ? String(topado) : valor);
        emitir(topado, descModo, descTipo, conceptoId);
    }
    function cambiarModo(modo: DescModo) {
        setDescModo(modo);
        const v = topar(parseFloat(descuentoVal) || 0, modo, descTipo);
        if (v !== (parseFloat(descuentoVal) || 0)) setDescuentoVal(String(v));
        emitir(v, modo, descTipo, conceptoId);
    }
    function cambiarTipo(tipo: DescTipo) {
        setDescTipo(tipo);
        const v = topar(parseFloat(descuentoVal) || 0, descModo, tipo);
        if (v !== (parseFloat(descuentoVal) || 0)) setDescuentoVal(String(v));
        emitir(v, descModo, tipo, conceptoId);
    }
    // Atajo de un toque: ese % del precio de este producto (100 % = gratis).
    function atajo(pct: number) {
        setDescTipo('porcentaje');
        setDescuentoVal(String(pct));
        setAvisoTope(null);
        emitir(pct, descModo, 'porcentaje', conceptoId);
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
        setAvisoTope(null);
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
    const precioInputRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => () => {
        if (clampTimer.current) window.clearTimeout(clampTimer.current);
    }, []);

    // Autofoco del precio cuando se agrega una línea con precio base 0.
    // Se ejecuta una sola vez al montar y notifica al padre para que limpie el flag.
    useEffect(() => {
        if (autoFocusPrecio && item.precio_unitario === 0 && precioInputRef.current) {
            precioInputRef.current.focus();
            precioInputRef.current.select();
            onAutoFocusPrecio?.();
        }
    }, []);

    /** `avisar`: el sistema lo corrigió solo (no lo pidió la cajera) → se le cuenta. */
    function ajustarAlCosto(avisar = true) {
        const costo = Math.round(item.costo_minimo * 100) / 100;
        setPrecioVal(costo.toFixed(2));
        onPrecio(item.key, costo);
        if (avisar) {
            toast(
                `El precio de "${item.producto_nombre}" se subió a S/ ${costo.toFixed(2)}: no se puede vender bajo el costo.`,
                { id: `precio-bajo-costo-${item.key}`, duration: 4000 },
            );
        }
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
            // El aviso en rojo junto al campo ya lo dice mientras escribe.
            // No dejar el precio bajo el costo: se corrige solo tras la pausa.
            clampTimer.current = window.setTimeout(() => ajustarAlCosto(), 900);
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

    // Precio cambiado a mano respecto del catálogo (se muestra el de lista tachado).
    const precioEditado = Math.abs(item.precio_unitario - item.precio_original) > 0.0001;
    const conCosto      = item.costo_minimo > 0;

    return (
        <li
            ref={filaRef}
            className="px-3 py-2"
            style={{
                backgroundColor: esInactivo ? 'color-mix(in srgb, var(--color-danger) 7%, var(--color-surface))' : undefined,
                borderTop: '1px solid var(--color-border)',
            }}
        >
            {esInactivo && (
                <p className="flex items-start gap-1.5 mb-1.5 text-[12px] leading-snug" style={{ color: 'var(--vp-coral-ink)' }}>
                    <AlertTriangle size={14} className="flex-shrink-0 mt-px" />
                    <span>
                        <strong>No se puede vender.</strong>{' '}
                        {item.motivo_inactivo ?? 'El producto o su presentación se desactivó desde que se agendó la cita.'}{' '}
                        Quítalo o pide al administrador reactivarlo.
                    </span>
                </p>
            )}

            {/* Fila 1: qué es y cuánto suma */}
            <div className="flex items-baseline justify-between gap-3">
                <p className="min-w-0 truncate text-[13px] font-semibold leading-tight"
                    style={{ color: esInactivo ? 'var(--vp-coral-ink)' : 'var(--color-text)' }}
                    title={item.producto_nombre}>
                    {item.producto_nombre}
                    {item.unidad_nombre && (
                        <span className="font-normal" style={{ color: 'var(--color-text-muted)' }}> · {item.unidad_nombre}</span>
                    )}
                </p>
                <span className="font-display text-[15px] font-bold tabular-nums whitespace-nowrap" style={{ color: 'var(--vp-navy)' }}>
                    S/ {item.subtotal.toFixed(2)}
                </span>
            </div>

            {/* Fila 2: cantidad × precio, stock y acciones */}
            <div className="flex items-center gap-2 mt-1.5">
                <div
                    className="flex items-center h-8 rounded-lg overflow-hidden select-none flex-shrink-0 transition-shadow"
                    style={{
                        border: `1px solid ${cantFocus ? 'var(--color-primary)' : 'var(--color-border)'}`,
                        // El anillo va en el contenedor: el overflow-hidden lo recortaría en el input.
                        boxShadow: cantFocus ? '0 0 0 3px color-mix(in srgb, var(--color-primary) 22%, transparent)' : 'none',
                        backgroundColor: 'var(--color-surface)',
                    }}
                >
                    <button onClick={() => onCantidad(item.key, -1)} aria-label="Quitar uno"
                        disabled={item.cantidad <= 1}
                        className="flex items-center justify-center w-7 h-full transition-colors hover:bg-black/5 active:bg-black/10 disabled:opacity-30"
                        style={{ color: 'var(--color-text-muted)' }}>
                        <Minus size={14} />
                    </button>
                    {/* Cantidad editable: admite decimales (metros, kilos). */}
                    <input
                        type="number" inputMode="decimal" min="0" step="any"
                        value={cantidadVal}
                        onChange={e => onCambioCantidad(e.target.value)}
                        onBlur={() => { setCantFocus(false); aplicarCantidad(); }}
                        onKeyDown={e => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                        onFocus={e => { setCantFocus(true); e.target.select(); }}
                        aria-label={`Cantidad de ${item.producto_nombre}`}
                        className="w-12 h-full text-center text-[14px] font-bold tabular-nums border-0 px-0.5 focus:outline-none"
                        style={{
                            color: 'var(--color-text)',
                            backgroundColor: cantFocus ? 'color-mix(in srgb, var(--color-primary) 8%, var(--color-surface))' : 'transparent',
                            borderLeft: '1px solid var(--color-border)',
                            borderRight: '1px solid var(--color-border)',
                        }}
                    />
                    <button onClick={() => onCantidad(item.key, 1)} aria-label="Agregar uno"
                        className="flex items-center justify-center w-7 h-full transition-colors hover:bg-black/5 active:bg-black/10"
                        style={{ color: 'var(--color-primary)' }}>
                        <Plus size={14} />
                    </button>
                </div>

                <span className="text-[12px] flex-shrink-0" style={{ color: 'var(--color-text-muted)' }} aria-hidden>×</span>

                {/* Precio editable: nunca por debajo del costo (aviso en vivo + ajuste solo). */}
                <label
                    className="flex items-center h-8 rounded-lg flex-shrink-0 transition-shadow"
                    style={{
                        border: `1px solid ${precioBajoCosto ? 'var(--color-danger)' : precioEditado ? 'var(--vp-amber)' : 'var(--color-border)'}`,
                        backgroundColor: precioBajoCosto ? 'color-mix(in srgb, var(--color-danger) 6%, var(--color-surface))' : 'var(--color-surface)',
                        boxShadow: precioFocus ? '0 0 0 3px color-mix(in srgb, var(--color-primary) 22%, transparent)' : 'none',
                    }}
                    title={precioEditado ? `Precio de lista: S/ ${item.precio_original.toFixed(2)}` : 'Precio por unidad'}
                >
                    <span className="pl-2 text-[12px] font-semibold" style={{ color: precioBajoCosto ? 'var(--vp-coral-ink)' : 'var(--color-text-muted)' }}>S/</span>
                    <input
                        ref={precioInputRef}
                        type="number" inputMode="decimal"
                        // min=0 (no el costo): las flechitas frenarían en el costo sin
                        // disparar onChange y no saldría el aviso. JS controla el piso.
                        min="0" step="0.01"
                        data-precio-key={item.key}
                        value={precioVal}
                        onChange={e => onCambioPrecio(e.target.value)}
                        onBlur={() => { setPrecioFocus(false); aplicarPrecio(); }}
                        onKeyDown={e => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                        onFocus={e => { setPrecioFocus(true); e.target.select(); }}
                        aria-label={`Precio de ${item.producto_nombre}`}
                        className="w-[4.5rem] h-full pl-1 pr-2 text-right text-[13px] font-bold tabular-nums bg-transparent border-0 focus:outline-none"
                        style={{ color: precioBajoCosto ? 'var(--vp-coral-ink)' : 'var(--color-text)' }}
                    />
                </label>

                {/* Stock que quedaría con esta línea. Al tocarlo: costo, margen y lo que viene en camino. */}
                <div className="relative min-w-0">
                    {stockRestante != null || conCosto ? (
                        <button
                            type="button"
                            onClick={() => setShowCosto(v => !v)}
                            onBlur={() => setShowCosto(false)}
                            className="flex items-center gap-1 h-6 px-1.5 rounded-md text-[11px] font-semibold tabular-nums whitespace-nowrap max-w-full"
                            title="Ver costo, margen y stock"
                            style={{
                                color: sinStock ? 'var(--vp-coral-ink)' : 'var(--color-text-muted)',
                                backgroundColor: sinStock
                                    ? 'color-mix(in srgb, var(--color-danger) 12%, transparent)'
                                    : 'color-mix(in srgb, var(--color-border) 45%, transparent)',
                            }}
                        >
                            {sinStock && <AlertTriangle size={11} className="flex-shrink-0" />}
                            <span className="truncate">{stockRestante != null ? `Stock ${stockRestante}` : 'Costo'}</span>
                        </button>
                    ) : null}
                    {showCosto && (
                        <div className="absolute bottom-full left-0 mb-1.5 z-30 rounded-lg px-3 py-2 whitespace-nowrap text-[12px] leading-snug"
                            style={{ backgroundColor: 'var(--vp-midnight)', color: '#fff', boxShadow: '0 10px 24px -8px rgb(15 25 35 / 0.45)' }}>
                            {conCosto ? (
                                <>
                                    <p className="font-semibold">Costo S/ {item.costo_minimo.toFixed(2)}</p>
                                    <p style={{ color: 'rgb(255 255 255 / 0.75)' }}>
                                        Margen S/ {(item.precio_unitario - item.costo_minimo).toFixed(2)}
                                        {' '}({Math.round(((item.precio_unitario - item.costo_minimo) / item.costo_minimo) * 100)} %)
                                    </p>
                                </>
                            ) : (
                                <p className="font-semibold">Sin costo registrado</p>
                            )}
                            {item.stock_disponible != null && (
                                <p className="mt-1 pt-1" style={{ borderTop: '1px solid rgb(255 255 255 / 0.15)' }}>
                                    Hay {item.stock_disponible}, quedarían{' '}
                                    <strong style={{ color: sinStock ? '#fca5a5' : undefined }}>{stockRestante}</strong>
                                    {sinStock && ' (se vende sin stock)'}
                                </p>
                            )}
                            {!!item.stock_en_transito && item.stock_en_transito > 0 && (
                                <p style={{ color: '#93c5fd' }}>
                                    En camino {item.stock_en_transito}{item.transito_fecha ? `, llega ${item.transito_fecha}` : ''}
                                </p>
                            )}
                        </div>
                    )}
                </div>

                <div className="ml-auto flex items-center flex-shrink-0">
                    {/* Precios anteriores a ESTE cliente (solo si existen). */}
                    {historial && historial.historial.length > 0 && (
                        <div className="relative">
                            <button
                                onClick={() => setShowHistorial(v => !v)}
                                aria-label="Precios anteriores a este cliente"
                                title="Precios anteriores a este cliente"
                                className="flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:bg-black/5"
                                style={{ color: showHistorial ? 'var(--color-primary)' : 'var(--vp-amber-ink)' }}
                            >
                                <History size={15} />
                            </button>
                            {showHistorial && (
                                <>
                                    <div className="fixed inset-0 z-20" onClick={() => setShowHistorial(false)} />
                                    <div className="absolute bottom-full right-0 mb-1.5 z-30 w-64 rounded-xl p-3"
                                        style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 12px 32px -8px rgba(15,23,42,0.35)' }}>
                                        <p className="text-[12px] font-bold mb-1.5" style={{ color: 'var(--color-text)' }}>
                                            Le vendiste antes ({historial.veces} {historial.veces === 1 ? 'vez' : 'veces'})
                                        </p>
                                        <div className="space-y-1">
                                            {historial.historial.map((h, i) => (
                                                <div key={i} className="flex items-center justify-between text-[12px]">
                                                    <span style={{ color: 'var(--color-text-muted)' }}>
                                                        {new Date(h.fecha).toLocaleDateString('es-PE')} · {Number(h.cantidad)} {h.unidad}
                                                    </span>
                                                    <span className="font-semibold tabular-nums" style={{ color: 'var(--color-text)' }}>S/ {h.precio.toFixed(2)}</span>
                                                </div>
                                            ))}
                                        </div>
                                        <button
                                            onClick={() => { onPrecio(item.key, historial.ultimo_precio); setShowHistorial(false); }}
                                            className="mt-2 w-full text-[12px] font-semibold py-1.5 rounded-lg"
                                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)', color: 'var(--color-primary)' }}>
                                            Usar último precio (S/ {historial.ultimo_precio.toFixed(2)})
                                        </button>
                                    </div>
                                </>
                            )}
                        </div>
                    )}
                    <button
                        onClick={() => showDescuento ? quitarDescuento() : setShowDescuento(true)}
                        aria-pressed={showDescuento}
                        aria-label={showDescuento ? 'Quitar descuento' : 'Aplicar descuento a este producto'}
                        title={showDescuento ? 'Quitar descuento' : 'Descuento a este producto'}
                        className="flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:bg-black/5"
                        style={{
                            color: hayDescuento || showDescuento ? 'var(--vp-amber-ink)' : 'var(--color-text-muted)',
                            backgroundColor: hayDescuento ? 'color-mix(in srgb, var(--vp-amber) 16%, transparent)' : undefined,
                        }}
                    >
                        <Percent size={15} />
                    </button>
                    <button
                        onClick={() => onEliminar(item.key)}
                        aria-label={`Quitar ${item.producto_nombre} del carrito`}
                        title="Quitar del carrito"
                        className="group flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:bg-red-50"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <Trash2 size={15} className="transition-colors group-hover:text-red-500" />
                    </button>
                </div>
            </div>

            {/* Fila 3 (solo si aplica): de dónde sale el precio que se cobra */}
            {(precioEditado || hayDescuento) && !precioBajoCosto && (
                <p className="flex items-center gap-1.5 mt-1 text-[11px] tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                    {precioEditado && <span className="line-through" title="Precio de lista">S/ {item.precio_original.toFixed(2)}</span>}
                    {precioEditado && hayDescuento && <span aria-hidden>›</span>}
                    {hayDescuento && (
                        <>
                            <span className="line-through" title="Precio antes del descuento">S/ {item.precio_unitario.toFixed(2)}</span>
                            <span aria-hidden>›</span>
                            <strong style={{ color: 'var(--color-primary)' }}>S/ {precioEfectivo.toFixed(2)} c/u</strong>
                            <span className="font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>
                                −S/ {(item.descuento_item * item.cantidad).toFixed(2)}{item.descuento_tipo === 'porcentaje' ? ` (${item.descuento_valor} %)` : ''}
                            </span>
                        </>
                    )}
                    {precioEditado && !hayDescuento && <span>precio cambiado</span>}
                </p>
            )}

            {/* Aviso EN VIVO: el precio está por debajo del costo. Dice qué hacer. */}
            {precioBajoCosto && (
                <p role="alert" className="flex items-center gap-1.5 mt-1 text-[12px] font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>
                    <AlertTriangle size={13} className="flex-shrink-0" />
                    <span>Está bajo el costo (S/ {item.costo_minimo.toFixed(2)}).</span>
                    <button type="button" onClick={() => ajustarAlCosto(false)} className="underline underline-offset-2 hover:opacity-80">
                        Subir al costo
                    </button>
                </p>
            )}

            {/* Descuento de esta línea (se abre con el botón %) */}
            {showDescuento && (
                <div className="mt-2 rounded-lg p-2 space-y-2"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 8%, var(--color-surface))', border: '1px solid color-mix(in srgb, var(--vp-amber) 35%, transparent)' }}>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-[12px] font-bold" style={{ color: 'var(--vp-amber-ink)' }}>Descuento</span>
                        {/* Sobre qué se aplica + en qué se expresa */}
                        <Segmento
                            opciones={[['pu', 'Por unidad', 'El descuento se resta del precio de cada unidad'], ['total', 'A la línea', 'El descuento se resta del total de esta línea']]}
                            valor={descModo} onCambio={v => cambiarModo(v as DescModo)} />
                        <Segmento
                            opciones={[['monto', 'S/', 'Descuento en soles'], ['porcentaje', '%', 'Descuento en porcentaje']]}
                            valor={descTipo} onCambio={v => cambiarTipo(v as DescTipo)} />
                        <button onClick={quitarDescuento} title="Quitar descuento" aria-label="Quitar descuento"
                            className="ml-auto p-1 rounded hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}>
                            <X size={14} />
                        </button>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-24 flex-shrink-0">
                            {descTipo === 'monto' && (
                                <span className="absolute left-2 top-1/2 -translate-y-1/2 text-[12px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                            )}
                            <input
                                type="number" inputMode="decimal" min="0"
                                step={descTipo === 'porcentaje' ? '0.1' : '0.01'}
                                max={maxDescuento(descModo, descTipo)}
                                value={descuentoVal}
                                onChange={e => onCambioDescuento(e.target.value)}
                                onFocus={e => { setDescFocus(true); e.target.select(); }}
                                onBlur={() => { setDescFocus(false); aplicarDescuento(); }}
                                onKeyDown={e => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                                placeholder={descTipo === 'porcentaje' ? '0' : '0.00'}
                                aria-label="Valor del descuento"
                                className={`w-full h-8 ${descTipo === 'monto' ? 'pl-7' : 'pl-2'} pr-6 text-[13px] font-semibold text-right tabular-nums border rounded-lg focus:outline-none focus:ring-2`}
                                style={{
                                    borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)',
                                    '--tw-ring-color': 'color-mix(in srgb, var(--vp-amber) 40%, transparent)',
                                } as React.CSSProperties}
                            />
                            {descTipo === 'porcentaje' && (
                                <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[12px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>%</span>
                            )}
                        </div>
                        {[10, 20, 50, 100].map(p => {
                            const activo = descTipo === 'porcentaje' && parseFloat(descuentoVal) === p;
                            return (
                                <button key={p} type="button" onClick={() => atajo(p)}
                                    className="h-8 px-2 rounded-lg text-[12px] font-bold transition-colors"
                                    style={{
                                        backgroundColor: activo ? 'var(--vp-amber)' : 'var(--color-surface)',
                                        color: activo ? '#3b2a00' : 'var(--vp-amber-ink)',
                                        border: `1px solid ${activo ? 'var(--vp-amber)' : 'color-mix(in srgb, var(--vp-amber) 40%, transparent)'}`,
                                    }}>
                                    {p === 100 ? 'Gratis' : `${p} %`}
                                </button>
                            );
                        })}
                    </div>

                    <Select size="sm" ariaLabel="Motivo del descuento" placeholder="Motivo del descuento…"
                        value={conceptoId != null ? String(conceptoId) : ''}
                        onChange={v => cambiarConcepto(v ? Number(v) : null)}
                        options={conceptos.map(c => ({ value: String(c.id), label: c.nombre }))} />

                    {avisoTope && (
                        <p className="text-[12px] font-semibold" style={{ color: 'var(--vp-coral-ink)' }} role="alert">{avisoTope}</p>
                    )}
                </div>
            )}
        </li>
    );
}

/** Control segmentado chico (dos o tres opciones excluyentes). */
function Segmento({ opciones, valor, onCambio }: {
    opciones: [string, string, string][];
    valor:    string;
    onCambio: (v: string) => void;
}) {
    return (
        <div className="inline-flex h-7 rounded-lg p-0.5" role="radiogroup"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            {opciones.map(([v, label, ayuda]) => {
                const activo = v === valor;
                return (
                    <button key={v} type="button" role="radio" aria-checked={activo} title={ayuda}
                        onClick={() => onCambio(v)}
                        className="px-2 rounded-md text-[12px] font-semibold transition-colors"
                        style={{
                            backgroundColor: activo ? 'var(--vp-amber)' : 'transparent',
                            color: activo ? '#3b2a00' : 'var(--color-text-muted)',
                        }}>
                        {label}
                    </button>
                );
            })}
        </div>
    );
}
