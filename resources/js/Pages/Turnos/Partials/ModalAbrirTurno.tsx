import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Info } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import type { Caja } from '@/types';

interface CajaDisponible extends Caja {
    tiene_turno_abierto: boolean;
}

interface AbrirForm {
    caja_id:               number | '';
    monto_apertura:        string;
    monto_caja_chica:      string;
    observacion_apertura:  string;
}

const emptyForm = (): AbrirForm => ({
    caja_id:              '',
    monto_apertura:       '',
    monto_caja_chica:     '',
    observacion_apertura: '',
});

interface Props {
    isOpen:           boolean;
    onClose:          () => void;
    cajasDisponibles: CajaDisponible[];
}

export default function ModalAbrirTurno({ isOpen, onClose, cajasDisponibles }: Props) {
    const [form, setForm]     = useState<AbrirForm>(emptyForm());
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const cajaSeleccionada = cajasDisponibles.find(c => c.id === Number(form.caja_id)) ?? null;
    const usaCajaChica     = cajaSeleccionada?.caja_chica_activa ?? false;

    const opcionesCajas = cajasDisponibles
        .filter(c => !c.tiene_turno_abierto)
        .map(c => ({ value: c.id, label: `${c.nombre}${c.local ? ` — ${c.local.nombre}` : ''}` }));

    function handleCajaChange(id: number | string) {
        const caja = cajasDisponibles.find(c => c.id === Number(id)) ?? null;
        setForm(f => ({
            ...f,
            caja_id:          Number(id) || '',
            monto_caja_chica: caja?.caja_chica_activa
                ? String(caja.caja_chica_monto_sugerido)
                : '',
        }));
    }

    function handleClose() {
        setForm(emptyForm());
        setErrors({});
        onClose();
    }

    function submit() {
        setSaving(true);
        router.post(route('turnos.abrir'), form as any, {
            onSuccess: () => { setSaving(false); handleClose(); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Abrir turno"
            size="md"
            footer={
                <>
                    <Button variant="ghost" onClick={handleClose}>Cancelar</Button>
                    <Button onClick={submit} disabled={saving}>
                        {saving ? 'Abriendo...' : 'Abrir turno'}
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                <Select
                    label="Caja"
                    required
                    value={form.caja_id}
                    onChange={v => handleCajaChange(v)}
                    options={opcionesCajas}
                    placeholder="Seleccionar caja"
                    error={errors.caja_id}
                    disabled={saving}
                />

                <Input
                    label="Monto de apertura (S/)"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={form.monto_apertura}
                    onChange={e => setForm(f => ({ ...f, monto_apertura: e.target.value }))}
                    placeholder="0.00"
                    error={errors.monto_apertura}
                    disabled={saving}
                />

                {usaCajaChica && (
                    <div className="space-y-2">
                        <Input
                            label="Monto caja chica (S/)"
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.monto_caja_chica}
                            onChange={e => setForm(f => ({ ...f, monto_caja_chica: e.target.value }))}
                            placeholder="0.00"
                            error={errors.monto_caja_chica}
                            disabled={saving}
                        />
                        <div
                            className="flex items-start gap-2 rounded-lg px-3 py-2 text-xs"
                            style={{ backgroundColor: 'rgba(59,130,246,0.06)', color: 'var(--color-text-muted)' }}
                        >
                            <Info size={13} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-primary)' }} />
                            Este monto es independiente del dinero de ventas y no se incluye en el arqueo de cierre.
                        </div>
                    </div>
                )}

                <div>
                    <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                        Observación (opcional)
                    </label>
                    <textarea
                        rows={3}
                        value={form.observacion_apertura}
                        onChange={e => setForm(f => ({ ...f, observacion_apertura: e.target.value }))}
                        disabled={saving}
                        className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                        style={{
                            border:           '1px solid var(--color-border)',
                            backgroundColor:  'var(--color-surface)',
                            color:            'var(--color-text)',
                        }}
                    />
                    {errors.observacion_apertura && (
                        <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.observacion_apertura}</p>
                    )}
                </div>
            </div>
        </Modal>
    );
}
