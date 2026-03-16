export interface Empresa extends Record<string, unknown> {
    id: number;
    razon_social: string;
    nombre_comercial: string | null;
    ruc: string;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    logo: string | null;
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
    modules: ModuloMenu[];
    flash: Flash;
};
