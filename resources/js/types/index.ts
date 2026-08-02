export type ModoCierreCaja = 'rapido' | 'con_declaraciones';
export type ModoCierreInventario = 'por_venta' | 'declarado';

/** Plantilla del ticket impreso; la edita el admin en Configuración → Empresas. */
export interface TicketConfig {
    cliente_celular?: boolean;
    cliente_direccion?: boolean;
    pie?: string | null;
    lineas_extra?: string[];
}

export interface Empresa extends Record<string, unknown> {
    id: number;
    razon_social: string;
    nombre_comercial: string | null;
    ruc: string;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    logo: string | null;
    ticket_config: TicketConfig | null;
    tasa_igv: number | string;
    modo_almacen: 'simple' | 'central_y_local';
    descuenta_stock_en_venta: boolean;
    permite_stock_negativo: boolean;
    modo_cierre_caja: ModoCierreCaja;
    modo_cierre_inventario: ModoCierreInventario;
    cierre_precarga_stock: boolean;
    usa_fondos_iniciales: boolean;
    fondos_iniciales_en_declaracion: boolean;
    requiere_consolidacion_caja: boolean;
    permite_devoluciones: boolean;
    dias_max_devolucion: number;
    requiere_aprobacion_devolucion: boolean;
    restock_default: boolean;
    /* ── Manejo de efectivo (opt-in por empresa) ── */
    modo_apertura_caja: 'libre' | 'arrastre' | 'fondo_fijo';
    apertura_editable: boolean;
    usa_retiros_caja: boolean;
    retiro_requiere_aprobacion: boolean;
    cierre_pregunta_destino: boolean;
    usa_caja_grande: boolean;
    /* "Afecta caja" por módulo (opt-in). Mapa resuelto (defaults ∪ config)
       que sirve el backend; lo lee el componente <AfectaCajaSelect> y la
       pantalla de Configuración → Empresa. */
    afecta_caja: Record<string, { activo: boolean; label: string; disponible: boolean }>;
    activo: boolean;
    created_at: string;
    updated_at: string;
}

export interface Local extends Record<string, unknown> {
    id: number;
    empresa_id: number;
    nombre: string;
    direccion: string | null;
    telefono: string | null;
    tipo: string | null;
    es_principal: boolean;
    activo: boolean;
    descuenta_stock_en_venta: boolean | null;
    modo_cierre_caja: ModoCierreCaja | null;
    modo_cierre_inventario: ModoCierreInventario | null;
    usa_fondos_iniciales: boolean | null;
    fondos_iniciales_en_declaracion: boolean | null;
    permite_devoluciones: boolean | null;
    dias_max_devolucion: number | null;
    requiere_aprobacion_devolucion: boolean | null;
    restock_default: boolean | null;
    empresa?: Empresa;
    created_at: string;
    updated_at: string;
}

export interface Rol extends Record<string, unknown> {
    id: number;
    empresa_id: number;
    nombre: string;
    descripcion: string | null;
    es_admin: boolean;
    activo: boolean;
    empresa?: Empresa;
    permisos?: Permiso[];
    created_at: string;
    updated_at: string;
}

export interface Modulo extends Record<string, unknown> {
    id: number;
    padre_id: number | null;
    nombre: string;
    slug: string;
    icono: string | null;
    ruta: string | null;
    orden: number;
    activo: boolean;
    padre?: Modulo;
    hijos?: Modulo[];
    created_at: string;
    updated_at: string;
}

export interface Permiso extends Record<string, unknown> {
    id: number;
    rol_id: number;
    modulo_id: number;
    ver: boolean;
    crear: boolean;
    editar: boolean;
    eliminar: boolean;
    modulo?: Modulo;
    created_at: string;
    updated_at: string;
}

export interface User extends Record<string, unknown> {
    id: number;
    // null solo para el superadmin global (panel /admin), que no pertenece a
    // ninguna empresa. Todo usuario de tenant la lleva siempre.
    empresa_id: number | null;
    local_id: number | null;
    rol_id: number | null;
    name: string;
    email: string;
    email_verified_at?: string | null;
    activo: boolean;
    es_superadmin?: boolean;
    empresa?: Empresa;
    local?: Local;
    rol?: Rol;
    created_at: string;
    updated_at: string;
}

export interface Categoria extends Record<string, unknown> {
    id: number;
    empresa_id: number;
    nombre: string;
    descripcion: string | null;
    activo: boolean;
    created_at: string;
    updated_at: string;
}

export interface UnidadMedida extends Record<string, unknown> {
    id: number;
    empresa_id: number;
    nombre: string;
    abreviatura: string;
    activo: boolean;
    created_at: string;
    updated_at: string;
}

export interface ProductoUnidad extends Record<string, unknown> {
    id: number;
    producto_id: number;
    unidad_medida_id: number;
    es_base: boolean;
    factor_conversion: string;
    tipo_precio: 'fijo' | 'referencial';
    precio_venta: string;
    precio_costo: string;
    activo: boolean;
    unidad_medida?: UnidadMedida;
    created_at: string;
    updated_at: string;
}

export interface Producto extends Record<string, unknown> {
    id: number;
    empresa_id: number;
    categoria_id: number | null;
    codigo: string | null;
    nombre: string;
    descripcion: string | null;
    tipo: 'producto' | 'servicio';
    tipo_precio: 'fijo' | 'referencial';
    precio_venta: string;
    precio_costo: string;
    imagen: string | null;
    activo: boolean;
    incluye_igv: boolean;
    controla_stock: boolean | null;
    es_retornable: boolean | null;
    // Stock disponible (unidad base) en el almacén de ventas; lo inyecta el POS.
    stock_disponible?: number | null;
    categoria?: Categoria | null;
    unidades?: ProductoUnidad[];
    unidad_base?: ProductoUnidad | null;
    created_at: string;
    updated_at: string;
}

export interface TipoMetodoPago {
    id:                    number;
    slug:                  string;
    nombre:                string;
    icono:                 string | null;
    admite_vuelto_default: boolean;
    requiere_referencia:   boolean;
}

export interface MetodoPago extends Record<string, unknown> {
    id:            number;
    nombre:        string;
    tipo_id:       number;
    tipo:          TipoMetodoPago | null;
    admite_vuelto: boolean;
    activo:        boolean;
}

export interface Caja extends Record<string, unknown> {
    id:                          number;
    empresa_id:                  number;
    local_id:                    number;
    nombre:                      string;
    caja_chica_activa:           boolean;
    caja_chica_monto_sugerido:   number;
    caja_chica_en_arqueo:        boolean;
    fondo_fijo_monto:            number | string;
    activo:                      boolean;
    token_impresora?:            string | null;
    local?:                      Local;
    tiene_turno_abierto?:        boolean;
    created_at:                  string;
    updated_at:                  string;
}

export interface GastoConcepto extends Record<string, unknown> {
    id:             number;
    empresa_id:     number;
    gasto_tipo_id:  number;
    nombre:         string;
    activo:         boolean;
    created_at:     string;
    updated_at:     string;
}

export interface GastoTipo extends Record<string, unknown> {
    id:         number;
    empresa_id: number;
    nombre:     string;
    categoria:  'administrativo' | 'operativo' | 'otro';
    activo:     boolean;
    conceptos?: GastoConcepto[];
    created_at: string;
    updated_at: string;
}

export interface Gasto extends Record<string, unknown> {
    id:                 number;
    empresa_id:         number;
    local_id:           number;
    user_id:            number;
    turno_id:           number | null;
    gasto_tipo_id:      number;
    gasto_concepto_id:  number;
    monto:              string;
    cuenta_id?:         number | null;
    fecha:              string;
    comentario:         string | null;
    tipo?:              GastoTipo;
    concepto?:          GastoConcepto;
    user?:              User;
    local?:             Local;
    turno?:             Turno;
    deleted_at?:        string | null;
    created_at:         string;
    updated_at:         string;
}

export interface TurnoArqueo extends Record<string, unknown> {
    id:           number;
    turno_id:     number;
    denominacion: number;
    cantidad:     number;
}

export interface TurnoArqueoMetodo extends Record<string, unknown> {
    id:               number;
    turno_id:         number;
    metodo_pago_id:   number;
    monto_declarado:  string;
    metodo_pago?:     MetodoPago;
}

export interface TurnoRetiro extends Record<string, unknown> {
    id:           number;
    empresa_id:   number;
    turno_id:     number;
    user_id:      number;
    aprobado_por: number | null;
    concepto:     string;
    monto:        string;
    momento:      'turno' | 'cierre';
    estado:       'registrado' | 'aprobado';
    observacion:  string | null;
    user?:        User;
    aprobado_por_user?: User | null;
    created_at:   string;
    updated_at:   string;
}

export interface Turno extends Record<string, unknown> {
    id:                      number;
    empresa_id:              number;
    local_id:                number;
    caja_id:                 number;
    user_id:                 number;
    user_cierre_id:          number | null;
    monto_apertura:          string;
    monto_caja_chica:        string;
    monto_cierre_declarado:  string | null;
    monto_cierre_esperado:   string | null;
    diferencia:              string | null;
    efectivo_arrastre:       string | null;
    destino_efectivo:        'caja' | 'administracion' | 'parcial' | null;
    estado:                  'abierto' | 'cerrado';
    fecha_apertura:          string;
    fecha_cierre:            string | null;
    observacion_apertura:    string | null;
    observacion_cierre:      string | null;
    caja?:                   Caja;
    local?:                  Local;
    user?:                   User;
    user_cierre?:            User | null;
    gastos?:                 Gasto[];
    ventas?:                 Venta[];
    retiros?:                TurnoRetiro[];
    arqueo?:                 TurnoArqueo[];
    arqueo_metodos?:         TurnoArqueoMetodo[];
    created_at:              string;
    updated_at:              string;
}

// ── Ventas / POS ─────────────────────────────────────────────────────────────

export interface DescuentoConcepto extends Record<string, unknown> {
    id:                   number;
    empresa_id:           number;
    nombre:               string;
    requiere_aprobacion:  boolean;
    activo:               boolean;
    created_at:           string;
    updated_at:           string;
}

export interface VentaItem extends Record<string, unknown> {
    id:                    number;
    venta_id:              number;
    producto_id:           number;
    producto_unidad_id:    number;
    producto_nombre:       string;
    unidad_nombre:         string;
    cantidad:              string;
    factor_conversion:     string;
    cantidad_base:         string;
    precio_unitario:       string;
    precio_original:       string;
    descuento_item:        string;
    descuento_concepto_id: number | null;
    subtotal:              string;
    producto?:             Producto;
    descuento_concepto?:   DescuentoConcepto | null;
    created_at:            string;
    updated_at:            string;
}

export interface Cuenta extends Record<string, unknown> {
    id:         number;
    empresa_id: number;
    nombre:     string;
    tipo:       string;
    numero:     string | null;
    activo:     boolean;
    created_at: string;
    updated_at: string;
    /** Presente cuando la cuenta viene de metodo.cuentas (belongsToMany):
     *  pivot.id es el cuenta_metodo_pago_id que espera el backend en el POS. */
    pivot?: { id: number; cuenta_id: number; metodo_pago_id: number };
}

export interface MetodoPagoConCuentas extends MetodoPago {
    cuentas?: Cuenta[];
}

export interface VentaPago extends Record<string, unknown> {
    id:                     number;
    venta_id:               number;
    metodo_pago_id:         number;
    cuenta_metodo_pago_id:  number | null;
    monto:                  string;
    referencia:             string | null;
    vuelto:                 string;
    metodo_pago?:           MetodoPago;
    cuenta_metodo_pago?:    Cuenta | null;
    created_at:             string;
    updated_at:             string;
}

export interface DescuentoLog extends Record<string, unknown> {
    id:                    number;
    empresa_id:            number;
    venta_id:              number | null;
    venta_item_id:         number | null;
    descuento_concepto_id: number;
    user_id:               number;
    cliente_id:            number | null;
    aprobado_por:          number | null;
    monto_descuento:       string;
    requeria_aprobacion:   boolean;
    notificacion_enviada:  boolean;
    concepto?:             DescuentoConcepto;
    user?:                 User;
    aprobado_por_user?:    User | null;
    venta?:                Venta;
    created_at:            string;
    updated_at:            string;
}

export interface Venta extends Record<string, unknown> {
    id:                    number;
    empresa_id:            number;
    local_id:              number;
    turno_id:              number;
    caja_id:               number;
    user_id:               number;
    cliente_id:            number | null;
    numero:                string;
    tipo_comprobante:      'ticket' | 'boleta' | 'factura';
    subtotal:              string;
    descuento_total:       string;
    descuento_concepto_id: number | null;
    igv:                   string;
    total:                 string;
    estado:                'completada' | 'anulada';
    observacion:           string | null;
    fecha_venta:           string;
    es_credito:            boolean;
    monto_pagado:          string;
    saldo_pendiente:       string;
    fecha_vencimiento:     string | null;
    moneda?:               string;
    tipo_cambio?:          string | null;
    monto_moneda?:         string | null;
    user?:                 User;
    cliente?:              Cliente;
    local?:                Local;
    items?:                VentaItem[];
    pagos?:                VentaPago[];
    descuentos_log?:       DescuentoLog[];
    /* Facturación electrónica (aditivo): solo llega cuando la venta tiene un
       comprobante emitido/en emisión. Las ventas `ticket` NO lo traen. */
    comprobante_electronico?: ComprobanteElectronico | null;
    created_at:            string;
    updated_at:            string;
}

/**
 * Estados del comprobante electrónico (tabla `venta_comprobantes`).
 * `pendiente_resumen` es el estado normal de una BOLETA recién emitida: SUNAT
 * la recibe en el resumen diario, no al instante. No es un error.
 */
/**
 * Debe coincidir con VentaComprobante::ESTADOS_* del backend. Faltaban cinco
 * estados que el emisor sí produce, y `simulado` / `no_emitido` no figuraban en
 * ninguna lista de terminales: el POS se quedaba preguntando indefinidamente por
 * comprobantes que jamás iban a moverse.
 */
export type ComprobanteEstado =
    | 'pendiente'
    | 'enviando'
    | 'enviado'
    | 'pendiente_resumen'
    | 'en_resumen'
    | 'pendiente_anulacion'
    | 'aceptado'
    | 'rechazado'
    | 'error_envio'
    | 'error_mapeo'
    | 'anulado'
    /** Modo simulación: calculado y numerado en serie de ensayo, nunca enviado. */
    | 'simulado'
    /** La empresa tiene la emisión apagada: no hay nada que esperar. */
    | 'no_emitido';

/**
 * Comprobante electrónico SUNAT asociado a una venta (`venta_comprobantes`).
 *
 * El detalle de venta lo recibe completo, pero la LISTA de ventas envía solo
 * lo imprescindible (número, tipo, estado y si el estado ya es final) para no
 * inflar 25 filas por página: por eso todo lo demás es opcional.
 */
export interface ComprobanteElectronico extends Record<string, unknown> {
    id?:                number;
    venta_id?:          number;
    /** Catálogo 01 SUNAT: '01' factura · '03' boleta. */
    tipo:               '01' | '03';
    serie?:             string;
    correlativo?:       number | string;
    /** Número oficial completo, p. ej. "F002-00000123". Null si nunca se numeró. */
    numero:             string | null;
    estado:             ComprobanteEstado;
    /** true = el estado ya no cambia solo (lo calcula el backend). */
    final?:             boolean;
    hash_cpe?:          string | null;
    /** String del QR ya armado con el formato SUNAT (no es una imagen). */
    qr?:                string | null;
    sunat_codigo?:      string | null;
    sunat_descripcion?: string | null;
    error?:             string | null;
    created_at?:        string;
    updated_at?:        string;
}

/**
 * Modo de emisión del EMISOR (FacturaMac), tal cual lo devuelve
 * `GET /api/v1/configuracion` (§7 del contrato).
 *
 * `desconocido` no viene del emisor: lo pone el POS cuando no ha podido leer la
 * configuración. Se trata como producción (aviso rojo) a propósito.
 */
export type ModoFacturacion =
    | 'desactivado' | 'simulacion' | 'beta' | 'produccion' | 'desconocido';

/**
 * Config de facturación electrónica que el POS necesita para avisar ANTES de
 * cobrar. Opcional: si el backend no la envía (módulo apagado o aún no
 * desplegado), el POS se comporta exactamente como hoy.
 *
 * TODO lo fiscal de aquí lo dicta el EMISOR (`GET /api/v1/configuracion`), no
 * `config/facturamac.php`. Lo único local es `enabled`, que es el interruptor de
 * emisión DE ESTA EMPRESA. Ver App\Services\Facturacion\ConfiguracionFacturacion.
 */
export interface FacturacionPosConfig {
    /**
     * ¿Esta EMPRESA emite comprobantes electrónicos?
     * `FacturacionEmpresa::activa($empresaId)`, resuelto desde
     * `facturacion_conexiones`. Antes era `config('facturamac.enabled')`, un flag
     * del `.env` común a toda la instalación: con dos contribuyentes, encender uno
     * encendía los dos y las ventas del segundo salían con el RUC del primero.
     */
    enabled:                    boolean;
    /** Modo del emisor: gobierna el banner del POS. */
    modo?:                      ModoFacturacion;
    /**
     * true = los comprobantes son reales. Lectura FAIL-SAFE: con el emisor caído
     * el `modo` es `desconocido` pero esto llega `true`, porque un aviso de más
     * no cuesta nada y uno de menos cuesta una Nota de Crédito.
     */
    produccion:                 boolean;
    /** ¿El emisor va a crear comprobante si le mandamos la venta? */
    emision_activa?:            boolean;
    /** ¿Los manda de verdad a SUNAT o los calcula y guarda sin enviar? */
    envia_a_sunat?:             boolean;
    /** Umbral de boleta identificada del EMISOR (default legal 700). */
    umbral_boleta_identificada?: number;
    /** Tasa de IGV con la que calcula el emisor (§8.4). */
    tasa_impuesto?:             number;
    /** Monedas admitidas por el emisor. v1: solo PEN (§8.3). */
    monedas?:                   string[];
    /**
     * Series con las que el emisor va a numerar. Son INFORMATIVAS: el POS las
     * pinta para que la cajera vea con qué serie se emitirá, pero NO las manda al
     * emitir (la numeración es competencia del emisor). Claves del contrato §7.
     */
    series?: {
        factura?: string | null;
        boleta?:  string | null;
    };
}

export interface Cliente extends Record<string, unknown> {
    id:                number;
    empresa_id:        number;
    tipo_documento:    string;
    numero_documento:  string | null;
    nombres:           string;
    apellidos:         string | null;
    razon_social:      string | null;
    email:             string | null;
    telefono:          string | null;
    direccion:         string | null;
    activo:            boolean;
    es_cliente_general?: boolean;
    nombre_completo?:  string;
    created_at:        string;
    updated_at:        string;
}

export interface Flash {
    success?: string | null;
    error?: string | null;
    /** La venta se acaba de registrar: auto-imprimir el ticket una sola vez. */
    imprimir_ticket?: boolean;
    /** Comprobante electrónico recién emitido (badge de estado en el POS). */
    comprobante?: {
        numero: string;
        estado: ComprobanteEstado;
        tipo?:  '01' | '03';
    } | null;
}

export interface ModuloMenu {
    id: number;
    nombre: string;
    slug: string;
    icono: string | null;
    ruta: string | null;
    orden: number;
    hijos: ModuloMenu[];
}

/** Campanita de cotizaciones (header). null si el usuario no puede verlas. */
export interface AlertasCotizaciones {
    por_vencer:             number;
    vencidas_sin_respuesta: number;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User & {
            empresa: Empresa;
            local: Local | null;
            rol: (Rol & { permisos: Permiso[] }) | null;
        };
    };
    modules:      ModuloMenu[];
    flash:        Flash;
    turno_activo: (Turno & { caja: Caja }) | null;
    alertasCotizaciones?: AlertasCotizaciones | null;
};
