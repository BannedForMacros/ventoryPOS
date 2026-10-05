import type { ModuloMenu } from '@/types';

/**
 * Orden del menú lateral, por TAREA y de lo más usado a lo menos usado.
 *
 * El servidor manda los módulos que el usuario puede ver (con permisos y
 * funciones de la empresa ya filtrados) en el orden de la tabla `modulos`, que
 * tenía órdenes repetidos y dejaba "Configuración" en segundo lugar. Aquí solo
 * se ORDENA y AGRUPA lo que llegó: nunca se muestra algo que el servidor no
 * mandó, y lo que no esté en este plano (un módulo nuevo) cae al final en
 * "Más", para que nada desaparezca.
 */

type Entrada =
    | string
    | { grupo: string; hijos?: string[]; subgrupos?: { titulo: string; items: string[] }[] };

const PLANO: { titulo: string | null; items: Entrada[] }[] = [
    { titulo: null, items: ['dashboard'] },
    { titulo: 'Vender', items: ['pos', 'ventas', 'cotizaciones', 'clientes', 'devoluciones', 'turnos', 'agenda'] },
    {
        titulo: 'Compras e inventario',
        items: [
            'proveedores',
            { grupo: 'inventario', hijos: [
                'inventario.stock', 'inventario.entradas', 'inventario.salidas', 'inventario.transferencias',
                'despachos', 'inventario.ajustes', 'inventario.cierres', 'inventario.inicial',
            ] },
            { grupo: 'catalogo', hijos: ['catalogo.productos', 'catalogo.categorias', 'catalogo.unidades'] },
        ],
    },
    {
        titulo: 'Dinero',
        items: [
            'gastos',
            { grupo: 'finanzas', hijos: [
                'finanzas.balance', 'finanzas.tesoreria', 'finanzas.consolidacion',
                'finanzas.cuentas-por-cobrar', 'finanzas.cuentas-por-pagar',
                'finanzas.anticipos', 'finanzas.adelantos', 'finanzas.deudas',
                'finanzas.estado-cuenta', 'finanzas.planilla-descuentos',
            ] },
        ],
    },
    {
        titulo: 'Análisis',
        items: [
            { grupo: 'reportes', hijos: [
                'reportes.ventas', 'reportes.utilidad', 'reportes.productos', 'reportes.caja',
                'reportes.gastos', 'reportes.descuentos', 'reportes.devoluciones', 'reportes.kardex',
                'reportes.cierre-mes', 'reportes.agenda', 'reportes.auditoria',
            ] },
        ],
    },
    {
        titulo: 'Ajustes',
        items: [
            { grupo: 'configuracion', subgrupos: [
                { titulo: 'Negocio', items: ['config.empresas', 'config.locales', 'configuracion.cajas', 'configuracion.almacenes', 'config.entregas'] },
                { titulo: 'Personas y accesos', items: ['config.usuarios', 'config.roles', 'config.permisos', 'config.modulos'] },
                { titulo: 'Cobros y comprobantes', items: ['configuracion.metodos-pago', 'configuracion.cuentas', 'configuracion.facturacion', 'config.ticket'] },
                { titulo: 'Listas', items: ['configuracion.gastos-tipos', 'configuracion.salidas-tipos', 'configuracion.devolucion-motivos', 'configuracion.descuento-conceptos'] },
            ] },
        ],
    },
];

/** Íconos que se repetían (Cotizaciones y Utilidad usaban el del Dashboard, etc.). */
const ICONO: Record<string, string> = {
    cotizaciones:             'FileText',
    'reportes.utilidad':      'BadgeDollarSign',
    'finanzas.estado-cuenta': 'BookUser',
    'config.usuarios':        'UserCog',
    despachos:                'PackageOpen',
    'inventario.inicial':     'PackagePlus',
    'configuracion.devolucion-motivos': 'MessageSquareWarning',
    'configuracion.salidas-tipos':      'ListTree',
};

/** Hijo del menú ya ordenado; `subtitulo` abre un subgrupo dentro del grupo. */
export interface ItemMenu extends Omit<ModuloMenu, 'hijos'> {
    hijos: ItemMenu[];
    subtitulo?: string;
}

export interface SeccionMenu {
    titulo: string | null;
    items: ItemMenu[];
}

export function organizarMenu(modulos: ModuloMenu[]): SeccionMenu[] {
    // Todo lo que llegó, aplanado por slug (los grupos también).
    const porSlug = new Map<string, ModuloMenu>();
    const recorrer = (ms: ModuloMenu[]) => ms.forEach(m => { porSlug.set(m.slug, m); recorrer(m.hijos ?? []); });
    recorrer(modulos);
    const usados = new Set<string>();

    const hoja = (slug: string, subtitulo?: string): ItemMenu | null => {
        const m = porSlug.get(slug);
        if (!m || usados.has(slug) || !m.ruta) return null;
        usados.add(slug);
        return { ...m, icono: ICONO[slug] ?? m.icono, hijos: [], subtitulo };
    };

    const secciones: SeccionMenu[] = PLANO.map(({ titulo, items }) => ({
        titulo,
        items: items.map(e => {
            if (typeof e === 'string') return hoja(e);
            const g = porSlug.get(e.grupo);
            if (!g) return null;
            usados.add(e.grupo);
            let hijos: ItemMenu[] = [];
            if (e.subgrupos) {
                for (const sg of e.subgrupos) {
                    const del = sg.items.map(s => hoja(s)).filter(Boolean) as ItemMenu[];
                    if (del.length) { del[0].subtitulo = sg.titulo; hijos.push(...del); }
                }
            } else {
                hijos = (e.hijos ?? []).map(s => hoja(s)).filter(Boolean) as ItemMenu[];
            }
            // Hijos que el servidor puso en este grupo y no están en el plano: al final.
            for (const h of g.hijos ?? []) {
                const extra = hoja(h.slug);
                if (extra) hijos.push(extra);
            }
            return hijos.length ? { ...g, icono: ICONO[g.slug] ?? g.icono, hijos } as ItemMenu : null;
        }).filter(Boolean) as ItemMenu[],
    })).filter(s => s.items.length > 0);

    // Lo que no está en el plano (p. ej. un módulo nuevo): nunca se pierde.
    const sobras = [...porSlug.values()].filter(m => !usados.has(m.slug) && m.ruta).map(m => hoja(m.slug)).filter(Boolean) as ItemMenu[];
    if (sobras.length) secciones.push({ titulo: 'Más', items: sobras });

    return secciones;
}
