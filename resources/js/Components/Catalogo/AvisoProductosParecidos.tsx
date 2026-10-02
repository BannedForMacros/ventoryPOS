import { useEffect, useState } from 'react';
import axios from 'axios';
import { AlertTriangle } from 'lucide-react';

interface Parecido { id: number; codigo: string | null; nombre: string }

/**
 * Debajo del campo Nombre al crear un producto: avisa si ya existe uno con
 * un nombre parecido ("¿no será el mismo?"). No bloquea: duplicar a veces es
 * lo correcto, pero un gemelo por error parte el stock en dos productos.
 */
export default function AvisoProductosParecidos({ nombre }: { nombre: string }) {
    const [parecidos, setParecidos] = useState<Parecido[]>([]);

    useEffect(() => {
        const q = nombre.trim();
        if (q.length < 4) { setParecidos([]); return; }
        const ctrl = new AbortController();
        const t = setTimeout(() => {
            axios.get<{ productos: Parecido[] }>(route('catalogo.productos.parecidos'), { params: { nombre: q }, signal: ctrl.signal })
                .then(({ data }) => setParecidos(data.productos))
                .catch(() => { /* sin aviso si falla: no estorba la creación */ });
        }, 400);
        return () => { clearTimeout(t); ctrl.abort(); };
    }, [nombre]);

    if (parecidos.length === 0) return null;

    return (
        <div role="status" className="mt-1.5 flex gap-2 rounded-lg border px-2.5 py-2 text-xs leading-snug"
            style={{
                borderColor: 'color-mix(in srgb, var(--color-warning) 55%, transparent)',
                backgroundColor: 'color-mix(in srgb, var(--color-warning) 10%, var(--color-surface))',
                color: 'var(--color-text)',
            }}>
            <AlertTriangle size={14} className="mt-0.5 shrink-0" style={{ color: 'var(--color-warning)' }} />
            <div className="min-w-0">
                <p className="font-semibold">Ya existe algo parecido. ¿No será el mismo producto?</p>
                <ul className="mt-0.5 space-y-0.5">
                    {parecidos.map(p => (
                        <li key={p.id} className="truncate">
                            {p.nombre}
                            {p.codigo && <span style={{ color: 'var(--color-text-muted)' }}> · {p.codigo}</span>}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
