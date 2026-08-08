import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Info, Pencil } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';

interface Props {
    isOpen:          boolean;
    onClose:         () => void;
    turnoId:         number;
    montoActual:     number | string;
    editable?:       boolean;
}

export default function ModalEditarApertura({
    isOpen,
    onClose,
    turnoId,
    montoActual,
    editable = true,
}: Props) {
    const [monto, setMonto]   = useState(String(montoActual ?? ''));
    const [motivo, setMotivo] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    function handleClose() {
        setMonto(String(montoActual ?? ''));
        setMotivo('');
        setErrors({});
        onClose();
    }

    function submit() {
        setSaving(true);
        router.patch(route('turnos.apertura.update', turnoId), {
            monto_apertura: monto,
            motivo,
        }, {
            preserveScroll: true,
            onSuccess:      () => { setSaving(false); handleClose(); },
            onError:        (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    const motivoValido = motivo.trim().length >= 10;

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Editar apertura"
            size="md"
            footer={
                <>
                    <Button variant="ghost" onClick={handleClose}>Cancelar</Button>
                    <Button onClick={submit} disabled={saving || !motivoValido || !editable}>
                        {saving ? 'Guardando...' : <><Pencil size={14} className="mr-1" />Guardar cambio</>}
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                {!editable && (
                    <div
                        className="flex items-start gap-2 rounded-lg px-3 py-2 text-xs"
                        style={{ backgroundColor: 'rgba(239,68,68,0.06)', color: 'var(--color-danger)' }}
                    >
                        <Info size={13} className="mt-0.5 flex-shrink-0" />
                        La empresa no permite editar el monto de apertura. Si hay un error, comunicalo con un administrador.
                    </div>
                )}

                <Input
                    label="Nuevo monto de apertura (S/)"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={monto}
                    onChange={e => setMonto(e.target.value)}
                    placeholder="0.00"
                    error={errors.monto_apertura}
                    disabled={saving || !editable}
                />

                <div>
                    <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                        Motivo de la correccion <span style={{ color: 'var(--color-danger)' }}>*</span>
                    </label>
                    <textarea
                        rows={3}
                        value={motivo}
                        onChange={e => setMotivo(e.target.value)}
                        disabled={saving || !editable}
                        maxLength={500}
                        placeholder="Ej: Se ingreso S/ 500 en vez de S/ 50 por error."
                        className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                        style={{
                            border:           '1px solid var(--color-border)',
                            backgroundColor:  'var(--color-surface)',
                            color:            'var(--color-text)',
                        }}
                    />
                    <div className="flex justify-between mt-1 text-xs">
                        <span style={{ color: motivoValido ? 'var(--color-text-muted)' : 'var(--color-danger)' }}>
                            {motivoValido
                                ? 'Minimo 10 caracteres ✓'
                                : `Minimo 10 caracteres (${motivo.trim().length}/10)`}
                        </span>
                        <span style={{ color: 'var(--color-text-muted)' }}>{motivo.length}/500</span>
                    </div>
                    {errors.motivo && (
                        <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.motivo}</p>
                    )}
                </div>

                <div
                    className="flex items-start gap-2 rounded-lg px-3 py-2 text-xs"
                    style={{ backgroundColor: 'rgba(59,130,246,0.06)', color: 'var(--color-text-muted)' }}
                >
                    <Info size={13} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-primary)' }} />
                    Este cambio afecta el monto esperado al cierre y queda registrado en auditoria.
                </div>
            </div>
        </Modal>
    );
}
