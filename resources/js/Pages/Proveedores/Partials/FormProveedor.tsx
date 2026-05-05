import { useRef, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Loader2, Search } from 'lucide-react';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';

const TIPOS_DOC = [
    { value: 'RUC', label: 'RUC' },
    { value: 'DNI', label: 'DNI' },
    { value: 'CE',  label: 'Carné de extranjería' },
    { value: 'otro', label: 'Otro' },
];

const DOC_CONFIG: Record<string, { maxLength: number; soloNumeros: boolean; placeholder: string }> = {
    RUC:  { maxLength: 11, soloNumeros: true,  placeholder: '11 dígitos' },
    DNI:  { maxLength: 8,  soloNumeros: true,  placeholder: '8 dígitos' },
    CE:   { maxLength: 12, soloNumeros: false, placeholder: 'Hasta 12 caracteres' },
    otro: { maxLength: 20, soloNumeros: false, placeholder: 'Documento' },
};

export interface ProveedorForm {
    tipo_documento:   string;
    numero_documento: string;
    razon_social:     string;
    nombre_comercial: string;
    contacto:         string;
    telefono:         string;
    email:            string;
    direccion:        string;
    observacion:      string;
    activo:           boolean;
}

interface Props {
    form:      ProveedorForm;
    setForm:   (fn: (prev: ProveedorForm) => ProveedorForm) => void;
    errors:    Partial<Record<keyof ProveedorForm, string>>;
    disabled?: boolean;
}

export const emptyProveedor = (): ProveedorForm => ({
    tipo_documento:   'RUC',
    numero_documento: '',
    razon_social:     '',
    nombre_comercial: '',
    contacto:         '',
    telefono:         '',
    email:            '',
    direccion:        '',
    observacion:      '',
    activo:           true,
});

export default function FormProveedor({ form, setForm, errors, disabled }: Props) {
    const [consultando, setConsultando] = useState(false);
    const abortRef = useRef<AbortController | null>(null);

    function handleTipoChange(tipo: string) {
        setForm(f => ({
            ...f,
            tipo_documento:   tipo,
            numero_documento: '',
            razon_social:     '',
            nombre_comercial: '',
        }));
    }

    function handleDocumentoChange(valor: string) {
        if (!valor) {
            setForm(f => ({
                ...f,
                numero_documento: '',
                razon_social:     '',
            }));
            return;
        }
        const cfg = DOC_CONFIG[form.tipo_documento];
        const sanitizado = cfg?.soloNumeros ? valor.replace(/\D/g, '') : valor;
        setForm(f => ({ ...f, numero_documento: sanitizado }));
    }

    async function consultarDocumento() {
        const valor = form.numero_documento;
        const esRuc = form.tipo_documento === 'RUC' && valor.length === 11;
        const esDni = form.tipo_documento === 'DNI' && valor.length === 8;

        if (!esRuc && !esDni) {
            toast.error(form.tipo_documento === 'RUC' ? 'El RUC debe tener 11 dígitos' : 'El DNI debe tener 8 dígitos');
            return;
        }

        abortRef.current?.abort();
        abortRef.current = new AbortController();
        setConsultando(true);

        try {
            if (esRuc) {
                const { data } = await axios.post('/api/decolecta/ruc', { ruc: valor }, { signal: abortRef.current.signal });
                setForm(f => ({ ...f, razon_social: data.razon_social, direccion: data.direccion ?? f.direccion }));
                toast.success('Datos obtenidos de SUNAT');
            } else {
                const { data } = await axios.post('/api/decolecta/dni', { dni: valor }, { signal: abortRef.current.signal });
                const nombreCompleto = `${data.nombres ?? ''} ${data.apellidos ?? ''}`.trim();
                setForm(f => ({ ...f, razon_social: nombreCompleto }));
                toast.success('Datos obtenidos de RENIEC');
            }
        } catch (e: unknown) {
            const isAxios = axios.isAxiosError(e);
            const msg = isAxios ? (e.response?.data?.message ?? 'Error al consultar') : 'Error al consultar';
            toast.error(msg);
        } finally {
            setConsultando(false);
        }
    }

    const cfg = DOC_CONFIG[form.tipo_documento];

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3">
                <Select
                    label="Tipo doc."
                    value={form.tipo_documento}
                    onChange={(v) => handleTipoChange(String(v))}
                    options={TIPOS_DOC}
                    disabled={disabled}
                />
                <div className="col-span-2 flex gap-2 items-end">
                    <div className="flex-1">
                        <Input
                            label="Número de documento"
                            value={form.numero_documento}
                            onChange={(e) => handleDocumentoChange(e.target.value)}
                            placeholder={cfg?.placeholder ?? ''}
                            maxLength={cfg?.maxLength}
                            error={errors.numero_documento}
                            disabled={disabled}
                        />
                    </div>
                    {(form.tipo_documento === 'RUC' || form.tipo_documento === 'DNI') && (
                        <button
                            type="button"
                            onClick={consultarDocumento}
                            disabled={disabled || consultando}
                            className="rounded-xl px-3 py-2 text-sm font-medium transition-colors flex items-center gap-1"
                            style={{
                                color: 'var(--color-on-primary)',
                                backgroundColor: 'var(--color-primary)',
                                opacity: (disabled || consultando) ? 0.6 : 1,
                            }}
                        >
                            {consultando
                                ? <><Loader2 size={14} className="animate-spin" />Consultando</>
                                : <><Search size={14} />Buscar</>}
                        </button>
                    )}
                </div>
            </div>

            <Input
                label="Razón social"
                value={form.razon_social}
                onChange={(e) => setForm(f => ({ ...f, razon_social: e.target.value }))}
                error={errors.razon_social}
                disabled={disabled}
            />

            <Input
                label="Nombre comercial"
                value={form.nombre_comercial}
                onChange={(e) => setForm(f => ({ ...f, nombre_comercial: e.target.value }))}
                error={errors.nombre_comercial}
                disabled={disabled}
            />

            <div className="grid grid-cols-2 gap-3">
                <Input
                    label="Contacto"
                    placeholder="Persona de contacto"
                    value={form.contacto}
                    onChange={(e) => setForm(f => ({ ...f, contacto: e.target.value }))}
                    error={errors.contacto}
                    disabled={disabled}
                />
                <Input
                    label="Teléfono"
                    value={form.telefono}
                    onChange={(e) => setForm(f => ({ ...f, telefono: e.target.value }))}
                    error={errors.telefono}
                    disabled={disabled}
                />
            </div>

            <div className="grid grid-cols-2 gap-3">
                <Input
                    label="Email"
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm(f => ({ ...f, email: e.target.value }))}
                    error={errors.email}
                    disabled={disabled}
                />
                <Input
                    label="Dirección"
                    value={form.direccion}
                    onChange={(e) => setForm(f => ({ ...f, direccion: e.target.value }))}
                    error={errors.direccion}
                    disabled={disabled}
                />
            </div>

            <div>
                <label className="text-sm font-medium block mb-1" style={{ color: 'var(--color-text)' }}>Observación</label>
                <textarea
                    rows={2}
                    value={form.observacion}
                    onChange={(e) => setForm(f => ({ ...f, observacion: e.target.value }))}
                    className="w-full rounded-xl border px-3 py-2 text-sm outline-none resize-none"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                    disabled={disabled}
                />
            </div>

            <Switch
                label="Activo"
                checked={form.activo}
                onChange={(v) => setForm(f => ({ ...f, activo: v }))}
                disabled={disabled}
            />
        </div>
    );
}
