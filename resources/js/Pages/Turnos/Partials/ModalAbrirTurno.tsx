import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Info } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import type { Caja } from '@/types';

interface AperturaSugerida {
    monto:   number;
    origen:  'arrastre' | 'fondo_fijo';
    detalle: string;
}

interface CajaDisponible extends Caja {
    tiene_turno_abierto: boolean;
    apertura_sugerida?:  AperturaSugerida | null;
}

interface ConfigEfectivo {
    modo_apertura_caja: 'libre' | 'arrastre' | 'fondo_fijo';
    apertura_editable:  boolean;
}

interface AbrirForm {
    caja_id:                    number | '';
    monto_apertura:             string;
    monto_fondos_adicionales:   string;
    monto_caja_chica:           string;
    observacion_apertura:       string;
}

const emptyForm = (): AbrirForm => ({
    caja_id:                  '',
    monto_apertura:           '',
    monto_fondos_adicionales: '',
    monto_caja_chica:         '',
    observacion_apertura:     '',
});

interface ConfigFondosLocal {
    usa_fondos_iniciales: boolean;
    fondos_iniciales_en_declaracion: boolean;
}

interface Props {
    isOpen:           boolean;
    onClose:          () => void;
    cajasDisponibles: CajaDisponible[];
    configFondos?:    Record<number, ConfigFondosLocal>;
    configEfectivo?:  ConfigEfectivo;
}

export default function ModalAbrirTurno({ isOpen, onClose, cajasDisponibles, configFondos = {}, configEfectivo }: Props) {
    const [form, setForm]     = useState<AbrirForm>(emptyForm());
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const cajaSeleccionada = cajasDisponibles.find(c => c.id === Number(form.caja_id)) ?? null;
    const empresaPideFondos = cajaSeleccionada
        ? (configFondos[cajaSeleccionada.local_id]?.usa_fondos_iniciales ?? true)
        : false;
    const usaCajaChica     = empresaPideFondos && (cajaSeleccionada?.caja_chica_activa ?? false);

    const opcionesCajas = cajasDisponibles
        .filter(c => !c.tiene_turno_abierto)
        .map(c => ({ value: c.id, label: `${c.nombre}${c.local ? ` — ${c.local.nombre}` : ''}` }));

    // Apertura sugerida por la empresa (arrastre del cierre anterior / fondo fijo)
    const sugerida = cajaSeleccionada?.apertura_sugerida ?? null;
    const aperturaBloqueada = !!sugerida && !(configEfectivo?.apertura_editable ?? true);

    function handleCajaChange(id: number | string) {
        const caja = cajasDisponibles.find(c => c.id === Number(id)) ?? null;
        const sugeridoMonto = caja?.apertura_sugerida?.monto ?? 0;
        setForm(f => ({
            ...f,
            caja_id:                  Number(id) || '',
            // Precarga con el arrastre (la cajera ya no digita de memoria). Los
            // fondos adicionales quedan en 0; el total es arrastre + adicionales.
            monto_apertura:           sugeridoMonto ? sugeridoMonto.toFixed(2) : '',
            monto_fondos_adicionales: '',
            monto_caja_chica:         caja?.caja_chica_activa
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
        const payload = {
            ...form,
            monto_fondos_adicionales: form.monto_fondos_adicionales || '0',
        };
        router.post(route('turnos.abrir'), payload as any, {
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

                <div className="space-y-2">
                    {sugerida ? (
                        <div className="rounded-xl p-3 space-y-3" style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                            <Input
                                label="Arrastre del cierre anterior (S/)"
                                type="number"
                                step="0.01"
                                min="0"
                                value={sugerida.monto.toFixed(2)}
                                disabled
                            />
                            <Input
                                label="Fondos adicionales (S/)"
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.monto_fondos_adicionales}
                                onChange={e => {
                                    const adicionales = Math.max(0, parseFloat(e.target.value || '0'));
                                    setForm(f => ({
                                        ...f,
                                        monto_fondos_adicionales: e.target.value,
                                        monto_apertura: (sugerida.monto + adicionales).toFixed(2),
                                    }));
                                }}
                                placeholder="0.00"
                                error={errors.monto_fondos_adicionales}
                                disabled={saving}
                            />
                            <Input
                                label="Total apertura (S/)"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                value={form.monto_apertura}
                                disabled={aperturaBloqueada || saving}
                                onChange={e => {
                                    const total = Math.max(0, parseFloat(e.target.value || '0'));
                                    setForm(f => ({
                                        ...f,
                                        monto_apertura: e.target.value,
                                        monto_fondos_adicionales: Math.max(0, total - sugerida.monto).toFixed(2),
                                    }));
                                }}
                                error={errors.monto_apertura}
                            />
                            <div
                                className="flex items-start gap-2 rounded-lg px-3 py-2 text-xs"
                                style={{
                                    backgroundColor: 'color-mix(in srgb, var(--color-success) 8%, transparent)',
                                    color: 'var(--color-text-muted)',
                                }}
                            >
                                <Info size={13} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--color-success)' }} />
                                <span>
                                    {sugerida.origen === 'arrastre'
                                        ? <>Apertura sugerida por <strong>arrastre</strong>: {sugerida.detalle}.</>
                                        : <>Apertura por <strong>fondo fijo</strong> de esta caja.</>}
                                    {' '}El arrastre es fijo; solo puedes sumar fondos adicionales. El cambio quedará auditado.
                                </span>
                            </div>
                        </div>
                    ) : (
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
                    )}
                </div>

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
