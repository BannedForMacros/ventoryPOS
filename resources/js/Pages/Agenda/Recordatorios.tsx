import { router, usePage, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import toast from 'react-hot-toast';
import {
    MessageCircle, CheckCircle2, PhoneOff, ChevronLeft, ChevronRight, Calendar, BellRing,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import type { PageProps } from '@/types';
import { fechaLocal } from '@/lib/fechas';

/**
 * A quién falta avisarle de su cita.
 *
 * Es el ritual de fin de jornada: "mañana vienen estas ocho personas, ¿a quién
 * no le he avisado?". Sin esta pantalla había que abrir cita por cita para
 * saberlo, y el plantón —la silla vacía que ya no se recupera— es la mayor
 * pérdida de estos negocios.
 */

interface Fila {
    id: number;
    numero: string;
    hora: string | null;
    cliente: string;
    telefono: string | null;
    profesional: string | null;
    servicios: string;
    estado: string;
    recordado_at: string | null;
    /** null cuando el cliente no tiene un teléfono utilizable. */
    url: string | null;
}

interface Props extends PageProps {
    citas: Fila[];
    fecha: string;
}

const tituloFecha = (iso: string) =>
    new Date(iso + 'T00:00:00').toLocaleDateString('es-PE', {
        weekday: 'long', day: '2-digit', month: 'long',
    });

export default function Recordatorios({ citas, fecha }: Props) {
    const { flash } = usePage<Props>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function irA(delta: number) {
        const d = new Date(fecha + 'T00:00:00');
        d.setDate(d.getDate() + delta);
        router.get(route('agenda.recordatorios'), { fecha: fechaLocal(d) }, { preserveState: true });
    }

    /**
     * Abre WhatsApp y deja constancia. La ventana se abre PRIMERO y dentro del
     * clic: cualquier navegador bloquea un `window.open` que llegue después de
     * una respuesta del servidor.
     */
    function recordar(fila: Fila) {
        if (!fila.url) {
            toast.error(`${fila.cliente} no tiene teléfono registrado. Agrégaselo en su ficha.`);
            return;
        }
        window.open(fila.url, '_blank', 'noopener');
        router.post(route('agenda.recordatorio', fila.id), {}, { preserveScroll: true });
    }

    const pendientes = citas.filter(c => !c.recordado_at);
    const sinTelefono = citas.filter(c => !c.url);

    return (
        <AppLayout title="Recordatorios">
            <PageHeader
                title="Recordatorios"
                subtitle="A quién falta avisarle de su cita"
                backHref={route('agenda.index')}
            />

            <div className="max-w-4xl mx-auto">
                <div className="flex items-center gap-2 mb-4">
                    <Button variant="ghost" size="sm" onClick={() => irA(-1)} startContent={<ChevronLeft size={14} />}>
                        Anterior
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => irA(1)} endContent={<ChevronRight size={14} />}>
                        Siguiente
                    </Button>
                    <h2 className="text-lg font-semibold ml-2 capitalize" style={{ color: 'var(--color-text)' }}>
                        {tituloFecha(fecha)}
                    </h2>
                </div>

                {citas.length > 0 && (
                    <Callout
                        variant={pendientes.length > 0 ? 'warning' : 'success'}
                        title={pendientes.length > 0
                            ? `Faltan ${pendientes.length} de ${citas.length} por avisar`
                            : 'Ya se les avisó a todos'}
                        className="mb-4"
                    >
                        {pendientes.length > 0
                            ? 'El mensaje se abre en tu WhatsApp con el texto ya escrito; solo tienes que enviarlo.'
                            : 'Todas las citas de este día tienen su recordatorio enviado.'}
                        {sinTelefono.length > 0 && (
                            <span className="block mt-1">
                                {sinTelefono.length === 1
                                    ? '1 cliente no tiene teléfono registrado y no se le puede avisar.'
                                    : `${sinTelefono.length} clientes no tienen teléfono registrado y no se les puede avisar.`}
                            </span>
                        )}
                    </Callout>
                )}

                {citas.length === 0 ? (
                    <div className="rounded-2xl border p-12 text-center"
                        style={{ backgroundColor: 'var(--color-surface)', borderColor: 'var(--color-border)' }}>
                        <Calendar size={40} className="mx-auto opacity-30 mb-3" style={{ color: 'var(--color-text-muted)' }} />
                        <p className="font-medium" style={{ color: 'var(--color-text)' }}>
                            No hay citas ese día.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {citas.map(c => (
                            <div key={c.id}
                                className="rounded-xl border p-3 flex items-center gap-3 flex-wrap"
                                style={{
                                    backgroundColor: 'var(--color-surface)',
                                    borderColor: c.recordado_at
                                        ? 'color-mix(in srgb, var(--color-success) 35%, var(--color-border))'
                                        : 'var(--color-border)',
                                }}
                            >
                                <div className="font-mono text-sm font-bold tabular-nums w-12 flex-shrink-0"
                                    style={{ color: 'var(--color-primary)' }}>
                                    {c.hora}
                                </div>

                                <div className="min-w-0 flex-1">
                                    <Link href={route('agenda.show', c.id)} className="hover:underline">
                                        <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                            {c.cliente}
                                        </span>
                                    </Link>
                                    <div className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>
                                        {c.servicios}{c.profesional ? ` · ${c.profesional}` : ''}
                                    </div>
                                </div>

                                {/* El estado de aviso se dice con palabras, no solo con color:
                                    quien mira esto necesita decidir a quién escribir. */}
                                {c.recordado_at ? (
                                    <Badge variant="success">
                                        <CheckCircle2 size={11} className="inline mr-1" />Avisado
                                    </Badge>
                                ) : !c.url ? (
                                    <Badge variant="secondary">
                                        <PhoneOff size={11} className="inline mr-1" />Sin teléfono
                                    </Badge>
                                ) : (
                                    <Badge variant="warning">
                                        <BellRing size={11} className="inline mr-1" />Falta avisar
                                    </Badge>
                                )}

                                <Button
                                    size="sm"
                                    variant={c.recordado_at ? 'ghost' : 'success'}
                                    startContent={<MessageCircle size={14} />}
                                    onClick={() => recordar(c)}
                                    disabled={!c.url}
                                    title={c.url ? 'Abre WhatsApp con el mensaje escrito' : 'Este cliente no tiene teléfono'}
                                >
                                    {c.recordado_at ? 'Otra vez' : 'Recordar'}
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
