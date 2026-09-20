import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { Truck, RefreshCw, CheckCircle2, Clock, AlertTriangle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import type { PageProps } from '@/types';

/**
 * Una guía.
 *
 * Lo primero y lo más grande es el semáforo, porque la pregunta que trae aquí a
 * cualquiera es una sola: ¿puede salir el camión? El nombre del estado no la
 * responde por sí mismo, así que se dice con todas las letras.
 *
 * Mientras SUNAT no conteste, la pantalla se refresca sola: quien está esperando
 * para cargar no tiene por qué estar pulsando F5.
 */

const tono = (c: string) =>
    ({ green: 'success', blue: 'info', red: 'danger', orange: 'warning', gray: 'neutral' }[c] ?? 'neutral') as any;

const Dato = ({ k, v }: { k: string; v: React.ReactNode }) => (
    <div className="flex gap-3 py-1 text-sm">
        <span className="w-40 shrink-0 font-medium text-[var(--color-text-muted)]">{k}</span>
        <span>{v ?? '—'}</span>
    </div>
);

export default function GuiaShow() {
    const { guia, flash } = usePage<PageProps & { guia: any }>().props as any;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    useEffect(() => {
        if (!guia.espera_respuesta) return;
        const t = setInterval(() => router.reload({ only: ['guia'] }), 15000);
        return () => clearInterval(t);
    }, [guia.espera_respuesta]);

    const traslado = guia.payload?.traslado ?? {};
    const chofer = traslado.conductores?.[0];

    return (
        <AppLayout title={guia.numero ?? 'Guía'}>
            <PageHeader
                title={<span className="font-mono">{guia.numero ?? 'Sin número'}</span>}
                subtitle="Guía de remisión remitente"
                icon={<Truck size={20} />}
                backHref={route('guias.index')}
                actions={
                    guia.reintentable && (
                        <Button
                            variant="secondary"
                            startContent={<RefreshCw size={16} />}
                            onClick={() => router.post(route('guias.reintentar', guia.id))}
                        >
                            Reintentar
                        </Button>
                    )
                }
            />

            <div className="space-y-4">
                {/* El semáforo. Lo primero que se lee. */}
                <Callout
                    variant={guia.puede_trasladar ? 'success' : guia.espera_respuesta ? 'info' : 'danger'}
                    title={
                        <span className="flex items-center gap-2">
                            {guia.puede_trasladar ? <CheckCircle2 size={18} />
                                : guia.espera_respuesta ? <Clock size={18} /> : <AlertTriangle size={18} />}
                            {guia.estado_label}
                            <Badge variant={tono(guia.estado_color)}>{guia.estado}</Badge>
                        </span>
                    }
                >
                    <p>{guia.aviso ?? (guia.puede_trasladar ? 'La mercadería puede salir.' : 'La mercadería no puede salir con esta guía.')}</p>
                    {guia.sunat_codigo && (
                        <p className="mt-1 text-xs">SUNAT [{guia.sunat_codigo}] {guia.sunat_descripcion}</p>
                    )}
                    {guia.error && <p className="mt-1 text-xs">{guia.error}</p>}
                </Callout>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                        <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Traslado</h2>
                        <Dato k="Motivo" v={guia.motivo} />
                        <Dato k="Modalidad" v={guia.modalidad === 'PUBLICO' ? 'Transporte público' : 'Transporte privado'} />
                        <Dato k="Inicio del traslado" v={guia.fecha_traslado} />
                        <Dato k="Peso bruto" v={traslado.peso_total ? `${traslado.peso_total} ${traslado.unidad_peso ?? ''}` : null} />
                        <Dato k="Bultos" v={traslado.numero_bultos} />
                        {guia.venta_id && <Dato k="Venta" v={`#${guia.venta_id}`} />}
                    </section>

                    <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                        <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Destino</h2>
                        <Dato k="Destinatario" v={guia.destinatario} />
                        <Dato k="Dirección" v={guia.llegada_direccion} />
                        <Dato k="Ubigeo" v={traslado.llegada?.ubigeo} />
                        <Dato k="Sale de" v={traslado.partida?.direccion} />
                    </section>
                </div>

                <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                    <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">
                        {traslado.transportista ? 'Transportista' : 'Vehículo y conductor'}
                    </h2>
                    {traslado.transportista ? (
                        <>
                            <Dato k="Razón social" v={traslado.transportista.razon_social} />
                            <Dato k="RUC" v={traslado.transportista.ruc} />
                        </>
                    ) : traslado.vehiculo_menor ? (
                        <p className="py-1 text-sm text-[var(--color-text-muted)]">
                            Traslado en moto o auto particular. SUNAT no exige declarar placa ni conductor.
                        </p>
                    ) : (
                        <>
                            <Dato k="Placa" v={<span className="font-mono font-semibold">{traslado.vehiculo?.placa}</span>} />
                            {chofer && (
                                <>
                                    <Dato k="Conductor" v={`${chofer.nombres} ${chofer.apellidos}`} />
                                    <Dato k="Licencia" v={<span className="font-mono">{chofer.licencia}</span>} />
                                </>
                            )}
                        </>
                    )}
                </section>

                <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                    <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Mercadería</h2>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-[var(--color-border)] text-xs uppercase text-[var(--color-text-muted)]">
                                <th className="py-2 text-left font-medium">Descripción</th>
                                <th className="py-2 text-center font-medium">Unidad</th>
                                <th className="py-2 text-right font-medium">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(guia.payload?.items ?? []).map((i: any, n: number) => (
                                <tr key={n} className="border-b border-[var(--color-border)]/40">
                                    <td className="py-2">{i.descripcion}</td>
                                    <td className="py-2 text-center text-[var(--color-text-muted)]">{i.unidad}</td>
                                    <td className="py-2 text-right tabular-nums">{i.cantidad}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {/* Que nadie busque importes: no los lleva. */}
                    <p className="mt-2 text-xs text-[var(--color-text-muted)]">
                        Una guía de remisión no lleva precios: es un documento de movimiento, no de valor.
                    </p>
                </section>
            </div>
        </AppLayout>
    );
}
