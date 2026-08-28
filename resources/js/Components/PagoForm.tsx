import Select from '@/Components/UI/Select';
import Input from '@/Components/UI/Input';
import Callout from '@/Components/UI/Callout';

export interface MetodoPagoOption {
    id: number;
    nombre: string;
    tipo_slug?: string | null;
    cuentas?: { id: number; nombre: string }[];
}

export interface CuentaOption {
    id: number;
    nombre: string;
    es_efectivo?: boolean;
}

export interface PagoFormValue {
    metodo_pago_id?: string | number | null;
    cuenta_id?: string | number | null;
    referencia?: string;
    observacion?: string;
}

interface PagoFormProps {
    value: PagoFormValue;
    onChange: (value: PagoFormValue) => void;
    metodosPago: MetodoPagoOption[];
    cuentas: CuentaOption[];
    errors?: Record<string, string>;
    required?: boolean;
    showReferencia?: boolean;
    showObservacion?: boolean;
    labels?: {
        metodo?: string;
        cuenta?: string;
        referencia?: string;
        observacion?: string;
    };
    hintMetodo?: string;
    hintCuenta?: string;
}

/**
 * Formulario de pago reutilizable: método de pago, cuenta destino,
 * referencia y observación.
 *
 * La lógica de "cuentas válidas para el método elegido" se encapsula aquí,
 * incluyendo la auto-selección cuando solo hay una cuenta disponible.
 */
export default function PagoForm({
    value,
    onChange,
    metodosPago,
    cuentas,
    errors,
    required = true,
    showReferencia = true,
    showObservacion = true,
    labels = {},
    hintMetodo,
    hintCuenta,
}: PagoFormProps) {
    function cuentasDeMetodo(mid: string | number | undefined | null): CuentaOption[] {
        const m = metodosPago.find(x => String(x.id) === String(mid));
        if (!m) return [];
        if (m.cuentas?.length) return m.cuentas;
        if (m.tipo_slug === 'efectivo') return cuentas.filter(c => c.es_efectivo);
        return [];
    }

    function handleMetodoChange(metodoId: string | number) {
        const cts = cuentasDeMetodo(metodoId);
        const nuevaCuentaId = cts.length === 1 ? String(cts[0].id) : '';
        onChange({
            ...value,
            metodo_pago_id: metodoId,
            cuenta_id: nuevaCuentaId || null,
        });
    }

    function handleCuentaChange(cuentaId: string | number) {
        onChange({ ...value, cuenta_id: cuentaId });
    }

    function handleReferenciaChange(referencia: string) {
        onChange({ ...value, referencia });
    }

    function handleObservacionChange(observacion: string) {
        onChange({ ...value, observacion });
    }

    const cuentasDisponibles = cuentasDeMetodo(value.metodo_pago_id);

    return (
        <div className="space-y-4">
            <Select
                label={labels.metodo ?? 'Método de pago'}
                required={required}
                options={metodosPago.map(m => ({ value: String(m.id), label: m.nombre }))}
                value={value.metodo_pago_id ?? ''}
                onChange={handleMetodoChange}
                placeholder="— Seleccionar —"
                hint={hintMetodo}
                error={errors?.metodo_pago_id}
            />

            {cuentasDisponibles.length > 0 ? (
                <Select
                    label={labels.cuenta ?? 'Cuenta destino'}
                    required={required}
                    options={cuentasDisponibles.map(c => ({ value: String(c.id), label: c.nombre }))}
                    value={value.cuenta_id ?? ''}
                    onChange={handleCuentaChange}
                    placeholder="— Seleccionar —"
                    hint={hintCuenta ?? 'Solo las cuentas vinculadas al método elegido'}
                    error={errors?.cuenta_id}
                />
            ) : value.metodo_pago_id ? (
                <Callout variant="info">
                    El dinero se registrara en la cuenta{' '}
                    <strong>«{metodosPago.find(m => String(m.id) === String(value.metodo_pago_id))?.nombre}»</strong>,
                    que el sistema crea y vincula automaticamente a este metodo. Puedes editarla luego en Configuracion → Cuentas.
                </Callout>
            ) : null}

            {showReferencia && (
                <Input
                    label={labels.referencia ?? 'Referencia (operación, voucher...)'}
                    value={value.referencia ?? ''}
                    onChange={e => handleReferenciaChange(e.target.value)}
                    error={errors?.referencia}
                />
            )}

            {showObservacion && (
                <Input
                    label={labels.observacion ?? 'Observación'}
                    value={value.observacion ?? ''}
                    onChange={e => handleObservacionChange(e.target.value)}
                    error={errors?.observacion}
                />
            )}
        </div>
    );
}
