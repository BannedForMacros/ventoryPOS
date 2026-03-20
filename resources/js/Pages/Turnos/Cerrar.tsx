import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Clock, Receipt, ShoppingCart, TrendingDown, Wallet } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import type { MetodoPago, Turno } from '@/types';

const DENOMINACIONES_PEN = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];

interface FilaArqueo {
    denominacion: number;
    cantidad:     number;
}

interface FilaMetodo {
    metodo_pago_id:  number;
    monto_declarado: string;
}

interface CerrarForm {
    arqueo:             FilaArqueo[];
    arqueo_metodos:     FilaMetodo[];
    observacion_cierre: string;
}

interface Props {
    turno:            Turno;
    ventasPorMetodo:  Record<string, number>;
    totalVentas:      number;
    totalGastos:      number;
    montoEsperado:    number;
    metodosPago:      MetodoPago[];
}

export default function CerrarTurno({ turno, ventasPorMetodo, totalVentas, totalGastos, montoEsperado, metodosPago }: Props) {
    const caja = turno.caja!;

    const [form, setForm] = useState<CerrarForm>({
        arqueo: DENOMINACIONES_PEN.map(d => ({ denominacion: d, cantidad: 0 })),
        arqueo_metodos: metodosPago
            .filter(m => m.tipo !== 'efectivo')
            .map(m => ({ metodo_pago_id: m.id, monto_declarado: '' })),
        observacion_cierre: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const totalEfectivo = useMemo(() =>
        form.arqueo.reduce((sum, f) => sum + f.denominacion * f.cantidad, 0),
    [form.arqueo]);

    const diferencia = totalEfectivo - montoEsperado;

    const metodosFiltrados = metodosPago.filter(m => m.tipo !== 'efectivo');

    function setCantidad(denominacion: number, cantidad: number) {
        setForm(f => ({
            ...f,
            arqueo: f.arqueo.map(row =>
                row.denominacion === denominacion ? { ...row, cantidad } : row
            ),
        }));
    }

    function setMontoMetodo(metodoPagoId: number, monto: string) {
        setForm(f => ({
            ...f,
            arqueo_metodos: f.arqueo_metodos.map(row =>
                row.metodo_pago_id === metodoPagoId ? { ...row, monto_declarado: monto } : row
            ),
        }));
    }

    function submit() {
        setSaving(true);
        const payload = {
            arqueo: form.arqueo,
            arqueo_metodos: form.arqueo_metodos.map(m => ({
                metodo_pago_id:  m.metodo_pago_id,
                monto_declarado: parseFloat(m.monto_declarado) || 0,
            })),
            observacion_cierre: form.observacion_cierre,
        };

        router.post(route('turnos.cerrar', turno.id), payload as any, {
            onSuccess: () => setSaving(false),
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    return (
        <AppLayout title="Cerrar turno">
            <PageHeader
                title="Cerrar turno"
                subtitle={`Caja: ${caja.nombre} — Abierto: ${new Date(turno.fecha_apertura).toLocaleString('es-PE')}`}
                actions={
                    <Button variant="ghost" onClick={() => router.visit(route('turnos.index'))}>
                        <ArrowLeft size={15} className="mr-1" />Volver
                    </Button>
                }
            />

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* ── Columna izquierda — Resúmenes ── */}
                <div className="space-y-5">
                    {/* Info turno */}
                    <Section title="Información del turno">
                        <div className="grid grid-cols-2 gap-3">
                            <InfoCard
                                icon={<Clock size={16} style={{ color: 'var(--color-primary)' }} />}
                                label="Apertura"
                                valor={new Date(turno.fecha_apertura).toLocaleString('es-PE')}
                            />
                            <InfoCard
                                icon={<Wallet size={16} style={{ color: 'var(--color-success)' }} />}
                                label="Monto apertura"
                                valor={`S/ ${parseFloat(turno.monto_apertura).toFixed(2)}`}
                            />
                        </div>
                    </Section>

                    {/* Resumen ventas */}
                    <Section title="Resumen de ventas">
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-sm flex items-center gap-2" style={{ color: 'var(--color-text)' }}>
                                    <ShoppingCart size={14} /> Cantidad de ventas
                                </span>
                                <span className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>
                                    {(turno.ventas ?? []).length}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm" style={{ color: 'var(--color-text)' }}>Total ventas</span>
                                <span className="font-semibold text-sm" style={{ color: 'var(--color-primary)' }}>
                                    S/ {totalVentas.toFixed(2)}
                                </span>
                            </div>
                            {Object.entries(ventasPorMetodo).length > 0 && (
                                <div
                                    className="rounded-xl mt-2 p-3 space-y-1"
                                    style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
                                >
                                    <p className="text-xs font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                                        Desglose por método de pago
                                    </p>
                                    {Object.entries(ventasPorMetodo).map(([metodo, monto]) => (
                                        <div key={metodo} className="flex justify-between text-sm">
                                            <span style={{ color: 'var(--color-text)' }}>{metodo}</span>
                                            <span style={{ color: 'var(--color-text)' }}>S/ {monto.toFixed(2)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </Section>

                    {/* Resumen gastos */}
                    <Section title="Resumen de gastos">
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-sm flex items-center gap-2" style={{ color: 'var(--color-text)' }}>
                                    <TrendingDown size={14} /> Cantidad de gastos
                                </span>
                                <span className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>
                                    {(turno.gastos ?? []).length}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm" style={{ color: 'var(--color-text)' }}>Total gastos</span>
                                <span className="font-semibold text-sm" style={{ color: 'var(--color-danger)' }}>
                                    S/ {totalGastos.toFixed(2)}
                                </span>
                            </div>
                        </div>
                    </Section>

                    {/* Monto esperado */}
                    <div
                        className="rounded-xl px-4 py-3 flex items-center justify-between"
                        style={{ backgroundColor: 'rgba(59,130,246,0.06)', border: '1px solid rgba(59,130,246,0.2)' }}
                    >
                        <div>
                            <p className="text-sm font-medium" style={{ color: 'var(--color-primary)' }}>Monto esperado en efectivo</p>
                            <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                Apertura + ventas efectivo − gastos
                            </p>
                        </div>
                        <span className="font-bold text-lg" style={{ color: 'var(--color-primary)' }}>
                            S/ {montoEsperado.toFixed(2)}
                        </span>
                    </div>

                    {/* Caja chica */}
                    {caja.caja_chica_activa && (
                        <div
                            className="rounded-xl px-4 py-3 flex items-center justify-between"
                            style={{ backgroundColor: 'rgba(234,179,8,0.08)', border: '1px solid rgba(234,179,8,0.3)' }}
                        >
                            <div>
                                <p className="text-sm font-medium" style={{ color: '#b45309' }}>Caja chica asignada</p>
                                <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    No incluida en el arqueo
                                </p>
                            </div>
                            <span className="font-semibold" style={{ color: '#b45309' }}>
                                S/ {parseFloat(turno.monto_caja_chica).toFixed(2)}
                            </span>
                        </div>
                    )}
                </div>

                {/* ── Columna derecha — Formulario arqueo ── */}
                <div className="space-y-5">
                    {/* Arqueo efectivo */}
                    <Section title="Arqueo de caja — efectivo">
                        <div
                            className="rounded-xl overflow-hidden"
                            style={{ border: '1px solid var(--color-border)' }}
                        >
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ backgroundColor: 'var(--color-bg)' }}>
                                        <th className="text-left px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Denominación</th>
                                        <th className="text-center px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Cantidad</th>
                                        <th className="text-right px-4 py-2 font-medium" style={{ color: 'var(--color-text-muted)' }}>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                                    {form.arqueo.map(row => (
                                        <tr key={row.denominacion}>
                                            <td className="px-4 py-2 font-medium" style={{ color: 'var(--color-text)' }}>
                                                S/ {row.denominacion.toFixed(2)}
                                            </td>
                                            <td className="px-4 py-1.5 text-center">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={row.cantidad || ''}
                                                    onChange={e => setCantidad(row.denominacion, parseInt(e.target.value) || 0)}
                                                    className="w-20 text-center rounded-lg px-2 py-1 text-sm"
                                                    style={{
                                                        border:          '1px solid var(--color-border)',
                                                        backgroundColor: 'var(--color-surface)',
                                                        color:           'var(--color-text)',
                                                    }}
                                                />
                                            </td>
                                            <td className="px-4 py-2 text-right" style={{ color: 'var(--color-text)' }}>
                                                S/ {(row.denominacion * row.cantidad).toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr style={{ backgroundColor: 'var(--color-bg)', borderTop: '2px solid var(--color-border)' }}>
                                        <td colSpan={2} className="px-4 py-2 font-semibold" style={{ color: 'var(--color-text)' }}>
                                            Total efectivo
                                        </td>
                                        <td className="px-4 py-2 text-right font-semibold" style={{ color: 'var(--color-primary)' }}>
                                            S/ {totalEfectivo.toFixed(2)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        {errors.arqueo && (
                            <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.arqueo}</p>
                        )}
                    </Section>

                    {/* Métodos de pago no efectivo */}
                    {metodosFiltrados.length > 0 && (
                        <Section title="Otros métodos de pago">
                            <div className="space-y-2">
                                {form.arqueo_metodos.map(row => {
                                    const metodo = metodosPago.find(m => m.id === row.metodo_pago_id)!;
                                    return (
                                        <div key={row.metodo_pago_id} className="flex items-center gap-3">
                                            <span className="flex-1 text-sm" style={{ color: 'var(--color-text)' }}>
                                                {metodo.nombre}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <span className="text-sm" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={row.monto_declarado}
                                                    onChange={e => setMontoMetodo(row.metodo_pago_id, e.target.value)}
                                                    placeholder="0.00"
                                                    className="w-28 rounded-lg px-2 py-1 text-sm text-right"
                                                    style={{
                                                        border:          '1px solid var(--color-border)',
                                                        backgroundColor: 'var(--color-surface)',
                                                        color:           'var(--color-text)',
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </Section>
                    )}

                    {/* Diferencia */}
                    <div
                        className="rounded-xl overflow-hidden"
                        style={{ border: '1px solid var(--color-border)' }}
                    >
                        <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                            <FilaResumen label="Total declarado (arqueo)" valor={totalEfectivo} />
                            <FilaResumen label="Total esperado (sistema)" valor={montoEsperado} />
                            <FilaDiferencia diferencia={diferencia} />
                        </div>
                    </div>

                    {/* Advertencia */}
                    <div
                        className="flex items-start gap-2 rounded-xl px-4 py-3 text-sm"
                        style={{ backgroundColor: 'rgba(239,68,68,0.06)', border: '1px solid rgba(239,68,68,0.2)' }}
                    >
                        <AlertTriangle size={15} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-danger)' }} />
                        <p style={{ color: 'var(--color-text)' }}>
                            Esta acción es <strong>irreversible</strong>. Una vez cerrado el turno no podrá reabrirse.
                        </p>
                    </div>

                    {/* Observación */}
                    <div>
                        <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                            Observación de cierre (opcional)
                        </label>
                        <textarea
                            rows={3}
                            value={form.observacion_cierre}
                            onChange={e => setForm(f => ({ ...f, observacion_cierre: e.target.value }))}
                            disabled={saving}
                            className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                            style={{
                                border:          '1px solid var(--color-border)',
                                backgroundColor: 'var(--color-surface)',
                                color:           'var(--color-text)',
                            }}
                        />
                    </div>

                    {/* Botón confirmar */}
                    <div className="flex justify-end gap-3">
                        <Button variant="ghost" onClick={() => router.visit(route('turnos.index'))}>
                            Cancelar
                        </Button>
                        <Button variant="danger" onClick={submit} disabled={saving}>
                            {saving ? 'Cerrando...' : 'Confirmar cierre'}
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div
            className="rounded-xl p-4"
            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
        >
            <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>{title}</p>
            {children}
        </div>
    );
}

function InfoCard({ icon, label, valor }: { icon: React.ReactNode; label: string; valor: string }) {
    return (
        <div
            className="rounded-lg px-3 py-2"
            style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
        >
            <div className="flex items-center gap-2 mb-1">{icon}<p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{label}</p></div>
            <p className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>{valor}</p>
        </div>
    );
}

function FilaResumen({ label, valor }: { label: string; valor: number }) {
    return (
        <div className="flex items-center justify-between px-4 py-3">
            <span className="text-sm" style={{ color: 'var(--color-text)' }}>{label}</span>
            <span className="font-semibold text-sm" style={{ color: 'var(--color-text)' }}>
                S/ {valor.toFixed(2)}
            </span>
        </div>
    );
}

function FilaDiferencia({ diferencia }: { diferencia: number }) {
    const color = diferencia === 0
        ? 'var(--color-success)'
        : diferencia > 0
            ? '#b45309'
            : 'var(--color-danger)';

    const label = diferencia === 0 ? 'Sin diferencia' : diferencia > 0 ? 'Sobrante' : 'Faltante';

    return (
        <div
            className="flex items-center justify-between px-4 py-3"
            style={{ backgroundColor: 'var(--color-bg)' }}
        >
            <span className="text-sm font-semibold" style={{ color }}>Diferencia ({label})</span>
            <span className="font-bold text-sm" style={{ color }}>
                S/ {Math.abs(diferencia).toFixed(2)}
            </span>
        </div>
    );
}
