import React, { useState, useRef, useEffect, useMemo } from 'react';
import { ChevronDown, Check, Search, X } from 'lucide-react';

interface SearchableSelectOption {
    value: string | number;
    label: string;
}

interface SearchableSelectProps {
    label?:             string;
    error?:             string;
    hint?:              string;
    required?:          boolean;
    options?:           SearchableSelectOption[];
    value?:             string | number;
    onChange?:          (value: string | number) => void;
    placeholder?:       string;
    /** Placeholder del input de busqueda dentro del dropdown */
    searchPlaceholder?: string;
    /** Mensaje cuando el filtro no encuentra coincidencias */
    emptyMessage?:      string;
    disabled?:          boolean;
    className?:         string;
}

/**
 * Select con busqueda integrada. Drop-in replacement de Select.tsx con la misma API.
 *
 * Diferencias de UX vs Select:
 *  - Al abrirse, aparece un input de busqueda autofocuseado.
 *  - Las opciones se filtran en tiempo real por substring (case-insensitive).
 *  - Navegacion por teclado:
 *      ↓ baja en la lista filtrada
 *      ↑ sube
 *      Enter selecciona el item resaltado
 *      Esc cierra y limpia la busqueda
 *  - Item resaltado visualmente distinto del seleccionado.
 *
 * Pensado para listas largas: presentaciones, productos, clientes, proveedores.
 */
export default function SearchableSelect({
    label,
    error,
    hint,
    required,
    options = [],
    value,
    onChange,
    placeholder       = 'Seleccionar...',
    searchPlaceholder = 'Buscar...',
    emptyMessage      = 'Sin resultados',
    disabled          = false,
    className         = '',
}: SearchableSelectProps) {
    const [open, setOpen]             = useState(false);
    const [focused, setFocused]       = useState(false);
    const [query, setQuery]           = useState('');
    // Indice del item resaltado (con teclado/hover) sobre la lista FILTRADA
    const [highlightIdx, setHighlightIdx] = useState<number>(-1);

    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef     = useRef<HTMLInputElement>(null);
    const listRef      = useRef<HTMLUListElement>(null);

    const selected = options.find(o => o.value === value);

    // Filtrado: case + acento insensitive (basico)
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options;
        return options.filter(o => o.label.toLowerCase().includes(q));
    }, [query, options]);

    // Cerrar al hacer click fuera
    useEffect(() => {
        function onClickOutside(e: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                close();
            }
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Al abrir: focus al input + reset de highlight al item seleccionado (si esta en filtro)
    useEffect(() => {
        if (open) {
            // microtask para que el input ya este montado
            requestAnimationFrame(() => inputRef.current?.focus());
            const idx = filtered.findIndex(o => o.value === value);
            setHighlightIdx(idx >= 0 ? idx : (filtered.length > 0 ? 0 : -1));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    // Al filtrar, asegurar que el highlight no salga del rango
    useEffect(() => {
        if (filtered.length === 0) { setHighlightIdx(-1); return; }
        if (highlightIdx >= filtered.length) setHighlightIdx(filtered.length - 1);
        if (highlightIdx < 0) setHighlightIdx(0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filtered.length]);

    // Scroll al item resaltado si se sale de la vista (navegacion por teclado)
    useEffect(() => {
        if (!open || highlightIdx < 0 || !listRef.current) return;
        const el = listRef.current.querySelector<HTMLLIElement>(`[data-idx="${highlightIdx}"]`);
        el?.scrollIntoView({ block: 'nearest' });
    }, [highlightIdx, open]);

    function close() {
        setOpen(false);
        setFocused(false);
        setQuery('');
        setHighlightIdx(-1);
    }

    function handleSelect(opt: SearchableSelectOption) {
        onChange?.(opt.value);
        close();
    }

    function handleTriggerKeyDown(e: React.KeyboardEvent) {
        if (disabled) return;
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
            e.preventDefault();
            setOpen(true);
        }
        if (e.key === 'Escape') close();
    }

    function handleInputKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightIdx(i => Math.min(i + 1, filtered.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightIdx(i => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (highlightIdx >= 0 && filtered[highlightIdx]) handleSelect(filtered[highlightIdx]);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    }

    function clearSelection(e: React.MouseEvent) {
        e.stopPropagation();
        onChange?.('');
        setQuery('');
    }

    const borderColor = error
        ? 'var(--color-danger)'
        : (focused || open)
            ? 'var(--color-primary)'
            : 'var(--color-border)';

    const ringColor = error
        ? 'rgba(239,68,68,0.15)'
        : 'color-mix(in srgb, var(--color-primary) 15%, transparent)';

    return (
        <div className={`flex flex-col gap-1 ${className}`} ref={containerRef}>
            {/* Label */}
            {label && (
                <label className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                    {label}
                    {required && <span className="ml-0.5" style={{ color: 'var(--color-danger)' }}>*</span>}
                </label>
            )}

            {/* Trigger */}
            <div className="relative">
                <button
                    type="button"
                    role="combobox"
                    aria-expanded={open}
                    aria-haspopup="listbox"
                    aria-controls="searchable-select-list"
                    disabled={disabled}
                    onFocus={() => setFocused(true)}
                    onBlur={() => { if (!open) setFocused(false); }}
                    onKeyDown={handleTriggerKeyDown}
                    onClick={() => { if (!disabled) setOpen(o => !o); }}
                    className="w-full flex items-center justify-between rounded-xl border px-3 py-2 text-sm outline-none transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-50"
                    style={{
                        borderColor,
                        backgroundColor: 'var(--color-surface)',
                        color: selected ? 'var(--color-text)' : 'var(--color-text-muted)',
                        boxShadow: (focused || open) ? `0 0 0 3px ${ringColor}` : 'none',
                    }}
                >
                    <span className="truncate text-left flex-1">
                        {selected?.label ?? placeholder}
                    </span>

                    <span className="flex items-center gap-1 flex-shrink-0">
                        {selected && !disabled && !required && (
                            <span
                                role="button"
                                tabIndex={-1}
                                aria-label="Limpiar selección"
                                onClick={clearSelection}
                                className="rounded p-0.5 transition-colors hover:bg-black/5 dark:hover:bg-white/10"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                <X size={13} />
                            </span>
                        )}
                        <ChevronDown
                            size={15}
                            className="transition-transform duration-200"
                            style={{
                                color: 'var(--color-text-muted)',
                                transform: open ? 'rotate(180deg)' : 'rotate(0deg)',
                            }}
                        />
                    </span>
                </button>

                {/* Dropdown */}
                {open && (
                    <div
                        className="absolute z-50 mt-1.5 w-full overflow-hidden rounded-xl border"
                        style={{
                            borderColor: 'var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                            boxShadow: '0 8px 24px rgb(0 0 0 / 0.10)',
                            animation: 'searchableSelectFadeIn 0.12s ease',
                        }}
                    >
                        {/* Input de busqueda */}
                        <div
                            className="flex items-center gap-2 px-3 py-2 border-b"
                            style={{ borderColor: 'var(--color-border)' }}
                        >
                            <Search size={14} style={{ color: 'var(--color-text-muted)' }} />
                            <input
                                ref={inputRef}
                                type="text"
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                onKeyDown={handleInputKeyDown}
                                placeholder={searchPlaceholder}
                                className="flex-1 bg-transparent text-sm outline-none"
                                style={{ color: 'var(--color-text)' }}
                                aria-autocomplete="list"
                                aria-controls="searchable-select-list"
                            />
                            {query && (
                                <button
                                    type="button"
                                    onClick={() => { setQuery(''); inputRef.current?.focus(); }}
                                    className="rounded p-0.5 transition-colors hover:bg-black/5 dark:hover:bg-white/10"
                                    style={{ color: 'var(--color-text-muted)' }}
                                    aria-label="Limpiar búsqueda"
                                >
                                    <X size={12} />
                                </button>
                            )}
                        </div>

                        {/* Lista de opciones filtradas */}
                        <ul
                            id="searchable-select-list"
                            ref={listRef}
                            role="listbox"
                            className="overflow-y-auto py-1"
                            style={{ maxHeight: '240px' }}
                        >
                            {filtered.length === 0 ? (
                                <li className="px-3 py-3 text-sm text-center" style={{ color: 'var(--color-text-muted)' }}>
                                    {emptyMessage}
                                </li>
                            ) : (
                                filtered.map((opt, idx) => {
                                    const isSelected    = opt.value === value;
                                    const isHighlighted = idx === highlightIdx;
                                    return (
                                        <li
                                            key={opt.value}
                                            data-idx={idx}
                                            role="option"
                                            aria-selected={isSelected}
                                            onMouseEnter={() => setHighlightIdx(idx)}
                                            onClick={() => handleSelect(opt)}
                                            className="flex items-center justify-between px-3 py-2 text-sm cursor-pointer transition-colors duration-75"
                                            style={{
                                                color: isSelected ? 'var(--color-primary)' : 'var(--color-text)',
                                                backgroundColor: isSelected
                                                    ? 'color-mix(in srgb, var(--color-primary) 12%, transparent)'
                                                    : isHighlighted
                                                        ? 'var(--color-bg)'
                                                        : 'transparent',
                                                fontWeight: isSelected ? 500 : 400,
                                            }}
                                        >
                                            <span className="truncate">{opt.label}</span>
                                            {isSelected && (
                                                <Check size={14} style={{ color: 'var(--color-primary)', flexShrink: 0 }} />
                                            )}
                                        </li>
                                    );
                                })
                            )}
                        </ul>

                        {/* Footer con contador (solo si hay filtro activo y hay resultados) */}
                        {query && filtered.length > 0 && (
                            <div
                                className="px-3 py-1.5 text-[10px] border-t"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    color: 'var(--color-text-muted)',
                                    backgroundColor: 'var(--color-bg)',
                                }}
                            >
                                {filtered.length} de {options.length} {filtered.length === 1 ? 'resultado' : 'resultados'}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Error / Hint */}
            {error && <p className="text-xs" style={{ color: 'var(--color-danger)' }}>{error}</p>}
            {hint && !error && <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{hint}</p>}

            {/* Animación */}
            <style>{`
                @keyframes searchableSelectFadeIn {
                    from { opacity: 0; transform: translateY(-4px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </div>
    );
}
