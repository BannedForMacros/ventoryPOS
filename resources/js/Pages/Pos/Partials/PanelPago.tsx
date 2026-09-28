import React, { useEffect, useRef, useState } from 'react';
import { ArrowLeftRight, Banknote, Check, CreditCard, Gift, PiggyBank, Plus, Smartphone, Split, Wallet, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { Cuenta, MetodoPago } from '@/types';

export interface LineaPago {
    key:                    string;
    metodo_pago_id:         number;
    cuenta_metodo_pago_id:  number | null;
    monto:                  number;
    referencia:             string;
    // Indica si el método de pago elegido admite vuelto/sobrepago. Se deriva
    // del flag `admite_vuelto` del método (configurable por el admin).
    // Mantenemos también `es_efectivo` por compatibilidad con código antiguo
    // del POS, pero el cálculo de vuelto usa `admite_vuelto`.
    admite_vuelto:          boolean;
    es_efectivo:            boolean;
}

interface MetodoPagoConCuentas extends MetodoPago {
    cuentas?: Cuenta[];
}

interface Props {
    pagos:          LineaPago[];
    metodosPago:    MetodoPagoConCuentas[];
    total:          number;
    anticipoMonto?: number;
    // En crédito el pago inicial es opcional: se puede quitar el único pago.
    esCredito?:     boolean;
    onChange:       (pagos: LineaPago[]) => void;
}

function uid() { return Math.random().toString(36).slice(2); }
const r2 = (n: number) => Math.round(n * 100) / 100;

/** Cuentas (pivote) vinculadas a un método. */
function cuentasDe(metodo?: MetodoPagoConCuentas): Cuenta[] {
    return (metodo?.cuentas ?? []).filter(c => c.pivot?.id);
}

/** Cuenta por defecto: si el método tiene EXACTAMENTE 1 cuenta, se autoselecciona
 *  (su id de pivote); si tiene 2+ queda null → el usuario debe elegir. */
function cuentaDefaultDe(metodo?: MetodoPagoConCuentas): number | null {
    const cts = cuentasDe(metodo);
    return cts.length === 1 ? cts[0].pivot!.id : null;
}

/** ¿Alguna línea usa un método CON cuentas pero sin cuenta elegida? (bloquea cobro) */
export function faltanCuentas(pagos: LineaPago[], metodosPago: MetodoPagoConCuentas[]): boolean {
    return pagos.some(p => {
        const cts = cuentasDe(metodosPago.find(m => m.id === p.metodo_pago_id));
        return cts.length > 0 && !p.cuenta_metodo_pago_id;
    });
}

// Icono y color por TIPO de método (catálogo global tipos_metodo_pago): así
// todas las empresas ven lo mismo sin configurar nada. El icono viene de la BD
// (tipos_metodo_pago.icono); el color es la marca reconocible de cada uno.
const ICONOS: Record<string, LucideIcon> = { Banknote, CreditCard, ArrowLeftRight, Smartphone, Wallet };
const COLORES: Record<string, string> = {
    efectivo:        '#059669',
    tarjeta_debito:  '#2563eb',
    tarjeta_credito: '#2563eb',
    transferencia:   '#475569',
    yape:            '#742284',
    plin:            '#0891b2',
};

function estiloDe(metodo?: MetodoPagoConCuentas): { Icono: LucideIcon; color: string } {
    const tipo = metodo?.tipo;
    return {
        Icono: (tipo?.icono && ICONOS[tipo.icono]) || Wallet,
        color: (tipo?.slug && COLORES[tipo.slug]) || 'var(--color-primary)',
    };
}

function lineaDe(metodo: MetodoPagoConCuentas, monto: number): LineaPago {
    return {
        key:                   uid(),
        metodo_pago_id:        metodo.id,
        cuenta_metodo_pago_id: cuentaDefaultDe(metodo),
        monto:                 r2(Math.max(0, monto)),
        referencia:            '',
        admite_vuelto:         !!metodo.admite_vuelto,
        es_efectivo:           metodo.tipo?.slug === 'efectivo',
    };
}

const inputStyle = {
    borderColor: 'var(--color-border)',
    backgroundColor: 'var(--color-bg)',
    color: 'var(--color-text)',
    '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
} as React.CSSProperties;

export default function PanelPago({ pagos, metodosPago, total, anticipoMonto = 0, esCredito = false, onChange }: Props) {
    const porCobrar = r2(Math.max(0, total - anticipoMonto));

    // Pago dividido: una tarjeta por método, cada una con su monto. Al agregar
    // un método recibe LO QUE FALTA; abajo se ve siempre cuánto falta asignar.
    const [modoDividir, setModoDividir] = useState(pagos.length > 1);
    const dividido = modoDividir || pagos.length > 1;
    const [enfocar, setEnfocar] = useState<string | null>(null);
    const refs = useRef<Record<string, HTMLInputElement | null>>({});

    // Tras cobrar los pagos vuelven a [] → la siguiente venta arranca simple.
    useEffect(() => { if (pagos.length === 0) setModoDividir(false); }, [pagos.length]);

    useEffect(() => {
        if (enfocar && refs.current[enfocar]) {
            refs.current[enfocar]!.focus();
            refs.current[enfocar]!.select();
            setEnfocar(null);
        }
    }, [enfocar, pagos]);

    const cubreAnticipo = anticipoMonto > 0.009 && porCobrar <= 0.009;
    const sinCobro = total <= 0.009;

    if (sinCobro && !esCredito) {
        return (
            <div
                className="flex items-start gap-2 rounded-lg px-2.5 py-2 text-xs"
                style={{
                    backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, var(--color-surface))',
                    border: '1px solid color-mix(in srgb, var(--color-success) 35%, transparent)',
                    color: 'var(--color-text)',
                }}
            >
                <Gift size={14} className="flex-shrink-0 mt-px" style={{ color: 'var(--color-success)' }} />
                <span>
                    <span className="font-semibold">Sin cobro:</span> el descuento cubre el 100 % de la venta.
                    No se registra ningún pago.
                </span>
            </div>
        );
    }

    const asignado = r2(pagos.reduce((s, p) => s + p.monto, 0));
    const falta    = r2(porCobrar - asignado);

    function cambiarMetodo(key: string | null, metodo: MetodoPagoConCuentas) {
        if (key === null) { onChange([lineaDe(metodo, esCredito ? 0 : porCobrar)]); return; }
        onChange(pagos.map(p => p.key === key ? {
            ...p,
            metodo_pago_id:        metodo.id,
            admite_vuelto:         !!metodo.admite_vuelto,
            es_efectivo:           metodo.tipo?.slug === 'efectivo',
            cuenta_metodo_pago_id: cuentaDefaultDe(metodo),
            referencia:            '',
        } : p));
    }

    function actualizar(key: string, patch: Partial<LineaPago>) {
        onChange(pagos.map(p => p.key === key ? { ...p, ...patch } : p));
    }

    function empezarDivision() {
        setModoDividir(true);
        // El primer método queda en blanco para escribir cuánto paga con él;
        // el siguiente método que se agregue recibe el resto.
        if (pagos[0]) {
            onChange([{ ...pagos[0], monto: 0 }]);
            setEnfocar(pagos[0].key);
        }
    }

    function volverAUnMetodo() {
        setModoDividir(false);
        if (pagos[0]) onChange([{ ...pagos[0], monto: esCredito ? pagos[0].monto : porCobrar }]);
    }

    function agregarMetodo(metodo: MetodoPagoConCuentas) {
        const nueva = lineaDe(metodo, Math.max(0, falta));
        onChange([...pagos, nueva]);
        setEnfocar(nueva.key);
    }

    function quitar(key: string) {
        onChange(pagos.filter(p => p.key !== key));
    }

    const usados      = new Set(pagos.map(p => p.metodo_pago_id));
    const disponibles = metodosPago.filter(m => !usados.has(m.id));

    return (
        <div
            className="flex flex-col gap-2 rounded-xl p-2.5"
            style={{
                backgroundColor: 'var(--vp-sky-light)',
                border: '1px solid color-mix(in srgb, var(--vp-sky) 18%, transparent)',
            }}
        >
            <div className="flex items-center justify-between gap-2">
                <h3 className="flex items-center gap-2 text-[13px] font-bold" style={{ color: 'var(--vp-navy)' }}>
                    <span className="h-3.5 w-1 rounded-full flex-shrink-0" style={{ background: 'linear-gradient(180deg, var(--vp-sky), var(--vp-mint))' }} />
                    {esCredito ? 'Pago inicial (opcional)' : dividido ? 'Pago con varios métodos' : '¿Cómo paga?'}
                </h3>
                {!cubreAnticipo && pagos.length > 0 && metodosPago.length > 1 && (
                    <button
                        type="button"
                        onClick={dividido ? volverAUnMetodo : empezarDivision}
                        className="flex items-center gap-1 text-[11px] font-semibold px-1.5 py-0.5 rounded-md hover:opacity-80"
                        style={{ color: 'var(--color-primary)' }}
                    >
                        {dividido ? <><X size={12} /> Volver a un solo método</> : <><Split size={12} /> Pagar con 2 o más métodos</>}
                    </button>
                )}
            </div>

            {anticipoMonto > 0.009 && (
                <div
                    className="flex gap-2 items-center rounded-lg px-2.5 py-1.5"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning) 10%, var(--color-surface))', border: '1px solid var(--color-border)' }}
                >
                    <PiggyBank size={14} style={{ color: 'var(--color-warning)' }} className="flex-shrink-0" />
                    <p className="flex-1 text-xs font-semibold" style={{ color: 'var(--color-text)' }}>
                        {cubreAnticipo ? 'El anticipo cubre toda la venta' : 'Anticipo de cliente'}
                    </p>
                    <span className="text-xs font-bold" style={{ color: 'var(--color-warning)' }}>S/ {anticipoMonto.toFixed(2)}</span>
                </div>
            )}

            {/* ── Un solo método (lo normal) ─────────────────────────────── */}
            {!cubreAnticipo && !dividido && (() => {
                const pago = pagos[0] ?? null;
                return (
                    <>
                        <FilaMetodos
                            metodos={metodosPago}
                            elegido={pago?.metodo_pago_id ?? null}
                            onElegir={m => cambiarMetodo(pago?.key ?? null, m)}
                        />
                        {pago && (
                            <DetallePago
                                pago={pago}
                                metodo={metodosPago.find(m => m.id === pago.metodo_pago_id)}
                                etiqueta={pago.admite_vuelto ? 'Recibido' : 'Monto'}
                                inputRef={el => { refs.current[pago.key] = el; }}
                                onCambio={patch => actualizar(pago.key, patch)}
                                onQuitar={esCredito ? () => onChange([]) : undefined}
                            />
                        )}
                    </>
                );
            })()}

            {/* ── Varios métodos: una tarjeta por pago ───────────────────── */}
            {!cubreAnticipo && dividido && (
                <>
                    {pagos.map((pago, i) => {
                        const metodo = metodosPago.find(m => m.id === pago.metodo_pago_id);
                        return (
                            <div
                                key={pago.key}
                                className="rounded-lg px-2.5 py-2"
                                style={{ backgroundColor: '#fff', boxShadow: '0 1px 3px rgba(15,76,129,0.10)' }}
                            >
                                <DetallePago
                                    pago={pago}
                                    metodo={metodo}
                                    etiqueta={metodo?.nombre ?? `Pago ${i + 1}`}
                                    conIcono
                                    inputRef={el => { refs.current[pago.key] = el; }}
                                    placeholder={i === 0 && pago.monto === 0 ? '¿Cuánto?' : '0.00'}
                                    onCambio={patch => actualizar(pago.key, patch)}
                                    onQuitar={pagos.length > 1 ? () => quitar(pago.key) : undefined}
                                />
                            </div>
                        );
                    })}

                    {disponibles.length > 0 && (
                        <div className="flex flex-wrap items-center gap-1">
                            <span className="text-[11px] font-semibold mr-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                <Plus size={11} className="inline -mt-px" /> Agregar:
                            </span>
                            {disponibles.map(m => {
                                const { Icono, color } = estiloDe(m);
                                return (
                                    <button
                                        key={m.id}
                                        type="button"
                                        onClick={() => agregarMetodo(m)}
                                        className="flex items-center gap-1 h-7 px-2 rounded-md border text-[11px] font-semibold hover:opacity-80"
                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                                    >
                                        <Icono size={12} style={{ color }} />
                                        {m.nombre}
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {!esCredito && (
                        <div
                            className="flex items-center justify-between text-xs font-semibold rounded-md px-2.5 py-1.5"
                            style={falta > 0.009
                                ? { backgroundColor: 'color-mix(in srgb, var(--color-warning) 12%, var(--color-surface))', color: 'color-mix(in srgb, var(--color-warning) 70%, #000)' }
                                : { backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, var(--color-surface))', color: 'var(--color-success)' }}
                        >
                            {falta > 0.009 ? (
                                <><span>Falta asignar</span><span>S/ {falta.toFixed(2)}</span></>
                            ) : (
                                <><span className="flex items-center gap-1"><Check size={13} /> Pagos completos</span><span>S/ {asignado.toFixed(2)}</span></>
                            )}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

/** Botones de método (un solo método): toque = elegir. */
function FilaMetodos({ metodos, elegido, onElegir }: {
    metodos:  MetodoPagoConCuentas[];
    elegido:  number | null;
    onElegir: (m: MetodoPagoConCuentas) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1" role="radiogroup" aria-label="Método de pago">
            {metodos.map((m, i) => {
                const { Icono, color } = estiloDe(m);
                const activo = m.id === elegido;
                return (
                    <button
                        key={m.id}
                        type="button"
                        role="radio"
                        aria-checked={activo}
                        data-metodo-pago={i === 0 ? '' : undefined}
                        onClick={() => onElegir(m)}
                        className="flex items-center gap-1 h-8 px-2 rounded-lg text-xs font-semibold transition-colors active:scale-95"
                        style={{
                            border: `${activo ? 2 : 1}px solid ${activo ? color : 'transparent'}`,
                            backgroundColor: activo ? `color-mix(in srgb, ${color} 14%, #fff)` : 'var(--color-surface)',
                            color: activo ? color : 'var(--color-text)',
                            boxShadow: activo ? 'none' : '0 1px 2px rgba(15,23,42,0.06)',
                        }}
                    >
                        {activo ? <Check size={14} strokeWidth={3} /> : <Icono size={14} style={{ color }} />}
                        {m.nombre}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Monto del pago y, SOLO si hace falta, la cuenta destino (cuando el método
 * tiene varias) y el N.º de operación. Etiquetas a la izquierda alineadas, así
 * se ve de un vistazo dónde se escribe cada cosa.
 */
function DetallePago({ pago, metodo, etiqueta, conIcono = false, placeholder = '0.00', inputRef, onCambio, onQuitar }: {
    pago:         LineaPago;
    metodo?:      MetodoPagoConCuentas;
    etiqueta:     string;
    conIcono?:    boolean;
    placeholder?: string;
    inputRef?:    (el: HTMLInputElement | null) => void;
    onCambio:     (patch: Partial<LineaPago>) => void;
    onQuitar?:    () => void;
}) {
    const { Icono, color } = estiloDe(metodo);
    const cuentas     = cuentasDe(metodo);
    const necesitaRef = !!metodo?.tipo?.requiere_referencia;
    const faltaCuenta = cuentas.length > 1 && !pago.cuenta_metodo_pago_id;
    const labelCls    = 'flex items-center gap-1.5 text-xs font-semibold w-24 flex-shrink-0 truncate';

    return (
        <div className="space-y-1.5">
            <div className="flex items-center gap-2">
                <span className={labelCls} style={{ color: conIcono ? 'var(--color-text)' : 'var(--color-text-muted)' }}>
                    {conIcono && <Icono size={14} style={{ color }} className="flex-shrink-0" />}
                    <span className="truncate">{etiqueta}</span>
                </span>
                <div className="relative flex-1">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                    <input
                        ref={inputRef}
                        type="number"
                        inputMode="decimal"
                        min="0"
                        step="0.01"
                        data-pago-monto={pago.key}
                        value={pago.monto || ''}
                        onChange={e => onCambio({ monto: parseFloat(e.target.value) || 0 })}
                        onFocus={e => e.target.select()}
                        placeholder={placeholder}
                        className="w-full h-10 pl-8 pr-3 text-lg text-right tabular-nums border rounded-lg focus:outline-none focus:ring-2 font-bold"
                        style={{ ...inputStyle, backgroundColor: '#fff', color: 'var(--vp-navy)' }}
                    />
                </div>
                {onQuitar && (
                    <button
                        type="button"
                        onClick={onQuitar}
                        title="Quitar este pago"
                        className="p-1 rounded-md hover:bg-red-50 flex-shrink-0"
                        style={{ color: 'var(--color-text-muted)' }}
                    >
                        <X size={14} />
                    </button>
                )}
            </div>

            {cuentas.length > 1 && (
                <div className="flex items-center gap-2">
                    <span
                        className={labelCls}
                        style={{ color: faltaCuenta ? 'color-mix(in srgb, var(--color-warning) 75%, #000)' : 'var(--color-text-muted)' }}
                    >
                        {faltaCuenta ? 'Elige cuenta' : 'Cuenta'}
                    </span>
                    <div className="flex flex-wrap gap-1 flex-1">
                        {cuentas.map((c, i) => {
                            const activa = pago.cuenta_metodo_pago_id === c.pivot!.id;
                            return (
                                <button
                                    key={c.pivot!.id}
                                    type="button"
                                    data-pago-cuenta={i === 0 ? pago.key : undefined}
                                    onClick={() => onCambio({ cuenta_metodo_pago_id: c.pivot!.id })}
                                    className="flex items-center gap-1 h-7 px-2 text-[11px] font-semibold rounded-md transition-colors focus:outline-none focus-visible:ring-2 focus:ring-2 focus:ring-[var(--color-warning)]"
                                    style={{
                                        border: `1px solid ${activa ? 'var(--color-primary)' : 'var(--color-border)'}`,
                                        backgroundColor: activa ? 'color-mix(in srgb, var(--color-primary) 10%, var(--color-surface))' : 'var(--color-surface)',
                                        color: activa ? 'var(--color-primary)' : 'var(--color-text)',
                                    }}
                                >
                                    {activa && <Check size={11} />}
                                    {c.nombre}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {necesitaRef && (
                <div className="flex items-center gap-2">
                    <span className={labelCls} style={{ color: 'var(--color-text-muted)' }}>N.º operación</span>
                    <input
                        type="text"
                        value={pago.referencia}
                        onChange={e => onCambio({ referencia: e.target.value })}
                        placeholder="Opcional"
                        className="flex-1 h-7 text-xs border rounded-md px-2 focus:outline-none focus:ring-2"
                        style={{ ...inputStyle, backgroundColor: '#fff' }}
                    />
                </div>
            )}
        </div>
    );
}
