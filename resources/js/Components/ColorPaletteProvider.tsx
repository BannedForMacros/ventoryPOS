import React, { createContext, useContext, useEffect, useState } from 'react';

const STORAGE_KEY = 'macsoft_palette';

export interface PaletteVars {
    '--color-primary': string;
    '--color-primary-hover': string;
    '--color-secondary': string;
    '--color-secondary-hover': string;
    '--color-success': string;
    '--color-success-hover': string;
    '--color-danger': string;
    '--color-danger-hover': string;
    '--color-warning': string;
    '--color-warning-hover': string;
    '--color-sidebar-bg': string;
    '--color-sidebar-text': string;
    '--color-sidebar-active': string;
    '--color-bg': string;
    '--color-surface': string;
    '--color-border': string;
    '--color-text': string;
    '--color-text-muted': string;
}

const defaults: PaletteVars = {
    '--color-primary': '#3b82f6',
    '--color-primary-hover': '#2563eb',
    '--color-secondary': '#6b7280',
    '--color-secondary-hover': '#4b5563',
    '--color-success': '#10b981',
    '--color-success-hover': '#059669',
    '--color-danger': '#ef4444',
    '--color-danger-hover': '#dc2626',
    '--color-warning': '#f59e0b',
    '--color-warning-hover': '#d97706',
    '--color-sidebar-bg': '#1e293b',
    '--color-sidebar-text': '#cbd5e1',
    '--color-sidebar-active': '#3b82f6',
    '--color-bg': '#f1f5f9',
    '--color-surface': '#ffffff',
    '--color-border': '#e2e8f0',
    '--color-text': '#0f172a',
    '--color-text-muted': '#64748b',
};

interface PaletteContextValue {
    palette: PaletteVars;
    setPaletteColor: (variable: keyof PaletteVars, value: string) => void;
    resetPalette: () => void;
}

const PaletteContext = createContext<PaletteContextValue | null>(null);

function applyToRoot(vars: PaletteVars) {
    const root = document.documentElement;
    Object.entries(vars).forEach(([key, value]) => {
        root.style.setProperty(key, value);
    });
}

export function ColorPaletteProvider({ children }: { children: React.ReactNode }) {
    const [palette, setPalette] = useState<PaletteVars>(() => {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) return { ...defaults, ...JSON.parse(stored) };
        } catch {
            // ignore
        }
        return defaults;
    });

    useEffect(() => {
        applyToRoot(palette);
    }, [palette]);

    const setPaletteColor = (variable: keyof PaletteVars, value: string) => {
        setPalette(prev => {
            const next = { ...prev, [variable]: value };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
            return next;
        });
    };

    const resetPalette = () => {
        localStorage.removeItem(STORAGE_KEY);
        setPalette(defaults);
    };

    return (
        <PaletteContext.Provider value={{ palette, setPaletteColor, resetPalette }}>
            {children}
        </PaletteContext.Provider>
    );
}

export function usePalette(): PaletteContextValue {
    const ctx = useContext(PaletteContext);
    if (!ctx) throw new Error('usePalette must be used inside ColorPaletteProvider');
    return ctx;
}
