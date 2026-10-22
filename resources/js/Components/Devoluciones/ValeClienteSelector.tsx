import { useEffect, useState } from 'react';
import axios from 'axios';
import { Search, UserPlus } from 'lucide-react';
import Button from '@/Components/UI/Button';
import ModalCrearCliente from '@/Pages/Pos/Partials/ModalCrearCliente';
import type { Cliente } from '@/types';

/** Dueño del vale de una devolución. */
export interface ValeCliente { id: number; nombre: string; documento: string | null }

type ClienteBusqueda = Pick<Cliente, 'id' | 'nombres' | 'apellidos' | 'razon_social' | 'numero_documento'> & { es_cliente_general?: boolean };

export const nombreDe = (c: Partial<ClienteBusqueda>) =>
    (c.razon_social || `${c.nombres ?? ''} ${c.apellidos ?? ''}`).trim() || `Cliente #${c.id}`;

const campo = { border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' };

/**
 * A nombre de quién queda el vale. Con la venta a «Cliente General» hay que
 * elegir o registrar a la persona: un vale sin dueño lo vería cualquiera en el
 * POS. La venta y su comprobante no cambian; solo el dueño del vale.
 */
export default function ValeClienteSelector({ value, onChange, bloqueado, error }: {
    value: ValeCliente | null;
    onChange: (c: ValeCliente | null) => void;
    /** Venta al crédito con deuda: lo devuelto baja su deuda, el dueño es fijo. */
    bloqueado?: string | null;
    error?: string;
}) {
    const [editando, setEditando] = useState(!value);
    const [q, setQ] = useState('');
    const [resultados, setResultados] = useState<ClienteBusqueda[]>([]);
    const [buscando, setBuscando] = useState(false);
    const [modalNuevo, setModalNuevo] = useState(false);

    useEffect(() => { if (!value) setEditando(true); }, [value]);

    useEffect(() => {
        const termino = q.trim();
        if (!editando || termino.length < 2) { setResultados([]); return; }
        let vivo = true;
        const t = setTimeout(() => {
            setBuscando(true);
            axios.get<{ clientes: ClienteBusqueda[] }>(route('devoluciones.clientes'), { params: { q: termino } })
                .then(({ data }) => { if (vivo) setResultados(data.clientes.filter(c => !c.es_cliente_general).slice(0, 8)); })
                .catch(() => { if (vivo) setResultados([]); })
                .finally(() => { if (vivo) setBuscando(false); });
        }, 300);
        return () => { vivo = false; clearTimeout(t); };
    }, [q, editando]);

    function elegir(c: ClienteBusqueda) {
        onChange({ id: c.id, nombre: nombreDe(c), documento: c.numero_documento ?? null });
        setEditando(false); setQ(''); setResultados([]);
    }

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                Vale a nombre de <span style={{ color: 'var(--color-danger)' }}>*</span>
            </p>

            {value && !editando ? (
                <div className="flex items-center justify-between gap-3 rounded-xl px-3 py-2" style={campo}>
                    <span className="text-sm">
                        <strong>{value.nombre}</strong>
                        {value.documento && <span style={{ color: 'var(--color-text-muted)' }}> · {value.documento}</span>}
                    </span>
                    {bloqueado
                        ? <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{bloqueado}</span>
                        : <Button type="button" variant="ghost" size="sm" onClick={() => setEditando(true)}>Cambiar</Button>}
                </div>
            ) : (
                <div className="space-y-2">
                    <div className="flex gap-2">
                        <div className="relative flex-1">
                            <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                            <input value={q} onChange={e => setQ(e.target.value)} autoFocus={!value}
                                placeholder="DNI, RUC o nombre de la persona"
                                aria-label="Buscar cliente para el vale"
                                className="w-full h-10 rounded-xl pl-9 pr-3 text-sm" style={campo} />
                        </div>
                        <Button type="button" variant="secondary" startContent={<UserPlus size={15} />} onClick={() => setModalNuevo(true)}>
                            Nuevo
                        </Button>
                        {value && <Button type="button" variant="ghost" onClick={() => setEditando(false)}>Cancelar</Button>}
                    </div>
                    {buscando && <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Buscando…</p>}
                    {resultados.length > 0 && (
                        <ul className="rounded-xl overflow-hidden" style={{ border: '1px solid var(--color-border)' }}>
                            {resultados.map(c => (
                                <li key={c.id}>
                                    <button type="button" onClick={() => elegir(c)}
                                        className="w-full text-left px-3 py-2 text-sm hover:opacity-80"
                                        style={{ backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', borderBottom: '1px solid var(--color-border)' }}>
                                        <strong>{nombreDe(c)}</strong>
                                        {c.numero_documento && <span style={{ color: 'var(--color-text-muted)' }}> · {c.numero_documento}</span>}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {!buscando && q.trim().length >= 2 && resultados.length === 0 && (
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>No se encontró. Regístralo con «Nuevo».</p>
                    )}
                </div>
            )}

            {error && <p className="text-xs font-semibold" role="alert" style={{ color: 'var(--color-danger)' }}>{error}</p>}

            <ModalCrearCliente
                isOpen={modalNuevo}
                onClose={() => setModalNuevo(false)}
                onCreated={c => { setModalNuevo(false); elegir(c as ClienteBusqueda); }}
            />
        </div>
    );
}
