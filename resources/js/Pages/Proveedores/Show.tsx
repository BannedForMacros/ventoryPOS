import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';

interface Proveedor {
    id: number;
    tipo_documento: string;
    numero_documento: string | null;
    razon_social: string | null;
    nombre_comercial: string | null;
    contacto: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    observacion: string | null;
    activo: boolean;
}

interface Entrada {
    id: number;
    fecha: string;
    numero_documento: string | null;
    estado: string;
    total: string;
}

interface Props extends PageProps {
    proveedor: Proveedor;
    entradas: Entrada[];
}

export default function ProveedorShow({ proveedor, entradas }: Props) {
    return (
        <AppLayout title={`Proveedor: ${proveedor.razon_social ?? '—'}`}>
            <PageHeader
                title={proveedor.razon_social ?? proveedor.nombre_comercial ?? 'Sin nombre'}
                subtitle={`${proveedor.tipo_documento} ${proveedor.numero_documento ?? ''}`}
                backHref={route('proveedores.index')}
            />

            <div className="space-y-6 max-w-5xl">
                <section className="rounded-2xl border p-5 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <Field label="Documento" value={`${proveedor.tipo_documento} ${proveedor.numero_documento ?? ''}`} />
                    <Field label="Razón social" value={proveedor.razon_social} />
                    <Field label="Nombre comercial" value={proveedor.nombre_comercial} />
                    <Field label="Contacto" value={proveedor.contacto} />
                    <Field label="Teléfono" value={proveedor.telefono} />
                    <Field label="Email" value={proveedor.email} />
                    <Field label="Dirección" value={proveedor.direccion} />
                    <div>
                        <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Estado</p>
                        <Badge variant={proveedor.activo ? 'success' : 'secondary'}>
                            {proveedor.activo ? 'Activo' : 'Inactivo'}
                        </Badge>
                    </div>
                    {proveedor.observacion && (
                        <div className="col-span-2 md:col-span-3">
                            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>Observación</p>
                            <p className="text-sm" style={{ color: 'var(--color-text)' }}>{proveedor.observacion}</p>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border p-5"
                    style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <h3 className="text-sm font-semibold mb-3" style={{ color: 'var(--color-text)' }}>Últimas entradas</h3>
                    {entradas.length === 0 ? (
                        <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Sin compras registradas con este proveedor.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead style={{ color: 'var(--color-text-muted)' }}>
                                <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                    <th className="text-left py-2 px-2 font-medium">Fecha</th>
                                    <th className="text-left py-2 px-2 font-medium">N° Doc</th>
                                    <th className="text-left py-2 px-2 font-medium">Estado</th>
                                    <th className="text-right py-2 px-2 font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entradas.map(e => (
                                    <tr key={e.id} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                                        <td className="py-2 px-2">{e.fecha}</td>
                                        <td className="py-2 px-2 font-mono text-xs">{e.numero_documento ?? '—'}</td>
                                        <td className="py-2 px-2">
                                            <Badge variant={e.estado === 'confirmado' ? 'success' : 'warning'}>{e.estado}</Badge>
                                        </td>
                                        <td className="py-2 px-2 text-right font-mono">S/ {Number(e.total).toFixed(2)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            <p className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                {value ?? <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
            </p>
        </div>
    );
}
