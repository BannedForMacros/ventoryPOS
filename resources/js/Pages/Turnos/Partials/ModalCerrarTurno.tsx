import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, ChevronRight } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
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

function buildArqueoInicial(): FilaArqueo[] {
    return DENOMINACIONES_PEN.map(d => ({ denominacion: d, cantidad: 0 }));
}

function buildMetodosInicial(metodos: MetodoPago[]): FilaMetodo[] {
    return metodos
        .filter(m => m.tipo !== 'efectivo')
        .map(m => ({ metodo_pago_id: m.id, monto_declarado: '' }));
}

interface Props {
    isOpen:       boolean;
    onClose:      () => void;
    turno:        Turno;
    metodosPago:  MetodoPago[];
}

type Paso = 'arqueo' | 'resumen';

export default function ModalCerrarTurno({ isOpen, onClose, turno, metodosPago }: Props) {
    const [paso, setPaso]       = useState<Paso>('arqueo');
    const [form, setForm]       = useState<CerrarForm>({
        arqueo:             buildArqueoInicial(),
        arqueo_metodos:     buildMetodosInicial(metodosPago),
        observacion_cierre: '',
    });
    const [errors, setErrors]   = useState<Record<string, string>>({});
    const [saving, setSaving]   = useState(false);

    const caja = turno.caja!;

    const totalEfectivo = useMemo(() =>
        form.arqueo.reduce((sum, f) => sum + f.denominacion * f.cantidad, 0),
    [form.arqueo]);

    const montoEsperado = parseFloat(turno.monto_apertura) || 0;

    const diferencia = totalEfectivo - montoEsperado;

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

    function handleClose() {
        setPaso('arqueo');
        setErrors({});
        onClose();
    }

    function submit() {
        setSaving(true);
        const payload = {
            arqueo:             form.arqueo,
            arqueo_metodos:     form.arqueo_metodos.map(m => ({
                metodo_pago_id:  m.metodo_pago_id,
                monto_declarado: parseFloat(m.monto_declarado) || 0,
            })),
            observacion_cierre: form.observacion_cierre,
        };

        router.post(route('turnos.cerrar', turno.id), payload as any, {
            onSuccess: () => { setSaving(false); handleClose(); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); setPaso('arqueo'); },
        });
    }

    const metodosFiltrados = metodosPago.filter(m => m.tipo !== 'efectivo');

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Cerrar turno"
            size="xl"
            footer={
                paso === 'arqueo' ? (
                    <>
                        <Button variant="ghost" onClick={handleClose}>Cancelar</Button>
                        <Button onClick={() => setPaso('resumen')}>
                            Ver resumen <ChevronRight size={15} className="ml-1" />
                        </Button>
                    </>
                ) : (
                    <>
                        <Button variant="ghost" onClick={() => setPaso('arqueo')}>Volver</Button>
                        <Button variant="danger" onClick={submit} disabled={saving}>
                            {saving ? 'Cerrando...' : 'Confirmar cierre'}
                        </Button>
                    </>
                )
            }
        >
            {paso === 'arqueo' ? (
                <div className="space-y-5">
                    {/* ── Arqueo de efectivo ── */}
                    <div>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                            Arqueo de caja — dinero de ventas en efectivo
                        </p>
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
                    </div>

                    {/* ── Arqueo por método de pago ── */}
                    {metodosFiltrados.length > 0 && (
                        <div>
                            <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                                Otros métodos de pago
                            </p>
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
                        </div>
                    )}

                    {/* ── Caja chica (solo informativo) ── */}
                    {caja.caja_chica_activa && caja.caja_chica_en_arqueo && (
                        <div
                            className="rounded-xl px-4 py-3 flex items-center justify-between"
                            style={{ backgroundColor: 'rgba(234,179,8,0.08)', border: '1px solid rgba(234,179,8,0.3)' }}
                        >
                            <div>
                                <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Caja chica asignada</p>
                                <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Este monto NO está incluido en el arqueo de ventas
                                </p>
                            </div>
                            <span className="font-semibold" style={{ color: '#b45309' }}>
                                S/ {parseFloat(turno.monto_caja_chica).toFixed(2)}
                            </span>
                        </div>
                    )}
                </div>
            ) : (
                <div className="space-y-5">
                    {/* ── Resumen ── */}
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

                    {/* ── Advertencia cierre irreversible ── */}
                    <div
                        className="flex items-start gap-2 rounded-xl px-4 py-3 text-sm"
                        style={{ backgroundColor: 'rgba(239,68,68,0.06)', border: '1px solid rgba(239,68,68,0.2)' }}
                    >
                        <AlertTriangle size={15} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-danger)' }} />
                        <p style={{ color: 'var(--color-text)' }}>
                            Esta acción es <strong>irreversible</strong>. Una vez cerrado el turno no podrá reabrirse.
                        </p>
                    </div>

                    {/* ── Observación ── */}
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
                </div>
            )}
        </Modal>
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
