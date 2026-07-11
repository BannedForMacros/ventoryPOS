import { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/UI/Modal';
import Button from '@/Components/UI/Button';
import FormCliente, { ClienteForm, emptyCliente } from '@/Pages/Clientes/Partials/FormCliente';
import type { Cliente } from '@/types';

interface Props {
    isOpen:    boolean;
    onClose:   () => void;
    // Se invoca con el cliente recién creado (ya refrescado en los props de la
    // página) para seleccionarlo automáticamente en la venta en curso.
    onCreated: (cliente: Cliente) => void;
}

/**
 * Alta de cliente sin salir del POS. Reutiliza el mismo FormCliente del módulo
 * de Clientes (incluida la consulta RENIEC/SUNAT por DNI/RUC) y postea a la
 * ruta clientes.store existente. Como el backend responde redirect()->back(),
 * Inertia recarga los props del POS (la lista `clientes` llega actualizada)
 * sin perder el carrito (preserveState es el default en POST).
 */
export default function ModalCrearCliente({ isOpen, onClose, onCreated }: Props) {
    const [form, setForm]     = useState<ClienteForm>(emptyCliente());
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    function cerrar() {
        setErrors({});
        onClose();
    }

    function submit(e?: React.FormEvent) {
        e?.preventDefault();
        if (saving) return;
        setSaving(true);
        setErrors({});

        router.post(route('clientes.store'), form as any, {
            preserveScroll: true,
            onSuccess: page => {
                setSaving(false);
                // Ubicar el cliente recién creado en la lista refrescada del POS:
                // por documento si se ingresó; si no, el de id más alto (último creado).
                const lista = (page.props as any).clientes as Cliente[] | undefined;
                let nuevo: Cliente | undefined;
                if (Array.isArray(lista) && lista.length > 0) {
                    if (form.numero_documento) {
                        nuevo = lista.find(c => c.numero_documento === form.numero_documento);
                    }
                    if (!nuevo) {
                        nuevo = [...lista].sort((a, b) => (b.id as number) - (a.id as number))[0];
                    }
                }
                setForm(emptyCliente());
                setErrors({});
                // Solo autoseleccionar si quedó activo (el POS lista solo activos).
                if (nuevo && form.activo) {
                    onCreated(nuevo);
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
