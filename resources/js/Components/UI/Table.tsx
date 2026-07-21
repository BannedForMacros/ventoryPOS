import React, { useState, useMemo, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import {
    ChevronDown, ChevronUp, ChevronRight, Search,
    ChevronsUpDown, ChevronLeft, ChevronsLeft,
    ChevronRight as ChevronRightIcon, ChevronsRight,
} from 'lucide-react';
import { cn } from '@/lib/utils';

// ── Tipos ──────────────────────────────────────────────────────────────────────
export interface Column<T extends Record<string, unknown>> {
    key: string;
    label: string;
    /** Ordenable por clic en la cabecera. Default TRUE — pasar false para desactivar. */
    sortable?: boolean;
    searchKey?: string;
    /** Alineación de la celda. Montos SIEMPRE 'right'. */
    align?: 'left' | 'right' | 'center';
    render?: (row: T) => React.ReactNode;
    /** Campo alterno para ordenar (p. ej. 'cliente.nombres' o un número crudo). */
    sortKey?: string;
}

interface TableProps<T extends Record<string, unknown>> {
    data: T[] | { data: T[] };
    columns: Column<T>[];
    searchable?: boolean;
    searchPlaceholder?: string;
    sortable?: boolean;
    emptyMessage?: string;
    renderExpandedRow?: ((row: T) => React.ReactNode) | null;
    rowClassName?: string | ((row: T) => string) | null;
    itemsPerPage?: number;
    pagination?: boolean;
    /**
     * Búsqueda SERVER-SIDE: si se pasa, el término se envía (debounced 350ms)
     * a este callback para consultar TODA la base, y el filtrado local se
     * desactiva (el servidor es la autoridad). Sin esto, la búsqueda solo ve
     * las filas ya cargadas — con paginación server-side eso oculta filas de
     * otras páginas (bug del buscador de Entradas).
     */
    onServerSearch?: (texto: string) => void;
    /** Término inicial (para persistir el buscar de la URL al recargar). */
    initialSearch?: string;
    /** Clic en cualquier parte de la fila (para tablas que llevan a un detalle). */
    onRowClick?: (row: T) => void;
}

interface PaginationBtnProps {
    children: React.ReactNode;
    onClick: () => void;
    disabled: boolean;
}

// ── PaginationBtn ──────────────────────────────────────────────────────────────
function PaginationBtn({ children, onClick, disabled }: PaginationBtnProps) {
    return (
        <button
            onClick={onClick}
            disabled={disabled}
            className="rounded-lg border p-1.5 transition-colors disabled:cursor-not-allowed"
            style={{
                borderColor: 'var(--color-border)',
                color: disabled ? 'var(--color-text-muted)' : 'var(--color-text)',
                backgroundColor: 'var(--color-surface)',
                opacity: disabled ? 0.45 : 1,
            }}
            onMouseEnter={e => { if (!disabled) e.currentTarget.style.backgroundColor = 'var(--color-bg)'; }}
            onMouseLeave={e => { if (!disabled) e.currentTarget.style.backgroundColor = 'var(--color-surface)'; }}
        >
            {children}
        </button>
    );
}

// ── Table ──────────────────────────────────────────────────────────────────────
export default function Table<T extends Record<string, unknown>>({
    data,
    columns,
    searchable = true,
    searchPlaceholder = 'Buscar...',
    sortable = true,
    emptyMessage = 'No hay datos disponibles',
    renderExpandedRow = null,
    rowClassName = null,
    itemsPerPage = 20,
    pagination = true,
    onServerSearch,
    initialSearch,
    onRowClick,
}: TableProps<T>) {
    const [search, setSearch] = useState(initialSearch ?? '');

    // Loading INTERNO de la tabla: cualquier visita Inertia hacia la MISMA ruta
    // (buscar, paginar, cambiar filtros con preserveState) muestra un velo con
    // spinner sobre las filas, al instante. El splash global de la app solo se
    // reserva para cambios de página reales (ver RouterLoadingOverlay).
    const [cargando, setCargando] = useState(false);
    useEffect(() => {
        const offStart = router.on('start', (event) => {
            const visitUrl = (event as CustomEvent).detail?.visit?.url as URL | undefined;
            if (visitUrl && visitUrl.pathname === window.location.pathname) setCargando(true);
        });
        const offFinish = router.on('finish', () => setCargando(false));
        return () => { offStart(); offFinish(); };
    }, []);

    // Búsqueda server-side debounced (500 ms: cómodo para escribir sin que la
    // tabla "salte" con cada tecla): notifica el término para que la página
    // consulte al backend. Se salta el primer render (ya viene filtrado).
    const primeraBusqueda = useRef(true);
    useEffect(() => {
        if (!onServerSearch) return;
        if (primeraBusqueda.current) { primeraBusqueda.current = false; return; }
        const t = setTimeout(() => onServerSearch(search.trim()), 500);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);
    const [sortColumn, setSortColumn] = useState<string | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
    const [expandedRows, setExpandedRows] = useState<unknown[]>([]);
    const [currentPage, setCurrentPage] = useState(1);

    const items: T[] = Array.isArray(data) ? data : (data?.data ?? []);

    // ── Paginación SERVER-SIDE ─────────────────────────────────────────────
    // Si `data` es un paginador de Laravel (current_page/last_page), la tabla
    // NO re-pagina localmente: muestra todas las filas recibidas y sus botones
    // navegan las páginas REALES del servidor (conservando la query actual).
    // Antes había doble paginación: el server mandaba 25 y la tabla las cortaba
    // en 20+5, dejando las páginas reales 2+ inalcanzables (bug CxP: entradas
    // "desaparecidas" que vivían en la página 2 del servidor).
    const serverPag = !Array.isArray(data) && data
        && typeof (data as Record<string, unknown>).current_page === 'number'
        && typeof (data as Record<string, unknown>).last_page === 'number'
        ? (data as unknown as { data: T[]; current_page: number; last_page: number; per_page: number; total: number })
        : null;

    const irAPaginaServidor = (page: number) => {
        const params = Object.fromEntries(new URLSearchParams(window.location.search));
        router.get(window.location.pathname, { ...params, page }, { preserveState: true, preserveScroll: true });
    };

    const toggleRow = (rowId: unknown) =>
        setExpandedRows(prev =>
            prev.includes(rowId) ? prev.filter(id => id !== rowId) : [...prev, rowId]
        );

    // 1. Filtrar — busca en TODOS los campos del row (no solo los keys de columnas).
    //    Con onServerSearch el filtrado es del backend: aquí no se re-filtra
    //    (el server pudo matchear por campos que no viajan en el row).
    const filteredData = useMemo(() => {
        if (onServerSearch) return items;
        if (!search || !items.length) return items;
        const searchLower = search.toLowerCase();
        const stringify = (obj: unknown): string => {
            if (obj === null || obj === undefined || typeof obj === 'boolean') return '';
            if (typeof obj === 'string' || typeof obj === 'number') return String(obj).toLowerCase();
            if (Array.isArray(obj)) return obj.map(stringify).join(' ');
            if (typeof obj === 'object') return Object.values(obj as object).map(stringify).join(' ');
            return '';
        };
        return items.filter(row => stringify(row).includes(searchLower));
    }, [items, search]);

    // 2. Ordenar — numérico-consciente ("9" < "10", montos como "1,250.00")
    //    y con soporte de rutas anidadas via sortKey ('cliente.nombres').
    const sortKeyDe = (col: string) => columns.find(c => c.key === col)?.sortKey ?? col;
    const valorDe = (row: T, path: string): unknown =>
        path.split('.').reduce<unknown>((acc, k) => (acc as Record<string, unknown> | null | undefined)?.[k], row);

    const sortedData = useMemo(() => {
        if (!sortColumn || !filteredData) return filteredData;
        const path = sortKeyDe(sortColumn);
        const aNum = (v: unknown): number | null => {
            if (typeof v === 'number') return v;
            if (typeof v === 'string') {
                const n = parseFloat(v.replace(/[^\d.-]/g, ''));
                return v.trim() !== '' && !isNaN(n) && /^[\s\d.,\-S/]+$/.test(v) ? n : null;
            }
            return null;
        };
        return [...filteredData].sort((a, b) => {
            const rawA = valorDe(a, path);
            const rawB = valorDe(b, path);
            const numA = aNum(rawA), numB = aNum(rawB);
            let cmp: number;
            if (numA !== null && numB !== null) {
                cmp = numA - numB;
            } else {
                const aVal = typeof rawA === 'object' && rawA !== null ? Object.values(rawA).join(' ') : String(rawA ?? '');
                const bVal = typeof rawB === 'object' && rawB !== null ? Object.values(rawB).join(' ') : String(rawB ?? '');
                cmp = aVal.toLowerCase().localeCompare(bVal.toLowerCase(), 'es');
            }
            return sortDirection === 'asc' ? cmp : -cmp;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filteredData, sortColumn, sortDirection]);

    // 3. Paginar — con paginador de servidor NO se corta localmente: las filas
    //    recibidas SON la página; los botones navegan páginas del backend.
    const pageActual  = serverPag ? serverPag.current_page : currentPage;
    const totalPages  = serverPag ? serverPag.last_page : Math.ceil((sortedData?.length ?? 0) / itemsPerPage);
    const porPagina   = serverPag ? serverPag.per_page : itemsPerPage;
    const startIndex  = (pageActual - 1) * porPagina;
    const paginatedData = (!serverPag && pagination) ? (sortedData?.slice(startIndex, startIndex + itemsPerPage) ?? []) : (sortedData ?? []);
    const endIndex    = startIndex + paginatedData.length;
    const totalRegistros = serverPag ? serverPag.total : (sortedData?.length ?? 0);
    const irAPagina   = (p: number) => serverPag ? irAPaginaServidor(p) : setCurrentPage(p);

    useMemo(() => { if (pagination && !serverPag) setCurrentPage(1); }, [search, pagination, serverPag]);

    // Ordenable por DEFAULT: solo se desactiva con sortable: false en la columna.
    const esOrdenable = (column: Column<T>) => sortable && column.sortable !== false;

    const handleSort = (column: Column<T>) => {
        if (!esOrdenable(column)) return;
        if (sortColumn === column.key) setSortDirection(d => d === 'asc' ? 'desc' : 'asc');
        else { setSortColumn(column.key); setSortDirection('asc'); }
    };

    const getPageNumbers = (): (number | string)[] => {
        const pages: (number | string)[] = [];
        if (totalPages <= 5) {
            for (let i = 1; i <= totalPages; i++) pages.push(i);
        } else if (pageActual <= 3) {
            for (let i = 1; i <= 4; i++) pages.push(i);
            pages.push('...'); pages.push(totalPages);
        } else if (pageActual >= totalPages - 2) {
            pages.push(1); pages.push('...');
            for (let i = totalPages - 3; i <= totalPages; i++) pages.push(i);
        } else {
            pages.push(1); pages.push('...');
            pages.push(pageActual - 1); pages.push(pageActual); pages.push(pageActual + 1);
            pages.push('...'); pages.push(totalPages);
        }
        return pages;
    };

    return (
        <div className="w-full space-y-3">

            {/* ── Búsqueda ───────────────────────────────────────────── */}
            {searchable && (
                <div className="flex items-center gap-3">
                    <div className="relative flex-1 max-w-sm">
                        <Search
                            size={15}
                            className="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                            style={{ color: 'var(--color-text-muted)' }}
                        />
                        <input
                            type="text"
                            placeholder={searchPlaceholder}
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="w-full rounded-xl border py-2 pl-9 pr-9 text-sm outline-none transition-all"
                            style={{
                                borderColor: 'var(--color-border)',
                                backgroundColor: 'var(--color-surface)',
                                color: 'var(--color-text)',
                            }}
                            onFocus={e => (e.currentTarget.style.borderColor = 'var(--color-primary)')}
                            onBlur={e => (e.currentTarget.style.borderColor = 'var(--color-border)')}
                        />
                        {cargando && onServerSearch ? (
                            <span
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 rounded-full animate-spin"
                                style={{
                                    border: '2px solid color-mix(in srgb, var(--color-primary) 25%, transparent)',
                                    borderTopColor: 'var(--color-primary)',
                                }}
                            />
                        ) : search && (
                            <button
                                onClick={() => setSearch('')}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-full p-0.5 transition-colors"
                                style={{ color: 'var(--color-text-muted)' }}
                                onMouseEnter={e => (e.currentTarget.style.color = 'var(--color-text)')}
                                onMouseLeave={e => (e.currentTarget.style.color = 'var(--color-text-muted)')}
                            >
                                ✕
                            </button>
                        )}
                    </div>
                    {search && (
                        <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            {sortedData.length} resultado{sortedData.length !== 1 ? 's' : ''}
                        </span>
                    )}
                </div>
            )}

            {/* ── Tabla ──────────────────────────────────────────────── */}
            <div
                className="relative overflow-hidden rounded-2xl border"
                style={{
                    borderColor: 'var(--color-border)',
                    backgroundColor: 'var(--color-surface)',
                    boxShadow: '0 1px 4px 0 rgb(0 0 0 / 0.04)',
                }}
            >
                {/* Velo de carga INMEDIATO al buscar/paginar/filtrar (mantiene
                    visibles las filas actuales debajo, sin tapar la app). */}
                {cargando && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-surface) 62%, transparent)', backdropFilter: 'blur(1px)' }}>
                        <div className="flex items-center gap-2.5 rounded-full px-4 py-2 shadow-md"
                            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                            <span className="h-4 w-4 rounded-full animate-spin"
                                style={{
                                    border: '2px solid color-mix(in srgb, var(--color-primary) 25%, transparent)',
                                    borderTopColor: 'var(--color-primary)',
                                }} />
                            <span className="text-xs font-medium" style={{ color: 'var(--color-text)' }}>Cargando…</span>
                        </div>
                    </div>
                )}
                <div className="overflow-x-auto">
                    <table className="w-full whitespace-nowrap">

                        {/* Head */}
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                                {renderExpandedRow && <th className="w-10 px-3 py-3" />}
                                {columns.map(column => (
                                    <th
                                        key={column.key}
                                        onClick={() => handleSort(column)}
                                        className={cn(
                                            'px-5 py-3 text-xs font-semibold uppercase tracking-wider',
                                            column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                                            esOrdenable(column) && 'cursor-pointer select-none'
                                        )}
                                        style={{ color: 'var(--color-text-muted)' }}
                                        onMouseEnter={e => { if (esOrdenable(column)) e.currentTarget.style.color = 'var(--color-text)'; }}
                                        onMouseLeave={e => { if (esOrdenable(column)) e.currentTarget.style.color = 'var(--color-text-muted)'; }}
                                    >
                                        <div className={cn(
                                            'flex items-center gap-1.5',
                                            column.align === 'right' && 'justify-end',
                                            column.align === 'center' && 'justify-center'
                                        )}>
                                            <span>{column.label}</span>
                                            {esOrdenable(column) && (
                                                <span className="flex flex-col" style={{ lineHeight: 0 }}>
                                                    {sortColumn === column.key ? (
                                                        sortDirection === 'asc'
                                                            ? <ChevronUp size={13} style={{ color: 'var(--color-primary)' }} />
                                                            : <ChevronDown size={13} style={{ color: 'var(--color-primary)' }} />
                                                    ) : (
                                                        <ChevronsUpDown size={13} style={{ opacity: 0.35 }} />
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        {/* Body */}
                        <tbody>
                            {paginatedData.length > 0 ? (
                                paginatedData.map((row, index) => {
                                    const rowId: unknown = 'id' in row ? row.id : index;
                                    const isExpanded = expandedRows.includes(rowId);
                                    const customRowClass = rowClassName
                                        ? (typeof rowClassName === 'function' ? rowClassName(row) : rowClassName)
                                        : '';

                                    return (
                                        <React.Fragment key={String(rowId ?? index)}>
                                            <tr
                                                className={cn('transition-colors duration-150', customRowClass)}
                                                style={{ borderTop: index !== 0 ? '1px solid var(--color-border)' : undefined }}
                                                onClick={onRowClick ? () => onRowClick(row) : undefined}
                                                onMouseEnter={e => { if (!isExpanded) e.currentTarget.style.backgroundColor = 'var(--color-bg)'; }}
                                                onMouseLeave={e => { if (!isExpanded) e.currentTarget.style.backgroundColor = ''; }}
                                            >
                                                {renderExpandedRow && (
                                                    <td className="w-10 px-3 py-3.5 text-center align-middle">
                                                        <button
                                                            onClick={e => { e.stopPropagation(); toggleRow(rowId); }}
                                                            className="rounded-lg p-1 transition-colors"
                                                            style={{ color: 'var(--color-text-muted)' }}
                                                            onMouseEnter={e => (e.currentTarget.style.backgroundColor = 'var(--color-border)')}
                                                            onMouseLeave={e => (e.currentTarget.style.backgroundColor = 'transparent')}
                                                        >
                                                            {isExpanded ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
                                                        </button>
                                                    </td>
                                                )}
                                                {columns.map(column => (
                                                    <td
                                                        key={column.key}
                                                        className={cn(
                                                            'px-5 py-3.5 text-sm align-middle',
                                                            column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left'
                                                        )}
                                                        style={{ color: 'var(--color-text)', fontVariantNumeric: 'tabular-nums' }}
                                                    >
                                                        {column.render
                                                            ? column.render(row)
                                                            : String(row[column.key] ?? '-')
                                                        }
                                                    </td>
                                                ))}
                                            </tr>

                                            {isExpanded && renderExpandedRow && (
                                                <tr style={{ backgroundColor: 'var(--color-bg)', borderTop: '1px solid var(--color-border)' }}>
                                                    <td colSpan={columns.length + 1} className="px-5 py-4">
                                                        {renderExpandedRow(row)}
                                                    </td>
                                                </tr>
                                            )}
                                        </React.Fragment>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td
                                        colSpan={columns.length + (renderExpandedRow ? 1 : 0)}
                                        className="py-20 text-center"
                                    >
                                        <div className="flex flex-col items-center gap-3">
                                            <div
                                                className="rounded-full p-4"
                                                style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                                            >
                                                <Search size={22} style={{ color: 'var(--color-text-muted)', opacity: 0.5 }} />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                                    {search ? 'No se encontraron resultados' : emptyMessage}
                                                </p>
                                                {search && (
                                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                                        Intenta con otros términos de búsqueda
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* ── Paginación ─────────────────────────────────────── */}
                {pagination && totalPages > 1 && (
                    <div
                        className="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-3"
                        style={{ borderTop: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}
                    >
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            Mostrando{' '}
                            <span style={{ color: 'var(--color-text)', fontWeight: 500 }}>{totalRegistros === 0 ? 0 : startIndex + 1}</span>
                            {' '}–{' '}
                            <span style={{ color: 'var(--color-text)', fontWeight: 500 }}>{Math.min(endIndex, totalRegistros)}</span>
                            {' '}de{' '}
                            <span style={{ color: 'var(--color-text)', fontWeight: 500 }}>{totalRegistros}</span>
                            {' '}registros
                        </p>

                        <div className="flex items-center gap-1">
                            <PaginationBtn onClick={() => irAPagina(1)} disabled={pageActual === 1}>
                                <ChevronsLeft size={15} />
                            </PaginationBtn>
                            <PaginationBtn onClick={() => irAPagina(pageActual - 1)} disabled={pageActual === 1}>
                                <ChevronLeft size={15} />
                            </PaginationBtn>

                            <div className="hidden sm:flex items-center gap-1">
                                {getPageNumbers().map((page, i) =>
                                    page === '...' ? (
                                        <span key={`e-${i}`} className="px-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>…</span>
                                    ) : (
                                        <button
                                            key={page}
                                            onClick={() => irAPagina(page as number)}
                                            className="min-w-[2rem] rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                                            style={pageActual === page ? {
                                                backgroundColor: 'var(--color-primary)',
                                                borderColor: 'var(--color-primary)',
                                                color: '#fff',
                                            } : {
                                                borderColor: 'var(--color-border)',
                                                color: 'var(--color-text)',
                                                backgroundColor: 'var(--color-surface)',
                                            }}
                                        >
                                            {page}
                                        </button>
                                    )
                                )}
                            </div>

                            <span className="sm:hidden px-3 text-xs" style={{ color: 'var(--color-text)' }}>
                                {pageActual} / {totalPages}
                            </span>

                            <PaginationBtn onClick={() => irAPagina(pageActual + 1)} disabled={pageActual === totalPages}>
                                <ChevronRightIcon size={15} />
                            </PaginationBtn>
                            <PaginationBtn onClick={() => irAPagina(totalPages)} disabled={pageActual === totalPages}>
                                <ChevronsRight size={15} />
                            </PaginationBtn>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}