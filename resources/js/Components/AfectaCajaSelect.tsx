import { usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import Select from '@/Components/UI/Select';
import type { PageProps } from '@/types';

/** Turno abierto tal como lo sirven los índices (campos mínimos). */
export interface TurnoLite {
    id: number;
    user?: { name?: string } | null;
    caja?: { nombre?: string } | null;
    fecha_apertura?: string;
    estado?: string;
}

interface Props {
    /** Clave del módulo en empresa.afecta_caja (ej. 'deuda', 'gastos'). */
    modulo: string;
    /** Turnos abiertos que sirve el controlador de la página. */
    turnos: TurnoLite[];
    /** turno_id seleccionado ('' = sin turno). */
    value: number | '';
    onChange: (v: number | '') => void;
    error?: string;
    /** Texto de ayuda opcional bajo el selector (qué caja se afecta y cómo). */
    hint?: string;
    /**
     * 'forzado' (gastos, devoluciones, deuda): el cajero con turno activo se
     * imputa a SU caja (no elige). 'libre' (entradas, cxp, cxc, anticipos):
     * TODOS ven el dropdown y pueden elegir "Sin turno".
     */
    modo?: 'forzado' | 'libre';
    /** 'corto' = "Caja · Usuario (tu turno)"; 'largo' = "#id · fecha hora · usuario · caja · abierto". */
    formato?: 'corto' | 'largo';
    /** Etiqueta del campo (default "Afecta caja a"). */
    label?: string;
    /** Etiqueta de la opción sin turno (default "Sin turno (no afecta ninguna caja)"). */
    sinTurnoLabel?: string;
}

function turnoLabel(t: TurnoLite, formato: 'corto' | 'largo', activoId?: number): string {
    if (formato === 'largo') {
        const fecha = t.fecha_apertura ? new Date(t.fecha_apertura) : null;
        const f = fecha ? fecha.toLocaleDateString('es-PE') : '';
        const hora = fecha ? fecha.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }) : '';
        return [`#${t.id}`, [f, hora].filter(Boolean).join(' '), t.user?.name, t.caja?.nombre]
            .filter(Boolean).join(' · ') + (t.estado === 'abierto' ? ' · abierto' : '');
    }
    const partes = [t.caja?.nombre ?? 'Caja', t.user?.name ?? ''].filter(Boolean);
    return partes.join(' · ') + (activoId === t.id ? ' (tu turno)' : '');
}

/**
 * Selector unificado "Afecta caja a (turno)". Reemplaza el bloque que estaba
 * duplicado en cada página. Se auto-gobierna con la config de la empresa
 * (empresa.afecta_caja[modulo].activo): si el módulo está apagado no renderiza
 * nada y el backend ignora cualquier turno_id (AfectaCaja::resolverTurno).
 */
export default function AfectaCajaSelect({
    modulo, turnos, value, onChange, error, hint,
    modo = 'forzado', formato = 'corto',
    label = 'Afecta caja a', sinTurnoLabel = 'Sin turno (no afecta ninguna caja)',
}: Props) {
    const { auth, turno_activo } = usePage<PageProps>().props;

    const activoModulo = auth.user.empresa?.afecta_caja?.[modulo]?.activo ?? false;
    if (!activoModulo) return null;

    const esAdmin = auth.user.rol?.es_admin ?? false;
    const turnoActivo = turno_activo;

    const marco = {
        backgroundColor: 'var(--color-surface)',
        border: '1px solid var(--color-border)',
    } as React.CSSProperties;

    const dropdown = (
        <div className="rounded-xl px-4 py-3" style={marco}>
            <Select
                label={label}
                value={value}
                onChange={v => onChange(v === '' ? '' : Number(v))}
                error={error}
                options={[
                    { value: '', label: sinTurnoLabel },
                    ...turnos.map(t => ({ value: t.id, label: turnoLabel(t, formato, turnoActivo?.id) })),
                ]}
            />
            <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                {hint ?? 'El efectivo saldrá/entrará de la caja de este turno. "Sin turno" solo lo registra, sin afectar caja.'}
            </p>
        </div>
    );

    // Modo LIBRE: todos ven el dropdown (cajero incluido) y pueden elegir "Sin
    // turno". Si no hay turnos abiertos, no hay caja que afectar → nada.
    if (modo === 'libre') {
        return turnos.length > 0 ? dropdown : null;
    }

    // Modo FORZADO: el cajero con turno propio se imputa a su caja (no elige).
    if (!esAdmin && turnoActivo) {
        return (
            <div className="rounded-xl px-4 py-3 text-sm" style={marco}>
                <span style={{ color: 'var(--color-text-muted)' }}>Afecta caja: </span>
                <strong>{turnoActivo.caja?.nombre ?? 'tu caja'}</strong>
                <span style={{ color: 'var(--color-text-muted)' }}> (tu turno abierto)</span>
            </div>
        );
    }

    // Admin o sin turno propio: sin turnos abiertos no hay caja que afectar.
    if (turnos.length === 0) {
        return (
            <div className="flex items-start gap-2 rounded-xl px-4 py-3 text-sm"
                style={{ backgroundColor: 'rgba(234,179,8,0.08)', border: '1px solid rgba(234,179,8,0.3)' }}>
                <AlertTriangle size={16} className="mt-0.5" style={{ color: '#b45309' }} />
                <span>No hay turnos abiertos. Se registrará sin afectar ninguna caja.</span>
            </div>
        );
    }

    return dropdown;
}
