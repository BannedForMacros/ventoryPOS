import React from 'react';
import { Plus, Trash2, CreditCard, Banknote, Wallet } from 'lucide-react';
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
    onChange:       (pagos: LineaPago[]) => void;
}

function uid() { return Math.random().toString(36).slice(2); }

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

function MetodoIcon({ tipo }: { tipo: string }) {
    const size = 14;
    if (tipo === 'efectivo') return <Banknote size={size} />;
    if (['tarjeta_credito', 'tarjeta_debito'].includes(tipo)) return <CreditCard size={size} />;
    return <Wallet size={size} />;
}

export default function PanelPago({ pagos, metodosPago, total, onChange }: Props) {
    const totalPagado  = pagos.reduce((s, p) => s + p.monto, 0);
    // Lo pendiente por cubrir se usa como monto sugerido al agregar una línea.
    const pendiente    = Math.max(0, total - totalPagado);

    function addPago() {
        const metodo = metodosPago[0];
        if (!metodo) return;
        const admiteVuelto = !!metodo.admite_vuelto;
        const tipoSlug     = metodo.tipo?.slug ?? '';
        const nuevoPago: LineaPago = {
            key:                   uid(),
            metodo_pago_id:        metodo.id,
            cuenta_metodo_pago_id: cuentaDefaultDe(metodo),
            monto:                 pendiente > 0 ? parseFloat(pendiente.toFixed(2)) : 0,
            referencia:            '',
            admite_vuelto:         admiteVuelto,
            es_efectivo:           tipoSlug === 'efectivo',
        };
        onChange([...pagos, nuevoPago]);
    }

    function updatePago(key: string, patch: Partial<LineaPago>) {
        onChange(pagos.map(p => p.key === key ? { ...p, ...patch } : p));
    }

    function removePago(key: string) {
        onChange(pagos.filter(p => p.key !== key));
    }

    function handleMetodoChange(key: string, metodoId: number) {
        const metodo   = metodosPago.find(m => m.id === metodoId);
        const tipoSlug = metodo?.tipo?.slug ?? '';
        updatePago(key, {
            metodo_pago_id:        metodoId,
            admite_vuelto:         !!metodo?.admite_vuelto,
            es_efectivo:           tipoSlug === 'efectivo',
            cuenta_metodo_pago_id: cuentaDefaultDe(metodo),
        });
    }

    return (
        <div className="flex flex-col gap-2">
            {pagos.map(pago => {
                const metodo      = metodosPago.find(m => m.id === pago.metodo_pago_id);
                const cuentas     = metodo?.cuentas ?? [];
                const tipoSlug    = metodo?.tipo?.slug ?? '';
                // `requiere_referencia` ahora viene del catálogo (en lugar de hardcodear
                // qué slugs lo necesitan). Si el admin marca el flag al crear el tipo,
                // el POS lo respeta sin tocar código.
                const necesitaRef = !!metodo?.tipo?.requiere_referencia;

                return (
                    <div
                        key={pago.key}
                        className="rounded-xl p-2 space-y-1.5"
                        style={{
                            backgroundColor: 'var(--color-surface)',
                            border: '1px solid var(--color-border)',
                            boxShadow: '0 1px 2px rgba(0,0,0,0.04)',
                        }}
                    >
                        <div className="flex gap-2 items-center">
                            <div className="flex items-center gap-2 flex-1 min-w-0">
                                <span className="flex-shrink-0" style={{ color: 'var(--color-primary)' }}>
                                    <MetodoIcon tipo={tipoSlug} />
                                </span>
                                <select
                                    value={pago.metodo_pago_id}
                                    onChange={e => handleMetodoChange(pago.key, Number(e.target.value))}
                                    className="flex-1 text-sm border rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 min-w-0"
                                    style={{
                                        borderColor: 'var(--color-border)',
                                        backgroundColor: 'var(--color-bg)',
                                        color: 'var(--color-text)',
                                        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                                    } as React.CSSProperties}
                                >
                                    {metodosPago.map(m => (
                                        <option key={m.id} value={m.id}>{m.nombre}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="relative w-28 flex-shrink-0">
                                <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                                <input
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={pago.monto || ''}
                                    onChange={e => updatePago(pago.key, { monto: parseFloat(e.target.value) || 0 })}
                                    placeholder="0.00"
                                    className="w-full pl-7 pr-2 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 font-semibold"
                                    style={{
                                        borderColor: 'var(--color-border)',
                                        backgroundColor: 'var(--color-bg)',
                                        color: 'var(--color-text)',
                                        '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                                    } as React.CSSProperties}
                                />
                            </div>
                            <button
                                onClick={() => removePago(pago.key)}
                                className="p-1.5 rounded-lg hover:bg-red-50 transition-colors group flex-shrink-0"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                <Trash2 size={14} className="group-hover:text-red-500 transition-colors" />
                            </button>
                        </div>

                        {cuentas.filter(c => c.pivot?.id).length > 0 && (
                            <select
                                value={pago.cuenta_metodo_pago_id ?? ''}
                                onChange={e => updatePago(pago.key, { cuenta_metodo_pago_id: e.target.value ? Number(e.target.value) : null })}
                                className="w-full text-sm border rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2"
                                style={{
                                    // Cuenta OBLIGATORIA: si el método tiene cuentas hay que elegir
                                    // una (con 1 sola se autoseleccionó). Borde rojo mientras falte.
                                    borderColor: pago.cuenta_metodo_pago_id ? 'var(--color-border)' : 'var(--color-danger)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                    '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                                } as React.CSSProperties}
                            >
                                <option value="">— Selecciona una cuenta —</option>
                                {/* value = id del PIVOTE cuenta_metodo_pago (lo que valida el
                                    backend), NO el id de la cuenta — antes coincidían de
                                    casualidad y al divergir los ids rompía la validación. */}
                                {cuentas.filter(c => c.pivot?.id).map(c => (
                                    <option key={c.pivot!.id} value={c.pivot!.id}>{c.nombre}</option>
                                ))}
                            </select>
                        )}

                        {necesitaRef && (
                            <input
                                type="text"
                                value={pago.referencia}
                                onChange={e => updatePago(pago.key, { referencia: e.target.value })}
                                placeholder="N° de operación / referencia"
                                className="w-full text-sm border rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                    '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                                } as React.CSSProperties}
                            />
                        )}
                    </div>
                );
            })}

            {/* El resumen (pagado/falta/vuelto) vive en el pie fijo del
                CarritoPanel, siempre visible — aquí solo las líneas de pago. */}
            <button
                onClick={addPago}
                className="flex items-center justify-center gap-1.5 text-sm font-medium py-2 px-3 rounded-xl transition-all hover:opacity-80 w-full"
                style={{
                    backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, transparent)',
                    color: 'var(--color-primary)',
                    border: '1px dashed color-mix(in srgb, var(--color-primary) 35%, transparent)',
                }}
            >
                <Plus size={14} /> Agregar pago
            </button>
        </div>
    );
}
