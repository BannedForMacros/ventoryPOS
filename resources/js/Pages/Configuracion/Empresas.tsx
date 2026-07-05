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
import Checkbox from '@/Components/UI/Checkbox';
import TableActions from '@/Components/UI/TableActions';
import type { Empresa, PageProps } from '@/types';

interface Props extends PageProps {
    empresas: Empresa[];
}

type ModoCierre = 'rapido' | 'con_declaraciones';
type ModoInventario = 'por_venta' | 'declarado';

type FormData = {
    razon_social: string;
    nombre_comercial: string;
    ruc: string;
    direccion: string;
    telefono: string;
    email: string;
    modo_almacen: 'simple' | 'central_y_local';
    descuenta_stock_en_venta: boolean;
    tasa_igv: number | '';
    modo_cierre_caja: ModoCierre;
    modo_cierre_inventario: ModoInventario;
    usa_fondos_iniciales: boolean;
    fondos_iniciales_en_declaracion: boolean;
    requiere_consolidacion_caja: boolean;
    permite_devoluciones: boolean;
    dias_max_devolucion: number | '';
    requiere_aprobacion_devolucion: boolean;
    restock_default: boolean;
    activo: boolean;
};

const emptyForm: FormData = {
    razon_social: '',
    nombre_comercial: '',
    ruc: '',
    direccion: '',
    telefono: '',
    email: '',
    modo_almacen: 'simple',
    descuenta_stock_en_venta: true,
    tasa_igv: 18,
    modo_cierre_caja: 'con_declaraciones',
    modo_cierre_inventario: 'por_venta',
    usa_fondos_iniciales: true,
    fondos_iniciales_en_declaracion: false,
    requiere_consolidacion_caja: false,
    permite_devoluciones: true,
    dias_max_devolucion: 0,
    requiere_aprobacion_devolucion: false,
    restock_default: true,
    activo: true,
};

export default function Empresas({ empresas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmId, setConfirmId] = useState<number | null>(null);
    const [editing, setEditing] = useState<Empresa | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm<FormData>(emptyForm);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    function openCreate() {
        setEditing(null);
        reset();
        setModalOpen(true);
    }

    function openEdit(emp: Empresa) {
        setEditing(emp);
        setData({
            razon_social: emp.razon_social,
            nombre_comercial: emp.nombre_comercial ?? '',
            ruc: emp.ruc,
            direccion: emp.direccion ?? '',
            telefono: emp.telefono ?? '',
            email: emp.email ?? '',
            modo_almacen: emp.modo_almacen,
            descuenta_stock_en_venta: emp.descuenta_stock_en_venta ?? true,
            tasa_igv: emp.tasa_igv != null ? Number(emp.tasa_igv) : 18,
            modo_cierre_caja: (emp.modo_cierre_caja as ModoCierre) ?? 'con_declaraciones',
            modo_cierre_inventario: (emp.modo_cierre_inventario as ModoInventario) ?? 'por_venta',
            usa_fondos_iniciales: emp.usa_fondos_iniciales ?? true,
            fondos_iniciales_en_declaracion: emp.fondos_iniciales_en_declaracion ?? false,
            requiere_consolidacion_caja: emp.requiere_consolidacion_caja ?? false,
            permite_devoluciones: emp.permite_devoluciones ?? true,
            dias_max_devolucion: emp.dias_max_devolucion ?? 0,
            requiere_aprobacion_devolucion: emp.requiere_aprobacion_devolucion ?? false,
            restock_default: emp.restock_default ?? true,
            activo: emp.activo,
        });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            put(route('configuracion.empresas.update', editing.id), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        } else {
            post(route('configuracion.empresas.store'), {
                onSuccess: () => { setModalOpen(false); reset(); },
            });
        }
    }

    function destroy(id: number) {
        setConfirmId(null);
        router.delete(route('configuracion.empresas.destroy', id));
    }

    const columns: Column<Empresa>[] = [
        {
            key: 'ruc',
            label: 'RUC',
            sortable: true,
            render: (emp) => (
                <span className="font-mono text-xs">{emp.ruc}</span>
            ),
        },
        {
            key: 'razon_social',
            label: 'Razón Social',
            sortable: true,
            render: (emp) => (
                <span className="font-medium">{emp.razon_social}</span>
            ),
        },
        {
            key: 'nombre_comercial',
            label: 'Nombre Comercial',
            sortable: true,
            render: (emp) => emp.nombre_comercial ?? '—',
        },
        {
            key: 'activo',
            label: 'Estado',
            sortable: true,
            render: (emp) => (
                <Badge variant={emp.activo ? 'success' : 'secondary'}>
                    {emp.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (emp) => (
                <TableActions
                    onEdit={() => openEdit(emp)}
                    onDelete={() => setConfirmId(emp.id)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Empresas">
            <PageHeader
                title="Empresas"
                subtitle="Gestión de empresas registradas"
                actions={<Button onClick={openCreate}>+ Nueva Empresa</Button>}
            />

            <Table
                data={empresas}
                columns={columns}
                searchPlaceholder="Buscar empresa..."
                emptyMessage="No hay empresas registradas"
            />

            {/* Form Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Editar Empresa' : 'Nueva Empresa'}
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            {editing ? 'Guardar cambios' : 'Crear empresa'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Razón Social" required value={data.razon_social} onChange={e => setData('razon_social', e.target.value)} error={errors.razon_social} />
                        <Input label="Nombre Comercial" value={data.nombre_comercial} onChange={e => setData('nombre_comercial', e.target.value)} error={errors.nombre_comercial} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="RUC" required maxLength={11} value={data.ruc} onChange={e => setData('ruc', e.target.value)} error={errors.ruc} />
                        <Input label="Email" type="email" value={data.email} onChange={e => setData('email', e.target.value)} error={errors.email} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Dirección" value={data.direccion} onChange={e => setData('direccion', e.target.value)} error={errors.direccion} />
                        <Input label="Teléfono" value={data.telefono} onChange={e => setData('telefono', e.target.value)} error={errors.telefono} />
                    </div>
                    <div>
                        <p className="text-sm font-medium mb-2" style={{ color: 'var(--color-text)' }}>
                            Modo de almacén <span style={{ color: 'var(--color-danger)' }}>*</span>
                        </p>
                        <div className="flex flex-col gap-2">
                            {([
                                {
                                    value: 'simple' as const,
                                    label: 'Simple',
                                    hint: 'Un solo almacén central actúa como bodega y punto de venta. Ideal para negocios con una sola ubicación.',
                                },
                                {
                                    value: 'central_y_local' as const,
                                    label: 'Central y local',
                                    hint: 'Almacén central para compras/entradas y almacenes por local para ventas. Requiere transferencias entre almacenes.',
                                },
                            ]).map(opt => (
                                <label key={opt.value} className="flex items-start gap-2 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="modo_almacen"
                                        checked={data.modo_almacen === opt.value}
                                        onChange={() => setData('modo_almacen', opt.value)}
                                        className="mt-0.5 accent-[var(--color-primary)]"
                                    />
                                    <span>
                                        <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{opt.label}</span>
                                        <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{opt.hint}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {errors.modo_almacen && (
                            <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.modo_almacen}</p>
                        )}
                    </div>

                    {/* ── Sección: Stock ── */}
                    <div className="border-t pt-4" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Stock</p>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.descuenta_stock_en_venta}
                                onChange={e => setData('descuenta_stock_en_venta', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Descontar stock al vender</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Si está activo, cada venta descuenta el stock del producto. Cada local y cada producto puede sobrescribir esta configuración.
                                </span>
                            </span>
                        </label>
                        {errors.descuenta_stock_en_venta && (
                            <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.descuenta_stock_en_venta}</p>
                        )}
                    </div>

                    {/* ── Sección: Impuestos (IGV) ── */}
                    <div className="border-t pt-4" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Impuestos</p>

                        <label className="block">
                            <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Tasa de IGV (%)</span>
                            <span className="block text-xs mt-0.5 mb-1" style={{ color: 'var(--color-text-muted)' }}>
                                Aplica solo a productos marcados como "incluye IGV". 0 = empresa exenta (RUS, comercio inafecto). Default Perú: 18.
                            </span>
                            <input
                                type="number"
                                step="0.01"
                                min={0}
                                max={30}
                                value={data.tasa_igv}
                                onChange={e => setData('tasa_igv', e.target.value === '' ? '' : Number(e.target.value))}
                                className="w-32 rounded border px-2 py-1 text-sm"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}
                            />
                        </label>
                        {errors.tasa_igv && (
                            <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.tasa_igv}</p>
                        )}
                    </div>

                    {/* ── Sección: Cierre de caja ── */}
                    <div className="border-t pt-4" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                            Cierre de caja <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>(por turno)</span>
                        </p>
                        <div className="flex flex-col gap-2">
                            {([
                                { value: 'rapido' as const,            label: 'Rápido',            hint: 'Solo cierra el turno. Sin arqueo ni declaraciones.' },
                                { value: 'con_declaraciones' as const, label: 'Con declaraciones', hint: 'Arqueo de efectivo (denominaciones) + totales por método de pago.' },
                            ]).map(opt => (
                                <label key={opt.value} className="flex items-start gap-2 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="modo_cierre_caja"
                                        checked={data.modo_cierre_caja === opt.value}
                                        onChange={() => setData('modo_cierre_caja', opt.value)}
                                        className="mt-0.5 accent-[var(--color-primary)]"
                                    />
                                    <span>
                                        <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{opt.label}</span>
                                        <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{opt.hint}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {errors.modo_cierre_caja && (
                            <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.modo_cierre_caja}</p>
                        )}
                    </div>

                    {/* ── Sección: Cierre de inventario ── */}
                    <div className="border-t pt-4" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>
                            Cierre de inventario <span className="text-xs font-normal" style={{ color: 'var(--color-text-muted)' }}>(por turno)</span>
                        </p>
                        <div className="flex flex-col gap-2">
                            {([
                                { value: 'por_venta' as const, label: 'Por venta', hint: 'El stock se descuenta automáticamente con cada venta. Sin declaración al cerrar turno.' },
                                { value: 'declarado' as const, label: 'Declarado', hint: 'Al cerrar turno se exige un cierre de inventario confirmado: el cajero declara stock real y se registran diferencias.' },
                            ]).map(opt => (
                                <label key={opt.value} className="flex items-start gap-2 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="modo_cierre_inventario"
                                        checked={data.modo_cierre_inventario === opt.value}
                                        onChange={() => setData('modo_cierre_inventario', opt.value)}
                                        className="mt-0.5 accent-[var(--color-primary)]"
                                    />
                                    <span>
                                        <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{opt.label}</span>
                                        <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{opt.hint}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {errors.modo_cierre_inventario && (
                            <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.modo_cierre_inventario}</p>
                        )}
                    </div>

                    {/* ── Sección: Devoluciones ── */}
                    <div className="border-t pt-4 space-y-3" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Devoluciones</p>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox checked={data.permite_devoluciones} onChange={e => setData('permite_devoluciones', e.target.checked)} />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Permite devoluciones</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Si está activo, los cajeros pueden registrar devoluciones de ventas.
                                </span>
                            </span>
                        </label>

                        {data.permite_devoluciones && (
                            <>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>
                                            Días máximos para devolver (0 = sin límite)
                                        </label>
                                        <input type="number" min="0" max="365"
                                            value={data.dias_max_devolucion}
                                            onChange={e => setData('dias_max_devolucion', e.target.value === '' ? '' : Number(e.target.value))}
                                            className="w-full rounded-xl border px-3 py-2 text-sm"
                                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                                    </div>
                                </div>

                                <label className="flex items-start gap-2 cursor-pointer">
                                    <Checkbox checked={data.requiere_aprobacion_devolucion} onChange={e => setData('requiere_aprobacion_devolucion', e.target.checked)} />
                                    <span>
                                        <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Requiere aprobación de administrador</span>
                                        <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                            Si está activo, las devoluciones quedan en estado "pendiente" hasta que un admin las apruebe.
                                        </span>
                                    </span>
                                </label>

                                <label className="flex items-start gap-2 cursor-pointer">
                                    <Checkbox checked={data.restock_default} onChange={e => setData('restock_default', e.target.checked)} />
                                    <span>
                                        <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Restock automático por defecto</span>
                                        <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                            Por defecto, los productos devueltos vuelven al stock. El cajero puede sobrescribir por línea.
                                        </span>
                                    </span>
                                </label>
                            </>
                        )}
                    </div>

                    {/* ── Sección: Fondos iniciales ── */}
                    <div className="border-t pt-4 space-y-3" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Fondos iniciales (caja chica)</p>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.usa_fondos_iniciales}
                                onChange={e => setData('usa_fondos_iniciales', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Pedir fondos iniciales al abrir/cerrar turno</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Si está activo, al abrir el turno se pide el monto que se entrega como caja chica para vueltos. Al cerrar se solicitará devolverlo.
                                </span>
                            </span>
                        </label>

                        {data.usa_fondos_iniciales && (
                            <label className="flex items-start gap-2 cursor-pointer pl-6">
                                <Checkbox
                                    checked={data.fondos_iniciales_en_declaracion}
                                    onChange={e => setData('fondos_iniciales_en_declaracion', e.target.checked)}
                                />
                                <span>
                                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Incluir fondos iniciales en la declaración del cierre</span>
                                    <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                        Si está activo, los fondos iniciales se SUMAN al monto esperado del arqueo (el cajero declara el efectivo total incluyendo los fondos). Si no, los fondos quedan aparte y solo se cuentan las ventas.
                                    </span>
                                </span>
                            </label>
                        )}
                    </div>

                    {/* ── Sección: Consolidación de caja ── */}
                    <div className="border-t pt-4 space-y-3" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Consolidación de caja</p>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.requiere_consolidacion_caja}
                                onChange={e => setData('requiere_consolidacion_caja', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Requiere consolidación de caja para el balance diario</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Si está activo, tras el cierre de la cajera un supervisor debe contar el efectivo (Finanzas → Consolidación de caja) y SU conteo es el que asienta el sobrante/faltante en tesorería y alimenta el balance. Si está inactivo, el conteo de la cajera al cierre es el que manda.
                                </span>
                            </span>
                        </label>
                    </div>

                    <label className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--color-text)' }}>
                        <Checkbox
                            name="activo"
                            checked={data.activo}
                            onChange={e => setData('activo', e.target.checked)}
                        />
                        Empresa activa
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
                    ¿Estás seguro de que deseas eliminar esta empresa? Esta acción no se puede deshacer.
                </p>
            </Modal>
        </AppLayout>
    );
}