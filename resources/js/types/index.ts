export interface Empresa extends Record<string, unknown> {
    id: number;
    razon_social: string;
    nombre_comercial: string | null;
    ruc: string;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    logo: string | null;
    modo_almacen: 'simple' | 'central_y_local';
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
    empresa_id: number;
    local_id: number | null;
    rol_id: number | null;
    name: string;
    email: string;
    email_verified_at?: string | null;
    activo: boolean;
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
    categoria?: Categoria | null;
    unidades?: ProductoUnidad[];
    unidad_base?: ProductoUnidad | null;
    created_at: string;
    updated_at: string;
}

export interface MetodoPago extends Record<string, unknown> {
    id:     number;
    nombre: string;
    tipo:   string;
    activo: boolean;
}

export interface Caja extends Record<string, unknown> {
    id:                          number;
    empresa_id:                  number;
    local_id:                    number;
    nombre:                      string;
    caja_chica_activa:           boolean;
    caja_chica_monto_sugerido:   number;
    caja_chica_en_arqueo:        boolean;
    activo:                      boolean;
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
    fecha:              string;
    comentario:         string | null;
    tipo?:              GastoTipo;
    concepto?:          GastoConcepto;
    user?:              User;
    local?:             Local;
    created_at:         string;
    updated_at:         string;
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
    user?:                 User;
    cliente?:              Cliente;
    local?:                Local;
    items?:                VentaItem[];
    pagos?:                VentaPago[];
    descuentos_log?:       DescuentoLog[];
    created_at:            string;
    updated_at:            string;
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
    nombre_completo?:  string;
    created_at:        string;
    updated_at:        string;
}

export interface Flash {
    success?: string | null;
    error?: string | null;
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
};
