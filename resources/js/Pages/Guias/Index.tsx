import { useEffect } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Truck, Plus, Eye, RefreshCw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import Table, { Column } from '@/Components/UI/Table';
import type { PageProps } from '@/types';

/**
 * Las guías de remisión emitidas.
 *
 * La columna que de verdad importa es la última del estado: «Puede salir». El nombre
 * del estado no responde solo a la pregunta que se hace quien tiene el camión
 * cargado delante, y por eso se pinta aparte y en verde.
 */

interface Fila extends Record<string, unknown> {
    id: number;
    numero: string;
    fecha_traslado: string | null;
    destinatario: string;
    llegada: string | null;
    estado: string;
    estado_label: string;
    estado_color: string;
    puede_trasladar: boolean;
    reintentable: boolean;
    venta: string | null;
}

interface Props extends PageProps {
    guias: { data: Fila[]; total: number; current_page: number; last_page: number; links: any[] };
    filtros: { buscar?: string; estado?: string };
    emision: { disponible: boolean; motivo: string | null; ensayo: string | null };
}

const tono = (color: string) =>
    ({ green: 'success', blue: 'info', red: 'danger', orange: 'warning', gray: 'neutral' }[color] ?? 'neutral') as any;

export default function GuiasIndex() {
    const { guias, filtros, emision, flash } = usePage<Props>().props as any;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const columnas: Column<Fila>[] = [
        {
            key: 'numero',
            label: 'Número',
            render: (g) => (
                <Link href={route('guias.show', g.id)} className="font-mono font-semibold text-[var(--color-primary)] hover:underline">
                    {g.numero}
                </Link>
            ),
        },
        {
            key: 'destinatario',
            label: 'Destinatario / Llegada',
            render: (g) => (
                <div>
                    <p className="text-sm font-medium">{g.destinatario}</p>
                    <p className="text-xs text-[var(--color-text-muted)]">{g.llegada ?? '—'}</p>
                </div>
            ),
        },
        { key: 'venta', label: 'Venta', render: (g) => g.venta ?? '—' },
        { key: 'fecha_traslado', label: 'Traslado' },
        {
            key: 'estado',
            label: 'Estado',
            render: (g) => (
                <div className="space-y-1">
                    <Badge variant={tono(g.estado_color)}>{g.estado_label}</Badge>
                    {/* La única pregunta que importa en el almacén. */}
                    {g.puede_trasladar && (
                        <p className="flex items-center gap-1 text-xs font-medium text-green-700">
                            <Truck size={12} /> Puede salir
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'acciones',
            label: '',
            sortable: false,
            align: 'right',
            render: (g) => (
                <div className="flex justify-end gap-1">
                    <Link href={route('guias.show', g.id)} title="Ver guía">
                        <Button variant="ghost" size="sm" iconOnly><Eye size={15} /></Button>
                    </Link>
                    {g.reintentable && (
                        <Button
                            variant="ghost"
                            size="sm"
                            iconOnly
                            title="Reintentar envío"
                            onClick={() => router.post(route('guias.reintentar', g.id), {}, { preserveScroll: true })}
                        >
                            <RefreshCw size={15} />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Guías de remisión">
            <PageHeader
                title="Guías de remisión"
                subtitle={`${guias.total} guía${guias.total === 1 ? '' : 's'} en total`}
                icon={<Truck size={20} />}
                actions={
                    <Link href={route('guias.create')}>
                        <Button startContent={<Plus size={16} />} disabled={!emision.disponible}>Nueva guía</Button>
                    </Link>
                }
            />

            <div className="space-y-4">
                {/* Un impedimento se pinta en ámbar y desactiva el botón; el ensayo, en
                    azul y sin bloquear. Son cosas distintas y no pueden verse igual. */}
                {!emision.disponible && emision.motivo && (
                    <Callout variant="warning" title="No se pueden emitir guías todavía">
                        {emision.motivo}
                    </Callout>
                )}

                {emision.disponible && emision.ensayo && (
                    <Callout variant="info">{emision.ensayo}</Callout>
                )}

                <Table
                    data={guias.data}
                    columns={columnas}
                    searchable
                    searchPlaceholder="Número, destinatario o referencia…"
                    initialSearch={filtros.buscar ?? ''}
                    onServerSearch={(t) => router.get(route('guias.index'), { buscar: t }, { preserveState: true, replace: true })}
                    pagination={false}
                    emptyMessage="Todavía no has emitido ninguna guía."
                />

                {guias.last_page > 1 && (
                    <div className="flex items-center justify-between rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3">
                        <p className="text-sm text-[var(--color-text-muted)]">
                            Página {guias.current_page} de {guias.last_page} · {guias.total} guías
                        </p>
                        <div className="flex gap-1">
                            {guias.links.map((l: any, i: number) => (
                                <Link
                                    key={i}
                                    href={l.url ?? '#'}
                                    preserveState
                                    className={`rounded-lg px-3 py-1.5 text-sm font-medium ${l.active ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-text-muted)] hover:bg-[var(--color-surface-2)]'} ${!l.url ? 'pointer-events-none opacity-30' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: l.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
