import { useEffect, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
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
type ModoApertura = 'libre' | 'arrastre' | 'fondo_fijo';

type FormData = {
    razon_social: string;
    nombre_comercial: string;
    ruc: string;
    direccion: string;
    telefono: string;
    email: string;
    modo_almacen: 'simple' | 'central_y_local';
    descuenta_stock_en_venta: boolean;
    permite_stock_negativo: boolean;
    tasa_igv: number | '';
    modo_cierre_caja: ModoCierre;
    modo_cierre_inventario: ModoInventario;
    cierre_precarga_stock: boolean;
    usa_fondos_iniciales: boolean;
    fondos_iniciales_en_declaracion: boolean;
    requiere_consolidacion_caja: boolean;
    permite_devoluciones: boolean;
    dias_max_devolucion: number | '';
    requiere_aprobacion_devolucion: boolean;
    restock_default: boolean;
    // Manejo de efectivo (opt-in por empresa)
    modo_apertura_caja: ModoApertura;
    apertura_editable: boolean;
    usa_retiros_caja: boolean;
    retiro_requiere_aprobacion: boolean;
    cierre_pregunta_destino: boolean;
    usa_caja_grande: boolean;
    usa_mercaderia_transito: boolean;
    vende_mercaderia_transito: boolean;
    // "Afecta caja" por módulo: solo se persiste el flag activo.
    afecta_caja_config: Record<string, { activo: boolean }>;
    activo: boolean;
    logo: File | null;
    // Plantilla del ticket impreso (se guarda como empresas.ticket_config).
    ticket_cliente_celular: boolean;
    ticket_cliente_direccion: boolean;
    ticket_mostrar_ruc: boolean;
    ticket_logo_escala: number;
    ticket_pie: string;
    ticket_lineas_extra: string;
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
    permite_stock_negativo: false,
    tasa_igv: 18,
    modo_cierre_caja: 'con_declaraciones',
    modo_cierre_inventario: 'por_venta',
    cierre_precarga_stock: false,
    usa_fondos_iniciales: true,
    fondos_iniciales_en_declaracion: false,
    requiere_consolidacion_caja: false,
    permite_devoluciones: true,
    dias_max_devolucion: 0,
    requiere_aprobacion_devolucion: false,
    restock_default: true,
    modo_apertura_caja: 'libre',
    apertura_editable: true,
    usa_retiros_caja: false,
    retiro_requiere_aprobacion: true,
    cierre_pregunta_destino: false,
    usa_caja_grande: false,
    usa_mercaderia_transito: false,
    vende_mercaderia_transito: false,
    afecta_caja_config: {},
    activo: true,
    logo: null,
    ticket_cliente_celular: true,
    ticket_cliente_direccion: true,
    ticket_mostrar_ruc: true,
    ticket_logo_escala: 100,
    ticket_pie: '',
    ticket_lineas_extra: '',
};

export default function Empresas({ empresas }: Props) {
    const { flash } = usePage<Props>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Empresa | null>(null);

    const { data, setData, post, transform, processing, errors, reset } = useForm<FormData>(emptyForm);
    // Preview local del logo elegido (antes de subirlo).
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

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
            permite_stock_negativo: emp.permite_stock_negativo ?? false,
            tasa_igv: emp.tasa_igv != null ? Number(emp.tasa_igv) : 18,
            modo_cierre_caja: (emp.modo_cierre_caja as ModoCierre) ?? 'con_declaraciones',
            modo_cierre_inventario: (emp.modo_cierre_inventario as ModoInventario) ?? 'por_venta',
            cierre_precarga_stock: emp.cierre_precarga_stock ?? false,
            usa_fondos_iniciales: emp.usa_fondos_iniciales ?? true,
            fondos_iniciales_en_declaracion: emp.fondos_iniciales_en_declaracion ?? false,
            requiere_consolidacion_caja: emp.requiere_consolidacion_caja ?? false,
            permite_devoluciones: emp.permite_devoluciones ?? true,
            dias_max_devolucion: emp.dias_max_devolucion ?? 0,
            requiere_aprobacion_devolucion: emp.requiere_aprobacion_devolucion ?? false,
            restock_default: emp.restock_default ?? true,
            modo_apertura_caja: (emp.modo_apertura_caja as ModoApertura) ?? 'libre',
            apertura_editable: emp.apertura_editable ?? true,
            usa_retiros_caja: emp.usa_retiros_caja ?? false,
            retiro_requiere_aprobacion: emp.retiro_requiere_aprobacion ?? true,
            cierre_pregunta_destino: emp.cierre_pregunta_destino ?? false,
            usa_caja_grande: emp.usa_caja_grande ?? false,
            usa_mercaderia_transito: emp.usa_mercaderia_transito ?? false,
            vende_mercaderia_transito: emp.vende_mercaderia_transito ?? false,
            // Del mapa resuelto (label/disponible/activo) tomamos solo `activo`.
            afecta_caja_config: Object.fromEntries(
                Object.entries(emp.afecta_caja ?? {}).map(([k, v]) => [k, { activo: v.activo }]),
            ),
            activo: emp.activo,
            logo: null,
            ticket_cliente_celular: emp.ticket_config?.cliente_celular ?? true,
            ticket_cliente_direccion: emp.ticket_config?.cliente_direccion ?? true,
            ticket_mostrar_ruc: emp.ticket_config?.mostrar_ruc ?? true,
            ticket_logo_escala: emp.ticket_config?.logo_escala ?? 100,
            ticket_pie: emp.ticket_config?.pie ?? '',
            ticket_lineas_extra: (emp.ticket_config?.lineas_extra ?? []).join('\n'),
        });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!editing) return;
        // Subir archivo con PUT requiere POST + method spoofing (limitación PHP).
        transform((d) => ({ ...d, _method: 'put' }));
        post(route('configuracion.empresas.update', editing.id), {
            forceFormData: true,
            onSuccess: () => { setModalOpen(false); reset(); setLogoPreview(null); transform((d) => d); },
        });
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
            sortable: false,
            render: (emp) => (
                <TableActions
                    onEdit={() => openEdit(emp)}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Empresas">
            <PageHeader
                title="Empresas"
                subtitle="Configuración de tu empresa"
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
                title="Editar Empresa"
                size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancelar</Button>
                        <Button loading={processing} onClick={submit}>
                            Guardar cambios
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

                    {/* Logo de la empresa (PNG/JPG). Se sube un archivo; no se usa URL. */}
                    <div>
                        <p className="text-sm font-medium mb-2" style={{ color: 'var(--color-text)' }}>Logo</p>
                        <div className="flex items-center gap-4">
                            {(logoPreview || (editing as { logo_url?: string | null } | null)?.logo_url) ? (
                                <img
                                    src={logoPreview ?? (editing as { logo_url?: string | null }).logo_url!}
                                    alt="Logo"
                                    className="h-16 w-16 rounded-lg object-contain border"
                                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)' }}
                                />
                            ) : (
                                <div className="h-16 w-16 rounded-lg border flex items-center justify-center text-[10px] text-center"
                                    style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)', backgroundColor: 'var(--color-bg)' }}>
                                    Sin logo
                                </div>
                            )}
                            <div className="flex-1">
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg"
                                    onChange={e => {
                                        const file = e.target.files?.[0] ?? null;
                                        setData('logo', file);
                                        setLogoPreview(file ? URL.createObjectURL(file) : null);
                                    }}
                                    className="block w-full text-sm"
                                    style={{ color: 'var(--color-text)' }}
                                />
                                <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                    PNG o JPG, máximo 2 MB. Si no eliges uno, se conserva el actual.
                                </p>
                                {errors.logo && <p className="text-xs mt-1" style={{ color: 'var(--color-danger)' }}>{errors.logo}</p>}
                            </div>
                        </div>
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

                        <label className="flex items-start gap-2 cursor-pointer mt-3">
                            <Checkbox
                                checked={data.permite_stock_negativo}
                                onChange={e => setData('permite_stock_negativo', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Permitir vender con stock negativo</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Si está activo, el POS deja vender aunque no alcance el stock: el saldo queda en negativo y al cerrar la caja se avisa qué productos quedaron así. Si está inactivo, la venta se bloquea cuando el stock no alcanza.
                                </span>
                            </span>
                        </label>
                        {errors.permite_stock_negativo && (
                            <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.permite_stock_negativo}</p>
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

                        {/* Modo de carga del formulario de cierre */}
                        <label className="flex items-start gap-2 cursor-pointer mt-4">
                            <Checkbox checked={data.cierre_precarga_stock} onChange={e => setData('cierre_precarga_stock', e.target.checked)} />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Cierre lógico (precargar stock del sistema)</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Activado: al cerrar inventario cada producto viene cargado con el stock del sistema; corriges solo los que difieren (parcial).
                                    Desactivado: todos salen vacíos y debes declarar la cantidad de todos los productos (conteo total).
                                </span>
                            </span>
                        </label>
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

                    {/* ── Sección: Manejo de efectivo ── */}
                    <div className="border-t pt-4 space-y-3" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Manejo de efectivo</p>

                        <div>
                            <p className="text-sm font-medium mb-2" style={{ color: 'var(--color-text)' }}>Modo de apertura de caja</p>
                            <div className="flex flex-col gap-2">
                                {([
                                    { value: 'libre' as const,      label: 'Libre',      hint: 'La cajera digita el monto con el que abre — comportamiento clásico.' },
                                    { value: 'arrastre' as const,   label: 'Arrastre',   hint: 'Se precarga con el efectivo que quedó al cierre anterior de esa misma caja.' },
                                    { value: 'fondo_fijo' as const, label: 'Fondo fijo', hint: 'Cada caja abre siempre con su fondo configurado (se define en Configuración → Cajas).' },
                                ]).map(opt => (
                                    <label key={opt.value} className="flex items-start gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="modo_apertura_caja"
                                            checked={data.modo_apertura_caja === opt.value}
                                            onChange={() => setData('modo_apertura_caja', opt.value)}
                                            className="mt-0.5 accent-[var(--color-primary)]"
                                        />
                                        <span>
                                            <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{opt.label}</span>
                                            <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{opt.hint}</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                            {errors.modo_apertura_caja && (
                                <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.modo_apertura_caja}</p>
                            )}
                        </div>

                        {data.modo_apertura_caja !== 'libre' && (
                            <label className="flex items-start gap-2 cursor-pointer pl-6">
                                <Checkbox
                                    checked={data.apertura_editable}
                                    onChange={e => setData('apertura_editable', e.target.checked)}
                                />
                                <span>
                                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Apertura editable</span>
                                    <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                        Si se desactiva, la cajera no puede cambiar el monto sugerido al abrir. Si está activo y lo cambia, la edición queda auditada.
                                    </span>
                                </span>
                            </label>
                        )}

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.usa_retiros_caja}
                                onChange={e => setData('usa_retiros_caja', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Retiros de caja</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Permite registrar "Entrega a administración" durante el turno. No son gastos: restan del efectivo esperado de la caja.
                                </span>
                            </span>
                        </label>

                        {data.usa_retiros_caja && (
                            <label className="flex items-start gap-2 cursor-pointer pl-6">
                                <Checkbox
                                    checked={data.retiro_requiere_aprobacion}
                                    onChange={e => setData('retiro_requiere_aprobacion', e.target.checked)}
                                />
                                <span>
                                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Retiro requiere aprobación de administrador</span>
                                    <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                        Los retiros quedan marcados como pendientes hasta que un administrador los apruebe.
                                    </span>
                                </span>
                            </label>
                        )}

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.cierre_pregunta_destino}
                                onChange={e => setData('cierre_pregunta_destino', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Preguntar destino del efectivo al cerrar turno</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Al cerrar, la cajera indica si el efectivo queda en caja para el siguiente turno o se entrega a administración.
                                </span>
                            </span>
                        </label>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.usa_caja_grande}
                                onChange={e => setData('usa_caja_grande', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Caja Grande</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Muestra en el Balance el desglose del efectivo: en cajas (puntos de venta) vs custodia de administración.
                                </span>
                            </span>
                        </label>
                    </div>

                    {/* ── Sección: Mercadería en tránsito (opt-in) ── */}
                    <div className="border-t pt-4 space-y-3" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Mercadería en tránsito</p>
                        <p className="text-xs -mt-2" style={{ color: 'var(--color-text-muted)' }}>
                            Para negocios que compran a un almacén central o distribuidor y reciben la mercadería
                            días después de que se la facturan.
                        </p>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.usa_mercaderia_transito}
                                onChange={e => setData('usa_mercaderia_transito', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Registrar compras que aún no llegan</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Agrega el estado "En camino" a las Entradas, con fecha estimada de llegada. El stock
                                    no entra hasta que marques la recepción, pero la deuda al proveedor ya cuenta si quedó saldo.
                                </span>
                            </span>
                        </label>

                        {data.usa_mercaderia_transito && (
                            <label className="flex items-start gap-2 cursor-pointer pl-6">
                                <Checkbox
                                    checked={data.vende_mercaderia_transito}
                                    onChange={e => setData('vende_mercaderia_transito', e.target.checked)}
                                />
                                <span>
                                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Permitir vender lo que viene en camino</span>
                                    <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                        El POS deja vender por encima del stock, pero solo hasta lo que realmente está en
                                        camino, y la línea queda como entrega pendiente al cliente. Es la alternativa
                                        controlada a permitir stock negativo en todo el catálogo.
                                    </span>
                                </span>
                            </label>
                        )}
                    </div>

                    {/* ── Sección: "Afecta caja" por módulo (opt-in) ── */}
                    <div className="border-t pt-4 space-y-3" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Afecta caja por módulo</p>
                        <p className="text-xs -mt-2" style={{ color: 'var(--color-text-muted)' }}>
                            Controla en qué módulos aparece el selector "Afecta caja a (turno)". Si lo apagas, ese
                            módulo deja de descontar/sumar al efectivo esperado de la caja y el selector desaparece.
                        </p>

                        {Object.entries(editing?.afecta_caja ?? {}).map(([key, meta]) => (
                            <label key={key} className={`flex items-start gap-2 ${meta.disponible ? 'cursor-pointer' : 'opacity-60'}`}>
                                <Checkbox
                                    checked={data.afecta_caja_config[key]?.activo ?? meta.activo}
                                    disabled={!meta.disponible}
                                    onChange={e => setData('afecta_caja_config', {
                                        ...data.afecta_caja_config,
                                        [key]: { activo: e.target.checked },
                                    })}
                                />
                                <span>
                                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                        {meta.label}
                                        {!meta.disponible && (
                                            <Badge className="ml-2" variant="primary">Próximamente</Badge>
                                        )}
                                    </span>
                                    <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                        {meta.disponible
                                            ? 'El movimiento en efectivo de este módulo puede afectar la caja de un turno.'
                                            : 'Aún usa su selector propio; pronto se controlará desde aquí.'}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>

                    {/* ── Sección: Ticket de venta (plantilla de impresión) ── */}
                    <div className="border-t pt-4 space-y-3" style={{ borderColor: 'var(--color-border)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Ticket de venta</p>
                        <p className="text-xs -mt-2" style={{ color: 'var(--color-text-muted)' }}>
                            Controla qué se imprime en la ticketera. Los cambios aplican de inmediato, sin actualizar el programa de impresión de las cajas.
                        </p>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.ticket_cliente_celular}
                                onChange={e => setData('ticket_cliente_celular', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Imprimir celular del cliente</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Sale como "Celular:" debajo del nombre, solo si el cliente tiene teléfono registrado.
                                </span>
                            </span>
                        </label>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.ticket_cliente_direccion}
                                onChange={e => setData('ticket_cliente_direccion', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Imprimir dirección del cliente</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Sale como "Direc:" debajo del nombre, solo si el cliente tiene dirección registrada.
                                </span>
                            </span>
                        </label>

                        <label className="flex items-start gap-2 cursor-pointer">
                            <Checkbox
                                checked={data.ticket_mostrar_ruc}
                                onChange={e => setData('ticket_mostrar_ruc', e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>Mostrar RUC del negocio en el ticket</span>
                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                    Apagarlo oculta el RUC de la cabecera en todos los tickets de esta empresa.
                                </span>
                            </span>
                        </label>

                        <div>
                            <label className="text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>
                                Escala del logo en el ticket (%)
                            </label>
                            <input type="number" min={10} max={200}
                                value={data.ticket_logo_escala}
                                onChange={e => setData('ticket_logo_escala', Math.max(10, Math.min(200, Number(e.target.value) || 100)))}
                                placeholder="100"
                                className="w-full rounded-xl border px-3 py-2 text-sm"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                            <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                100% = ancho máximo del papel. Bajarlo para logos que salen muy grandes.
                            </p>
                            {errors.ticket_logo_escala && <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.ticket_logo_escala}</p>}
                        </div>

                        <div>
                            <label className="text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>
                                Texto del pie del ticket
                            </label>
                            <input type="text" maxLength={500}
                                value={data.ticket_pie}
                                onChange={e => setData('ticket_pie', e.target.value)}
                                placeholder="Gracias por su preferencia"
                                className="w-full rounded-xl border px-3 py-2 text-sm"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                            {errors.ticket_pie && <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.ticket_pie}</p>}
                        </div>

                        <div>
                            <label className="text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>
                                Líneas extra al final (una por renglón)
                            </label>
                            <textarea rows={3} maxLength={500}
                                value={data.ticket_lineas_extra}
                                onChange={e => setData('ticket_lineas_extra', e.target.value)}
                                placeholder={'Yape/Plin: 999 999 999\nSíguenos: @minegocio'}
                                className="w-full rounded-xl border px-3 py-2 text-sm"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                            <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                                Se imprimen centradas al final de todos los tickets (venta, cotización y entrega de anticipo).
                            </p>
                            {errors.ticket_lineas_extra && <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{errors.ticket_lineas_extra}</p>}
                        </div>
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
        </AppLayout>
    );
}