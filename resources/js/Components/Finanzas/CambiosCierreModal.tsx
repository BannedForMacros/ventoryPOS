import { useEffect, useState } from 'react';
import { ChevronDown, FileText } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';

/**
 * "¿Qué cambió después del cierre?" de un día confirmado.
 *
 * Muestra la foto guardada al cerrar contra lo que dicen hoy los documentos,
 * línea por línea, con los documentos que lo causaron (qué, cuándo y quién).
 * Solo lee: el día cerrado no se modifica.
 */

interface Documento {
    categorias: string[];
    tipo: string;
    documento: string | null;
    fecha_documento: string | null;
    cuando: string | null;
    monto: number | null;
    usuario: string | null;
}

interface Categoria {
    categoria: string;
    seccion: 'favor' | 'contra';
    guardado: number;
    actual: number;
    diferencia: number;
    efecto: number;
    documentos: Documento[];
    sin_documento: boolean;
}

interface Linea {
    seccion: string;
    categoria: string;
    descripcion: string;
    guardado: number;
    actual: number;
    diferencia: number;
}

interface Analisis {
    fecha: string;
    foto_tomada: string | null;
    patrimonio_guardado: number;
    patrimonio_actual: number;
    diferencia_patrimonio: number;
    relevante: boolean;
    umbral: number;
    categorias: Categoria[];
    lineas: Linea[];
    metricas: Record<string, { guardado: number; actual: number; diferencia: number }>;
}

export const CATEGORIA_NOMBRE: Record<string, string> = {
    efectivo:           'Efectivo',
    cuenta_bancaria:    'Cuentas bancarias',
    stock:              'Stock (inventario)',
    cxc:                'Deudas por cobrar',
    prestamo_otorgado:  'Préstamos otorgados',
    adelanto_proveedor: 'Adelantos a proveedores',
    planilla_descuento: 'Descuentos de planilla',
    cxp:                'Proveedores por pagar',
    anticipo_cliente:   'Anticipos de clientes',
    deuda:              'Deudas y préstamos',
    personal:           'Deudas con el personal',
};

const METRICA_NOMBRE: Record<string, string> = {
    ventas_dia: 'Ventas del día',
    costo_dia:  'Costo de lo vendido',
    gastos_dia: 'Gastos del día',
};

const money = (v: number) =>
    `S/ ${Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const signed = (v: number) => `${v > 0 ? '+' : v < 0 ? '−' : ''}${money(Math.abs(v))}`;

const colorDe = (v: number) => (v > 0 ? 'var(--color-success)' : v < 0 ? 'var(--color-danger)' : 'var(--color-text)');

const fechaLarga = (f: string) =>
    new Date(f.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE', { weekday: 'long', day: '2-digit', month: 'long' });

const fechaCorta = (f: string | null) =>
    f ? new Date(f.slice(0, 10) + 'T00:00:00').toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' }) : '—';

export default function CambiosCierreModal({ fecha, onClose }: { fecha: string | null; onClose: () => void }) {
    const [data, setData] = useState<Analisis | null>(null);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(false);
    const [abiertas, setAbiertas] = useState<string[]>([]);

    useEffect(() => {
        if (!fecha) return;
        setData(null);
        setError(false);
        setCargando(true);
        fetch(route('finanzas.balance.cambios-cierre', fecha), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => { if (!r.ok) throw new Error(); return r.json(); })
            .then((d: Analisis) => {
                setData(d);
                // Abre de entrada la línea que más movió el patrimonio.
                setAbiertas(d.categorias.length ? [d.categorias[0].categoria] : []);
            })
            .catch(() => setError(true))
            .finally(() => setCargando(false));
    }, [fecha]);

    const toggle = (cat: string) =>
        setAbiertas(a => (a.includes(cat) ? a.filter(c => c !== cat) : [...a, cat]));

    return (
        <Modal
            isOpen={fecha !== null}
            onClose={onClose}
            size="4xl"
            title={fecha ? `¿Qué cambió después del cierre? — ${fechaLarga(fecha)}` : ''}
        >
            {cargando && (
                <p className="py-10 text-center text-sm" style={{ color: 'var(--color-text-muted)' }}>Revisando el día…</p>
            )}

            {error && (
                <Callout variant="danger">No se pudo revisar este día. Intenta de nuevo.</Callout>
            )}

            {data && (
                <div className="space-y-4">
                    <Callout variant="info" title="Este día no se modifica">
                        Es la foto de cómo estaba el negocio al cerrar
                        {data.foto_tomada ? ` (${new Date(data.foto_tomada).toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' })})` : ''}.
                        {' '}Lo que se registró o corrigió después con fecha de este día o anterior se muestra acá, y en el
                        balance siguiente aparece separado como <strong>cambios en días ya cerrados</strong>.
                    </Callout>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Resumen label="Patrimonio al cerrar" valor={money(data.patrimonio_guardado)} />
                        <Resumen label="Con los datos de hoy" valor={money(data.patrimonio_actual)} />
                        <Resumen label="Cambió después del cierre" valor={signed(data.diferencia_patrimonio)}
                            color={colorDe(data.diferencia_patrimonio)} destacado />
                    </div>

                    {data.categorias.length === 0 ? (
                        <p className="py-6 text-center text-sm" style={{ color: 'var(--color-text-muted)' }}>
                            Nada cambió: este día sigue igual que cuando se cerró.
                        </p>
                    ) : (
                        <div className="rounded-xl overflow-hidden border" style={{ borderColor: 'var(--color-border)' }}>
                            <div className="grid grid-cols-12 gap-2 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider"
                                style={{ backgroundColor: 'var(--color-bg)', color: 'var(--color-text-muted)' }}>
                                <span className="col-span-5">Línea del balance</span>
                                <span className="col-span-2 text-right">Al cerrar</span>
                                <span className="col-span-2 text-right">Hoy</span>
                                <span className="col-span-3 text-right">Efecto en el patrimonio</span>
                            </div>

                            {data.categorias.map(c => {
                                const abierta = abiertas.includes(c.categoria);
                                const lineas = data.lineas.filter(l => l.categoria === c.categoria);
                                return (
                                    <div key={c.categoria} className="border-t" style={{ borderColor: 'var(--color-border)' }}>
                                        <button onClick={() => toggle(c.categoria)}
                                            className="w-full grid grid-cols-12 gap-2 px-4 py-2.5 text-sm text-left items-center hover:bg-black/[0.02]">
                                            <span className="col-span-5 flex items-center gap-1.5 font-medium" style={{ color: 'var(--color-text)' }}>
                                                <ChevronDown size={14} className={`transition-transform ${abierta ? '' : '-rotate-90'}`} />
                                                {CATEGORIA_NOMBRE[c.categoria] ?? c.categoria}
                                                <span className="text-[10px] font-normal uppercase" style={{ color: 'var(--color-text-muted)' }}>
                                                    {c.seccion === 'favor' ? 'a favor' : 'en contra'}
                                                </span>
                                            </span>
                                            <span className="col-span-2 text-right tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{money(c.guardado)}</span>
                                            <span className="col-span-2 text-right tabular-nums" style={{ color: 'var(--color-text)' }}>{money(c.actual)}</span>
                                            <span className="col-span-3 text-right font-bold tabular-nums" style={{ color: colorDe(c.efecto) }}>{signed(c.efecto)}</span>
                                        </button>

                                        {abierta && (
                                            <div className="px-4 pb-4 pt-1 space-y-3" style={{ backgroundColor: 'color-mix(in srgb, var(--color-bg) 60%, transparent)' }}>
                                                {lineas.length > 1 && (
                                                    <table className="w-full text-xs">
                                                        <tbody>
                                                            {lineas.map(l => (
                                                                <tr key={l.descripcion} className="border-b last:border-0" style={{ borderColor: 'var(--color-border)' }}>
                                                                    <td className="py-1.5" style={{ color: 'var(--color-text)' }}>{l.descripcion}</td>
                                                                    <td className="py-1.5 text-right tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{money(l.guardado)}</td>
                                                                    <td className="py-1.5 text-right tabular-nums">→ {money(l.actual)}</td>
                                                                    <td className="py-1.5 text-right tabular-nums font-semibold">{signed(l.diferencia)}</td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                )}

                                                {c.sin_documento ? (
                                                    <Callout variant="neutral" title="Sin documento posterior">
                                                        Ningún registro hecho después del cierre explica este cambio: corresponde a una
                                                        corrección del cálculo del sistema (un error que ya se arregló). Los datos
                                                        del negocio no se modificaron.
                                                    </Callout>
                                                ) : (
                                                    <div>
                                                        <p className="text-[11px] font-semibold uppercase tracking-wider mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                                            Documentos de este día o anteriores tocados después del cierre
                                                        </p>
                                                        <ul className="space-y-1.5">
                                                            {c.documentos.map((d, i) => (
                                                                <li key={i} className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-xs">
                                                                    <FileText size={12} className="self-center shrink-0" style={{ color: 'var(--color-text-muted)' }} />
                                                                    <span className="font-semibold" style={{ color: 'var(--color-text)' }}>{d.tipo}</span>
                                                                    {d.documento && <span className="font-mono">{d.documento}</span>}
                                                                    {d.fecha_documento && <span style={{ color: 'var(--color-text-muted)' }}>del {fechaCorta(d.fecha_documento)}</span>}
                                                                    {d.monto !== null && <span className="tabular-nums">{money(d.monto)}</span>}
                                                                    <span className="ml-auto" style={{ color: 'var(--color-text-muted)' }}>
                                                                        {d.cuando ? `el ${new Date(d.cuando.replace(' ', 'T')).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })}` : ''}
                                                                        {d.usuario ? ` · ${d.usuario}` : ''}
                                                                    </span>
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {Object.keys(data.metricas).length > 0 && (
                        <div className="rounded-xl border px-4 py-3 text-sm" style={{ borderColor: 'var(--color-border)' }}>
                            <p className="text-[11px] font-semibold uppercase tracking-wider mb-1.5" style={{ color: 'var(--color-text-muted)' }}>
                                Resultados del día que también cambiaron
                            </p>
                            {Object.entries(data.metricas).map(([k, m]) => (
                                <div key={k} className="flex justify-between gap-3 py-0.5 tabular-nums">
                                    <span>{METRICA_NOMBRE[k] ?? k}</span>
                                    <span>{money(m.guardado)} → {money(m.actual)} <strong>({signed(m.diferencia)})</strong></span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}

function Resumen({ label, valor, color, destacado }: { label: string; valor: string; color?: string; destacado?: boolean }) {
    return (
        <div className="rounded-xl px-4 py-3"
            style={{
                border: `1px solid ${destacado ? 'var(--color-primary)' : 'var(--color-border)'}`,
                backgroundColor: 'var(--color-surface)',
            }}>
            <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className="text-lg font-bold tabular-nums" style={{ color: color ?? 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}
