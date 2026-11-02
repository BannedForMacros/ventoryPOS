import { Factory, PackageCheck, Route, Truck } from 'lucide-react';

/**
 * Piezas compartidas de Flota y viajes: estado del viaje, barra de carga del
 * camión, semáforo de la licencia y etiquetas de tipo.
 */

export type EstadoViaje = 'programado' | 'en_ruta' | 'cerrado' | 'anulado';
export type TipoViaje = 'reparto' | 'abastecimiento' | 'flete';
export type EstadoLicencia = 'vigente' | 'por_vencer' | 'vencida' | null;

export const TIPOS_VIAJE: Record<TipoViaje, { label: string; ayuda: string; Icono: typeof Truck }> = {
    reparto:        { label: 'Reparto',        ayuda: 'Lleva ventas a los clientes', Icono: Truck },
    abastecimiento: { label: 'Abastecimiento', ayuda: 'Trae carga de la fábrica o del proveedor al almacén', Icono: Factory },
    flete:          { label: 'Flete',          ayuda: 'Transporte para un tercero, cobrado con una venta', Icono: Route },
};

const ESTADOS: Record<EstadoViaje, { label: string; color: string }> = {
    programado: { label: 'Programado', color: 'var(--vp-amber-ink)' },
    en_ruta:    { label: 'En ruta',    color: 'var(--vp-navy)' },
    cerrado:    { label: 'Cerrado',    color: 'var(--vp-mint-ink)' },
    anulado:    { label: 'Anulado',    color: 'var(--color-text-muted)' },
};

export function Pastilla({ color, children, icono, className = '' }: { color: string; children: React.ReactNode; icono?: React.ReactNode; className?: string }) {
    return (
        <span className={`inline-flex items-center gap-1 text-[13px] font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap ${className}`}
            style={{ color, backgroundColor: `color-mix(in srgb, ${color} 12%, transparent)` }}>
            {icono}{children}
        </span>
    );
}

export function EstadoViajeBadge({ estado }: { estado: EstadoViaje }) {
    const e = ESTADOS[estado] ?? ESTADOS.programado;
    return (
        <Pastilla color={e.color}>
            {estado === 'en_ruta' && (
                <span className="relative flex h-2 w-2 mr-0.5" aria-hidden>
                    <span className="motion-safe:animate-ping absolute inline-flex h-full w-full rounded-full opacity-60" style={{ backgroundColor: e.color }} />
                    <span className="relative inline-flex rounded-full h-2 w-2" style={{ backgroundColor: e.color }} />
                </span>
            )}
            {e.label}
        </Pastilla>
    );
}

export function TipoViajeBadge({ tipo }: { tipo: TipoViaje }) {
    const t = TIPOS_VIAJE[tipo] ?? TIPOS_VIAJE.reparto;
    return <Pastilla color="var(--color-text-muted)" icono={<t.Icono size={13} />}>{t.label}</Pastilla>;
}

export function LicenciaBadge({ estado, vence }: { estado: EstadoLicencia; vence?: string | null }) {
    if (!estado) return <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Sin fecha</span>;
    const map = {
        vigente:    { color: 'var(--vp-mint-ink)',  label: 'Vigente' },
        por_vencer: { color: 'var(--vp-amber-ink)', label: 'Por vencer' },
        vencida:    { color: 'var(--vp-coral-ink)', label: 'Vencida' },
    }[estado];
    return (
        <Pastilla color={map.color}>
            {map.label}{vence ? <span className="font-normal opacity-80 tabular-nums">· {fmtFechaCorta(vence)}</span> : null}
        </Pastilla>
    );
}

export interface Carga {
    peso_kg: number;
    capacidad_kg: number | null;
    porcentaje: number | null;
    sin_peso: boolean;
}

/** Peso cargado contra la capacidad del camión. Ámbar desde 90 %, rojo al pasarse. */
export function BarraCarga({ carga, compacta = false }: { carga: Carga; compacta?: boolean }) {
    const pct = carga.porcentaje;
    const color = pct === null ? 'var(--color-text-muted)'
        : pct > 100 ? 'var(--color-danger)'
        : pct >= 90 ? 'var(--vp-amber)'
        : 'var(--vp-mint)';

    return (
        <div className={compacta ? 'min-w-[110px]' : ''}>
            <div className="flex items-baseline justify-between gap-2">
                <span className={`tabular-nums font-semibold ${compacta ? 'text-[13px]' : 'text-sm'}`} style={{ color: 'var(--color-text)' }}>
                    {fmtKg(carga.peso_kg)}
                    {carga.capacidad_kg ? <span className="font-normal" style={{ color: 'var(--color-text-muted)' }}> / {fmtKg(carga.capacidad_kg)}</span> : null}
                </span>
                {pct !== null && (
                    <span className="text-[13px] font-bold tabular-nums" style={{ color: pct > 100 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>{pct}%</span>
                )}
            </div>
            {pct !== null && (
                <div className={`mt-1 rounded-full overflow-hidden ${compacta ? 'h-1.5' : 'h-2'}`} style={{ backgroundColor: 'color-mix(in srgb, var(--color-text-muted) 15%, transparent)' }}
                    role="meter" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.min(pct, 100)} aria-label="Carga del vehículo">
                    <div className="h-full rounded-full origin-left transition-transform duration-300 ease-out"
                        style={{ backgroundColor: color, transform: `scaleX(${Math.min(pct, 100) / 100})` }} />
                </div>
            )}
            {!compacta && carga.sin_peso && (
                <p className="mt-1 text-[12px]" style={{ color: 'var(--color-text-muted)' }}>
                    Algunos productos no tienen peso: cárgalo en Catálogo → Productos para que la carga sea exacta.
                </p>
            )}
        </div>
    );
}

export function fmtKg(kg: number | null | undefined): string {
    const n = Number(kg ?? 0);
    if (n >= 1000) return `${(n / 1000).toLocaleString('es-PE', { maximumFractionDigits: 2 })} t`;
    return `${n.toLocaleString('es-PE', { maximumFractionDigits: 1 })} kg`;
}

export function fmtCantidad(v: number | string | null | undefined): string {
    const n = Number(v ?? 0);
    return n.toLocaleString('es-PE', { maximumFractionDigits: 4 });
}

export function fmtFechaCorta(ymd: string): string {
    const [y, m, d] = ymd.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-PE', { day: 'numeric', month: 'short', year: '2-digit' });
}

export function fmtDia(ymd: string): string {
    const [y, m, d] = ymd.slice(0, 10).split('-').map(Number);
    const fecha = new Date(y, m - 1, d);
    const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    const dif = Math.round((fecha.getTime() - hoy.getTime()) / 86400000);
    if (dif === 0) return 'Hoy';
    if (dif === 1) return 'Mañana';
    if (dif === -1) return 'Ayer';
    return fecha.toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric', month: 'short' });
}

export const IconoEntregado = PackageCheck;
