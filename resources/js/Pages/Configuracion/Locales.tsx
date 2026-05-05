import { useEffect, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Table, { Column } from '@/Components/UI/Table';
import Modal from '@/Components/UI/Modal';
import Badge from '@/Components/UI/Badge';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Checkbox from '@/Components/UI/Checkbox';
import TableActions from '@/Components/UI/TableActions';
import type { Empresa, Local, ModoCierreCaja, ModoCierreInventario, PageProps } from '@/types';

interface Props extends PageProps {
    locales: Local[];
    empresas: Empresa[];
}

type TriBool = 'heredar' | 'si' | 'no';
type ModoCierreSel = 'heredar' | ModoCierreCaja;
type ModoInvSel    = 'heredar' | ModoCierreInventario;

type FormData = {
    empresa_id: string;
    nombre: string;
    direccion: string;
    telefono: string;
    tipo: string;
    activo: boolean;
    descuenta_stock_en_venta: TriBool;
    modo_cierre_caja: ModoCierreSel;
    modo_cierre_inventario: ModoInvSel;
    usa_fondos_iniciales: TriBool;
    fondos_iniciales_en_declaracion: TriBool;
};

const emptyForm: FormData = {
    empresa_id: '',
    nombre: '',
    direccion: '',
    telefono: '',
    tipo: '',
    activo: true,
    descuenta_stock_en_venta: 'heredar',
    modo_cierre_caja: 'heredar',
    modo_cierre_inventario: 'heredar',
    usa_fondos_iniciales: 'heredar',
    fondos_iniciales_en_declaracion: 'heredar',
};

const TIPO_OPTIONS = [
    { value: 'almacen', label: 'Almacén' },
    { value: 'tienda', label: 'Tienda' },
];

const MODO_CIERRE_LABELS: Record<ModoCierreCaja, string> = {
    rapido: 'Rápido',
    con_declaraciones: 'Con declaraciones',
};

const MODO_INV_LABELS: Record<ModoCierreInventario, string> = {
    por_venta: 'Por venta',
    declarado: 'Declarado',
};

function triFromBoolean(v: boolean | null): TriBool {
    if (v === null || v === undefined) return 'heredar';
    return v ? 'si' : 'no';
}

function triToPayload(v: TriBool): boolean | null {
    if (v === 'heredar') return null;
    return v === 'si';
}

export default function Locales({ locales, empresas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing] = useState<Local | null>(null);

    const { data, setData, transform, post, put, processing, errors, reset } = useForm<FormData>(emptyForm);

    transform(d => ({
        ...d,
        descuenta_stock_en_venta: triToPayload(d.descuenta_stock_en_venta),
        modo_cierre_caja: d.modo_cierre_caja === 'heredar' ? null : d.modo_cierre_caja,
        modo_cierre_inventario: d.modo_cierre_inventario === 'heredar' ? null : d.modo_cierre_inventario,
        usa_fondos_iniciales: triToPayload(d.usa_fondos_iniciales),
        fondos_iniciales_en_declaracion: triToPayload(d.fondos_iniciales_en_declaracion),
    }));

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    function openCreate() {
        setEditing(null);
        reset();
        setModalOpen(true);
    }

    function openEdit(local: Local) {
        setEditing(local);
        setData({
            empresa_id: String(local.empresa_id),
            nombre: local.nombre,
            direccion: local.direccion ?? '',
            telefono: local.telefono ?? '',
            tipo: local.tipo ?? '',
            activo: local.activo,
            descuenta_stock_en_venta: triFromBoolean(local.descuenta_stock_en_venta),
            modo_cierre_caja: local.modo_cierre_caja ?? 'heredar',
            modo_cierre_inventario: local.modo_cierre_inventario ?? 'heredar',
            usa_fondos_iniciales: triFromBoolean(local.usa_fondos_iniciales),
            fondos_iniciales_en_declaracion: triFromBoolean(local.fondos_iniciales_en_declaracion),
        });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('configuracion.locales.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('configuracion.locales.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.locales.destroy', id));
    }

    const empresaOptions = empresas.map(e => ({
        value: String(e.id),
        label: e.nombre_comercial ?? e.razon_social,
    }));

    const empresaSeleccionada = empresas.find(e => String(e.id) === data.empresa_id);
    const hintHerenciaStock = empresaSeleccionada
        ? `Hoy la empresa: ${empresaSeleccionada.descuenta_stock_en_venta ? 'sí descuenta' : 'no descuenta'}`
        : 'La empresa decide';
    const hintHerenciaCierre = empresaSeleccionada
        ? `Hoy la empresa: ${MODO_CIERRE_LABELS[empresaSeleccionada.modo_cierre_caja]}`
        : 'La empresa decide';
    const hintHerenciaInv = empresaSeleccionada
        ? `Hoy la empresa: ${MODO_INV_LABELS[empresaSeleccionada.modo_cierre_inventario]}`
        : 'La empresa decide';
    const hintHerenciaFondos = empresaSeleccionada
        ? `Hoy la empresa: ${empresaSeleccionada.usa_fondos_iniciales ? 'sí pide' : 'no pide'}`
        : 'La empresa decide';
    const hintHerenciaFondosDecl = empresaSeleccionada
        ? `Hoy la empresa: ${empresaSeleccionada.fondos_iniciales_en_declaracion ? 'sí incluye' : 'no incluye'}`
        : 'La empresa decide';

    const columns: Column<Local>[] = [
        {
            key: 'nombre',
            label: 'Nombre',
            sortable: true,
            render: (local) => <span className="font-medium">{local.nombre}</span>,
        },
        {
            key: 'empresa',
            label: 'Empresa',
            sortable: true,
            searchKey: 'empresa_id',
            render: (local) => local.empresa
                ? (local.empresa.nombre_comercial ?? local.empresa.razon_social)
                : '—',
        },
        {
            key: 'direccion',
            label: 'Dirección',
            render: (local) => local.direccion ?? '—',
        },
        {
            key: 'modo_cierre_caja',
            label: 'Cierre caja',
            render: (local) => local.modo_cierre_caja
                ? <Badge variant="primary">{MODO_CIERRE_LABELS[local.modo_cierre_caja]}</Badge>
                : <span style={{ color: 'var(--color-text-muted)' }}>Hereda</span>,
        },
        {
            key: 'modo_cierre_inventario',
            label: 'Cierre inventario',
            render: (local) => local.modo_cierre_inventario
                ? <Badge variant="primary">{MODO_INV_LABELS[local.modo_cierre_inventario]}</Badge>
                : <span style={{ color: 'var(--color-text-muted)' }}>Hereda</span>,
        },
        {
            key: 'activo',
            label: 'Estado',
            sortable: true,
            render: (local) => (
                <Badge variant={local.activo ? 'success' : 'secondary'}>
                    {local.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (local) => (
                <TableActions
                    onEdit={() => openEdit(local)}
                    onDelete={() => setConfirmId(local.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Locales">
            <PageHeader
                title="Locales"
                subtitle="Gestión de locales o sucursales"
                actions={<Button onClick={openCreate}>+ Nuevo Local</Button>}
            />

            <Table
                data={locales}
                columns={columns}
                searchPlaceholder="Buscar local..."
                emptyMessage="No hay locales registrados"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Local' : 'Nuevo Local'}
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear local'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    <Select
                        label="Empresa"
                        required
                        options={empresaOptions}
                        value={data.empresa_id}
                        onChange={(value) => setData('empresa_id', value as string)}
                        placeholder="Seleccione empresa"
                        error={errors.empresa_id}
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Nombre"
                            required
                            value={data.nombre}
                            onChange={e => setData('nombre', e.target.value)}
                            error={errors.nombre}
                        />
                        <Select
                            label="Tipo"
                            options={TIPO_OPTIONS}
                            value={data.tipo}
                            onChange={(value) => setData('tipo', value as string)}
                            placeholder="Seleccione tipo"
                            error={errors.tipo}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Dirección"
                            value={data.direccion}
                            onChange={e => setData('direccion', e.target.value)}
                            error={errors.direccion}
                        />
                        <Input
                            label="Teléfono"
                            value={data.telefono}
                            onChange={e => setData('telefono', e.target.value)}
                            error={errors.telefono}
                        />
                    </div>

                    <div className="border-t pt-4" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                            Operación de este local <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>(opcional, sobrescribe configuración de empresa)</span>
                        </p>

                        <div className="grid grid-cols-2 gap-4">
                            <Select
                                label="Descontar stock al vender"
                                options={[
                                    { value: 'heredar', label: `Heredar (${hintHerenciaStock})` },
                                    { value: 'si',      label: 'Sí descontar' },
                                    { value: 'no',      label: 'No descontar' },
                                ]}
                                value={data.descuenta_stock_en_venta}
                                onChange={(v) => setData('descuenta_stock_en_venta', v as TriBool)}
                            />

                            <Select
                                label="Modo de cierre de caja"
                                options={[
                                    { value: 'heredar',           label: `Heredar (${hintHerenciaCierre})` },
                                    { value: 'rapido',            label: 'Rápido' },
                                    { value: 'con_declaraciones', label: 'Con declaraciones' },
                                ]}
                                value={data.modo_cierre_caja}
                                onChange={(v) => setData('modo_cierre_caja', v as ModoCierreSel)}
                            />

                            <Select
                                label="Modo de cierre de inventario"
                                options={[
                                    { value: 'heredar',   label: `Heredar (${hintHerenciaInv})` },
                                    { value: 'por_venta', label: 'Por venta (descuento automático)' },
                                    { value: 'declarado', label: 'Declarado (cierre obligatorio)' },
                                ]}
                                value={data.modo_cierre_inventario}
                                onChange={(v) => setData('modo_cierre_inventario', v as ModoInvSel)}
                            />

                            <Select
                                label="Pide fondos iniciales"
                                options={[
                                    { value: 'heredar', label: `Heredar (${hintHerenciaFondos})` },
                                    { value: 'si',      label: 'Sí — pide al abrir y cerrar' },
                                    { value: 'no',      label: 'No — no pide' },
                                ]}
                                value={data.usa_fondos_iniciales}
                                onChange={(v) => setData('usa_fondos_iniciales', v as TriBool)}
                            />

                            <Select
                                label="Fondos en declaración del cierre"
                                options={[
                                    { value: 'heredar', label: `Heredar (${hintHerenciaFondosDecl})` },
                                    { value: 'si',      label: 'Sí — sumar al monto esperado' },
                                    { value: 'no',      label: 'No — quedan aparte' },
                                ]}
                                value={data.fondos_iniciales_en_declaracion}
                                onChange={(v) => setData('fondos_iniciales_en_declaracion', v as TriBool)}
                            />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                        <Checkbox
                            name="activo"
                            checked={data.activo}
                            onChange={e => setData('activo', e.target.checked)}
                        />
                        Local activo
                    </label>
                </form>
            </Modal>

            {/* Confirm Delete */}
            <Modal
                isOpen={confirmId !== null}
                onClose={() => setConfirmId(null)}
                title="Confirmar eliminación"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmId(null)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => confirmId && destroy(confirmId)}>Eliminar</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    ¿Estás seguro de que deseas eliminar este local? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}
