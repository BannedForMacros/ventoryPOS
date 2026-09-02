import { useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import {
    Search, ShoppingCart, User, X, ArrowLeft, ChevronDown,
    Package, Receipt, Layers, AlertTriangle, ShoppingBag, ChevronUp,
    Image as ImageIcon, CreditCard, RefreshCw, Truck, FileCheck2, Wrench,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import PosLayout from '@/Layouts/PosLayout';
import Button from '@/Components/UI/Button';
import CarritoItem, { LineaCarrito, HistorialPrecioCliente, DescModo, DescTipo } from './Partials/CarritoItem';
import PanelPago, { LineaPago, faltanCuentas } from './Partials/PanelPago';
import PanelDescuento from './Partials/PanelDescuento';
import ModalClienteRapido from './Partials/ModalClienteRapido';
import ModalCrearCliente from './Partials/ModalCrearCliente';
import ModalConfirmacionVenta from './Partials/ModalConfirmacionVenta';
import ModalSelectorPresentacion from './Partials/ModalSelectorPresentacion';
import {
    validarComprobante, etiquetaComprobante, metaEstado, avisoModoEmision,
    UMBRAL_BOLETA_IDENTIFICADA, type BloqueoComprobante, type TipoComprobantePos,
} from '@/lib/comprobanteElectronico';
import type {
    Cliente, DescuentoConcepto, MetodoPago, Cuenta, Producto, ProductoUnidad,
    Turno, PageProps, FacturacionPosConfig,
} from '@/types';

interface MetodoPagoConCuentas extends MetodoPago { cuentas?: Cuenta[]; }

interface CitaPrellenadaItem {
    producto_id:        number;
    producto_unidad_id: number;
    producto_nombre:    string;
    unidad_nombre:      string;
    cantidad:           number;
    precio_unitario:    number;
    incluye_igv:        boolean;
    // Flags de frescura: el producto o la unidad pueden haber sido desactivados
    // entre el agendamiento y el cobro. El cajero debe verlo y resolverlo.
    producto_activo:    boolean;
    unidad_activa:      boolean;
    inactivo:           boolean;
}

interface CitaPrellenada {
    id:               number;
    numero:           string;
    sujeto_label:     string | null;
    sujeto:           string | null;
    cliente:          Cliente;
    items:            CitaPrellenadaItem[];
    tiene_inactivos:  boolean;
}

// Cotización prellenada (POS abierto con ?cotizacion_id=): mismo patrón que
// la cita, pero con PRECIOS COTIZADOS congelados (incluye descuento por línea).
interface CotizacionPrellenadaItem extends CitaPrellenadaItem {
    descuento_item: number;
}

interface CotizacionPrellenada {
    id:              number;
    numero:          string;
    referencia:      string | null;
    cliente:         Cliente;
    items:           CotizacionPrellenadaItem[];
    tiene_inactivos: boolean;
}

// Venta precargada para EDICIÓN (POS abierto con ?venta_id=). El submit del POS
// irá a ventas.update en vez de ventas.store.
interface VentaEnEdicionItem {
    producto_id:           number;
    producto_unidad_id:    number;
    producto_nombre:       string;
    unidad_nombre:         string;
    cantidad:              number;
    cantidad_pendiente?:   number;
    precio_unitario:       number;
    descuento_item:        number;
    descuento_concepto_id: number | null;
    incluye_igv:           boolean;
}
interface VentaEnEdicionPago {
    metodo_pago_id:        number;
    cuenta_metodo_pago_id: number | null;
    monto:                 number;
    referencia:            string | null;
}
interface VentaEnEdicion {
    id:                    number;
    numero:                string;
    tipo_comprobante:      TipoComprobante;
    numero_comprobante:    string | null;
    descuento_total:       number;
    descuento_concepto_id: number | null;
    moneda:                'PEN' | 'USD';
    es_admin:              boolean;
    expira_en:             string | null;
    cliente:               Cliente | null;
    // Crédito guardado en la venta — para recargar el toggle al editar.
    es_credito?:           boolean;
    fecha_vencimiento?:    string | null;
    // Estado de pago del crédito: decide si se permite marcar pendiente por entregar.
    monto_pagado?:         number;
    saldo_pendiente?:      number;
    total?:                number;
    // Pendiente por entregar existente (prellenado del panel). Si ya hubo
    // entregas registradas, la edición está bloqueada en el backend.
    entrega_pendiente?:      boolean;
    despacho_almacen?:       boolean;
    fecha_entrega_estimada?: string | null;
    pendiente_bloqueado?:    boolean;
    items:                 VentaEnEdicionItem[];
    pagos:                 VentaEnEdicionPago[];
}

// Modo turno específico (admin): el POS opera sobre un turno abierto ajeno —
// típicamente uno REABIERTO de un día anterior. Las ventas se guardan con la
// FECHA del turno (backdate), no con la de hoy.
interface TurnoBackdate {
    turno_id: number;
    fecha:    string | null;   // fecha de apertura del turno (la fecha de la venta)
    cajera:   string | null;
    caja:     string | null;
    es_hoy:   boolean;
}

interface Props extends PageProps {
    turno:              Turno;
    productos:          Producto[];
    productosHasMore: boolean;
    productosCursor:    string | null;
    clienteGeneral:     Cliente | null;
    categorias:         string[];
    hayServicios:       boolean;
    metodosPago:        MetodoPagoConCuentas[];
    conceptosDescuento: DescuentoConcepto[];
    citaPrellenada?:    CitaPrellenada | null;
    cotizacionPrellenada?: CotizacionPrellenada | null;
    ventaEnEdicion?:    VentaEnEdicion | null;
    turnoBackdate?:     TurnoBackdate | null;
    // A14: el backend valida que el usuario pueda operar el POS al CARGAR la
    // pantalla (admin sin local_id en modo central_y_local, almacén
    // desactivado, etc.). Si puedeVender=false bloqueamos el botón cobrar
    // desde el principio en vez de fallar al final con un 422.
    puedeVender:        boolean;
    razonNoVender:      string | null;
    // Multimoneda: monedas disponibles y TC del día (soles por 1 USD).
    monedas?:           string[];
    tipoCambioHoy?:     number | null;
    // Facturación electrónica (V10). OPCIONAL: si el backend no la envía (o el
    // módulo está apagado) el POS se comporta exactamente como hoy.
    facturacion?:       FacturacionPosConfig | null;
    // Mercadería en tránsito: `usaTransito` solo muestra qué viene en camino;
    // `vendeTransito` además habilita prometerlo como entrega pendiente.
    usaTransito?:       boolean;
    vendeTransito?:     boolean;
}

type TipoComprobante = TipoComprobantePos;

function uid() { return Math.random().toString(36).slice(2); }

/**
 * Costo minimo de una presentacion: el precio de venta editable no puede
 * bajar de este valor. Usa el costo propio de la unidad si esta definido;
 * si no, el costo promedio real del stock del almacen de ventas
 * (unidad base) multiplicado por el factor de conversion; finalmente
 * fallback al costo base del producto. Devuelve 0 cuando no hay costo
 * registrado (sin piso).
 */
function costoMinimoDe(producto: Producto, unidad: ProductoUnidad): number {
    const costoUnidad = parseFloat(unidad.precio_costo ?? '0') || 0;
    if (costoUnidad > 0) return costoUnidad;

    const costoStock = Number(producto.stock_costo_promedio ?? 0) || 0;
    const factor     = parseFloat(unidad.factor_conversion ?? '1') || 1;
    if (costoStock > 0 && factor > 0) {
        return Math.round(costoStock * factor * 100) / 100;
    }

    const costoBase = Number(producto.precio_costo ?? 0) || 0;
    return Math.round(costoBase * factor * 100) / 100;
}

/**
 * Miniatura del producto en el grid del POS. Compacta (44px) para que la card
 * sea baja y entren mas productos en pantalla. Cae al icono placeholder si la
 * URL no carga (link roto, CDN caido). Manejamos el estado de error por card
 * para no penalizar al grid entero por una sola imagen mala.
 */
function ProductoThumbnail({ url, alt }: { url: string | null; alt: string }) {
    const [failed, setFailed] = useState(false);
    const showImg = !!url && !failed;
    return (
        <div
            className="w-11 h-11 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center"
            style={{ backgroundColor: 'var(--color-bg)' }}
        >
            {showImg ? (
                <img
                    src={url!}
                    alt={alt}
                    loading="lazy"
                    className="w-full h-full object-cover"
                    onError={() => setFailed(true)}
                />
            ) : (
                <ImageIcon size={18} style={{ color: 'var(--color-text-muted)', opacity: 0.35 }} />
            )}
        </div>
    );
}

/**
 * Token unico de la venta-en-construccion. Se genera al abrir la confirmacion y
 * acompana cada intento de submit. El backend usa este key para garantizar que
 * un doble click o un reintento por timeout NO genere ventas duplicadas.
 * Usa crypto.randomUUID si esta disponible (browsers modernos), si no, fallback.
 */
function generarIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

/**
 * Descuento efectivo POR UNIDAD (en soles) a partir de cómo lo tecleó el cajero.
 * El backend siempre recibe/persiste `descuento_item` como soles por unidad y
 * calcula el subtotal como (precio_unitario − descuento_item) × cantidad, así
 * que aquí traducimos cualquier modo/tipo a esa magnitud:
 *   - P.U + monto:   descuento = valor (soles que baja cada unidad)
 *   - P.U + %:       descuento = precio × valor/100
 *   - Total + monto: valor es el descuento del TOTAL de la línea → por unidad = valor / cantidad
 *   - Total + %:     descuento = precio × valor/100 (el % del total equivale al % del P.U)
 * Nunca deja el descuento por encima del precio (no se cobra negativo).
 */
function derivarDescuentoItem(
    precio: number, cantidad: number, modo: DescModo, tipo: DescTipo, valor: number,
): number {
    if (!valor || valor <= 0 || precio <= 0 || cantidad <= 0) return 0;
    let d: number;
    if (modo === 'total') {
        const totalBase = precio * cantidad;
        const totalDesc = tipo === 'porcentaje' ? totalBase * (valor / 100) : valor;
        d = totalDesc / cantidad;
    } else {
        d = tipo === 'porcentaje' ? precio * (valor / 100) : valor;
    }
    d = Math.min(d, precio);
    return Math.round(d * 100) / 100;
}

/** Recalcula descuento_item + subtotal de una línea manteniendo su modo/tipo/valor. */
function recalcularLinea(i: LineaCarrito, patch: Partial<LineaCarrito> = {}): LineaCarrito {
    const l = { ...i, ...patch };
    const descItem = derivarDescuentoItem(l.precio_unitario, l.cantidad, l.descuento_modo, l.descuento_tipo, l.descuento_valor);
    return {
        ...l,
        descuento_item: descItem,
        subtotal: Math.round((l.precio_unitario - descItem) * l.cantidad * 100) / 100,
    };
}

/**
 * Espejo en TS del calculo del backend (Venta::calcularTotales).
 * Separa base gravada (afecta IGV) de base exonerada (no afecta IGV) y
 * prorratea el descuento_total entre ambas bases. Debe coincidir centavo
 * a centavo con el backend para que el cajero no vea un total y el backend
 * cobre otro.
 */
function calcularTotales(items: LineaCarrito[], descuentoTotal: number, tasaPorcentaje: number) {
    const tasa = tasaPorcentaje / 100;
    const subtotal = items.reduce((s, i) => s + i.subtotal, 0);

    let baseGravadaRaw = 0;
    let baseExonerada  = 0;
    for (const i of items) {
        if (i.incluye_igv) {
            baseGravadaRaw += tasa > 0 ? i.subtotal / (1 + tasa) : i.subtotal;
        } else {
            baseExonerada += i.subtotal;
        }
    }

    // El descuento global está en SOLES BRUTOS (lo que el cajero quiere que baje
    // el total, IGV incluido). Se prorratea por el BRUTO de cada base; en la parte
    // gravada se convierte a neto dividiéndolo entre (1+tasa) ANTES de restarlo a
    // la base sin IGV, para que al re-sumar el IGV el total baje EXACTAMENTE el
    // descuento ingresado (antes bajaba descuento×1.18 y se descuadraba).
    const brutoGravado = baseGravadaRaw * (1 + tasa);
    const totalBruto   = brutoGravado + baseExonerada;
    let descGravadoNeto = 0;
    let descExon        = 0;
    if (totalBruto > 0 && descuentoTotal > 0) {
        const descGravadoBruto = descuentoTotal * (brutoGravado / totalBruto);
        descExon        = descuentoTotal * (baseExonerada / totalBruto);
        descGravadoNeto = tasa > 0 ? descGravadoBruto / (1 + tasa) : descGravadoBruto;
    }

    const baseGravadaFinal = Math.max(0, baseGravadaRaw - descGravadoNeto);
    const baseExonFinal    = Math.max(0, baseExonerada  - descExon);

    const igv   = Math.round(baseGravadaFinal * tasa * 100) / 100;
    const total = Math.round((baseGravadaFinal + igv + baseExonFinal) * 100) / 100;
    // Se devuelven también las BASES NETAS. Antes solo salían `subtotal` (el bruto)
    // e `igv`, y el desglose las pintaba una debajo de la otra:
    //
    //     Subtotal    100.00      <- bruto, con el IGV YA dentro
    //     IGV (18%)    15.25      <- ese mismo IGV, otra vez
    //
    // que leído en columna parece una suma de 115.25. Con las bases netas el
    // desglose cuadra —84.75 + 15.25 = 100.00— y además dice lo mismo que va a
    // declarar el comprobante, así que la cajera puede cotejarlo con el impreso.
    return { subtotal, igv, total, baseGravada: baseGravadaFinal, baseExonerada: baseExonFinal };
}

export default function PosIndex({ turno, productos, productosHasMore, productosCursor, clienteGeneral, categorias, hayServicios, metodosPago, conceptosDescuento, flash, citaPrellenada, cotizacionPrellenada, ventaEnEdicion, turnoBackdate, puedeVender, razonNoVender, monedas, tipoCambioHoy, facturacion, usaTransito, vendeTransito }: Props) {
    // Configuración de la empresa (configurable por tenant).
    const empresaAuth = usePage().props.auth?.user?.empresa as {
        tasa_igv?: number | string;
        permite_duplicar_items_venta?: boolean;
        usa_despacho_almacen?: boolean;
    } | undefined;
    const tasaIgv = Number(empresaAuth?.tasa_igv ?? 18);
    const permiteDuplicarItems = empresaAuth?.permite_duplicar_items_venta ?? false;

    // Si venimos desde una cita O una cotización, prellenar carrito y cliente
    // automaticamente. Cada linea propaga su flag `inactivo` para que
    // CarritoItem la pinte en rojo y el boton de cobrar quede deshabilitado
    // mientras existan inactivos. La cotización además trae su descuento por
    // línea con el precio COTIZADO (congelado), que se respeta tal cual.
    const itemsPrellenados = citaPrellenada?.items ?? cotizacionPrellenada?.items ?? null;
    const carritoInicial: LineaCarrito[] = itemsPrellenados?.map(it => {
        const descuentoItem = (it as CotizacionPrellenadaItem).descuento_item ?? 0;
        const subtotal = (it.precio_unitario - descuentoItem) * it.cantidad;
        const motivo = !it.producto_activo
            ? `El producto "${it.producto_nombre}" fue desactivado.`
            : !it.unidad_activa
                ? `La presentación "${it.unidad_nombre}" fue desactivada.`
                : undefined;
        // Resolver el costo minimo desde el catalogo cargado (la cita solo trae ids).
        const prodCatalogo = productos.find(p => p.id === it.producto_id);
        const uniCatalogo  = prodCatalogo?.unidades?.find(u => u.id === it.producto_unidad_id);
        return {
            key: `${it.producto_id}-${it.producto_unidad_id}`,
            producto_id:           it.producto_id,
            producto_unidad_id:    it.producto_unidad_id,
            producto_nombre:       it.producto_nombre,
            unidad_nombre:         it.unidad_nombre,
            precio_unitario:       it.precio_unitario,
            precio_original:       it.precio_unitario,
            costo_minimo:          prodCatalogo && uniCatalogo ? costoMinimoDe(prodCatalogo, uniCatalogo) : 0,
            stock_disponible:      prodCatalogo?.stock_disponible ?? null,
            stock_en_transito:     prodCatalogo?.stock_en_transito ?? 0,
            transito_fecha:        prodCatalogo?.transito_fecha ?? null,
            factor_conversion:     uniCatalogo ? (parseFloat(uniCatalogo.factor_conversion) || 1) : 1,
            cantidad:              it.cantidad,
            descuento_item:        descuentoItem,
            // La cotización congela el descuento como soles por unidad → lo
            // reabrimos en modo "P. Unit. / S/" con ese mismo valor.
            descuento_modo:        'pu',
            descuento_tipo:        'monto',
            descuento_valor:       descuentoItem,
            descuento_concepto_id: null,
            subtotal,
            incluye_igv:           it.incluye_igv,
            inactivo:              it.inactivo,
            motivo_inactivo:       motivo,
        };
    }) ?? [];

    // Si el POS se abrió en modo EDICIÓN (?venta_id=), prellenar el carrito con
    // los items de la venta existente (resolviendo costo_minimo del catálogo).
    const carritoEdicion: LineaCarrito[] = ventaEnEdicion?.items.map(it => {
        const prod = productos.find(p => p.id === it.producto_id);
        const uni  = prod?.unidades?.find(u => u.id === it.producto_unidad_id);
        return {
            key: `${it.producto_id}-${it.producto_unidad_id}`,
            producto_id:           it.producto_id,
            producto_unidad_id:    it.producto_unidad_id,
            producto_nombre:       it.producto_nombre,
            unidad_nombre:         it.unidad_nombre,
            precio_unitario:       it.precio_unitario,
            precio_original:       uni ? parseFloat(uni.precio_venta) : it.precio_unitario,
            costo_minimo:          prod && uni ? costoMinimoDe(prod, uni) : 0,
            stock_disponible:      prod?.stock_disponible ?? null,
            stock_en_transito:     prod?.stock_en_transito ?? 0,
            transito_fecha:        prod?.transito_fecha ?? null,
            factor_conversion:     uni ? (parseFloat(uni.factor_conversion) || 1) : 1,
            cantidad:              it.cantidad,
            descuento_item:        it.descuento_item,
            descuento_modo:        'pu',
            descuento_tipo:        'monto',
            descuento_valor:       it.descuento_item,
            descuento_concepto_id: it.descuento_concepto_id,
            subtotal:              Math.round((it.precio_unitario - it.descuento_item) * it.cantidad * 100) / 100,
            incluye_igv:           it.incluye_igv,
        };
    }) ?? [];

    // Pagos precargados en edición (resolviendo admite_vuelto/es_efectivo del método).
    const pagosEdicion: LineaPago[] = ventaEnEdicion?.pagos.map(p => {
        const m = metodosPago.find(mp => mp.id === p.metodo_pago_id);
        return {
            key:                   uid(),
            metodo_pago_id:        p.metodo_pago_id,
            cuenta_metodo_pago_id: p.cuenta_metodo_pago_id,
            monto:                 p.monto,
            referencia:            p.referencia ?? '',
            admite_vuelto:         !!m?.admite_vuelto,
            es_efectivo:           m?.tipo?.slug === 'efectivo',
        };
    }) ?? [];

    const clienteInicial: Cliente | null =
        ventaEnEdicion?.cliente ?? citaPrellenada?.cliente ?? cotizacionPrellenada?.cliente ?? clienteGeneral;

    const [busqueda, setBusqueda]           = useState('');
    const [carrito, setCarrito]             = useState<LineaCarrito[]>(ventaEnEdicion ? carritoEdicion : carritoInicial);
    const [pagos, setPagos]                 = useState<LineaPago[]>(pagosEdicion);
    const [cliente, setCliente]             = useState<Cliente | null>(clienteInicial);
    // Historial de precios de venta de ESTE cliente por producto. Se carga al
    // elegir cliente y sirve para mostrar en cada línea a cuánto se le vendió
    // antes. Funciona en cualquier orden (cliente→productos o productos→cliente).
    const [historialCliente, setHistorialCliente] = useState<Record<number, HistorialPrecioCliente>>({});
    const [descuentoTotal, setDescuentoTotal]       = useState(ventaEnEdicion?.descuento_total ?? 0);
    const [descuentoConceptoId, setDescuentoConceptoId] = useState<number | null>(ventaEnEdicion?.descuento_concepto_id ?? null);
    const [tipoComprobante, setTipoComprobante]     = useState<TipoComprobante>(ventaEnEdicion?.tipo_comprobante ?? 'ticket');
    const [numeroComprobante, setNumeroComprobante] = useState(ventaEnEdicion?.numero_comprobante ?? '');
    // Multimoneda: moneda de la venta. En USD los precios/pagos se ingresan en
    // dólares y el backend los convierte a soles al TC del día (congelado).
    const [moneda, setMoneda]                       = useState<'PEN' | 'USD'>(ventaEnEdicion?.moneda ?? 'PEN');
    // F1 — Venta a crédito: se entrega mercadería sin cobrar el total.
    // Requiere cliente identificado; el saldo queda como cuenta por cobrar.
    // En EDICIÓN se prellena con lo guardado (antes salía siempre desmarcado).
    const [esCredito, setEsCredito]                 = useState(!!ventaEnEdicion?.es_credito);
    const [fechaVencimiento, setFechaVencimiento]   = useState(ventaEnEdicion?.fecha_vencimiento ?? '');
    // Pendiente por entregar: el cliente paga todo pero se lleva solo PARTE.
    // `pendientes` guarda por línea (key del carrito) cuánto QUEDA pendiente;
    // el POS crea automáticamente el anticipo material en finanzas y el stock
    // pendiente sale del almacén recién al registrarse la entrega.
    // En EDICIÓN se prellena con el pendiente actual de la venta (editar lo
    // reemplaza: el anticipo anterior se anula y se recrea con lo nuevo).
    const [entregaPendiente, setEntregaPendiente]   = useState(!!ventaEnEdicion?.entrega_pendiente);
    const [despachoAlmacen, setDespachoAlmacen]       = useState(!!ventaEnEdicion?.despacho_almacen);
    const [fechaEntrega, setFechaEntrega]             = useState(ventaEnEdicion?.fecha_entrega_estimada ?? '');
    const [pendientes, setPendientes]               = useState<Record<string, number>>(() => {
        const m: Record<string, number> = {};
        ventaEnEdicion?.items.forEach(it => {
            if (it.cantidad_pendiente && it.cantidad_pendiente > 0) {
                m[`${it.producto_id}-${it.producto_unidad_id}`] = it.cantidad_pendiente;
            }
        });
        return m;
    });
    const [modalCliente, setModalCliente]   = useState(false);
    // Alta de cliente sin salir del POS (se abre desde el modal de selección).
    const [modalCrearCliente, setModalCrearCliente] = useState(false);
    const [modalConfirm, setModalConfirm]   = useState(false);
    const [loading, setLoading]             = useState(false);
    const [carritoAbierto, setCarritoAbierto] = useState(false);
    const [categoriaActiva, setCategoriaActiva] = useState<string | null>(null);
    // Pestaña Productos / Servicios. null = ver todo. Solo se pinta si la
    // empresa realmente vende servicios, para no estorbar a quien no los usa.
    const [tipoActivo, setTipoActivo] = useState<'producto' | 'servicio' | null>(null);
    // Token de idempotencia: se genera al abrir el modal de confirmacion y se
    // mantiene mientras siga la misma venta-en-construccion. Se renueva al
    // limpiar el carrito tras un OK exitoso.
    const [idempotencyKey, setIdempotencyKey] = useState<string>(() => generarIdempotencyKey());
    // Producto pendiente de elegir presentacion (cuando tiene 2+ unidades)
    const [productoEnSeleccion, setProductoEnSeleccion] = useState<Producto | null>(null);
    // Cuando se agrega una línea con precio base 0, guardamos su key para
    // enfocar automáticamente el input de precio y que la cajera lo cambie al toque.
    const [nuevaLineaPrecioKey, setNuevaLineaPrecioKey] = useState<string | null>(null);
    // Advertencia al duplicar un producto: solo la primera vez por producto/unidad
    // en el carrito actual. Se resetea al limpiar el carrito.
    const advertenciasDuplicados = useRef<Set<string>>(new Set());
    // Tooltip de producto: solo aparece cuando el nombre de la tarjeta quedó
    // cortado (line-clamp). Un unico tooltip con posicion fija (no lo recorta
    // el scroll del grid) que muestra imagen + nombre completo. La cajera solo
    // pasa el mouse; no tiene que hacer clic en nada.
    const [tooltipProd, setTooltipProd] = useState<{ producto: Producto; top: number; bottom: number; left: number } | null>(null);
    // Anticipos de efectivo del cliente seleccionado.
    const [anticiposCliente, setAnticiposCliente] = useState<{ id: number; fecha: string; monto: number; saldo: number; observacion: string | null }[]>([]);
    const [anticipoSeleccionado, setAnticipoSeleccionado] = useState<number | null>(null);
    const [cargandoAnticipos, setCargandoAnticipos] = useState(false);

    // Totales de la venta (disponibles temprano para efectos y validaciones).
    const { subtotal, igv, total, baseGravada, baseExonerada } = calcularTotales(carrito, descuentoTotal, tasaIgv);

    // Anticipo de efectivo aplicado a la venta (si el usuario lo activó).
    const anticipoActivo = anticiposCliente.find(a => a.id === anticipoSeleccionado);
    const saldoAnticipo = anticipoActivo ? anticipoActivo.saldo : 0;
    const montoAnticipoUsado = Math.min(total, saldoAnticipo);
    const totalPagadoConAnticipo = (pagos.reduce((s, p) => s + p.monto, 0)) + montoAnticipoUsado;

    // Refresco del catálogo (solo la lista de productos) sin perder el carrito.
    const [refrescando, setRefrescando] = useState(false);

    // ── Búsqueda server-side de productos (scroll infinito) ───────────────
    // El POS ya no carga TODOS los productos de la empresa; solo los 40 más
    // vendidos y búsquedas paginadas. `listaProductos` es el catalogo actual.
    const [listaProductos, setListaProductos] = useState<Producto[]>(productos);
    const [hasMoreProductos, setHasMoreProductos] = useState(productosHasMore);
    const [cursorProductos, setCursorProductos] = useState<string | null>(productosCursor);
    const [cargandoProductos, setCargandoProductos] = useState(false);
    const [productosQuery, setProductosQuery] = useState('');
    const productosAbortRef = useRef<AbortController | null>(null);
    const productosTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const gridRef = useRef<HTMLDivElement>(null);
    const sentinelRef = useRef<HTMLDivElement>(null);
    const paramsProductosRef = useRef({ q: '', categoria: null as string | null, tipo: null as 'producto' | 'servicio' | null });
    const productosInicialRef = useRef(true);

    paramsProductosRef.current = { q: productosQuery, categoria: categoriaActiva, tipo: tipoActivo };

    function mergeProductosUnicos(base: Producto[], nuevos: Producto[]): Producto[] {
        const vistos = new Set(base.map(p => p.id));
        return [...base, ...nuevos.filter(p => !vistos.has(p.id))];
    }

    async function fetchProductos({
        q = productosQuery,
        categoria = categoriaActiva,
        tipo = tipoActivo,
        cursor = null,
        append = false,
        onFinally,
    }: {
        q?: string;
        categoria?: string | null;
        tipo?: 'producto' | 'servicio' | null;
        cursor?: string | null;
        append?: boolean;
        onFinally?: () => void;
    }) {
        if (cargandoProductos) { onFinally?.(); return; }
        setCargandoProductos(true);
        productosAbortRef.current?.abort();
        const ctrl = new AbortController();
        productosAbortRef.current = ctrl;
        try {
            const params: Record<string, any> = {
                q: q.trim(),
                categoria_id: categoria,
                tipo,
            };
            if (cursor) params.cursor = cursor;
            if (ventaEnEdicion?.id) params.venta_id = ventaEnEdicion.id;
            const { data } = await axios.get<{
                productos: Producto[];
                has_more: boolean;
                cursor: string | null;
            }>(route('pos.productos'), { params, signal: ctrl.signal });
            setHasMoreProductos(data.has_more);
            setCursorProductos(data.cursor ?? null);
            setListaProductos(prev => append ? mergeProductosUnicos(prev, data.productos) : data.productos);
        } catch (e: any) {
            if (!axios.isCancel(e)) {
                toast.error(e?.response?.data?.message || 'Error al cargar productos');
            }
        } finally {
            setCargandoProductos(false);
            onFinally?.();
        }
    }

    // Debounce de la búsqueda y fetch automático cuando cambian filtros.
    useEffect(() => {
        productosTimerRef.current && clearTimeout(productosTimerRef.current);
        productosTimerRef.current = setTimeout(() => setProductosQuery(busqueda), 250);
        return () => { productosTimerRef.current && clearTimeout(productosTimerRef.current); };
    }, [busqueda]);

    useEffect(() => {
        // Saltar la primera ejecución si ya tenemos los productos iniciales.
        if (productosInicialRef.current && productosQuery === '' && !categoriaActiva && !tipoActivo) {
            productosInicialRef.current = false;
            return;
        }
        productosInicialRef.current = false;
        fetchProductos({ q: productosQuery, cursor: null, append: false });
    }, [productosQuery, categoriaActiva, tipoActivo]);

    // Scroll infinito: observar el sentinel dentro del grid de productos.
    useEffect(() => {
        const grid = gridRef.current;
        const sentinel = sentinelRef.current;
        if (!grid || !sentinel) return;
        const obs = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMoreProductos && !cargandoProductos) {
                    const p = paramsProductosRef.current;
                    fetchProductos({ ...p, cursor: cursorProductos, append: true });
                }
            },
            { root: grid, rootMargin: '0px 0px 120px 0px', threshold: 0 },
        );
        obs.observe(sentinel);
        return () => obs.disconnect();
    }, [hasMoreProductos, cargandoProductos, cursorProductos]);

    function mostrarTooltipSiCortado(e: React.MouseEvent<HTMLButtonElement>, producto: Producto) {
        const nombreEl = e.currentTarget.querySelector('[data-nombre]') as HTMLElement | null;
        // scrollHeight > clientHeight => el texto no cupo y se truncó con "..."
        if (!nombreEl || nombreEl.scrollHeight <= nombreEl.clientHeight + 1) return;
        const r = e.currentTarget.getBoundingClientRect();
        setTooltipProd({ producto, top: r.top, bottom: r.bottom, left: r.left + r.width / 2 });
    }

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    // Refrescar catálogo de productos vía búsqueda server-side. Al resetear
    // filtros y consultar, se actualiza la lista sin perder el carrito.
    function refrescarCatalogo() {
        if (refrescando) return;
        setRefrescando(true);
        setBusqueda('');
        setCategoriaActiva(null);
        setTipoActivo(null);
        fetchProductos({ q: '', categoria: null, tipo: null, cursor: null, append: false, onFinally: () => setRefrescando(false) });
    }

    // Cancelar búsquedas pendientes al desmontar el POS.
    useEffect(() => () => { productosAbortRef.current?.abort(); }, []);

    // Aviso inmediato al cajero cuando se abre el POS desde una cita o una
    // cotización con items desactivados. Solo se dispara una vez al montar;
    // despues se ven los flags por linea y el banner persistente.
    useEffect(() => {
        if (citaPrellenada?.tiene_inactivos) {
            const inactivos = citaPrellenada.items.filter(i => i.inactivo);
            toast.error(
                `Esta cita tiene ${inactivos.length} ítem(s) desactivado(s) desde que se agendó. ` +
                'Revisa el carrito antes de cobrar.',
                { duration: 6000 },
            );
        }
        if (cotizacionPrellenada?.tiene_inactivos) {
            const inactivos = cotizacionPrellenada.items.filter(i => i.inactivo);
            toast.error(
                `Esta cotización tiene ${inactivos.length} ítem(s) desactivado(s) desde que se cotizó. ` +
                'Revisa el carrito antes de cobrar.',
                { duration: 6000 },
            );
        }
    }, []);

    // Auto-agregar pago en efectivo por defecto cuando hay items y no hay pagos.
    // Historial de precios del cliente: se recarga al cambiar de cliente. Para el
    // cliente general (sin identificar) no tiene sentido, así que se limpia.
    // También consultamos anticipos de efectivo activos del cliente.
    useEffect(() => {
        const id = cliente?.id;
        const esGeneral = !cliente
            || (cliente as Cliente & { es_cliente_general?: boolean }).es_cliente_general
            || cliente.numero_documento === '99999999';
        if (!id || esGeneral) {
            setHistorialCliente({});
            setAnticiposCliente([]);
            setAnticipoSeleccionado(null);
            return;
        }
        let vivo = true;
        axios.get(route('pos.historial-precios'), { params: { cliente_id: id } })
            .then(r => { if (vivo) setHistorialCliente(r.data ?? {}); })
            .catch(() => { if (vivo) setHistorialCliente({}); });

        setCargandoAnticipos(true);
        axios.get<{ anticipos: { id: number; fecha: string; monto: number; saldo: number; observacion: string | null }[]; total: number }>(route('pos.clientes.anticipos', id))
            .then(r => {
                if (!vivo) return;
                setAnticiposCliente(r.data.anticipos);
                if (r.data.anticipos.length === 0) setAnticipoSeleccionado(null);
            })
            .catch(() => { if (vivo) setAnticiposCliente([]); })
            .finally(() => { if (vivo) setCargandoAnticipos(false); });
        return () => { vivo = false; };
    }, [cliente?.id]);

    // Buscamos el método con tipo.slug === 'efectivo' como conveniencia inicial.
    // El flag `admite_vuelto` se lee del método (BD).
    const efectivo = metodosPago.find(m => m.tipo?.slug === 'efectivo');
    useEffect(() => {
        if (esCredito) return; // en crédito el pago inicial es opcional y manual
        // Si el anticipo cubre el total, no agregar efectivo automático.
        if (anticipoSeleccionado && montoAnticipoUsado >= total - 0.009) return;
        if (carrito.length > 0 && pagos.length === 0 && efectivo) {
            setPagos([{
                key:                   uid(),
                metodo_pago_id:        efectivo.id,
                cuenta_metodo_pago_id: null,
                monto:                 parseFloat(total.toFixed(2)),
                referencia:            '',
                admite_vuelto:         !!efectivo.admite_vuelto,
                es_efectivo:           true,
            }]);
        }
    }, [carrito.length]);

    // Auto-actualizar monto del pago si es el único y admite vuelto (efectivo
    // por defecto). Si no admite vuelto el monto debe ser exacto, lo dejamos.
    // Si hay anticipo seleccionado, ajustar al resto por pagar (no al total).
    useEffect(() => {
        if (esCredito) return; // no forzar el monto al total: puede ser pago parcial
        if (pagos.length === 1 && pagos[0].admite_vuelto && total > 0) {
            const resto = Math.max(0, total - montoAnticipoUsado);
            setPagos(prev => [{ ...prev[0], monto: parseFloat(resto.toFixed(2)) }]);
        }
    }, [total, anticipoSeleccionado, montoAnticipoUsado]);

    // El catálogo visible es ahora el resultado de búsquedas server-side.
    const productosFiltrados = listaProductos;

    /**
     * Enter en el buscador: agrega directo si hay match exacto de codigo
     * (flujo de lector de codigo de barras: escanea → teclea codigo + Enter)
     * o si la busqueda dejo un unico resultado. Limpia el buscador para el
     * siguiente escaneo.
     */
    function onBusquedaKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key !== 'Enter') return;
        const qRaw = busqueda.trim();
        const q = qRaw.toLowerCase();
        if (!q) return;
        // Cancelar el debounce pendiente y buscar inmediatamente en el servidor.
        productosTimerRef.current && clearTimeout(productosTimerRef.current);
        const exactoLocal = productosFiltrados.find(p => (p.codigo ?? '').toLowerCase() === q);
        if (exactoLocal) {
            agregarProducto(exactoLocal);
            setBusqueda('');
            return;
        }
        // Consulta directa al servidor; si hay un único resultado o match exacto,
        // se agrega automáticamente (flujo escáner de código de barras).
        productosAbortRef.current?.abort();
        const ctrl = new AbortController();
        productosAbortRef.current = ctrl;
        setCargandoProductos(true);
        axios.get<{ productos: Producto[]; has_more: boolean; cursor: string | null }>(
            route('pos.productos'),
            {
                params: { q: qRaw, categoria_id: categoriaActiva, tipo: tipoActivo, venta_id: ventaEnEdicion?.id },
                signal: ctrl.signal,
            },
        ).then(({ data }) => {
            const items = data.productos;
            const exacto = items.find(p => (p.codigo ?? '').toLowerCase() === q);
            const candidato = exacto ?? (items.length === 1 ? items[0] : null);
            if (candidato) {
                agregarProducto(candidato);
                setBusqueda('');
                // Limpiar la lista para que el debounce posterior vuelva a vaciar.
                setProductosQuery('');
            } else {
                setBusqueda(qRaw);
                setProductosQuery(qRaw);
            }
            setHasMoreProductos(data.has_more);
            setCursorProductos(data.cursor ?? null);
            setListaProductos(items);
        }).catch((err: any) => {
            if (!axios.isCancel(err)) {
                toast.error(err?.response?.data?.message || 'Error al buscar producto');
            }
        }).finally(() => setCargandoProductos(false));
    }

    /**
     * Punto de entrada al hacer click en un producto del catalogo.
     * - Si tiene 1 sola presentacion (caso tipico) → agrega directo al carrito.
     * - Si tiene 2+ presentaciones (talla P/M/G en servicios, presentaciones
     *   de bebida en bodega) → abre el modal selector para que el cajero elija.
     */
    function agregarProducto(producto: Producto) {
        const unidadesActivas = (producto.unidades ?? []).filter(u => u.activo !== false);

        if (unidadesActivas.length === 0) {
            toast.error('El producto no tiene presentaciones configuradas.');
            return;
        }

        if (unidadesActivas.length === 1) {
            agregarConPresentacion(producto, unidadesActivas[0]);
            return;
        }

        // 2+ presentaciones → abrir modal selector
        setProductoEnSeleccion(producto);
    }

    /**
     * Agrega al carrito una linea con la presentacion ya elegida.
     *
     * Comportamiento clásico (default): si la misma presentación ya está en el
     * carrito, se incrementa la cantidad.
     *
     * Si la empresa tiene activa "Permitir duplicar ítems en una venta", cada
     * toque crea una línea independiente. Esto permite vender el mismo producto
     * con precios distintos, útil para negocios con precios variables.
     */
    function agregarConPresentacion(producto: Producto, unidad: ProductoUnidad) {
        const baseKey = `${producto.id}-${unidad.id}`;
        const precio = parseFloat(unidad.precio_venta);
        const nombreCompleto = unidad.unidad_medida?.nombre
            ? `${producto.nombre} (${unidad.unidad_medida.nombre})`
            : producto.nombre;

        if (permiteDuplicarItems) {
            const yaExiste = carrito.some(i => i.producto_id === producto.id && i.producto_unidad_id === unidad.id);
            if (yaExiste && !advertenciasDuplicados.current.has(baseKey)) {
                advertenciasDuplicados.current.add(baseKey);
                toast(
                    `${nombreCompleto} ya está en el carrito. Se agregó como una nueva línea.`,
                    { icon: '⚠️', duration: 2500 },
                );
            }

            const key = `${baseKey}-${Date.now()}-${uid()}`;
            const item: LineaCarrito = {
                key,
                producto_id:          producto.id,
                producto_unidad_id:   unidad.id,
                producto_nombre:      producto.nombre,
                unidad_nombre:        unidad.unidad_medida?.nombre ?? '',
                precio_unitario:      precio,
                precio_original:      precio,
                costo_minimo:         costoMinimoDe(producto, unidad),
                stock_disponible:     producto.stock_disponible ?? null,
                stock_en_transito:    producto.stock_en_transito ?? 0,
                transito_fecha:       producto.transito_fecha ?? null,
                factor_conversion:    parseFloat(unidad.factor_conversion) || 1,
                cantidad:             1,
                descuento_item:       0,
                descuento_modo:       'pu',
                descuento_tipo:       'monto',
                descuento_valor:      0,
                descuento_concepto_id: null,
                subtotal:             precio,
                incluye_igv:          producto.incluye_igv,
            };
            setCarrito(prev => [...prev, item]);

            // Si el precio base es 0, enfocar el input de precio de la nueva línea
            // para que la cajera lo cambie inmediatamente.
            if (precio === 0) {
                setNuevaLineaPrecioKey(key);
            }

            toast.success(`${nombreCompleto} agregado`, { duration: 1000 });
            return;
        }

        // Comportamiento clásico: sumar cantidad si ya existe.
        const existente = carrito.find(i => i.key === baseKey);
        if (existente) {
            cambiarCantidad(baseKey, 1);
        } else {
            const item: LineaCarrito = {
                key: baseKey,
                producto_id:          producto.id,
                producto_unidad_id:   unidad.id,
                producto_nombre:      producto.nombre,
                unidad_nombre:        unidad.unidad_medida?.nombre ?? '',
                precio_unitario:      precio,
                precio_original:      precio,
                costo_minimo:         costoMinimoDe(producto, unidad),
                stock_disponible:     producto.stock_disponible ?? null,
                stock_en_transito:    producto.stock_en_transito ?? 0,
                transito_fecha:       producto.transito_fecha ?? null,
                factor_conversion:    parseFloat(unidad.factor_conversion) || 1,
                cantidad:             1,
                descuento_item:       0,
                descuento_modo:       'pu',
                descuento_tipo:       'monto',
                descuento_valor:      0,
                descuento_concepto_id: null,
                subtotal:             precio,
                incluye_igv:          producto.incluye_igv,
            };
            setCarrito(prev => [...prev, item]);
        }

        toast.success(`${nombreCompleto} agregado`, { duration: 1000 });
    }

    function cambiarCantidad(key: string, delta: number) {
        setCarrito(prev => prev.map(i =>
            i.key === key ? recalcularLinea(i, { cantidad: Math.max(1, i.cantidad + delta) }) : i,
        ));
    }

    /** Cantidad tecleada directamente en el input de la linea (permite decimales). */
    function establecerCantidad(key: string, cantidad: number) {
        setCarrito(prev => prev.map(i =>
            i.key === key ? recalcularLinea(i, { cantidad: Math.max(0.0001, Math.round(cantidad * 10000) / 10000) }) : i,
        ));
    }

    /**
     * Precio de venta editado en la linea. CarritoItem ya valido el piso de
     * costo antes de llamar; aqui solo recalculamos. Como el descuento se guarda
     * por modo/tipo/valor, `recalcularLinea` re-deriva el descuento efectivo con
     * el precio nuevo (ej. un descuento en % sigue el nuevo precio).
     */
    function cambiarPrecio(key: string, precio: number) {
        setCarrito(prev => prev.map(i =>
            i.key === key ? recalcularLinea(i, { precio_unitario: Math.max(0, precio) }) : i,
        ));
    }

    function aplicarDescuentoItem(key: string, valor: number, modo: DescModo, tipo: DescTipo, conceptoId: number | null) {
        setCarrito(prev => prev.map(i =>
            i.key === key
                ? recalcularLinea(i, {
                    descuento_modo:        modo,
                    descuento_tipo:        tipo,
                    descuento_valor:       Math.max(0, valor),
                    descuento_concepto_id: valor > 0 ? conceptoId : null,
                })
                : i,
        ));
    }

    function eliminarItem(key: string) {
        setCarrito(prev => prev.filter(i => i.key !== key));
    }

    function limpiarCarrito() {
        setCarrito([]);
        setPagos([]);
        setCliente(clienteGeneral);
        setMoneda('PEN');
        setDescuentoTotal(0);
        setDescuentoConceptoId(null);
        setTipoComprobante('ticket');
        setNumeroComprobante('');
        // La venta a crédito no debe "heredarse" a la siguiente venta.
        setEsCredito(false);
        setFechaVencimiento('');
        // Tampoco el pendiente por entregar.
        setEntregaPendiente(false);
        setFechaEntrega('');
        setPendientes({});
        // Resetear advertencias de duplicados para la siguiente venta.
        advertenciasDuplicados.current.clear();
        setNuevaLineaPrecioKey(null);
    }

    /** Pendiente efectivo de una línea: lo tecleado, recortado a [0, cantidad]. */
    function pendienteDe(item: LineaCarrito): number {
        const p = pendientes[item.key] ?? 0;
        return Math.min(Math.max(0, p), item.cantidad);
    }

    const totalPendientes = entregaPendiente
        ? carrito.reduce((s, i) => s + pendienteDe(i), 0)
        : 0;

    // Lineas que vienen de una cita con producto/unidad desactivada. Si hay,
    // no permitimos confirmar la venta hasta que el cajero las elimine o pida
    // al admin reactivarlas. El backend tambien lo rechaza, pero queremos UX
    // clara en lugar de un error 422 al final del flujo.
    const itemsInactivos = carrito.filter(i => i.inactivo);
    const hayInactivos   = itemsInactivos.length > 0;

    // F1 — Cliente General no puede llevar crédito: sin nombre no hay a quién cobrar.
    const esClienteGeneralSel = !cliente
        || (cliente as Cliente & { es_cliente_general?: boolean }).es_cliente_general
        || cliente.numero_documento === '99999999';

    /* ── V10 · Facturación electrónica ───────────────────────────────────────
       Las validaciones de §5.4 se resuelven ACÁ, una sola vez, y se reparten a
       los dos selectores de comprobante (barra superior y barra móvil) y al
       botón de cobrar. Para una venta `ticket` —hoy el 100 % del flujo— todo
       esto es null y la pantalla se comporta exactamente como antes. */
    // Las reglas SUNAT se validan SIEMPRE que el comprobante no sea `ticket`:
    // StoreVentaRequest las aplica igual aunque el módulo esté apagado, y un 422
    // después de cobrar es justo lo que V10 existe para evitar.
    const feActiva  = !!facturacion?.enabled;
    const esComprobanteExterno = tipoComprobante === 'boleta_externa' || tipoComprobante === 'factura_externa';
    const emiteCPE  = tipoComprobante !== 'ticket' && !esComprobanteExterno;
    // El umbral bueno lo dicta el EMISOR (§7). El 700 de la constante es el
    // default de último recurso, no una configuración del POS.
    const umbralCPE = facturacion?.umbral_boleta_identificada ?? UMBRAL_BOLETA_IDENTIFICADA;
    // Serie INFORMATIVA: la que el emisor dice que va a usar (`series_por_defecto`
    // de /api/v1/configuracion). El POS no la manda al emitir —la numeración es
    // del emisor— pero enseñarla en caja es lo que habría hecho visible, antes de
    // cobrar, la boleta de prueba que salió por la serie fiscal real B001.
    const serieCPE  = !feActiva || esComprobanteExterno ? null
        : tipoComprobante === 'factura' ? (facturacion?.series?.factura ?? null)
        : tipoComprobante === 'boleta'  ? (facturacion?.series?.boleta  ?? null)
        : null;
    // Franja de modo: distingue simulacion / beta / produccion. Ver avisoModoEmision.
    // Si el backend aún no manda `modo` (props antiguas), se deduce del booleano
    // `produccion`, que conserva el mismo fail-safe: ante la duda, aviso rojo.
    const modoCPE   = facturacion?.modo ?? (facturacion?.produccion === false ? 'beta' : 'produccion');
    const avisoModo = feActiva ? avisoModoEmision(modoCPE) : null;

    const bloqueoComprobante: BloqueoComprobante | null = useMemo(
        () => validarComprobante({ tipoComprobante, cliente, total, moneda, umbral: umbralCPE, emisionActiva: feActiva }),
        [tipoComprobante, cliente, total, moneda, umbralCPE, feActiva],
    );

    // La franja informativa solo aparece si el módulo está activo (hay algo real
    // que emitir) o si hay un problema que impide cobrar. Con el módulo apagado
    // y todo en orden, el POS se ve exactamente como hoy.
    const mostrarAvisoCPE = emiteCPE && (feActiva || !!bloqueoComprobante);

    // Badge del comprobante recién emitido (lo trae el flash de la venta anterior).
    const comprobanteFlash = flash?.comprobante ?? null;

    function confirmarVenta() {
        // V10 — avisar ANTES de cobrar, no después de emitir mal.
        if (bloqueoComprobante) {
            toast.error(bloqueoComprobante.motivo);
            if (bloqueoComprobante.requiereCliente) setModalCliente(true);
            return;
        }
        if (carrito.length === 0) { toast.error('El carrito está vacío.'); return; }
        if (hayInactivos) {
            toast.error(`Hay ${itemsInactivos.length} ítem(s) inactivo(s). Elimínalos del carrito antes de cobrar.`);
            return;
        }
        // Defensa adicional al piso de costo (el input ya lo valida al editar,
        // y el backend lo vuelve a validar al registrar la venta).
        const bajoCosto = carrito.filter(i => (i.costo_minimo ?? 0) > 0 && i.precio_unitario < i.costo_minimo - 0.009);
        if (bajoCosto.length > 0) {
            toast.error(`Hay precios por debajo del costo: ${bajoCosto.map(i => i.producto_nombre).join(', ')}. Corrígelos antes de cobrar.`);
            return;
        }
        const totalPagado = totalPagadoConAnticipo;

        if (entregaPendiente) {
            if (esCredito && !creditoYaPagado) {
                const msg = (ventaEnEdicion?.es_credito && (ventaEnEdicion?.saldo_pendiente ?? 0) > 0.0001)
                    ? 'No puedes marcar pendiente por entregar: la venta a crédito aún tiene saldo pendiente. Salda el crédito primero.'
                    : 'No puedes combinar "Pendiente por entregar" con venta a crédito: el pendiente exige que la venta esté pagada.';
                toast.error(msg);
                return;
            }
            if (esClienteGeneralSel) {
                toast.error('Marcar mercadería pendiente por entregar requiere un cliente identificado.');
                return;
            }
            if (totalPendientes <= 0.00009) {
                toast.error('Indica cuánto queda pendiente por entregar en al menos un producto (o desmarca la opción).');
                return;
            }
        }

        if (esCredito) {
            if (esClienteGeneralSel) {
                toast.error('Una venta a crédito requiere seleccionar un cliente identificado.');
                return;
            }
            if (totalPagado > total + 0.009) {
                toast.error('En una venta a crédito el pago inicial no puede exceder el total.');
                return;
            }
        } else {
            if (pagos.length === 0 && !anticipoSeleccionado) { toast.error('Agrega al menos un método de pago o selecciona un anticipo.'); return; }
            if (totalPagado < total - 0.009) { toast.error(`Faltan S/ ${(total - totalPagado).toFixed(2)} por cubrir.`); return; }
        }
        // Cuenta obligatoria: si un método tiene 2+ cuentas hay que elegir cuál
        // (con 1 se autoselecciona). Aplica también al pago inicial de crédito.
        if (faltanCuentas(pagos, metodosPago)) {
            toast.error('Selecciona la cuenta de cada pago (obligatorio cuando el método tiene más de una cuenta).');
            return;
        }
        setModalConfirm(true);
    }

    // imprimir = true → crear la venta Y mandarla a la impresora (botón secundario);
    // false → solo crearla (botón principal). El backend usa este flag para decidir
    // si activa el auto-print del ticket al redirigir al detalle.
    function submitVenta(imprimir = false) {
        // Anti-doble-click: si ya hay una venta en proceso, ignorar nuevos intentos.
        // El boton del modal ya se deshabilita visualmente, pero esta guarda cubre
        // casos como tecla Enter o clicks muy rapidos antes del re-render.
        if (loading) return;

        setLoading(true);

        const payload = {
            cliente_id:            cliente?.id ?? null,
            tipo_comprobante:      tipoComprobante,
            numero_comprobante:    esComprobanteExterno ? (numeroComprobante.trim() || null) : null,
            // Solo el POST de creación lo usa; en edición se ignora (no auto-imprime).
            imprimir,
            descuento_total:       descuentoTotal,
            descuento_concepto_id: descuentoConceptoId,
            es_credito:            esCredito,
            fecha_vencimiento:     esCredito && fechaVencimiento ? fechaVencimiento : null,
            entrega_pendiente:     entregaPendiente && !despachoAlmacen,
            despacho_almacen:      despachoAlmacen,
            fecha_entrega_estimada: (entregaPendiente || despachoAlmacen) && fechaEntrega ? fechaEntrega : null,
            moneda,
            tipo_cambio:           moneda === 'USD' ? (tipoCambioHoy ?? null) : null,
            // Modo turno específico (admin): la venta va a ESE turno con la
            // fecha del turno (backdate). El backend valida admin + turno abierto.
            turno_id:              turnoBackdate?.turno_id ?? null,
            fecha_venta:           turnoBackdate?.fecha ?? null,
            // Se reenvia el mismo key en cada reintento. El backend desduplica.
            idempotency_key:       idempotencyKey,
            // Si vino de una cita, lo enviamos para que el backend la vincule.
            cita_id:               citaPrellenada?.id ?? null,
            // Si vino de una cotización, el backend la marca 'convertida' y
            // le guarda el venta_id.
            cotizacion_id:         cotizacionPrellenada?.id ?? null,
            // Anticipo de efectivo del cliente con el que se pagará la venta.
            anticipo_id:           anticipoSeleccionado,
            items: carrito.map(i => ({
                producto_id:           i.producto_id,
                producto_unidad_id:    i.producto_unidad_id,
                cantidad:              i.cantidad,
                cantidad_pendiente:    entregaPendiente ? pendienteDe(i) : 0,
                precio_unitario:       i.precio_unitario,
                descuento_item:        i.descuento_item,
                descuento_concepto_id: i.descuento_concepto_id,
                incluye_igv:           i.incluye_igv,
            })),
            pagos: pagos.map(p => ({
                metodo_pago_id:        p.metodo_pago_id,
                cuenta_metodo_pago_id: p.cuenta_metodo_pago_id,
                monto:                 p.monto,
                referencia:            p.referencia,
                // El backend ignora estos flags y deriva la decisión desde
                // metodos_pago.admite_vuelto en BD. Los enviamos por compatibilidad.
                admite_vuelto:         p.admite_vuelto,
                es_efectivo:           p.es_efectivo,
            })),
        };

        // Modo EDICIÓN: PUT a ventas.update (conserva número/turno). El backend
        // redirige al detalle de la venta, así que no limpiamos el carrito.
        if (ventaEnEdicion) {
            router.put(route('ventas.update', ventaEnEdicion.id), payload as any, {
                onSuccess: () => { setLoading(false); setModalConfirm(false); },
                onError: (errors) => {
                    setLoading(false);
                    const msg = Object.values(errors)[0];
                    if (msg) toast.error(msg as string);
                },
            });
            return;
        }

        router.post(route('ventas.store'), payload as any, {
            onSuccess: () => {
                setLoading(false);
                setModalConfirm(false);
                limpiarCarrito();
                setCarritoAbierto(false);
                // Renovar key para la proxima venta (la actual ya quedo persistida).
                setIdempotencyKey(generarIdempotencyKey());
            },
            onError: (errors) => {
                setLoading(false);
                const msg = Object.values(errors)[0];
                if (msg) toast.error(msg as string);
            },
        });
    }

    const cantidadItems = carrito.reduce((s, i) => s + i.cantidad, 0);

    // Crédito y pendiente-por-entregar son excluyentes POR DEFECTO, pero si
    // la venta a crédito YA FUE PAGADA (saldo_pendiente == 0), sí se permite
    // marcar pendiente por entregar: el dinero cubre la mercadería.
    const saldoPendienteEdicion = ventaEnEdicion?.saldo_pendiente ?? 0;
    const creditoYaPagado = ventaEnEdicion?.es_credito === true && saldoPendienteEdicion <= 0.0001;
    const creditoBloqueadoEnEdicion = !!ventaEnEdicion?.es_credito;

    function activarCredito(v: boolean) {
        // Solo se bloquea quitar el crédito a una venta que ya lo era (afecta
        // abonos, saldo por cobrar y trazabilidad). Activar crédito en una venta
        // que estaba de contado sí está permitido y el backend lo respeta.
        if (creditoBloqueadoEnEdicion && !v) {
            toast.error('No puedes quitar "Venta a crédito" al editar una venta que ya estaba a crédito.');
            return;
        }
        setEsCredito(v);
        if (v) setEntregaPendiente(false);
    }
    function activarPendiente(v: boolean) {
        if (v && esCredito && !creditoYaPagado) {
            const msg = saldoPendienteEdicion > 0
                ? 'No puedes marcar pendiente por entregar: la venta a crédito aún tiene saldo pendiente. Salda el crédito primero.'
                : 'No puedes combinar "Pendiente por entregar" con venta a crédito.';
            toast.error(msg);
            return;
        }
        setEntregaPendiente(v);
        if (v) setDespachoAlmacen(false);
        // Solo desmarcamos crédito si la venta NO estaba a crédito originalmente.
        // Una venta a crédito ya pagada puede mantener el flag por trazabilidad.
        if (v && !creditoBloqueadoEnEdicion) setEsCredito(false);
    }
    function activarDespachoAlmacen(v: boolean) {
        if (v && esCredito && !creditoYaPagado) {
            const msg = saldoPendienteEdicion > 0
                ? 'No puedes marcar despacho en almacén: la venta a crédito aún tiene saldo pendiente. Salda el crédito primero.'
                : 'No puedes combinar "Despacho en almacén" con venta a crédito.';
            toast.error(msg);
            return;
        }
        setDespachoAlmacen(v);
        if (v) {
            setEntregaPendiente(false);
            if (!creditoBloqueadoEnEdicion) setEsCredito(false);
        }
    }
    function setPendienteLinea(key: string, valor: number) {
        setPendientes(prev => ({ ...prev, [key]: valor }));
    }

    const propsPendiente = {
        // Editable también en edición de venta, SALVO que ya haya entregas
        // registradas del pendiente (el backend bloquea toda la edición ahí).
        permitirPendiente:     !ventaEnEdicion?.pendiente_bloqueado,
        entregaPendiente,
        despachoAlmacen,
        fechaEntrega,
        pendienteDe,
        totalPendientes,
        onSetEntregaPendiente: activarPendiente,
        onSetDespachoAlmacen:  activarDespachoAlmacen,
        onSetFechaEntrega:     setFechaEntrega,
        onSetPendiente:        setPendienteLinea,
        // Bandeja de despacho en almacén (solo si la empresa lo activó).
        usaDespachoAlmacen:    empresaAuth?.usa_despacho_almacen ?? false,
        // Autofoco del precio en líneas recién agregadas con precio base 0.
        nuevaLineaPrecioKey,
        onAutoFocusPrecio:     () => setNuevaLineaPrecioKey(null),
    };

    return (
        <PosLayout>
            {/* Banner de edición de venta (POS abierto con ?venta_id=) */}
            {ventaEnEdicion && (
                <div
                    className="flex items-center justify-between gap-2 px-3 sm:px-4 py-2 flex-shrink-0 border-b text-sm"
                    style={{
                        backgroundColor: 'color-mix(in srgb, var(--color-warning) 15%, var(--color-bg))',
                        borderColor: 'var(--color-warning)',
                        color: 'var(--color-text)',
                    }}
                >
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                            style={{ backgroundColor: 'var(--color-warning)', color: '#fff' }}>
                            Editando {ventaEnEdicion.numero}
                        </span>
                        <span style={{ color: 'var(--color-text-muted)' }}>
                            Modifica productos, cantidades, precios o pagos y guarda los cambios.
                            {!ventaEnEdicion.es_admin && ' Tienes 3 minutos desde que se creó la venta.'}
                            {ventaEnEdicion.pendiente_bloqueado && (
                                <strong style={{ color: 'var(--color-danger)' }}>
                                    {' '}Esta venta ya tiene entregas del pendiente registradas: no se puede editar (anúlala y regístrala de nuevo).
                                </strong>
                            )}
                        </span>
                    </div>
                    <Link href={route('ventas.index')}
                        className="text-xs font-medium underline hover:opacity-80"
                        style={{ color: 'var(--color-warning)' }}>
                        Cancelar
                    </Link>
                </div>
            )}

            {/* Banner de turno específico/backdate (POS abierto con ?turno_id=) */}
            {turnoBackdate && !ventaEnEdicion && (
                <div
                    className="flex items-center justify-between gap-2 px-3 sm:px-4 py-2 flex-shrink-0 border-b text-sm"
                    style={{
                        backgroundColor: 'color-mix(in srgb, var(--color-danger) 12%, var(--color-bg))',
                        borderColor: 'var(--color-danger)',
                        color: 'var(--color-text)',
                    }}
                >
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                            style={{ backgroundColor: 'var(--color-danger)', color: '#fff' }}>
                            Turno #{turnoBackdate.turno_id}{turnoBackdate.caja ? ` · ${turnoBackdate.caja}` : ''}
                        </span>
                        <span style={{ color: 'var(--color-text)' }}>
                            {turnoBackdate.cajera && <>Turno de <strong>{turnoBackdate.cajera}</strong> · </>}
                            {turnoBackdate.es_hoy
                                ? 'Las ventas se registran en este turno.'
                                : <>Las ventas se guardarán con fecha <strong>
                                    {turnoBackdate.fecha ? new Date(turnoBackdate.fecha + 'T00:00:00').toLocaleDateString('es-PE') : '—'}
                                  </strong> (la del turno, no la de hoy).</>}
                        </span>
                    </div>
                    <Link href={route('turnos.show', turnoBackdate.turno_id)}
                        className="text-xs font-medium underline hover:opacity-80"
                        style={{ color: 'var(--color-danger)' }}>
                        Ver turno
                    </Link>
                </div>
            )}

            {/* Banner de cita activa (cuando se llega desde la agenda) */}
            {citaPrellenada && (
                <div
                    className="flex items-center justify-between gap-2 px-3 sm:px-4 py-2 flex-shrink-0 border-b text-sm"
                    style={{
                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, var(--color-bg))',
                        borderColor: 'var(--color-primary)',
                        color: 'var(--color-text)',
                    }}
                >
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                            style={{ backgroundColor: 'var(--color-primary)', color: '#fff' }}>
                            Cita {citaPrellenada.numero}
                        </span>
                        <span style={{ color: 'var(--color-text)' }}>
                            <strong>
                                {citaPrellenada.cliente.razon_social
                                    ?? `${citaPrellenada.cliente.nombres ?? ''} ${citaPrellenada.cliente.apellidos ?? ''}`.trim()}
                            </strong>
                        </span>
                        {citaPrellenada.sujeto && citaPrellenada.sujeto_label && (
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                · {citaPrellenada.sujeto_label}: <strong>{citaPrellenada.sujeto}</strong>
                            </span>
                        )}
                        <span className="text-xs opacity-70" style={{ color: 'var(--color-text-muted)' }}>
                            · Carrito prellenado, puedes agregar/quitar antes de cobrar
                        </span>
                    </div>
                    <Link href={route('agenda.show', citaPrellenada.id)}
                        className="text-xs font-medium underline hover:opacity-80"
                        style={{ color: 'var(--color-primary)' }}>
                        Ver cita
                    </Link>
                </div>
            )}

            {/* Banner de cotización (cuando se llega desde el módulo Cotizaciones) */}
            {cotizacionPrellenada && (
                <div
                    className="flex items-center justify-between gap-2 px-3 sm:px-4 py-2 flex-shrink-0 border-b text-sm"
                    style={{
                        backgroundColor: 'color-mix(in srgb, var(--color-success) 12%, var(--color-bg))',
                        borderColor: 'var(--color-success)',
                        color: 'var(--color-text)',
                    }}
                >
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                            style={{ backgroundColor: 'var(--color-success)', color: '#fff' }}>
                            Cotización {cotizacionPrellenada.numero}
                        </span>
                        <span style={{ color: 'var(--color-text)' }}>
                            <strong>
                                {cotizacionPrellenada.cliente.razon_social
                                    ?? `${cotizacionPrellenada.cliente.nombres ?? ''} ${cotizacionPrellenada.cliente.apellidos ?? ''}`.trim()}
                            </strong>
                        </span>
                        {cotizacionPrellenada.referencia && (
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                · Ref: <strong>{cotizacionPrellenada.referencia}</strong>
                            </span>
                        )}
                        <span className="text-xs opacity-70" style={{ color: 'var(--color-text-muted)' }}>
                            · Carrito con los precios cotizados; al cobrar quedará convertida en venta
                        </span>
                    </div>
                    <Link href={route('cotizaciones.index')}
                        className="text-xs font-medium underline hover:opacity-80"
                        style={{ color: 'var(--color-success)' }}>
                        Ver cotizaciones
                    </Link>
                </div>
            )}

            {/* ── Barra superior ─────────────────────────────────────────── */}
            <div
                className="flex items-center justify-between px-3 sm:px-4 py-2.5 flex-shrink-0"
                style={{
                    backgroundColor: 'var(--color-primary)',
                    color: '#fff',
                }}
            >
                <div className="flex items-center gap-2 sm:gap-3 min-w-0">
                    <Link
                        href={route('dashboard')}
                        aria-label="Volver al dashboard"
                        className="flex items-center justify-center h-9 w-9 rounded-lg hover:bg-white/15 active:bg-white/25 transition-colors flex-shrink-0"
                    >
                        <ArrowLeft size={18} />
                    </Link>
                    <div className="hidden sm:block w-px h-5 bg-white/20 flex-shrink-0" />
                    <div className="min-w-0">
                        <p className="font-bold text-sm leading-tight truncate">
                            <span className="sm:hidden">POS</span>
                            <span className="hidden sm:inline">POS · {turno.caja?.nombre ?? 'Caja'}</span>
                        </p>
                        <p className="hidden sm:block text-[10px] opacity-70 leading-tight">
                            Turno #{turno.id}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2 flex-shrink-0">
                    {/* Comprobante (solo desktop/tablet) */}
                    <div className="hidden sm:flex items-center gap-1.5">
                        <Receipt size={14} className="opacity-70" />
                        <select
                            value={tipoComprobante}
                            onChange={e => setTipoComprobante(e.target.value as TipoComprobante)}
                            className="text-xs bg-white/15 border-0 rounded-lg px-2 py-1.5 text-white focus:outline-none focus:ring-2 focus:ring-white/30"
                        >
                            <option value="ticket" className="text-gray-900">Sin comprobante</option>
                            {feActiva ? (
                                <>
                                    <option value="boleta" className="text-gray-900">Boleta</option>
                                    <option value="factura" className="text-gray-900">Factura</option>
                                </>
                            ) : (
                                <>
                                    <option value="boleta_externa" className="text-gray-900">Boleta electrónica externa</option>
                                    <option value="factura_externa" className="text-gray-900">Factura electrónica externa</option>
                                </>
                            )}
                        </select>
                        {esComprobanteExterno && (
                            <input
                                type="text"
                                value={numeroComprobante}
                                onChange={e => setNumeroComprobante(e.target.value.toUpperCase())}
                                placeholder="N° comprobante"
                                maxLength={30}
                                className="text-xs bg-white/15 border-0 rounded-lg px-2 py-1.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-white/30 w-32"
                            />
                        )}
                        {/* Serie que se usará / motivo de bloqueo, junto al selector. */}
                        <PistaComprobante
                            visible={emiteCPE}
                            serie={serieCPE}
                            bloqueo={bloqueoComprobante}
                            sobrePrimario
                        />
                    </div>

                    {/* Badge del comprobante de la venta recién cerrada. */}
                    {comprobanteFlash && (
                        <span
                            className="hidden sm:inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-1 rounded-lg bg-white/15 whitespace-nowrap"
                            title={`Comprobante ${comprobanteFlash.numero}: ${metaEstado(comprobanteFlash.estado).label}`}
                        >
                            <FileCheck2 size={12} className="opacity-80" />
                            {comprobanteFlash.numero} · {metaEstado(comprobanteFlash.estado).label}
                        </span>
                    )}

                    {/* Moneda (multimoneda). USD requiere TC del día disponible. */}
                    {(monedas ?? ['PEN']).includes('USD') && tipoCambioHoy ? (
                        <div className="hidden sm:flex items-center gap-1.5" title={`Tipo de cambio del día: S/ ${Number(tipoCambioHoy).toFixed(3)} por US$ 1`}>
                            <select
                                value={moneda}
                                onChange={e => setMoneda(e.target.value as 'PEN' | 'USD')}
                                className="text-xs bg-white/15 border-0 rounded-lg px-2 py-1.5 text-white focus:outline-none focus:ring-2 focus:ring-white/30"
                            >
                                <option value="PEN" className="text-gray-900">S/ Soles</option>
                                <option value="USD" className="text-gray-900">US$ Dólares</option>
                            </select>
                            {moneda === 'USD' && (
                                <span className="text-[11px] font-medium text-white/90 whitespace-nowrap">TC {Number(tipoCambioHoy).toFixed(3)}</span>
                            )}
                        </div>
                    ) : null}

                    {/* Cliente — siempre visible (incluso en móvil/PWA) */}
                    <button
                        onClick={() => setModalCliente(true)}
                        aria-label="Cambiar cliente"
                        className="flex items-center gap-2 text-xs px-3 py-2 rounded-lg bg-white/15 hover:bg-white/25 active:bg-white/30 transition-colors min-h-[36px] max-w-[180px] sm:max-w-[220px]"
                    >
                        <User size={14} className="flex-shrink-0" />
                        <span className="truncate font-medium text-[13px]">
                            {cliente
                                ? (cliente.razon_social ?? `${cliente.nombres} ${cliente.apellidos ?? ''}`.trim())
                                : 'Cliente general'}
                        </span>
                        <ChevronDown size={12} className="opacity-60 flex-shrink-0" />
                    </button>
                </div>
            </div>

            {/* ── Anticipo de efectivo del cliente ─────────────────────────
                Si el cliente tiene anticipos de efectivo activos, ofrece usar
                uno para descontar de la venta. El anticipo actúa como pago sin
                generar movimiento de caja (el dinero ya entró al registrarlo). */}
            {!!cliente && anticiposCliente.length > 0 && (
                <div
                    className="flex items-center justify-between gap-2 px-3 sm:px-4 py-2 text-sm border-b flex-shrink-0"
                    style={{
                        backgroundColor: anticipoSeleccionado
                            ? 'color-mix(in srgb, var(--color-success) 12%, var(--color-bg))'
                            : 'color-mix(in srgb, var(--color-warning) 12%, var(--color-bg))',
                        borderColor: anticipoSeleccionado ? 'var(--color-success)' : 'var(--color-warning)',
                        color: 'var(--color-text)',
                    }}
                >
                    <div className="flex items-center gap-2 flex-wrap min-w-0">
                        <span className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                            style={{ backgroundColor: anticipoSeleccionado ? 'var(--color-success)' : 'var(--color-warning)', color: '#fff' }}>
                            {anticipoSeleccionado ? 'Anticipo activo' : 'Anticipo disponible'}
                        </span>
                        <span className="truncate">
                            {anticipoSeleccionado
                                ? `Se descontará S/ ${montoAnticipoUsado.toFixed(2)} de los anticipos del cliente.`
                                : `Este cliente tiene S/ ${anticiposCliente.reduce((s, a) => s + a.saldo, 0).toFixed(2)} en anticipos de efectivo.`}
                        </span>
                    </div>
                    <div className="flex items-center gap-2 flex-shrink-0">
                        {anticiposCliente.length > 1 && (
                            <select
                                value={anticipoSeleccionado ?? ''}
                                onChange={e => setAnticipoSeleccionado(e.target.value ? Number(e.target.value) : null)}
                                disabled={cargandoAnticipos}
                                className="text-xs border rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                }}
                            >
                                <option value="">— Elegir anticipo —</option>
                                {anticiposCliente.map(a => (
                                    <option key={a.id} value={a.id}>
                                        S/ {a.saldo.toFixed(2)} {a.observacion ? `(${a.observacion})` : ''}
                                    </option>
                                ))}
                            </select>
                        )}
                        <button
                            onClick={() => setAnticipoSeleccionado(anticipoSeleccionado ? null : anticiposCliente[0]?.id ?? null)}
                            disabled={cargandoAnticipos}
                            className="text-xs font-bold px-2.5 py-1.5 rounded-lg transition-colors hover:opacity-90"
                            style={{
                                backgroundColor: anticipoSeleccionado
                                    ? 'color-mix(in srgb, var(--color-danger) 12%, transparent)'
                                    : 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                color: anticipoSeleccionado ? 'var(--color-danger)' : 'var(--color-primary)',
                            }}
                        >
                            {anticipoSeleccionado ? 'No usar' : 'Usar anticipo'}
                        </button>
                    </div>
                </div>
            )}

            {/* ── V10 · Aviso de comprobante electrónico ─────────────────
                Franja permanente mientras el comprobante NO sea "ticket": qué
                se va a emitir, con qué serie, y en qué MODO está el emisor.

                Los tres modos se ven distintos a propósito (§7): `simulacion` no
                sale del emisor, `beta` va al SUNAT de pruebas y `produccion` es
                irreversible. Antes esto era un booleano sacado de /ping y
                `simulacion` se pintaba igual que `produccion` —o al revés—, que
                es justo la confusión que provocó el incidente de emitir contra
                producción creyendo estar en pruebas. */}
            {mostrarAvisoCPE && (
                <div className="flex flex-col flex-shrink-0">
                    {avisoModo && (
                        <div
                            className="flex items-center gap-2 px-3 sm:px-4 py-2 text-sm font-bold"
                            style={{ backgroundColor: avisoModo.fondo, color: avisoModo.tinta }}
                        >
                            {avisoModo.grave && <AlertTriangle size={16} className="flex-shrink-0" />}
                            <span>{avisoModo.texto}</span>
                        </div>
                    )}
                    <div
                        className="flex items-center justify-between gap-2 px-3 sm:px-4 py-2 flex-wrap border-b text-sm"
                        style={{
                            backgroundColor: bloqueoComprobante
                                ? 'color-mix(in srgb, var(--color-danger) 12%, var(--color-bg))'
                                : 'color-mix(in srgb, var(--color-primary) 10%, var(--color-bg))',
                            borderColor: bloqueoComprobante ? 'var(--color-danger)' : 'var(--color-primary)',
                            color: 'var(--color-text)',
                        }}
                    >
                        <div className="flex items-center gap-2 flex-wrap min-w-0">
                            <span
                                className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                                style={{
                                    backgroundColor: bloqueoComprobante ? 'var(--color-danger)' : 'var(--color-primary)',
                                    color: '#fff',
                                }}
                            >
                                {etiquetaComprobante(tipoComprobante)}
                            </span>
                            {serieCPE && (
                                <span style={{ color: 'var(--color-text-muted)' }}>
                                    Serie <strong style={{ color: 'var(--color-text)' }}>{serieCPE}</strong>
                                </span>
                            )}
                            {bloqueoComprobante ? (
                                <span className="font-semibold" style={{ color: 'var(--color-danger)' }}>
                                    {bloqueoComprobante.motivo}
                                </span>
                            ) : feActiva && !esComprobanteExterno ? (
                                <span className="text-xs opacity-80" style={{ color: 'var(--color-text-muted)' }}>
                                    · Se emitirá a SUNAT al cerrar la venta
                                </span>
                            ) : esComprobanteExterno ? (
                                <span className="text-xs opacity-80" style={{ color: 'var(--color-text-muted)' }}>
                                    · Se registra sin emitir desde el sistema
                                </span>
                            ) : null}
                        </div>
                        <div className="flex items-center gap-3 flex-shrink-0">
                            {bloqueoComprobante?.requiereCliente && (
                                <button
                                    onClick={() => setModalCliente(true)}
                                    className="text-xs font-bold underline hover:opacity-80"
                                    style={{ color: 'var(--color-danger)' }}
                                >
                                    Elegir cliente
                                </button>
                            )}
                            <button
                                onClick={() => setTipoComprobante('ticket')}
                                className="text-xs font-medium underline hover:opacity-80"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                Sin comprobante
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ── Contenido principal ───────────────────────────────────
                flex-row-reverse: el DOM mantiene productos primero (autofocus
                del buscador, orden de tabulación) pero visualmente el carrito
                queda a la IZQUIERDA y los productos a la derecha. */}
            <div className="flex flex-row-reverse flex-1 overflow-hidden relative">
                {/* ── Panel izquierdo: productos ──────────────────────── */}
                <div className="flex-1 flex flex-col overflow-hidden">
                    {/* Header de productos + buscador + categorías */}
                    <div className="px-3 sm:px-4 py-3 flex flex-col gap-2.5 flex-shrink-0" style={{ backgroundColor: 'var(--color-surface)', borderBottom: '1px solid var(--color-border)' }}>
                        <div className="flex items-center gap-2">
                            <Package size={16} style={{ color: 'var(--color-primary)' }} />
                            <span className="font-bold text-sm" style={{ color: 'var(--color-text)' }}>
                                Productos
                            </span>
                            <span
                                className="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                style={{
                                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                    color: 'var(--color-primary)',
                                }}
                            >
                                {productosFiltrados.length}
                            </span>
                            {/* Refrescar catálogo (sin perder el carrito): útil cuando
                                se crea un producto en otra pestaña. */}
                            <button
                                onClick={refrescarCatalogo}
                                disabled={refrescando}
                                title="Actualizar lista de productos"
                                aria-label="Actualizar lista de productos"
                                className="ml-auto flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-lg transition-colors hover:bg-black/5 active:bg-black/10 disabled:opacity-60"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                <RefreshCw size={13} className={refrescando ? 'animate-spin' : ''} />
                                <span className="hidden sm:inline">{refrescando ? 'Actualizando…' : 'Actualizar'}</span>
                            </button>
                        </div>

                        <div className="relative">
                            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                            <input
                                type="search"
                                inputMode="search"
                                enterKeyHint="search"
                                value={busqueda}
                                onChange={e => setBusqueda(e.target.value)}
                                onKeyDown={onBusquedaKeyDown}
                                placeholder="Buscar por nombre o código (Enter agrega)..."
                                autoFocus
                                autoComplete="off"
                                className="w-full pl-10 pr-3 py-2.5 text-sm border rounded-xl focus:outline-none focus:ring-2"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                    '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                                } as React.CSSProperties}
                            />
                            {busqueda && (
                                <button
                                    onClick={() => setBusqueda('')}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 p-0.5 rounded hover:bg-black/5"
                                    style={{ color: 'var(--color-text-muted)' }}
                                >
                                    <X size={14} />
                                </button>
                            )}
                        </div>

                        {/* Pestañas Productos / Servicios. Solo si hay servicios
                            que separar; con búsqueda activa se atenúan porque en
                            ese momento se busca en todo el catálogo. */}
                        {hayServicios && (
                            <div
                                className="flex gap-1 p-1 rounded-xl transition-opacity"
                                style={{ backgroundColor: 'var(--color-bg)', opacity: busqueda ? 0.5 : 1 }}
                            >
                                {([
                                    { val: null,        label: 'Todo',      icon: Layers },
                                    { val: 'producto',  label: 'Productos', icon: Package },
                                    { val: 'servicio',  label: 'Servicios', icon: Wrench },
                                ] as const).map(({ val, label, icon: Icono }) => (
                                    <button
                                        key={label}
                                        onClick={() => setTipoActivo(val)}
                                        className="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg transition-all"
                                        style={{
                                            backgroundColor: tipoActivo === val ? 'var(--color-surface)' : 'transparent',
                                            color: tipoActivo === val ? 'var(--color-primary)' : 'var(--color-text-muted)',
                                            boxShadow: tipoActivo === val ? '0 1px 3px rgba(0,0,0,0.08)' : 'none',
                                        }}
                                    >
                                        <Icono size={13} />
                                        {label}
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Chips de categorías */}
                        {categorias.length > 0 && (
                            <div className="flex gap-1.5 overflow-x-auto pb-0.5 scrollbar-hide">
                                <button
                                    onClick={() => setCategoriaActiva(null)}
                                    className="flex-shrink-0 text-xs font-medium px-3 py-1.5 rounded-full transition-all whitespace-nowrap"
                                    style={{
                                        backgroundColor: !categoriaActiva ? 'var(--color-primary)' : 'var(--color-bg)',
                                        color: !categoriaActiva ? '#fff' : 'var(--color-text-muted)',
                                        border: `1px solid ${!categoriaActiva ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                    }}
                                >
                                    <Layers size={11} className="inline mr-1" />
                                    Todos
                                </button>
                                {categorias.map(cat => (
                                    <button
                                        key={cat}
                                        onClick={() => setCategoriaActiva(cat === categoriaActiva ? null : cat)}
                                        className="flex-shrink-0 text-xs font-medium px-3 py-1.5 rounded-full transition-all whitespace-nowrap"
                                        style={{
                                            backgroundColor: categoriaActiva === cat ? 'var(--color-primary)' : 'var(--color-bg)',
                                            color: categoriaActiva === cat ? '#fff' : 'var(--color-text-muted)',
                                            border: `1px solid ${categoriaActiva === cat ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                        }}
                                    >
                                        {cat}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Grid de productos */}
                    <div
                        ref={gridRef}
                        className="flex-1 overflow-y-auto px-3 sm:px-4 py-3 pb-[calc(96px+env(safe-area-inset-bottom,0px))] lg:pb-3"
                        style={{ overscrollBehavior: 'contain' }}
                    >
                        {/* Card compacta horizontal: thumb 44px + nombre + precio.
                            auto-fill para que la densidad se adapte al ancho real. */}
                        <div className="grid gap-2" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(168px, 1fr))' }}>
                            {productosFiltrados.map(producto => {
                                const enCarrito = carrito.find(i => i.producto_id === producto.id);
                                return (
                                    <button
                                        key={producto.id}
                                        onClick={() => agregarProducto(producto)}
                                        onMouseEnter={e => mostrarTooltipSiCortado(e, producto)}
                                        onMouseLeave={() => setTooltipProd(null)}
                                        className="text-left p-2.5 rounded-xl border transition-all hover:shadow-md active:scale-[0.97] relative group flex flex-col gap-1.5"
                                        style={{
                                            backgroundColor: 'var(--color-surface)',
                                            borderColor: enCarrito ? 'var(--color-primary)' : 'var(--color-border)',
                                            boxShadow: enCarrito ? '0 0 0 1px var(--color-primary), 0 2px 8px rgba(26,115,200,0.1)' : '0 1px 3px rgba(0,0,0,0.05)',
                                        }}
                                    >
                                        {enCarrito && (
                                            <span
                                                className="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center text-white shadow-sm z-10"
                                                style={{ backgroundColor: 'var(--color-primary)' }}
                                            >
                                                {enCarrito.cantidad}
                                            </span>
                                        )}
                                        <div className="flex items-start gap-2 min-w-0">
                                            <ProductoThumbnail url={producto.imagen ?? null} alt={producto.nombre} />
                                            <div className="flex-1 min-w-0">
                                                <p data-nombre className="text-[13px] font-semibold leading-snug line-clamp-2" style={{ color: 'var(--color-text)' }}>
                                                    {producto.nombre}
                                                </p>
                                                {producto.codigo && (
                                                    <p className="text-[10px] font-mono mt-0.5 opacity-50 truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                        {producto.codigo}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between gap-1 mt-auto">
                                            <span className="text-[10px] font-medium truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                {producto.categoria?.nombre ?? 'General'}
                                            </span>
                                            {(() => {
                                                const unidadesActivas = (producto.unidades ?? []).filter(u => u.activo !== false);
                                                if (unidadesActivas.length > 1) {
                                                    const precios = unidadesActivas.map(u => parseFloat(u.precio_venta));
                                                    const min = Math.min(...precios);
                                                    return (
                                                        <span className="text-[13px] font-bold flex items-center gap-1 whitespace-nowrap" style={{ color: 'var(--color-primary)' }}>
                                                            <span className="text-[9px] font-medium opacity-70 uppercase tracking-wider">desde</span>
                                                            S/ {min.toFixed(2)}
                                                        </span>
                                                    );
                                                }
                                                return (
                                                    <span className="text-[13px] font-bold whitespace-nowrap" style={{ color: 'var(--color-primary)' }}>
                                                        S/ {parseFloat(
                                                            producto.unidad_base?.precio_venta
                                                            ?? producto.unidades?.find(u => u.es_base)?.precio_venta
                                                            ?? producto.precio_venta
                                                        ).toFixed(2)}
                                                    </span>
                                                );
                                            })()}
                                        </div>
                                    </button>
                                );
                            })}
                            {productosFiltrados.length === 0 && (
                                <div className="col-span-full flex flex-col items-center justify-center py-16 gap-3" style={{ color: 'var(--color-text-muted)' }}>
                                    <Package size={48} className="opacity-20" />
                                    <p className="text-sm">No se encontraron productos</p>
                                    {busqueda && (
                                        <button
                                            onClick={() => { setBusqueda(''); setCategoriaActiva(null); }}
                                            className="text-xs underline"
                                            style={{ color: 'var(--color-primary)' }}
                                        >
                                            Limpiar filtros
                                        </button>
                                    )}
                                </div>
                            )}
                            {/* Sentinel + loader para scroll infinito. */}
                            {(hasMoreProductos || cargandoProductos) && productosFiltrados.length > 0 && (
                                <div
                                    ref={sentinelRef}
                                    className="col-span-full flex items-center justify-center py-4"
                                    style={{ color: 'var(--color-text-muted)' }}
                                >
                                    {cargandoProductos && <RefreshCw size={16} className="animate-spin" />}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Comprobante en móvil (debajo de productos) */}
                    <div className="sm:hidden px-3 py-2 flex-shrink-0" style={{ borderTop: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                        <div className="flex items-center gap-2">
                            <Receipt size={14} style={{ color: 'var(--color-text-muted)' }} />
                            <select
                                value={tipoComprobante}
                                onChange={e => setTipoComprobante(e.target.value as TipoComprobante)}
                                className="flex-1 text-xs border rounded-lg px-2 py-1.5"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                            >
                                <option value="ticket">Sin comprobante</option>
                                {feActiva ? (
                                    <>
                                        <option value="boleta">Boleta</option>
                                        <option value="factura">Factura</option>
                                    </>
                                ) : (
                                    <>
                                        <option value="boleta_externa">Boleta electrónica externa</option>
                                        <option value="factura_externa">Factura electrónica externa</option>
                                    </>
                                )}
                            </select>
                            {/* Misma pista que en la barra superior (misma lógica, sin duplicar). */}
                            <PistaComprobante
                                visible={emiteCPE}
                                serie={serieCPE}
                                bloqueo={bloqueoComprobante}
                            />
                        </div>
                        {esComprobanteExterno && (
                            <input
                                type="text"
                                value={numeroComprobante}
                                onChange={e => setNumeroComprobante(e.target.value.toUpperCase())}
                                placeholder="N° comprobante (opcional)"
                                maxLength={30}
                                className="mt-1.5 w-full text-xs border rounded-lg px-2 py-1.5"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                            />
                        )}
                        {emiteCPE && bloqueoComprobante && (
                            <button
                                onClick={() => bloqueoComprobante.requiereCliente ? setModalCliente(true) : setTipoComprobante('ticket')}
                                className="mt-1.5 w-full text-left text-[11px] font-medium leading-tight underline"
                                style={{ color: 'var(--color-danger)' }}
                            >
                                {bloqueoComprobante.motivo}
                            </button>
                        )}
                    </div>
                </div>

                {/* ── Separador vertical (desktop) ───────────────────── */}
                <div className="w-px flex-shrink-0 hidden lg:block" style={{ backgroundColor: 'var(--color-border)' }} />

                {/* ── Panel izquierdo: carrito y cobro (desktop) ─────── */}
                <div
                    className="hidden lg:flex w-[420px] xl:w-[470px] 2xl:w-[510px] flex-col overflow-hidden flex-shrink-0"
                    style={{ backgroundColor: 'var(--color-bg)' }}
                >
                    <CarritoPanel
                        carrito={carrito}
                        pagos={pagos}
                        conceptosDescuento={conceptosDescuento}
                        historial={historialCliente}
                        metodosPago={metodosPago}
                        cliente={cliente}
                        onAbrirCliente={() => setModalCliente(true)}
                        descuentoTotal={descuentoTotal}
                        descuentoConceptoId={descuentoConceptoId}
                        subtotal={subtotal}
                        igv={igv}
                        baseGravada={baseGravada}
                        baseExonerada={baseExonerada}
                        total={total}
                        tasaIgv={tasaIgv}
                        inactivosCount={itemsInactivos.length}
                        onCambiarCantidad={cambiarCantidad}
                        onEstablecerCantidad={establecerCantidad}
                        onCambiarPrecio={cambiarPrecio}
                        onAplicarDescuentoItem={aplicarDescuentoItem}
                        onEliminarItem={eliminarItem}
                        onLimpiarCarrito={limpiarCarrito}
                        onSetDescuento={(d, cid) => { setDescuentoTotal(d); setDescuentoConceptoId(cid); }}
                        onSetPagos={setPagos}
                        onConfirmar={confirmarVenta}
                        puedeVender={puedeVender}
                        razonNoVender={razonNoVender}
                        bloqueoComprobante={bloqueoComprobante}
                        esCredito={esCredito}
                        fechaVencimiento={fechaVencimiento}
                        onSetEsCredito={activarCredito}
                        onSetFechaVencimiento={setFechaVencimiento}
                        anticipoSeleccionado={anticipoSeleccionado}
                        montoAnticipoUsado={montoAnticipoUsado}
                        {...propsPendiente}
                    />
                </div>

                {/* ── Drawer del carrito (móvil/tablet) ────────────────
                    En móvil: bottom-sheet (slide up) con drag handle.
                    En tablet: side drawer derecho. Ambos con safe-area. */}
                {carritoAbierto && (
                    <div className="lg:hidden fixed inset-0 z-50 flex">
                        <div
                            className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm animate-fade-in"
                            onClick={() => setCarritoAbierto(false)}
                        />
                        <div
                            className="
                                relative flex flex-col overflow-hidden
                                w-full bottom-sheet
                                mt-auto rounded-t-3xl
                                md:mt-0 md:ml-auto md:rounded-t-none md:rounded-l-3xl md:max-w-md md:h-full md:side-drawer
                            "
                            style={{
                                backgroundColor: 'var(--color-bg)',
                                maxHeight: '92dvh',
                                boxShadow: '0 -20px 50px -10px rgba(15,23,42,0.25)',
                            }}
                        >
                            {/* Drag handle (móvil) + Cerrar (tablet) */}
                            <div className="relative flex items-center justify-center flex-shrink-0 pt-2 pb-1 md:hidden">
                                <span
                                    className="block h-1.5 w-10 rounded-full"
                                    style={{ backgroundColor: 'var(--color-border)' }}
                                />
                            </div>
                            <button
                                onClick={() => setCarritoAbierto(false)}
                                aria-label="Cerrar carrito"
                                className="hidden md:flex absolute top-3 left-3 items-center justify-center w-10 h-10 rounded-lg z-10 hover:bg-black/5 transition-colors"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                <X size={20} />
                            </button>
                            <CarritoPanel
                                carrito={carrito}
                                pagos={pagos}
                                conceptosDescuento={conceptosDescuento}
                                historial={historialCliente}
                                metodosPago={metodosPago}
                                cliente={cliente}
                                onAbrirCliente={() => setModalCliente(true)}
                                descuentoTotal={descuentoTotal}
                                descuentoConceptoId={descuentoConceptoId}
                                subtotal={subtotal}
                                igv={igv}
                        baseGravada={baseGravada}
                        baseExonerada={baseExonerada}
                                total={total}
                                tasaIgv={tasaIgv}
                                inactivosCount={itemsInactivos.length}
                                onCambiarCantidad={cambiarCantidad}
                                onEstablecerCantidad={establecerCantidad}
                                onCambiarPrecio={cambiarPrecio}
                                onAplicarDescuentoItem={aplicarDescuentoItem}
                                onEliminarItem={eliminarItem}
                                onLimpiarCarrito={limpiarCarrito}
                                onSetDescuento={(d, cid) => { setDescuentoTotal(d); setDescuentoConceptoId(cid); }}
                                onSetPagos={setPagos}
                                onConfirmar={confirmarVenta}
                                puedeVender={puedeVender}
                                razonNoVender={razonNoVender}
                                bloqueoComprobante={bloqueoComprobante}
                                esCredito={esCredito}
                                fechaVencimiento={fechaVencimiento}
                                onSetEsCredito={activarCredito}
                                onSetFechaVencimiento={setFechaVencimiento}
                                anticipoSeleccionado={anticipoSeleccionado}
                                montoAnticipoUsado={montoAnticipoUsado}
                                {...propsPendiente}
                            />
                        </div>
                    </div>
                )}

                {/* ── Barra de acción inferior (móvil/tablet) ────────
                    Se mantiene siempre visible para que el pulgar la
                    alcance sin estirar la mano. Reemplaza al FAB de esquina. */}
                {!carritoAbierto && (
                    <button
                        onClick={() => setCarritoAbierto(true)}
                        className="lg:hidden fixed left-3 right-3 z-40 flex items-center justify-between gap-3 px-4 py-3.5 rounded-2xl shadow-2xl transition-all active:scale-[0.98]"
                        style={{
                            bottom: 'calc(env(safe-area-inset-bottom, 0px) + 12px)',
                            backgroundColor: 'var(--color-primary)',
                            color: '#fff',
                            boxShadow: '0 10px 40px -10px rgba(15,23,42,0.45), 0 4px 12px rgba(15,23,42,0.15)',
                        }}
                    >
                        <div className="flex items-center gap-3 min-w-0">
                            <div className="relative flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/15">
                                <ShoppingCart size={18} />
                                {cantidadItems > 0 && (
                                    <span
                                        className="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-white text-[10px] font-bold flex items-center justify-center"
                                        style={{ color: 'var(--color-primary)' }}
                                    >
                                        {cantidadItems}
                                    </span>
                                )}
                            </div>
                            <div className="flex flex-col items-start leading-tight min-w-0">
                                <span className="text-[10px] font-medium uppercase tracking-wider opacity-80">
                                    {cantidadItems === 0 ? 'Carrito vacío' : `${cantidadItems} ${cantidadItems === 1 ? 'item' : 'items'} · Tocar para cobrar`}
                                </span>
                                <span className="text-base font-bold">S/ {total.toFixed(2)}</span>
                            </div>
                        </div>
                        <ChevronUp size={20} className="flex-shrink-0 opacity-80" />
                    </button>
                )}
            </div>

            {/* Modales */}
            <ModalClienteRapido
                isOpen={modalCliente}
                onClose={() => setModalCliente(false)}
                selected={cliente}
                onSelect={setCliente}
                onCrearNuevo={() => { setModalCliente(false); setModalCrearCliente(true); }}
            />

            <ModalCrearCliente
                isOpen={modalCrearCliente}
                // Cancelar/cerrar vuelve al selector de clientes.
                onClose={() => { setModalCrearCliente(false); setModalCliente(true); }}
                onCreated={c => {
                    setCliente(c);
                    setModalCrearCliente(false);
                    toast.success(`Cliente "${c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim()}" seleccionado para la venta.`);
                }}
            />

            <ModalConfirmacionVenta
                isOpen={modalConfirm}
                onClose={() => setModalConfirm(false)}
                onConfirmar={submitVenta}
                loading={loading}
                items={carrito}
                pagos={pagos}
                cliente={cliente}
                descuentoTotal={descuentoTotal}
                descuentoConceptoId={descuentoConceptoId}
                tipoComprobante={tipoComprobante}
                numeroComprobante={numeroComprobante}
                subtotal={subtotal}
                igv={igv}
                total={total}
                metodosPago={metodosPago}
                conceptos={conceptosDescuento}
                entregaPendiente={entregaPendiente}
                pendienteDe={pendienteDe}
                fechaEntrega={fechaEntrega}
                despachoAlmacen={despachoAlmacen}
                anticipoMonto={montoAnticipoUsado}
            />

            <ModalSelectorPresentacion
                isOpen={productoEnSeleccion !== null}
                onClose={() => setProductoEnSeleccion(null)}
                producto={productoEnSeleccion}
                onElegir={agregarConPresentacion}
            />

            {/* Tooltip de producto con nombre cortado (imagen + nombre completo).
                Posicion fija: no lo recorta el scroll del grid. Se abre arriba de
                la tarjeta salvo que esté muy cerca del borde superior. */}
            {tooltipProd && (
                <div
                    className="fixed z-[60] pointer-events-none"
                    style={{
                        left: tooltipProd.left,
                        top: tooltipProd.top < 150 ? tooltipProd.bottom + 8 : tooltipProd.top - 8,
                        transform: tooltipProd.top < 150 ? 'translateX(-50%)' : 'translate(-50%, -100%)',
                    }}
                >
                    <div
                        className="flex items-center gap-2.5 rounded-xl border p-2.5"
                        style={{
                            width: 244,
                            backgroundColor: 'var(--color-surface)',
                            borderColor: 'var(--color-border)',
                            boxShadow: '0 12px 32px -8px rgba(15,23,42,0.35)',
                        }}
                    >
                        <div
                            className="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center"
                            style={{ backgroundColor: 'var(--color-bg)' }}
                        >
                            {tooltipProd.producto.imagen ? (
                                <img src={tooltipProd.producto.imagen} alt="" className="w-full h-full object-cover" />
                            ) : (
                                <ImageIcon size={22} style={{ color: 'var(--color-text-muted)', opacity: 0.35 }} />
                            )}
                        </div>
                        <div className="min-w-0">
                            <p className="text-[13px] font-semibold leading-snug" style={{ color: 'var(--color-text)' }}>
                                {tooltipProd.producto.nombre}
                            </p>
                            <p className="text-[11px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                {tooltipProd.producto.categoria?.nombre ?? 'General'}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* CSS para animaciones del drawer */}
            <style>{`
                @keyframes slideUp {
                    from { transform: translateY(100%); }
                    to { transform: translateY(0); }
                }
                @keyframes slideInRight {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                .bottom-sheet {
                    animation: slideUp 0.32s cubic-bezier(0.32, 0.72, 0.0, 1);
                }
                @media (min-width: 768px) {
                    .side-drawer {
                        animation: slideInRight 0.32s cubic-bezier(0.32, 0.72, 0.0, 1);
                    }
                }
                .animate-fade-in {
                    animation: fadeIn 0.25s ease-out;
                }
                .scrollbar-hide::-webkit-scrollbar { display: none; }
                .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
                .line-clamp-2 {
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
            `}</style>
        </PosLayout>
    );
}

/* ─── Componente interno: Panel del carrito ─────────────────────────────────── */

interface CarritoPanelProps {
    carrito: LineaCarrito[];
    pagos: LineaPago[];
    conceptosDescuento: DescuentoConcepto[];
    historial: Record<number, HistorialPrecioCliente>;
    metodosPago: (MetodoPago & { cuentas?: any[] })[];
    cliente: Cliente | null;
    onAbrirCliente: () => void;
    descuentoTotal: number;
    descuentoConceptoId: number | null;
    subtotal: number;
    igv: number;
    total: number;
    baseGravada: number;
    baseExonerada: number;
    tasaIgv: number;
    inactivosCount: number;
    onCambiarCantidad: (key: string, delta: number) => void;
    onEstablecerCantidad: (key: string, cantidad: number) => void;
    onCambiarPrecio: (key: string, precio: number) => void;
    onAplicarDescuentoItem: (key: string, valor: number, modo: DescModo, tipo: DescTipo, cid: number | null) => void;
    onEliminarItem: (key: string) => void;
    onLimpiarCarrito: () => void;
    onSetDescuento: (d: number, cid: number | null) => void;
    onSetPagos: (pagos: LineaPago[]) => void;
    onConfirmar: () => void;
    // A14: bandera de bloqueo del POS (admin sin local, almacén desactivado, etc.)
    puedeVender: boolean;
    razonNoVender: string | null;
    // V10: motivo por el que el comprobante elegido no se puede emitir (null =
    // no hay problema; siempre null para ventas `ticket`).
    bloqueoComprobante: BloqueoComprobante | null;
    // F1 — Venta a crédito
    esCredito: boolean;
    fechaVencimiento: string;
    onSetEsCredito: (v: boolean) => void;
    onSetFechaVencimiento: (v: string) => void;
    // Pendiente por entregar (pagado pero se lleva solo parte)
    permitirPendiente: boolean;
    entregaPendiente: boolean;
    despachoAlmacen: boolean;
    fechaEntrega: string;
    pendienteDe: (item: LineaCarrito) => number;
    totalPendientes: number;
    onSetEntregaPendiente: (v: boolean) => void;
    onSetDespachoAlmacen: (v: boolean) => void;
    onSetFechaEntrega: (v: string) => void;
    onSetPendiente: (key: string, v: number) => void;
    usaDespachoAlmacen: boolean;
    // Autofoco del precio en líneas recién agregadas con precio base 0.
    nuevaLineaPrecioKey: string | null;
    onAutoFocusPrecio: () => void;
    // Anticipo de efectivo aplicado a la venta.
    anticipoSeleccionado: number | null;
    montoAnticipoUsado: number;
}

function CarritoPanel({
    carrito, pagos, conceptosDescuento, historial, metodosPago,
    cliente, onAbrirCliente,
    descuentoTotal, descuentoConceptoId,
    subtotal, igv, total, baseGravada, baseExonerada, tasaIgv, inactivosCount,
    onCambiarCantidad, onEstablecerCantidad, onCambiarPrecio, onAplicarDescuentoItem, onEliminarItem,
    onLimpiarCarrito, onSetDescuento, onSetPagos, onConfirmar,
    puedeVender, razonNoVender, bloqueoComprobante,
    esCredito, fechaVencimiento, onSetEsCredito, onSetFechaVencimiento,
    permitirPendiente, entregaPendiente, despachoAlmacen, fechaEntrega, pendienteDe, totalPendientes,
    onSetEntregaPendiente, onSetDespachoAlmacen, onSetFechaEntrega, onSetPendiente,
    usaDespachoAlmacen,
    nuevaLineaPrecioKey, onAutoFocusPrecio,
    anticipoSeleccionado, montoAnticipoUsado,
}: CarritoPanelProps) {
    const hayInactivos = inactivosCount > 0;

    const clienteNombre = cliente
        ? (cliente.razon_social ?? `${cliente.nombres} ${cliente.apellidos ?? ''}`.trim())
        : 'Cliente general';
    const clienteDoc = cliente?.numero_documento
        ? `${cliente.tipo_documento ?? 'DOC'}: ${cliente.numero_documento}`
        : null;
    const clienteInicial = (clienteNombre || 'C').charAt(0).toUpperCase();
    const esClienteGeneral = !cliente
        || (cliente as Cliente & { es_cliente_general?: boolean }).es_cliente_general
        || cliente.numero_documento === '99999999';

    return (
        <>
            {/* Cabecera carrito */}
            <div
                className="flex items-center justify-between px-4 py-3 flex-shrink-0"
                style={{ borderBottom: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
            >
                <div className="flex items-center gap-2">
                    <ShoppingCart size={16} style={{ color: 'var(--color-primary)' }} />
                    <span className="font-bold text-sm" style={{ color: 'var(--color-text)' }}>
                        Carrito
                    </span>
                    {carrito.length > 0 && (
                        <span
                            className="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                color: 'var(--color-primary)',
                            }}
                        >
                            {carrito.reduce((s, i) => s + i.cantidad, 0)} items
                        </span>
                    )}
                </div>
                {carrito.length > 0 && (
                    <button
                        onClick={onLimpiarCarrito}
                        className="text-xs font-medium px-2 py-1 rounded-lg transition-colors hover:bg-red-50"
                        style={{ color: 'var(--color-danger)' }}
                    >
                        Limpiar
                    </button>
                )}
            </div>

            {/* Cliente activo — siempre visible mientras se revisa el carrito.
                Tappable para cambiarlo. Se distingue visualmente entre Cliente
                general (neutro) y un cliente identificado (acento primary). */}
            <button
                onClick={onAbrirCliente}
                className="flex items-center gap-3 px-4 py-2.5 w-full text-left flex-shrink-0 transition-colors hover:bg-black/5 active:bg-black/10"
                style={{
                    borderBottom: '1px solid var(--color-border)',
                    backgroundColor: 'var(--color-surface)',
                }}
            >
                <div
                    className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-white text-sm font-bold"
                    style={{
                        backgroundColor: esClienteGeneral ? 'var(--color-text-muted)' : 'var(--color-primary)',
                    }}
                >
                    {esClienteGeneral ? <User size={16} /> : clienteInicial}
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                        Facturando a
                    </p>
                    <p className="text-sm font-semibold truncate leading-tight" style={{ color: 'var(--color-text)' }}>
                        {clienteNombre}
                    </p>
                    {clienteDoc && (
                        <p className="text-[11px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                            {clienteDoc}
                        </p>
                    )}
                </div>
                <ChevronDown size={14} className="flex-shrink-0" style={{ color: 'var(--color-text-muted)' }} />
            </button>

            {/* ── Zona de scroll única ─────────────────────────────────
                Items + descuento + crédito + pagos + desglose comparten UN
                solo scroll: nada aplasta a nada. Abajo queda fijo solo lo
                esencial (estado de pago, TOTAL y Cobrar), siempre visible
                sin importar cuántos items o métodos de pago haya. */}
            <div className="flex-1 overflow-y-auto px-3 py-2 flex flex-col gap-3">
                {/* Banner persistente: items inactivos en la cita prellenada.
                    Se mantiene visible mientras el cajero no resuelva los ítems
                    (eliminándolos o pidiendo al admin reactivar el catálogo). */}
                {hayInactivos && (
                    <div
                        className="rounded-lg p-3 flex items-start gap-2"
                        style={{
                            backgroundColor: 'rgba(239,68,68,0.10)',
                            border: '1px solid var(--color-danger)',
                        }}
                    >
                        <AlertTriangle size={16} className="flex-shrink-0 mt-0.5" style={{ color: 'var(--color-danger)' }} />
                        <div className="text-xs leading-tight" style={{ color: 'var(--color-danger)' }}>
                            <p className="font-bold mb-0.5">
                                {inactivosCount} ítem(s) inactivo(s) en este carrito
                            </p>
                            <p className="opacity-90">
                                No podrás cobrar hasta que los elimines del carrito o pidas al administrador reactivar el producto/presentación.
                            </p>
                        </div>
                    </div>
                )}

                {/* Lista de items */}
                {carrito.length === 0 ? (
                    <div className="flex flex-col items-center justify-center flex-1 gap-3 py-10" style={{ color: 'var(--color-text-muted)' }}>
                        <ShoppingCart size={48} className="opacity-15" />
                        <div className="text-center">
                            <p className="text-sm font-medium">Carrito vacío</p>
                            <p className="text-xs mt-0.5 opacity-70">Toca un producto para agregarlo</p>
                        </div>
                    </div>
                ) : (
                    <div>
                        {carrito.map(item => (
                            <CarritoItem
                                key={item.key}
                                item={item}
                                conceptos={conceptosDescuento}
                                historial={historial[item.producto_id]}
                                autoFocusPrecio={nuevaLineaPrecioKey === item.key}
                                onAutoFocusPrecio={onAutoFocusPrecio}
                                onCantidad={onCambiarCantidad}
                                onCantidadExacta={onEstablecerCantidad}
                                onPrecio={onCambiarPrecio}
                                onDescuento={onAplicarDescuentoItem}
                                onEliminar={onEliminarItem}
                            />
                        ))}
                    </div>
                )}

                {carrito.length > 0 && (
                    <>
                        {/* Descuento global */}
                        <PanelDescuento
                            descuentoTotal={descuentoTotal}
                            descuentoConceptoId={descuentoConceptoId}
                            base={subtotal}
                            conceptos={conceptosDescuento}
                            onChange={onSetDescuento}
                        />

                        {/* F1 — Venta a crédito */}
                        <div
                            className="rounded-xl px-3 py-2.5"
                            style={{
                                border: `1px solid ${esCredito ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                backgroundColor: esCredito
                                    ? 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))'
                                    : 'var(--color-surface)',
                            }}
                        >
                            <label className="flex items-center justify-between gap-2 cursor-pointer select-none">
                                <span className="flex items-center gap-2 text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                    <CreditCard size={15} style={{ color: esCredito ? 'var(--color-primary)' : 'var(--color-text-muted)' }} />
                                    Venta a crédito
                                </span>
                                <input
                                    type="checkbox"
                                    checked={esCredito}
                                    onChange={e => onSetEsCredito(e.target.checked)}
                                    className="h-4 w-4 accent-[var(--color-primary)]"
                                />
                            </label>
                            {esCredito && (
                                <div className="mt-2 space-y-1.5">
                                    {esClienteGeneral && (
                                        <p className="text-[11px] font-medium" style={{ color: 'var(--color-danger)' }}>
                                            Selecciona un cliente identificado para vender a crédito.
                                        </p>
                                    )}
                                    <div className="flex items-center gap-2">
                                        <span className="text-[11px] flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>
                                            Vence (opcional)
                                        </span>
                                        <input
                                            type="date"
                                            value={fechaVencimiento}
                                            onChange={e => onSetFechaVencimiento(e.target.value)}
                                            className="flex-1 text-xs rounded-lg px-2 py-1.5 border outline-none"
                                            style={{
                                                borderColor: 'var(--color-border)',
                                                backgroundColor: 'var(--color-bg)',
                                                color: 'var(--color-text)',
                                            }}
                                        />
                                    </div>
                                    <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                        El pago inicial es opcional; el saldo queda como cuenta por cobrar.
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Pendiente por entregar: pagó todo, se lleva solo parte.
                            El POS crea el anticipo material en Finanzas solo;
                            el stock pendiente sale recién al entregarse. */}
                        {permitirPendiente && (
                            <div
                                className="rounded-xl px-3 py-2.5"
                                style={{
                                    border: `1px solid ${entregaPendiente ? 'var(--color-warning)' : 'var(--color-border)'}`,
                                    backgroundColor: entregaPendiente
                                        ? 'color-mix(in srgb, var(--color-warning) 8%, var(--color-bg))'
                                        : 'var(--color-surface)',
                                }}
                            >
                                <label className="flex items-center justify-between gap-2 cursor-pointer select-none">
                                    <span className="flex items-center gap-2 text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                        <Truck size={15} style={{ color: entregaPendiente ? 'var(--color-warning)' : 'var(--color-text-muted)' }} />
                                        Pendiente por entregar
                                    </span>
                                    <input
                                        type="checkbox"
                                        checked={entregaPendiente}
                                        onChange={e => onSetEntregaPendiente(e.target.checked)}
                                        className="h-4 w-4 accent-[var(--color-warning)]"
                                    />
                                </label>
                                {entregaPendiente && (
                                    <div className="mt-2 space-y-2">
                                        {esClienteGeneral && (
                                            <p className="text-[11px] font-medium" style={{ color: 'var(--color-danger)' }}>
                                                Selecciona un cliente identificado para dejar mercadería pendiente.
                                            </p>
                                        )}
                                        <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                            Indica cuánto <strong>se lleva ahora</strong> de cada producto; el resto queda pendiente y se registra solo en Finanzas → Anticipos.
                                        </p>
                                        <div className="space-y-1.5">
                                            {carrito.map(item => {
                                                const pendiente = pendienteDe(item);
                                                const llevado   = Math.round((item.cantidad - pendiente) * 10000) / 10000;
                                                return (
                                                    <div key={item.key} className="flex items-center gap-2 text-xs">
                                                        <span className="flex-1 min-w-0 truncate" style={{ color: 'var(--color-text)' }}>
                                                            {item.producto_nombre}
                                                        </span>
                                                        <span className="flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>lleva</span>
                                                        <input
                                                            type="number"
                                                            min={0}
                                                            max={item.cantidad}
                                                            step="any"
                                                            value={llevado}
                                                            onChange={e => {
                                                                const l = parseFloat(e.target.value);
                                                                const llevaAhora = isNaN(l) ? 0 : Math.min(Math.max(0, l), item.cantidad);
                                                                onSetPendiente(item.key, Math.round((item.cantidad - llevaAhora) * 10000) / 10000);
                                                            }}
                                                            className="w-16 text-xs text-right rounded-lg px-1.5 py-1 border outline-none flex-shrink-0"
                                                            style={{
                                                                borderColor: pendiente > 0 ? 'var(--color-warning)' : 'var(--color-border)',
                                                                backgroundColor: 'var(--color-bg)',
                                                                color: 'var(--color-text)',
                                                            }}
                                                        />
                                                        <span className="flex-shrink-0 w-24 text-right font-medium"
                                                            style={{ color: pendiente > 0 ? 'var(--color-warning)' : 'var(--color-text-muted)' }}>
                                                            {pendiente > 0 ? `queda ${pendiente}` : 'completo'}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-[11px] flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>
                                                Entrega estimada (opcional)
                                            </span>
                                            <input
                                                type="date"
                                                value={fechaEntrega}
                                                onChange={e => onSetFechaEntrega(e.target.value)}
                                                className="flex-1 text-xs rounded-lg px-2 py-1.5 border outline-none"
                                                style={{
                                                    borderColor: 'var(--color-border)',
                                                    backgroundColor: 'var(--color-bg)',
                                                    color: 'var(--color-text)',
                                                }}
                                            />
                                        </div>
                                        {totalPendientes > 0 ? (
                                            <p className="text-[11px] font-medium" style={{ color: 'var(--color-warning)' }}>
                                                {totalPendientes} und quedarán pendientes por entregar (no salen del stock hasta entregarse).
                                            </p>
                                        ) : (
                                            <p className="text-[11px]" style={{ color: 'var(--color-danger)' }}>
                                                Aún no marcaste nada como pendiente: reduce lo que "lleva" en algún producto.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Despacho en almacén: toda la venta queda pendiente de
                            entrega. El almacenero la confirma luego y recién ahí
                            descuenta el stock. */}
                        {usaDespachoAlmacen && (
                            <div
                                className="rounded-xl px-3 py-2.5"
                                style={{
                                    border: `1px solid ${despachoAlmacen ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                    backgroundColor: despachoAlmacen
                                        ? 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))'
                                        : 'var(--color-surface)',
                                }}
                            >
                                <label className="flex items-center justify-between gap-2 cursor-pointer select-none">
                                    <span className="flex items-center gap-2 text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                        <Package size={15} style={{ color: despachoAlmacen ? 'var(--color-primary)' : 'var(--color-text-muted)' }} />
                                        Despacho en almacén
                                    </span>
                                    <input
                                        type="checkbox"
                                        checked={despachoAlmacen}
                                        onChange={e => onSetDespachoAlmacen(e.target.checked)}
                                        className="h-4 w-4 accent-[var(--color-primary)]"
                                    />
                                </label>
                                {despachoAlmacen && (
                                    <div className="mt-2 space-y-2">
                                        {esClienteGeneral && (
                                            <p className="text-[11px] font-medium" style={{ color: 'var(--color-danger)' }}>
                                                Selecciona un cliente identificado para dejar mercadería en almacén.
                                            </p>
                                        )}
                                        <p className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>
                                            Toda la mercadería quedará pendiente de despacho. El almacenero la verá en su bandeja y confirmará la entrega; el stock saldrá del almacén en ese momento.
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <span className="text-[11px] flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>
                                                Entrega estimada (opcional)
                                            </span>
                                            <input
                                                type="date"
                                                value={fechaEntrega}
                                                onChange={e => onSetFechaEntrega(e.target.value)}
                                                className="flex-1 text-xs rounded-lg px-2 py-1.5 border outline-none"
                                                style={{
                                                    borderColor: 'var(--color-border)',
                                                    backgroundColor: 'var(--color-bg)',
                                                    color: 'var(--color-text)',
                                                }}
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Pagos */}
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-wider mb-1.5 px-1" style={{ color: 'var(--color-text-muted)' }}>
                                {esCredito ? 'Pago inicial (opcional)' : 'Métodos de pago'}
                            </p>
                            <PanelPago
                                pagos={pagos}
                                metodosPago={metodosPago}
                                total={total}
                                anticipoMonto={montoAnticipoUsado}
                                onChange={onSetPagos}
                            />
                        </div>

                        {/* ── Desglose ────────────────────────────────────────────
                            Las líneas SUMAN el total: gravado + exonerado + IGV.

                            Antes la primera línea decía «Subtotal» y mostraba el
                            importe BRUTO (los precios del catálogo llevan el IGV
                            dentro), con el IGV debajo. Leído en columna parecía una
                            suma: «Subtotal 100.00 / IGV 15.25» daba a entender 115.25
                            cuando lo que se iba a cobrar eran 100.00.

                            Ahora se muestran las bases NETAS, que es además el mismo
                            desglose que va impreso en el comprobante, así que la
                            cajera puede cotejar pantalla y papel sin traducir nada. */}
                        <div className="space-y-1 px-1 pb-1">
                            {descuentoTotal > 0 && (
                                <div className="flex justify-between text-xs">
                                    <span style={{ color: 'var(--color-text-muted)' }}>Descuento aplicado</span>
                                    <span className="font-medium" style={{ color: 'var(--color-danger)' }}>-S/ {descuentoTotal.toFixed(2)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                <span>Op. gravada</span>
                                <span className="font-medium" style={{ color: 'var(--color-text)' }}>S/ {baseGravada.toFixed(2)}</span>
                            </div>
                            {baseExonerada > 0 && (
                                <div className="flex justify-between text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                    <span>Op. exonerada</span>
                                    <span className="font-medium" style={{ color: 'var(--color-text)' }}>S/ {baseExonerada.toFixed(2)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                <span>IGV ({tasaIgv.toFixed(tasaIgv % 1 === 0 ? 0 : 2)}%)</span>
                                <span className="font-medium" style={{ color: 'var(--color-text)' }}>S/ {igv.toFixed(2)}</span>
                            </div>
                        </div>
                    </>
                )}
            </div>

            {/* ── Pie FIJO: estado de pago + TOTAL + Cobrar ──────────── */}
            <div
                className="flex-shrink-0 px-3 pt-2.5 flex flex-col gap-2"
                style={{
                    borderTop: '2px solid var(--color-border)',
                    backgroundColor: 'var(--color-surface)',
                    paddingBottom: 'calc(10px + env(safe-area-inset-bottom, 0px))',
                }}
            >
                {/* Estado del pago SIEMPRE visible: el cajero ve cuánto falta
                    o cuánto es el vuelto sin buscar entre las líneas de pago. */}
                {carrito.length > 0 && (pagos.length > 0 || esCredito || anticipoSeleccionado) && (() => {
                    const totalPagadoMetodos = pagos.reduce((s, p) => s + p.monto, 0);
                    const totalPagado = totalPagadoMetodos + montoAnticipoUsado;
                    const falta  = Math.max(0, total - totalPagado);
                    const vuelto = pagos.some(p => p.admite_vuelto) ? Math.max(0, totalPagado - total) : 0;
                    return (
                        <div className="flex items-center justify-between gap-2 text-xs px-1">
                            <span style={{ color: 'var(--color-text-muted)' }}>
                                {esCredito ? 'Pago inicial' : 'Pagado'}{' '}
                                <span className="font-bold" style={{ color: 'var(--color-text)' }}>S/ {totalPagado.toFixed(2)}</span>
                                {anticipoSeleccionado && (
                                    <span style={{ color: 'var(--color-warning)' }}>
                                        {' '}<span className="font-semibold">(anticipo S/ {montoAnticipoUsado.toFixed(2)})</span>
                                    </span>
                                )}
                            </span>
                            {esCredito ? (
                                <span className="font-bold" style={{ color: 'var(--color-primary)' }}>
                                    Saldo a crédito S/ {falta.toFixed(2)}
                                </span>
                            ) : falta > 0.009 ? (
                                <span className="font-bold" style={{ color: 'var(--color-danger)' }}>
                                    Falta S/ {falta.toFixed(2)}
                                </span>
                            ) : vuelto > 0.009 ? (
                                <span className="font-bold" style={{ color: 'var(--color-success)' }}>
                                    Vuelto S/ {vuelto.toFixed(2)}
                                </span>
                            ) : (
                                <span className="font-bold" style={{ color: 'var(--color-success)' }}>
                                    Cubierto ✓
                                </span>
                            )}
                        </div>
                    );
                })()}

                {/* Total grande */}
                <div
                    className="flex items-center justify-between px-4 py-2.5 rounded-xl font-bold text-lg"
                    style={{
                        background: 'linear-gradient(135deg, var(--color-primary), var(--color-primary-hover))',
                        color: '#fff',
                        boxShadow: '0 4px 15px rgba(26,115,200,0.25)',
                    }}
                >
                    <span>TOTAL</span>
                    <span>S/ {total.toFixed(2)}</span>
                </div>

                {/* A14: banner rojo cuando el backend dice que no puede vender */}
                {!puedeVender && razonNoVender && (
                    <div
                        className="flex items-start gap-2 px-3 py-2 rounded-lg text-sm font-medium border"
                        style={{
                            background: '#fef2f2',
                            color: '#991b1b',
                            borderColor: '#fecaca',
                        }}
                    >
                        <AlertTriangle size={16} className="flex-shrink-0 mt-0.5" />
                        <span>{razonNoVender}</span>
                    </div>
                )}

                {/* V10: el comprobante elegido no se puede emitir con estos datos.
                    Se avisa ACÁ (antes de cobrar) y no después de emitir mal. */}
                {bloqueoComprobante && (
                    <div
                        className="flex items-start gap-2 px-3 py-2 rounded-lg text-sm font-medium border"
                        style={{
                            background: '#fef2f2',
                            color: '#991b1b',
                            borderColor: '#fecaca',
                        }}
                    >
                        <AlertTriangle size={16} className="flex-shrink-0 mt-0.5" />
                        <div className="min-w-0">
                            <p>{bloqueoComprobante.motivo}</p>
                            {bloqueoComprobante.requiereCliente && (
                                <button
                                    onClick={onAbrirCliente}
                                    className="mt-0.5 text-xs font-bold underline hover:opacity-80"
                                >
                                    Elegir cliente
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {/* Botón cobrar */}
                <Button
                    variant="success"
                    size="lg"
                    radius="lg"
                    className="w-full !py-3 !text-base !font-bold"
                    onClick={onConfirmar}
                    disabled={carrito.length === 0 || hayInactivos || !puedeVender || !!bloqueoComprobante}
                    title={
                        !puedeVender ? (razonNoVender ?? 'No puedes registrar ventas en este momento.')
                        : hayInactivos ? 'Hay ítems inactivos en el carrito. Elimínalos para cobrar.'
                        : bloqueoComprobante ? bloqueoComprobante.motivo
                        : undefined
                    }
                >
                    {!puedeVender ? 'POS bloqueado'
                     : hayInactivos ? 'Resuelve ítems inactivos'
                     : bloqueoComprobante ? 'Corrige el comprobante'
                     : 'Cobrar venta'}
                </Button>
            </div>
        </>
    );
}

/* ─── V10 · Pista compacta junto a CADA selector de comprobante ──────────────
   Hay dos selectores (barra superior y barra móvil) porque hay dos layouts.
   Este componente es el único lugar donde se decide qué se muestra al lado del
   selector, así los dos quedan consistentes sin duplicar lógica: la serie que
   se usará, o un aviso rojo si la venta no se puede emitir así. */
function PistaComprobante({ visible, serie, bloqueo, sobrePrimario = false }: {
    visible: boolean;
    serie: string | null;
    bloqueo: BloqueoComprobante | null;
    /** true = va sobre la barra de color primario (texto blanco). */
    sobrePrimario?: boolean;
}) {
    if (!visible) return null;

    if (bloqueo) {
        return (
            <span
                className="inline-flex items-center gap-1 text-[11px] font-bold px-1.5 py-0.5 rounded whitespace-nowrap"
                style={
                    sobrePrimario
                        ? { backgroundColor: '#fff', color: 'var(--color-danger)' }
                        : { backgroundColor: 'color-mix(in srgb, var(--color-danger) 15%, transparent)', color: 'var(--color-danger)' }
                }
                title={bloqueo.motivo}
            >
                <AlertTriangle size={11} />
                Revisar
            </span>
        );
    }

    if (!serie) return null;

    return (
        <span
            className="text-[11px] font-semibold whitespace-nowrap"
            style={sobrePrimario ? { color: 'rgba(255,255,255,0.9)' } : { color: 'var(--color-text-muted)' }}
            title="Serie con la que se emitirá el comprobante"
        >
            {serie}
        </span>
    );
}
