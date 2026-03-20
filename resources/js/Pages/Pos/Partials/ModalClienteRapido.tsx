import React, { useState } from 'react';
import { Search, User, Check } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import type { Cliente } from '@/types';

interface Props {
    isOpen:    boolean;
    onClose:   () => void;
    clientes:  Cliente[];
    selected:  Cliente | null;
    onSelect:  (cliente: Cliente | null) => void;
}

export default function ModalClienteRapido({ isOpen, onClose, clientes, selected, onSelect }: Props) {
    const [busqueda, setBusqueda] = useState('');

    const filtrados = clientes.filter(c => {
        const q = busqueda.toLowerCase();
        return (
            c.nombres.toLowerCase().includes(q) ||
            (c.apellidos ?? '').toLowerCase().includes(q) ||
            (c.razon_social ?? '').toLowerCase().includes(q) ||
            (c.numero_documento ?? '').includes(busqueda) ||
            (c.telefono ?? '').includes(busqueda)
        );
    });

    function elegir(cliente: Cliente | null) {
        onSelect(cliente);
        onClose();
        setBusqueda('');
    }

    return (
        <Modal isOpen={isOpen} onClose={() => { onClose(); setBusqueda(''); }} title="Seleccionar cliente" size="md">
            <div className="flex flex-col gap-3">
                {/* Buscador */}
                <div className="relative">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                    <input
                        type="text"
                        value={busqueda}
                        onChange={e => setBusqueda(e.target.value)}
                        placeholder="Buscar por nombre, DNI, teléfono..."
                        autoFocus
                        className="w-full pl-9 pr-3 py-2.5 text-sm border rounded-xl focus:outline-none focus:ring-2"
                        style={{
                            borderColor: 'var(--color-border)',
                            backgroundColor: 'var(--color-bg)',
                            color: 'var(--color-text)',
                            '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                        } as React.CSSProperties}
                    />
                </div>

                <div className="max-h-80 overflow-y-auto flex flex-col gap-1">
                    {/* Opción: cliente general */}
                    <button
                        onClick={() => elegir(null)}
                        className="text-left px-3 py-2.5 rounded-xl text-sm transition-colors flex items-center gap-3 group"
                        style={{
                            color: 'var(--color-text)',
                            backgroundColor: selected === null ? 'color-mix(in srgb, var(--color-primary) 8%, transparent)' : undefined,
                            border: selected === null ? '1px solid color-mix(in srgb, var(--color-primary) 20%, transparent)' : '1px solid transparent',
                        }}
                    >
                        <div
                            className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                            style={{
                                backgroundColor: 'color-mix(in srgb, var(--color-secondary) 10%, transparent)',
                                color: 'var(--color-secondary)',
                            }}
                        >
                            <User size={14} />
                        </div>
                        <div className="flex-1">
                            <p className="font-semibold">Cliente general</p>
                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Sin documento</p>
                        </div>
                        {selected === null && <Check size={16} style={{ color: 'var(--color-primary)' }} />}
                    </button>

                    {filtrados.map(c => (
                        <button
                            key={c.id}
                            onClick={() => elegir(c)}
                            className="text-left px-3 py-2.5 rounded-xl text-sm transition-colors flex items-center gap-3 group hover:bg-black/[0.03]"
                            style={{
                                color: 'var(--color-text)',
                                backgroundColor: selected?.id === c.id ? 'color-mix(in srgb, var(--color-primary) 8%, transparent)' : undefined,
                                border: selected?.id === c.id ? '1px solid color-mix(in srgb, var(--color-primary) 20%, transparent)' : '1px solid transparent',
                            }}
                        >
                            <div
                                className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold"
                                style={{
                                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)',
                                    color: 'var(--color-primary)',
                                }}
                            >
                                {(c.nombres[0] ?? '').toUpperCase()}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="font-semibold truncate">
                                    {c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim()}
                                </p>
                                <p className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>
                                    {c.tipo_documento} {c.numero_documento}
                                    {c.telefono && ` · ${c.telefono}`}
                                </p>
                            </div>
                            {selected?.id === c.id && <Check size={16} className="flex-shrink-0" style={{ color: 'var(--color-primary)' }} />}
                        </button>
                    ))}

                    {filtrados.length === 0 && busqueda && (
                        <div className="text-center py-8" style={{ color: 'var(--color-text-muted)' }}>
                            <User size={32} className="mx-auto mb-2 opacity-20" />
                            <p className="text-sm">No se encontraron clientes</p>
                        </div>
                    )}
                </div>
            </div>
        </Modal>
    );
}
