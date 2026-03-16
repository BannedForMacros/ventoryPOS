import React, { useState, useRef, useEffect } from 'react';
import { ChevronDown, Check } from 'lucide-react';

interface SelectOption {
    value: string | number;
    label: string;
}

interface SelectProps {
    label?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    options?: SelectOption[];
    value?: string | number;
    onChange?: (value: string | number) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
}

export default function Select({
    label,
    error,
    hint,
    required,
    options = [],
    value,
    onChange,
    placeholder = 'Seleccionar...',
    disabled = false,
    className = '',
}: SelectProps) {
    const [open, setOpen] = useState(false);
    const [focused, setFocused] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    const selected = options.find(o => o.value === value);

    // Cerrar al hacer click fuera
    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
                setFocused(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function handleSelect(opt: SelectOption) {
        onChange?.(opt.value);
        setOpen(false);
        setFocused(false);
    }

    function handleKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpen(o => !o); }
        if (e.key === 'Escape') { setOpen(false); setFocused(false); }
        if (e.key === 'ArrowDown' && options.length) {
            e.preventDefault();
            const idx = options.findIndex(o => o.value === value);
            handleSelect(options[Math.min(idx + 1, options.length - 1)]);
        }
        if (e.key === 'ArrowUp' && options.length) {
            e.preventDefault();
            const idx = options.findIndex(o => o.value === value);
            handleSelect(options[Math.max(idx - 1, 0)]);
        }
    }

    const borderColor = error
        ? 'var(--color-danger)'
        : focused || open
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
                    disabled={disabled}
                    onFocus={() => setFocused(true)}
                    onBlur={() => { if (!open) setFocused(false); }}
                    onKeyDown={handleKeyDown}
                    onClick={() => { if (!disabled) setOpen(o => !o); }}
                    className="w-full flex items-center justify-between rounded-xl border px-3 py-2 text-sm outline-none transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-50"
                    style={{
                        borderColor,
                        backgroundColor: 'var(--color-surface)',
                        color: selected ? 'var(--color-text)' : 'var(--color-text-muted)',
                        boxShadow: focused || open ? `0 0 0 3px ${ringColor}` : 'none',
                    }}
                >
                    <span className="truncate">{selected?.label ?? placeholder}</span>
                    <ChevronDown
                        size={15}
                        className="flex-shrink-0 transition-transform duration-200"
                        style={{
                            color: 'var(--color-text-muted)',
                            transform: open ? 'rotate(180deg)' : 'rotate(0deg)',
                        }}
                    />
                </button>

                {/* Dropdown — aquí están las "opciones" o "items del listado" */}
                {open && (
                    <ul
                        role="listbox"
                        className="absolute z-50 mt-1.5 w-full overflow-hidden rounded-xl border py-1"
                        style={{
                            borderColor: 'var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                            boxShadow: '0 8px 24px rgb(0 0 0 / 0.10)',
                            animation: 'selectFadeIn 0.12s ease',
                        }}
                    >
                        {options.length === 0 ? (
                            <li className="px-3 py-2.5 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                                Sin opciones
                            </li>
                        ) : (
                            options.map(opt => {
                                const isSelected = opt.value === value;
                                return (
                                    <li
                                        key={opt.value}
                                        role="option"
                                        aria-selected={isSelected}
                                        onClick={() => handleSelect(opt)}
                                        className="flex items-center justify-between px-3 py-2.5 text-sm cursor-pointer transition-colors duration-100"
                                        style={{
                                            color: isSelected ? 'var(--color-primary)' : 'var(--color-text)',
                                            backgroundColor: isSelected
                                                ? 'color-mix(in srgb, var(--color-primary) 8%, transparent)'
                                                : 'transparent',
                                            fontWeight: isSelected ? 500 : 400,
                                        }}
                                        onMouseEnter={e => {
                                            if (!isSelected)
                                                e.currentTarget.style.backgroundColor = 'var(--color-bg)';
                                        }}
                                        onMouseLeave={e => {
                                            if (!isSelected)
                                                e.currentTarget.style.backgroundColor = 'transparent';
                                        }}
                                    >
                                        <span>{opt.label}</span>
                                        {isSelected && (
                                            <Check size={14} style={{ color: 'var(--color-primary)', flexShrink: 0 }} />
                                        )}
                                    </li>
                                );
                            })
                        )}
                    </ul>
                )}
            </div>

            {/* Error / Hint */}
            {error && <p className="text-xs" style={{ color: 'var(--color-danger)' }}>{error}</p>}
            {hint && !error && <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{hint}</p>}

            {/* Animación */}
            <style>{`
                @keyframes selectFadeIn {
                    from { opacity: 0; transform: translateY(-4px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </div>
    );
}