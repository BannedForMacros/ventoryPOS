import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { router, useForm, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Loader2, Search, Users } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import Badge from '@/Components/UI/Badge';
import Input from '@/Components/UI/Input';
import Checkbox from '@/Components/UI/Checkbox';
import TableActions from '@/Components/UI/TableActions';
import type { Empresa, PageProps } from '@/types';

type EmpresaFila = Empresa & {
    users_count: number;
    locales_count: number;
};

interface Props extends PageProps {
    empresas: EmpresaFila[];
}

type CrearForm = {
    razon_social: string;
    nombre_comercial: string;
    ruc: string;
    direccion: string;
    telefono: string;
    email: string;
    admin_name: string;
    admin_email: string;
    admin_password: string;
};

type EditarForm = {
    razon_social: string;
    nombre_comercial: string;
    ruc: string;
    direccion: string;
    telefono: string;
    email: string;
    activo: boolean;
};

const emptyCrear: CrearForm = {
    razon_social: '',
    nombre_comercial: '',
    ruc: '',
    direccion: '',
    telefono: '',
    email: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
};

export default function Empresas({ empresas }: Props) {
    const { flash } = usePage<Props>().props;
    const [crearOpen, setCrearOpen] = useState(false);
    const [editing, setEditing] = useState<EmpresaFila | null>(null);

    const crear = useForm<CrearForm>(emptyCrear);
    const editar = useForm<EditarForm>({
        razon_social: '',
        nombre_comercial: '',
        ruc: '',
        direccion: '',
        telefono: '',
        email: '',
        activo: true,
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // Lookup SUNAT vía Decolecta: rellena razón social y dirección.
    // Sirve para crear y editar, por eso recibe el form destino.
    const [consultandoRuc, setConsultandoRuc] = useState(false);
    const abortRef = useRef<AbortController | null>(null);

    async function buscarRuc(form: typeof crear | typeof editar) {
        const ruc = form.data.ruc;
        if (ruc.length !== 11) {
            toast.error('El RUC debe tener 11 dígitos');
            return;
        }

        abortRef.current?.abort();
        abortRef.current = new AbortController();
        setConsultandoRuc(true);

        try {
            const { data } = await axios.post('/api/decolecta/ruc', { ruc }, { signal: abortRef.current.signal });
            // Ambos forms comparten estos campos; TS no llama sobre la unión.
            const setData = form.setData as (key: 'razon_social' | 'direccion', value: string) => void;
            if (data.razon_social) setData('razon_social', data.razon_social);
            if (data.direccion) setData('direccion', data.direccion);
            toast.success('Datos obtenidos de SUNAT');
        } catch (e: unknown) {
            if (axios.isCancel(e)) return;
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al consultar') : 'Error al consultar';
            toast.error(msg);
        } finally {
            setConsultandoRuc(false);
        }
    }

    function botonBuscarRuc(form: typeof crear | typeof editar) {
        const deshabilitado = consultandoRuc || form.data.ruc.length !== 11;
        return (
            <button
                type="button"
                onClick={() => buscarRuc(form)}
                disabled={deshabilitado}
                className="rounded-xl px-3 py-2 text-sm font-medium transition-colors flex items-center gap-1 shrink-0"
                style={{
                    color: 'var(--color-on-primary)',
                    backgroundColor: 'var(--color-primary)',
                    opacity: deshabilitado ? 0.6 : 1,
                }}
            >
                {consultandoRuc
                    ? <><Loader2 size={14} className="animate-spin" />Consultando</>
                    : <><Search size={14} />Buscar</>}
            </button>
        );
    }

    function openCrear() {
        crear.reset();
        setCrearOpen(true);
    }

    function openEditar(e: EmpresaFila) {
        editar.setData({
            razon_social: e.razon_social,
            nombre_comercial: e.nombre_comercial ?? '',
            ruc: e.ruc,
            direccion: e.direccion ?? '',
            telefono: e.telefono ?? '',
            email: e.email ?? '',
            activo: e.activo,
        });
        setEditing(e);
    }

    function submitCrear(e: React.FormEvent) {
        e.preventDefault();
        crear.post(route('admin.empresas.store'), {
            onSuccess: () => { setCrearOpen(false); crear.reset(); },
        });
    }

    function submitEditar(e: React.FormEvent) {
        e.preventDefault();
        if (!editing) return;
        editar.put(route('admin.empresas.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    }

    const columns: Column<EmpresaFila>[] = [
        {
            key: 'razon_social',
            label: 'Empresa',
            sortable: true,
            render: (e) => (
                <div>
                    <p className="font-medium">{e.nombre_comercial ?? e.razon_social}</p>
                    {e.nombre_comercial && (
                        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{e.razon_social}</p>
                    )}
                </div>
            ),
        },
        {
            key: 'ruc',
            label: 'RUC',
            sortable: true,
            render: (e) => <span className="font-mono text-xs">{e.ruc}</span>,
        },
        {
            key: 'users_count',
            label: 'Usuarios',
            sortable: true,
            render: (e) => e.users_count,
        },
        {
            key: 'activo',
            label: 'Estado',
            sortable: true,
            render: (e) => (
                <Badge variant={e.activo ? 'success' : 'secondary'}>
                    {e.activo ? 'Activa' : 'Inactiva'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            sortable: false,
            render: (e) => (
                <div className="flex items-center gap-2">
                    <Button
                        size="xs"
                        flat
                        startContent={<Users size={13} />}
                        onClick={() => router.visit(route('admin.empresas.usuarios', e.id))}
                    >
                        Usuarios
                    </Button>
                    <TableActions onEdit={() => openEditar(e)} />
                </div>
            ),
        },
    ];

    return (
        <AdminLayout title="Empresas">
            <PageHeader
                title="Empresas"
                subtitle="Alta y gestión de todas las empresas del sistema"
                actions={<Button onClick={openCrear}>+ Nueva Empresa</Button>}
            />

            <Table
                data={empresas}
                columns={columns}
                searchPlaceholder="Buscar empresa..."
                emptyMessage="No hay empresas registradas"
            />

            {/* Crear: empresa + su primer administrador */}
            <Modal
                isOpen={crearOpen}
                onClose={() => setCrearOpen(false)}
                title="Nueva Empresa"
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setCrearOpen(false)}>Cancelar</Button>
                        <Button loading={crear.processing} onClick={submitCrear}>Crear empresa</Button>
                    </>
                }
            >
                <form onSubmit={submitCrear} className="space-y-4">
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        Se crea lista para operar: local, caja, almacén, roles (Administrador y Cajera),
                        métodos de pago, cuenta de efectivo, unidad base y Cliente General.
                    </p>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Razón social"
                            required
                            value={crear.data.razon_social}
                            onChange={e => crear.setData('razon_social', e.target.value)}
                            error={crear.errors.razon_social}
                        />
                        <Input
                            label="Nombre comercial"
                            value={crear.data.nombre_comercial}
                            onChange={e => crear.setData('nombre_comercial', e.target.value)}
                            error={crear.errors.nombre_comercial}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex gap-2 items-end">
                            <div className="flex-1">
                                <Input
                                    label="RUC"
                                    required
                                    maxLength={11}
                                    value={crear.data.ruc}
                                    onChange={e => crear.setData('ruc', e.target.value.replace(/\D/g, ''))}
                                    onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); buscarRuc(crear); } }}
                                    error={crear.errors.ruc}
                                />
                            </div>
                            {botonBuscarRuc(crear)}
                        </div>
                        <Input
                            label="Teléfono"
                            value={crear.data.telefono}
                            onChange={e => crear.setData('telefono', e.target.value)}
                            error={crear.errors.telefono}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Dirección"
                            value={crear.data.direccion}
                            onChange={e => crear.setData('direccion', e.target.value)}
                            error={crear.errors.direccion}
                        />
                        <Input
                            label="Email de la empresa"
                            type="email"
                            value={crear.data.email}
                            onChange={e => crear.setData('email', e.target.value)}
                            error={crear.errors.email}
                        />
                    </div>

                    <div className="border-t pt-4" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="mb-3 text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                            Administrador de la empresa
                        </p>
                        <div className="grid grid-cols-2 gap-4">
                            <Input
                                label="Nombre"
                                required
                                value={crear.data.admin_name}
                                onChange={e => crear.setData('admin_name', e.target.value)}
                                error={crear.errors.admin_name}
                            />
                            <Input
                                label="Email"
                                type="email"
                                required
                                value={crear.data.admin_email}
                                onChange={e => crear.setData('admin_email', e.target.value)}
                                error={crear.errors.admin_email}
                            />
                        </div>
                        <div className="mt-4">
                            <Input
                                label="Contraseña"
                                type="password"
                                required
                                value={crear.data.admin_password}
                                onChange={e => crear.setData('admin_password', e.target.value)}
                                error={crear.errors.admin_password}
                            />
                        </div>
                    </div>
                </form>
            </Modal>

            {/* Editar datos básicos + activar/desactivar */}
            <Modal
                isOpen={editing !== null}
                onClose={() => setEditing(null)}
                title="Editar Empresa"
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditing(null)}>Cancelar</Button>
                        <Button loading={editar.processing} onClick={submitEditar}>Guardar cambios</Button>
                    </>
                }
            >
                <form onSubmit={submitEditar} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Razón social"
                            required
                            value={editar.data.razon_social}
                            onChange={e => editar.setData('razon_social', e.target.value)}
                            error={editar.errors.razon_social}
                        />
                        <Input
                            label="Nombre comercial"
                            value={editar.data.nombre_comercial}
                            onChange={e => editar.setData('nombre_comercial', e.target.value)}
                            error={editar.errors.nombre_comercial}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex gap-2 items-end">
                            <div className="flex-1">
                                <Input
                                    label="RUC"
                                    required
                                    maxLength={11}
                                    value={editar.data.ruc}
                                    onChange={e => editar.setData('ruc', e.target.value.replace(/\D/g, ''))}
                                    onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); buscarRuc(editar); } }}
                                    error={editar.errors.ruc}
                                />
                            </div>
                            {botonBuscarRuc(editar)}
                        </div>
                        <Input
                            label="Teléfono"
                            value={editar.data.telefono}
                            onChange={e => editar.setData('telefono', e.target.value)}
                            error={editar.errors.telefono}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Dirección"
                            value={editar.data.direccion}
                            onChange={e => editar.setData('direccion', e.target.value)}
                            error={editar.errors.direccion}
                        />
                        <Input
                            label="Email de la empresa"
                            type="email"
                            value={editar.data.email}
                            onChange={e => editar.setData('email', e.target.value)}
                            error={editar.errors.email}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                        <Checkbox
                            name="activo"
                            checked={editar.data.activo}
                            onChange={e => editar.setData('activo', e.target.checked)}
                        />
                        Empresa activa
                    </label>
                    <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                        La configuración fina (almacén, cierres, devoluciones, ticket, facturación) la
                        administra el propio administrador de la empresa desde su panel.
                    </p>
                </form>
            </Modal>
        </AdminLayout>
    );
}
