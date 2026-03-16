import React, { useState, useMemo } from 'react';
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
    sortable?: boolean;
    searchKey?: string;
    render?: (row: T) => React.ReactNode;
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
}: TableProps<T>) {
    const [search, setSearch] = useState('');
    const [sortColumn, setSortColumn] = useState<string | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
    const [expandedRows, setExpandedRows] = useState<unknown[]>([]);
    const [currentPage, setCurrentPage] = useState(1);

    const items: T[] = Array.isArray(data) ? data : (data?.data ?? []);

    const toggleRow = (rowId: unknown) =>
        setExpandedRows(prev =>
            prev.includes(rowId) ? prev.filter(id => id !== rowId) : [...prev, rowId]
        );

    // 1. Filtrar
    const filteredData = useMemo(() => {
        if (!search || !items.length) return items;
        const searchLower = search.toLowerCase();
        const getSearchableString = (obj: unknown): string => {
            if (typeof obj === 'string' || typeof obj === 'number') return String(obj).toLowerCase();
            if (typeof obj === 'object' && obj !== null) return Object.values(obj).map(getSearchableString).join(' ');
            return '';
        };
        return items.filter(row =>
            columns.some(column => {
                const key = column.render && column.searchKey ? column.searchKey : column.key;
                const value: unknown = row[key];
                return getSearchableString(value).includes(searchLower);
            })
        );
    }, [items, search, columns]);

    // 2. Ordenar
    const sortedData = useMemo(() => {
        if (!sortColumn || !filteredData) return filteredData;
        return [...filteredData].sort((a, b) => {
            const rawA: unknown = a[sortColumn];
            const rawB: unknown = b[sortColumn];
            const aVal = typeof rawA === 'object' && rawA !== null
                ? Object.values(rawA).join(' ')
                : String(rawA ?? '');
            const bVal = typeof rawB === 'object' && rawB !== null
                ? Object.values(rawB).join(' ')
                : String(rawB ?? '');
            const aStr = aVal.toLowerCase();
            const bStr = bVal.toLowerCase();
            return sortDirection === 'asc' ? (aStr > bStr ? 1 : -1) : (aStr < bStr ? 1 : -1);
        });
    }, [filteredData, sortColumn, sortDirection]);

    // 3. Paginar
    const totalPages = Math.ceil((sortedData?.length ?? 0) / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = pagination ? (sortedData?.slice(startIndex, endIndex) ?? []) : (sortedData ?? []);

    useMemo(() => { if (pagination) setCurrentPage(1); }, [search, pagination]);

    const handleSort = (column: Column<T>) => {
        if (!sortable || !column.sortable) return;
        if (sortColumn === column.key) setSortDirection(d => d === 'asc' ? 'desc' : 'asc');
        else { setSortColumn(column.key); setSortDirection('asc'); }
    };

    const getPageNumbers = (): (number | string)[] => {
        const pages: (number | string)[] = [];
        if (totalPages <= 5) {
            for (let i = 1; i <= totalPages; i++) pages.push(i);
        } else if (currentPage <= 3) {
            for (let i = 1; i <= 4; i++) pages.push(i);
            pages.push('...'); pages.push(totalPages);
        } else if (currentPage >= totalPages - 2) {
            pages.push(1); pages.push('...');
            for (let i = totalPages - 3; i <= totalPages; i++) pages.push(i);
        } else {
            pages.push(1); pages.push('...');
            pages.push(currentPage - 1); pages.push(currentPage); pages.push(currentPage + 1);
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
                        {search && (
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
                className="overflow-hidden rounded-2xl border"
                style={{
                    borderColor: 'var(--color-border)',
                    backgroundColor: 'var(--color-surface)',
                    boxShadow: '0 1px 4px 0 rgb(0 0 0 / 0.04)',
                }}
            >
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
                                            'px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider',
                                            column.sortable && sortable && 'cursor-pointer select-none'
                                        )}
                                        style={{ color: 'var(--color-text-muted)' }}
                                        onMouseEnter={e => { if (column.sortable && sortable) e.currentTarget.style.color = 'var(--color-text)'; }}
                                        onMouseLeave={e => { if (column.sortable && sortable) e.currentTarget.style.color = 'var(--color-text-muted)'; }}
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <span>{column.label}</span>
                                            {column.sortable && sortable && (
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
                                                        className="px-5 py-3.5 text-sm align-middle"
                                                        style={{ color: 'var(--color-text)' }}
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
                            <span style={{ color: 'var(--color-text)', fontWeight: 500 }}>{startIndex + 1}</span>
                            {' '}–{' '}
                            <span style={{ color: 'var(--color-text)', fontWeight: 500 }}>{Math.min(endIndex, sortedData.length)}</span>
                            {' '}de{' '}
                            <span style={{ color: 'var(--color-text)', fontWeight: 500 }}>{sortedData.length}</span>
                            {' '}registros
                        </p>

                        <div className="flex items-center gap-1">
                            <PaginationBtn onClick={() => setCurrentPage(1)} disabled={currentPage === 1}>
                                <ChevronsLeft size={15} />
                            </PaginationBtn>
                            <PaginationBtn onClick={() => setCurrentPage(prev => prev - 1)} disabled={currentPage === 1}>
                                <ChevronLeft size={15} />
                            </PaginationBtn>

                            <div className="hidden sm:flex items-center gap-1">
                                {getPageNumbers().map((page, i) =>
                                    page === '...' ? (
                                        <span key={`e-${i}`} className="px-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>…</span>
                                    ) : (
                                        <button
                                            key={page}
                                            onClick={() => setCurrentPage(page as number)}
                                            className="min-w-[2rem] rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                                            style={currentPage === page ? {
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
                                {currentPage} / {totalPages}
                            </span>

                            <PaginationBtn onClick={() => setCurrentPage(prev => prev + 1)} disabled={currentPage === totalPages}>
                                <ChevronRightIcon size={15} />
                            </PaginationBtn>
                            <PaginationBtn onClick={() => setCurrentPage(totalPages)} disabled={currentPage === totalPages}>
                                <ChevronsRight size={15} />
                            </PaginationBtn>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}