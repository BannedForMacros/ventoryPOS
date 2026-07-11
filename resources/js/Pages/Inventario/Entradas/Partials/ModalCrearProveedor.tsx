import { useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import FormProveedor, { ProveedorForm, emptyProveedor } from '@/Pages/Proveedores/Partials/FormProveedor';

// Forma mínima del proveedor que necesita el selector de la entrada.
export interface ProveedorLite {
    id: number;
    razon_social: string | null;
    nombre_comercial: string | null;
    numero_documento: string | null;
    tipo_documento: string;
}

interface Props {
    isOpen:    boolean;
    onClose:   () => void;
    // Se invoca con el proveedor recién creado para seleccionarlo en la entrada.
    onCreated: (proveedor: ProveedorLite) => void;
}

/**
 * Alta de proveedor sin salir de la pantalla de entrada. Reutiliza el mismo
 * FormProveedor (con consulta RENIEC/SUNAT por DNI/RUC) y postea a proveedores.store
 * pidiendo JSON: el backend responde el modelo creado (ProveedorController@store
 * con wantsJson), así NO se recarga la página ni se pierde lo que se está cargando.
 * Sirve igual para persona natural (DNI) y jurídica (RUC): la única diferencia es
 * el tipo de documento; el nombre siempre va en razon_social.
 */
export default function ModalCrearProveedor({ isOpen, onClose, onCreated }: Props) {
    const [form, setForm]     = useState<ProveedorForm>(emptyProveedor());
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    function cerrar() {
        setErrors({});
        onClose();
    }

    async function submit(e?: React.FormEvent) {
        e?.preventDefault();
        if (saving) return;
        setSaving(true);
        setErrors({});

        try {
            const { data } = await axios.post(route('proveedores.store'), form, {
                headers: { Accept: 'application/json' },
            });
            setSaving(false);
            const nombre = data.razon_social ?? data.nombre_comercial ?? 'Proveedor';
            toast.success(`Proveedor "${nombre}" creado y seleccionado.`);
            setForm(emptyProveedor());
            setErrors({});
            onCreated(data as ProveedorLite);
        } catch (err: any) {
            setSaving(false);
            if (err?.response?.status === 422) {
                // Errores de validación de Laravel → primer mensaje por campo.
                const raw = err.response.data?.errors ?? {};
                const flat: Record<string, string> = {};
                Object.keys(raw).forEach(k => { flat[k] = Array.isArray(raw[k]) ? raw[k][0] : String(raw[k]); });
                setErrors(flat);
            } else {
                toast.error('No se pudo crear el proveedor. Intenta de nuevo.');
            }
        }
    }

    return (
        <Modal
            isOpen={isOpen}
            onClose={cerrar}
            title="Nuevo proveedor"
            size="md"
            footer={
                <>
                    <Button variant="ghost" onClick={cerrar} disabled={saving}>Cancelar</Button>
                    <Button loading={saving} onClick={() => submit()}>Guardar y seleccionar</Button>
                </>
            }
        >
            <form onSubmit={submit}>
                <FormProveedor
                    form={form}
                    setForm={setForm}
                    errors={errors as any}
                    disabled={saving}
                />
            </form>
        </Modal>
    );
}
