import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import type { GastoConcepto, GastoTipo, Local, Turno } from '@/types';

export interface GastoForm {
    gasto_tipo_id:      number | '';
    gasto_concepto_id:  number | '';
    monto:              string;
    fecha:              string;
    comentario:         string;
    turno_id:           number | null;
    local_id:           number | '';
}

export const emptyGasto = (turnoId: number | null = null): GastoForm => ({
    gasto_tipo_id:     '',
    gasto_concepto_id: '',
    monto:             '',
    fecha:             new Date().toISOString().split('T')[0],
    comentario:        '',
    turno_id:          turnoId,
    local_id:          '',
});

interface Props {
    isOpen:       boolean;
    onClose:      () => void;
    tipos:        GastoTipo[];
    turnoActivo:  Turno | null;
    locales:      Local[];
    esAdmin:      boolean;
}

export default function ModalGasto({ isOpen, onClose, tipos, turnoActivo, locales, esAdmin }: Props) {
    const [form, setForm]     = useState<GastoForm>(emptyGasto(turnoActivo?.id ?? null));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    // Cuando cambia el turno activo, sincronizar turno_id
    useEffect(() => {
        setForm(f => ({ ...f, turno_id: turnoActivo?.id ?? null }));
    }, [turnoActivo?.id]);

    const esTurnogasto = form.turno_id !== null;

    const tipoSeleccionado = tipos.find(t => t.id === Number(form.gasto_tipo_id)) ?? null;
    const conceptosFiltrados: GastoConcepto[] = tipoSeleccionado?.conceptos?.filter(c => c.activo) ?? [];

    const opcionesTipos = tipos
        .filter(t => t.activo)
        .map(t => ({ value: t.id, label: `${t.nombre} (${t.categoria})` }));

    const opcionesConceptos = conceptosFiltrados.map(c => ({ value: c.id, label: c.nombre }));
    const opcionesLocales   = locales.map(l => ({ value: l.id, label: l.nombre }));

    function handleTipoChange(v: number | string) {
        setForm(f => ({ ...f, gasto_tipo_id: Number(v) || '', gasto_concepto_id: '' }));
    }

    function handleClose() {
        setForm(emptyGasto(turnoActivo?.id ?? null));
        setErrors({});
        onClose();
    }

    function submit() {
        setSaving(true);
        router.post(route('gastos.store'), form as any, {
            onSuccess: () => { setSaving(false); handleClose(); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Registrar gasto"
            size="md"
            footer={
                <>
                    <Button variant="ghost" onClick={handleClose}>Cancelar</Button>
                    <Button onClick={submit} disabled={saving}>
                        {saving ? 'Guardando...' : 'Registrar gasto'}
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                {/* Badge tipo de gasto */}
                {esTurnogasto ? (
                    <div
                        className="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium"
                        style={{ backgroundColor: 'rgba(59,130,246,0.06)', color: 'var(--color-primary)', border: '1px solid rgba(59,130,246,0.2)' }}
                    >
                        <Receipt size={13} />
                        Se registrará en el turno activo
                    </div>
                ) : (
                    <div
                        className="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium"
                        style={{ backgroundColor: 'rgba(99,102,241,0.06)', color: '#6366f1', border: '1px solid rgba(99,102,241,0.2)' }}
                    >
                        <Receipt size={13} />
                        Gasto administrativo (sin turno)
                    </div>
                )}

                <Select
                    label="Tipo de gasto"
                    required
                    value={form.gasto_tipo_id}
                    onChange={v => handleTipoChange(v)}
                    options={opcionesTipos}
                    placeholder="Seleccionar tipo"
                    error={errors.gasto_tipo_id}
                    disabled={saving}
                />

                <Select
                    label="Concepto"
                    required
                    value={form.gasto_concepto_id}
                    onChange={v => setForm(f => ({ ...f, gasto_concepto_id: Number(v) || '' }))}
                    options={opcionesConceptos}
                    placeholder={form.gasto_tipo_id ? 'Seleccionar concepto' : 'Primero selecciona un tipo'}
                    error={errors.gasto_concepto_id}
                    disabled={saving || !form.gasto_tipo_id}
                />

                <Input
                    label="Monto (S/)"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    value={form.monto}
                    onChange={e => setForm(f => ({ ...f, monto: e.target.value }))}
                    placeholder="0.00"
                    error={errors.monto}
                    disabled={saving}
                />

                <Input
                    label="Fecha"
                    type="date"
                    required
                    value={form.fecha}
                    onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))}
                    error={errors.fecha}
                    disabled={saving}
                />

                {/* Local — solo para gastos administrativos cuando es admin */}
                {!esTurnogasto && esAdmin && (
                    <Select
                        label="Local"
                        required
                        value={form.local_id}
                        onChange={v => setForm(f => ({ ...f, local_id: Number(v) || '' }))}
                        options={opcionesLocales}
                        placeholder="Seleccionar local"
                        error={errors.local_id}
                        disabled={saving}
                    />
                )}

                <div>
                    <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                        Comentario <span style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>(opcional)</span>
                    </label>
                    <textarea
                        rows={2}
                        value={form.comentario}
                        onChange={e => setForm(f => ({ ...f, comentario: e.target.value }))}
                        disabled={saving}
                        placeholder="Detalles adicionales..."
                        className="w-full rounded-xl px-3 py-2 text-sm resize-none"
                        style={{
                            border:          '1px solid var(--color-border)',
                            backgroundColor: 'var(--color-surface)',
                            color:           'var(--color-text)',
                        }}
                    />
                    {errors.comentario && (
                        <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.comentario}</p>
                    )}
                </div>
            </div>
        </Modal>
    );
}
