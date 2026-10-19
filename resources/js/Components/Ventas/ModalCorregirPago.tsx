import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import Callout from '@/Components/UI/Callout';
import Select from '@/Components/UI/Select';

/** Método de pago elegible al corregir el pago de una venta. */
interface MetodoCambio {
    id: number;
    nombre: string;
    admite_vuelto: boolean;
    requiere_referencia: boolean;
    cuentas: { pivot_id: number; nombre: string }[];
}

/** Línea editable: el monto y el vuelto son fijos, solo cambia cómo se pagó. */
interface LineaCambio {
    id: number;
    monto: number;
    vuelto: number;
    metodo_pago_id: number;
    cuenta_metodo_pago_id: number | null;
    referencia: string;
}

interface Datos {
    venta: { id: number; numero: string; tipo_comprobante: string };
    bloqueo: string | null;
    pagos: (Omit<LineaCambio, 'referencia'> & { referencia: string | null })[];
    metodos: MetodoCambio[];
}

const fmtS = (n: number) => `S/ ${n.toFixed(2)}`;
const campo = { border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' };

/**
 * Corregir SOLO el método de pago de una venta, sin entrar a editarla. Sirve
 * también con boleta/factura informada a SUNAT: el comprobante no lleva el
 * medio de pago. Pide sus datos al abrirse (lista de ventas o detalle).
 */
export default function ModalCorregirPago({ ventaId, onClose }: { ventaId: number | null; onClose: () => void }) {
    const [datos, setDatos] = useState<Datos | null>(null);
    const [lineas, setLineas] = useState<LineaCambio[]>([]);
    const [motivo, setMotivo] = useState('');
    const [cargando, setCargando] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!ventaId) return;
        setDatos(null); setLineas([]); setMotivo(''); setError(null); setCargando(true);
        axios.get<Datos>(route('ventas.metodo-pago.datos', ventaId))
            .then(({ data }) => {
                setDatos(data);
                setLineas(data.pagos.map(p => ({ ...p, referencia: p.referencia ?? '' })));
            })
            .catch(e => setError(e.response?.status === 403
                ? 'No tienes permiso para corregir el método de pago.'
                : 'No se pudo cargar los pagos de la venta. Inténtalo de nuevo.'))
            .finally(() => setCargando(false));
    }, [ventaId]);

    function cambiarLinea(id: number, cambio: Partial<LineaCambio>) {
        setLineas(ls => ls.map(l => {
            if (l.id !== id) return l;
            const nueva = { ...l, ...cambio };
            // Al cambiar de método, su cuenta: la única que tenga, o elegir.
            if (cambio.metodo_pago_id !== undefined) {
                const cts = datos?.metodos.find(m => m.id === cambio.metodo_pago_id)?.cuentas ?? [];
                nueva.cuenta_metodo_pago_id = cts.length === 1 ? cts[0].pivot_id : null;
            }
            return nueva;
        }));
    }

    function guardar() {
        if (!ventaId) return;
        setGuardando(true);
        setError(null);
        router.put(route('ventas.metodo-pago', ventaId), {
            pagos: lineas.map(l => ({ id: l.id, metodo_pago_id: l.metodo_pago_id, cuenta_metodo_pago_id: l.cuenta_metodo_pago_id, referencia: l.referencia })),
            motivo,
        }, {
            preserveScroll: true,
            onSuccess: () => { setGuardando(false); onClose(); },
            onError:   (errs) => { setGuardando(false); setError((errs as Record<string, string>).metodo_pago ?? Object.values(errs)[0] ?? 'No se pudo guardar.'); },
        });
    }

    const bloqueado = !!datos?.bloqueo;

    return (
        <Modal
            isOpen={ventaId !== null}
            onClose={() => { if (!guardando) onClose(); }}
            title={`Corregir método de pago${datos ? ` · ${datos.venta.numero}` : ''}`}
            size="md"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                    <Button variant="primary" onClick={guardar} loading={guardando} disabled={!datos || bloqueado || cargando}>
                        Guardar método de pago
                    </Button>
                </>
            }
        >
            <div className="space-y-3">
                {cargando && <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Cargando pagos…</p>}

                {datos && bloqueado && <Callout variant="warning">{datos.bloqueo}</Callout>}

                {datos && !bloqueado && (
                    <>
                        <Callout variant="info">
                            <p style={{ color: 'var(--color-text)' }}>
                                Solo cambia <strong>cómo se pagó</strong>. El monto, los productos y el comprobante
                                {datos.venta.tipo_comprobante !== 'ticket' ? ' (que ante SUNAT solo dice «Contado» o «Crédito»)' : ''} no cambian.
                                El dinero pasa a la cuenta correcta con la fecha de la venta.
                            </p>
                        </Callout>
                        {lineas.map(l => {
                            const metodo = datos.metodos.find(m => m.id === l.metodo_pago_id);
                            const opciones = datos.metodos
                                .filter(m => l.vuelto <= 0 || m.admite_vuelto || m.id === l.metodo_pago_id)
                                .map(m => ({ value: m.id, label: m.nombre }));
                            return (
                                <div key={l.id} className="rounded-xl p-3 space-y-2" style={{ border: '1px solid var(--color-border)' }}>
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Pago de {fmtS(l.monto)}</span>
                                        {l.vuelto > 0 && (
                                            <span className="text-[13px] font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>
                                                dio vuelto {fmtS(l.vuelto)}: solo métodos con vuelto
                                            </span>
                                        )}
                                    </div>
                                    <Select label="Método de pago" options={opciones} value={l.metodo_pago_id}
                                        onChange={v => cambiarLinea(l.id, { metodo_pago_id: Number(v) })} disabled={guardando} />
                                    {(metodo?.cuentas.length ?? 0) > 1 && (
                                        <Select label="Cuenta donde entró" placeholder="Elige la cuenta" required
                                            options={metodo!.cuentas.map(c => ({ value: c.pivot_id, label: c.nombre }))}
                                            value={l.cuenta_metodo_pago_id ?? ''}
                                            onChange={v => cambiarLinea(l.id, { cuenta_metodo_pago_id: Number(v) })} disabled={guardando} />
                                    )}
                                    {metodo?.requiere_referencia && (
                                        <div>
                                            <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>N° de operación</label>
                                            <input value={l.referencia} maxLength={100} disabled={guardando}
                                                onChange={e => cambiarLinea(l.id, { referencia: e.target.value })}
                                                className="w-full h-10 rounded-xl px-3 text-sm" style={campo} />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                        <div>
                            <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>Motivo (opcional)</label>
                            <input value={motivo} maxLength={255} onChange={e => setMotivo(e.target.value)} disabled={guardando}
                                placeholder="Ej.: se marcó efectivo y pagó con Yape"
                                className="w-full h-10 rounded-xl px-3 text-sm" style={campo} />
                        </div>
                    </>
                )}

                {error && <p className="text-sm font-semibold" role="alert" style={{ color: 'var(--color-danger)' }}>{error}</p>}
            </div>
        </Modal>
    );
}
