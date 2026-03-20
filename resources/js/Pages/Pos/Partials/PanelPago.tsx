import React from 'react';
import { Plus, Trash2, CreditCard, Banknote, Wallet } from 'lucide-react';
import type { Cuenta, MetodoPago } from '@/types';

export interface LineaPago {
    key:                    string;
    metodo_pago_id:         number;
    cuenta_metodo_pago_id:  number | null;
    monto:                  number;
    referencia:             string;
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

function MetodoIcon({ tipo }: { tipo: string }) {
    const size = 14;
    if (tipo === 'efectivo') return <Banknote size={size} />;
    if (['tarjeta_credito', 'tarjeta_debito'].includes(tipo)) return <CreditCard size={size} />;
    return <Wallet size={size} />;
}

export default function PanelPago({ pagos, metodosPago, total, onChange }: Props) {
    const totalPagado  = pagos.reduce((s, p) => s + p.monto, 0);
    const pendiente    = Math.max(0, total - totalPagado);
    const vuelto       = pagos.find(p => p.es_efectivo)
        ? Math.max(0, totalPagado - total)
        : 0;

    function addPago() {
        const metodo = metodosPago[0];
        if (!metodo) return;
        const nuevoPago: LineaPago = {
            key:                   uid(),
            metodo_pago_id:        metodo.id,
            cuenta_metodo_pago_id: null,
            monto:                 pendiente > 0 ? parseFloat(pendiente.toFixed(2)) : 0,
            referencia:            '',
            es_efectivo:           metodo.tipo === 'efectivo',
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
        const metodo = metodosPago.find(m => m.id === metodoId);
        updatePago(key, {
            metodo_pago_id:        metodoId,
            es_efectivo:           metodo?.tipo === 'efectivo',
            cuenta_metodo_pago_id: null,
        });
    }

    return (
        <div className="flex flex-col gap-2">
            {pagos.map(pago => {
                const metodo   = metodosPago.find(m => m.id === pago.metodo_pago_id);
                const cuentas  = metodo?.cuentas ?? [];
                const necesitaRef = ['yape', 'plin', 'transferencia'].includes(metodo?.tipo ?? '');

                return (
                    <div
                        key={pago.key}
                        className="rounded-xl p-3 space-y-2"
                        style={{
                            backgroundColor: 'var(--color-surface)',
                            border: '1px solid var(--color-border)',
                            boxShadow: '0 1px 2px rgba(0,0,0,0.04)',
                        }}
                    >
                        <div className="flex gap-2 items-center">
                            <div className="flex items-center gap-2 flex-1 min-w-0">
                                <span className="flex-shrink-0" style={{ color: 'var(--color-primary)' }}>
                                    <MetodoIcon tipo={metodo?.tipo ?? ''} />
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
                                    min="0"
                                    step="0.01"
                                    value={pago.monto || ''}
                                    onChange={e => updatePago(pago.key, { monto: parseFloat(e.target.value) || 0 })}
                                    placeholder="0.00"
                                    className="w-full pl-7 pr-2 py-1.5 text-sm border rounded-lg focus:outline-none focus:ring-2 font-semibold"
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

                        {cuentas.length > 0 && (
                            <select
                                value={pago.cuenta_metodo_pago_id ?? ''}
                                onChange={e => updatePago(pago.key, { cuenta_metodo_pago_id: e.target.value ? Number(e.target.value) : null })}
                                className="w-full text-sm border rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: 'var(--color-bg)',
                                    color: 'var(--color-text)',
                                    '--tw-ring-color': 'color-mix(in srgb, var(--color-primary) 40%, transparent)',
                                } as React.CSSProperties}
                            >
                                <option value="">— Cuenta (opcional)</option>
                                {cuentas.map(c => (
                                    <option key={c.id} value={c.id}>{c.nombre}</option>
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

            {/* Resumen de pagos */}
            {pagos.length > 0 && (
                <div
                    className="rounded-xl p-3 space-y-1.5"
                    style={{
                        backgroundColor: 'var(--color-bg)',
                        border: '1px solid var(--color-border)',
                    }}
                >
                    <div className="flex justify-between text-xs">
                        <span style={{ color: 'var(--color-text-muted)' }}>Total a pagar</span>
                        <span className="font-semibold" style={{ color: 'var(--color-text)' }}>S/ {total.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between text-xs">
                        <span style={{ color: 'var(--color-text-muted)' }}>Pagado</span>
                        <span className="font-bold" style={{ color: totalPagado >= total ? 'var(--color-success)' : 'var(--color-danger)' }}>
                            S/ {totalPagado.toFixed(2)}
                        </span>
                    </div>
                    {pendiente > 0.009 && (
                        <div className="flex justify-between text-xs font-bold" style={{ color: 'var(--color-danger)' }}>
                            <span>Pendiente</span>
                            <span>S/ {pendiente.toFixed(2)}</span>
                        </div>
                    )}
                    {vuelto > 0.009 && (
                        <div className="flex justify-between text-xs font-bold" style={{ color: 'var(--color-success)' }}>
                            <span>Vuelto</span>
                            <span>S/ {vuelto.toFixed(2)}</span>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
