import React, { useState } from 'react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
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
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Seleccionar cliente" size="md">
            <div className="flex flex-col gap-3">
                <input
                    type="text"
                    value={busqueda}
                    onChange={e => setBusqueda(e.target.value)}
                    placeholder="Buscar por nombre, DNI, teléfono..."
                    autoFocus
                    className="w-full text-sm border rounded-lg px-3 py-2"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                />

                <div className="max-h-72 overflow-y-auto flex flex-col gap-1">
                    {/* Opción: cliente general */}
                    <button
                        onClick={() => elegir(null)}
                        className="text-left px-3 py-2 rounded-lg text-sm hover:bg-black/5 transition-colors flex items-center gap-2"
                        style={{
                            color: 'var(--color-text)',
                            backgroundColor: selected === null ? 'color-mix(in srgb, var(--color-primary) 10%, transparent)' : undefined,
                        }}
                    >
                        <span className="font-medium">Cliente general</span>
                        <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Sin documento</span>
                    </button>

                    {filtrados.map(c => (
                        <button
                            key={c.id}
                            onClick={() => elegir(c)}
                            className="text-left px-3 py-2 rounded-lg text-sm hover:bg-black/5 transition-colors"
                            style={{
                                color: 'var(--color-text)',
                                backgroundColor: selected?.id === c.id ? 'color-mix(in srgb, var(--color-primary) 10%, transparent)' : undefined,
                            }}
                        >
                            <p className="font-medium">
                                {c.razon_social ?? `${c.nombres} ${c.apellidos ?? ''}`.trim()}
                            </p>
                            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                {c.tipo_documento} {c.numero_documento}
                                {c.telefono && ` · ${c.telefono}`}
                            </p>
                        </button>
                    ))}

                    {filtrados.length === 0 && busqueda && (
                        <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>
                            No se encontraron clientes
                        </p>
                    )}
                </div>
            </div>
        </Modal>
    );
}
