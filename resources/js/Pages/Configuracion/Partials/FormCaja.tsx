import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import type { Local } from '@/types';

export interface CajaForm {
    local_id:                   number | '';
    nombre:                     string;
    caja_chica_activa:          boolean;
    caja_chica_monto_sugerido:  number;
    caja_chica_en_arqueo:       boolean;
    activo:                     boolean;
}

export const emptyCaja = (): CajaForm => ({
    local_id:                  '',
    nombre:                    '',
    caja_chica_activa:         false,
    caja_chica_monto_sugerido: 0,
    caja_chica_en_arqueo:      false,
    activo:                    true,
});

interface Props {
    form:        CajaForm;
    setForm:     (fn: (prev: CajaForm) => CajaForm) => void;
    errors:      Partial<Record<keyof CajaForm, string>>;
    locales:     Local[];
    editando:    boolean;
    disabled?:   boolean;
}

export default function FormCaja({ form, setForm, errors, locales, editando, disabled }: Props) {
    const opcionesLocal = locales.map(l => ({ value: l.id, label: l.nombre }));

    return (
        <div className="space-y-4">
            {!editando && (
                <Select
                    label="Local"
                    required
                    value={form.local_id}
                    onChange={v => setForm(f => ({ ...f, local_id: Number(v) }))}
                    options={opcionesLocal}
                    placeholder="Seleccionar local"
                    error={errors.local_id}
                    disabled={disabled}
                />
            )}

            <Input
                label="Nombre"
                required
                value={form.nombre}
                onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                placeholder="Ej: Caja principal"
                error={errors.nombre}
                disabled={disabled}
            />

            <div
                className="rounded-xl p-4 space-y-3"
                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}
            >
                <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Caja chica</p>

                <Switch
                    label="Activar caja chica en esta caja"
                    checked={form.caja_chica_activa}
                    onChange={e => setForm(f => ({
                        ...f,
                        caja_chica_activa: (e.target as HTMLInputElement).checked,
                    }))}
                    disabled={disabled}
                />

                {form.caja_chica_activa && (
                    <>
                        <Input
                            label="Monto sugerido al abrir turno (S/)"
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.caja_chica_monto_sugerido}
                            onChange={e => setForm(f => ({ ...f, caja_chica_monto_sugerido: parseFloat(e.target.value) || 0 }))}
                            error={errors.caja_chica_monto_sugerido}
                            disabled={disabled}
                        />
                        <Switch
                            label="Mostrar caja chica en el arqueo de cierre (solo informativo)"
                            checked={form.caja_chica_en_arqueo}
                            onChange={e => setForm(f => ({
                                ...f,
                                caja_chica_en_arqueo: (e.target as HTMLInputElement).checked,
                            }))}
                            disabled={disabled}
                        />
                    </>
                )}
            </div>

            {editando && (
                <Switch
                    label="Activo"
                    checked={form.activo}
                    onChange={e => setForm(f => ({ ...f, activo: (e.target as HTMLInputElement).checked }))}
                    disabled={disabled}
                />
            )}
        </div>
    );
}
