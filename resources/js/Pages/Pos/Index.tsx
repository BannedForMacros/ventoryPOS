import { useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { useTiempoReal } from '@/lib/useTiempoReal';
import {
    Search, ShoppingCart, User, X, ArrowLeft, ChevronDown,
    Package, Receipt, Layers, AlertTriangle, ShoppingBag, ChevronUp,
    Image as ImageIcon, CreditCard, RefreshCw, Truck, FileCheck2, Wrench, Banknote, CheckCircle2, Store, Plus, PackageCheck, Printer,
    ArrowRight, Info, Camera,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import PosLayout from '@/Layouts/PosLayout';
import { celebrarVenta } from '@/lib/celebrarVenta';
import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import CarritoItem, { LineaCarrito, HistorialPrecioCliente, DescModo, DescTipo } from './Partials/CarritoItem';
import PanelPago, { LineaPago, faltanCuentas, cuentaDefaultDe } from './Partials/PanelPago';
import SelectorComprobante from './Partials/SelectorComprobante';
import ModalVisorVentas, { prepararLectura, type LecturaVisor, type LineaPlan, type VentaLeida } from './Partials/ModalVisorVentas';
import type { LucideIcon } from 'lucide-react';
import PanelDescuento from './Partials/PanelDescuento';
import ModalClienteRapido from './Partials/ModalClienteRapido';
import ModalCrearCliente from './Partials/ModalCrearCliente';
import DatosClienteVenta, { type DatosCliente } from './Partials/DatosClienteVenta';
import ModalNuevoProducto from './Partials/ModalNuevoProducto';
import ModalConfirmacionVenta from './Partials/ModalConfirmacionVenta';
import ModalSelectorPresentacion from './Partials/ModalSelectorPresentacion';
import Select from '@/Components/UI/Select';
import {
    validarComprobante, etiquetaComprobante, metaEstado, avisoModoEmision,
    UMBRAL_BOLETA_IDENTIFICADA, type BloqueoComprobante, type TipoComprobantePos,
    type VentanaEmision, fechaCorta,
} from '@/lib/comprobanteElectronico';
import type {
    Cliente, DescuentoConcepto, MetodoPago, Cuenta, Producto, ProductoUnidad,
    Turno, PageProps, FacturacionPosConfig,
} from '@/types';
import { avisoError } from '@/lib/avisoError';
import { estadoAgente } from '@/lib/ticketPrinter';

interface MetodoPagoConCuentas extends MetodoPago { cuentas?: Cuenta[]; }

/** Configuración de Entregas que llega al POS (Configuración → Entregas). */
interface EntregasPos {
    aviso_monto:            number | null;
    ruta_obligatoria:       boolean;
    fecha_obligatoria:      boolean;
    envio_sale_al_entregar: boolean;
    texto_recojo:           string;
    texto_envio:            string;
    rutas:                  { id: number; nombre: string; zona: string | null }[];
}
type TipoEntrega = 'recojo' | 'envio';

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
    observacion?:       string | null;
    cliente_telefono?:  string | null;
    cliente_direccion?: string | null;
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
    // Ya entregado del pendiente: se conserva al editar (piso de la línea).
    entregado?:            number;
    precio_unitario:       number;
    descuento_item:        number;
    descuento_concepto_id: number | null;
    incluye_igv:           boolean;
}
interface AnticipoCliente {
    id:          number;
    fecha:       string;
    monto:       number;
    saldo:       number;
    observacion: string | null;
    aplicado?:   number;
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
    tipo_cambio?:          number | null;
    // Anticipos de dinero con que se pagó la venta (saldo = el disponible al
    // guardar, incluido lo que esta venta le devuelve).
    anticipos?:            AnticipoCliente[];
    cliente:               Cliente | null;
    observacion?:          string | null;
    cliente_telefono?:     string | null;
    cliente_direccion?:    string | null;
    tipo_entrega?:         'recojo' | 'envio' | null;
    ruta_entrega_id?:      number | null;
    entrega_programada?:   string | null;
    // Crédito guardado en la venta — para recargar el toggle al editar.
    es_credito?:           boolean;
    fecha_vencimiento?:    string | null;
    // Estado de pago del crédito: decide si se permite marcar pendiente por entregar.
    monto_pagado?:         number;
    saldo_pendiente?:      number;
    total?:                number;
    // Pendiente por entregar existente (prellenado del panel). Lo ya
    // entregado se conserva al editar (items[].entregado).
    entrega_pendiente?:      boolean;
    despacho_almacen?:       boolean;
    fecha_entrega_estimada?: string | null;
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
    /**
     * Puede venir NULO: en los negocios sin caja (modo_turno = automatico) el
     * turno del día se abre en la PRIMERA VENTA, no al entrar al POS. Crearlo
     * solo por visitar la pantalla dejaría turnos vacíos cada vez que alguien
     * echa un ojo al catálogo.
     */
    turno:              Turno | null;
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
    // Casillas que la empresa puede apagar en Configuración → Empresa.
    permiteCredito?:           boolean;
    permitePendienteEntrega?:  boolean;
    // Pedir teléfono, dirección y observación del cliente (Configuración → Ticket).
    pideDatosCliente?:         boolean;
    // Entregas: recojo o envío. null = la empresa no usa la función.
    entregas?:                 EntregasPos | null;
    // Puede crear productos desde el POS (permiso de Catálogo → Productos).
    puedeCrearProducto?:       boolean;
    ticketPorPlantilla?:       boolean;
    // Visor de ventas (leer el cuaderno con IA). null = no está en el plan.
    visorVentas?:              { limite: number; restantes: number; ultima?: { sesion: number; ventas: VentaLeida[]; leida: string } | null } | null;
    // Ventana de SUNAT para la fecha del comprobante (hoy y hasta 3 días atrás),
    // calculada en el servidor. Ver App\Support\VentanaEmisionSunat.
    ventanaEmision?:           VentanaEmision | null;
    // Selector de fecha de emisión al elegir Factura (Configuración → Empresas).
    permiteFechaFactura?:      boolean;
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
    // Tope de descuento del rol en % (null = sin tope). El servidor lo exige igual.
    topeDescuento?:     number | null;
}

type TipoComprobante = TipoComprobantePos;

function uid() { return Math.random().toString(36).slice(2); }

const r2 = (n: number) => Math.round(n * 100) / 100;

/**
 * Clave de cada línea prellenada (edición, cita o cotización). La primera de
 * cada producto+presentación usa la clave clásica (así "agregar" sigue sumando
 * en ella); las repetidas llevan sufijo propio. Con la misma clave para dos
 * líneas, cambiar una cambiaba las dos y el pendiente se mezclaba.
 * Determinista: el estado inicial del carrito y el de los pendientes deben
 * calcular las MISMAS claves.
 */
function clavesDeLineas(items: { producto_id: number; producto_unidad_id: number }[]): string[] {
    const vistos = new Set<string>();
    return items.map((it, i) => {
        const base = `${it.producto_id}-${it.producto_unidad_id}`;
        if (!vistos.has(base)) { vistos.add(base); return base; }
        return `${base}-l${i}`;
    });
}

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

export default function PosIndex({ turno, productos, productosHasMore, productosCursor, clienteGeneral, categorias, hayServicios, metodosPago, conceptosDescuento, flash, citaPrellenada, cotizacionPrellenada, ventaEnEdicion, turnoBackdate, puedeVender, razonNoVender, monedas, tipoCambioHoy, facturacion, usaTransito, vendeTransito, permiteCredito = true, permitePendienteEntrega = true, pideDatosCliente = false, entregas = null, puedeCrearProducto = false, ventanaEmision = null, permiteFechaFactura = false, ticketPorPlantilla = false, topeDescuento = null , visorVentas = null }: Props) {
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
    const clavesPrellenados = clavesDeLineas(itemsPrellenados ?? []);
    const carritoInicial: LineaCarrito[] = itemsPrellenados?.map((it, idx) => {
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
            key:                   clavesPrellenados[idx],
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
    const clavesEdicion = clavesDeLineas(ventaEnEdicion?.items ?? []);
    // Una venta en USD llega en dólares; el catálogo (precio de lista y costo)
    // está en soles: se convierte con el TC congelado de la venta.
    const tcEdicion = ventaEnEdicion?.moneda === 'USD' ? (ventaEnEdicion.tipo_cambio || tipoCambioHoy || 1) : 1;
    const carritoEdicion: LineaCarrito[] = ventaEnEdicion?.items.map((it, idx) => {
        const prod = productos.find(p => p.id === it.producto_id);
        const uni  = prod?.unidades?.find(u => u.id === it.producto_unidad_id);
        return {
            key:                   clavesEdicion[idx],
            producto_id:           it.producto_id,
            producto_unidad_id:    it.producto_unidad_id,
            producto_nombre:       it.producto_nombre,
            unidad_nombre:         it.unidad_nombre,
            precio_unitario:       it.precio_unitario,
            precio_original:       uni ? r2(parseFloat(uni.precio_venta) / tcEdicion) : it.precio_unitario,
            costo_minimo:          prod && uni ? r2(costoMinimoDe(prod, uni) / tcEdicion) : 0,
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
            // Pagos viejos sin cuenta en un método que hoy tiene una sola: se
            // completa sola (con 1 cuenta el panel no muestra selector y la
            // cajera no tendría cómo elegirla).
            cuenta_metodo_pago_id: p.cuenta_metodo_pago_id ?? cuentaDefaultDe(m),
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
    // Datos con que se atiende ESTA venta. Al editar una venta o convertir una
    // cotización se respetan los que ya traía; si no, los de la ficha del cliente.
    const origenDatos = ventaEnEdicion ?? cotizacionPrellenada;
    const [datosCliente, setDatosCliente]   = useState<DatosCliente>({
        telefono:    origenDatos?.cliente_telefono ?? clienteInicial?.telefono ?? '',
        direccion:   origenDatos?.cliente_direccion ?? clienteInicial?.direccion ?? '',
        observacion: origenDatos?.observacion ?? '',
    });
    // Al cambiar de cliente, el teléfono y la dirección pasan a ser los suyos.
    const clienteAnterior = useRef(clienteInicial?.id ?? null);
    useEffect(() => {
        if ((cliente?.id ?? null) === clienteAnterior.current) return;
        clienteAnterior.current = cliente?.id ?? null;
        setDatosCliente(d => ({ ...d, telefono: cliente?.telefono ?? '', direccion: cliente?.direccion ?? '' }));
    }, [cliente]);
    // Historial de precios de venta de ESTE cliente por producto. Se carga al
    // elegir cliente y sirve para mostrar en cada línea a cuánto se le vendió
    // antes. Funciona en cualquier orden (cliente→productos o productos→cliente).
    const [historialCliente, setHistorialCliente] = useState<Record<number, HistorialPrecioCliente>>({});
    const [descuentoTotal, setDescuentoTotal]       = useState(ventaEnEdicion?.descuento_total ?? 0);
    const [descuentoConceptoId, setDescuentoConceptoId] = useState<number | null>(ventaEnEdicion?.descuento_concepto_id ?? null);
    const [tipoComprobante, setTipoComprobante]     = useState<TipoComprobante>(ventaEnEdicion?.tipo_comprobante ?? 'ticket');
    const [numeroComprobante, setNumeroComprobante] = useState(ventaEnEdicion?.numero_comprobante ?? '');
    // Fecha de emisión de la factura (solo con el selector activo). Por defecto hoy.
    const [fechaEmision, setFechaEmision] = useState<string>(ventanaEmision?.maxima ?? '');
    // Multimoneda: moneda de la venta. En USD los precios/pagos se ingresan en
    // dólares y el backend los convierte a soles al TC del día (congelado).
    const [moneda, setMoneda]                       = useState<'PEN' | 'USD'>(ventaEnEdicion?.moneda ?? 'PEN');
    // Soles por 1 US$: al editar, el TC congelado de la venta (el servidor
    // convierte con ese); en una venta nueva, el del día.
    const tcVenta = (ventaEnEdicion?.tipo_cambio || tipoCambioHoy || 0) as number;
    // Factor de la moneda de la venta → soles. El catálogo, el costo y los
    // anticipos están en soles; el carrito, en la moneda de la venta.
    const factorMoneda = moneda === 'USD' && tcVenta > 0 ? tcVenta : 1;
    const sim = moneda === 'USD' ? 'US$' : 'S/';
    const aMonedaVenta = (soles: number) => r2(soles / factorMoneda);
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
    // Entregas: toda venta nueva empieza como recojo en tienda.
    const [tipoEntrega, setTipoEntrega]               = useState<TipoEntrega>(entregas && ventaEnEdicion?.tipo_entrega === 'envio' ? 'envio' : 'recojo');
    const [rutaEntregaId, setRutaEntregaId]           = useState<number | null>(ventaEnEdicion?.ruta_entrega_id ?? null);
    const [entregaProgramada, setEntregaProgramada]   = useState(ventaEnEdicion?.entrega_programada ?? '');
    const [avisoEnvio, setAvisoEnvio]                 = useState(false);
    // El aviso "¿recoge o es envío?" se pregunta una sola vez por venta.
    const avisoRespondido                             = useRef(false);
    // Si el envío cambió la modalidad a "por entregar", volver a recojo la deshace.
    const modalidadPorEnvio                           = useRef(false);
    const esEnvio        = !!entregas && tipoEntrega === 'envio';
    // La empresa configuró que en un envío la mercadería sale al entregarse:
    // por defecto queda "Por entregar". Pero en cada venta se puede marcar
    // "Entregado" (p. ej. Puesto en obra que sale hoy con el camión): sale del
    // stock al cobrar y no se crea el pedido pendiente.
    const envioSaleAlEntregar = esEnvio && !!entregas?.envio_sale_al_entregar;
    const [envioEntregado, setEnvioEntregado] = useState(
        ventaEnEdicion?.tipo_entrega === 'envio' && !ventaEnEdicion?.entrega_pendiente && !ventaEnEdicion?.despacho_almacen,
    );
    const envioPendiente = envioSaleAlEntregar && !envioEntregado;
    const [pendientes, setPendientes]               = useState<Record<string, number>>(() => {
        const m: Record<string, number> = {};
        const claves = clavesDeLineas(ventaEnEdicion?.items ?? []);
        ventaEnEdicion?.items.forEach((it, idx) => {
            if (it.cantidad_pendiente && it.cantidad_pendiente > 0) {
                m[claves[idx]] = it.cantidad_pendiente;
            }
        });
        return m;
    });
    const [modalCliente, setModalCliente]   = useState(false);
    // Alta de cliente sin salir del POS (se abre desde el modal de selección).
    const [modalCrearCliente, setModalCrearCliente] = useState(false);
    // Alta rápida de productos: el nombre arranca con lo que se estaba buscando.
    const [modalNuevoProducto, setModalNuevoProducto] = useState(false);
    const [nombreNuevoProducto, setNombreNuevoProducto] = useState('');
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
    // Línea recién agregada o sumada → se ilumina en el carrito (ver CarritoItem).
    const [pulsos, setPulsos] = useState<Record<string, number>>({});
    // Visor de ventas: la lectura vive aquí para cobrar venta por venta sin
    // gastar otra lectura al reabrir el modal.
    const [verVisor, setVerVisor] = useState(false);
    // Al volver al POS después de cobrar, la última lectura de hoy sigue ahí (las cobradas vienen marcadas).
    const [lecturaVisor, setLecturaVisor] = useState<LecturaVisor | null>(() => visorVentas?.ultima ? prepararLectura(visorVentas.ultima.ventas, visorVentas.ultima.sesion) : null);
    // El visor carga ventas nuevas: no aplica al editar una venta ni al cobrar una cotización o cita.
    const visorActivo = !!visorVentas && !ventaEnEdicion && !cotizacionPrellenada && !citaPrellenada;
    const [restantesVisor, setRestantesVisor] = useState(visorVentas?.restantes ?? 0);
    // Total anotado en el cuaderno de la venta cargada (aviso en el carrito).
    const [totalCuaderno, setTotalCuaderno] = useState<number | null>(null);
    // Lo que se manda al cobrar una venta cargada desde el cuaderno.
    const [visorCarga, setVisorCarga] = useState<{ indice: number; sesion?: number; fecha: string | null; total: number | null; huella?: string; items: { texto: string; producto_id: number; cantidad: number | null }[] } | null>(null);
    // Vaciar el carrito sin cobrar devuelve la venta del cuaderno a "por cargar".
    useEffect(() => {
        if (carrito.length > 0) return;
        setTotalCuaderno(null);
        if (visorCarga) {
            const indice = visorCarga.indice;
            setLecturaVisor(l => l && ({ ...l, ventas: l.ventas.map((v, vi) => vi === indice ? { ...v, cargada: false } : v) }));
            setVisorCarga(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [carrito.length]);

    /**
     * Confirma que el producto entró. En pantalla grande el carrito está a la
     * vista: la línea se ilumina y basta. En celular/tablet el carrito está
     * cerrado, así que ahí sí va el aviso flotante.
     */
    function avisarAgregado(key: string, nombre: string) {
        setPulsos(p => ({ ...p, [key]: (p[key] ?? 0) + 1 }));
        if (!window.matchMedia('(min-width: 1024px)').matches) {
            toast.success(`${nombre} agregado`, { id: 'pos-agregado', duration: 1000 });
        }
    }
    // Advertencia al duplicar un producto: solo la primera vez por producto/unidad
    // en el carrito actual. Se resetea al limpiar el carrito.
    const advertenciasDuplicados = useRef<Set<string>>(new Set());
    // Tooltip de producto: solo aparece cuando el nombre de la tarjeta quedó
    // cortado (line-clamp). Un unico tooltip con posicion fija (no lo recorta
    // el scroll del grid) que muestra imagen + nombre completo. La cajera solo
    // pasa el mouse; no tiene que hacer clic en nada.
    const [tooltipProd, setTooltipProd] = useState<{ producto: Producto; top: number; bottom: number; left: number } | null>(null);
    // Anticipos de efectivo del cliente seleccionado.
    // Al EDITAR una venta pagada con anticipo, arranca con esos anticipos ya
    // marcados (y con el saldo que tendrán al guardar). Antes arrancaba sin
    // anticipo, el efectivo automático cubría todo y al guardar la venta quedaba
    // pagada con un efectivo que nunca entró.
    const anticiposEdicion = ventaEnEdicion?.anticipos ?? [];
    const [anticiposCliente, setAnticiposCliente] = useState<AnticipoCliente[]>(anticiposEdicion);
    // Anticipos elegidos para pagar la venta. 'auto' recalcula solo el mínimo
    // necesario (del más antiguo al más nuevo) cada vez que cambia el total;
    // marcar/desmarcar a mano pasa a 'manual' y respeta lo elegido.
    const [modoAnticipo, setModoAnticipo] = useState<'off' | 'auto' | 'manual'>(anticiposEdicion.length ? 'manual' : 'off');
    const [anticiposManual, setAnticiposManual] = useState<number[]>(anticiposEdicion.map(a => a.id));
    // La primera carga del cliente de la venta en edición no debe desmarcarlos.
    const conservarAnticiposEdicion = useRef(anticiposEdicion.length > 0);
    const [cargandoAnticipos, setCargandoAnticipos] = useState(false);

    // Totales de la venta (disponibles temprano para efectos y validaciones).
    const { subtotal, igv, total, baseGravada, baseExonerada } = calcularTotales(carrito, descuentoTotal, tasaIgv);

    // Anticipos aplicados a la venta. Se consumen del más antiguo al más nuevo
    // (la lista ya viene así del backend, igual que los aplica VentaService):
    // los primeros se agotan y el último afectado conserva su sobrante.
    const anticiposIds: number[] = modoAnticipo === 'off' ? []
        : modoAnticipo === 'manual' ? anticiposManual
        : (() => {
            const ids: number[] = [];
            let falta = total;
            for (const a of anticiposCliente) {
                if (falta <= 0.009 && ids.length) break;
                ids.push(a.id);
                falta = Math.round((falta - aMonedaVenta(a.saldo)) * 100) / 100;
            }
            return ids;
        })();
    const repartoAnticipos = (() => {
        const r: Record<number, number> = {};
        let falta = total;
        for (const a of anticiposCliente) {
            if (!anticiposIds.includes(a.id)) continue;
            // El saldo está en soles: en una venta en US$ se compara en dólares.
            const usa = Math.max(0, Math.min(aMonedaVenta(a.saldo), Math.round(falta * 100) / 100));
            r[a.id] = usa;
            falta -= usa;
        }
        return r;
    })();
    const anticipoSeleccionado: number | null = anticiposIds[0] ?? null;
    const montoAnticipoUsado = Math.round(Object.values(repartoAnticipos).reduce((s, v) => s + v, 0) * 100) / 100;
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

    // Tiempo real: otra caja vendió, entró mercadería o cambió un precio →
    // se actualizan en silencio el stock y los precios de las tarjetas a la
    // vista, sin tocar el carrito, la búsqueda ni el scroll.
    useTiempoReal(['stock', 'productos'], async () => {
        const { q, categoria, tipo } = paramsProductosRef.current;
        try {
            const params: Record<string, any> = { q: q.trim(), categoria_id: categoria, tipo };
            if (ventaEnEdicion?.id) params.venta_id = ventaEnEdicion.id;
            const { data } = await axios.get<{ productos: Producto[] }>(route('pos.productos'), { params });
            const frescos = new Map(data.productos.map(p => [p.id, p]));
            setListaProductos(prev => prev.map(p => frescos.get(p.id) ?? p));
        } catch {
            // Sin conexión: el próximo aviso (o la reconexión) lo intenta de nuevo.
        }
    });

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
            setModoAnticipo('off');
            setAnticiposManual([]);
            return;
        }
        let vivo = true;
        axios.get(route('pos.historial-precios'), { params: { cliente_id: id } })
            .then(r => { if (vivo) setHistorialCliente(r.data ?? {}); })
            .catch(() => { if (vivo) setHistorialCliente({}); });

        setCargandoAnticipos(true);
        axios.get<{ anticipos: AnticipoCliente[]; total: number }>(route('pos.clientes.anticipos', id))
            .then(r => {
                if (!vivo) return;
                // Cliente de la venta en edición: lo que esta venta consumió vuelve a
                // estar disponible al guardar (el servidor lo devuelve antes de
                // re-aplicar), aunque el anticipo haya quedado agotado por ella.
                let lista = r.data.anticipos;
                if (ventaEnEdicion && id === ventaEnEdicion.cliente?.id && anticiposEdicion.length) {
                    const propios = new Map(anticiposEdicion.map(a => [a.id, a]));
                    lista = [
                        ...lista.filter(a => !propios.has(a.id)),
                        ...anticiposEdicion,
                    ].sort((a, b) => (a.fecha === b.fecha ? a.id - b.id : a.fecha < b.fecha ? -1 : 1));
                }
                setAnticiposCliente(lista);
                if (conservarAnticiposEdicion.current) {
                    conservarAnticiposEdicion.current = false;
                    return;
                }
                // Otro cliente: lo elegido del anterior no aplica.
                setModoAnticipo('off');
                setAnticiposManual([]);
            })
            // Sin conexión: al menos se conservan los que ya pagaban esta venta.
            .catch(() => { if (vivo) setAnticiposCliente(ventaEnEdicion && id === ventaEnEdicion.cliente?.id ? anticiposEdicion : []); })
            .finally(() => { if (vivo) setCargandoAnticipos(false); });
        return () => { vivo = false; };
    }, [cliente?.id]);

    // ── Buscador siempre listo ────────────────────────────────────────────
    // El POS arranca con el cursor en el buscador y vuelve ahí tras cada venta
    // (la pantalla de carga y la de "venta confirmada" le quitaban el foco).
    // Además, si la cajera escribe o escanea sin estar en ningún campo, el
    // texto va directo al buscador. En pantallas táctiles no se fuerza el foco
    // para no abrir el teclado en pantalla sin que lo pidan.
    const buscadorRef = useRef<HTMLInputElement>(null);
    const esTactil = typeof window !== 'undefined' && window.matchMedia?.('(pointer: coarse)').matches;
    // Al volver al buscador se SELECCIONA lo que tenía: lo nuevo que se teclee
    // o escanee reemplaza la búsqueda anterior en vez de pegarse a ella.
    const enfocarBuscador = () => {
        if (esTactil || !buscadorRef.current) return;
        buscadorRef.current.focus();
        buscadorRef.current.select();
    };

    useEffect(() => {
        const t = setTimeout(enfocarBuscador, 250);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Carrito vacío de nuevo (venta cobrada o limpiada) → listo para la siguiente.
    useEffect(() => {
        if (carrito.length === 0) {
            const t = setTimeout(enfocarBuscador, 400);
            return () => clearTimeout(t);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [carrito.length === 0]);

    useEffect(() => {
        if (esTactil) return;
        function alTeclear(e: KeyboardEvent) {
            if (e.ctrlKey || e.metaKey || e.altKey || e.key.length !== 1) return;
            const el = document.activeElement as HTMLElement | null;
            const enCampo = el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable);
            // Con un modal abierto no se roba el foco.
            if (enCampo || document.querySelector('[role="dialog"]')) return;
            enfocarBuscador();
        }
        window.addEventListener('keydown', alTeclear);
        return () => window.removeEventListener('keydown', alTeclear);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Buscamos el método con tipo.slug === 'efectivo' como conveniencia inicial.
    // El flag `admite_vuelto` se lee del método (BD).
    const efectivo = metodosPago.find(m => m.tipo?.slug === 'efectivo');
    // Venta sin cobro: el descuento dejó el total en 0. No se registra ningún
    // pago (el servidor exige S/ 0.01 mínimo por pago, y no hubo dinero).
    const sinCobro = carrito.length > 0 && total <= 0.009;
    useEffect(() => {
        if (esCredito) return; // en crédito el pago inicial es opcional y manual
        if (sinCobro) return;
        // Si el anticipo cubre el total, no agregar efectivo automático.
        if (anticipoSeleccionado && montoAnticipoUsado >= total - 0.009) return;
        if (carrito.length > 0 && pagos.length === 0 && efectivo) {
            setPagos([{
                key:                   uid(),
                metodo_pago_id:        efectivo.id,
                // Si el efectivo tiene UNA cuenta vinculada va esa (el panel no
                // pinta selector con una sola y el cobro quedaba trabado).
                cuenta_metodo_pago_id: cuentaDefaultDe(efectivo),
                // Lo que falta: si hay un anticipo parcial, no el total entero.
                monto:                 parseFloat(Math.max(0, total - montoAnticipoUsado).toFixed(2)),
                referencia:            '',
                admite_vuelto:         !!efectivo.admite_vuelto,
                es_efectivo:           true,
            }]);
        }
        // `sinCobro`: al quitar un descuento del 100 % vuelve el efectivo automático.
        // `esCredito`: al volver de crédito a contado, también.
        // Anticipo: si deja de cubrir todo (se quitó o subió el total), vuelve el
        // efectivo por lo que falta en vez de quedar "Elige cómo paga".
    }, [carrito.length, sinCobro, esCredito, !!anticipoSeleccionado && montoAnticipoUsado >= total - 0.009]);

    // Pago único: su monto sigue al total cuando cambia el carrito. Vale para
    // efectivo y también para Yape/tarjeta (antes solo efectivo, y con Yape
    // agregar un producto dejaba "Falta S/ …" hasta corregirlo a mano).
    // Si hay anticipo seleccionado, ajustar al resto por pagar (no al total).
    useEffect(() => {
        if (esCredito) return; // no forzar el monto al total: puede ser pago parcial
        // Descuento del 100 %: no hay nada que cobrar → sin métodos de pago.
        if (sinCobro) {
            if (pagos.length > 0) setPagos([]);
            return;
        }
        if (pagos.length === 1 && carrito.length > 0) {
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
        // El catálogo está en soles: en una venta en US$ entra ya convertido.
        const precio = aMonedaVenta(parseFloat(unidad.precio_venta));
        const costoMinimo = aMonedaVenta(costoMinimoDe(producto, unidad));
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
                costo_minimo:         costoMinimo,
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

            avisarAgregado(key, nombreCompleto);
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
                costo_minimo:         costoMinimo,
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

        avisarAgregado(baseKey, nombreCompleto);
    }

    /**
     * Carga al carrito una venta leída del cuaderno. El modal ya resolvió todo:
     * una línea por producto (los repetidos sumados, salvo que la empresa
     * permita duplicar) y el precio final (de lista o ajustado al total que
     * anotó la cajera). Va en la unidad base: el cuaderno cuenta tabletas,
     * sobres, frascos.
     */
    async function cargarVentaLeida(venta: VentaLeida, indice: number, lineasPlan: LineaPlan[]): Promise<boolean> {
        // El cuaderno está en soles: convertir cada precio a dólares descuadraría el total.
        if (moneda !== 'PEN') {
            toast.error('El cuaderno está en soles: cambia la venta a soles para cargarla.');
            return false;
        }
        // Nunca pisa lo que la cajera ya tiene en el carrito.
        if (carrito.length > 0) {
            toast.error('Cobra o vacía el carrito actual antes de cargar otra venta del cuaderno.');
            return false;
        }
        const ids = [...new Set(lineasPlan.map(l => l.producto_id))];
        let encontrados: Producto[] = [];
        try {
            const { data } = await axios.get(route('pos.productos'), { params: { ids } });
            encontrados = data.productos ?? [];
        } catch {
            toast.error('No se pudieron traer los productos. Revisa tu conexión e intenta de nuevo.');
            return false;
        }
        const lineas: LineaCarrito[] = [];
        for (const plan of lineasPlan) {
            const producto = encontrados.find(p => p.id === plan.producto_id);
            const unidades = (producto?.unidades ?? []).filter(u => u.activo !== false);
            // El cuaderno cuenta unidades sueltas: sin unidad base activa no se adivina la presentación.
            const unidad = unidades.find(u => u.es_base) ?? producto?.unidad_base;
            if (!producto || !unidad) {
                toast.error(`"${plan.nombre}" ya no está disponible para la venta.`);
                return false;
            }
            const lista = aMonedaVenta(parseFloat(unidad.precio_venta));
            lineas.push(recalcularLinea({
                // Sin duplicados permitidos, la clave base: si luego tocan el mismo producto, se suma a esta línea.
                key:                  permiteDuplicarItems ? `${producto.id}-${unidad.id}-${uid()}` : `${producto.id}-${unidad.id}`,
                producto_id:          producto.id,
                producto_unidad_id:   unidad.id,
                producto_nombre:      producto.nombre,
                unidad_nombre:        unidad.unidad_medida?.nombre ?? '',
                // Precio final elegido en la revisión; el de lista queda tachado si cambió.
                precio_unitario:      aMonedaVenta(plan.precio),
                precio_original:      lista,
                costo_minimo:         aMonedaVenta(costoMinimoDe(producto, unidad)),
                stock_disponible:     producto.stock_disponible ?? null,
                stock_en_transito:    producto.stock_en_transito ?? 0,
                transito_fecha:       producto.transito_fecha ?? null,
                factor_conversion:    parseFloat(unidad.factor_conversion) || 1,
                cantidad:             plan.cantidad,
                descuento_item:       0,
                descuento_modo:       'pu',
                descuento_tipo:       'monto',
                descuento_valor:      0,
                descuento_concepto_id: null,
                subtotal:             0,
                incluye_igv:          producto.incluye_igv,
            }));
        }
        setCarrito(lineas);
        setTotalCuaderno(venta.total);
        // Al cobrar se manda con la venta: se recuerda (no cobrarla dos veces)
        // y se aprende qué producto era cada texto escrito.
        setVisorCarga({
            indice,
            sesion:  lecturaVisor?.sesion,
            huella: venta.huella,
            fecha: venta.fecha,
            total: venta.total,
            items: venta.items.filter(i => !i.quitado && i.elegido).map(i => ({ texto: i.texto, producto_id: i.elegido!.producto_id, cantidad: i.cantidad })),
        });
        setLecturaVisor(l => l && ({ ...l, ventas: l.ventas.map((v, vi) => vi === indice ? { ...v, cargada: true } : v) }));
        setVerVisor(false);
        toast.success(`Venta ${indice + 1} cargada: revisa y cobra.`);
        return true;
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

    /**
     * Cambiar de moneda con productos en el carrito convierte sus precios (y el
     * descuento y los pagos) al TC. Antes solo cambiaba la etiqueta: los S/ 10
     * de un producto pasaban a cobrarse US$ 10.
     */
    const descuentoSoles = useRef<number | null>(null);
    function cambiarMoneda(nueva: 'PEN' | 'USD') {
        if (nueva === moneda) return;
        if (!(tcVenta > 0)) { toast.error('No hay tipo de cambio del día: no se puede vender en dólares.'); return; }
        const f = nueva === 'USD' ? 1 / tcVenta : tcVenta;
        // De vuelta a soles: si el monto en dólares no se tocó, vuelve el de soles
        // exacto; solo lo que se cambió en dólares se convierte.
        const volver = (soles: number | undefined, actual: number) =>
            soles != null && r2(soles / tcVenta) === actual ? soles : r2(actual * f);
        setCarrito(prev => prev.map(i => {
            if (nueva === 'USD') {
                return recalcularLinea(i, {
                    en_soles:        { precio_unitario: i.precio_unitario, precio_original: i.precio_original, costo_minimo: i.costo_minimo, descuento_valor: i.descuento_valor },
                    precio_unitario: r2(i.precio_unitario * f),
                    precio_original: r2(i.precio_original * f),
                    costo_minimo:    r2(i.costo_minimo * f),
                    descuento_valor: i.descuento_tipo === 'monto' ? r2(i.descuento_valor * f) : i.descuento_valor,
                });
            }
            const s = i.en_soles;
            return recalcularLinea(i, {
                en_soles:        undefined,
                precio_unitario: volver(s?.precio_unitario, i.precio_unitario),
                precio_original: volver(s?.precio_original, i.precio_original),
                costo_minimo:    volver(s?.costo_minimo, i.costo_minimo),
                descuento_valor: i.descuento_tipo === 'monto' ? volver(s?.descuento_valor, i.descuento_valor) : i.descuento_valor,
            });
        }));
        if (nueva === 'USD') descuentoSoles.current = descuentoTotal;
        setDescuentoTotal(d => (nueva === 'USD' ? r2(d * f) : volver(descuentoSoles.current ?? undefined, d)));
        setPagos(prev => prev.map(p => ({ ...p, monto: r2(p.monto * f) })));
        setMoneda(nueva);
    }

    function eliminarItem(key: string) {
        setCarrito(prev => prev.filter(i => i.key !== key));
    }

    function limpiarCarrito() {
        setCarrito([]);
        setPagos([]);
        setCliente(clienteGeneral);
        setDatosCliente({ telefono: clienteGeneral?.telefono ?? '', direccion: clienteGeneral?.direccion ?? '', observacion: '' });
        setMoneda('PEN');
        setDescuentoTotal(0);
        setDescuentoConceptoId(null);
        setTipoComprobante('ticket');
        setNumeroComprobante('');
        // La fecha elegida para una factura no se hereda: la siguiente vuelve a hoy.
        setFechaEmision(ventanaEmision?.maxima ?? '');
        // La venta a crédito no debe "heredarse" a la siguiente venta.
        setEsCredito(false);
        setFechaVencimiento('');
        // Tampoco el pendiente por entregar.
        setEntregaPendiente(false);
        setFechaEntrega('');
        // Ni el envío: la siguiente venta vuelve a empezar como recojo.
        setTipoEntrega('recojo');
        setEnvioEntregado(false);
        setRutaEntregaId(null);
        setEntregaProgramada('');
        avisoRespondido.current = false;
        modalidadPorEnvio.current = false;
        setPendientes({});
        // Resetear advertencias de duplicados para la siguiente venta.
        advertenciasDuplicados.current.clear();
        setNuevaLineaPrecioKey(null);
    }

    /** Pendiente efectivo de una línea: lo tecleado, recortado a [0, cantidad]. */
    function pendienteDe(item: LineaCarrito): number {
        // En un envío, lo que no se marcó como llevado queda todo por entregar.
        const p = pendientes[item.key] ?? (envioPendiente ? item.cantidad : 0);
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

    // Selector de fecha de la factura: solo si la empresa lo activó, la emisión está
    // encendida y se eligió Factura. Fuera de eso el POS se ve como siempre.
    const muestraFechaFactura = permiteFechaFactura && feActiva && tipoComprobante === 'factura' && !!ventanaEmision;
    // Fecha con la que saldrá el comprobante: la elegida, o la del turno reabierto.
    const fechaComprobante = muestraFechaFactura ? fechaEmision : (turnoBackdate?.fecha ?? null);

    const bloqueoComprobante: BloqueoComprobante | null = useMemo(
        () => validarComprobante({
            tipoComprobante, cliente, total, moneda, umbral: umbralCPE, emisionActiva: feActiva,
            fechaComprobante, ventana: ventanaEmision,
        }),
        [tipoComprobante, cliente, total, moneda, umbralCPE, feActiva, fechaComprobante, ventanaEmision],
    );

    // La franja informativa solo aparece si el módulo está activo (hay algo real
    // que emitir) o si hay un problema que impide cobrar. Con el módulo apagado
    // y todo en orden, el POS se ve exactamente como hoy.
    const mostrarAvisoCPE = emiteCPE && (feActiva || !!bloqueoComprobante);

    // Badge del comprobante recién emitido (lo trae el flash de la venta anterior).
    const comprobanteFlash = flash?.comprobante ?? null;

    /**
     * Qué impide cobrar AHORA, en palabras de la cajera, y cómo llevarla a
     * resolverlo. Lo usa el botón "Cobrar" (que muestra el texto en vez de
     * dejar pulsar y recién ahí soltar un error) y la propia confirmación.
     * Devuelve null si todo está listo.
     */
    function problemaCobro(): { texto: string; resolver?: () => void } | null {
        const enfocar = (selector: string) => () => {
            const el = document.querySelector<HTMLElement>(selector);
            el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            el?.focus();
        };
        const irACliente = () => setModalCliente(true);

        if (carrito.length === 0) return { texto: 'Agrega productos' };
        if (bloqueoComprobante) {
            return {
                texto: bloqueoComprobante.requiereCliente ? 'Elige el cliente del comprobante' : bloqueoComprobante.motivo,
                resolver: bloqueoComprobante.requiereCliente ? irACliente : undefined,
            };
        }
        if (hayInactivos) {
            return { texto: itemsInactivos.length === 1
                ? 'Quita el producto que ya no se vende (en rojo)'
                : `Quita los ${itemsInactivos.length} productos que ya no se venden (en rojo)` };
        }
        if (descuentoTotal > 0 && !descuentoConceptoId) {
            return { texto: 'Elige el motivo del descuento', resolver: enfocar('[data-descuento-concepto]') };
        }
        // Mismo piso que el servidor: lo que se cobra (precio menos un descuento
        // SIN motivo) no puede quedar bajo el costo.
        const cobradoDe = (i: LineaCarrito) => i.precio_unitario - (i.descuento_concepto_id ? 0 : i.descuento_item);
        const bajoCosto = carrito.find(i => (i.costo_minimo ?? 0) > 0 && cobradoDe(i) < i.costo_minimo - 0.009);
        if (bajoCosto) {
            return {
                texto: bajoCosto.precio_unitario < bajoCosto.costo_minimo - 0.009
                    ? `Sube el precio de ${bajoCosto.producto_nombre}: está bajo el costo (${sim} ${bajoCosto.costo_minimo.toFixed(2)})`
                    : `El descuento deja ${bajoCosto.producto_nombre} bajo el costo (${sim} ${bajoCosto.costo_minimo.toFixed(2)}): bájalo o elige su motivo`,
                resolver: enfocar(`[data-precio-key="${bajoCosto.key}"]`),
            };
        }
        // Tope de descuento del rol, sumando el global y los de cada producto.
        if (topeDescuento !== null && topeDescuento !== undefined) {
            const bruto = carrito.reduce((s, i) => s + i.precio_unitario * i.cantidad, 0);
            const descontado = descuentoTotal + carrito.reduce((s, i) => s + Math.min(i.descuento_item, i.precio_unitario) * i.cantidad, 0);
            const pct = bruto > 0 ? Math.round((descontado / bruto) * 10000) / 100 : 0;
            if (descontado > 0.009 && pct > topeDescuento + 0.01) {
                return { texto: `Tu rol permite hasta ${topeDescuento}% de descuento y vas en ${pct}%: pide a un supervisor` };
            }
        }

        if (esEnvio && entregas) {
            if (esClienteGeneralSel) return { texto: 'Elige el cliente del envío', resolver: irACliente };
            if (!datosCliente.direccion.trim() && !cliente?.direccion) {
                return { texto: 'Falta la dirección del envío', resolver: enfocar('[data-envio-direccion]') };
            }
            if (entregas.ruta_obligatoria && entregas.rutas.length > 0 && !rutaEntregaId) {
                return { texto: 'Elige la ruta del envío', resolver: enfocar('[data-envio-ruta]') };
            }
            if (entregas.fecha_obligatoria && !entregaProgramada) {
                return { texto: 'Indica cuándo se entrega', resolver: enfocar('[data-envio-fecha]') };
            }
        }
        if (entregaPendiente) {
            if (esClienteGeneralSel) return { texto: 'Elige el cliente que recogerá lo pendiente', resolver: irACliente };
            if (totalPendientes <= 0.00009) return { texto: 'Indica cuánto se lleva ahora', resolver: enfocar('[data-pendiente-input]') };
        }
        if (despachoAlmacen && esClienteGeneralSel) return { texto: 'Elige el cliente del despacho', resolver: irACliente };

        const totalPagado = totalPagadoConAnticipo;
        if (esCredito) {
            if (esClienteGeneralSel) return { texto: 'Elige el cliente del crédito', resolver: irACliente };
            if (totalPagado > total + 0.009) return { texto: 'El pago inicial supera el total', resolver: enfocar('[data-pago-monto]') };
        } else if (!sinCobro) {
            if (pagos.length === 0 && !anticipoSeleccionado) return { texto: 'Elige cómo paga', resolver: enfocar('[data-metodo-pago]') };
            if (totalPagado < total - 0.009) {
                return { texto: `Falta cubrir ${sim} ${(total - totalPagado).toFixed(2)}`, resolver: enfocar('[data-pago-monto]') };
            }
        }

        const enCero = pagos.find(p => p.monto <= 0.009);
        if (enCero && pagos.length > 1) {
            const m = metodosPago.find(x => x.id === enCero.metodo_pago_id);
            return { texto: `Escribe el monto de ${m?.nombre ?? 'un pago'} o quítalo`, resolver: enfocar(`[data-pago-monto="${enCero.key}"]`) };
        }
        // Un pago en cero no se envía (ver payload): no exige cuenta.
        const sinCuenta = pagos.find(p => p.monto > 0.009 && faltanCuentas([p], metodosPago));
        if (sinCuenta) {
            const m = metodosPago.find(x => x.id === sinCuenta.metodo_pago_id);
            return { texto: `Elige la cuenta de ${m?.nombre ?? 'este pago'}`, resolver: enfocar(`[data-pago-cuenta="${sinCuenta.key}"]`) };
        }
        return null;
    }

    function confirmarVenta() {
        const problema = problemaCobro();
        if (problema) {
            // El botón ya mostraba qué falta: pulsarlo lleva a resolverlo.
            if (problema.resolver) problema.resolver();
            else toast.error(problema.texto);
            return;
        }
        // Para no olvidar el envío: una venta grande marcada como recojo se
        // confirma antes de cobrar (una sola vez, y no al editar una venta).
        if (entregas?.aviso_monto && !esEnvio && !ventaEnEdicion && !avisoRespondido.current && total >= entregas.aviso_monto) {
            setAvisoEnvio(true);
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
            ...(entregas ? {
                tipo_entrega:       tipoEntrega,
                // Envío ya entregado: el servidor no lo convierte en pedido pendiente.
                envio_entregado:    esEnvio && envioEntregado,
                ruta_entrega_id:    esEnvio ? rutaEntregaId : null,
                entrega_programada: esEnvio && entregaProgramada ? entregaProgramada : null,
            } : {}),
            // Solo viajan si la empresa los pide: así editar una venta no los borra.
            ...(pideDatosCliente || esEnvio ? {
                cliente_telefono:  datosCliente.telefono.trim() || null,
                cliente_direccion: datosCliente.direccion.trim() || null,
                observacion:       datosCliente.observacion.trim() || null,
            } : {}),
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
            tipo_cambio:           moneda === 'USD' ? (tcVenta || null) : null,
            // Modo turno específico (admin): la venta va a ESE turno con la
            // fecha del turno (backdate). El backend valida admin + turno abierto.
            turno_id:              turnoBackdate?.turno_id ?? null,
            fecha_venta:           turnoBackdate?.fecha ?? null,
            // Fecha elegida para la FACTURA; la venta sigue siendo de hoy.
            fecha_emision:         muestraFechaFactura ? fechaEmision : null,
            // Se reenvia el mismo key en cada reintento. El backend desduplica.
            idempotency_key:       idempotencyKey,
            // Si vino de una cita, lo enviamos para que el backend la vincule.
            cita_id:               citaPrellenada?.id ?? null,
            // Si vino de una cotización, el backend la marca 'convertida' y
            // le guarda el venta_id.
            cotizacion_id:         cotizacionPrellenada?.id ?? null,
            // Venta que vino del cuaderno (visor de ventas): recordarla y aprender.
            visor:                 visorCarga,
            // Anticipos de efectivo del cliente con los que se pagará la venta
            // (el backend los consume del más antiguo al más nuevo).
            anticipo_ids:          anticiposIds.filter(id => (repartoAnticipos[id] ?? 0) > 0.009),
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
            // Un pago en S/ 0 no es dinero: pasa cuando el anticipo cubre todo y
            // el efectivo automático quedó en cero. El servidor exige 0.01.
            pagos: pagos.filter(p => p.monto > 0.009).map(p => ({
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
                    avisoError(msg);
                },
            });
            return;
        }

        // Para la pantalla verde: se toman AHORA, porque al terminar el
        // carrito ya se habrá limpiado. El vuelto, igual que en el pie del POS.
        const cobrado = {
            total,
            vuelto: pagos.some(p => p.admite_vuelto) ? Math.max(0, totalPagadoConAnticipo - total) : 0,
        };

        router.post(route('ventas.store'), payload as any, {
            onSuccess: () => {
                celebrarVenta(cobrado);
                setLoading(false);
                setModalConfirm(false);
                // Cobrada: la venta del cuaderno queda como cargada (no vuelve a "por cargar").
                setVisorCarga(null);
                limpiarCarrito();
                setCarritoAbierto(false);
                // Renovar key para la proxima venta (la actual ya quedo persistida).
                setIdempotencyKey(generarIdempotencyKey());
            },
            onError: (errors) => {
                setLoading(false);
                const msg = Object.values(errors)[0];
                avisoError(msg);
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
        // Crédito: el pago inicial es opcional → empieza vacío (antes quedaba el
        // efectivo por el total y la "venta a crédito" en realidad era contado).
        if (v) setPagos([]);
    }
    // Entrega: "se lleva todo", "por entregar" o "despacho" (excluyentes entre
    // sí, pero SÍ combinables con crédito: la mercadería pendiente de una venta
    // a crédito se controla igual y solo lo pagado cuenta como saldo a favor).
    function activarPendiente(v: boolean) {
        setEntregaPendiente(v);
        if (v) setDespachoAlmacen(false);
    }
    function activarDespachoAlmacen(v: boolean) {
        setDespachoAlmacen(v);
        if (v) setEntregaPendiente(false);
    }
    type Entrega = 'completa' | 'pendiente' | 'despacho';
    const entrega: Entrega = despachoAlmacen ? 'despacho' : entregaPendiente ? 'pendiente' : 'completa';
    function elegirEntrega(e: Entrega) {
        // En un envío, "Entregado" = la mercadería sale ya con el envío.
        if (envioSaleAlEntregar) setEnvioEntregado(e === 'completa');
        modalidadPorEnvio.current = false;
        activarPendiente(e === 'pendiente');
        activarDespachoAlmacen(e === 'despacho');
    }

    function elegirTipoEntrega(t: TipoEntrega) {
        if (!entregas || t === tipoEntrega) return;
        setTipoEntrega(t);
        setEnvioEntregado(false);
        avisoRespondido.current = true;

        if (t === 'envio') {
            // La mercadería sale al entregarse: queda por entregar (o en despacho,
            // si la empresa usa la bandeja del almacén).
            if (entregas.envio_sale_al_entregar && entrega === 'completa') {
                modalidadPorEnvio.current = true;
                if (empresaAuth?.usa_despacho_almacen) activarDespachoAlmacen(true);
                else activarPendiente(true);
            }
            return;
        }

        setRutaEntregaId(null);
        setEntregaProgramada('');
        if (modalidadPorEnvio.current) {
            modalidadPorEnvio.current = false;
            activarPendiente(false);
            activarDespachoAlmacen(false);
        }
    }

    function setPendienteLinea(key: string, valor: number) {
        setPendientes(prev => ({ ...prev, [key]: valor }));
    }

    // ¿El programa de impresión de esta PC imprime plantillas? Si la empresa
    // usa una y el programa es anterior a la 1.3.0, sale el ticket estándar.
    const [agenteViejo, setAgenteViejo] = useState<string | null>(null);
    useEffect(() => {
        if (!ticketPorPlantilla) return;
        estadoAgente().then(a => { if (a.activo && a.bloques < 1) setAgenteViejo(a.version ?? 'anterior'); });
    }, [ticketPorPlantilla]);

    // Edición de una venta con entregas registradas: "3000 Unidad de Alambre…".
    const yaEntregado = (ventaEnEdicion?.items ?? [])
        .filter(it => (it.entregado ?? 0) > 0)
        .map(it => `${+(it.entregado ?? 0).toFixed(4)} ${it.unidad_nombre} de ${it.producto_nombre}`);

    const propsPendiente = {
        // Editable también en edición de venta (lo ya entregado se conserva).
        // Oculto si la empresa lo apagó, salvo al editar una venta que ya lo usa.
        permitirPendiente:     permitePendienteEntrega || !!ventaEnEdicion?.entrega_pendiente,
        // Idem crédito: una venta que nació al crédito sigue mostrando la casilla.
        permitirCredito:       permiteCredito || !!ventaEnEdicion?.es_credito,
        entregaPendiente,
        despachoAlmacen,
        fechaEntrega,
        pendienteDe,
        totalPendientes,
        onSetEntregaPendiente: activarPendiente,
        onSetDespachoAlmacen:  activarDespachoAlmacen,
        onSetFechaEntrega:     setFechaEntrega,
        onSetPendiente:        setPendienteLinea,
        entrega,
        onElegirEntrega:       elegirEntrega,
        // Bandeja de despacho en almacén (solo si la empresa lo activó).
        usaDespachoAlmacen:    empresaAuth?.usa_despacho_almacen ?? false,
        // En un envío que sale al entregarse no existe "se lleva todo".
        envioPendiente,
        envioSaleAlEntregar,
        // Recojo o envío (solo si la empresa usa Entregas).
        slotEntrega: entregas ? (
            <EntregaVenta
                entregas={entregas}
                tipo={tipoEntrega}
                onTipo={elegirTipoEntrega}
                rutaId={rutaEntregaId}
                onRuta={setRutaEntregaId}
                programada={entregaProgramada}
                onProgramada={setEntregaProgramada}
                datos={datosCliente}
                onDatos={setDatosCliente}
                pedirDatosAqui={!pideDatosCliente}
                entregado={envioEntregado}
            />
        ) : null,
        // Autofoco del precio en líneas recién agregadas con precio base 0.
        nuevaLineaPrecioKey,
        onAutoFocusPrecio:     () => setNuevaLineaPrecioKey(null),
        pulsos,
        // Resumen de las opciones de la venta (pie del carrito) y si deben verse
        // abiertas: un envío tiene datos que llenar (ruta, fecha, dirección).
        totalCuaderno,
        resumenEntrega:        entregas ? comoFrase(tipoEntrega === 'envio' ? entregas.texto_envio : entregas.texto_recojo) : null,
        forzarOpciones:        esEnvio,
        // Símbolo de la moneda de la venta (carrito, pago y total).
        simbolo:               sim,
    };

    /*
     * Comprobante, arriba del carrito y junto al cliente (pantalla grande).
     * Antes vivía en la barra azul, lejos del cliente del que depende (la
     * factura pide RUC) y con la misma pinta que la cabecera: se confundían.
     * Ahora "a quién y con qué documento" se decide en un solo lugar, y lo que
     * se va a emitir —o por qué no se puede— se lee ahí mismo.
     */
    const slotComprobante = (
        <div className="space-y-1.5">
            <SelectorComprobante
                variante="carrito"
                valor={tipoComprobante}
                feActiva={feActiva}
                onChange={v => setTipoComprobante(v as TipoComprobante)}
            />
            {(muestraFechaFactura && ventanaEmision) || esComprobanteExterno ? (
                <div className="flex items-center gap-2">
                    {muestraFechaFactura && ventanaEmision && (
                        <label className="flex items-center gap-2 text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                            Fecha de emisión
                            <input
                                type="date"
                                value={fechaEmision}
                                min={ventanaEmision.minima}
                                max={ventanaEmision.maxima}
                                onChange={e => setFechaEmision(e.target.value || ventanaEmision.maxima)}
                                title={`Desde el ${fechaCorta(ventanaEmision.minima)} hasta hoy`}
                                className="h-8 text-[13px] border rounded-lg px-2"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                            />
                        </label>
                    )}
                    {esComprobanteExterno && (
                        <input
                            type="text"
                            value={numeroComprobante}
                            onChange={e => setNumeroComprobante(e.target.value.toUpperCase())}
                            placeholder="N.º del comprobante (opcional)"
                            aria-label="Número del comprobante emitido en otro sistema"
                            maxLength={30}
                            className="flex-1 h-8 text-[13px] border rounded-lg px-2.5"
                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                        />
                    )}
                </div>
            ) : null}
            {/* Qué se va a emitir. El bloqueo (si lo hay) va en la fila del cliente. */}
            {emiteCPE && !bloqueoComprobante && (
                <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                    {etiquetaComprobante(tipoComprobante)}
                    {serieCPE && <> · serie <strong style={{ color: 'var(--color-text)' }}>{serieCPE}</strong></>}
                    {feActiva && ' · se envía a SUNAT al cobrar'}
                </p>
            )}
            {esComprobanteExterno && (
                <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                    Emitido en otro sistema: aquí solo se anota.
                </p>
            )}
        </div>
    );

    return (
        <PosLayout>
            {/* Banner de edición de venta (POS abierto con ?venta_id=) */}
            {agenteViejo && (
                <div role="status" className="flex items-center gap-2 px-3 sm:px-4 py-1.5 flex-shrink-0 border-b text-xs"
                    style={{
                        backgroundColor: 'color-mix(in srgb, var(--color-warning) 12%, var(--color-bg))',
                        borderColor: 'var(--color-warning)', color: 'var(--color-text)',
                    }}>
                    <Printer size={14} className="shrink-0" style={{ color: 'var(--color-warning)' }} />
                    <span>
                        El programa de impresión de esta PC es la versión <b>{agenteViejo}</b>: los tickets salen con el diseño anterior.
                        Actualízalo a la 1.3.0 o superior (si ya lo actualizaste, cierra el programa viejo o reinicia la PC).
                    </span>
                    <button onClick={() => setAgenteViejo(null)} className="ml-auto shrink-0 font-semibold underline hover:opacity-80">Entendido</button>
                </div>
            )}
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
                            {yaEntregado.length > 0 && (ventaEnEdicion.es_admin ? (
                                <strong>
                                    {' '}Ya entregado: {yaEntregado.join(' · ')}. Eso se conserva y no puedes vender menos.
                                </strong>
                            ) : (
                                <strong style={{ color: 'var(--color-danger)' }}>
                                    {' '}Esta venta ya tiene entregas registradas: solo un administrador puede editarla.
                                </strong>
                            ))}
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
                            <span className="hidden sm:inline">POS{turno?.caja?.nombre ? ` · ${turno.caja.nombre}` : ''}</span>
                        </p>
                        {turno && (
                            <p className="hidden sm:block text-[10px] opacity-70 leading-tight">
                                Turno #{turno.id}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-2 flex-shrink-0">
                    {/* Comprobante en tablet. En pantalla grande va arriba del carrito, junto al cliente. */}
                    <div className="hidden sm:flex lg:hidden items-center gap-1.5">
                        <SelectorComprobante
                            variante="primario"
                            valor={tipoComprobante}
                            feActiva={feActiva}
                            onChange={v => setTipoComprobante(v as TipoComprobante)}
                        />
                        {muestraFechaFactura && ventanaEmision && (
                            <input
                                type="date"
                                value={fechaEmision}
                                min={ventanaEmision.minima}
                                max={ventanaEmision.maxima}
                                onChange={e => setFechaEmision(e.target.value || ventanaEmision.maxima)}
                                title={`Fecha de emisión de la factura (desde el ${fechaCorta(ventanaEmision.minima)} hasta hoy)`}
                                aria-label="Fecha de emisión de la factura"
                                className="text-xs bg-white/15 border-0 rounded-lg px-2 py-1.5 text-white focus:outline-none focus:ring-2 focus:ring-white/30 [color-scheme:dark]"
                            />
                        )}
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
                            <Select variant="oscuro" size="sm" ariaLabel="Moneda" className="w-32"
                                value={moneda}
                                // Editar no cambia la moneda (VentaService la conserva).
                                disabled={!!ventaEnEdicion}
                                onChange={v => cambiarMoneda(v as 'PEN' | 'USD')}
                                options={[{ value: 'PEN', label: 'S/ Soles' }, { value: 'USD', label: 'US$ Dólares' }]} />
                            {moneda === 'USD' && (
                                <span className="text-[11px] font-medium text-white/90 whitespace-nowrap">TC {Number(tipoCambioHoy).toFixed(3)}</span>
                            )}
                        </div>
                    ) : null}

                    {/* Cliente (celular y tablet). En pantalla grande está arriba del carrito. */}
                    <button
                        onClick={() => setModalCliente(true)}
                        aria-label="Cambiar cliente"
                        className="lg:hidden flex items-center gap-2 text-xs px-3 py-2 rounded-lg bg-white/15 hover:bg-white/25 active:bg-white/30 transition-colors min-h-[36px] max-w-[180px] sm:max-w-[220px]"
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

            {pideDatosCliente && <DatosClienteVenta valor={datosCliente} onChange={setDatosCliente} />}

            {/* ── Anticipos de efectivo del cliente ────────────────────────
                Se pueden usar VARIOS: se consumen del más antiguo al más nuevo y
                el último afectado conserva su sobrante. Actúan como pago sin
                mover caja (el dinero ya entró al registrar cada anticipo). */}
            {!!cliente && anticiposCliente.length > 0 && (() => {
                const activo = anticiposIds.length > 0;
                const saldoTotal = anticiposCliente.reduce((s, a) => s + aMonedaVenta(a.saldo), 0);
                const falta = Math.max(0, Math.round((total - montoAnticipoUsado) * 100) / 100);
                const alternar = (id: number) => {
                    const base = anticiposIds;
                    const sig = base.includes(id) ? base.filter(x => x !== id) : [...base, id];
                    setAnticiposManual(sig);
                    setModoAnticipo(sig.length ? 'manual' : 'off');
                };
                return (
                <div
                    className="px-3 sm:px-4 py-2 text-sm border-b flex-shrink-0"
                    style={{
                        backgroundColor: activo
                            ? 'color-mix(in srgb, var(--color-success) 12%, var(--color-bg))'
                            : 'color-mix(in srgb, var(--color-warning) 12%, var(--color-bg))',
                        borderColor: activo ? 'var(--color-success)' : 'var(--color-warning)',
                        color: 'var(--color-text)',
                    }}
                >
                    <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2 flex-wrap min-w-0">
                            <span className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                                style={{ backgroundColor: activo ? 'var(--color-success)' : 'var(--color-warning)', color: '#fff' }}>
                                {activo ? (anticiposIds.length > 1 ? `${anticiposIds.length} anticipos` : 'Anticipo activo') : 'Anticipo disponible'}
                            </span>
                            <span className="truncate">
                                {activo
                                    ? `Se descontará ${sim} ${montoAnticipoUsado.toFixed(2)}${falta > 0.009 ? ` · falta ${sim} ${falta.toFixed(2)} por pagar` : ''}.`
                                    : `Este cliente tiene ${sim} ${saldoTotal.toFixed(2)} en ${anticiposCliente.length > 1 ? `${anticiposCliente.length} anticipos` : 'un anticipo'} de efectivo.`}
                            </span>
                        </div>
                        <button
                            onClick={() => { setAnticiposManual([]); setModoAnticipo(activo ? 'off' : 'auto'); }}
                            disabled={cargandoAnticipos}
                            className="text-xs font-bold px-2.5 py-1.5 rounded-lg transition-colors hover:opacity-90 flex-shrink-0"
                            style={{
                                backgroundColor: activo
                                    ? 'color-mix(in srgb, var(--color-danger) 12%, transparent)'
                                    : 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                                color: activo ? 'var(--color-danger)' : 'var(--color-primary)',
                            }}
                        >
                            {activo ? 'No usar' : (anticiposCliente.length > 1 ? 'Usar anticipos' : 'Usar anticipo')}
                        </button>
                    </div>

                    {activo && anticiposCliente.length > 1 && (
                        <div className="mt-2 flex flex-col gap-1">
                            {anticiposCliente.map(a => {
                                const marcado = anticiposIds.includes(a.id);
                                const usa = repartoAnticipos[a.id] ?? 0;
                                const queda = Math.round((aMonedaVenta(a.saldo) - usa) * 100) / 100;
                                return (
                                    <label key={a.id} className="flex items-center gap-2 text-xs cursor-pointer rounded px-1.5 py-1"
                                        style={{ backgroundColor: marcado ? 'color-mix(in srgb, var(--color-success) 8%, transparent)' : 'transparent' }}>
                                        <input type="checkbox" checked={marcado} onChange={() => alternar(a.id)} disabled={cargandoAnticipos} />
                                        <span className="tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{a.fecha}</span>
                                        <span className="truncate min-w-0 flex-1">
                                            #{a.id}{a.observacion ? ` · ${a.observacion}` : ''} — saldo {sim} {aMonedaVenta(a.saldo).toFixed(2)}
                                        </span>
                                        <span className="tabular-nums font-semibold flex-shrink-0">
                                            {!marcado ? ''
                                                : usa <= 0.009 ? <span style={{ color: 'var(--color-text-muted)' }}>no se necesita</span>
                                                : queda > 0.009 ? `usa ${sim} ${usa.toFixed(2)} · queda ${sim} ${queda.toFixed(2)}`
                                                : `usa ${sim} ${usa.toFixed(2)} · se agota`}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                    )}
                </div>
                );
            })()}

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
                    {/* En pantalla grande esto se lee arriba del carrito (slotComprobante). */}
                    <div
                        className="lg:hidden flex items-center justify-between gap-2 px-3 sm:px-4 py-2 flex-wrap border-b text-sm"
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
                    {/* Buscador + UNA sola fila de filtros. Antes había una cabecera
                        "Productos 40", pestañas Todo/Productos/Servicios y además
                        chips "Todos/categorías": dos filtros que decían lo mismo. */}
                    <div className="px-3 sm:px-4 pt-3 pb-2.5 flex flex-col gap-2 flex-shrink-0" style={{ backgroundColor: 'var(--color-surface)', borderBottom: '1px solid var(--color-border)' }}>
                        <div className="flex items-center gap-2">
                            <div className="relative flex-1 min-w-0">
                                <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                                <input
                                    type="search"
                                    inputMode="search"
                                    enterKeyHint="search"
                                    value={busqueda}
                                    onChange={e => setBusqueda(e.target.value)}
                                    onKeyDown={onBusquedaKeyDown}
                                    ref={buscadorRef}
                                    placeholder="Busca por nombre o código, o escanea (Enter agrega)"
                                    aria-label="Buscar producto"
                                    autoFocus
                                    autoComplete="off"
                                    className="w-full h-10 pl-10 pr-9 text-sm border rounded-xl focus:outline-none focus:ring-2"
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
                                        aria-label="Borrar búsqueda"
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-black/5"
                                        style={{ color: 'var(--color-text-muted)' }}
                                    >
                                        <X size={14} />
                                    </button>
                                )}
                            </div>
                            {/* Refrescar catálogo sin perder el carrito (p. ej. tras crear un producto en otra pestaña). */}
                            <button
                                onClick={refrescarCatalogo}
                                disabled={refrescando}
                                title="Actualizar lista de productos"
                                aria-label="Actualizar lista de productos"
                                className="flex items-center justify-center h-10 w-10 flex-shrink-0 rounded-xl border transition-colors hover:bg-black/5 disabled:opacity-60"
                                style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}
                            >
                                <RefreshCw size={15} className={refrescando ? 'animate-spin' : ''} />
                            </button>
                            {visorActivo && (
                                <button
                                    onClick={() => setVerVisor(true)}
                                    title="Leer ventas del cuaderno con una foto"
                                    aria-label="Leer ventas del cuaderno con una foto"
                                    className="relative flex items-center gap-1 h-10 px-3 flex-shrink-0 rounded-xl text-[13px] font-semibold transition-colors hover:brightness-95"
                                    style={{ color: 'var(--vp-navy)', backgroundColor: 'var(--vp-sky-light)' }}
                                >
                                    <Camera size={16} /> <span className="hidden sm:inline">Cuaderno</span>
                                    {(() => {
                                        const porCargar = lecturaVisor?.ventas.filter(v => !v.cargada && !v.saltada && !(v.ya_cobrada && !v.forzar)).length ?? 0;
                                        return porCargar > 0 && (
                                            <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full text-[11px] font-bold text-white flex items-center justify-center"
                                                style={{ backgroundColor: 'var(--color-primary)' }} title={`${porCargar} por cargar`}>
                                                {porCargar}
                                            </span>
                                        );
                                    })()}
                                </button>
                            )}
                            {puedeCrearProducto && (
                                <button
                                    onClick={() => { setNombreNuevoProducto(''); setModalNuevoProducto(true); }}
                                    title="Crear un producto y agregarlo al carrito"
                                    className="flex items-center gap-1 h-10 px-3 flex-shrink-0 rounded-xl text-[13px] font-semibold transition-colors hover:brightness-95"
                                    style={{ color: 'var(--color-primary)', backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)' }}
                                >
                                    <Plus size={15} /> <span className="hidden sm:inline">Nuevo</span>
                                </button>
                            )}
                        </div>

                        {/* Un solo filtro: Todo · Servicios (si hay) · categorías.
                            Con búsqueda activa se atenúa: se busca en todo el catálogo. */}
                        {(categorias.length > 0 || hayServicios) && (
                            <div className="flex gap-1.5 overflow-x-auto pb-0.5 scrollbar-hide transition-opacity" style={{ opacity: busqueda ? 0.5 : 1 }}>
                                {[
                                    { key: 'todo', label: 'Todo', activo: !categoriaActiva && !tipoActivo, Icono: Layers as LucideIcon | null,
                                      elegir: () => { setCategoriaActiva(null); setTipoActivo(null); } },
                                    ...(hayServicios ? [{ key: 'servicios', label: 'Servicios', activo: tipoActivo === 'servicio', Icono: Wrench as LucideIcon | null,
                                      elegir: () => { setCategoriaActiva(null); setTipoActivo(tipoActivo === 'servicio' ? null : 'servicio'); } }] : []),
                                    ...categorias.map(cat => ({ key: `c-${cat}`, label: cat, activo: categoriaActiva === cat, Icono: null as LucideIcon | null,
                                      elegir: () => { setTipoActivo(null); setCategoriaActiva(cat === categoriaActiva ? null : cat); } })),
                                ].map(({ key, label, activo, Icono, elegir }) => (
                                    <button
                                        key={key}
                                        onClick={elegir}
                                        aria-pressed={activo}
                                        className="flex-shrink-0 flex items-center gap-1 h-8 text-[13px] font-medium px-3 rounded-full transition-colors whitespace-nowrap"
                                        style={{
                                            backgroundColor: activo ? 'var(--color-primary)' : 'var(--color-bg)',
                                            color: activo ? '#fff' : 'var(--color-text-muted)',
                                            border: `1px solid ${activo ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                        }}
                                    >
                                        {Icono && <Icono size={13} />}
                                        {label}
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
                                    {busqueda && puedeCrearProducto && (
                                        <Button size="sm" onClick={() => { setNombreNuevoProducto(busqueda.trim()); setModalNuevoProducto(true); }} startContent={<Plus size={14} />}>
                                            Crear «{busqueda.trim().slice(0, 40)}»
                                        </Button>
                                    )}
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
                            <SelectorComprobante
                                valor={tipoComprobante}
                                feActiva={feActiva}
                                onChange={v => setTipoComprobante(v as TipoComprobante)}
                            />
                            {muestraFechaFactura && ventanaEmision && (
                                <input
                                    type="date"
                                    value={fechaEmision}
                                    min={ventanaEmision.minima}
                                    max={ventanaEmision.maxima}
                                    onChange={e => setFechaEmision(e.target.value || ventanaEmision.maxima)}
                                    aria-label="Fecha de emisión de la factura"
                                    className="text-xs border rounded-lg px-2 py-1.5"
                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                />
                            )}
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
                        problemaCobro={carrito.length > 0 ? problemaCobro()?.texto ?? null : null}
                        puedeVender={puedeVender}
                        razonNoVender={razonNoVender}
                        bloqueoComprobante={bloqueoComprobante}
                        esCredito={esCredito}
                        fechaVencimiento={fechaVencimiento}
                        onSetEsCredito={activarCredito}
                        onSetFechaVencimiento={setFechaVencimiento}
                        anticipoSeleccionado={anticipoSeleccionado}
                        montoAnticipoUsado={montoAnticipoUsado}
                        slotComprobante={slotComprobante}
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
                                problemaCobro={carrito.length > 0 ? problemaCobro()?.texto ?? null : null}
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
                                <span className="text-base font-bold">{sim} {total.toFixed(2)}</span>
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

            {puedeCrearProducto && (
                <ModalNuevoProducto
                    isOpen={modalNuevoProducto}
                    onClose={() => setModalNuevoProducto(false)}
                    nombreInicial={nombreNuevoProducto}
                    ventaId={ventaEnEdicion?.id ?? null}
                    onCreado={p => {
                        // Entra al carrito como uno buscado y queda en el catálogo de la pantalla.
                        setListaProductos(prev => [p, ...prev.filter(x => x.id !== p.id)]);
                        setBusqueda('');
                        agregarProducto(p);
                    }}
                />
            )}

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

            <Modal isOpen={avisoEnvio} onClose={() => setAvisoEnvio(false)} title="¿Recoge en tienda o es un envío?" size="sm"
                footer={<>
                    <Button variant="secondary" onClick={() => { avisoRespondido.current = true; setAvisoEnvio(false); setModalConfirm(true); }}>
                        Recoge en tienda
                    </Button>
                    <Button onClick={() => { setAvisoEnvio(false); elegirTipoEntrega('envio'); }} startContent={<Truck size={15} />}>
                        Es un envío
                    </Button>
                </>}>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Esta venta suma <strong>{sim} {total.toFixed(2)}</strong> y está marcada como recojo en tienda.
                    Si hay que llevársela al cliente, márcala como envío para que salga en los despachos con su dirección y su hora.
                </p>
            </Modal>

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
                simbolo={sim}
            />

            {visorVentas && visorActivo && (
                <ModalVisorVentas
                    isOpen={verVisor}
                    onClose={() => setVerVisor(false)}
                    limite={visorVentas.limite}
                    restantes={restantesVisor}
                    onRestantes={setRestantesVisor}
                    lectura={lecturaVisor}
                    onLectura={setLecturaVisor}
                    carritoVacio={carrito.length === 0}
                    permiteDuplicar={permiteDuplicarItems}
                    onCargar={cargarVentaLeida}
                />
            )}

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
    // Qué falta para poder cobrar (null = listo). Se muestra EN el botón.
    problemaCobro: string | null;
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
    permitirCredito: boolean;
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
    entrega: 'completa' | 'pendiente' | 'despacho';
    onElegirEntrega: (e: 'completa' | 'pendiente' | 'despacho') => void;
    usaDespachoAlmacen: boolean;
    envioPendiente: boolean;
    envioSaleAlEntregar: boolean;
    slotEntrega: React.ReactNode;
    // Autofoco del precio en líneas recién agregadas con precio base 0.
    nuevaLineaPrecioKey: string | null;
    onAutoFocusPrecio: () => void;
    // Anticipo de efectivo aplicado a la venta.
    anticipoSeleccionado: number | null;
    montoAnticipoUsado: number;
    // Comprobante arriba del carrito (solo pantalla grande; en el cajón móvil va en otra parte).
    slotComprobante?: React.ReactNode;
    // Línea recién agregada → se ilumina (ver CarritoItem).
    pulsos: Record<string, number>;
    // "Recojo en tienda" / "Puesto en obra" (null si la empresa no usa entregas).
    resumenEntrega: string | null;
    // Las opciones se muestran abiertas sí o sí (envío con datos por llenar).
    forzarOpciones: boolean;
    simbolo: string;
    // Total anotado en el cuaderno de la venta cargada por el visor.
    totalCuaderno: number | null;
}

function CarritoPanel({
    carrito, pagos, conceptosDescuento, historial, metodosPago,
    cliente, onAbrirCliente,
    descuentoTotal, descuentoConceptoId,
    subtotal, igv, total, baseGravada, baseExonerada, tasaIgv, inactivosCount,
    onCambiarCantidad, onEstablecerCantidad, onCambiarPrecio, onAplicarDescuentoItem, onEliminarItem,
    onLimpiarCarrito, onSetDescuento, onSetPagos, onConfirmar, problemaCobro,
    puedeVender, razonNoVender, bloqueoComprobante,
    esCredito, fechaVencimiento, onSetEsCredito, onSetFechaVencimiento,
    permitirCredito, permitirPendiente, entregaPendiente, despachoAlmacen, fechaEntrega, pendienteDe, totalPendientes,
    onSetEntregaPendiente, onSetDespachoAlmacen, onSetFechaEntrega, onSetPendiente,
    entrega, onElegirEntrega,
    usaDespachoAlmacen, envioPendiente, envioSaleAlEntregar, slotEntrega,
    nuevaLineaPrecioKey, onAutoFocusPrecio,
    anticipoSeleccionado, montoAnticipoUsado,
    slotComprobante, pulsos, resumenEntrega, forzarOpciones, simbolo, totalCuaderno,
}: CarritoPanelProps) {
    // Opciones de la venta (recojo/envío, crédito, por entregar, despacho): casi
    // siempre quedan en su valor normal, así que van RESUMIDAS en una línea del
    // pie con "Cambiar". Se abren al pedirlo o si hay datos que llenar.
    const [verOpciones, setVerOpciones] = useState(false);
    const opcionesRef = useRef<HTMLDivElement | null>(null);
    const hayModalidad = permitirCredito || permitirPendiente || usaDespachoAlmacen || envioSaleAlEntregar;
    const hayOpciones  = hayModalidad || !!slotEntrega;
    const mostrarOpciones = verOpciones || forzarOpciones;
    useEffect(() => {
        if (verOpciones) opcionesRef.current?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }, [verOpciones]);
    const especiales = [
        esCredito && MODALIDADES.credito,
        entrega !== 'completa' && MODALIDADES[entrega],
    ].filter(Boolean) as (typeof MODALIDADES)[keyof typeof MODALIDADES][];

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

    const unidades = carrito.reduce((s, i) => s + i.cantidad, 0);
    // El comprobante no se puede emitir por falta de cliente → la fila del
    // cliente misma lo dice y ofrece arreglarlo.
    const faltaCliente = !!bloqueoComprobante?.requiereCliente;

    return (
        <>
            {/* ── Para quién y con qué documento ───────────────────────
                Una sola zona arriba: comprobante (pantalla grande) y cliente.
                Nada de cabecera "Carrito" que compita con la barra azul. */}
            <div
                className="px-3 pt-3 pb-2.5 bajo:pt-2 bajo:pb-2 flex flex-col gap-2 bajo:gap-1.5 flex-shrink-0"
                style={{ borderBottom: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
            >
                {slotComprobante}

                <button
                    type="button"
                    onClick={onAbrirCliente}
                    aria-label={`Cliente: ${clienteNombre}. Cambiar cliente`}
                    className="group flex items-center gap-2.5 w-full text-left rounded-lg px-2 py-1.5 bajo:py-1 -mx-0 transition-colors"
                    style={{
                        border: `1px solid ${faltaCliente ? 'var(--color-danger)' : 'var(--color-border)'}`,
                        backgroundColor: faltaCliente ? 'color-mix(in srgb, var(--color-danger) 6%, var(--color-surface))' : 'var(--color-surface)',
                    }}
                >
                    <span
                        className="flex h-8 w-8 bajo:h-7 bajo:w-7 flex-shrink-0 items-center justify-center rounded-full text-[13px] font-bold"
                        style={esClienteGeneral
                            ? { backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }
                            : { backgroundColor: 'var(--vp-navy)', color: '#fff' }}
                    >
                        {esClienteGeneral ? <User size={16} /> : clienteInicial}
                    </span>
                    <span className="flex-1 min-w-0">
                        <span className="block text-[13px] font-semibold truncate leading-tight" style={{ color: 'var(--color-text)' }}>
                            {clienteNombre}
                        </span>
                        <span className="block text-[12px] truncate leading-tight mt-0.5"
                            style={{ color: faltaCliente ? 'var(--vp-coral-ink)' : 'var(--color-text-muted)' }}>
                            {faltaCliente ? bloqueoComprobante!.motivo : clienteDoc ?? 'Sin documento'}
                        </span>
                    </span>
                    <span className="flex items-center gap-0.5 text-[12px] font-semibold flex-shrink-0"
                        style={{ color: faltaCliente ? 'var(--vp-coral-ink)' : 'var(--color-primary)' }}>
                        {faltaCliente ? 'Elegir' : 'Cambiar'} <ChevronDown size={14} />
                    </span>
                </button>

                {/* Bloqueo del comprobante que NO se arregla con el cliente (p. ej. fecha). */}
                {bloqueoComprobante && !faltaCliente && (
                    <Aviso tono="error">{bloqueoComprobante.motivo}</Aviso>
                )}
            </div>

            {/* ── Zona de scroll única ─────────────────────────────────
                Productos + descuento + modalidad + pago + desglose comparten
                UN scroll. Abajo queda fijo solo lo esencial: estado del pago,
                TOTAL y Cobrar. */}
            <div className="flex-1 overflow-y-auto px-3 py-2.5 bajo:py-1.5 flex flex-col gap-3 bajo:gap-2 min-h-[7rem]">
                {hayInactivos && (
                    <Aviso tono="error" titulo={`${inactivosCount === 1 ? 'Hay 1 producto que ya no se vende' : `Hay ${inactivosCount} productos que ya no se venden`}`}>
                        Quítalos del carrito o pide al administrador que los reactive.
                    </Aviso>
                )}

                {totalCuaderno != null && carrito.length > 0 && (Math.abs(totalCuaderno - total) > 0.009 ? (
                    <Aviso tono="aviso">
                        El cuaderno dice <strong>S/ {totalCuaderno.toFixed(2)}</strong>; el carrito suma S/ {total.toFixed(2)}. Ajusta precios o cantidades si cobraste distinto.
                    </Aviso>
                ) : (
                    // Coincide: basta una línea, el espacio es para los productos.
                    <p className="flex items-center gap-1.5 px-1 text-[12px] font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>
                        <CheckCircle2 size={13} /> Igual al cuaderno (S/ {totalCuaderno.toFixed(2)})
                    </p>
                ))}

                {carrito.length === 0 ? (
                    <div className="flex flex-col items-center justify-center flex-1 gap-2 py-10 text-center" style={{ color: 'var(--color-text-muted)' }}>
                        <ShoppingCart size={40} className="opacity-25" />
                        <p className="text-[13px] font-semibold" style={{ color: 'var(--color-text)' }}>Aún no hay productos</p>
                        <p className="text-[12px]">Búscalo arriba o tócalo en la lista para agregarlo.</p>
                    </div>
                ) : (
                    <section aria-label="Productos de la venta">
                        <div className="flex items-center justify-between gap-2 mb-1.5">
                            <TituloSeccion>
                                Productos
                                <span className="text-[12px] font-semibold tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
                                    {carrito.length === 1 ? '1 línea' : `${carrito.length} líneas`} · {+unidades.toFixed(4)} und
                                </span>
                            </TituloSeccion>
                            <button
                                onClick={onLimpiarCarrito}
                                className="text-[12px] font-semibold px-2 py-1 rounded-md transition-colors hover:bg-red-50"
                                style={{ color: 'var(--vp-coral-ink)' }}
                            >
                                Vaciar
                            </button>
                        </div>
                        <ul className="rounded-xl overflow-hidden [&>li:first-child]:border-t-0"
                            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
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
                                    pulso={pulsos[item.key]}
                                    simbolo={simbolo}
                                />
                            ))}
                        </ul>
                    </section>
                )}

                {carrito.length > 0 && (
                    <>
                        {/* Descuento a toda la venta: acción propia y discreta; al
                            abrirla muestra su panel completo. */}
                        <PanelDescuento
                            descuentoTotal={descuentoTotal}
                            descuentoConceptoId={descuentoConceptoId}
                            base={subtotal}
                            conceptos={conceptosDescuento}
                            onChange={onSetDescuento}
                        />

                        {/* Opciones de la venta: se abren desde "Cambiar" en el pie. */}
                        {hayOpciones && mostrarOpciones && (
                            <div ref={opcionesRef} className="flex flex-col gap-3 scroll-mt-2">
                                {slotEntrega}
                                {hayModalidad && (
                                    <ModalidadVenta
                                        esCredito={esCredito}
                                        onCredito={onSetEsCredito}
                                        entrega={entrega}
                                        onEntrega={onElegirEntrega}
                                        credito={permitirCredito}
                                        pendiente={permitirPendiente || envioSaleAlEntregar}
                                        despacho={usaDespachoAlmacen}
                                        envio={envioSaleAlEntregar}
                                    />
                                )}
                                {verOpciones && !forzarOpciones && (
                                    <button type="button" onClick={() => setVerOpciones(false)}
                                        className="self-end text-[12px] font-semibold px-2 py-1 rounded-md hover:bg-black/5"
                                        style={{ color: 'var(--color-primary)' }}>
                                        Listo
                                    </button>
                                )}
                            </div>
                        )}

                        {/* F1 — Venta a crédito: detalle, solo si está marcada */}
                        {permitirCredito && esCredito && (<div
                            className="rounded-xl px-3 py-2.5"
                            style={{
                                border: `1px solid ${MODALIDADES.credito.borde}`,
                                backgroundColor: MODALIDADES.credito.tinte,
                            }}
                        >
                            {esCredito && (
                                <div className="space-y-1.5">
                                    <div className="flex items-center gap-2">
                                        <span className="text-[12px] flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>
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
                                    <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                                        El pago inicial es opcional; el saldo queda como cuenta por cobrar.
                                    </p>
                                </div>
                            )}
                        </div>)}

                        {/* Pendiente por entregar: pagó todo, se lleva solo parte.
                            El POS crea el anticipo material en Finanzas solo;
                            el stock pendiente sale recién al entregarse. */}
                        {(permitirPendiente || envioPendiente) && entregaPendiente && (
                            <div
                                className="rounded-xl px-3 py-2.5"
                                style={{
                                    border: `1px solid ${MODALIDADES.pendiente.borde}`,
                                    backgroundColor: MODALIDADES.pendiente.tinte,
                                }}
                            >
                                {entregaPendiente && (
                                    <div className="space-y-2">
                                        <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                                            {envioPendiente
                                                ? <>Si el cliente <strong>se lleva algo ahora</strong>, indícalo; el resto queda en Despachos para el envío.</>
                                                : <>Indica cuánto <strong>se lleva ahora</strong> de cada producto; el resto queda pendiente y se registra solo en Finanzas → Anticipos.</>}
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
                                                            data-pendiente-input
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
                                        {/* En un envío la fecha es la programada, arriba. */}
                                        {!envioPendiente && (
                                            <div className="flex items-center gap-2">
                                                <span className="text-[12px] flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>
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
                                        )}
                                        {totalPendientes > 0 ? (
                                            <p className="text-[12px] font-medium" style={{ color: 'var(--color-warning)' }}>
                                                {totalPendientes} und quedarán {envioPendiente ? 'para el envío' : 'pendientes por entregar'} (no salen del stock hasta entregarse).
                                            </p>
                                        ) : (
                                            <p className="text-[12px]" style={{ color: 'var(--color-danger)' }}>
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
                        {usaDespachoAlmacen && despachoAlmacen && (
                            <div
                                className="rounded-xl px-3 py-2.5"
                                style={{
                                    border: `1px solid ${MODALIDADES.despacho.borde}`,
                                    backgroundColor: MODALIDADES.despacho.tinte,
                                }}
                            >
                                {despachoAlmacen && (
                                    <div className="space-y-2">
                                        <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                                            Toda la mercadería quedará pendiente de despacho. El almacenero la verá en su bandeja y confirmará la entrega; el stock saldrá del almacén en ese momento.
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <span className="text-[12px] flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>
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

                    </>
                )}
            </div>

            {/* ── Pie FIJO: opciones + cómo paga + TOTAL + Cobrar ─────── */}
            <div
                className="flex-shrink-0 px-3 pt-2 bajo:pt-1.5 flex flex-col gap-2 bajo:gap-1.5"
                style={{
                    borderTop: '1px solid var(--color-border)',
                    backgroundColor: 'var(--color-surface)',
                    boxShadow: '0 -8px 20px -14px rgb(15 76 129 / 0.35)',
                    paddingBottom: 'calc(10px + env(safe-area-inset-bottom, 0px))',
                }}
            >
                {/* Opciones y pago con su propio scroll: con un pago dividido (cuentas,
                    n.º de operación) crece, pero el TOTAL y Cobrar nunca salen de la vista. */}
                <div className="flex flex-col gap-2 bajo:gap-1.5 overflow-y-auto max-h-[40vh] bajo:max-h-[30vh] -mx-1 px-1 empty:hidden">
                {/* Opciones de la venta, resumidas: lo normal en gris, lo especial
                    (crédito, por entregar, despacho) con su color. */}
                {carrito.length > 0 && hayOpciones && (
                    <div className="flex items-center gap-2 px-1 min-h-[28px]">
                        <div className="flex-1 min-w-0 flex flex-wrap items-center gap-1.5 text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                            {resumenEntrega && <span className="font-medium">{resumenEntrega}</span>}
                            {especiales.length === 0 ? (
                                <span>{resumenEntrega ? '· ' : ''}Contado, se lleva todo</span>
                            ) : especiales.map(m => (
                                <span key={m.label} className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-bold"
                                    style={{ backgroundColor: m.fondo, color: m.texto }}>
                                    <m.Icono size={12} /> {m.label}
                                </span>
                            ))}
                        </div>
                        {!forzarOpciones && (
                            <button type="button" onClick={() => setVerOpciones(v => !v)} aria-expanded={verOpciones}
                                className="flex items-center gap-0.5 flex-shrink-0 text-[12px] font-semibold px-2 py-1 rounded-md hover:bg-black/5"
                                style={{ color: 'var(--color-primary)' }}>
                                {verOpciones ? 'Ocultar' : 'Cambiar'}
                                <ChevronDown size={14} className={`transition-transform ${verOpciones ? 'rotate-180' : ''}`} />
                            </button>
                        )}
                    </div>
                )}

                {/* Cómo paga: SIEMPRE a la vista, pegado al total. Antes quedaba
                    al fondo del scroll y con 3 productos ya no se veía. */}
                {carrito.length > 0 && (
                    <PanelPago
                        compacto
                        pagos={pagos}
                        metodosPago={metodosPago}
                        total={total}
                        anticipoMonto={montoAnticipoUsado}
                        esCredito={esCredito}
                        onChange={onSetPagos}
                        simbolo={simbolo}
                    />
                )}
                </div>

                {/* TOTAL + estado del pago en el mismo bloque: lo que se cobra y si ya está cubierto. */}
                <div
                    className="rounded-xl px-4 py-2.5 bajo:py-1.5 text-white"
                    style={{
                        background: 'linear-gradient(135deg, var(--vp-sky), var(--vp-navy))',
                        boxShadow: '0 6px 16px -8px rgb(15 76 129 / 0.55)',
                    }}
                >
                    <div className="flex items-center justify-between gap-3">
                        <span className="min-w-0">
                            <span className="flex items-center gap-2 text-[13px] font-bold tracking-wide">
                                TOTAL
                                {(esCredito || entrega !== 'completa') && (
                                    <span className="text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md bg-white/20">
                                        {[esCredito && MODALIDADES.credito.enTotal, entrega !== 'completa' && MODALIDADES[entrega].enTotal]
                                            .filter(Boolean).join(' · ')}
                                    </span>
                                )}
                            </span>
                            {/* Las bases SUMAN el total, igual que en el comprobante impreso. */}
                            {carrito.length > 0 && (
                                <span className="block text-[11px] tabular-nums mt-0.5 bajo:mt-0" style={{ color: 'rgb(255 255 255 / 0.78)' }}>
                                    Op. gravada {baseGravada.toFixed(2)}
                                    {baseExonerada > 0 && <> · Exonerada {baseExonerada.toFixed(2)}</>}
                                    {' · '}IGV {igv.toFixed(2)}
                                    {descuentoTotal > 0 && <> · Dcto −{descuentoTotal.toFixed(2)}</>}
                                </span>
                            )}
                        </span>
                        <span className="font-display text-[24px] bajo:text-[21px] font-extrabold tabular-nums leading-none">{simbolo} {total.toFixed(2)}</span>
                    </div>
                    {carrito.length > 0 && (pagos.length > 0 || esCredito || anticipoSeleccionado) && (() => {
                        const totalPagado = pagos.reduce((s, p) => s + p.monto, 0) + montoAnticipoUsado;
                        const falta  = Math.max(0, total - totalPagado);
                        const vuelto = pagos.some(p => p.admite_vuelto) ? Math.max(0, totalPagado - total) : 0;
                        // Pago exacto sin nada especial: no hay nada que decir (ahorra una fila).
                        if (!esCredito && !anticipoSeleccionado && falta <= 0.009 && vuelto <= 0.009) return null;
                        const [texto, fondo] = esCredito
                            ? [`Saldo a crédito ${simbolo} ${falta.toFixed(2)}`, 'rgb(255 255 255 / 0.18)']
                            : falta > 0.009
                                ? [`Falta ${simbolo} ${falta.toFixed(2)}`, 'var(--vp-amber)']
                                : vuelto > 0.009
                                    ? [`Vuelto ${simbolo} ${vuelto.toFixed(2)}`, 'var(--vp-mint)']
                                    : ['Pago completo', 'var(--vp-mint)'];
                        const oscuro = !esCredito; // ámbar y menta llevan texto oscuro
                        return (
                            <div className="flex items-center justify-between gap-2 mt-1.5 pt-1.5 text-[12px]" style={{ borderTop: '1px solid rgb(255 255 255 / 0.2)' }}>
                                <span className="tabular-nums" style={{ color: 'rgb(255 255 255 / 0.85)' }}>
                                    {esCredito ? 'Pago inicial' : 'Pagado'} <strong className="text-white">{simbolo} {totalPagado.toFixed(2)}</strong>
                                    {anticipoSeleccionado && montoAnticipoUsado > 0.009 && <> · anticipo {simbolo} {montoAnticipoUsado.toFixed(2)}</>}
                                </span>
                                <span className="flex items-center gap-1 px-2 py-0.5 rounded-md font-bold tabular-nums whitespace-nowrap"
                                    style={{ backgroundColor: fondo, color: oscuro ? '#0F1923' : '#fff' }}>
                                    {!esCredito && falta <= 0.009 && <CheckCircle2 size={13} />}
                                    {texto}
                                </span>
                            </div>
                        );
                    })()}
                </div>

                {/* El POS no puede vender (sin local, almacén apagado…): dicho y sin botón engañoso. */}
                {!puedeVender && razonNoVender && <Aviso tono="error">{razonNoVender}</Aviso>}

                {/* Botón cobrar. Si falta algo, el botón LO DICE y al pulsarlo lleva
                    al lugar que hay que corregir: nada de adivinar. Listo → verde. */}
                {problemaCobro && puedeVender ? (
                    <button
                        type="button"
                        onClick={onConfirmar}
                        className="w-full flex items-center gap-2 h-12 bajo:h-10 px-3 rounded-xl text-[13px] font-bold text-left transition-colors hover:brightness-[0.98]"
                        style={{
                            backgroundColor: 'color-mix(in srgb, var(--vp-amber) 16%, var(--color-surface))',
                            border: '1.5px solid var(--vp-amber)',
                            color: 'var(--vp-amber-ink)',
                        }}
                    >
                        <AlertTriangle size={17} className="flex-shrink-0" />
                        <span className="flex-1 min-w-0 leading-tight line-clamp-2">{problemaCobro}</span>
                        <span className="flex items-center gap-0.5 text-[12px] flex-shrink-0 opacity-90">
                            Corregir <ArrowRight size={14} />
                        </span>
                    </button>
                ) : (
                    <Button
                        variant="success"
                        size="lg"
                        radius="lg"
                        className="w-full !h-12 bajo:!h-10 !text-[15px] !font-bold"
                        onClick={onConfirmar}
                        disabled={carrito.length === 0 || !puedeVender}
                        title={!puedeVender ? (razonNoVender ?? 'No puedes registrar ventas en este momento.') : undefined}
                    >
                        {!puedeVender ? 'POS bloqueado'
                         : carrito.length === 0 ? 'Cobrar venta'
                         : total > 0 ? `Cobrar ${simbolo} ${total.toFixed(2)}`
                         : 'Registrar venta sin cobro'}
                    </Button>
                )}
            </div>
        </>
    );
}

/**
 * Aviso dentro del carrito, con un solo lenguaje para todo el POS:
 *   error → no se puede seguir así (rojo).   aviso → revisa esto (ámbar).
 *   info  → para que sepas (azul).
 * El texto dice el problema y qué hacer; la acción, si hay, va como botón.
 */
function Aviso({ tono, titulo, children, accion }: {
    tono:     'error' | 'aviso' | 'info';
    titulo?:  string;
    children: React.ReactNode;
    accion?:  { label: string; onClick: () => void };
}) {
    const t = {
        error: { borde: 'var(--color-danger)', fondo: 'color-mix(in srgb, var(--color-danger) 8%, var(--color-surface))', tinta: 'var(--vp-coral-ink)', Icono: AlertTriangle },
        aviso: { borde: 'var(--vp-amber)',     fondo: 'color-mix(in srgb, var(--vp-amber) 12%, var(--color-surface))',    tinta: 'var(--vp-amber-ink)', Icono: AlertTriangle },
        info:  { borde: 'var(--vp-sky)',       fondo: 'var(--vp-sky-light)',                                              tinta: 'var(--vp-navy)',      Icono: Info },
    }[tono];
    return (
        <div role={tono === 'info' ? 'status' : 'alert'} className="flex items-start gap-2 rounded-lg px-3 py-2 text-[12px] leading-snug"
            style={{ backgroundColor: t.fondo, border: `1px solid ${t.borde}`, color: t.tinta }}>
            <t.Icono size={15} className="flex-shrink-0 mt-px" />
            <div className="flex-1 min-w-0">
                {titulo && <p className="text-[13px] font-bold">{titulo}</p>}
                <div className={titulo ? '' : 'font-semibold'}>{children}</div>
            </div>
            {accion && (
                <button type="button" onClick={accion.onClick} className="flex-shrink-0 text-[12px] font-bold underline underline-offset-2 hover:opacity-80">
                    {accion.label}
                </button>
            )}
        </div>
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
                className="inline-flex items-center gap-1 text-[12px] font-bold px-1.5 py-0.5 rounded whitespace-nowrap"
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
            className="text-[12px] font-semibold whitespace-nowrap"
            style={sobrePrimario ? { color: 'rgba(255,255,255,0.9)' } : { color: 'var(--color-text-muted)' }}
            title="Serie con la que se emitirá el comprobante"
        >
            {serie}
        </span>
    );
}

/** Colores de cada modalidad, tomados de la paleta de la marca (variables.css). */
const MODALIDADES = {
    credito:   { label: 'Crédito',      enTotal: 'A crédito',    Icono: CreditCard, fondo: 'var(--vp-sky)',   texto: '#fff',    tinte: 'var(--vp-sky-light)', borde: 'color-mix(in srgb, var(--vp-sky) 40%, transparent)',   ayuda: 'El cliente paga después: el saldo queda en Cuentas por cobrar.' },
    pendiente: { label: 'Por entregar', enTotal: 'Por entregar', Icono: Truck,      fondo: 'var(--vp-amber)', texto: '#3b2a00', tinte: 'color-mix(in srgb, var(--vp-amber) 12%, #fff)', borde: 'color-mix(in srgb, var(--vp-amber) 55%, transparent)', ayuda: 'Paga todo, pero se lleva solo una parte ahora.' },
    despacho:  { label: 'Despacho',     enTotal: 'Despacho',     Icono: Package,    fondo: 'var(--vp-navy)',  texto: '#fff',    tinte: 'color-mix(in srgb, var(--vp-navy) 7%, #fff)', borde: 'color-mix(in srgb, var(--vp-navy) 35%, transparent)', ayuda: 'Paga ahora; el almacén entrega la mercadería después.' },
} as const;

/** Título de sección con la barra de acento de la marca (la misma de los modales). */
function TituloSeccion({ children, extra }: { children: React.ReactNode; extra?: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-2">
            <h3 className="flex items-center gap-2 text-[13px] font-bold" style={{ color: 'var(--vp-navy)' }}>
                <span className="h-3.5 w-1 rounded-full flex-shrink-0" style={{ background: 'linear-gradient(180deg, var(--vp-sky), var(--vp-mint))' }} />
                {children}
            </h3>
            {extra}
        </div>
    );
}

/**
 * Modalidad de la venta en DOS preguntas independientes:
 *   Pago:    Contado | Crédito
 *   Entrega: Se lleva todo | Por entregar | Despacho
 * Se pueden combinar (p. ej. Crédito + Despacho). Lo normal se marca en
 * tono tranquilo (borde + ✓); lo excepcional se RELLENA de su color para
 * que la cajera vea de un vistazo que es una venta especial.
 */
function ModalidadVenta({ esCredito, onCredito, entrega, onEntrega, credito, pendiente, despacho, envio = false }: {
    esCredito: boolean;
    onCredito: (v: boolean) => void;
    entrega:   'completa' | 'pendiente' | 'despacho';
    onEntrega: (e: 'completa' | 'pendiente' | 'despacho') => void;
    credito:   boolean;
    pendiente: boolean;
    despacho:  boolean;
    /** Envío: la primera opción se llama "Entregado" (sale ya con el envío). */
    envio?: boolean;
}) {
    const hayEntrega = pendiente || despacho;

    return (
        <div className="space-y-1.5">
            <TituloSeccion>Modalidad</TituloSeccion>
            <div className="rounded-xl p-2.5 space-y-2.5" style={{ backgroundColor: 'var(--color-surface)', boxShadow: '0 1px 3px rgba(15,23,42,0.08)' }}>
                {credito && (
                    <FilaModalidad etiqueta="Pago">
                        <OpcionModalidad normal activo={!esCredito} onClick={() => onCredito(false)} Icono={Banknote} label="Contado" />
                        <OpcionModalidad activo={esCredito} onClick={() => onCredito(true)} Icono={MODALIDADES.credito.Icono} label={MODALIDADES.credito.label} m={MODALIDADES.credito} />
                    </FilaModalidad>
                )}
                {hayEntrega && (
                    <FilaModalidad etiqueta="Entrega">
                        <OpcionModalidad normal activo={entrega === 'completa'} onClick={() => onEntrega('completa')}
                            Icono={envio ? PackageCheck : ShoppingBag} label={envio ? 'Entregado' : 'Se lleva todo'} />
                        {pendiente && (
                            <OpcionModalidad activo={entrega === 'pendiente'} onClick={() => onEntrega('pendiente')} Icono={MODALIDADES.pendiente.Icono} label={MODALIDADES.pendiente.label} m={MODALIDADES.pendiente} />
                        )}
                        {despacho && (
                            <OpcionModalidad activo={entrega === 'despacho'} onClick={() => onEntrega('despacho')} Icono={MODALIDADES.despacho.Icono} label={MODALIDADES.despacho.label} m={MODALIDADES.despacho} />
                        )}
                    </FilaModalidad>
                )}
            </div>
        </div>
    );
}

/** "ENVÍO A OBRA" → "Envío a obra": el texto del ticket, en tono de botón. */
const comoFrase = (t: string) => t.charAt(0).toUpperCase() + t.slice(1).toLowerCase();

/**
 * Recojo en tienda o envío. En un envío se piden la ruta y la fecha y hora
 * programadas; la dirección y el teléfono se piden aquí solo si la empresa no
 * los pide ya en la franja de datos del cliente.
 */
function EntregaVenta({ entregas, tipo, onTipo, rutaId, onRuta, programada, onProgramada, datos, onDatos, pedirDatosAqui, entregado = false }: {
    entregas: EntregasPos;
    tipo: TipoEntrega;
    onTipo: (t: TipoEntrega) => void;
    rutaId: number | null;
    onRuta: (id: number | null) => void;
    programada: string;
    onProgramada: (v: string) => void;
    datos: DatosCliente;
    onDatos: (d: DatosCliente) => void;
    pedirDatosAqui: boolean;
    /** El envío se marcó "Entregado" en Modalidad. */
    entregado?: boolean;
}) {
    const campo = 'w-full text-sm rounded-lg px-2.5 py-1.5 border outline-none focus:ring-2';
    const estilo: React.CSSProperties = { borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' };

    return (
        <div className="space-y-1.5">
            <TituloSeccion>¿Cómo lo recibe?</TituloSeccion>
            <div className="rounded-xl p-2.5 space-y-2.5" style={{ backgroundColor: 'var(--color-surface)', boxShadow: '0 1px 3px rgba(15,23,42,0.08)' }}>
                <div className="grid grid-cols-2 gap-2">
                    <OpcionModalidad normal activo={tipo === 'recojo'} onClick={() => onTipo('recojo')} Icono={Store} label={comoFrase(entregas.texto_recojo)} />
                    <OpcionModalidad activo={tipo === 'envio'} onClick={() => onTipo('envio')} Icono={Truck} label={comoFrase(entregas.texto_envio)} m={MODALIDADES.despacho} />
                </div>

                {tipo === 'envio' && (
                    <div className="rounded-lg p-2.5 space-y-2" style={{ backgroundColor: MODALIDADES.despacho.tinte, border: `1px solid ${MODALIDADES.despacho.borde}` }}>
                        {entregas.rutas.length > 0 && (
                            <label className="block">
                                <span className="block text-xs font-semibold mb-1" style={{ color: 'var(--color-text)' }}>
                                    Ruta{entregas.ruta_obligatoria ? '' : ' (opcional)'}
                                </span>
                                <select data-envio-ruta value={rutaId ?? ''} onChange={e => onRuta(e.target.value ? Number(e.target.value) : null)} className={campo} style={estilo}>
                                    <option value="">Elegir ruta…</option>
                                    {entregas.rutas.map(r => <option key={r.id} value={r.id}>{r.nombre}{r.zona ? `, ${r.zona}` : ''}</option>)}
                                </select>
                            </label>
                        )}
                        <label className="block">
                            <span className="block text-xs font-semibold mb-1" style={{ color: 'var(--color-text)' }}>
                                Fecha y hora de entrega{entregas.fecha_obligatoria ? '' : ' (opcional)'}
                            </span>
                            <input data-envio-fecha type="datetime-local" value={programada} onChange={e => onProgramada(e.target.value)} className={campo} style={estilo} />
                        </label>
                        {pedirDatosAqui && (
                            <>
                                <label className="block">
                                    <span className="block text-xs font-semibold mb-1" style={{ color: 'var(--color-text)' }}>Dirección de entrega</span>
                                    <input data-envio-direccion type="text" maxLength={255} value={datos.direccion} placeholder="Calle, número, referencia"
                                        onChange={e => onDatos({ ...datos, direccion: e.target.value })} className={campo} style={estilo} />
                                </label>
                                <label className="block">
                                    <span className="block text-xs font-semibold mb-1" style={{ color: 'var(--color-text)' }}>Teléfono de contacto (opcional)</span>
                                    <input type="tel" inputMode="tel" maxLength={30} value={datos.telefono}
                                        onChange={e => onDatos({ ...datos, telefono: e.target.value })} className={campo} style={estilo} />
                                </label>
                            </>
                        )}
                        {entregas.envio_sale_al_entregar && (
                            <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                                {entregado
                                    ? 'Marcado como entregado: la mercadería sale del stock al cobrar, no queda en Despachos.'
                                    : 'La mercadería queda en Despachos y sale del stock cuando se confirma la entrega. Si sale ahora, marca "Entregado" en Modalidad.'}
                            </p>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function FilaModalidad({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center gap-3">
            <span className="w-14 flex-shrink-0 text-[12px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>
                {etiqueta}
            </span>
            <div className="flex-1 grid grid-flow-col auto-cols-fr gap-2">{children}</div>
        </div>
    );
}

function OpcionModalidad({ activo, onClick, Icono, label, normal = false, m }: {
    activo:  boolean;
    onClick: () => void;
    Icono:   LucideIcon;
    label:   string;
    normal?: boolean;
    m?:      { fondo: string; texto: string };
}) {
    // Normal activo: borde verde + ✓ (tranquilo). Excepcional activo: relleno.
    const estilo: React.CSSProperties = !activo
        ? { backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)', border: '1px solid transparent' }
        : normal
            ? { backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, #fff)', color: '#047857', border: '1.5px solid color-mix(in srgb, var(--color-success) 60%, transparent)' }
            : { backgroundColor: m!.fondo, color: m!.texto, border: '1.5px solid transparent', boxShadow: `0 3px 10px -4px ${m!.fondo}` };

    return (
        <button
            type="button"
            aria-pressed={activo}
            onClick={onClick}
            className="flex items-center justify-center gap-1.5 h-9 px-1.5 rounded-lg text-[12px] font-bold transition-all active:scale-[0.97] min-w-0"
            style={estilo}
        >
            {activo ? <CheckCircle2 size={14} className="flex-shrink-0" /> : <Icono size={14} className="flex-shrink-0" />}
            <span className="truncate">{label}</span>
        </button>
    );
}
