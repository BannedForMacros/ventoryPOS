import { useRef, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Loader2 } from 'lucide-react';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';

const TIPOS_DOC = [
    { value: 'DNI',       label: 'DNI' },
    { value: 'RUC',       label: 'RUC' },
    { value: 'CE',        label: 'Carné de extranjería' },
    { value: 'pasaporte', label: 'Pasaporte' },
    { value: 'otro',      label: 'Otro' },
];

export interface ClienteForm {
    tipo_documento:   string;
    numero_documento: string;
    nombres:          string;
    apellidos:        string;
    razon_social:     string;
    telefono:         string;
    email:            string;
    direccion:        string;
    fecha_nacimiento: string;
    activo:           boolean;
}

interface Props {
    form:      ClienteForm;
    setForm:   (fn: (prev: ClienteForm) => ClienteForm) => void;
    errors:    Partial<Record<keyof ClienteForm, string>>;
    disabled?: boolean;
}

export const emptyCliente = (): ClienteForm => ({
    tipo_documento:   'DNI',
    numero_documento: '',
    nombres:          '',
    apellidos:        '',
    razon_social:     '',
    telefono:         '',
    email:            '',
    direccion:        '',
    fecha_nacimiento: '',
    activo:           true,
});

export default function FormCliente({ form, setForm, errors, disabled }: Props) {
    const [consultando, setConsultando] = useState(false);
    const abortRef = useRef<AbortController | null>(null);

    function handleTipoChange(tipo: string) {
        setForm(f => ({
            ...f,
            tipo_documento:   tipo,
            numero_documento: '',
            nombres:          '',
            apellidos:        '',
            razon_social:     '',
        }));
    }

    async function consultarDocumento(valor: string) {
        const esDni = form.tipo_documento === 'DNI' && valor.length === 8;
        const esRuc = form.tipo_documento === 'RUC' && valor.length === 11;

        if (!esDni && !esRuc) return;

        abortRef.current?.abort();
        abortRef.current = new AbortController();

        setConsultando(true);

        try {
            if (esDni) {
                const { data } = await axios.post('/api/decolecta/dni', { dni: valor }, { signal: abortRef.current.signal });
                setForm(f => ({ ...f, nombres: data.nombres, apellidos: data.apellidos }));
                toast.success('Datos obtenidos de RENIEC');
            } else {
                const { data } = await axios.post('/api/decolecta/ruc', { ruc: valor }, { signal: abortRef.current.signal });
                setForm(f => ({ ...f, razon_social: data.razon_social, direccion: data.direccion }));
                toast.success('Datos obtenidos de SUNAT');
            }
        } catch (err: any) {
            if (axios.isCancel(err)) return;
            const msg = err.response?.data?.message ?? 'No se pudo consultar, ingresa los datos manualmente';
            toast.error(msg);
        } finally {
            setConsultando(false);
        }
    }

    function handleDocumentoChange(valor: string) {
        setForm(f => ({ ...f, numero_documento: valor }));
        consultarDocumento(valor);
    }

    const esRuc   = form.tipo_documento === 'RUC';
    const usaApi  = form.tipo_documento === 'DNI' || esRuc;
    const maxDoc  = esRuc ? 11 : form.tipo_documento === 'DNI' ? 8 : 20;
    const hoy     = new Date().toISOString().split('T')[0];

    return (
        <div className="space-y-4">
            <Select
                label="Tipo de documento"
                required
                value={form.tipo_documento}
                onChange={v => handleTipoChange(String(v))}
                options={TIPOS_DOC}
                disabled={disabled}
            />

            <div className="relative">
                <Input
                    label="Número de documento"
                    value={form.numero_documento}
                    onChange={e => handleDocumentoChange(e.target.value)}
                    maxLength={maxDoc}
                    disabled={disabled || consultando}
                    error={errors.numero_documento}
                    placeholder={esRuc ? '20xxxxxxxxx' : form.tipo_documento === 'DNI' ? '8 dígitos' : ''}
                />
                {consultando && (
                    <div className="absolute right-3 top-8">
                        <Loader2 size={16} className="animate-spin" style={{ color: 'var(--color-primary)' }} />
                    </div>
                )}
            </div>

            {!esRuc ? (
                <>
                    <Input
                        label="Nombres"
                        required={form.tipo_documento === 'DNI'}
                        value={form.nombres}
                        onChange={e => setForm(f => ({ ...f, nombres: e.target.value }))}
                        disabled={disabled || (usaApi && consultando)}
                        error={errors.nombres}
                    />
                    <Input
                        label="Apellidos"
                        value={form.apellidos}
                        onChange={e => setForm(f => ({ ...f, apellidos: e.target.value }))}
                        disabled={disabled || (usaApi && consultando)}
                        error={errors.apellidos}
                    />
                </>
            ) : (
                <Input
                    label="Razón social"
                    required
                    value={form.razon_social}
                    onChange={e => setForm(f => ({ ...f, razon_social: e.target.value }))}
                    disabled={disabled || consultando}
                    error={errors.razon_social}
                />
            )}

            <Input
                label="Teléfono"
                value={form.telefono}
                onChange={e => setForm(f => ({ ...f, telefono: e.target.value }))}
                disabled={disabled}
                error={errors.telefono}
            />

            <Input
                label="Email"
                type="email"
                value={form.email}
                onChange={e => setForm(f => ({ ...f, email: e.target.value }))}
                disabled={disabled}
                error={errors.email}
            />

            <Input
                label="Dirección"
                value={form.direccion}
                onChange={e => setForm(f => ({ ...f, direccion: e.target.value }))}
                disabled={disabled || (esRuc && consultando)}
                error={errors.direccion}
            />

            {!esRuc && (
                <Input
                    label="Fecha de nacimiento"
                    type="date"
                    value={form.fecha_nacimiento}
                    onChange={e => setForm(f => ({ ...f, fecha_nacimiento: e.target.value }))}
                    max={hoy}
                    disabled={disabled}
                    error={errors.fecha_nacimiento}
                />
            )}

            <Switch
                label="Activo"
                checked={form.activo}
                onChange={e => setForm(f => ({ ...f, activo: (e.target as HTMLInputElement).checked }))}
                disabled={disabled}
            />
        </div>
    );
}
