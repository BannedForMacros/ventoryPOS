import React from 'react';
import { Plus, Trash2 } from 'lucide-react';
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
                        className="rounded-lg p-3 flex flex-col gap-2"
                        style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}
                    >
                        <div className="flex gap-2 items-center">
                            <select
                                value={pago.metodo_pago_id}
                                onChange={e => handleMetodoChange(pago.key, Number(e.target.value))}
                                className="flex-1 text-sm border rounded px-2 py-1.5"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                            >
                                {metodosPago.map(m => (
                                    <option key={m.id} value={m.id}>{m.nombre}</option>
                                ))}
                            </select>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={pago.monto || ''}
                                onChange={e => updatePago(pago.key, { monto: parseFloat(e.target.value) || 0 })}
                                placeholder="Monto"
                                className="w-28 text-sm border rounded px-2 py-1.5"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                            />
                            <button
                                onClick={() => removePago(pago.key)}
                                className="p-1 rounded hover:text-red-500 transition-colors"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                <Trash2 size={14} />
                            </button>
                        </div>

                        {cuentas.length > 0 && (
                            <select
                                value={pago.cuenta_metodo_pago_id ?? ''}
                                onChange={e => updatePago(pago.key, { cuenta_metodo_pago_id: e.target.value ? Number(e.target.value) : null })}
                                className="text-sm border rounded px-2 py-1.5"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
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
                                className="text-sm border rounded px-2 py-1.5"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                            />
                        )}
                    </div>
                );
            })}

            <button
                onClick={addPago}
                className="flex items-center gap-1 text-sm"
                style={{ color: 'var(--color-primary)' }}
            >
                <Plus size={14} /> Agregar método de pago
            </button>

            {/* Resumen */}
            <div
                className="rounded-lg p-3 mt-1 space-y-1"
                style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
            >
                <div className="flex justify-between text-sm">
                    <span style={{ color: 'var(--color-text-muted)' }}>Total a pagar</span>
                    <span className="font-semibold" style={{ color: 'var(--color-text)' }}>S/ {total.toFixed(2)}</span>
                </div>
                <div className="flex justify-between text-sm">
                    <span style={{ color: 'var(--color-text-muted)' }}>Total pagado</span>
                    <span className="font-semibold" style={{ color: totalPagado >= total ? 'var(--color-success)' : 'var(--color-danger)' }}>
                        S/ {totalPagado.toFixed(2)}
                    </span>
                </div>
                {pendiente > 0.009 && (
                    <div className="flex justify-between text-sm">
                        <span style={{ color: 'var(--color-danger)' }}>Pendiente</span>
                        <span className="font-bold" style={{ color: 'var(--color-danger)' }}>S/ {pendiente.toFixed(2)}</span>
                    </div>
                )}
                {vuelto > 0.009 && (
                    <div className="flex justify-between text-sm">
                        <span style={{ color: 'var(--color-success)' }}>Vuelto</span>
                        <span className="font-bold" style={{ color: 'var(--color-success)' }}>S/ {vuelto.toFixed(2)}</span>
                    </div>
                )}
            </div>
        </div>
    );
}
