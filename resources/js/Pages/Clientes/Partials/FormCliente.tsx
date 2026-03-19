import { useRef, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Loader2, Search } from 'lucide-react';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';

const TIPOS_DOC = [
    { value: 'DNI', label: 'DNI' },
    { value: 'RUC', label: 'RUC' },
    { value: 'CE',  label: 'Carné de extranjería' },
];

// Reglas por tipo de documento
const DOC_CONFIG: Record<string, { maxLength: number; soloNumeros: boolean; placeholder: string }> = {
    DNI: { maxLength: 8,  soloNumeros: true,  placeholder: '8 dígitos' },
    RUC: { maxLength: 11, soloNumeros: true,  placeholder: '11 dígitos' },
    CE:  { maxLength: 12, soloNumeros: false, placeholder: 'Hasta 12 caracteres' },
};

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
    const [fromApi, setFromApi]         = useState(false);
    const abortRef = useRef<AbortController | null>(null);

    function handleTipoChange(tipo: string) {
        setFromApi(false);
        setForm(f => ({
            ...f,
            tipo_documento:   tipo,
            numero_documento: '',
            nombres:          '',
            apellidos:        '',
            razon_social:     '',
        }));
    }

    function handleDocumentoChange(valor: string) {
        // Si el usuario borra el documento, se desbloquean los campos
        if (!valor) {
            setFromApi(false);
            setForm(f => ({
                ...f,
                numero_documento: '',
                nombres:          '',
                apellidos:        '',
                razon_social:     '',
            }));
            return;
        }

        const cfg = DOC_CONFIG[form.tipo_documento];
        // Filtrar solo números si aplica
        const sanitizado = cfg?.soloNumeros ? valor.replace(/\D/g, '') : valor;
        setForm(f => ({ ...f, numero_documento: sanitizado }));
    }

    async function consultarDocumento() {
        const valor = form.numero_documento;
        const esDni = form.tipo_documento === 'DNI' && valor.length === 8;
        const esRuc = form.tipo_documento === 'RUC' && valor.length === 11;

        if (!esDni && !esRuc) {
            toast.error(
                form.tipo_documento === 'DNI'
                    ? 'El DNI debe tener 8 dígitos'
                    : 'El RUC debe tener 11 dígitos'
            );
            return;
        }

        abortRef.current?.abort();
        abortRef.current = new AbortController();
        setConsultando(true);

        try {
            if (esDni) {
                const { data } = await axios.post('/api/decolecta/dni', { dni: valor }, { signal: abortRef.current.signal });
                setForm(f => ({ ...f, nombres: data.nombres, apellidos: data.apellidos }));
                setFromApi(true);
                toast.success('Datos obtenidos de RENIEC');
            } else {
                const { data } = await axios.post('/api/decolecta/ruc', { ruc: valor }, { signal: abortRef.current.signal });
                setForm(f => ({ ...f, razon_social: data.razon_social, direccion: data.direccion }));
                setFromApi(true);
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

    const esRuc      = form.tipo_documento === 'RUC';
    const usaApi     = form.tipo_documento === 'DNI' || esRuc;
    const docCfg     = DOC_CONFIG[form.tipo_documento] ?? { maxLength: 20, soloNumeros: false, placeholder: '' };
    const hoy        = new Date().toISOString().split('T')[0];

    const hintBloqueado = esRuc
        ? 'Eliminar el RUC para editar'
        : 'Eliminar el DNI para editar';

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

            {/* Documento + botón lupa */}
            <div className="flex gap-2 items-end">
                <div className="flex-1">
                    <Input
                        label="Número de documento"
                        value={form.numero_documento}
                        onChange={e => handleDocumentoChange(e.target.value)}
                        maxLength={docCfg.maxLength}
                        inputMode={docCfg.soloNumeros ? 'numeric' : 'text'}
                        disabled={disabled || consultando}
                        error={errors.numero_documento}
                        placeholder={esRuc ? '20xxxxxxxxx' : docCfg.placeholder}
                    />
                </div>

                {usaApi && (
                    <button
                        type="button"
                        onClick={consultarDocumento}
                        disabled={disabled || consultando || !form.numero_documento}
                        className="flex items-center justify-center rounded-xl border-2 px-3 py-2.5 transition-all duration-200
                            hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                        style={{
                            borderColor:     'var(--color-primary)',
                            color:           'var(--color-primary)',
                            backgroundColor: 'transparent',
                            // alinea con el input (compensa el label encima)
                            marginBottom: errors.numero_documento ? '1.25rem' : '0',
                        }}
                        title="Consultar en RENIEC / SUNAT"
                    >
                        {consultando
                            ? <Loader2 size={16} className="animate-spin" />
                            : <Search size={16} />
                        }
                    </button>
                )}
            </div>

            {!esRuc ? (
                <>
                    <Input
                        label="Nombres"
                        required={form.tipo_documento === 'DNI'}
                        value={form.nombres}
                        onChange={e => setForm(f => ({ ...f, nombres: e.target.value }))}
                        disabled={disabled || (usaApi && fromApi)}
                        error={errors.nombres}
                        hint={usaApi && fromApi ? hintBloqueado : undefined}
                    />
                    <Input
                        label="Apellidos"
                        value={form.apellidos}
                        onChange={e => setForm(f => ({ ...f, apellidos: e.target.value }))}
                        disabled={disabled || (usaApi && fromApi)}
                        error={errors.apellidos}
                        hint={usaApi && fromApi ? hintBloqueado : undefined}
                    />
                </>
            ) : (
                <Input
                    label="Razón social"
                    required
                    value={form.razon_social}
                    onChange={e => setForm(f => ({ ...f, razon_social: e.target.value }))}
                    disabled={disabled || (usaApi && fromApi)}
                    error={errors.razon_social}
                    hint={usaApi && fromApi ? hintBloqueado : undefined}
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
                disabled={disabled}
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
