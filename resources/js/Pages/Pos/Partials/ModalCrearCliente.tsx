import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import FormCliente, { ClienteForm, emptyCliente } from '@/Pages/Clientes/Partials/FormCliente';
import type { Cliente } from '@/types';

interface Props {
    isOpen:    boolean;
    onClose:   () => void;
    // Se invoca con el cliente recién creado para seleccionarlo automáticamente
    // en la venta en curso. El POS no recarga toda la lista de clientes, por lo
    // que el modal la consulta directamente tras el alta.
    onCreated: (cliente: Cliente) => void;
}

/**
 * Alta de cliente sin salir del POS. Reutiliza el mismo FormCliente del módulo
 * de Clientes (incluida la consulta RENIEC/SUNAT por DNI/RUC) y postea a la
 * ruta clientes.store existente. Tras el alta se busca el nuevo cliente por su
 * documento para seleccionarlo automáticamente.
 */
export default function ModalCrearCliente({ isOpen, onClose, onCreated }: Props) {
    const [form, setForm]     = useState<ClienteForm>(emptyCliente());
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    function cerrar() {
        setErrors({});
        onClose();
    }

    async function buscarCreado() {
        const doc = form.numero_documento?.trim();
        try {
            const params: Record<string, any> = doc ? { q: doc } : {};
            const { data } = await axios.get<{ clientes: Cliente[] }>(route('pos.clientes'), { params });
            let nuevo: Cliente | undefined;
            if (doc) {
                nuevo = data.clientes.find(c => c.numero_documento === doc);
            }
            // Fallback: si no hay documento, el primero de la lista (el backend
            // devuelve Cliente General primero, así que evitamos autoseleccionarlo).
            if (!nuevo && data.clientes.length > 0) {
                nuevo = data.clientes.find(c => c.numero_documento !== '99999999');
            }
            if (nuevo) {
                onCreated(nuevo);
            } else {
                onClose();
            }
        } catch (e) {
            // Si la búsqueda falla, al menos cerramos el modal limpiamente.
            onClose();
        }
    }

    function submit(e?: React.FormEvent) {
        e?.preventDefault();
        if (saving) return;
        setSaving(true);
        setErrors({});

        router.post(route('clientes.store'), form as any, {
            preserveScroll: true,
            onSuccess: () => {
                setSaving(false);
                setForm(emptyCliente());
                setErrors({});
                // Solo autoseleccionar si quedó activo (el POS lista solo activos).
                if (form.activo) {
                    buscarCreado();
                } else {
                    onClose();
                }
            },
            onError: errs => {
                setSaving(false);
                setErrors(errs as Record<string, string>);
            },
        });
    }

    return (
        <Modal
            isOpen={isOpen}
            onClose={cerrar}
            title="Nuevo cliente"
            size="md"
            footer={
                <>
                    <Button variant="ghost" onClick={cerrar} disabled={saving}>
                        Cancelar
                    </Button>
                    <Button loading={saving} onClick={() => submit()}>
                        Guardar y seleccionar
                    </Button>
                </>
            }
        >
            <form onSubmit={submit}>
                <FormCliente
                    form={form}
                    setForm={setForm}
                    errors={errors as any}
                    disabled={saving}
                />
            </form>
        </Modal>
    );
}
