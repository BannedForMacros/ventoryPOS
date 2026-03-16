import { useState } from 'react';
import { Palette, X } from 'lucide-react';
import { usePalette, type PaletteVars } from './ColorPaletteProvider';

interface ColorEntry {
    label: string;
    variable: keyof PaletteVars;
}

const COLOR_ENTRIES: ColorEntry[] = [
    { label: 'Primary', variable: '--color-primary' },
    { label: 'Primary Hover', variable: '--color-primary-hover' },
    { label: 'Secondary', variable: '--color-secondary' },
    { label: 'Secondary Hover', variable: '--color-secondary-hover' },
    { label: 'Success', variable: '--color-success' },
    { label: 'Success Hover', variable: '--color-success-hover' },
    { label: 'Danger', variable: '--color-danger' },
    { label: 'Danger Hover', variable: '--color-danger-hover' },
    { label: 'Warning', variable: '--color-warning' },
    { label: 'Warning Hover', variable: '--color-warning-hover' },
    { label: 'Sidebar BG', variable: '--color-sidebar-bg' },
    { label: 'Sidebar Text', variable: '--color-sidebar-text' },
    { label: 'Sidebar Active', variable: '--color-sidebar-active' },
    { label: 'Background', variable: '--color-bg' },
    { label: 'Surface', variable: '--color-surface' },
    { label: 'Border', variable: '--color-border' },
    { label: 'Text', variable: '--color-text' },
    { label: 'Text Muted', variable: '--color-text-muted' },
];

export default function ColorPaletteEditor() {
    const [open, setOpen] = useState(false);
    const { palette, setPaletteColor, resetPalette } = usePalette();

    return (
        <>
            {/* Floating trigger */}
            <button
                onClick={() => setOpen(true)}
                className="fixed bottom-6 right-6 z-50 flex h-12 w-12 items-center justify-center rounded-full shadow-lg text-white transition-all duration-150"
                style={{ backgroundColor: 'var(--color-primary)' }}
                title="Editar paleta de colores"
            >
                <Palette size={20} />
            </button>

            {/* Overlay */}
            {open && (
                <div
                    className="fixed inset-0 z-40 bg-black/30"
                    onClick={() => setOpen(false)}
                />
            )}

            {/* Drawer */}
            <div
                className="fixed top-0 right-0 z-50 h-full w-80 shadow-2xl flex flex-col transition-transform duration-300"
                style={{
                    backgroundColor: 'var(--color-surface)',
                    borderLeft: '1px solid var(--color-border)',
                    transform: open ? 'translateX(0)' : 'translateX(100%)',
                }}
            >
                {/* Header */}
                <div
                    className="flex items-center justify-between px-4 py-3 border-b"
                    style={{ borderColor: 'var(--color-border)' }}
                >
                    <div className="flex items-center gap-2">
                        <Palette size={18} style={{ color: 'var(--color-primary)' }} />
                        <span className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>
                            Paleta de Colores
                        </span>
                    </div>
                    <button
                        onClick={() => setOpen(false)}
                        className="rounded p-1 transition-colors duration-150"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <X size={18} />
                    </button>
                </div>

                {/* Color list */}
                <div className="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                    {COLOR_ENTRIES.map(({ label, variable }) => (
                        <div key={variable} className="flex items-center gap-3">
                            <input
                                type="color"
                                value={palette[variable]}
                                onChange={e => setPaletteColor(variable, e.target.value)}
                                className="h-8 w-8 cursor-pointer rounded border p-0.5"
                                style={{ borderColor: 'var(--color-border)' }}
                            />
                            <input
                                type="text"
                                value={palette[variable]}
                                onChange={e => setPaletteColor(variable, e.target.value)}
                                className="flex-1 rounded border px-2 py-1 text-xs font-mono"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    color: 'var(--color-text)',
                                    backgroundColor: 'var(--color-bg)',
                                }}
                            />
                            <span
                                className="w-24 text-xs truncate"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                {label}
                            </span>
                        </div>
                    ))}
                </div>

                {/* Footer */}
                <div
                    className="px-4 py-3 border-t"
                    style={{ borderColor: 'var(--color-border)' }}
                >
                    <button
                        onClick={resetPalette}
                        className="w-full rounded border px-3 py-2 text-sm font-medium transition-colors duration-150"
                        style={{
                            borderColor: 'var(--color-border)',
                            color: 'var(--color-text-muted)',
                            backgroundColor: 'var(--color-bg)',
                        }}
                    >
                        Resetear paleta
                    </button>
                </div>
            </div>
        </>
    );
}
