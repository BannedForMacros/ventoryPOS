import { Link } from '@inertiajs/react';
import { ArrowLeft, ShoppingBag } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import type { PageProps } from '@/types';

interface Cliente {
    id:               number;
    tipo_documento:   string;
    numero_documento: string | null;
    nombres:          string | null;
    apellidos:        string | null;
    razon_social:     string | null;
    telefono:         string | null;
    email:            string | null;
    direccion:        string | null;
    fecha_nacimiento: string | null;
    activo:           boolean;
    nombre_completo:  string;
    es_cliente_general: boolean;
}

interface Props extends PageProps {
    cliente: Cliente;
    compras: unknown[];
}

function Campo({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide mb-0.5" style={{ color: 'var(--color-text-muted)' }}>
                {label}
            </p>
            <p className="text-sm" style={{ color: value ? 'var(--color-text)' : 'var(--color-text-muted)' }}>
                {value || '—'}
            </p>
        </div>
    );
}

export default function ClienteShow({ cliente }: Props) {
    return (
        <AppLayout title={cliente.nombre_completo}>
            <PageHeader
                title={cliente.nombre_completo}
                subtitle={`${cliente.tipo_documento}${cliente.numero_documento ? ` · ${cliente.numero_documento}` : ''}`}
                actions={
                    <Link href={route('clientes.index')}>
                        <Button variant="ghost">
                            <ArrowLeft size={15} className="mr-1" />Volver
                        </Button>
                    </Link>
                }
            />

            <div className="space-y-4">
                {/* Datos principales */}
                <div className="rounded-lg p-5 space-y-4" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>Datos del cliente</h3>
                        <Badge variant={cliente.activo ? 'success' : 'secondary'}>
                            {cliente.activo ? 'Activo' : 'Inactivo'}
                        </Badge>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <Campo label="Tipo documento"   value={cliente.tipo_documento} />
                        <Campo label="Número documento" value={cliente.numero_documento} />
                        {cliente.razon_social ? (
                            <Campo label="Razón social" value={cliente.razon_social} />
                        ) : (
                            <>
                                <Campo label="Nombres"   value={cliente.nombres} />
                                <Campo label="Apellidos" value={cliente.apellidos} />
                            </>
                        )}
                        <Campo label="Teléfono" value={cliente.telefono} />
                        <Campo label="Email"    value={cliente.email} />
                        <Campo label="Dirección" value={cliente.direccion} />
                        {!cliente.razon_social && (
                            <Campo label="Fecha de nacimiento" value={cliente.fecha_nacimiento} />
                        )}
                    </div>
                </div>

                {/* Historial de compras */}
                <div className="rounded-lg p-5" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    <h3 className="text-sm font-semibold mb-4" style={{ color: 'var(--color-text)' }}>Historial de compras</h3>
                    <div className="flex flex-col items-center justify-center py-10 gap-2" style={{ color: 'var(--color-text-muted)' }}>
                        <ShoppingBag size={36} strokeWidth={1.5} />
                        <p className="text-sm">Sin compras registradas aún</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
