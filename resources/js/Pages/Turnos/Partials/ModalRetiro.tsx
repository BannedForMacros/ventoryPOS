import { useState } from 'react';
import { router } from '@inertiajs/react';
import { HandCoins, Info } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';

interface Props {
    isOpen:  boolean;
    onClose: () => void;
    turnoId: number;
    /** true si la empresa exige aprobación de un administrador. */
    requiereAprobacion: boolean;
}

/**
 * Retiro de efectivo del turno ("Entrega a administración" / sangría).
 * No es un gasto: el dinero solo cambia de custodia y queda trazado.
 */
export default function ModalRetiro({ isOpen, onClose, turnoId, requiereAprobacion }: Props) {
    const [monto, setMonto]             = useState('');
    const [concepto, setConcepto]       = useState('Entrega a administración');
    const [observacion, setObservacion] = useState('');
    const [errors, setErrors]           = useState<Record<string, string>>({});
    const [saving, setSaving]           = useState(false);

    function handleClose() {
        setMonto(''); setConcepto('Entrega a administración'); setObservacion('');
        setErrors({});
        onClose();
    }

    function submit() {
        setSaving(true);
        router.post(route('turnos.retiros.store', turnoId),
            { monto, concepto, observacion },
            {
                onSuccess: () => { setSaving(false); handleClose(); },
                onError:   (errs: any) => { setErrors(errs); setSaving(false); },
            });
    }

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Retiro de efectivo"
            size="md"
            footer={
                <>
                    <Button variant="ghost" onClick={handleClose}>Cancelar</Button>
                    <Button onClick={submit} disabled={saving || !parseFloat(monto)}>
                        <HandCoins size={15} className="mr-1" />
                        {saving ? 'Registrando…' : 'Registrar retiro'}
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                <Input
                    label="Monto a retirar (S/)"
                    type="number" step="0.01" min="0" required
                    value={monto}
                    onChange={e => setMonto(e.target.value)}
                    placeholder="0.00"
                    error={errors.monto}
                    disabled={saving}
                />
                <Input
                    label="Concepto"
                    value={concepto}
                    onChange={e => setConcepto(e.target.value)}
                    placeholder="Entrega a administración"
                    error={errors.concepto}
                    disabled={saving}
                />
                <div>
                    <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                        Observación (opcional)
                    </label>
                    <textarea
                        rows={2}
                        value={observacion}
                        onChange={e => setObservacion(e.target.value)}
                        disabled={saving}
                        className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                        style={{
                            border: '1px solid var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                            color: 'var(--color-text)',
                        }}
                    />
                </div>
                <div className="flex items-start gap-2 rounded-lg px-3 py-2 text-xs"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 6%, transparent)', color: 'var(--color-text-muted)' }}>
                    <Info size={13} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-primary)' }} />
                    <span>
                        El retiro <strong>no es un gasto</strong>: el dinero pasa a custodia de administración
                        y se descuenta del efectivo esperado de tu caja.
                        {requiereAprobacion && ' Quedará pendiente de aprobación por un administrador.'}
                    </span>
                </div>
            </div>
        </Modal>
    );
}
