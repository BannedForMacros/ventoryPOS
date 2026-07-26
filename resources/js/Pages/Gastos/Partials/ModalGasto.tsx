import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import type { Gasto, GastoConcepto, GastoTipo, Local, MetodoPagoConCuentas, PageProps, Turno } from '@/types';
import { hoyLocal } from '@/lib/fechas';

export interface GastoForm {
    gasto_tipo_id:         number | '';
    gasto_concepto_id:     number | '';
    monto:                 string;
    fecha:                 string;
    comentario:            string;
    turno_id:              number | null;
    local_id:              number | '';
    // Se paga con: método de pago y (si el método tiene cuentas) su cuenta.
    // cuenta_metodo_pago_id es el id de la fila pivote, igual que en el POS.
    metodo_pago_id:        number | '';
    cuenta_metodo_pago_id: number | '';
}

export const emptyGasto = (turnoId: number | null = null, metodoPagoId: number | '' = ''): GastoForm => ({
    gasto_tipo_id:         '',
    gasto_concepto_id:     '',
    monto:                 '',
    fecha:                 hoyLocal(),
    comentario:            '',
    turno_id:              turnoId,
    local_id:              '',
    metodo_pago_id:        metodoPagoId,
    cuenta_metodo_pago_id: '',
});

interface Props {
    isOpen:           boolean;
    onClose:          () => void;
    tipos:            GastoTipo[];
    turnoActivo:      Turno | null;
    locales:          Local[];
    esAdmin:          boolean;
    turnosAbiertos:   Turno[];
    metodosPago?:     MetodoPagoConCuentas[];
    // Si viene, el modal entra en modo EDICIÓN de ese gasto.
    gastoEditar?:     Gasto | null;
}

export default function ModalGasto({ isOpen, onClose, tipos, turnoActivo, locales, esAdmin, turnosAbiertos, metodosPago = [], gastoEditar = null }: Props) {
    const editando = !!gastoEditar;
    // Config: si la empresa apagó el módulo 'gastos', los gastos NO afectan caja
    // (ningún gasto lleva turno; el backend lo fuerza a null). Ocultamos los
    // controles de turno y no preseleccionamos el turno del cajero.
    const afectaCajaGastos = usePage<PageProps>().props.auth.user.empresa?.afecta_caja?.gastos?.activo ?? false;
    const turnoInicial = afectaCajaGastos ? (turnoActivo?.id ?? null) : null;
    // Método por defecto: efectivo si existe, si no el primero disponible.
    const metodoPorDefecto = (): number | '' =>
        (metodosPago.find(m => m.tipo?.slug === 'efectivo') ?? metodosPago[0])?.id ?? '';

    const [form, setForm]     = useState<GastoForm>(emptyGasto(turnoInicial, metodoPorDefecto()));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    // Al abrir: precargar el gasto a editar, o resetear para uno nuevo.
    useEffect(() => {
        if (!isOpen) return;
        if (gastoEditar) {
            setForm({
                gasto_tipo_id:         gastoEditar.gasto_tipo_id,
                gasto_concepto_id:     gastoEditar.gasto_concepto_id,
                monto:                 String(gastoEditar.monto),
                fecha:                 gastoEditar.fecha.slice(0, 10),
                comentario:            gastoEditar.comentario ?? '',
                turno_id:              gastoEditar.turno_id,
                local_id:              gastoEditar.local_id ?? '',
                // '' = mantener la cuenta actual (no se toca la tesorería salvo
                // que el usuario elija otro método a propósito).
                metodo_pago_id:        '',
                cuenta_metodo_pago_id: '',
            });
        } else {
            setForm(emptyGasto(turnoInicial, metodoPorDefecto()));
        }
        setErrors({});
    }, [isOpen, gastoEditar?.id]);

    // Cuando cambia el turno activo, sincronizar turno_id (solo al crear, y solo
    // si el módulo afecta caja; si está apagado, el gasto no lleva turno).
    useEffect(() => {
        if (editando) return;
        setForm(f => ({ ...f, turno_id: turnoInicial }));
    }, [turnoActivo?.id, afectaCajaGastos]);

    // Si los métodos llegan después del montaje y aún no hay uno elegido, fijar
    // el default (solo al crear; al editar '' significa "mantener cuenta").
    useEffect(() => {
        if (editando) return;
        if (form.metodo_pago_id === '' && metodosPago.length > 0) {
            setForm(f => ({ ...f, metodo_pago_id: metodoPorDefecto() }));
        }
    }, [metodosPago]);

    // Método elegido y sus cuentas (para el segundo select, igual que el POS).
    const metodoSel        = metodosPago.find(m => m.id === Number(form.metodo_pago_id)) ?? null;
    const cuentasDelMetodo = (metodoSel?.cuentas ?? []).filter(c => c.pivot?.id);
    // Cuenta por defecto: si el método tiene EXACTAMENTE 1 cuenta se autoselecciona
    // (id del pivote); con 2+ queda '' y es obligatorio elegir.
    const cuentaDefaultDe = (m: typeof metodoSel): number | '' => {
        const cts = (m?.cuentas ?? []).filter(c => c.pivot?.id);
        return cts.length === 1 ? cts[0].pivot!.id : '';
    };
    const faltaCuenta = cuentasDelMetodo.length > 0 && !form.cuenta_metodo_pago_id;

    function handleMetodoChange(v: number | string) {
        // Al cambiar de método: autoselecciona su cuenta si tiene 1, si no resetea.
        const m = metodosPago.find(x => x.id === Number(v)) ?? null;
        setForm(f => ({ ...f, metodo_pago_id: Number(v) || '', cuenta_metodo_pago_id: cuentaDefaultDe(m) }));
    }

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
        setForm(emptyGasto(turnoActivo?.id ?? null, metodoPorDefecto()));
        setErrors({});
        onClose();
    }

    function submit() {
        // Cuenta obligatoria: si el método tiene cuentas, hay que elegir una.
        if (faltaCuenta) {
            setErrors({ cuenta_metodo_pago_id: 'Selecciona la cuenta para este método de pago.' });
            return;
        }
        setSaving(true);
        const opts = {
            onSuccess: () => { setSaving(false); handleClose(); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        };
        if (gastoEditar) {
            router.put(route('gastos.update', gastoEditar.id), form as any, opts);
        } else {
            router.post(route('gastos.store'), form as any, opts);
        }
    }

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title={editando ? 'Editar gasto' : 'Registrar gasto'}
            size="md"
            footer={
                <>
                    <Button variant="ghost" onClick={handleClose}>Cancelar</Button>
                    <Button onClick={submit} disabled={saving}>
                        {saving ? 'Guardando...' : (editando ? 'Guardar cambios' : 'Registrar gasto')}
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
                        Se registrará en el turno seleccionado
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

                {/* Admin: selector de turno abierto (no se cambia al editar).
                    Oculto si la empresa apagó el módulo 'gastos' (no afecta caja). */}
                {afectaCajaGastos && !editando && esTurnogasto && esAdmin && turnosAbiertos.length > 0 && (
                    <Select
                        label="Turno"
                        required
                        value={form.turno_id ?? ''}
                        onChange={v => setForm(f => ({ ...f, turno_id: Number(v) || null }))}
                        options={turnosAbiertos.map(t => ({
                            value: t.id,
                            label: `${t.caja?.nombre ?? 'Caja'} — ${t.user?.name ?? 'Usuario'}`,
                        }))}
                        placeholder="Seleccionar turno"
                        error={errors.turno_id}
                        disabled={saving}
                    />
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

                {/* Se paga con: método de pago y, si tiene cuentas, la cuenta.
                    Mismo patrón que el panel de pago del POS. */}
                {metodosPago.length > 0 && (
                    <div>
                        <Select
                            label={editando ? 'Cambiar cuenta (opcional)' : 'Se paga con'}
                            required={!editando}
                            value={form.metodo_pago_id}
                            onChange={handleMetodoChange}
                            options={metodosPago.map(m => ({ value: m.id, label: m.nombre }))}
                            placeholder={editando ? 'Mantener cuenta actual' : 'Seleccionar método de pago'}
                            error={errors.metodo_pago_id}
                            disabled={saving}
                        />
                        {editando && (
                            <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                Déjalo en blanco para mantener la misma cuenta. Elige un método solo si quieres mover el gasto a otra cuenta.
                            </p>
                        )}
                    </div>
                )}

                {cuentasDelMetodo.length > 0 && (
                    <Select
                        label="Cuenta"
                        required
                        value={form.cuenta_metodo_pago_id}
                        onChange={v => setForm(f => ({ ...f, cuenta_metodo_pago_id: Number(v) || '' }))}
                        // value = id del PIVOTE cuenta_metodo_pago (lo que valida el
                        // backend), NO el id de la cuenta.
                        options={cuentasDelMetodo
                            .filter(c => c.pivot?.id)
                            .map(c => ({ value: c.pivot!.id, label: c.nombre }))}
                        placeholder="— Selecciona una cuenta —"
                        error={errors.cuenta_metodo_pago_id ?? (faltaCuenta ? 'Selecciona la cuenta de este método.' : undefined)}
                        disabled={saving}
                    />
                )}

                {/* Local — solo para gastos administrativos cuando es admin (no se cambia al editar) */}
                {!editando && !esTurnogasto && esAdmin && (
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
