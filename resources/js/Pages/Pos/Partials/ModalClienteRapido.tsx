import React, { useEffect, useRef, useState } from 'react';
import { Search, User, Check, UserPlus, RefreshCw } from 'lucide-react';
import axios from 'axios';
import Modal from '@/Components/UI/Modal';
import type { Cliente } from '@/types';

interface Props {
    isOpen:    boolean;
    onClose:   () => void;
    selected:  Cliente | null;
    onSelect:  (cliente: Cliente | null) => void;
    // Abre el modal de alta de cliente sin salir del POS.
    onCrearNuevo: () => void;
}

type ClienteItem = Cliente & { es_cliente_general?: boolean };

export default function ModalClienteRapido({ isOpen, onClose, selected, onSelect, onCrearNuevo }: Props) {
    const [busqueda, setBusqueda]           = useState('');
    const [debouncedQ, setDebouncedQ]       = useState('');
    const [clientes, setClientes]           = useState<ClienteItem[]>([]);
    const [hasMore, setHasMore]             = useState(false);
    const [cursor, setCursor]               = useState<string | null>(null);
    const [loading, setLoading]             = useState(false);
    const abortRef = useRef<AbortController | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const listRef  = useRef<HTMLDivElement>(null);
    const sentinelRef = useRef<HTMLDivElement>(null);

    // Debounce de la búsqueda.
    useEffect(() => {
        timerRef.current && clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => setDebouncedQ(busqueda.trim()), 250);
        return () => { timerRef.current && clearTimeout(timerRef.current); };
    }, [busqueda]);

    // Cargar clientes cuando cambia la búsqueda o se abre el modal.
    useEffect(() => {
        if (!isOpen) return;
        cargarClientes({ q: debouncedQ, cursor: null, reset: true });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedQ, isOpen]);

    // Scroll infinito dentro de la lista de clientes.
    useEffect(() => {
        const root = listRef.current;
        const sentinel = sentinelRef.current;
        if (!root || !sentinel) return;
        const obs = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore && !loading) {
                    cargarClientes({ q: debouncedQ, cursor, reset: false });
                }
            },
            { root, rootMargin: '0px 0px 80px 0px', threshold: 0 },
        );
        obs.observe(sentinel);
        return () => obs.disconnect();
    }, [hasMore, loading, cursor, debouncedQ]);

    // Cancelar request pendiente al cerrar.
    useEffect(() => () => { abortRef.current?.abort(); }, []);

    async function cargarClientes({
        q,
        cursor,
        reset,
    }: { q: string; cursor: string | null; reset: boolean }) {
        if (loading) return;
        setLoading(true);
        abortRef.current?.abort();
        const ctrl = new AbortController();
        abortRef.current = ctrl;
        try {
            const params: Record<string, any> = { q };
            if (cursor) params.cursor = cursor;
            const { data } = await axios.get<{
                clientes: ClienteItem[];
                has_more: boolean;
                cursor: string | null;
            }>(route('pos.clientes'), { params, signal: ctrl.signal });
            setHasMore(data.has_more);
            setCursor(data.cursor ?? null);
            setClientes(prev => {
                if (reset) return data.clientes;
                const vistos = new Set(prev.map(c => c.id));
                return [...prev, ...data.clientes.filter(c => !vistos.has(c.id))];
            });
        } catch (e: any) {
            if (!axios.isCancel(e)) {
                // No molestar al cajero con toast; se muestra el estado vacío.
            }
        } finally {
            setLoading(false);
        }
    }

    function elegir(cliente: ClienteItem | null) {
        onSelect(cliente);
        onClose();
        setBusqueda('');
        setDebouncedQ('');
    }

    const esGeneral = !selected
        || (selected as ClienteItem).es_cliente_general
        || selected.numero_documento === '99999999';

    return (
        <Modal isOpen={isOpen} onClose={() => { onClose(); setBusqueda(''); setDebouncedQ(''); }} title="Seleccionar cliente" size="md">
            <div className="flex flex-col gap-3">
                {/* Buscador */}
                <div className="relative">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                    <input
                        type="text"
                        value={busqueda}
                        onChange={e => setBusqueda(e.target.value)}
                        placeholder="Buscar por nombre, DNI, RUC..."
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

                {/* Alta rápida: crear el cliente aquí mismo, sin ir a otra página */}
                <button
                    onClick={() => { setBusqueda(''); setDebouncedQ(''); onCrearNuevo(); }}
                    className="flex items-center justify-center gap-2 w-full px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors hover:opacity-90 active:scale-[0.99]"
                    style={{
                        border: '1.5px dashed var(--color-primary)',
                        color: 'var(--color-primary)',
                        backgroundColor: 'color-mix(in srgb, var(--color-primary) 6%, transparent)',
                    }}
                >
                    <UserPlus size={16} />
                    Crear nuevo cliente
                </button>

                <div ref={listRef} className="max-h-80 overflow-y-auto flex flex-col gap-1">
                    {/* Opción: cliente general */}
                    <button
                        onClick={() => elegir(null)}
                        className="text-left px-3 py-2.5 rounded-xl text-sm transition-colors flex items-center gap-3 group"
                        style={{
                            color: 'var(--color-text)',
                            backgroundColor: esGeneral ? 'color-mix(in srgb, var(--color-primary) 8%, transparent)' : undefined,
                            border: esGeneral ? '1px solid color-mix(in srgb, var(--color-primary) 20%, transparent)' : '1px solid transparent',
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
                        {esGeneral && <Check size={16} style={{ color: 'var(--color-primary)' }} />}
                    </button>

                    {clientes.map(c => {
                        // RUC → razon_social; persona natural → nombres+apellidos.
                        const displayName = (c.razon_social ?? `${c.nombres ?? ''} ${c.apellidos ?? ''}`.trim()) || 'Cliente';
                        return (
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
                                {displayName.charAt(0).toUpperCase()}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="font-semibold truncate">
                                    {displayName}
                                </p>
                                <p className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>
                                    {c.tipo_documento} {c.numero_documento}
                                    {c.telefono && ` · ${c.telefono}`}
                                </p>
                            </div>
                            {selected?.id === c.id && <Check size={16} className="flex-shrink-0" style={{ color: 'var(--color-primary)' }} />}
                        </button>
                        );
                    })}

                    {(hasMore || loading) && clientes.length > 0 && (
                        <div ref={sentinelRef} className="flex items-center justify-center py-3">
                            {loading && <RefreshCw size={16} className="animate-spin" style={{ color: 'var(--color-text-muted)' }} />}
                        </div>
                    )}

                    {clientes.length === 0 && debouncedQ && !loading && (
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
